#!/usr/bin/env python3
# coding: utf-8
"""
Superhote Worker Daemon - Workers persistants avec sessions reutilisees.

Architecture:
- N workers fixes (configurable)
- Sessions Selenium persistantes (pas de re-login a chaque tache)
- Queue de taches dans la BDD (superhote_price_updates)
- Healthcheck automatique et reconnexion si necessaire

Usage:
    python superhote_daemon_v2.py --workers 3 --interval 30
    python superhote_daemon_v2.py -w 2 -i 60 --groups
"""

import os
import sys
import time
import logging
import configparser
import pymysql
import signal
import argparse
import threading
import queue
from datetime import datetime, timedelta
from typing import Optional, Dict, List, Tuple, Any
from pathlib import Path
from dataclasses import dataclass, field
from enum import Enum

# ============================================================================
# CONFIGURATION
# ============================================================================

BASE_DIR = Path(__file__).parent.parent.parent
CONFIG_DIR = BASE_DIR / "config"
LOG_DIR = BASE_DIR / "logs"
LOG_DIR.mkdir(exist_ok=True)

# Configuration par defaut
DEFAULT_CONFIG = {
    "num_workers": 2,
    "poll_interval": 30,  # secondes entre chaque verification de la queue
    "session_timeout": 1800,  # 30 minutes avant de forcer un refresh
    "max_tasks_per_session": 50,  # nombre max de taches avant refresh session
    "retry_delay": 5,  # delai entre les tentatives en cas d'erreur
    "healthcheck_interval": 300,  # 5 minutes entre chaque healthcheck
}


# ============================================================================
# LOGGING
# ============================================================================

def setup_logging(name: str = "superhote_daemon_v2") -> logging.Logger:
    """Configure le logging pour le daemon."""
    logger = logging.getLogger(name)

    # Eviter les doublons si deja configure
    if logger.handlers:
        return logger

    logger.setLevel(logging.INFO)
    logger.propagate = False  # Eviter propagation vers root logger

    # Handler fichier
    file_handler = logging.FileHandler(LOG_DIR / f"{name}.log")
    file_handler.setFormatter(logging.Formatter(
        "%(asctime)s - %(levelname)s - [%(threadName)s] %(message)s"
    ))
    logger.addHandler(file_handler)

    # Handler console (seulement si pas en mode nohup)
    import sys
    if sys.stdout.isatty():
        console_handler = logging.StreamHandler()
        console_handler.setFormatter(logging.Formatter(
            "%(asctime)s - %(levelname)s - [%(threadName)s] %(message)s"
        ))
        logger.addHandler(console_handler)

    return logger


logger = setup_logging()


# ============================================================================
# DATABASE
# ============================================================================

def get_db_connection() -> Optional[pymysql.Connection]:
    """Obtient une connexion a la base de donnees."""
    config = configparser.ConfigParser()
    config.read(CONFIG_DIR / "config.ini")

    try:
        return pymysql.connect(
            host=config.get("DATABASE", "host"),
            user=config.get("DATABASE", "user"),
            password=config.get("DATABASE", "password"),
            database=config.get("DATABASE", "database"),
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=True,
            connect_timeout=10
        )
    except Exception as e:
        logger.error(f"Erreur connexion BDD: {e}")
        return None


# ============================================================================
# WORKER STATE
# ============================================================================

class WorkerState(Enum):
    """Etats possibles d'un worker."""
    IDLE = "idle"
    STARTING = "starting"
    CONNECTED = "connected"
    PROCESSING = "processing"
    RECONNECTING = "reconnecting"
    ERROR = "error"
    STOPPED = "stopped"


@dataclass
class WorkerStats:
    """Statistiques d'un worker."""
    tasks_completed: int = 0
    tasks_failed: int = 0
    session_start: Optional[datetime] = None
    last_activity: Optional[datetime] = None
    reconnect_count: int = 0
    current_task: Optional[str] = None


# ============================================================================
# TASK QUEUE MANAGER
# ============================================================================

