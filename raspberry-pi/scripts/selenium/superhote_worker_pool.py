#!/usr/bin/env python3
# coding: utf-8
"""
Pool de workers pour la mise a jour parallele des prix Superhote.
Chaque worker gere un groupe de logements avec sa propre session Selenium.
"""

import os
import sys
import time
import logging
import configparser
import pymysql
import signal
import argparse
from datetime import datetime, timedelta
from typing import Optional, Dict, List, Tuple
from pathlib import Path
from multiprocessing import Process, Queue, Manager
from concurrent.futures import ProcessPoolExecutor, as_completed

# Configuration du logging pour le pool
LOG_DIR = Path(__file__).parent.parent.parent / "logs"
LOG_DIR.mkdir(exist_ok=True)

# Logger specifique pour le pool
pool_logger = logging.getLogger("superhote_pool")
pool_logger.setLevel(logging.INFO)
pool_handler = logging.FileHandler(LOG_DIR / "superhote_worker_pool.log")
pool_handler.setFormatter(logging.Formatter("%(asctime)s - %(levelname)s - [%(process)d] %(message)s"))
pool_logger.addHandler(pool_handler)

# Aussi afficher dans la console
console_handler = logging.StreamHandler()
console_handler.setFormatter(logging.Formatter("%(asctime)s - %(levelname)s - [%(process)d] %(message)s"))
pool_logger.addHandler(console_handler)

# Config
CONFIG_DIR = Path(__file__).parent.parent.parent / "config"


class WorkerConfig:
    """Configuration pour un worker."""
    def __init__(self, worker_id: int, logement_ids: List[int], logement_names: List[str],
                 group_name: str = None, reference_property: str = None):
        self.worker_id = worker_id
        self.logement_ids = logement_ids
        self.logement_names = logement_names
        # Mode groupe: utilise un logement de reference pour ouvrir la modale
        self.group_name = group_name
        self.reference_property = reference_property  # Nom Superhote du logement de reference


def get_db_connection():
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
            cursorclass=pymysql.cursors.DictCursor
        )
    except Exception as e:
        pool_logger.error(f"Erreur connexion BDD: {e}")
        return None


def get_all_active_logements() -> List[Dict]:
    """Recupere tous les logements actifs."""
    db = get_db_connection()
    if not db:
        return []

    try:
        with db.cursor() as cursor:
            cursor.execute("""
                SELECT
                    l.id,
                    l.nom_du_logement,
                    sc.superhote_property_id,
                    sc.groupe
                FROM liste_logements l
                INNER JOIN superhote_config sc ON l.id = sc.logement_id
                WHERE sc.is_active = 1 AND sc.superhote_property_id IS NOT NULL
                ORDER BY l.id
            """)
            return cursor.fetchall()
    except Exception as e:
        pool_logger.error(f"Erreur recuperation logements: {e}")
        return []
    finally:
        db.close()


def get_groups_with_reference() -> List[Dict]:
    """Recupere tous les groupes avec leur logement de reference."""
    db = get_db_connection()
    if not db:
        return []

    try:
        with db.cursor() as cursor:
            cursor.execute("""
                SELECT
                    g.id,
                    g.nom,
                    g.logement_reference_id,
                    l.nom_du_logement as reference_name,
                    sc.superhote_property_id as reference_superhote_id
                FROM superhote_groups g
                LEFT JOIN liste_logements l ON g.logement_reference_id = l.id
                LEFT JOIN superhote_config sc ON g.logement_reference_id = sc.logement_id
                WHERE g.is_active = 1
                ORDER BY g.nom
            """)
            return cursor.fetchall()
    except Exception as e:
        pool_logger.error(f"Erreur recuperation groupes: {e}")
        return []
    finally:
        db.close()


