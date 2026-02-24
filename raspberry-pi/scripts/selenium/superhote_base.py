#!/usr/bin/env python3
# coding: utf-8
"""
Module de base pour l'automatisation Superhote avec Selenium.
Fournit les fonctions de connexion, navigation et mise a jour des prix.
"""

import os
import sys
import time
import logging
import configparser
from datetime import datetime, timedelta
from typing import Optional, Dict, List, Tuple
from pathlib import Path

# Selenium imports
from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import (
    TimeoutException,
    NoSuchElementException,
    ElementClickInterceptedException,
    StaleElementReferenceException
)

# Configuration du logging
LOG_DIR = Path(__file__).parent.parent.parent / "logs"
LOG_DIR.mkdir(exist_ok=True)

logging.basicConfig(
    filename=str(LOG_DIR / "superhote_automation.log"),
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s"
)
logger = logging.getLogger(__name__)


# ============================================================================
# RETRY UTILITIES - Pour la stabilite du systeme
# ============================================================================
import functools
import random

def retry_on_exception(max_attempts=3, base_delay=1.0, backoff=2.0, jitter=0.5,
                       exceptions=(TimeoutException, StaleElementReferenceException)):
    """
    Decorateur pour retry avec exponential backoff.

    Args:
        max_attempts: Nombre max de tentatives
        base_delay: Delai initial en secondes
        backoff: Multiplicateur pour exponential backoff
        jitter: Variation aleatoire (0-1) pour eviter thundering herd
        exceptions: Tuple d'exceptions a intercepter
    """
    def decorator(func):
        @functools.wraps(func)
        def wrapper(*args, **kwargs):
            last_exception = None
            for attempt in range(max_attempts):
                try:
                    return func(*args, **kwargs)
                except exceptions as e:
                    last_exception = e
                    if attempt < max_attempts - 1:
                        delay = base_delay * (backoff ** attempt)
                        delay += random.uniform(0, jitter * delay)
                        logger.warning(f"{func.__name__} - Tentative {attempt + 1}/{max_attempts} echouee: {e}. Retry dans {delay:.1f}s")
                        time.sleep(delay)
                    else:
                        logger.error(f"{func.__name__} - Echec apres {max_attempts} tentatives: {e}")
            raise last_exception
        return wrapper
    return decorator


def safe_find_element(driver, by, selector, timeout=10, description="element"):
    """
    Trouve un element de maniere securisee avec gestion d'erreur explicite.

    Returns:
        Element trouve ou None si non trouve
    """
    try:
        element = WebDriverWait(driver, timeout).until(
            EC.presence_of_element_located((by, selector))
        )
        return element
    except TimeoutException:
        logger.debug(f"Element non trouve apres {timeout}s: {description} ({selector})")
        return None
    except NoSuchElementException:
        logger.debug(f"Element inexistant: {description} ({selector})")
        return None
    except StaleElementReferenceException:
        logger.debug(f"Element stale: {description} ({selector})")
        return None


def safe_click(driver, element, description="element", use_js=False):
    """
    Clique sur un element de maniere securisee.

    Returns:
        True si clic reussi, False sinon
    """
    try:
        if use_js:
            driver.execute_script("arguments[0].click();", element)
        else:
            element.click()
        return True
    except ElementClickInterceptedException as e:
        logger.warning(f"Clic intercepte sur {description}: {e}")
        # Retry avec JS
        try:
            driver.execute_script("arguments[0].click();", element)
            return True
        except Exception as e2:
            logger.error(f"Clic JS echoue sur {description}: {e2}")
            return False
    except StaleElementReferenceException as e:
        logger.warning(f"Element stale lors du clic sur {description}: {e}")
        return False
    except Exception as e:
        logger.error(f"Erreur clic sur {description}: {type(e).__name__}: {e}")
        return False


def get_memory_usage_mb():
    """
    Retourne l'utilisation memoire du processus actuel en MB.
    Utile pour detecter les fuites memoire sur Raspberry Pi.
    """
    try:
        import resource
        # getrusage retourne la memoire en KB sur Linux
        usage = resource.getrusage(resource.RUSAGE_SELF)
        return usage.ru_maxrss / 1024  # Convertir en MB
    except Exception:
        return 0


def check_system_memory():
    """
    Verifie la memoire systeme disponible.
    Retourne (total_mb, available_mb, percent_used)
    """
    try:
        with open('/proc/meminfo', 'r') as f:
            meminfo = {}
            for line in f:
                parts = line.split()
                if len(parts) >= 2:
                    meminfo[parts[0].rstrip(':')] = int(parts[1])

            total_mb = meminfo.get('MemTotal', 0) / 1024
            available_mb = meminfo.get('MemAvailable', meminfo.get('MemFree', 0)) / 1024
            percent_used = ((total_mb - available_mb) / total_mb * 100) if total_mb > 0 else 0
            return total_mb, available_mb, percent_used
    except Exception:
        return 0, 0, 0


def log_memory_status(context=""):
    """
    Log le statut memoire actuel.
    """
    total, available, percent = check_system_memory()
    process_mb = get_memory_usage_mb()
    if total > 0:
        logger.info(f"Memoire {context}: systeme {percent:.1f}% utilise ({available:.0f}MB libre), processus {process_mb:.0f}MB")
        if percent > 85:
            logger.warning(f"ATTENTION: Memoire systeme critique ({percent:.1f}% utilise)")
        return percent > 90  # Retourne True si memoire critique
    return False


def cleanup_orphan_chromium_processes(max_age_minutes=30):
    """
    Tue les processus Chromium orphelins ou trop vieux.

    Args:
        max_age_minutes: Age maximum en minutes (defaut: 30)

    Criteres pour tuer:
    - Orphelins: parent = init (ppid=1)
    - Trop vieux: running depuis plus de max_age_minutes
    """
    try:
        import subprocess
        import os

        our_pid = os.getpid()
        killed = 0

        # Recuperer tous les processus avec leur age
        result = subprocess.run(
            ["ps", "-eo", "pid,ppid,etimes,args"],
            capture_output=True,
            text=True,
            timeout=5
        )

        if result.returncode != 0:
            return 0

        for line in result.stdout.strip().split('\n')[1:]:  # Skip header
            parts = line.split(None, 3)
            if len(parts) < 4:
                continue

            try:
                pid = int(parts[0])
                ppid = int(parts[1])
                elapsed_seconds = int(parts[2])  # etimes = elapsed time in seconds
                cmd = parts[3]

                # Ignorer notre propre processus
                if pid == our_pid:
                    continue

                # Verifier si c'est un chromium headless
                if 'chromium' not in cmd.lower() and 'chrome' not in cmd.lower():
                    continue
                if '--headless' not in cmd:
                    continue

                should_kill = False
                reason = ""

                # Critere 1: Orphelin (ppid = 1)
                if ppid == 1:
                    should_kill = True
                    reason = "orphelin"

                # Critere 2: Trop vieux (> max_age_minutes)
                elif elapsed_seconds > max_age_minutes * 60:
                    should_kill = True
                    reason = f"trop vieux ({elapsed_seconds//60}min)"

                if should_kill:
                    os.kill(pid, 9)
                    killed += 1
                    logger.info(f"Cleanup: tue chromium PID {pid} ({reason})")

            except (ValueError, ProcessLookupError, PermissionError):
                continue

        if killed > 0:
            logger.info(f"Cleanup: {killed} processus Chromium termines au total")
        return killed

    except Exception as e:
        logger.debug(f"Cleanup Chromium: {e}")
    return 0


def wait_for_page_ready(driver, timeout=10):
    """
    Attend que la page soit completement chargee.
    Plus intelligent que time.sleep() car s'adapte a la vitesse de connexion.
    """
    try:
        # Attendre que le document soit ready
        WebDriverWait(driver, timeout).until(
            lambda d: d.execute_script("return document.readyState") == "complete"
        )
        # Attendre que jQuery soit idle (si present)
        try:
            WebDriverWait(driver, 2).until(
                lambda d: d.execute_script(
                    "return (typeof jQuery === 'undefined') || (jQuery.active === 0)"
                )
            )
        except TimeoutException:
            pass  # jQuery pas present ou timeout, on continue
        return True
    except TimeoutException:
        logger.warning(f"Page non ready apres {timeout}s")
        return False


def wait_for_element_stable(driver, by, selector, timeout=10, stability_time=0.5):
    """
    Attend qu'un element soit present ET stable (ne change plus de position).
    Utile pour les animations CSS.
    """
    try:
        element = WebDriverWait(driver, timeout).until(
            EC.presence_of_element_located((by, selector))
        )
        # Verifier la stabilite de position
        last_location = element.location
        stable_count = 0
        for _ in range(int(stability_time * 10)):
            time.sleep(0.1)
            try:
                current_location = element.location
                if current_location == last_location:
                    stable_count += 1
                    if stable_count >= 3:  # Stable pendant 0.3s
                        return element
                else:
                    stable_count = 0
                    last_location = current_location
            except StaleElementReferenceException:
                return None
        return element
    except TimeoutException:
        return None


def wait_and_click(driver, by, selector, timeout=10, description="element"):
    """
    Attend qu'un element soit cliquable puis clique dessus.
    Remplace: element = find(); time.sleep(1); element.click()
    """
    try:
        element = WebDriverWait(driver, timeout).until(
            EC.element_to_be_clickable((by, selector))
        )
        return safe_click(driver, element, description)
    except TimeoutException:
        logger.debug(f"Element non cliquable apres {timeout}s: {description}")
        return False
    except Exception as e:
        logger.debug(f"Erreur wait_and_click {description}: {type(e).__name__}")
        return False