class TaskQueueManager:
    """
    Gestionnaire de la queue de taches.
    Lit les taches depuis la BDD et les distribue aux workers.
    """

    def __init__(self, batch_size: int = 10):
        self.batch_size = batch_size
        self._lock = threading.Lock()

    def get_pending_tasks(self, worker_id: int, logement_ids: List[int] = None) -> List[Dict]:
        """
        Recupere et reserve les taches en attente pour un worker.
        Compatible avec MariaDB < 10.6 (sans SKIP LOCKED).

        Approche: UPDATE d'abord pour reserver, puis SELECT pour recuperer.

        Args:
            worker_id: ID du worker
            logement_ids: Liste des logements assignes au worker (optionnel)

        Returns:
            Liste des taches reservees
        """
        db = get_db_connection()
        if not db:
            return []

        try:
            with self._lock:  # Eviter les race conditions entre workers
                with db.cursor() as cursor:
                    # Etape 1: Reserver les taches avec UPDATE
                    # On utilise un marqueur unique pour ce worker
                    worker_marker = f"Worker_{worker_id}_{int(time.time())}"

                    if logement_ids:
                        placeholders = ",".join(["%s"] * len(logement_ids))
                        cursor.execute(f"""
                            UPDATE superhote_price_updates
                            SET status = 'processing',
                                error_message = %s,
                                updated_at = NOW()
                            WHERE status = 'pending'
                            AND logement_id IN ({placeholders})
                            ORDER BY date_start ASC
                            LIMIT %s
                        """, [worker_marker] + logement_ids + [self.batch_size])
                    else:
                        cursor.execute("""
                            UPDATE superhote_price_updates
                            SET status = 'processing',
                                error_message = %s,
                                updated_at = NOW()
                            WHERE status = 'pending'
                            ORDER BY date_start ASC
                            LIMIT %s
                        """, (worker_marker, self.batch_size))

                    db.commit()

                    # Etape 2: Recuperer les taches qu'on vient de reserver
                    cursor.execute("""
                        SELECT spu.id, spu.logement_id, spu.superhote_property_id,
                               spu.date_start, spu.date_end, spu.price, spu.nom_du_logement,
                               sc.superhote_property_name
                        FROM superhote_price_updates spu
                        LEFT JOIN superhote_config sc ON spu.logement_id = sc.logement_id
                        WHERE spu.status = 'processing'
                        AND spu.error_message = %s
                        ORDER BY spu.date_start ASC
                    """, (worker_marker,))

                    tasks = cursor.fetchall()

                    if tasks:
                        logger.info(f"Worker {worker_id}: {len(tasks)} taches reservees")

                    return tasks

        except Exception as e:
            logger.error(f"Erreur recuperation taches: {e}")
            return []
        finally:
            db.close()

    def mark_completed(self, task_ids: List[int]):
        """Marque des taches comme completees."""
        if not task_ids:
            return

        db = get_db_connection()
        if not db:
            return

        try:
            with db.cursor() as cursor:
                placeholders = ",".join(["%s"] * len(task_ids))
                cursor.execute(f"""
                    UPDATE superhote_price_updates
                    SET status = 'completed', updated_at = NOW()
                    WHERE id IN ({placeholders})
                """, task_ids)
        except Exception as e:
            logger.error(f"Erreur marquage completed: {e}")
        finally:
            db.close()

    def mark_failed(self, task_ids: List[int], error: str):
        """Marque des taches comme echouees."""
        if not task_ids:
            return

        db = get_db_connection()
        if not db:
            return

        try:
            with db.cursor() as cursor:
                placeholders = ",".join(["%s"] * len(task_ids))
                cursor.execute(f"""
                    UPDATE superhote_price_updates
                    SET status = 'failed', error_message = %s, updated_at = NOW()
                    WHERE id IN ({placeholders})
                """, [error] + task_ids)
        except Exception as e:
            logger.error(f"Erreur marquage failed: {e}")
        finally:
            db.close()

    def release_stale_tasks(self, max_age_minutes: int = 30):
        """
        Libere les taches 'processing' bloquees depuis trop longtemps.
        Utile en cas de crash d'un worker.
        """
        db = get_db_connection()
        if not db:
            return

        try:
            with db.cursor() as cursor:
                cursor.execute("""
                    UPDATE superhote_price_updates
                    SET status = 'pending', error_message = 'Released - worker timeout'
                    WHERE status = 'processing'
                    AND updated_at < DATE_SUB(NOW(), INTERVAL %s MINUTE)
                """, (max_age_minutes,))

                if cursor.rowcount > 0:
                    logger.warning(f"Libere {cursor.rowcount} taches bloquees")
        except Exception as e:
            logger.error(f"Erreur liberation taches: {e}")
        finally:
            db.close()

    def get_queue_stats(self) -> Dict:
        """Retourne les statistiques de la queue."""
        db = get_db_connection()
        if not db:
            return {}

        try:
            with db.cursor() as cursor:
                cursor.execute("""
                    SELECT status, COUNT(*) as count
                    FROM superhote_price_updates
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    GROUP BY status
                """)

                stats = {row["status"]: row["count"] for row in cursor.fetchall()}
                return stats
        except Exception as e:
            logger.error(f"Erreur stats queue: {e}")
            return {}
        finally:
            db.close()