def get_logements_by_group(group_name: str) -> List[Dict]:
    """Recupere tous les logements d'un groupe."""
    db = get_db_connection()
    if not db:
        return []

    try:
        with db.cursor() as cursor:
            cursor.execute("""
                SELECT
                    l.id,
                    l.nom_du_logement,
                    sc.superhote_property_id
                FROM liste_logements l
                INNER JOIN superhote_config sc ON l.id = sc.logement_id
                WHERE sc.is_active = 1
                AND sc.groupe = %s
                AND sc.superhote_property_id IS NOT NULL
                ORDER BY l.nom_du_logement
            """, (group_name,))
            return cursor.fetchall()
    except Exception as e:
        pool_logger.error(f"Erreur recuperation logements groupe {group_name}: {e}")
        return []
    finally:
        db.close()


def get_ungrouped_logements() -> List[Dict]:
    """Recupere tous les logements actifs qui ne sont pas dans un groupe."""
    db = get_db_connection()
    if not db:
        return []

    try:
        with db.cursor() as cursor:
            cursor.execute("""
                SELECT
                    l.id,
                    l.nom_du_logement,
                    sc.superhote_property_id
                FROM liste_logements l
                INNER JOIN superhote_config sc ON l.id = sc.logement_id
                WHERE sc.is_active = 1
                AND (sc.groupe IS NULL OR sc.groupe = '')
                AND sc.superhote_property_id IS NOT NULL
                ORDER BY l.nom_du_logement
            """)
            return cursor.fetchall()
    except Exception as e:
        pool_logger.error(f"Erreur recuperation logements sans groupe: {e}")
        return []
    finally:
        db.close()


def assign_logements_to_workers(logements: List[Dict], properties_per_worker: int = 6) -> List[WorkerConfig]:
    """
    Repartit les logements entre les workers.

    Args:
        logements: Liste des logements actifs
        properties_per_worker: Nombre de logements par worker (defaut: 6)

    Returns:
        Liste de WorkerConfig pour chaque worker
    """
    workers = []

    for i in range(0, len(logements), properties_per_worker):
        chunk = logements[i:i + properties_per_worker]
        worker_id = len(workers) + 1
        logement_ids = [l["id"] for l in chunk]
        logement_names = [l["nom_du_logement"] for l in chunk]

        workers.append(WorkerConfig(
            worker_id=worker_id,
            logement_ids=logement_ids,
            logement_names=logement_names
        ))

    return workers


def get_groups_with_pending_tasks() -> List[str]:
    """Recupere la liste des groupes qui ont des taches pending."""
    db = get_db_connection()
    if not db:
        return []

    try:
        with db.cursor() as cursor:
            cursor.execute("""
                SELECT DISTINCT COALESCE(sc.groupe, '__ORPHELINS__') as groupe_name
                FROM superhote_price_updates spu
                JOIN superhote_config sc ON spu.logement_id = sc.logement_id
                WHERE spu.status = 'pending'
            """)
            return [row["groupe_name"] for row in cursor.fetchall()]
    except Exception as e:
        pool_logger.error(f"Erreur recuperation groupes pending: {e}")
        return []
    finally:
        db.close()


