#!/usr/bin/env python3
# coding: utf-8
"""
Daemon pour l'execution automatique des mises a jour de prix Superhote.
Surveille la file d'attente et execute les mises a jour programmees.
"""

import os
import sys
import time
import signal
import logging
import configparser
import pymysql
from datetime import datetime, timedelta
from pathlib import Path
from typing import Optional, Dict, List

# Configuration du logging
LOG_DIR = Path(__file__).parent.parent.parent / "logs"
LOG_DIR.mkdir(exist_ok=True)

logging.basicConfig(
    filename=str(LOG_DIR / "superhote_daemon.log"),
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s"
)
logger = logging.getLogger(__name__)

# Ajouter aussi la sortie console
console_handler = logging.StreamHandler()
console_handler.setLevel(logging.INFO)
logger.addHandler(console_handler)

# Import du module de mise a jour
from superhote_price_updater import SuperhotePriceUpdater


class SuperhoteDaemon:
    """
    Daemon pour la gestion automatique des mises a jour de prix Superhote.
    """

    def __init__(self):
        """Initialise le daemon."""
        self.config = configparser.ConfigParser()
        self.config.read(Path(__file__).parent.parent.parent / "config" / "config.ini")

        # Configuration BDD
        self.db_host = self.config.get("DATABASE", "host")
        self.db_user = self.config.get("DATABASE", "user")
        self.db_password = self.config.get("DATABASE", "password")
        self.db_name = self.config.get("DATABASE", "database")

        # Configuration du daemon
        self.running = True
        self.check_interval = 300  # 5 minutes entre chaque verification
        self.batch_size = 10  # Nombre max de mises a jour par cycle

        # Gestion des signaux
        signal.signal(signal.SIGINT, self._signal_handler)
        signal.signal(signal.SIGTERM, self._signal_handler)

        logger.info("Daemon Superhote initialise")

    def _signal_handler(self, signum, frame):
        """Gere les signaux d'arret."""
        logger.info(f"Signal {signum} recu, arret du daemon...")
        self.running = False

    def get_db_connection(self):
        """Obtient une connexion a la base de donnees."""
        try:
            return pymysql.connect(
                host=self.db_host,
                user=self.db_user,
                password=self.db_password,
                database=self.db_name,
                charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor
            )
        except Exception as e:
            logger.error(f"Erreur connexion BDD: {e}")
            return None

    def check_pending_updates(self) -> int:
        """
        Verifie le nombre de mises a jour en attente.

        Returns:
            Nombre de mises a jour en attente
        """
        db = self.get_db_connection()
        if not db:
            return 0

        try:
            with db.cursor() as cursor:
                cursor.execute("""
                    SELECT COUNT(*) as count FROM superhote_price_updates
                    WHERE status = 'pending'
                    AND (scheduled_at IS NULL OR scheduled_at <= NOW())
                """)
                result = cursor.fetchone()
                return result['count'] if result else 0
        except pymysql.err.ProgrammingError:
            # Table n'existe pas encore
            return 0
        finally:
            db.close()

    def check_auto_sync_logements(self) -> List[Dict]:
        """
        Recupere les logements avec auto-sync active qui doivent etre synchronises.

        Returns:
            Liste des logements a synchroniser
        """
        db = self.get_db_connection()
        if not db:
            return []

        try:
            with db.cursor() as cursor:
                cursor.execute("""
                    SELECT sc.*, l.nom_du_logement
                    FROM superhote_config sc
                    LEFT JOIN liste_logements l ON sc.logement_id = l.id
                    WHERE sc.is_active = 1
                    AND sc.auto_sync = 1
                    AND sc.superhote_property_id IS NOT NULL
                    AND (
                        sc.last_sync_at IS NULL
                        OR sc.last_sync_at < DATE_SUB(NOW(), INTERVAL sc.sync_interval_hours HOUR)
                    )
                """)
                return cursor.fetchall()
        except pymysql.err.ProgrammingError:
            return []
        finally:
            db.close()

    def create_auto_sync_updates(self, logement_config: Dict):
        """
        Cree les mises a jour automatiques pour un logement.

        Args:
            logement_config: Configuration du logement
        """
        db = self.get_db_connection()
        if not db:
            return

        try:
            logement_id = logement_config['logement_id']
            property_id = logement_config['superhote_property_id']
            default_price = logement_config.get('default_price')
            weekend_price = logement_config.get('weekend_price')

            if not default_price:
                logger.warning(f"Pas de prix par defaut pour logement {logement_id}")
                return

            today = datetime.now().date()
            end_date = today + timedelta(days=90)

            with db.cursor() as cursor:
                # Verifier s'il y a deja des mises a jour en attente
                cursor.execute("""
                    SELECT COUNT(*) as count FROM superhote_price_updates
                    WHERE logement_id = %s AND status = 'pending'
                """, (logement_id,))
                result = cursor.fetchone()

                if result and result['count'] > 0:
                    logger.info(f"Mises a jour deja en attente pour logement {logement_id}")
                    return

                # Creer la mise a jour pour le prix de base
                cursor.execute("""
                    INSERT INTO superhote_price_updates
                    (logement_id, superhote_property_id, date_start, date_end, price, status, created_by)
                    VALUES (%s, %s, %s, %s, %s, 'pending', 'daemon_auto_sync')
                """, (logement_id, property_id, today, end_date, default_price))

                # Si prix weekend different, creer des mises a jour pour les weekends
                if weekend_price and weekend_price != default_price:
                    current = today
                    while current <= end_date:
                        # Samedi = 5, Dimanche = 6
                        if current.weekday() in [5, 6]:
                            cursor.execute("""
                                INSERT INTO superhote_price_updates
                                (logement_id, superhote_property_id, date_start, date_end, price, status, priority, created_by)
                                VALUES (%s, %s, %s, %s, %s, 'pending', 10, 'daemon_auto_sync')
                            """, (logement_id, property_id, current, current, weekend_price))
                        current += timedelta(days=1)

                # Mettre a jour la date de derniere sync
                cursor.execute("""
                    UPDATE superhote_config SET last_sync_at = NOW() WHERE logement_id = %s
                """, (logement_id,))

                db.commit()
                logger.info(f"Mises a jour auto-sync creees pour logement {logement_id}")

        except Exception as e:
            logger.error(f"Erreur creation auto-sync: {e}")
            db.rollback()
        finally:
            db.close()

    def log_daemon_activity(self, action: str, status: str, message: str = None, details: Dict = None):
        """
        Enregistre l'activite du daemon dans la base.

        Args:
            action: Type d'action
            status: Statut (started, success, failed, warning)
            message: Message descriptif
            details: Details supplementaires
        """
        db = self.get_db_connection()
        if not db:
            return

        try:
            import json
            with db.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO superhote_automation_logs
                    (action, status, message, details)
                    VALUES (%s, %s, %s, %s)
                """, (
                    action, status, message,
                    json.dumps(details) if details else None
                ))
                db.commit()
        except pymysql.err.ProgrammingError:
            # Table n'existe pas
            pass
        except Exception as e:
            logger.error(f"Erreur log activite: {e}")
        finally:
            db.close()

    def run_update_cycle(self) -> bool:
        """
        Execute un cycle de mise a jour des prix.

        Returns:
            True si des mises a jour ont ete effectuees
        """
        pending_count = self.check_pending_updates()

        if pending_count == 0:
            logger.debug("Aucune mise a jour en attente")
            return False

        logger.info(f"Lancement du cycle de mise a jour ({pending_count} en attente)")
        self.log_daemon_activity("update_cycle", "started",
                                 f"{pending_count} mises a jour en attente")

        try:
            updater = SuperhotePriceUpdater()
            success, failures = updater.process_pending_updates()

            self.log_daemon_activity(
                "update_cycle", "success" if failures == 0 else "warning",
                f"{success} succes, {failures} echecs",
                {"success": success, "failures": failures}
            )

            return success > 0 or failures > 0

        except Exception as e:
            logger.error(f"Erreur lors du cycle de mise a jour: {e}")
            self.log_daemon_activity("update_cycle", "failed", str(e))
            return False

    def run_auto_sync_cycle(self):
        """Execute un cycle de synchronisation automatique."""
        logements = self.check_auto_sync_logements()

        if not logements:
            logger.debug("Aucun logement a synchroniser automatiquement")
            return

        logger.info(f"Auto-sync: {len(logements)} logement(s) a traiter")

        for logement in logements:
            try:
                logger.info(f"Auto-sync pour: {logement.get('nom_du_logement', logement['logement_id'])}")
                self.create_auto_sync_updates(logement)
            except Exception as e:
                logger.error(f"Erreur auto-sync logement {logement['logement_id']}: {e}")

    def run(self):
        """Boucle principale du daemon."""
        logger.info("=== Demarrage du daemon Superhote ===")
        self.log_daemon_activity("daemon", "started", "Daemon demarre")

        cycle_count = 0

        while self.running:
            try:
                cycle_count += 1
                logger.info(f"--- Cycle {cycle_count} ---")

                # 1. Verifier les logements avec auto-sync
                self.run_auto_sync_cycle()

                # 2. Executer les mises a jour en attente
                if self.check_pending_updates() > 0:
                    self.run_update_cycle()

                # 3. Attendre avant le prochain cycle
                logger.info(f"Attente de {self.check_interval} secondes...")

                # Attente interruptible
                for _ in range(self.check_interval):
                    if not self.running:
                        break
                    time.sleep(1)

            except Exception as e:
                logger.error(f"Erreur dans le cycle principal: {e}")
                self.log_daemon_activity("daemon", "warning", f"Erreur cycle: {e}")
                time.sleep(60)  # Attendre 1 minute en cas d'erreur

        logger.info("=== Arret du daemon Superhote ===")
        self.log_daemon_activity("daemon", "success", "Daemon arrete proprement")


def main():
    """Point d'entree du script."""
    # Verifier si on est en mode test
    if len(sys.argv) > 1 and sys.argv[1] == "--test":
        print("Mode test: verification de la configuration...")

        daemon = SuperhoteDaemon()
        pending = daemon.check_pending_updates()
        print(f"Mises a jour en attente: {pending}")

        auto_sync = daemon.check_auto_sync_logements()
        print(f"Logements avec auto-sync: {len(auto_sync)}")

        print("Test termine.")
        return

    # Mode daemon normal
    daemon = SuperhoteDaemon()
    daemon.run()


if __name__ == "__main__":
    main()