# ============================================================================
# PERSISTENT WORKER
# ============================================================================

class PersistentWorker(threading.Thread):
    """
    Worker persistant avec session Selenium reutilisable.

    - Demarre une session Selenium au lancement
    - Se connecte une fois et garde la session active
    - Pioche dans la queue en continu
    - Se reconnecte automatiquement si necessaire
    """

    def __init__(
        self,
        worker_id: int,
        task_queue: TaskQueueManager,
        stop_event: threading.Event,
        config: Dict,
        logement_ids: List[int] = None,
        group_name: str = None,
        reference_property: str = None,
        group_refs: Dict = None
    ):
        super().__init__(name=f"Worker-{worker_id}", daemon=True)

        self.worker_id = worker_id
        self.task_queue = task_queue
        self.stop_event = stop_event
        self.config = config
        self.logement_ids = logement_ids or []
        self.group_name = group_name
        self.reference_property = reference_property
        self.group_refs = group_refs or {}  # Mapping logement_id -> {group_name, reference_id}

        self.state = WorkerState.IDLE
        self.stats = WorkerStats()
        self.automation = None
        self._tasks_since_refresh = 0

    def run(self):
        """Boucle principale du worker."""
        logger.info(f"Worker {self.worker_id} demarre")
        self.state = WorkerState.STARTING

        try:
            # Demarrer la session Selenium
            if not self._start_session():
                logger.error(f"Worker {self.worker_id}: Echec demarrage initial")
                self.state = WorkerState.ERROR
                return

            # Boucle principale
            while not self.stop_event.is_set():
                try:
                    # Verifier si refresh necessaire
                    if self._should_refresh_session():
                        self._refresh_session()

                    # Recuperer des taches
                    self.state = WorkerState.IDLE
                    tasks = self.task_queue.get_pending_tasks(
                        self.worker_id,
                        self.logement_ids if self.logement_ids else None
                    )

                    if tasks:
                        self._process_tasks(tasks)
                    else:
                        # Pas de taches, attendre
                        time.sleep(self.config.get("poll_interval", 30))

                except Exception as e:
                    logger.error(f"Worker {self.worker_id}: Erreur boucle - {e}")
                    self.state = WorkerState.ERROR
                    time.sleep(self.config.get("retry_delay", 5))

                    # Tenter de se reconnecter
                    if not self._refresh_session():
                        logger.error(f"Worker {self.worker_id}: Echec reconnexion, arret")
                        break

        finally:
            self._cleanup()
            self.state = WorkerState.STOPPED
            logger.info(f"Worker {self.worker_id} arrete - {self.stats.tasks_completed} OK, {self.stats.tasks_failed} echecs")

    def _start_session(self) -> bool:
        """Demarre une nouvelle session Selenium et se connecte."""
        try:
            # Importer ici pour eviter les problemes de thread
            from superhote_base import SuperhoteAutomation

            self.automation = SuperhoteAutomation()

            if not self.automation.start():
                logger.error(f"Worker {self.worker_id}: Echec demarrage Selenium")
                return False

            if not self.automation.login():
                logger.error(f"Worker {self.worker_id}: Echec login Superhote")
                self.automation.stop()
                return False

            self.state = WorkerState.CONNECTED
            self.stats.session_start = datetime.now()
            self.stats.last_activity = datetime.now()
            self._tasks_since_refresh = 0

            logger.info(f"Worker {self.worker_id}: Session Selenium active")
            return True

        except Exception as e:
            logger.error(f"Worker {self.worker_id}: Erreur demarrage session - {e}")
            return False

    def _should_refresh_session(self) -> bool:
        """Verifie si la session doit etre rafraichie."""
        if not self.stats.session_start:
            return True

        # Timeout de session
        session_age = (datetime.now() - self.stats.session_start).total_seconds()
        if session_age > self.config.get("session_timeout", 1800):
            logger.info(f"Worker {self.worker_id}: Session timeout ({session_age:.0f}s)")
            return True

        # Nombre max de taches
        if self._tasks_since_refresh >= self.config.get("max_tasks_per_session", 50):
            logger.info(f"Worker {self.worker_id}: Max taches atteint ({self._tasks_since_refresh})")
            return True

        return False

    def _refresh_session(self) -> bool:
        """Rafraichit la session Selenium (ferme et reouvre)."""
        logger.info(f"Worker {self.worker_id}: Refresh session...")
        self.state = WorkerState.RECONNECTING
        self.stats.reconnect_count += 1

        # Fermer l'ancienne session
        self._cleanup()

        # Attendre un peu
        time.sleep(2)

        # Demarrer une nouvelle session
        return self._start_session()

    def _process_tasks(self, tasks: List[Dict]):
        """Traite un lot de taches."""
        self.state = WorkerState.PROCESSING

        # Regrouper par prix/date pour optimiser
        grouped = self._group_tasks(tasks)

        for group in grouped:
            if self.stop_event.is_set():
                # Remettre les taches non traitees en pending
                remaining_ids = [t["id"] for t in group["tasks"]]
                self.task_queue.mark_failed(remaining_ids, "Worker stopped")
                break

            self.stats.current_task = f"{group['date_start']} - {group['price']}EUR"
            self.stats.last_activity = datetime.now()

            try:
                success = self._apply_price_update(group)

                if success:
                    self.task_queue.mark_completed(group["task_ids"])
                    self.stats.tasks_completed += len(group["task_ids"])
                else:
                    self.task_queue.mark_failed(group["task_ids"], "Echec application prix")
                    self.stats.tasks_failed += len(group["task_ids"])

                self._tasks_since_refresh += 1

            except Exception as e:
                logger.error(f"Worker {self.worker_id}: Erreur traitement - {e}")
                self.task_queue.mark_failed(group["task_ids"], str(e))
                self.stats.tasks_failed += len(group["task_ids"])

            # Petite pause entre les groupes
            time.sleep(1)

        self.stats.current_task = None

    def _group_tasks(self, tasks: List[Dict]) -> List[Dict]:
        """Regroupe les taches par prix et date pour optimiser."""
        from collections import defaultdict

        groups = defaultdict(list)

        for task in tasks:
            price = float(task["price"])
            date_start = str(task["date_start"])
            date_end = str(task["date_end"])

            key = (price, date_start, date_end)
            groups[key].append(task)

        result = []
        for (price, date_start, date_end), group_tasks in groups.items():
            result.append({
                "price": price,
                "date_start": date_start,
                "date_end": date_end,
                "tasks": group_tasks,
                "task_ids": [t["id"] for t in group_tasks],
                "property_names": [t.get("superhote_property_name") or t.get("nom_du_logement") or t.get("superhote_property_id") for t in group_tasks],
                "superhote_ids": [t.get("superhote_property_id") for t in group_tasks]
            })

        return result

    def _apply_price_update(self, group: Dict) -> bool:
        """Applique une mise a jour de prix via Selenium."""
        price = group["price"]
        date_start = datetime.strptime(group["date_start"], "%Y-%m-%d")
        date_end = datetime.strptime(group["date_end"], "%Y-%m-%d")
        property_names = group["property_names"]
        superhote_ids = [sid for sid in group["superhote_ids"] if sid]
        logement_ids = [t.get("logement_id") for t in group["tasks"]]

        # Chercher la reference du groupe pour le premier logement
        reference_property = self.reference_property  # Mode groupe fixe
        if not reference_property and self.group_refs and logement_ids:
            # Mode pool: chercher dynamiquement la reference
            first_logement = logement_ids[0]
            if first_logement in self.group_refs:
                ref_info = self.group_refs[first_logement]
                reference_property = ref_info.get("reference_id")
                logger.info(f"Worker {self.worker_id}: Utilise reference {reference_property} (groupe {ref_info.get('group_name')})")

        logger.info(
            f"Worker {self.worker_id}: Maj prix {date_start.strftime('%d/%m')} - "
            f"{date_end.strftime('%d/%m')} = {price}EUR pour {len(property_names)} logements"
        )

        try:
            # Mode avec logement de reference (groupe fixe ou dynamique)
            if reference_property:
                results = self.automation.update_price_for_multiple_properties(
                    property_names=superhote_ids or property_names,
                    start_date=date_start,
                    end_date=date_end,
                    price=price,
                    reference_property=reference_property
                )
                return any(results.values()) if results else False

            # Plusieurs logements sans reference
            elif len(superhote_ids) > 1 or len(property_names) > 1:
                props = superhote_ids if superhote_ids else property_names
                results = self.automation.update_price_for_multiple_properties(
                    property_names=props,
                    start_date=date_start,
                    end_date=date_end,
                    price=price
                )
                return any(results.values()) if results else False

            # Un seul logement
            else:
                property_id = superhote_ids[0] if superhote_ids else property_names[0]
                return self.automation.update_price_for_dates(
                    property_id=property_id,
                    start_date=date_start,
                    end_date=date_end,
                    price=price
                )

        except Exception as e:
            logger.error(f"Worker {self.worker_id}: Erreur Selenium - {e}")
            return False

    def _cleanup(self):
        """Nettoie les ressources."""
        if self.automation:
            try:
                self.automation.stop()
            except:
                pass
            self.automation = None

    def get_status(self) -> Dict:
        """Retourne le statut du worker."""
        return {
            "worker_id": self.worker_id,
            "state": self.state.value,
            "group_name": self.group_name,
            "logement_ids": self.logement_ids,
            "tasks_completed": self.stats.tasks_completed,
            "tasks_failed": self.stats.tasks_failed,
            "reconnect_count": self.stats.reconnect_count,
            "current_task": self.stats.current_task,
            "session_age": (datetime.now() - self.stats.session_start).total_seconds() if self.stats.session_start else 0,
            "is_alive": self.is_alive()
        }