def assign_workers_by_group(properties_per_worker: int = 6) -> List[WorkerConfig]:
    """
    Cree un worker par groupe configure + workers pour les logements sans groupe.
    Ne cree des workers que pour les groupes qui ont des taches pending.

    Mode hybride:
    - Un worker par groupe (utilise le logement de reference)
    - Des workers supplementaires pour les logements orphelins (mode standard)

    Args:
        properties_per_worker: Nombre de logements par worker pour les orphelins

    Returns:
        Liste de WorkerConfig pour chaque groupe + orphelins
    """
    workers = []
    groups = get_groups_with_reference()

    # Recuperer les groupes qui ont des pending
    groups_with_pending = get_groups_with_pending_tasks()
    pool_logger.info(f"Groupes avec pending: {groups_with_pending}")

    # 1. Workers pour les groupes (seulement ceux avec pending)
    for group in groups:
        group_name = group["nom"]

        # Ignorer les groupes sans pending
        if group_name not in groups_with_pending:
            pool_logger.info(f"Groupe {group_name}: pas de pending, ignore")
            continue

        reference_property = group.get("reference_superhote_id") or group.get("reference_name")

        if not reference_property:
            pool_logger.warning(f"Groupe {group_name}: pas de logement de reference, ignore")
            continue

        # Recuperer les logements du groupe
        logements = get_logements_by_group(group_name)

        if not logements:
            pool_logger.warning(f"Groupe {group_name}: aucun logement, ignore")
            continue

        worker_id = len(workers) + 1
        logement_ids = [l["id"] for l in logements]
        logement_names = [l["nom_du_logement"] for l in logements]

        workers.append(WorkerConfig(
            worker_id=worker_id,
            logement_ids=logement_ids,
            logement_names=logement_names,
            group_name=group_name,
            reference_property=reference_property
        ))

        pool_logger.info(f"Worker {worker_id} [GROUPE {group_name}]: {len(logements)} logements, ref={reference_property}")

    # 2. Workers pour les logements sans groupe (mode standard) - seulement si pending
    if '__ORPHELINS__' in groups_with_pending or None in groups_with_pending:
        ungrouped = get_ungrouped_logements()
        if ungrouped:
            pool_logger.info(f"Logements sans groupe: {len(ungrouped)}")

            for i in range(0, len(ungrouped), properties_per_worker):
                chunk = ungrouped[i:i + properties_per_worker]
                worker_id = len(workers) + 1
                logement_ids = [l["id"] for l in chunk]
                logement_names = [l["nom_du_logement"] for l in chunk]

                workers.append(WorkerConfig(
                    worker_id=worker_id,
                    logement_ids=logement_ids,
                    logement_names=logement_names,
                    group_name=None,  # Mode standard
                    reference_property=None
                ))

                pool_logger.info(f"Worker {worker_id} [STANDARD]: {len(chunk)} logements orphelins")

    return workers


def get_pending_updates_for_logements(logement_ids: List[int]) -> List[Dict]:
    """
    Recupere les mises a jour en attente pour une liste de logements.
    Marque les updates comme 'processing' pour eviter les conflits.
    """
    db = get_db_connection()
    if not db:
        return []

    try:
        with db.cursor() as cursor:
            # Recuperer les updates pending pour ces logements
            placeholders = ",".join(["%s"] * len(logement_ids))
            cursor.execute(f"""
                SELECT
                    spu.id,
                    spu.logement_id,
                    spu.superhote_property_id,
                    spu.date_start,
                    spu.date_end,
                    spu.price,
                    spu.nom_du_logement,
                    l.nom_du_logement as logement_name
                FROM superhote_price_updates spu
                LEFT JOIN liste_logements l ON spu.logement_id = l.id
                WHERE spu.status = 'pending'
                AND spu.logement_id IN ({placeholders})
                ORDER BY spu.date_start ASC, spu.price ASC
            """, logement_ids)

            updates = cursor.fetchall()

            # Marquer comme 'processing'
            if updates:
                update_ids = [u["id"] for u in updates]
                placeholders = ",".join(["%s"] * len(update_ids))
                cursor.execute(f"""
                    UPDATE superhote_price_updates
                    SET status = 'processing', updated_at = NOW()
                    WHERE id IN ({placeholders})
                """, update_ids)
                db.commit()

            return updates
    except Exception as e:
        pool_logger.error(f"Erreur recuperation updates: {e}")
        return []
    finally:
        db.close()