class SuperhoteAutomation:
    """
    Classe principale pour l'automatisation de Superhote via Selenium.
    Gere la connexion, la navigation et la mise a jour des prix.
    """

    def __init__(self, config_path: Optional[str] = None):
        """
        Initialise l'automatisation Superhote.

        Args:
            config_path: Chemin vers le fichier de configuration.
                        Si None, utilise le fichier par defaut.
        """
        self.config = configparser.ConfigParser()

        if config_path is None:
            config_path = Path(__file__).parent / "config_superhote.ini"

        self.config.read(config_path)

        # Charger la config principale pour la BDD si necessaire
        if self.config.getboolean("DATABASE", "use_main_config", fallback=True):
            main_config_path = Path(__file__).parent.parent.parent / "config" / "config.ini"
            self.main_config = configparser.ConfigParser()
            self.main_config.read(main_config_path)
        else:
            self.main_config = self.config

        self.driver: Optional[webdriver.Chrome] = None
        self.wait: Optional[WebDriverWait] = None
        self.is_logged_in = False

        # URLs Superhote
        self.login_url = self.config.get("SUPERHOTE", "login_url",
                                          fallback="https://app.superhote.com/login")
        self.dashboard_url = self.config.get("SUPERHOTE", "dashboard_url",
                                              fallback="https://app.superhote.com/dashboard")

        # Credentials
        self.email = self.config.get("SUPERHOTE", "email", fallback="")
        self.password = self.config.get("SUPERHOTE", "password", fallback="")
        # Enlever les guillemets si presents (pour compatibilite avec caracteres speciaux)
        if self.password.startswith('"') and self.password.endswith('"'):
            self.password = self.password[1:-1]

        # Timeouts
        self.timeout = self.config.getint("SUPERHOTE", "timeout", fallback=30)
        self.page_load_timeout = self.config.getint("SUPERHOTE", "page_load_timeout", fallback=60)
        # Timeouts supplementaires pour Raspberry Pi
        self.element_timeout = self.config.getint("SUPERHOTE", "element_timeout", fallback=15)
        self.navigation_timeout = self.config.getint("SUPERHOTE", "navigation_timeout", fallback=20)
        self.action_delay = self.config.getfloat("SUPERHOTE", "action_delay", fallback=0.5)

        # Options de debug et nettoyage
        self.debug_screenshots = self.config.getboolean("SELENIUM", "debug_screenshots", fallback=False)
        self.cleanup_orphan_processes = self.config.getboolean("SELENIUM", "cleanup_orphan_processes", fallback=True)

        logger.info(f"SuperhoteAutomation initialisee (timeouts: element={self.element_timeout}s, nav={self.navigation_timeout}s, screenshots={self.debug_screenshots})")

    def _create_driver(self) -> webdriver.Chrome:
        """
        Cree et configure le driver Chrome/Chromium pour Selenium.

        Returns:
            Instance de webdriver.Chrome configuree
        """
        options = Options()

        # Mode headless
        if self.config.getboolean("SELENIUM", "headless", fallback=True):
            options.add_argument("--headless=new")

        # Options de securite et performance
        options.add_argument("--no-sandbox")
        options.add_argument("--disable-dev-shm-usage")
        options.add_argument("--disable-gpu")
        options.add_argument("--disable-extensions")
        options.add_argument("--disable-infobars")

        # Taille de fenetre
        width = self.config.getint("SELENIUM", "window_width", fallback=1920)
        height = self.config.getint("SELENIUM", "window_height", fallback=1080)
        options.add_argument(f"--window-size={width},{height}")

        # User agent realiste
        options.add_argument(
            "user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
            "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )

        # Desactiver les notifications
        options.add_experimental_option("prefs", {
            "profile.default_content_setting_values.notifications": 2
        })

        # Detecter Chromium sur Raspberry Pi / Linux ARM
        # Priorite: config > chemin absolu > shutil.which
        import shutil
        chromium_path = self.config.get("SELENIUM", "chromium_path", fallback="")

        if not chromium_path:
            # Verifier les chemins absolus communs
            chromium_candidates = [
                "/usr/bin/chromium-browser",
                "/usr/bin/chromium",
                "/snap/bin/chromium",
            ]
            for candidate in chromium_candidates:
                if os.path.exists(candidate) and os.access(candidate, os.X_OK):
                    chromium_path = candidate
                    break

        if not chromium_path:
            # Fallback: shutil.which
            chromium_path = shutil.which("chromium-browser") or shutil.which("chromium")

        if chromium_path:
            options.binary_location = chromium_path
            logger.info(f"Utilisation de Chromium: {chromium_path}")
        else:
            logger.warning("Chromium non trouve - le driver risque de ne pas demarrer")

        # Chemin vers chromedriver (config > auto-detection)
        chromedriver_path = self.config.get("SELENIUM", "chromedriver_path", fallback="")

        # Auto-detection du chromedriver systeme (priorite sur /usr/bin)
        if not chromedriver_path:
            # Priorite: /usr/bin/chromedriver (system), puis PATH
            if os.path.exists("/usr/bin/chromedriver"):
                chromedriver_path = "/usr/bin/chromedriver"
                logger.info(f"Auto-detection chromedriver systeme: {chromedriver_path}")
            else:
                # Chercher dans le PATH mais eviter celui de node
                for path_dir in os.environ.get("PATH", "").split(os.pathsep):
                    if "node" in path_dir.lower():
                        continue
                    candidate = os.path.join(path_dir, "chromedriver")
                    if os.path.exists(candidate) and os.access(candidate, os.X_OK):
                        chromedriver_path = candidate
                        logger.info(f"Auto-detection chromedriver: {chromedriver_path}")
                        break

        if chromedriver_path:
            service = Service(executable_path=chromedriver_path)
            driver = webdriver.Chrome(service=service, options=options)
        else:
            driver = webdriver.Chrome(options=options)

        driver.set_page_load_timeout(self.page_load_timeout)

        logger.info("Driver Chrome/Chromium cree avec succes")
        return driver

    def start(self) -> bool:
        """
        Demarre le driver Selenium.

        Returns:
            True si le driver a ete demarre avec succes
        """
        try:
            # Nettoyer les processus Chromium orphelins si configure
            if self.cleanup_orphan_processes:
                cleanup_orphan_chromium_processes()

            # Verifier la memoire avant de demarrer
            total, available, percent = check_system_memory()
            log_memory_status("avant demarrage driver")

            # Refuser de demarrer si memoire trop basse (< 150MB libre ou > 95%)
            if available < 150 or percent > 95:
                logger.error(f"Memoire insuffisante pour demarrer Chromium: {available:.0f}MB libre ({percent:.1f}% utilise)")
                logger.error("Attendre que de la memoire se libere ou arreter d'autres processus")
                return False

            if percent > 85:
                logger.warning("Memoire critique - le driver pourrait etre instable")

            self.driver = self._create_driver()
            self.wait = WebDriverWait(self.driver, self.timeout)
            logger.info("Driver Selenium demarre")

            # Log memoire apres demarrage
            log_memory_status("apres demarrage driver")
            return True
        except Exception as e:
            logger.error(f"Erreur au demarrage du driver: {e}")
            return False

    def stop(self):
        """Arrete le driver Selenium proprement."""
        if self.driver:
            try:
                log_memory_status("avant arret driver")
                self.driver.quit()
                logger.info("Driver Selenium arrete")
            except Exception as e:
                logger.error(f"Erreur a l'arret du driver: {e}")
            finally:
                self.driver = None
                self.wait = None
                self.is_logged_in = False
                # Forcer le garbage collection pour liberer la memoire
                import gc
                gc.collect()
                # Nettoyer les processus Chromium orphelins apres l'arret
                if self.cleanup_orphan_processes:
                    time.sleep(0.5)  # Laisser le temps au processus de se terminer
                    cleanup_orphan_chromium_processes()
                log_memory_status("apres arret driver")

    def __enter__(self):
        """Context manager: entree."""
        self.start()
        return self

    def __exit__(self, exc_type, exc_val, exc_tb):
        """Context manager: sortie."""
        self.stop()
        return False

    def login(self) -> bool:
        """
        Se connecte a Superhote avec les credentials configures.

        Returns:
            True si la connexion a reussi
        """
        if not self.driver:
            logger.error("Driver non initialise. Appelez start() d'abord.")
            return False

        if not self.email or not self.password:
            logger.error("Email ou mot de passe non configure")
            return False

        try:
            logger.info(f"Connexion a Superhote: {self.login_url}")
            self.driver.get(self.login_url)

            # Attendre que la page de login soit chargee
            wait_for_page_ready(self.driver, timeout=15)

            # Chercher le champ email (plusieurs selecteurs possibles)
            email_selectors = [
                (By.ID, "email"),
                (By.NAME, "email"),
                (By.CSS_SELECTOR, "input[type='email']"),
                (By.CSS_SELECTOR, "input[name='email']"),
                (By.XPATH, "//input[@type='email']"),
            ]

            email_field = None
            for by, selector in email_selectors:
                try:
                    email_field = self.wait.until(
                        EC.presence_of_element_located((by, selector))
                    )
                    if email_field:
                        break
                except TimeoutException:
                    continue

            if not email_field:
                logger.error("Champ email non trouve")
                return False

            # Entrer l'email
            email_field.clear()
            email_field.send_keys(self.email)
            logger.info("Email entre")

            # Chercher le champ mot de passe
            password_selectors = [
                (By.ID, "password"),
                (By.NAME, "password"),
                (By.CSS_SELECTOR, "input[type='password']"),
                (By.XPATH, "//input[@type='password']"),
            ]

            password_field = None
            for by, selector in password_selectors:
                try:
                    password_field = self.driver.find_element(by, selector)
                    if password_field:
                        break
                except NoSuchElementException:
                    continue

            if not password_field:
                logger.error("Champ mot de passe non trouve")
                return False

            # Entrer le mot de passe
            password_field.clear()
            password_field.send_keys(self.password)
            logger.info("Mot de passe entre")

            # Chercher et cliquer sur le bouton de connexion
            submit_selectors = [
                (By.CSS_SELECTOR, "button[type='submit']"),
                (By.XPATH, "//button[@type='submit']"),
                (By.XPATH, "//button[contains(text(), 'Connexion')]"),
                (By.XPATH, "//button[contains(text(), 'Se connecter')]"),
                (By.XPATH, "//input[@type='submit']"),
            ]

            submit_button = None
            for by, selector in submit_selectors:
                try:
                    submit_button = self.driver.find_element(by, selector)
                    if submit_button and submit_button.is_displayed():
                        break
                except NoSuchElementException:
                    continue

            if submit_button:
                submit_button.click()
            else:
                # Alternative: appuyer sur Entree
                password_field.send_keys(Keys.RETURN)

            logger.info("Formulaire de connexion soumis")

            # Attendre la redirection vers le dashboard (intelligent)
            try:
                WebDriverWait(self.driver, 15).until(
                    lambda d: "login" not in d.current_url.lower() or "dashboard" in d.current_url.lower()
                )
            except TimeoutException:
                pass  # Continuer pour verifier manuellement

            # Verifier si on est connecte (URL changee ou element specifique present)
            current_url = self.driver.current_url
            if "login" not in current_url.lower() or "dashboard" in current_url.lower():
                self.is_logged_in = True
                logger.info("Connexion reussie!")
                return True

            # Verifier s'il y a un message d'erreur
            error_selectors = [
                (By.CSS_SELECTOR, ".error-message"),
                (By.CSS_SELECTOR, ".alert-danger"),
                (By.CSS_SELECTOR, "[role='alert']"),
            ]

            for by, selector in error_selectors:
                try:
                    error_elem = self.driver.find_element(by, selector)
                    if error_elem and error_elem.is_displayed():
                        logger.error(f"Erreur de connexion: {error_elem.text}")
                        return False
                except NoSuchElementException:
                    continue

            # Si pas d'erreur visible, considerer la connexion comme reussie
            self.is_logged_in = True
            logger.info("Connexion probablement reussie (pas d'erreur detectee)")
            return True

        except TimeoutException as e:
            logger.error(f"Timeout lors de la connexion: {e}")
            return False
        except Exception as e:
            logger.error(f"Erreur lors de la connexion: {e}")
            return False

    def navigate_to_property(self, property_id: str) -> bool:
        """
        Navigue vers la page d'un logement specifique.

        Args:
            property_id: ID du logement sur Superhote

        Returns:
            True si la navigation a reussi
        """
        if not self.is_logged_in:
            logger.error("Non connecte. Appelez login() d'abord.")
            return False

        try:
            # URL de la page du logement
            property_url = f"https://app.superhote.com/properties/{property_id}"
            logger.info(f"Navigation vers le logement: {property_url}")

            self.driver.get(property_url)
            wait_for_page_ready(self.driver, timeout=15)

            # Verifier que la page s'est chargee
            if property_id in self.driver.current_url:
                logger.info(f"Navigation vers logement {property_id} reussie")
                return True
            else:
                logger.warning(f"URL inattendue: {self.driver.current_url}")
                return True  # Continuer quand meme

        except Exception as e:
            logger.error(f"Erreur navigation vers logement {property_id}: {e}")
            return False

    def navigate_to_calendar(self, property_id: str) -> bool:
        """
        Navigue vers le calendrier/tarifs d'un logement.

        Args:
            property_id: ID du logement sur Superhote

        Returns:
            True si la navigation a reussi
        """
        if not self.is_logged_in:
            logger.error("Non connecte. Appelez login() d'abord.")
            return False

        try:
            # URL du calendrier
            calendar_url = f"https://app.superhote.com/properties/{property_id}/calendar"
            logger.info(f"Navigation vers le calendrier: {calendar_url}")

            self.driver.get(calendar_url)
            wait_for_page_ready(self.driver, timeout=20)

            logger.info(f"Navigation vers calendrier {property_id} reussie")
            return True

        except Exception as e:
            logger.error(f"Erreur navigation vers calendrier {property_id}: {e}")
            return False

    def navigate_to_pricing(self, property_id: str) -> bool:
        """
        Navigue vers la page de tarification d'un logement.

        Args:
            property_id: ID du logement sur Superhote

        Returns:
            True si la navigation a reussi
        """
        if not self.is_logged_in:
            logger.error("Non connecte. Appelez login() d'abord.")
            return False

        try:
            # URL de la tarification (a ajuster selon l'interface Superhote)
            pricing_url = f"https://app.superhote.com/properties/{property_id}/pricing"
            logger.info(f"Navigation vers la tarification: {pricing_url}")

            self.driver.get(pricing_url)
            time.sleep(3)

            logger.info(f"Navigation vers tarification {property_id} reussie")
            return True

        except Exception as e:
            logger.error(f"Erreur navigation vers tarification {property_id}: {e}")
            return False

    def filter_by_property(self, property_name: str) -> bool:
        """
        Filtre le calendrier pour afficher UN SEUL logement.

        Workflow:
        1. Cliquer sur "Filtrer par hebergement"
        2. Cliquer sur "Effacer" pour tout decocher
        3. Taper le nom dans le champ de recherche
        4. Cocher le logement correspondant
        5. Cliquer "Sauvegarder"

        Args:
            property_name: Nom du logement (ex: "Delphin - ZEN - 1")

        Returns:
            True si le filtrage a reussi
        """
        try:
            # ETAPE 1: Cliquer sur "Filtrer par hebergement"
            filter_clicked = False
            try:
                filter_btn = self.driver.find_element(
                    By.XPATH,
                    "//button[contains(text(), 'Filtrer par')]"
                )
                self.driver.execute_script("arguments[0].click();", filter_btn)
                # Attendre que le dropdown s'ouvre
                try:
                    WebDriverWait(self.driver, 3).until(
                        EC.presence_of_element_located((By.CSS_SELECTOR, ".dropdown-menu.show, .dropdown.open"))
                    )
                except TimeoutException:
                    time.sleep(0.5)  # Fallback
                filter_clicked = True
                logger.info("Menu filtre ouvert")
            except (NoSuchElementException, TimeoutException, StaleElementReferenceException) as e:
                logger.debug(f"Bouton 'Filtrer par' non trouve: {type(e).__name__}")
                # Essayer avec d'autres selecteurs
                try:
                    filter_btn = self.driver.find_element(By.CSS_SELECTOR, "button.dropdown-toggle")
                    self.driver.execute_script("arguments[0].click();", filter_btn)
                    time.sleep(0.5)  # Court delai pour l'animation
                    filter_clicked = True
                except (NoSuchElementException, TimeoutException, StaleElementReferenceException) as e2:
                    logger.debug(f"Bouton dropdown non trouve: {type(e2).__name__}")

            if not filter_clicked:
                logger.warning("Bouton filtre non trouve")
                return False

            # ETAPE 2: Cliquer sur "Effacer" pour tout decocher
            time.sleep(0.5)
            try:
                effacer_link = self.driver.find_element(By.XPATH, "//a[contains(text(), 'Effacer')]")
                self.driver.execute_script("arguments[0].click();", effacer_link)
                time.sleep(0.5)
                logger.info("Liste effacee")
            except (NoSuchElementException, TimeoutException, StaleElementReferenceException):
                logger.info("Lien Effacer non trouve, on continue")

            # ETAPE 3: Taper le nom dans le champ de recherche
            search_input = None
            try:
                # Chercher le champ de recherche dans le dropdown
                search_input = self.driver.find_element(
                    By.CSS_SELECTOR,
                    "input[type='text'], input[type='search'], input[placeholder*='echerch']"
                )
                if search_input and search_input.is_displayed():
                    search_input.clear()
                    # Utiliser une partie du nom pour la recherche
                    search_term = property_name.split(' - ')[0] if ' - ' in property_name else property_name
                    search_input.send_keys(search_term)
                    time.sleep(1)
                    logger.info(f"Recherche: {search_term}")
            except (NoSuchElementException, TimeoutException, StaleElementReferenceException) as e:
                logger.info(f"Champ de recherche non trouve ({type(e).__name__}), on cherche directement")

            # ETAPE 4: Cocher le logement correspondant
            time.sleep(0.5)
            property_selected = False

            # Chercher la checkbox ou le label du logement
            try:
                # Chercher par le nom exact
                checkbox_label = self.driver.find_element(
                    By.XPATH,
                    f"//label[contains(text(), '{property_name}')] | //span[contains(text(), '{property_name}')]"
                )
                self.driver.execute_script("arguments[0].click();", checkbox_label)
                property_selected = True
                logger.info(f"Logement coche: {property_name}")
            except (NoSuchElementException, TimeoutException, StaleElementReferenceException) as e:
                logger.debug(f"Logement non trouve par nom exact ({type(e).__name__}), essai nom partiel")
                # Essayer avec une partie du nom
                try:
                    short_name = property_name.split(' - ')[-1] if ' - ' in property_name else property_name
                    checkbox_label = self.driver.find_element(
                        By.XPATH,
                        f"//*[contains(text(), '{short_name}')]"
                    )
                    self.driver.execute_script("arguments[0].click();", checkbox_label)
                    property_selected = True
                    logger.info(f"Logement coche (nom partiel): {short_name}")
                except (NoSuchElementException, TimeoutException, StaleElementReferenceException) as e2:
                    logger.warning(f"Logement non trouve: {property_name} ({type(e2).__name__})")

            # ETAPE 5: Cliquer sur "Sauvegarder"
            # IMPORTANT: Sauvegarder est un <a> pas un <button>!
            if property_selected:
                time.sleep(1)
                save_clicked = False

                # Methode 1: Chercher le lien <a> avec classe btn-red
                try:
                    save_link = self.driver.find_element(By.CSS_SELECTOR, "a.btn-red")
                    if save_link.is_displayed():
                        self.driver.execute_script("arguments[0].click();", save_link)
                        time.sleep(2)
                        save_clicked = True
                        logger.info("Filtre sauvegarde (lien btn-red)")
                except (NoSuchElementException, StaleElementReferenceException):
                    pass

                # Methode 2: Chercher par texte dans les liens
                if not save_clicked:
                    try:
                        save_link = self.driver.find_element(By.XPATH, "//a[contains(text(), 'Sauvegarder')]")
                        if save_link.is_displayed():
                            self.driver.execute_script("arguments[0].click();", save_link)
                            time.sleep(2)
                            save_clicked = True
                            logger.info("Filtre sauvegarde (lien texte)")
                    except (NoSuchElementException, StaleElementReferenceException):
                        pass

                # Methode 3: Chercher bouton ou lien avec classe btn-red
                if not save_clicked:
                    try:
                        elements = self.driver.find_elements(By.CSS_SELECTOR, ".btn-red, [class*='btn-red']")
                        for elem in elements:
                            if elem.is_displayed() and "sauv" in (elem.text or "").lower():
                                self.driver.execute_script("arguments[0].click();", elem)
                                time.sleep(2)
                                save_clicked = True
                                logger.info("Filtre sauvegarde (btn-red)")
                                break
                    except (NoSuchElementException, StaleElementReferenceException):
                        pass

                if not save_clicked:
                    logger.warning("Bouton Sauvegarder non trouve, fermeture du dropdown")
                    try:
                        self.driver.find_element(By.TAG_NAME, "body").click()
                        time.sleep(1)
                    except (NoSuchElementException, StaleElementReferenceException):
                        pass

            return property_selected

        except Exception as e:
            logger.error(f"Erreur filtrage par logement: {e}")
            return False

    def _click_date_in_picker(self, field, date_val: datetime, date_type: str):
        """
        Clique sur un champ de date et selectionne la date dans le picker.

        Args:
            field: Element WebDriver du champ de date
            date_val: Date a selectionner
            date_type: Type de date ("debut" ou "fin")
        """
        try:
            # Cliquer pour ouvrir le date picker
            self.driver.execute_script("arguments[0].click();", field)
            time.sleep(1)

            # Naviguer vers le bon mois si necessaire
            self._navigate_to_month(date_val)

            # Cliquer sur le jour
            day_to_click = date_val.day
            day_clicked = False

            # METHODE 0: Superhote specifique - vdp-datepicker
            # Le picker Superhote utilise .vdp-datepicker__calendar avec des spans pour les jours
            try:
                # Chercher dans le picker vdp visible
                picker_days = self.driver.find_elements(By.CSS_SELECTOR,
                    ".vdp-datepicker__calendar:not([style*='display: none']) span")
                for day_span in picker_days:
                    if day_span.is_displayed():
                        text = day_span.text.strip()
                        if text == str(day_to_click):
                            # Verifier que ce n'est pas disabled
                            parent = day_span.find_element(By.XPATH, "./..")
                            parent_class = parent.get_attribute("class") or ""
                            if "disabled" not in parent_class and "blank" not in parent_class:
                                self.driver.execute_script("arguments[0].click();", day_span)
                                logger.info(f"Date {date_type} selectionnee (vdp-datepicker): {date_val.strftime('%d/%m/%Y')}")
                                day_clicked = True
                                break
            except Exception as e:
                logger.debug(f"vdp-datepicker: {e}")

            # Methode 1: XPath avec texte exact (fallback)
            if not day_clicked:
                day_selectors = [
                    f"//td[normalize-space(text())='{day_to_click}']",
                    f"//td/span[normalize-space(text())='{day_to_click}']",
                    f"//td/div[normalize-space(text())='{day_to_click}']",
                    f"//td/a[normalize-space(text())='{day_to_click}']",
                    f"//*[contains(@class, 'day')][normalize-space(text())='{day_to_click}']",
                    f"//span[normalize-space(text())='{day_to_click}']"
                ]

                for selector in day_selectors:
                    try:
                        day_elements = self.driver.find_elements(By.XPATH, selector)
                        for day_elem in day_elements:
                            if day_elem.is_displayed():
                                # Eviter de cliquer sur les jours du mois precedent/suivant
                                elem_class = (day_elem.get_attribute("class") or "").lower()
                                parent_class = ""
                                try:
                                    parent = day_elem.find_element(By.XPATH, "./..")
                                    parent_class = (parent.get_attribute("class") or "").lower()
                                except (NoSuchElementException, StaleElementReferenceException):
                                    pass

                                # Skip si disabled/old/new
                                all_classes = elem_class + " " + parent_class
                                if any(skip in all_classes for skip in ["disabled", "old", "new", "off", "other-month"]):
                                    continue

                                self.driver.execute_script("arguments[0].click();", day_elem)
                                logger.info(f"Date {date_type} selectionnee (XPath): {date_val.strftime('%d/%m/%Y')}")
                                day_clicked = True
                                break
                    except Exception as e:
                        logger.debug(f"Selector {selector}: {e}")
                        continue
                    if day_clicked:
                        break

            # Methode 2: CSS selector avec le numero du jour
            if not day_clicked:
                try:
                    css_selectors = [
                        f"td.day:not(.disabled):not(.old):not(.new)",
                        f".datepicker-days td:not(.disabled)",
                        f".calendar td:not(.disabled)"
                    ]
                    for css_sel in css_selectors:
                        elements = self.driver.find_elements(By.CSS_SELECTOR, css_sel)
                        for elem in elements:
                            if elem.is_displayed() and elem.text.strip() == str(day_to_click):
                                self.driver.execute_script("arguments[0].click();", elem)
                                logger.info(f"Date {date_type} selectionnee (CSS): {date_val.strftime('%d/%m/%Y')}")
                                day_clicked = True
                                break
                        if day_clicked:
                            break
                except (NoSuchElementException, StaleElementReferenceException, TimeoutException) as e:
                    logger.debug(f"Methode CSS echouee: {type(e).__name__}")

            if not day_clicked:
                logger.warning(f"Jour {day_to_click} non trouve dans le picker")
                self.take_screenshot(f"debug_picker_{date_type}_{day_to_click}.png")

            time.sleep(0.5)

        except Exception as e:
            logger.error(f"Erreur date picker {date_type}: {e}")

    def _navigate_to_month(self, target_date: datetime):
        """
        Navigue vers le mois cible dans le date picker si necessaire.

        Superhote utilise:
        - .day__month_btn pour afficher le mois (ex: "Jan 2026") - DOUBLE underscore!
        - span.next pour le bouton suivant
        - span.prev pour le bouton precedent

        Args:
            target_date: Date cible
        """
        try:
            # Mois complets et abreges (Superhote utilise des abreges comme "Jan")
            months_full = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                           'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre']
            months_abbr = ['jan', 'fév', 'mar', 'avr', 'mai', 'juin',
                           'juil', 'août', 'sep', 'oct', 'nov', 'déc']

            target_month_full = months_full[target_date.month - 1]
            target_month_abbr = months_abbr[target_date.month - 1]
            logger.info(f"Navigation vers {target_month_full} ({target_month_abbr}) {target_date.year}")

            max_clicks = 12  # Maximum 1 an de navigation
            for click_num in range(max_clicks):
                # Chercher le mois affiche - PRIORITE: .day__month_btn pour Superhote (DOUBLE underscore!)
                month_selectors = [
                    ".day__month_btn",           # Superhote specifique - DOUBLE underscore!
                    ".vdp-datepicker__calendar .day__month_btn",
                    "span.day__month_btn.up",
                    ".datepicker-switch",
                    ".month-title",
                    "[class*='month_btn']",
                    "th[colspan]",
                    ".calendar-header"
                ]

                current_month_year = None
                for selector in month_selectors:
                    try:
                        elements = self.driver.find_elements(By.CSS_SELECTOR, selector)
                        for elem in elements:
                            if elem.is_displayed():
                                text = elem.text.lower().strip()
                                # Verifier mois complets et abreges
                                all_months = months_full + months_abbr
                                for month in all_months:
                                    if month in text:
                                        current_month_year = text
                                        logger.info(f"Mois actuel detecte: '{text}' (selector: {selector})")
                                        break
                            if current_month_year:
                                break
                    except (NoSuchElementException, StaleElementReferenceException) as e:
                        logger.debug(f"Selector mois '{selector}' non trouve: {type(e).__name__}")
                        continue
                    if current_month_year:
                        break

                if not current_month_year:
                    logger.warning("Mois du picker non detecte - verifier si le picker est ouvert")
                    break

                # Verifier si on est au bon mois (complet OU abrege)
                if target_month_full in current_month_year or target_month_abbr in current_month_year:
                    logger.info(f"Bon mois atteint: {current_month_year}")
                    break

                # Cliquer sur suivant - PRIORITE: span.next pour Superhote
                next_clicked = False
                next_selectors = [
                    "span.next",                # Superhote specifique !
                    ".vdp-datepicker__calendar span.next",
                    ".next",
                    ".datepicker-next",
                    "th.next",
                    "[data-action='next']",
                    "button[class*='right']",
                    "i.fa-chevron-right"
                ]

                for selector in next_selectors:
                    try:
                        next_btns = self.driver.find_elements(By.CSS_SELECTOR, selector)
                        for next_btn in next_btns:
                            if next_btn.is_displayed():
                                self.driver.execute_script("arguments[0].click();", next_btn)
                                time.sleep(0.5)
                                next_clicked = True
                                logger.info(f"Clic suivant (selector: {selector})")
                                break
                    except (NoSuchElementException, StaleElementReferenceException, ElementClickInterceptedException) as e:
                        logger.debug(f"Selector suivant '{selector}' echoue: {type(e).__name__}")
                        continue
                    if next_clicked:
                        break

                if not next_clicked:
                    # Fallback: XPath pour les fleches
                    try:
                        arrows = self.driver.find_elements(By.XPATH, "//*[contains(text(), '›') or contains(text(), '>') or contains(text(), '→')]")
                        for arrow in arrows:
                            if arrow.is_displayed():
                                self.driver.execute_script("arguments[0].click();", arrow)
                                time.sleep(0.5)
                                next_clicked = True
                                logger.info("Clic suivant (fleche XPath)")
                                break
                    except (NoSuchElementException, StaleElementReferenceException, ElementClickInterceptedException) as e:
                        logger.debug(f"Fleche XPath echouee: {type(e).__name__}")

                if not next_clicked:
                    logger.warning("Bouton suivant non trouve")
                    break

        except Exception as e:
            logger.error(f"Navigation mois: {e}")

    def _find_and_set_date(self, date_val: datetime, date_type: str):
        """
        Trouve et definit une date quand le champ n'est pas directement accessible.

        Args:
            date_val: Date a definir
            date_type: Type de date ("debut" ou "fin")
        """
        try:
            # Chercher un label puis le champ associe
            labels = ["date de début", "date début", "du", "début"] if date_type == "debut" else ["date de fin", "date fin", "au", "fin"]

            for label_text in labels:
                try:
                    label = self.driver.find_element(By.XPATH, f"//*[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '{label_text}')]")
                    if label.is_displayed():
                        # Chercher le champ proche du label
                        parent = label.find_element(By.XPATH, "./..")
                        field = parent.find_element(By.CSS_SELECTOR, "input, [class*='date']")
                        if field.is_displayed():
                            self._click_date_in_picker(field, date_val, date_type)
                            return
                except (NoSuchElementException, StaleElementReferenceException) as e:
                    logger.debug(f"Label '{label_text}' non trouve: {type(e).__name__}")
                    continue

        except Exception as e:
            logger.debug(f"Recherche champ date {date_type}: {e}")

    def _navigate_main_calendar_to_month(self, target_date: datetime):
        """
        Navigue le calendrier principal vers le mois cible.

        Superhote utilise:
        - <p>Janvier 2026</p> pour afficher le mois
        - div.next_date pour le bouton suivant
        - div.pre_date pour le bouton precedent

        Args:
            target_date: Date cible
        """
        try:
            months_full = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                           'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre']

            target_month = months_full[target_date.month - 1]
            target_year = target_date.year
            logger.info(f"Navigation calendrier principal vers {target_month} {target_year}")

            max_clicks = 12
            for _ in range(max_clicks):
                # Lire le mois affiche (dans un <p> qui contient le mois et l'annee)
                current_month_text = None
                try:
                    # Chercher le paragraphe qui contient le mois
                    month_elements = self.driver.find_elements(By.CSS_SELECTOR, "p")
                    for p in month_elements:
                        if p.is_displayed():
                            text = p.text.lower().strip()
                            for month in months_full:
                                if month in text:
                                    current_month_text = text
                                    break
                        if current_month_text:
                            break
                except (NoSuchElementException, StaleElementReferenceException) as e:
                    logger.debug(f"Detection mois echouee: {type(e).__name__}")

                if not current_month_text:
                    logger.warning("Mois du calendrier principal non detecte")
                    break

                logger.info(f"Calendrier principal affiche: {current_month_text}")

                # Verifier si on est au bon mois ET annee
                if target_month in current_month_text and str(target_year) in current_month_text:
                    logger.info(f"Bon mois atteint: {target_month} {target_year}")
                    # Attendre que le calendrier soit completement charge
                    time.sleep(1.5)
                    break

                # Determiner si on doit avancer ou reculer
                # Extraire le mois et l'annee actuels
                current_month_idx = None
                current_year = None
                for idx, month in enumerate(months_full):
                    if month in current_month_text:
                        current_month_idx = idx
                        break

                # Extraire l'annee du texte
                import re
                year_match = re.search(r'(\d{4})', current_month_text)
                if year_match:
                    current_year = int(year_match.group(1))

                if current_month_idx is None or current_year is None:
                    # Ne peut pas determiner, avancer par defaut
                    need_next = True
                else:
                    # Comparer les dates
                    current_date_approx = current_year * 12 + current_month_idx
                    target_date_approx = target_year * 12 + (target_date.month - 1)
                    need_next = target_date_approx > current_date_approx

                # Cliquer sur suivant ou precedent
                if need_next:
                    try:
                        next_btn = self.driver.find_element(By.CSS_SELECTOR, "div.next_date")
                        if next_btn.is_displayed():
                            self.driver.execute_script("arguments[0].click();", next_btn)
                            time.sleep(1)
                            logger.info("Clic sur mois suivant (next_date)")
                            continue
                    except (NoSuchElementException, StaleElementReferenceException, ElementClickInterceptedException) as e:
                        logger.debug(f"Bouton suivant non trouve: {type(e).__name__}")
                else:
                    try:
                        prev_btn = self.driver.find_element(By.CSS_SELECTOR, "div.pre_date")
                        if prev_btn.is_displayed():
                            self.driver.execute_script("arguments[0].click();", prev_btn)
                            time.sleep(1)
                            logger.info("Clic sur mois precedent (pre_date)")
                            continue
                    except (NoSuchElementException, StaleElementReferenceException, ElementClickInterceptedException) as e:
                        logger.debug(f"Bouton precedent non trouve: {type(e).__name__}")

                logger.warning("Boutons de navigation non trouves")
                break

        except Exception as e:
            logger.error(f"Erreur navigation calendrier principal: {e}")

    def update_price_for_dates(
        self,
        property_id: str,
        start_date: datetime,
        end_date: datetime,
        price: float,
        property_name: str = None
    ) -> bool:
        """
        Met a jour le prix pour une plage de dates sur Superhote.

        Workflow complet:
        1. Aller sur le calendrier mensuel (#/calendar/month)
        2. Filtrer pour afficher UNIQUEMENT ce logement
        3. Cliquer sur une cellule de prix pour ouvrir le panneau
        4. Remplir le prix ET les dates (debut/fin)
        5. Cliquer sur Enregistrer

        Args:
            property_id: ID du logement sur Superhote
            start_date: Date de debut
            end_date: Date de fin
            price: Nouveau prix par nuit
            property_name: Nom du logement (REQUIS pour filtrer)

        Returns:
            True si la mise a jour a reussi
        """
        if not self.is_logged_in:
            logger.error("Non connecte. Appelez login() d'abord.")
            return False

        if not property_name:
            logger.error("Nom du logement requis pour filtrer")
            return False

        try:
            # ETAPE 1: Naviguer vers le calendrier mensuel
            calendar_url = "https://app.superhote.com/#/calendar/month"
            logger.info(f"Navigation vers le calendrier: {calendar_url}")
            self.driver.get(calendar_url)
            wait_for_page_ready(self.driver, timeout=20)
            # Attendre que le calendrier soit visible
            try:
                WebDriverWait(self.driver, 10).until(
                    EC.presence_of_element_located((By.CSS_SELECTOR, ".calendar-container, .month-calendar, table"))
                )
            except TimeoutException:
                time.sleep(2)  # Fallback si le selecteur n'existe pas

            # ETAPE 2: Filtrer pour n'afficher que ce logement
            logger.info(f"Filtrage pour: {property_name}")
            self.filter_by_property(property_name)
            # Attendre que le filtre soit applique (calendrier se rafraichit)
            time.sleep(1.5)

            # ETAPE 2.5: Naviguer vers le bon mois dans le calendrier principal
            self._navigate_main_calendar_to_month(start_date)
            time.sleep(0.5)

            # ETAPE 3: Verifier si la date est reservee et cliquer sur la cellule
            # Les cellules ont un attribut data-td-date="YYYY-MM-DD"
            # Les cellules reservees contiennent span.ninja-btn.booking-item
            cell_clicked = False
            target_date_str = start_date.strftime("%Y-%m-%d")
            logger.info(f"Recherche cellule pour date: {target_date_str}")

            # Methode 1: Utiliser data-td-date (plus fiable)
            try:
                cell = self.driver.find_element(By.CSS_SELECTOR, f"td[data-td-date='{target_date_str}']")
                if cell.is_displayed():
                    # Verifier si la cellule a une reservation
                    try:
                        booking = cell.find_element(By.CSS_SELECTOR, ".booking-item, .ninja-btn.booking-item")
                        if booking:
                            # Calculer la duree de la reservation via la largeur du span
                            skip_days = 1  # Minimum 1 jour
                            try:
                                # Obtenir la largeur du booking et d'une cellule
                                booking_width = booking.size.get('width', 0)
                                cell_width = cell.size.get('width', 27)  # ~27px par defaut
                                if booking_width > 0 and cell_width > 0:
                                    skip_days = max(1, int(booking_width / cell_width) + 1)
                                logger.info(f"Reservation detectee: {target_date_str}, largeur={booking_width}px, skip={skip_days} jours")
                            except (NoSuchElementException, StaleElementReferenceException) as e:
                                logger.debug(f"Calcul largeur reservation echoue: {type(e).__name__}")

                            # Retourner un dict avec le nombre de jours a skip
                            return {"status": "skipped", "skip_days": skip_days}
                    except (NoSuchElementException, StaleElementReferenceException):
                        pass  # Pas de reservation, continuer

                    self.driver.execute_script("arguments[0].click();", cell)
                    time.sleep(2)
                    cell_clicked = True
                    logger.info(f"Cellule cliquee via data-td-date: {target_date_str}")
            except Exception as e:
                logger.debug(f"data-td-date non trouve: {e}")

            # Methode 2: Fallback - chercher par prix (20-500)
            if not cell_clicked:
                try:
                    all_elements = self.driver.find_elements(By.XPATH, "//td//span | //td//div")
                    for elem in all_elements:
                        try:
                            if elem.is_displayed():
                                text = elem.text.strip()
                                if text and text.isdigit() and 20 <= int(text) <= 500:
                                    self.driver.execute_script("arguments[0].click();", elem)
                                    time.sleep(2)
                                    cell_clicked = True
                                    logger.info(f"Cellule prix cliquee (fallback): {text}")
                                    break
                        except (StaleElementReferenceException, ValueError):
                            continue
                except (NoSuchElementException, StaleElementReferenceException) as e:
                    logger.debug(f"Fallback prix echoue: {type(e).__name__}")

            if not cell_clicked:
                logger.error(f"Aucune cellule trouvee pour {target_date_str}")
                self.take_screenshot("error_no_price_cell.png")
                return False

            # ETAPE 4: Remplir le formulaire du panneau lateral

            # 4a. Remplir le champ Prix via son ID specifique
            # Le champ prix a l'ID "price-number"
            price_filled = False
            try:
                price_input = self.driver.find_element(By.ID, "price-number")
                if price_input.is_displayed():
                    price_input.clear()
                    price_input.send_keys(str(int(price)))
                    logger.info(f"Prix entre via #price-number: {int(price)}")
                    price_filled = True
            except Exception as e:
                logger.debug(f"#price-number non trouve: {e}")

            # Fallback: chercher par CSS selector
            if not price_filled:
                try:
                    price_input = self.driver.find_element(By.CSS_SELECTOR, "input#price-number, input.form-control[type='text']")
                    if price_input.is_displayed():
                        price_input.clear()
                        price_input.send_keys(str(int(price)))
                        logger.info(f"Prix entre (fallback): {int(price)}")
                        price_filled = True
                except Exception as e:
                    logger.debug(f"Fallback prix: {e}")

            # 4b. Definir la date de fin (checkout = derniere nuit + 1 jour)
            # Dans Superhote, on selectionne le jour de checkout, pas la derniere nuit
            checkout_date = end_date + timedelta(days=1)
            logger.info(f"Definition date de fin: {checkout_date.strftime('%d/%m/%Y')}")

            # Chercher et definir la date de fin
            self._find_and_set_date(checkout_date, "fin")

            # ETAPE 5: Cliquer sur Enregistrer
            # Le bouton est dans le panneau lateral .panel-price
            time.sleep(1)
            save_clicked = False

            # Methode 1: Chercher le bouton Enregistrer dans le panneau .panel-price
            try:
                # Le panneau lateral a la classe panel-price
                enregistrer_btn = self.driver.find_element(
                    By.CSS_SELECTOR,
                    ".panel-price button.btn-red, .edit-price-section button.btn-red"
                )
                if enregistrer_btn.is_displayed():
                    self.driver.execute_script("arguments[0].click();", enregistrer_btn)
                    time.sleep(2)
                    logger.info(f"Prix {price}EUR enregistre pour {property_name} (panel-price btn-red)")
                    save_clicked = True
            except Exception as e:
                logger.debug(f"panel-price btn-red: {e}")

            # Methode 2: Chercher par texte "Enregistrer" dans les boutons visibles
            if not save_clicked:
                try:
                    buttons = self.driver.find_elements(By.CSS_SELECTOR, "button.btn-red")
                    for btn in buttons:
                        if btn.is_displayed():
                            btn_text = (btn.text or "").strip().lower()
                            if "enregistrer" in btn_text:
                                self.driver.execute_script("arguments[0].click();", btn)
                                time.sleep(2)
                                logger.info(f"Prix {price}EUR enregistre pour {property_name} (texte Enregistrer)")
                                save_clicked = True
                                break
                except Exception as e:
                    logger.debug(f"Recherche texte Enregistrer: {e}")

            # Methode 3: XPath avec texte exact
            if not save_clicked:
                try:
                    btn = self.driver.find_element(
                        By.XPATH,
                        "//button[contains(@class, 'btn-red') and contains(text(), 'Enregistrer')]"
                    )
                    if btn.is_displayed():
                        self.driver.execute_script("arguments[0].click();", btn)
                        time.sleep(2)
                        logger.info(f"Prix {price}EUR enregistre pour {property_name} (XPath)")
                        save_clicked = True
                except Exception as e:
                    logger.debug(f"XPath Enregistrer: {e}")

            if not save_clicked:
                logger.warning("Bouton Enregistrer non trouve")
                self.take_screenshot("error_no_save.png")
                return False

            return True

        except Exception as e:
            logger.error(f"Erreur mise a jour prix: {e}")
            self.take_screenshot(f"error_{datetime.now().strftime('%Y%m%d_%H%M%S')}.png")
            return False

    def get_current_price(self, property_id: str, date: datetime) -> Optional[float]:
        """
        Recupere le prix actuel pour une date donnee.

        Args:
            property_id: ID du logement sur Superhote
            date: Date pour laquelle recuperer le prix

        Returns:
            Prix actuel ou None si non trouve
        """
        if not self.is_logged_in:
            logger.error("Non connecte. Appelez login() d'abord.")
            return None

        try:
            if not self.navigate_to_calendar(property_id):
                return None

            time.sleep(2)

            # Chercher le prix dans le calendrier
            # Implementation a adapter selon l'interface Superhote

            price_elements = self.driver.find_elements(
                By.CSS_SELECTOR,
                f"[data-date='{date.strftime('%Y-%m-%d')}'] .price, "
                f".calendar-day[data-date='{date.strftime('%Y-%m-%d')}'] .price"
            )

            for elem in price_elements:
                try:
                    price_text = elem.text.strip().replace("EUR", "").replace("€", "").strip()
                    return float(price_text)
                except (ValueError, AttributeError):
                    continue

            return None

        except Exception as e:
            logger.error(f"Erreur recuperation prix: {e}")
            return None

    def take_screenshot(self, filename: str = None, force: bool = False) -> str:
        """
        Prend une capture d'ecran (si active dans la config ou forcee).

        Args:
            filename: Nom du fichier (optionnel)
            force: Forcer la capture meme si desactivee dans la config

        Returns:
            Chemin vers la capture d'ecran ou chaine vide
        """
        if not self.driver:
            return ""

        # Ne pas prendre de screenshot si desactive (sauf si force)
        if not force and not self.debug_screenshots:
            logger.debug(f"Screenshot ignore (debug_screenshots=False): {filename}")
            return ""

        screenshots_dir = LOG_DIR / "screenshots"
        screenshots_dir.mkdir(exist_ok=True)

        if not filename:
            filename = f"screenshot_{datetime.now().strftime('%Y%m%d_%H%M%S')}.png"

        filepath = screenshots_dir / filename
        self.driver.save_screenshot(str(filepath))
        logger.info(f"Screenshot sauvegarde: {filepath}")
        return str(filepath)

    def update_price_for_multiple_properties(
        self,
        property_names: List[str],
        start_date: datetime,
        end_date: datetime,
        price: float,
        reference_property: str = None
    ) -> Dict[str, bool]:
        """
        Met a jour le prix pour PLUSIEURS logements en une seule operation.

        Utilise la fonctionnalite native de Superhote "Appliquer aussi sur d'autres appartements"
        pour appliquer le meme prix a plusieurs logements simultanement.

        Args:
            property_names: Liste des noms de logements
            start_date: Date de debut
            end_date: Date de fin
            price: Nouveau prix par nuit
            reference_property: (Optionnel) Logement de reference pour ouvrir la modale.
                               Utile quand les logements cibles ont des reservations.
                               Si None, utilise le premier logement de la liste.

        Returns:
            Dict avec le statut de mise a jour pour chaque logement
        """
        if not self.is_logged_in:
            logger.error("Non connecte. Appelez login() d'abord.")
            return {name: False for name in property_names}

        if not property_names:
            return {}

        results = {name: False for name in property_names}

        # Si un logement de reference est fourni, l'utiliser pour ouvrir la modale
        # Sinon, utiliser le premier logement de la liste
        if reference_property:
            first_property = reference_property
            other_properties = property_names  # Tous les logements sont "autres"
            logger.info(f"Mode groupe: reference={reference_property}, cibles={property_names}")
        else:
            first_property = property_names[0]
            other_properties = property_names[1:] if len(property_names) > 1 else []

        try:
            # ETAPE 1: Naviguer vers le calendrier mensuel
            calendar_url = "https://app.superhote.com/#/calendar/month"
            logger.info(f"Navigation vers le calendrier: {calendar_url}")
            self.driver.get(calendar_url)
            wait_for_page_ready(self.driver, timeout=20)
            # Attendre que le calendrier soit visible
            try:
                WebDriverWait(self.driver, 10).until(
                    EC.presence_of_element_located((By.CSS_SELECTOR, ".calendar-container, .month-calendar, table"))
                )
            except TimeoutException:
                time.sleep(1.5)  # Fallback

            # ETAPE 2: Filtrer pour le premier logement
            logger.info(f"Filtrage pour: {first_property}")
            self.filter_by_property(first_property)
            time.sleep(1.5)  # Attendre que le filtre soit applique

            # ETAPE 3: Naviguer vers le bon mois
            self._navigate_main_calendar_to_month(start_date)

            # ETAPE 4: Attendre que la cellule cible soit presente dans le DOM
            target_date_str = start_date.strftime("%Y-%m-%d")
            logger.info(f"Recherche cellule pour date: {target_date_str}")

            # Attendre explicitement que la cellule existe et soit visible
            try:
                WebDriverWait(self.driver, 10).until(
                    EC.presence_of_element_located((By.CSS_SELECTOR, f"td[data-td-date='{target_date_str}']"))
                )
                time.sleep(0.5)  # Petit delai supplementaire pour l'animation
                logger.info(f"Cellule {target_date_str} trouvee dans le DOM")
            except TimeoutException:
                logger.error(f"Cellule {target_date_str} non trouvee apres navigation - mauvais mois?")
                self.take_screenshot(f"cell_not_found_{target_date_str}.png")
                return results

            def try_click_cell():
                """Tente de cliquer sur la cellule et verifie que le panneau s'ouvre."""
                cell = self.driver.find_element(By.CSS_SELECTOR, f"td[data-td-date='{target_date_str}']")

                # Verifier si la cellule a une reservation active (prix cache ou booking-item visible)
                try:
                    booking_item = cell.find_elements(By.CSS_SELECTOR, ".booking-item")
                    for item in booking_item:
                        # Si le booking-item a une largeur > 0, c'est une reservation
                        style = item.get_attribute("style") or ""
                        if "width" in style and "width: 0" not in style and "min-width: 0" not in style:
                            text = item.text.strip()
                            if text:  # Il y a un nom de client
                                logger.info(f"Reservation detectee sur {target_date_str}: {text}")
                                return "reservation"
                except (NoSuchElementException, StaleElementReferenceException):
                    pass

                # Fermer tout panneau existant en cliquant ailleurs d'abord
                try:
                    body = self.driver.find_element(By.TAG_NAME, "body")
                    self.driver.execute_script("arguments[0].click();", body)
                    time.sleep(0.3)
                except (NoSuchElementException, StaleElementReferenceException):
                    pass

                # Scroll la cellule au centre de l'ecran
                self.driver.execute_script(
                    "arguments[0].scrollIntoView({block: 'center', inline: 'center'});",
                    cell
                )
                time.sleep(0.5)

                # Methode 1: Cliquer sur le div.checkin-price (le bon element!)
                try:
                    checkin_price = cell.find_element(By.CSS_SELECTOR, ".checkin-price")
                    # Verifier que le prix n'est pas cache (pas de reservation)
                    style = checkin_price.get_attribute("style") or ""
                    if "display: none" not in style and "display:none" not in style:
                        self.driver.execute_script("arguments[0].click();", checkin_price)
                        time.sleep(1.5)
                        price_input = self.driver.find_element(By.ID, "price-number")
                        if price_input.is_displayed():
                            return True
                except (NoSuchElementException, StaleElementReferenceException, ElementClickInterceptedException):
                    pass

                # Methode 2: Cliquer sur le lien open-price-modal
                try:
                    open_modal_link = cell.find_element(By.CSS_SELECTOR, "a.open-price-modal, a.needsclick")
                    self.driver.execute_script("arguments[0].click();", open_modal_link)
                    time.sleep(1.5)
                    price_input = self.driver.find_element(By.ID, "price-number")
                    if price_input.is_displayed():
                        return True
                except (NoSuchElementException, StaleElementReferenceException, ElementClickInterceptedException):
                    pass

                # Methode 3: Clic sur la cellule td elle-meme
                try:
                    self.driver.execute_script("arguments[0].click();", cell)
                    time.sleep(1.5)
                    price_input = self.driver.find_element(By.ID, "price-number")
                    if price_input.is_displayed():
                        return True
                except (NoSuchElementException, StaleElementReferenceException, ElementClickInterceptedException):
                    pass

                # Methode 4: Double-clic sur checkin-price
                try:
                    from selenium.webdriver.common.action_chains import ActionChains
                    checkin_price = cell.find_element(By.CSS_SELECTOR, ".checkin-price")
                    actions = ActionChains(self.driver)
                    actions.double_click(checkin_price).perform()
                    time.sleep(1.5)
                    price_input = self.driver.find_element(By.ID, "price-number")
                    if price_input.is_displayed():
                        return True
                except (NoSuchElementException, StaleElementReferenceException, ElementClickInterceptedException):
                    pass

                return False

            # Essayer jusqu'a 3 fois
            cell_clicked = False
            has_reservation = False
            for attempt in range(3):
                try:
                    result = try_click_cell()
                    if result == "reservation":
                        has_reservation = True
                        logger.warning(f"Reservation sur {target_date_str} - impossible de modifier le prix")
                        break
                    elif result:
                        cell_clicked = True
                        logger.info(f"Cellule cliquee: {target_date_str} (tentative {attempt + 1})")
                        break
                    else:
                        logger.debug(f"Panneau non ouvert, tentative {attempt + 1}/3")
                        time.sleep(1)
                except Exception as e:
                    logger.debug(f"Erreur clic cellule tentative {attempt + 1}: {e}")
                    time.sleep(1)

            # Si reservation, on ne peut pas modifier - retourner echec
            if has_reservation:
                for name in property_names:
                    results[name] = False
                return results

            if not cell_clicked:
                logger.error(f"Impossible d'ouvrir le panneau prix pour {target_date_str}")
                # Prendre un screenshot pour debug
                self.take_screenshot(f"cell_click_failed_{target_date_str}.png")
                return results

            # ETAPE 5: Remplir le prix
            price_entered = False
            try:
                price_input = self.driver.find_element(By.ID, "price-number")
                price_input.clear()
                price_input.send_keys(str(int(price)))
                logger.info(f"Prix entre: {int(price)}")
                price_entered = True
            except Exception as e:
                logger.error(f"Champ prix non trouve: {e}")

            if not price_entered:
                logger.error("Prix non entre - abandon de cette mise a jour")
                return results

            # ETAPE 6: Definir la date de fin (checkout = derniere nuit + 1 jour)
            # Dans Superhote, on selectionne le jour de checkout, pas la derniere nuit
            checkout_date = end_date + timedelta(days=1)
            self._click_end_date_in_panel(checkout_date)

            # ETAPE 7: Si plusieurs logements, cocher "Appliquer aussi sur d'autres appartements"
            if other_properties:
                logger.info(f"Application multi-logements: {len(property_names)} logements")

                checkbox_clicked = False
                try:
                    # La checkbox est souvent cachée visuellement, le label est l'élément cliquable
                    # Méthode 1: Cliquer sur le label directement (le plus fiable)
                    try:
                        label = self.driver.find_element(By.CSS_SELECTOR, "label[for='price-multiple-modification']")
                        if label:
                            self.driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", label)
                            time.sleep(0.3)
                            self.driver.execute_script("arguments[0].click();", label)
                            time.sleep(0.5)
                            checkbox_clicked = True
                            logger.info("Checkbox multi-modification cochee via label")
                    except Exception as e:
                        logger.debug(f"Label checkbox non trouve: {e}")

                    # Méthode 2: Cliquer sur la checkbox par ID (même si hidden)
                    if not checkbox_clicked:
                        try:
                            checkbox = self.driver.find_element(By.ID, "price-multiple-modification")
                            if checkbox:
                                # Vérifier si déjà cochée
                                if not checkbox.is_selected():
                                    self.driver.execute_script("arguments[0].click();", checkbox)
                                    time.sleep(0.5)
                                checkbox_clicked = True
                                logger.info("Checkbox multi-modification cochee via ID")
                        except Exception as e:
                            logger.debug(f"Checkbox par ID non trouvee: {e}")

                    # Méthode 3: Chercher dans le conteneur .checkbox-custom
                    if not checkbox_clicked:
                        try:
                            container = self.driver.find_element(By.CSS_SELECTOR,
                                ".checkbox-custom.checkbox-primary:has(#price-multiple-modification), "
                                ".edit-price-section .checkbox-custom")
                            if container:
                                self.driver.execute_script("arguments[0].click();", container)
                                time.sleep(0.5)
                                checkbox_clicked = True
                                logger.info("Checkbox multi-modification cochee via conteneur")
                        except Exception as e:
                            logger.debug(f"Conteneur checkbox non trouve: {e}")

                    # Méthode 4: Chercher le texte "Appliquer aussi sur d'autres appartements"
                    if not checkbox_clicked:
                        try:
                            labels = self.driver.find_elements(By.TAG_NAME, "label")
                            for lbl in labels:
                                lbl_text = (lbl.text or "").lower()
                                if "appliquer" in lbl_text and "appartement" in lbl_text:
                                    self.driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", lbl)
                                    time.sleep(0.2)
                                    self.driver.execute_script("arguments[0].click();", lbl)
                                    time.sleep(0.5)
                                    checkbox_clicked = True
                                    logger.info("Checkbox multi-modification cochee via texte label")
                                    break
                        except Exception as e:
                            logger.debug(f"Recherche par texte echouee: {e}")

                    if not checkbox_clicked:
                        logger.warning("Checkbox multi-modification non trouvee - le modal ne s'ouvrira pas")
                        # Prendre un screenshot pour debug
                        self.take_screenshot(f"checkbox_not_found_{datetime.now().strftime('%H%M%S')}.png")
                except Exception as e:
                    logger.warning(f"Erreur checkbox multi-modification: {e}")

            # ETAPE 8: Cliquer sur Enregistrer (ouvre le modal si checkbox cochee)
            save_clicked = False
            try:
                # Bouton dans le panneau lateral
                enregistrer_btn = self.driver.find_element(
                    By.CSS_SELECTOR,
                    ".panel-price button.btn-red, .edit-price-section button.btn-red"
                )
                if enregistrer_btn.is_displayed():
                    self.driver.execute_script("arguments[0].click();", enregistrer_btn)
                    time.sleep(2)
                    save_clicked = True
                    logger.info("Bouton Enregistrer clique")
            except Exception as e:
                logger.debug(f"Bouton Enregistrer: {e}")

            if not save_clicked:
                try:
                    buttons = self.driver.find_elements(By.CSS_SELECTOR, "button.btn-red")
                    for btn in buttons:
                        if btn.is_displayed() and "enregistrer" in (btn.text or "").lower():
                            self.driver.execute_script("arguments[0].click();", btn)
                            time.sleep(2)
                            save_clicked = True
                            break
                except (NoSuchElementException, StaleElementReferenceException) as e:
                    logger.debug(f"Boutons enregistrer non trouves: {type(e).__name__}")

            if not save_clicked:
                logger.error("Bouton Enregistrer non trouve")
                return results

            # ETAPE 9: Si multi-logements, gerer le modal de selection
            if other_properties:
                time.sleep(1.5)

                # Attendre que le modal soit visible et charge
                modal_found = False
                modal = None
                try:
                    # Attendre le modal avec la classe 'show' qui indique qu'il est visible
                    modal = WebDriverWait(self.driver, 8).until(
                        EC.presence_of_element_located((By.CSS_SELECTOR, "#edit-rentals-price.show, .modal.show"))
                    )
                    # Attendre aussi que le contenu soit charge
                    WebDriverWait(self.driver, 5).until(
                        EC.presence_of_element_located((By.CSS_SELECTOR, ".multi-select-rentals"))
                    )
                    modal_found = True
                    logger.info("Modal de selection ouvert et charge")
                except (TimeoutException, NoSuchElementException) as e:
                    logger.debug(f"Modal principal non trouve: {type(e).__name__}")
                    # Fallback: verifier si le modal est present meme sans classe 'show'
                    try:
                        modal = self.driver.find_element(By.CSS_SELECTOR, "#edit-rentals-price")
                        if modal:
                            # Verifier aria-hidden
                            aria_hidden = modal.get_attribute("aria-hidden")
                            if aria_hidden == "false" or modal.is_displayed():
                                modal_found = True
                                logger.info("Modal trouve via fallback")
                    except (NoSuchElementException, StaleElementReferenceException):
                        pass

                if not modal_found:
                    logger.warning("Modal de selection non trouve")

                if modal_found:
                    # Attendre un peu que les elements soient interactifs
                    time.sleep(1)

                    # Screenshot de debug pour voir le modal
                    self.take_screenshot(f"modal_debug_{datetime.now().strftime('%H%M%S')}.png")

                    # Trouver le conteneur scrollable des rentals
                    scroll_container = None
                    try:
                        scroll_container = self.driver.find_element(By.CSS_SELECTOR, ".multi-select-rentals")
                    except (NoSuchElementException, StaleElementReferenceException):
                        pass

                    # Selectionner les autres logements dans le modal
                    for prop_name in other_properties:
                        selected = False
                        try:
                            # Methode 1: Chercher la checkbox par son ID exact et scroller vers elle
                            try:
                                checkbox = self.driver.find_element(By.ID, prop_name)
                                if checkbox:
                                    # Scroller vers l'element
                                    self.driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", checkbox)
                                    time.sleep(0.2)
                                    # Cliquer meme si pas "displayed" (peut etre hors viewport)
                                    if not checkbox.is_selected():
                                        self.driver.execute_script("arguments[0].click();", checkbox)
                                    time.sleep(0.3)
                                    logger.info(f"Logement coche par ID: {prop_name}")
                                    selected = True
                            except Exception as e:
                                logger.debug(f"Methode ID echouee pour {prop_name}: {e}")

                            # Methode 2: Chercher le label avec l'attribut 'for'
                            if not selected:
                                try:
                                    # Echapper les caracteres speciaux pour CSS
                                    escaped_name = prop_name.replace('"', '\\"')
                                    label = self.driver.find_element(By.CSS_SELECTOR, f'label[for="{escaped_name}"]')
                                    if label:
                                        self.driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", label)
                                        time.sleep(0.2)
                                        self.driver.execute_script("arguments[0].click();", label)
                                        time.sleep(0.3)
                                        logger.info(f"Logement coche par label[for]: {prop_name}")
                                        selected = True
                                except Exception as e:
                                    logger.debug(f"Methode label[for] echouee pour {prop_name}: {e}")

                            # Methode 3: Chercher le span avec le nom de la rental
                            if not selected:
                                try:
                                    span = self.driver.find_element(By.XPATH,
                                        f"//div[contains(@class, 'multi-select-rentals')]//span[contains(@class, 'rental-name') and contains(text(), '{prop_name}')]")
                                    if span:
                                        # Remonter au label parent et cliquer
                                        label = span.find_element(By.XPATH, "./ancestor::label")
                                        self.driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", label)
                                        time.sleep(0.2)
                                        self.driver.execute_script("arguments[0].click();", label)
                                        time.sleep(0.3)
                                        logger.info(f"Logement coche par span rental-name: {prop_name}")
                                        selected = True
                                except Exception as e:
                                    logger.debug(f"Methode span echouee pour {prop_name}: {e}")

                            # Methode 4: Chercher dans la liste li avec classe dropdown-item
                            if not selected:
                                try:
                                    li_elem = self.driver.find_element(By.XPATH,
                                        f"//li[contains(@class, 'dropdown-item')]//label[contains(., '{prop_name}')]")
                                    if li_elem:
                                        self.driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", li_elem)
                                        time.sleep(0.2)
                                        self.driver.execute_script("arguments[0].click();", li_elem)
                                        time.sleep(0.3)
                                        logger.info(f"Logement coche par li dropdown-item: {prop_name}")
                                        selected = True
                                except Exception as e:
                                    logger.debug(f"Methode li echouee pour {prop_name}: {e}")

                            # Methode 5: Recherche par texte exact dans le modal
                            if not selected:
                                try:
                                    labels = self.driver.find_elements(By.CSS_SELECTOR, ".multi-select-rentals label")
                                    for lbl in labels:
                                        if prop_name in (lbl.text or ""):
                                            self.driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", lbl)
                                            time.sleep(0.2)
                                            self.driver.execute_script("arguments[0].click();", lbl)
                                            time.sleep(0.3)
                                            logger.info(f"Logement coche par recherche texte: {prop_name}")
                                            selected = True
                                            break
                                except Exception as e:
                                    logger.debug(f"Methode recherche texte echouee pour {prop_name}: {e}")

                            if selected:
                                results[prop_name] = True
                            else:
                                logger.warning(f"Logement non trouve dans modal: {prop_name}")

                        except Exception as e:
                            logger.warning(f"Erreur selection logement {prop_name}: {e}")

                    # Cliquer sur Enregistrer dans le modal
                    time.sleep(0.5)
                    modal_save_clicked = False

                    # Le bouton Enregistrer est dans le modal-body, pas modal-footer
                    # C'est un bouton rouge (btn-red ou btn-primary selon le theme)
                    try:
                        modal_btn_selectors = [
                            "#edit-rentals-price button.btn-red",
                            "#edit-rentals-price .btn-save",
                            ".modal.show button.btn-red",
                            ".modal.show .btn-save",
                            ".modal-body button.btn-red",
                            ".modal-content button.btn-red",
                            ".modal-footer button.btn-red",
                            ".modal-footer .btn-primary",
                            "button.btn-save",
                        ]

                        for sel in modal_btn_selectors:
                            if modal_save_clicked:
                                break
                            try:
                                btns = self.driver.find_elements(By.CSS_SELECTOR, sel)
                                for btn in btns:
                                    btn_text = (btn.text or "").lower().strip()
                                    # Verifier que c'est bien un bouton Enregistrer visible
                                    if "enregistrer" in btn_text:
                                        self.driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", btn)
                                        time.sleep(0.2)
                                        self.driver.execute_script("arguments[0].click();", btn)
                                        time.sleep(2)
                                        modal_save_clicked = True
                                        logger.info(f"Modal: bouton Enregistrer clique ({sel})")
                                        break
                            except Exception as e:
                                logger.debug(f"Selecteur {sel} echoue: {e}")
                                continue

                        # Fallback: chercher tous les boutons avec texte Enregistrer
                        if not modal_save_clicked:
                            try:
                                all_buttons = self.driver.find_elements(By.TAG_NAME, "button")
                                for btn in all_buttons:
                                    btn_text = (btn.text or "").lower().strip()
                                    if "enregistrer" in btn_text and btn.is_displayed():
                                        self.driver.execute_script("arguments[0].click();", btn)
                                        time.sleep(2)
                                        modal_save_clicked = True
                                        logger.info("Modal: bouton Enregistrer clique (fallback)")
                                        break
                            except (NoSuchElementException, StaleElementReferenceException) as e:
                                logger.debug(f"Fallback boutons modal echoue: {type(e).__name__}")
                    except (NoSuchElementException, StaleElementReferenceException, ElementClickInterceptedException) as e:
                        logger.debug(f"Bouton modal Enregistrer non trouve: {type(e).__name__}")

                    if modal_save_clicked:
                        # Marquer le premier logement comme reussi (il a ete selectionne via le filtre)
                        results[first_property] = True
                        logger.info(f"Prix {price}EUR applique - premier logement: {first_property}")

                        # Les autres logements sont marques selon s'ils ont ete trouves dans le modal
                        selected_count = sum(1 for p in property_names if results.get(p, False))
                        logger.info(f"Total logements selectionnes: {selected_count}/{len(property_names)}")
                    else:
                        logger.warning("Modal: bouton Enregistrer non trouve")
                        # Prendre un screenshot pour debug
                        self.take_screenshot(f"modal_save_not_found_{datetime.now().strftime('%H%M%S')}.png")
                else:
                    # Pas de modal mais save clique - premier logement OK
                    results[first_property] = True
            else:
                # Un seul logement
                results[first_property] = True
                logger.info(f"Prix {price}EUR enregistre pour {first_property}")

            return results

        except Exception as e:
            logger.error(f"Erreur mise a jour multi-logements: {e}")
            self.take_screenshot(f"error_multi_{datetime.now().strftime('%Y%m%d_%H%M%S')}.png")
            return results

    def _click_end_date_in_panel(self, end_date: datetime):
        """
        Clique sur la date de fin dans le panneau lateral.

        Superhote utilise:
        - #edit-price-end-date pour l'input de date de fin
        - .vdp-datepicker__calendar pour le calendrier
        - span.cell.day pour les jours

        Args:
            end_date: Date de fin a selectionner
        """
        try:
            logger.info(f"Definition date de fin: {end_date.strftime('%d/%m/%Y')}")

            # Methode 0: Selector direct #edit-price-end-date (PRIORITAIRE!)
            try:
                end_input = self.driver.find_element(By.CSS_SELECTOR, "#edit-price-end-date")
                if end_input and end_input.is_displayed():
                    self.driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", end_input)
                    time.sleep(0.3)
                    self.driver.execute_script("arguments[0].click();", end_input)
                    time.sleep(1.2)

                    # Verifier si le picker s'est ouvert
                    try:
                        calendar = WebDriverWait(self.driver, 3).until(
                            EC.visibility_of_element_located((By.CSS_SELECTOR, ".vdp-datepicker__calendar"))
                        )
                        if calendar:
                            self._navigate_to_month(end_date)
                            time.sleep(0.5)
                            self._click_day_in_picker(end_date.day)
                            logger.info(f"Date de fin selectionnee via #edit-price-end-date: {end_date.strftime('%d/%m/%Y')}")
                            return
                    except (TimeoutException, NoSuchElementException) as e:
                        logger.debug(f"Calendrier non visible apres clic sur #edit-price-end-date: {type(e).__name__}")
            except Exception as e:
                logger.debug(f"#edit-price-end-date non trouve: {e}")

            # Methode 1: Chercher specifiquement le deuxieme vdp-datepicker (date de fin)
            # Le panneau a 2 date pickers: debut et fin
            datepickers = self.driver.find_elements(By.CSS_SELECTOR,
                ".edit-price-section .vdp-datepicker, .panel-price .vdp-datepicker")

            # Filtrer les datepickers visibles
            visible_pickers = [p for p in datepickers if p.is_displayed()]
            logger.debug(f"Datepickers visibles trouves: {len(visible_pickers)}")

            end_picker = None
            if len(visible_pickers) >= 2:
                end_picker = visible_pickers[1]  # Le 2eme est la date de fin
            elif len(visible_pickers) == 1:
                # Un seul picker visible, peut-etre structure differente
                end_picker = visible_pickers[0]

            if end_picker:
                # Cliquer sur l'input du date picker
                try:
                    end_input = end_picker.find_element(By.CSS_SELECTOR, "input")
                    self.driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", end_input)
                    time.sleep(0.3)
                    self.driver.execute_script("arguments[0].click();", end_input)
                    time.sleep(1.2)

                    # Verifier si le picker s'est ouvert en cherchant le calendrier visible
                    picker_opened = False
                    try:
                        calendar = WebDriverWait(self.driver, 3).until(
                            EC.visibility_of_element_located((By.CSS_SELECTOR, ".vdp-datepicker__calendar"))
                        )
                        if calendar:
                            picker_opened = True
                            logger.debug("Calendrier du picker ouvert")
                    except (TimeoutException, NoSuchElementException) as e:
                        logger.debug(f"Calendrier non visible apres clic: {type(e).__name__}")

                    if picker_opened:
                        # Le picker devrait s'ouvrir - naviguer et cliquer
                        self._navigate_to_month(end_date)
                        time.sleep(0.5)
                        self._click_day_in_picker(end_date.day)
                        logger.info(f"Date de fin selectionnee via vdp-datepicker: {end_date.strftime('%d/%m/%Y')}")
                        return
                    else:
                        logger.debug("Picker non ouvert, tentative avec double-clic")
                        # Essayer un double-clic ou autre interaction
                        self.driver.execute_script("arguments[0].focus();", end_input)
                        time.sleep(0.2)
                        self.driver.execute_script("arguments[0].click();", end_input)
                        time.sleep(1)
                        self._navigate_to_month(end_date)
                        time.sleep(0.5)
                        self._click_day_in_picker(end_date.day)
                        logger.info(f"Date de fin selectionnee via vdp-datepicker (2e tentative): {end_date.strftime('%d/%m/%Y')}")
                        return
                except Exception as e:
                    logger.debug(f"Erreur vdp-datepicker: {e}")

            # Methode 2: Chercher par label "Date de fin"
            try:
                labels = self.driver.find_elements(By.XPATH,
                    "//*[contains(text(), 'fin') or contains(text(), 'Fin')]")
                for label in labels:
                    if label.is_displayed():
                        # Chercher l'input proche
                        parent = label.find_element(By.XPATH, "./ancestor::div[contains(@class, 'form-group')]")
                        if parent:
                            date_input = parent.find_element(By.CSS_SELECTOR, "input, .vdp-datepicker input")
                            if date_input and date_input.is_displayed():
                                self.driver.execute_script("arguments[0].click();", date_input)
                                time.sleep(1)
                                self._navigate_to_month(end_date)
                                time.sleep(0.5)
                                self._click_day_in_picker(end_date.day)
                                logger.info(f"Date de fin selectionnee via label: {end_date.strftime('%d/%m/%Y')}")
                                return
            except Exception as e:
                logger.debug(f"Methode label: {e}")

            # Methode 3: Fallback - chercher tous les inputs de date
            date_fields = self.driver.find_elements(By.CSS_SELECTOR,
                ".edit-price-section input, .panel-price input[type='text']")

            visible_fields = [f for f in date_fields if f.is_displayed()]
            if len(visible_fields) >= 2:
                end_field = visible_fields[1]  # Le 2eme champ est la date de fin
                self.driver.execute_script("arguments[0].click();", end_field)
                time.sleep(1)
                self._navigate_to_month(end_date)
                time.sleep(0.5)
                self._click_day_in_picker(end_date.day)
                logger.info(f"Date de fin selectionnee via fallback: {end_date.strftime('%d/%m/%Y')}")
            else:
                # Dernier recours
                self._find_and_set_date(end_date, "fin")

        except Exception as e:
            logger.warning(f"Erreur date de fin: {e}")
            self._find_and_set_date(end_date, "fin")

    def _click_day_in_picker(self, day: int):
        """
        Clique sur un jour specifique dans le date picker ouvert.

        Args:
            day: Numero du jour (1-31)
        """
        day_clicked = False

        # Methode 1: vdp-datepicker visible
        try:
            picker_days = self.driver.find_elements(By.CSS_SELECTOR,
                ".vdp-datepicker__calendar:not([style*='display: none']) span.cell.day")
            for day_span in picker_days:
                if day_span.is_displayed():
                    text = day_span.text.strip()
                    if text == str(day):
                        span_class = day_span.get_attribute("class") or ""
                        if "disabled" not in span_class and "blank" not in span_class:
                            self.driver.execute_script("arguments[0].click();", day_span)
                            logger.info(f"Jour {day} selectionne (vdp span.cell.day)")
                            day_clicked = True
                            return
        except Exception as e:
            logger.debug(f"Methode 1 vdp: {e}")

        # Methode 2: Tous les spans dans le calendrier visible
        if not day_clicked:
            try:
                picker_days = self.driver.find_elements(By.CSS_SELECTOR,
                    ".vdp-datepicker__calendar span")
                for day_span in picker_days:
                    if day_span.is_displayed():
                        text = day_span.text.strip()
                        if text == str(day):
                            parent = day_span.find_element(By.XPATH, "./..")
                            parent_class = parent.get_attribute("class") or ""
                            span_class = day_span.get_attribute("class") or ""
                            all_classes = parent_class + " " + span_class
                            if "disabled" not in all_classes and "blank" not in all_classes:
                                self.driver.execute_script("arguments[0].click();", day_span)
                                logger.info(f"Jour {day} selectionne (vdp span)")
                                day_clicked = True
                                return
            except Exception as e:
                logger.debug(f"Methode 2 vdp: {e}")

        # Methode 3: XPath generique pour trouver le jour
        if not day_clicked:
            try:
                selectors = [
                    f"//span[normalize-space(text())='{day}' and not(contains(@class, 'disabled'))]",
                    f"//td[normalize-space(text())='{day}' and not(contains(@class, 'disabled'))]",
                    f"//*[contains(@class, 'day')][normalize-space(text())='{day}']",
                ]
                for selector in selectors:
                    try:
                        elements = self.driver.find_elements(By.XPATH, selector)
                        for elem in elements:
                            if elem.is_displayed():
                                elem_class = (elem.get_attribute("class") or "").lower()
                                if "disabled" not in elem_class and "blank" not in elem_class:
                                    self.driver.execute_script("arguments[0].click();", elem)
                                    logger.info(f"Jour {day} selectionne (XPath)")
                                    day_clicked = True
                                    return
                    except (NoSuchElementException, StaleElementReferenceException) as e:
                        logger.debug(f"Selector jour echoue: {type(e).__name__}")
                        continue
            except Exception as e:
                logger.debug(f"Methode 3 XPath: {e}")

        if not day_clicked:
            logger.warning(f"Jour {day} non trouve dans le picker")


# Fonction utilitaire pour tester la connexion
def test_connection():
    """Teste la connexion a Superhote."""
    with SuperhoteAutomation() as bot:
        if bot.login():
            print("Connexion reussie!")
            bot.take_screenshot("test_login_success.png")
            return True
        else:
            print("Echec de la connexion")
            bot.take_screenshot("test_login_failed.png")
            return False


if __name__ == "__main__":
    # Test de la connexion
    test_connection()