# ============================================================================
# WORKER DAEMON
# ============================================================================

class SuperhoteDaemon:
    """
    Daemon principal gerant les workers persistants.

    - Lance N workers au demarrage
    - Surveille leur sante
    - Relance les workers morts
    - Libere les taches bloquees
    """

    def __init__(self, config: Dict):
        self.config = config
        self.stop_event = threading.Event()
        self.task_queue = TaskQueueManager(batch_size=10)
        self.workers: List[PersistentWorker] = []
        self._healthcheck_thread = None

    def setup_workers(self) -> int:
        """Configure et lance les workers."""
        num_workers = self.config.get("num_workers", 2)
        use_groups = self.config.get("use_groups", False)

        # Nouveau mode par defaut: pool partage (workers piochent toutes les taches)
        logger.info(f"Configuration: {num_workers} workers, mode={'groupe' if use_groups else 'pool'}")

        if use_groups:
            # Ancien mode: 1 worker par groupe (consomme plus de memoire)
            return self._setup_group_workers(num_workers)
        else:
            # Nouveau mode: workers partages qui piochent toutes les taches
            return self._setup_pool_workers(num_workers)

    def _setup_pool_workers(self, num_workers: int) -> int:
        """Configure les workers en mode pool (tous piochent dans la meme queue)."""
        # Charger les infos de groupes pour les workers
        group_refs = self._load_group_references()

        for i in range(num_workers):
            worker = PersistentWorker(
                worker_id=i + 1,
                task_queue=self.task_queue,
                stop_event=self.stop_event,
                config=self.config,
                logement_ids=None,  # Pas de filtre - pioche tout
                group_refs=group_refs  # Passe les references de groupes
            )
            self.workers.append(worker)
            logger.info(f"Worker {i+1} [POOL]: pioche toutes les taches")

        return len(self.workers)

    def _load_group_references(self) -> Dict:
        """Charge les references de groupes depuis la BDD."""
        db = get_db_connection()
        if not db:
            return {}

        try:
            with db.cursor() as cursor:
                # Mapping logement_id -> reference_superhote_id
                cursor.execute("""
                    SELECT sc.logement_id, g.nom as group_name,
                           ref_sc.superhote_property_id as reference_id
                    FROM superhote_config sc
                    JOIN superhote_groups g ON sc.groupe = g.nom
                    JOIN superhote_config ref_sc ON g.logement_reference_id = ref_sc.logement_id
                    WHERE sc.is_active = 1 AND g.is_active = 1
                """)

                refs = {}
                for row in cursor.fetchall():
                    refs[row["logement_id"]] = {
                        "group_name": row["group_name"],
                        "reference_id": row["reference_id"]
                    }

                logger.info(f"Charge {len(refs)} logements avec reference de groupe")
                return refs
        except Exception as e:
            logger.error(f"Erreur chargement references: {e}")
            return {}
        finally:
            db.close()

    def _setup_standard_workers(self, num_workers: int) -> int:
        """Configure les workers en mode standard (partage equitable des logements)."""
        # Recuperer tous les logements actifs
        db = get_db_connection()
        if not db:
            return 0

        try:
            with db.cursor() as cursor:
                cursor.execute("""
                    SELECT l.id, l.nom_du_logement, sc.superhote_property_id
                    FROM liste_logements l
                    INNER JOIN superhote_config sc ON l.id = sc.logement_id
                    WHERE sc.is_active = 1 AND sc.superhote_property_id IS NOT NULL
                    ORDER BY l.id
                """)
                logements = cursor.fetchall()
        finally:
            db.close()

        if not logements:
            logger.warning("Aucun logement actif")
            return 0

        # Repartir les logements entre les workers
        logement_ids = [l["id"] for l in logements]
        chunk_size = max(1, len(logement_ids) // num_workers)

        for i in range(num_workers):
            start_idx = i * chunk_size
            end_idx = start_idx + chunk_size if i < num_workers - 1 else len(logement_ids)
            worker_logements = logement_ids[start_idx:end_idx]

            if worker_logements:
                worker = PersistentWorker(
                    worker_id=i + 1,
                    task_queue=self.task_queue,
                    stop_event=self.stop_event,
                    config=self.config,
                    logement_ids=worker_logements
                )
                self.workers.append(worker)
                logger.info(f"Worker {i+1}: {len(worker_logements)} logements")

        return len(self.workers)

    def _setup_group_workers(self, max_workers: int) -> int:
        """Configure les workers en mode groupe."""
        db = get_db_connection()
        if not db:
            return 0

        try:
            with db.cursor() as cursor:
                # Recuperer les groupes avec leur reference
                cursor.execute("""
                    SELECT g.nom, g.logement_reference_id,
                           sc.superhote_property_id as reference_superhote_id
                    FROM superhote_groups g
                    LEFT JOIN superhote_config sc ON g.logement_reference_id = sc.logement_id
                    WHERE g.is_active = 1
                """)
                groups = cursor.fetchall()

                worker_id = 0
                for group in groups:
                    if worker_id >= max_workers:
                        break

                    group_name = group["nom"]
                    reference = group.get("reference_superhote_id")

                    # Recuperer les logements du groupe
                    cursor.execute("""
                        SELECT l.id FROM liste_logements l
                        INNER JOIN superhote_config sc ON l.id = sc.logement_id
                        WHERE sc.groupe = %s AND sc.is_active = 1
                    """, (group_name,))
                    logement_ids = [r["id"] for r in cursor.fetchall()]

                    if logement_ids and reference:
                        worker_id += 1
                        worker = PersistentWorker(
                            worker_id=worker_id,
                            task_queue=self.task_queue,
                            stop_event=self.stop_event,
                            config=self.config,
                            logement_ids=logement_ids,
                            group_name=group_name,
                            reference_property=reference
                        )
                        self.workers.append(worker)
                        logger.info(f"Worker {worker_id} [GROUPE {group_name}]: {len(logement_ids)} logements, ref={reference}")

                # Workers pour les logements orphelins si places disponibles
                if worker_id < max_workers:
                    cursor.execute("""
                        SELECT l.id FROM liste_logements l
                        INNER JOIN superhote_config sc ON l.id = sc.logement_id
                        WHERE sc.is_active = 1 AND (sc.groupe IS NULL OR sc.groupe = '')
                    """)
                    orphan_ids = [r["id"] for r in cursor.fetchall()]

                    if orphan_ids:
                        remaining_workers = max_workers - worker_id
                        chunk_size = max(1, len(orphan_ids) // remaining_workers)

                        for i in range(remaining_workers):
                            start = i * chunk_size
                            end = start + chunk_size if i < remaining_workers - 1 else len(orphan_ids)
                            worker_logements = orphan_ids[start:end]

                            if worker_logements:
                                worker_id += 1
                                worker = PersistentWorker(
                                    worker_id=worker_id,
                                    task_queue=self.task_queue,
                                    stop_event=self.stop_event,
                                    config=self.config,
                                    logement_ids=worker_logements
                                )
                                self.workers.append(worker)
                                logger.info(f"Worker {worker_id} [ORPHELINS]: {len(worker_logements)} logements")

        finally:
            db.close()

        return len(self.workers)

    def start(self):
        """Demarre le daemon et tous les workers."""
        logger.info("=" * 60)
        logger.info("DEMARRAGE SUPERHOTE DAEMON V2")
        logger.info(f"  Workers: {self.config.get('num_workers', 2)}")
        logger.info(f"  Interval: {self.config.get('poll_interval', 30)}s")
        logger.info(f"  Session timeout: {self.config.get('session_timeout', 1800)}s")
        logger.info("=" * 60)

        # Liberer les taches bloquees au demarrage
        self.task_queue.release_stale_tasks()

        # Configurer les workers
        num_workers = self.setup_workers()

        if num_workers == 0:
            logger.error("Aucun worker configure, arret")
            return

        # Demarrer les workers
        for worker in self.workers:
            worker.start()
            time.sleep(2)  # Petit delai entre chaque demarrage

        # Demarrer le healthcheck
        self._healthcheck_thread = threading.Thread(
            target=self._healthcheck_loop,
            name="Healthcheck",
            daemon=True
        )
        self._healthcheck_thread.start()

        logger.info(f"{num_workers} workers demarres")

        # Attendre l'arret
        try:
            while not self.stop_event.is_set():
                time.sleep(1)
        except KeyboardInterrupt:
            logger.info("Arret demande (Ctrl+C)")

        self.stop()

    def _healthcheck_loop(self):
        """Boucle de healthcheck des workers."""
        interval = self.config.get("healthcheck_interval", 300)

        while not self.stop_event.is_set():
            time.sleep(interval)

            if self.stop_event.is_set():
                break

            # Verifier chaque worker
            for i, worker in enumerate(self.workers):
                if not worker.is_alive():
                    logger.warning(f"Worker {worker.worker_id} mort, relance...")

                    # Creer un nouveau worker avec les memes parametres
                    new_worker = PersistentWorker(
                        worker_id=worker.worker_id,
                        task_queue=self.task_queue,
                        stop_event=self.stop_event,
                        config=self.config,
                        logement_ids=worker.logement_ids,
                        group_name=worker.group_name,
                        reference_property=worker.reference_property
                    )
                    new_worker.start()
                    self.workers[i] = new_worker

            # Liberer les taches bloquees
            self.task_queue.release_stale_tasks()

            # Rafraichir les references de groupes (si modifiees dans le PHP)
            updated_refs = self._load_group_references()
            if updated_refs:
                for worker in self.workers:
                    worker.group_refs = updated_refs

            # Log des stats
            stats = self.task_queue.get_queue_stats()
            logger.info(f"Queue stats: {stats}")

    def stop(self):
        """Arrete le daemon et tous les workers."""
        logger.info("Arret du daemon...")
        self.stop_event.set()

        # Attendre que les workers se terminent
        for worker in self.workers:
            worker.join(timeout=10)

        logger.info("Daemon arrete")

    def get_status(self) -> Dict:
        """Retourne le statut complet du daemon."""
        return {
            "running": not self.stop_event.is_set(),
            "workers": [w.get_status() for w in self.workers],
            "queue": self.task_queue.get_queue_stats()
        }


# ============================================================================
# MAIN
# ============================================================================

def main():
    parser = argparse.ArgumentParser(
        description="Superhote Daemon V2 - Workers persistants",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Exemples:
  python superhote_daemon_v2.py -w 3              # 3 workers, mode standard
  python superhote_daemon_v2.py -w 2 --groups     # 2 workers, mode groupe
  python superhote_daemon_v2.py -w 4 -i 60        # 4 workers, poll toutes les 60s
        """
    )

    parser.add_argument(
        "-w", "--workers",
        type=int,
        default=2,
        help="Nombre de workers (defaut: 2)"
    )
    parser.add_argument(
        "-i", "--interval",
        type=int,
        default=30,
        help="Intervalle de polling en secondes (defaut: 30)"
    )
    parser.add_argument(
        "-g", "--groups",
        action="store_true",
        help="Mode groupe (un worker par groupe configure)"
    )
    parser.add_argument(
        "--session-timeout",
        type=int,
        default=1800,
        help="Timeout session Selenium en secondes (defaut: 1800 = 30min)"
    )
    parser.add_argument(
        "--max-tasks",
        type=int,
        default=50,
        help="Nombre max de taches par session avant refresh (defaut: 50)"
    )
    parser.add_argument(
        "--status",
        action="store_true",
        help="Affiche le statut de la queue et quitte"
    )

    args = parser.parse_args()

    # Mode status seulement
    if args.status:
        queue = TaskQueueManager()
        stats = queue.get_queue_stats()
        print("=== Queue Status ===")
        for status, count in stats.items():
            print(f"  {status}: {count}")
        return

    # Configuration
    config = {
        "num_workers": args.workers,
        "poll_interval": args.interval,
        "use_groups": args.groups,
        "session_timeout": args.session_timeout,
        "max_tasks_per_session": args.max_tasks,
        "healthcheck_interval": 300,
        "retry_delay": 5
    }

    # Gerer les signaux
    daemon = SuperhoteDaemon(config)

    def signal_handler(signum, frame):
        logger.info("Signal d'arret recu")
        daemon.stop()

    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    # Demarrer
    daemon.start()


if __name__ == "__main__":
    main()