def update_status(update_ids: List[int], status: str, error_message: str = None):
    """Met a jour le statut de plusieurs updates."""
    if not update_ids:
        return

    db = get_db_connection()
    if not db:
        return

    try:
        with db.cursor() as cursor:
            placeholders = ",".join(["%s"] * len(update_ids))
            if error_message:
                cursor.execute(f"""
                    UPDATE superhote_price_updates
                    SET status = %s, error_message = %s, updated_at = NOW()
                    WHERE id IN ({placeholders})
                """, [status, error_message] + update_ids)
            else:
                cursor.execute(f"""
                    UPDATE superhote_price_updates
                    SET status = %s, updated_at = NOW()
                    WHERE id IN ({placeholders})
                """, [status] + update_ids)
            db.commit()
    except Exception as e:
        pool_logger.error(f"Erreur update status: {e}")
    finally:
        db.close()


def group_updates_by_price_and_date(updates: List[Dict]) -> List[Dict]:
    """
    Regroupe les mises a jour par (prix, date_start, date_end).
    Permet d'appliquer le meme prix a plusieurs logements en une seule operation.
    """
    from collections import defaultdict

    groups = defaultdict(list)

    for update in updates:
        price = float(update["price"])
        date_start = update["date_start"]
        date_end = update["date_end"]

        # Convertir en string
        if hasattr(date_start, 'strftime'):
            date_start_str = date_start.strftime("%Y-%m-%d")
        else:
            date_start_str = str(date_start)

        if hasattr(date_end, 'strftime'):
            date_end_str = date_end.strftime("%Y-%m-%d")
        else:
            date_end_str = str(date_end)

        key = (price, date_start_str, date_end_str)
        groups[key].append(update)

    result = []
    for (price, date_start_str, date_end_str), group_updates in groups.items():
        result.append({
            "price": price,
            "date_start": date_start_str,
            "date_end": date_end_str,
            "updates": group_updates,
            "logement_names": [u.get("logement_name") or u.get("nom_du_logement") for u in group_updates],
            "superhote_property_ids": [u.get("superhote_property_id") for u in group_updates],
            "update_ids": [u["id"] for u in group_updates]
        })

    return result


def merge_consecutive_dates(update_groups: List[Dict]) -> List[Dict]:
    """
    Fusionne les groupes de mises a jour qui ont des dates consecutives et le meme prix.

    Optimisation majeure: au lieu de faire N operations pour N jours consecutifs
    avec le meme prix, on fait 1 seule operation avec date_start -> date_end.

    Ex: [2026-02-11:70€, 2026-02-12:70€, 2026-02-13:77€]
    ->  [2026-02-11 to 2026-02-12:70€, 2026-02-13:77€]
    """
    if not update_groups:
        return []

    # Trier par date_start
    sorted_groups = sorted(update_groups, key=lambda x: x["date_start"])

    merged = []
    current = None

    for group in sorted_groups:
        if current is None:
            current = {
                "price": group["price"],
                "date_start": group["date_start"],
                "date_end": group["date_end"],
                "updates": list(group["updates"]),
                "logement_names": list(group["logement_names"]),
                "superhote_property_ids": list(group["superhote_property_ids"]),
                "update_ids": list(group["update_ids"])
            }
            continue

        # Verifier si on peut fusionner
        current_end = datetime.strptime(current["date_end"], "%Y-%m-%d")
        group_start = datetime.strptime(group["date_start"], "%Y-%m-%d")

        # Dates consecutives (jour suivant) et meme prix et memes logements
        same_price = current["price"] == group["price"]
        consecutive = (group_start - current_end).days == 1
        same_logements = set(current["superhote_property_ids"]) == set(group["superhote_property_ids"])

        if same_price and consecutive and same_logements:
            # Fusionner: etendre la date de fin
            current["date_end"] = group["date_end"]
            current["updates"].extend(group["updates"])
            current["update_ids"].extend(group["update_ids"])
            pool_logger.debug(f"Fusion: {current['date_start']} - {current['date_end']} @ {current['price']}EUR")
        else:
            # Sauvegarder le groupe actuel et commencer un nouveau
            merged.append(current)
            current = {
                "price": group["price"],
                "date_start": group["date_start"],
                "date_end": group["date_end"],
                "updates": list(group["updates"]),
                "logement_names": list(group["logement_names"]),
                "superhote_property_ids": list(group["superhote_property_ids"]),
                "update_ids": list(group["update_ids"])
            }

    # Ne pas oublier le dernier groupe
    if current:
        merged.append(current)

    # Log de l'optimisation
    if len(merged) < len(update_groups):
        pool_logger.info(f"Optimisation: {len(update_groups)} -> {len(merged)} operations (fusion dates consecutives)")

    return merged


def run_worker(worker_config: WorkerConfig, stop_event = None) -> Dict:
    """
    Fonction executee par chaque worker.

    Deux modes de fonctionnement:
    1. Mode standard: traite chaque logement individuellement
    2. Mode groupe: utilise un logement de reference pour ouvrir la modale,
       puis applique les changements a tous les membres du groupe

    Args:
        worker_config: Configuration du worker (logements assignes)
        stop_event: Event pour arreter le worker proprement

    Returns:
        Statistiques du worker
    """
    worker_id = worker_config.worker_id
    logement_ids = worker_config.logement_ids
    logement_names = worker_config.logement_names
    group_name = worker_config.group_name
    reference_property = worker_config.reference_property

    is_group_mode = group_name is not None and reference_property is not None

    if is_group_mode:
        pool_logger.info(f"Worker {worker_id} [GROUPE {group_name}] demarre - Ref: {reference_property}, Membres: {logement_names}")
    else:
        pool_logger.info(f"Worker {worker_id} demarre - Logements: {logement_names}")

    stats = {
        "worker_id": worker_id,
        "group_name": group_name,
        "total_updates": 0,
        "successful": 0,
        "failed": 0,
        "logements": logement_names
    }

    # Importer ici pour eviter les problemes de multiprocessing
    from superhote_base import SuperhoteAutomation

    automation = None
    try:
        # Creer l'instance Selenium pour ce worker
        automation = SuperhoteAutomation()

        # Demarrer le driver Selenium
        if not automation.start():
            pool_logger.error(f"Worker {worker_id}: Echec demarrage Selenium")
            return stats

        # Se connecter
        if not automation.login():
            pool_logger.error(f"Worker {worker_id}: Echec connexion Superhote")
            return stats

        pool_logger.info(f"Worker {worker_id}: Connecte a Superhote")

        # Recuperer les updates pour nos logements
        updates = get_pending_updates_for_logements(logement_ids)

        if not updates:
            pool_logger.info(f"Worker {worker_id}: Aucune mise a jour en attente")
            return stats

        stats["total_updates"] = len(updates)
        pool_logger.info(f"Worker {worker_id}: {len(updates)} mises a jour a traiter")

        # Grouper par prix/date pour optimiser
        update_groups = group_updates_by_price_and_date(updates)

        # Fusionner les dates consecutives avec le meme prix (optimisation majeure)
        update_groups = merge_consecutive_dates(update_groups)
        pool_logger.info(f"Worker {worker_id}: {len(update_groups)} groupes de mises a jour (apres fusion)")

        # Traiter chaque groupe
        for update_group in update_groups:
            if stop_event and stop_event.is_set():
                pool_logger.info(f"Worker {worker_id}: Arret demande")
                break

            price = update_group["price"]
            date_start_str = update_group["date_start"]
            date_end_str = update_group["date_end"]
            property_names = update_group["logement_names"]
            superhote_ids = update_group.get("superhote_property_ids", [])
            update_ids = update_group["update_ids"]

            pool_logger.info(
                f"Worker {worker_id}: Traitement groupe {date_start_str} - {date_end_str}, "
                f"{price}EUR, {len(property_names)} logements"
            )

            try:
                # Convertir les dates
                start_date = datetime.strptime(date_start_str, "%Y-%m-%d")
                end_date = datetime.strptime(date_end_str, "%Y-%m-%d")

                # MODE GROUPE: Utiliser le logement de reference pour ouvrir la modale
                # et appliquer a tous les membres du groupe
                if is_group_mode:
                    pool_logger.info(
                        f"Worker {worker_id} [GROUPE]: Utilisation ref={reference_property} "
                        f"pour appliquer a {len(logement_names)} membres"
                    )
                    # Recuperer les noms Superhote des membres du groupe
                    db = get_db_connection()
                    member_superhote_names = []
                    if db:
                        try:
                            with db.cursor() as cursor:
                                placeholders = ",".join(["%s"] * len(logement_ids))
                                cursor.execute(f"""
                                    SELECT superhote_property_id
                                    FROM superhote_config
                                    WHERE logement_id IN ({placeholders})
                                    AND superhote_property_id IS NOT NULL
                                """, logement_ids)
                                member_superhote_names = [r["superhote_property_id"] for r in cursor.fetchall()]
                        finally:
                            db.close()

                    if member_superhote_names:
                        results = automation.update_price_for_multiple_properties(
                            property_names=member_superhote_names,
                            start_date=start_date,
                            end_date=end_date,
                            price=price,
                            reference_property=reference_property  # Utilise la ref pour ouvrir la modale
                        )
                    else:
                        pool_logger.warning(f"Worker {worker_id}: Aucun nom Superhote pour les membres")
                        results = {}

                # MODE STANDARD: traitement normal
                elif len(property_names) > 1:
                    # Utiliser les superhote_ids si disponibles, sinon property_names
                    props_to_use = [sid for sid in superhote_ids if sid] if superhote_ids else property_names
                    results = automation.update_price_for_multiple_properties(
                        property_names=props_to_use,
                        start_date=start_date,
                        end_date=end_date,
                        price=price
                    )
                else:
                    # Un seul logement: utiliser update_price_for_dates
                    property_id = superhote_ids[0] if superhote_ids and superhote_ids[0] else property_names[0]
                    property_name = property_names[0] if property_names else None
                    success = automation.update_price_for_dates(
                        property_id=property_id,
                        start_date=start_date,
                        end_date=end_date,
                        price=price,
                        property_name=property_name
                    )
                    results = {property_name or property_id: success}

                # Mettre a jour les statuts
                if is_group_mode:
                    # En mode groupe, si une maj a reussi, toutes ont reussi
                    any_success = any(results.values()) if results else False
                    if any_success:
                        update_status(update_ids, "completed")
                        stats["successful"] += len(update_ids)
                    else:
                        update_status(update_ids, "failed", "Echec application prix groupe")
                        stats["failed"] += len(update_ids)
                else:
                    for i, (name, success) in enumerate(results.items()):
                        if i < len(update_ids):
                            if success:
                                update_status([update_ids[i]], "completed")
                                stats["successful"] += 1
                            else:
                                update_status([update_ids[i]], "failed", "Echec application prix")
                                stats["failed"] += 1

                # Pause entre les groupes
                time.sleep(2)

            except Exception as e:
                pool_logger.error(f"Worker {worker_id}: Erreur groupe - {e}")
                update_status(update_ids, "failed", str(e))
                stats["failed"] += len(update_ids)

        pool_logger.info(
            f"Worker {worker_id}: Termine - {stats['successful']} OK, {stats['failed']} echecs"
        )

    except Exception as e:
        pool_logger.error(f"Worker {worker_id}: Erreur fatale - {e}")

    finally:
        # Fermer le navigateur
        if automation:
            try:
                automation.stop()  # Methode correcte (pas close())
            except Exception as e:
                pool_logger.warning(f"Worker {worker_id}: Erreur fermeture navigateur: {type(e).__name__}: {e}")

    return stats


class SuperhoteWorkerPool:
    """
    Gestionnaire du pool de workers Selenium.

    Deux modes de fonctionnement:
    - Mode standard: repartit les logements entre workers (X logements par worker)
    - Mode groupe: un worker par groupe, utilise un logement de reference pour eviter les reservations
    """

    def __init__(self, properties_per_worker: int = 6, max_workers: int = None, use_groups: bool = False, logement_id: int = None):
        """
        Initialise le pool de workers.

        Args:
            properties_per_worker: Nombre de logements par worker en mode standard (defaut: 6)
            max_workers: Nombre maximum de workers (defaut: illimite)
            use_groups: Si True, utilise le mode groupe (un worker par groupe configure)
            logement_id: Si specifie, ne traiter que ce logement
        """
        self.properties_per_worker = properties_per_worker
        self.max_workers = max_workers
        self.use_groups = use_groups
        self.logement_id = logement_id
        self.workers = []
        # Utiliser Manager().Event() pour partager entre processus
        self._manager = Manager()
        self.stop_event = self._manager.Event()

    def setup_workers(self) -> int:
        """
        Configure les workers en fonction des logements actifs.

        En mode groupe:
        - Un worker par groupe configure dans superhote_groups
        - Chaque worker utilise le logement de reference pour ouvrir les modales

        En mode standard:
        - Repartit les logements entre workers (X par worker)

        Si logement_id est specifie, filtre pour ne garder que le worker
        qui contient ce logement.

        Returns:
            Nombre de workers configures
        """
        if self.logement_id:
            pool_logger.info(f"Mode LOGEMENT UNIQUE: id={self.logement_id}")

        if self.use_groups:
            # Mode groupe: un worker par groupe + workers pour orphelins
            pool_logger.info("Mode GROUPE active (hybride: groupes + orphelins)")
            self.workers = assign_workers_by_group(self.properties_per_worker)

            if not self.workers:
                pool_logger.warning("Aucun groupe configure et aucun logement actif")
                pool_logger.info("Fallback vers mode standard...")
                self.use_groups = False
            else:
                # Filtrer par logement_id si specifie
                if self.logement_id:
                    self.workers = [w for w in self.workers if self.logement_id in w.logement_ids]
                    pool_logger.info(f"Filtre logement_id={self.logement_id}: {len(self.workers)} worker(s) retenu(s)")

                group_workers = [w for w in self.workers if w.group_name]
                standard_workers = [w for w in self.workers if not w.group_name]
                pool_logger.info(f"Workers configures: {len(self.workers)} ({len(group_workers)} groupes, {len(standard_workers)} standards)")
                for w in self.workers:
                    if w.group_name:
                        pool_logger.info(f"  Worker {w.worker_id} [GROUPE {w.group_name}]: ref={w.reference_property}, membres={w.logement_names}")
                    else:
                        pool_logger.info(f"  Worker {w.worker_id} [STANDARD]: {w.logement_names}")

        if not self.use_groups:
            # Mode standard: repartition par nombre
            logements = get_all_active_logements()

            # Filtrer par logement_id si specifie
            if self.logement_id:
                logements = [l for l in logements if l["id"] == self.logement_id]
                pool_logger.info(f"Filtre logement_id={self.logement_id}: {len(logements)} logement(s)")

            if not logements:
                pool_logger.warning("Aucun logement actif trouve")
                return 0

            pool_logger.info(f"Mode STANDARD - Logements actifs: {len(logements)}")

            # Repartir les logements
            self.workers = assign_logements_to_workers(logements, self.properties_per_worker)

            pool_logger.info(f"Workers configures: {len(self.workers)}")
            for w in self.workers:
                pool_logger.info(f"  Worker {w.worker_id}: {w.logement_names}")

        # Limiter si necessaire
        if self.max_workers and len(self.workers) > self.max_workers:
            pool_logger.info(f"Limitation a {self.max_workers} workers")
            self.workers = self.workers[:self.max_workers]

        return len(self.workers)

    def run(self) -> List[Dict]:
        """
        Lance tous les workers en parallele.

        Returns:
            Liste des statistiques de chaque worker
        """
        if not self.workers:
            self.setup_workers()

        if not self.workers:
            pool_logger.warning("Aucun worker a lancer")
            return []

        pool_logger.info(f"Lancement de {len(self.workers)} workers en parallele...")

        all_stats = []

        # Utiliser ProcessPoolExecutor pour le parallelisme
        with ProcessPoolExecutor(max_workers=len(self.workers)) as executor:
            # Soumettre tous les workers
            futures = {
                executor.submit(run_worker, worker, self.stop_event): worker
                for worker in self.workers
            }

            # Attendre les resultats
            for future in as_completed(futures):
                worker = futures[future]
                try:
                    stats = future.result()
                    all_stats.append(stats)
                except Exception as e:
                    pool_logger.error(f"Worker {worker.worker_id} a echoue: {e}")
                    all_stats.append({
                        "worker_id": worker.worker_id,
                        "error": str(e),
                        "successful": 0,
                        "failed": 0
                    })

        # Resume
        total_success = sum(s.get("successful", 0) for s in all_stats)
        total_failed = sum(s.get("failed", 0) for s in all_stats)

        pool_logger.info("=" * 50)
        pool_logger.info("RESUME FINAL")
        pool_logger.info(f"  Workers: {len(all_stats)}")
        pool_logger.info(f"  Succes: {total_success}")
        pool_logger.info(f"  Echecs: {total_failed}")
        pool_logger.info("=" * 50)

        return all_stats

    def stop(self):
        """Demande l'arret de tous les workers."""
        pool_logger.info("Arret demande pour tous les workers...")
        self.stop_event.set()


def main():
    """Point d'entree principal."""
    parser = argparse.ArgumentParser(description="Pool de workers Superhote")
    parser.add_argument(
        "--properties-per-worker", "-p",
        type=int,
        default=6,
        help="Nombre de logements par worker en mode standard (defaut: 6)"
    )
    parser.add_argument(
        "--max-workers", "-m",
        type=int,
        default=None,
        help="Nombre maximum de workers (defaut: illimite)"
    )
    parser.add_argument(
        "--groups", "-g",
        action="store_true",
        help="Mode groupe: un worker par groupe, utilise le logement de reference"
    )
    parser.add_argument(
        "--dry-run", "-d",
        action="store_true",
        help="Affiche la configuration sans lancer les workers"
    )
    parser.add_argument(
        "--logement-id", "-l",
        type=int,
        default=None,
        help="Ne traiter que ce logement (filtre les workers)"
    )

    args = parser.parse_args()

    pool_logger.info("=" * 50)
    pool_logger.info("DEMARRAGE POOL WORKERS SUPERHOTE")
    pool_logger.info(f"  Mode: {'GROUPE' if args.groups else 'STANDARD'}")
    if not args.groups:
        pool_logger.info(f"  Logements par worker: {args.properties_per_worker}")
    pool_logger.info(f"  Max workers: {args.max_workers or 'illimite'}")
    pool_logger.info("=" * 50)

    pool = SuperhoteWorkerPool(
        properties_per_worker=args.properties_per_worker,
        max_workers=args.max_workers,
        use_groups=args.groups,
        logement_id=args.logement_id
    )

    # Gerer SIGINT/SIGTERM
    def signal_handler(signum, frame):
        pool_logger.info("Signal d'arret recu")
        pool.stop()

    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    # Configurer les workers
    num_workers = pool.setup_workers()

    if args.dry_run:
        pool_logger.info("Mode dry-run - pas de lancement")
        return

    if num_workers == 0:
        pool_logger.warning("Aucun worker configure, fin du programme")
        return

    # Lancer les workers
    stats = pool.run()

    pool_logger.info("Pool termine")


if __name__ == "__main__":
    main()
