#!/usr/bin/env python3
# coding: utf-8
"""
Mise a jour quotidienne planifiee des prix Superhote.

Ce script remplace le daemon permanent par une execution planifiee:
1. Verifie le statut (taches bloquees)
2. Genere les prix automatiquement
3. Lance les workers
4. Attend la fin et quitte proprement

Usage:
    python run_scheduled_update.py                    # Execution complete
    python run_scheduled_update.py --generate-only   # Genere les prix sans lancer les workers
    python run_scheduled_update.py --workers-only    # Lance les workers sans regenerer
    python run_scheduled_update.py --status          # Affiche le statut et quitte
"""

import os
import sys
import time
import logging
import configparser
import pymysql
import argparse
import signal
import subprocess
from datetime import datetime, timedelta
from pathlib import Path
from typing import Optional, Dict, List, Tuple

# Numero de telephone pour les notifications SMS
SMS_NOTIFICATION_NUMBER = "+33647554678"

# ============================================================================
# CONFIGURATION
# ============================================================================

BASE_DIR = Path(__file__).parent.parent.parent
CONFIG_DIR = BASE_DIR / "config"
LOG_DIR = BASE_DIR / "logs"
LOG_DIR.mkdir(exist_ok=True)

# Fichier de statut pour l'interface web
STATUS_FILE = LOG_DIR / "scheduled_update_status.json"

# ============================================================================
# LOGGING
# ============================================================================

def setup_logging() -> logging.Logger:
    """Configure le logging."""
    logger = logging.getLogger("scheduled_update")

    if logger.handlers:
        return logger

    logger.setLevel(logging.INFO)
    logger.propagate = False

    # Fichier log avec date
    today = datetime.now().strftime("%Y-%m-%d")
    file_handler = logging.FileHandler(LOG_DIR / f"scheduled_update_{today}.log")
    file_handler.setFormatter(logging.Formatter(
        "%(asctime)s - %(levelname)s - %(message)s"
    ))
    logger.addHandler(file_handler)

    # Console
    console_handler = logging.StreamHandler()
    console_handler.setFormatter(logging.Formatter(
        "%(asctime)s - %(levelname)s - %(message)s"
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
            autocommit=True
        )
    except Exception as e:
        logger.error(f"Erreur connexion BDD: {e}")
        return None


def get_settings() -> Dict:
    """Recupere les parametres depuis superhote_settings."""
    db = get_db_connection()
    if not db:
        return {}

    try:
        with db.cursor() as cursor:
            cursor.execute("SELECT key_name, value FROM superhote_settings")
            return {row["key_name"]: row["value"] for row in cursor.fetchall()}
    except Exception as e:
        logger.error(f"Erreur lecture settings: {e}")
        return {}
    finally:
        db.close()


# ============================================================================
# STATUS MANAGEMENT
# ============================================================================

def write_status(status: Dict):
    """Ecrit le statut dans un fichier JSON pour l'interface web."""
    import json
    status["updated_at"] = datetime.now().isoformat()

    try:
        with open(STATUS_FILE, "w") as f:
            json.dump(status, f, indent=2)
    except Exception as e:
        logger.error(f"Erreur ecriture statut: {e}")


def read_status() -> Dict:
    """Lit le statut depuis le fichier JSON."""
    import json

    if not STATUS_FILE.exists():
        return {"status": "never_run"}

    try:
        with open(STATUS_FILE, "r") as f:
            return json.load(f)
    except Exception as e:
        logger.error(f"Erreur lecture statut: {e}")
        return {"status": "error", "error": str(e)}


# ============================================================================
# STEP 1: CLEANUP STALE TASKS
# ============================================================================

def cleanup_stale_tasks() -> int:
    """
    Libere les taches bloquees en 'processing' depuis plus de 30 minutes.

    Returns:
        Nombre de taches liberees
    """
    logger.info("Etape 1: Nettoyage des taches bloquees...")

    db = get_db_connection()
    if not db:
        return 0

    try:
        with db.cursor() as cursor:
            # Liberer les taches bloquees
            cursor.execute("""
                UPDATE superhote_price_updates
                SET status = 'pending',
                    error_message = 'Released by scheduled update'
                WHERE status = 'processing'
                AND updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            """)
            released = cursor.rowcount

            if released > 0:
                logger.info(f"  {released} taches bloquees liberees")
            else:
                logger.info("  Aucune tache bloquee")

            return released
    except Exception as e:
        logger.error(f"Erreur nettoyage: {e}")
        return 0
    finally:
        db.close()


# ============================================================================
# STEP 2: GENERATE PRICES
# ============================================================================

def calculate_price(prix_plancher: float, prix_standard: float, jours_avant: int,
                   jour_semaine: int, weekend_pourcent: float, dimanche_reduction: float,
                   settings: Dict) -> Tuple[float, str]:
    """
    Calcule le prix selon l'anticipation et le jour de la semaine.

    Args:
        prix_plancher: Prix minimum (J0)
        prix_standard: Prix normal (J14+)
        jours_avant: Nombre de jours avant la date
        jour_semaine: 0=Dimanche, 6=Samedi
        weekend_pourcent: Majoration weekend en %
        dimanche_reduction: Reduction dimanche en euros
        settings: Parametres globaux

    Returns:
        Tuple (prix, nom_du_palier)
    """
    palier_j1_3 = float(settings.get("palier_j1_3_pourcent", 25)) / 100
    palier_j4_6 = float(settings.get("palier_j4_6_pourcent", 50)) / 100
    palier_j7_13 = float(settings.get("palier_j7_13_pourcent", 75)) / 100

    ecart = prix_standard - prix_plancher

    # Prix de base selon anticipation
    if jours_avant == 0:
        prix = prix_plancher
        palier = "J0"
    elif jours_avant <= 3:
        prix = prix_plancher + (ecart * palier_j1_3)
        palier = "J1-3"
    elif jours_avant <= 6:
        prix = prix_plancher + (ecart * palier_j4_6)
        palier = "J4-6"
    elif jours_avant <= 13:
        prix = prix_plancher + (ecart * palier_j7_13)
        palier = "J7-13"
    else:
        prix = prix_standard
        palier = "J14+"

    # Majoration weekend (vendredi=5, samedi=6)
    if jour_semaine in (5, 6):
        prix = prix * (1 + weekend_pourcent / 100)
        palier += "+WE"
    # Reduction dimanche (dimanche=0)
    elif jour_semaine == 0:
        prix = prix - dimanche_reduction
        palier += "-Dim"

    return round(prix, 0), palier


def generate_prices() -> Tuple[int, int]:
    """
    Genere les prix pour tous les logements actifs.

    Returns:
        Tuple (nombre_logements, nombre_mises_a_jour)
    """
    logger.info("Etape 2: Generation des prix...")

    db = get_db_connection()
    if not db:
        return 0, 0

    settings = get_settings()
    jours_generation = int(settings.get("jours_generation", 30))

    try:
        with db.cursor() as cursor:
            # Recuperer les logements actifs avec leurs configs
            cursor.execute("""
                SELECT
                    sc.logement_id,
                    sc.superhote_property_id,
                    l.nom_du_logement,
                    COALESCE(g.prix_plancher, sc.prix_plancher) as prix_plancher,
                    COALESCE(g.prix_standard, sc.prix_standard) as prix_standard,
                    COALESCE(g.weekend_pourcent, sc.weekend_pourcent, 10) as weekend_pourcent,
                    COALESCE(g.dimanche_reduction, sc.dimanche_reduction, 5) as dimanche_reduction
                FROM superhote_config sc
                JOIN liste_logements l ON sc.logement_id = l.id
                LEFT JOIN superhote_groups g ON sc.groupe = g.nom
                WHERE sc.is_active = 1
                AND sc.superhote_property_id IS NOT NULL
            """)
            logements = cursor.fetchall()

            if not logements:
                logger.warning("  Aucun logement actif trouve")
                return 0, 0

            logger.info(f"  {len(logements)} logements actifs")

            # Supprimer les anciennes taches pending
            cursor.execute("DELETE FROM superhote_price_updates WHERE status = 'pending'")
            deleted = cursor.rowcount
            if deleted > 0:
                logger.info(f"  {deleted} anciennes taches supprimees")

            total_updates = 0
            today = datetime.now().date()

            for logement in logements:
                if not logement["prix_plancher"] or not logement["prix_standard"]:
                    logger.warning(f"  {logement['nom_du_logement']}: prix non configures, ignore")
                    continue

                prix_plancher = float(logement["prix_plancher"])
                prix_standard = float(logement["prix_standard"])
                weekend_pourcent = float(logement["weekend_pourcent"])
                dimanche_reduction = float(logement["dimanche_reduction"])

                # Generer pour les N prochains jours
                for i in range(jours_generation + 1):
                    date = today + timedelta(days=i)
                    jour_semaine = date.weekday()  # 0=Lundi, 6=Dimanche
                    # Convertir en format PHP (0=Dimanche, 6=Samedi)
                    jour_semaine_php = (jour_semaine + 1) % 7

                    prix, palier = calculate_price(
                        prix_plancher, prix_standard, i,
                        jour_semaine_php, weekend_pourcent, dimanche_reduction,
                        settings
                    )

                    cursor.execute("""
                        INSERT INTO superhote_price_updates
                        (logement_id, superhote_property_id, nom_du_logement,
                         date_start, date_end, price, rule_name, status)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, 'pending')
                    """, (
                        logement["logement_id"],
                        logement["superhote_property_id"],
                        logement["nom_du_logement"],
                        date.strftime("%Y-%m-%d"),
                        date.strftime("%Y-%m-%d"),
                        prix,
                        palier
                    ))
                    total_updates += 1

            db.commit()
            logger.info(f"  {total_updates} mises a jour generees pour {len(logements)} logements")

            return len(logements), total_updates

    except Exception as e:
        logger.error(f"Erreur generation: {e}")
        return 0, 0
    finally:
        db.close()


# ============================================================================
# STEP 3: RUN WORKERS
# ============================================================================

def get_pending_count() -> int:
    """Compte le nombre de taches pending."""
    db = get_db_connection()
    if not db:
        return 0

    try:
        with db.cursor() as cursor:
            cursor.execute("SELECT COUNT(*) as count FROM superhote_price_updates WHERE status = 'pending'")
            return cursor.fetchone()["count"]
    except Exception:
        return 0
    finally:
        db.close()


def get_groups_with_pending() -> List[Dict]:
    """Recupere la liste des groupes qui ont des taches pending."""
    db = get_db_connection()
    if not db:
        return []

    try:
        with db.cursor() as cursor:
            # Recuperer les groupes distincts avec des pending
            cursor.execute("""
                SELECT DISTINCT
                    COALESCE(sc.groupe, 'ORPHELINS') as groupe_name,
                    COUNT(*) as pending_count
                FROM superhote_price_updates spu
                JOIN superhote_config sc ON spu.logement_id = sc.logement_id
                WHERE spu.status = 'pending'
                GROUP BY COALESCE(sc.groupe, 'ORPHELINS')
                ORDER BY pending_count DESC
            """)
            return cursor.fetchall()
    except Exception as e:
        logger.error(f"Erreur recuperation groupes: {e}")
        return []
    finally:
        db.close()


def run_workers(max_workers: int = 2, use_groups: bool = True) -> Dict:
    """
    Lance les workers pour traiter la queue.
    Mode sequentiel: traite les groupes un par un pour economiser la RAM.

    Args:
        max_workers: Nombre maximum de workers en parallele
        use_groups: Utiliser le mode groupe

    Returns:
        Statistiques d'execution
    """
    logger.info("Etape 3: Lancement des workers...")

    initial_pending = get_pending_count()
    if initial_pending == 0:
        logger.info("  Aucune tache en attente")
        return {"status": "no_tasks", "pending": 0}

    logger.info(f"  {initial_pending} taches en attente")

    script_path = Path(__file__).parent / "superhote_worker_pool.py"
    start_time = time.time()
    iteration = 0
    max_iterations = 15  # Securite: max 15 iterations
    consecutive_no_progress = 0

    # Boucle jusqu'a ce qu'il n'y ait plus de pending
    while iteration < max_iterations:
        iteration += 1
        pending_before = get_pending_count()

        if pending_before == 0:
            logger.info(f"  Iteration {iteration}: Plus de taches pending, fin.")
            break

        # Afficher les groupes avec pending
        groups = get_groups_with_pending()
        if groups:
            logger.info(f"  Iteration {iteration}: {pending_before} pending dans {len(groups)} groupes:")
            for g in groups:
                logger.info(f"    - {g['groupe_name']}: {g['pending_count']} taches")

        # Lancer les workers
        # En mode sequentiel (max_workers <= 2), on lance 1 worker a la fois
        # En mode parallele (max_workers > 2), on peut lancer plusieurs
        effective_workers = 1 if max_workers <= 2 else max_workers

        cmd = [
            sys.executable,
            str(script_path),
            "-m", str(effective_workers)
        ]

        if use_groups:
            cmd.append("--groups")

        logger.info(f"  Commande: {' '.join(cmd)}")

        try:
            result = subprocess.run(
                cmd,
                cwd=str(Path(__file__).parent),
                capture_output=True,
                text=True,
                timeout=3600  # 1 heure max par iteration
            )

            if result.returncode != 0:
                logger.error(f"  Erreur workers (code {result.returncode})")
                if result.stderr:
                    logger.error(f"  Stderr: {result.stderr[:500]}")

        except subprocess.TimeoutExpired:
            logger.error("  Timeout: iteration trop longue (>1h)")
        except Exception as e:
            logger.error(f"  Erreur execution: {e}")

        # Verifier la progression
        pending_after = get_pending_count()
        processed = pending_before - pending_after

        if processed <= 0:
            consecutive_no_progress += 1
            logger.warning(f"  Iteration {iteration}: Aucune progression ({pending_after} pending) - tentative {consecutive_no_progress}/3")

            if consecutive_no_progress >= 3:
                logger.error("  3 iterations sans progression, arret.")
                break
        else:
            consecutive_no_progress = 0
            logger.info(f"  Iteration {iteration}: {processed} taches traitees, reste {pending_after}")

        # Petite pause entre les iterations pour laisser Chrome se fermer
        time.sleep(5)

    duration = time.time() - start_time
    final_pending = get_pending_count()

    logger.info(f"  Workers termines en {duration:.1f}s apres {iteration} iterations")
    logger.info(f"  Resultat: {initial_pending - final_pending} traitees, {final_pending} restantes")

    return {
        "status": "completed" if final_pending == 0 else "partial",
        "duration": duration,
        "iterations": iteration,
        "initial_pending": initial_pending,
        "final_pending": final_pending,
        "processed": initial_pending - final_pending
    }


# ============================================================================
# STEP 4: FINAL STATS
# ============================================================================

def send_sms(message: str):
    """Envoie un SMS de notification via la table sms_outbox."""
    if not SMS_NOTIFICATION_NUMBER or SMS_NOTIFICATION_NUMBER == "+33600000000":
        return

    db = get_db_connection()
    if not db:
        logger.warning("Impossible d'envoyer le SMS: pas de connexion BDD")
        return

    try:
        with db.cursor() as cursor:
            cursor.execute(
                "INSERT INTO sms_outbox (receiver, message, status, created_at) "
                "VALUES (%s, %s, 'pending', NOW())",
                (SMS_NOTIFICATION_NUMBER, message)
            )
        db.commit()
        logger.info(f"SMS de notification envoye: {message}")
    except Exception as e:
        logger.error(f"Erreur envoi SMS: {e}")
    finally:
        db.close()


def get_final_stats() -> Dict:
    """Recupere les statistiques finales."""
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
        logger.error(f"Erreur stats: {e}")
        return {}
    finally:
        db.close()


# ============================================================================
# MAIN
# ============================================================================

def run_full_update(max_workers: int = 2, use_groups: bool = True) -> Dict:
    """
    Execute la mise a jour complete.

    Returns:
        Rapport d'execution
    """
    start_time = datetime.now()
    report = {
        "status": "running",
        "started_at": start_time.isoformat(),
        "steps": {}
    }

    write_status(report)

    logger.info("=" * 60)
    logger.info("MISE A JOUR QUOTIDIENNE SUPERHOTE")
    logger.info(f"Demarrage: {start_time.strftime('%Y-%m-%d %H:%M:%S')}")
    logger.info("=" * 60)

    try:
        # Etape 1: Nettoyage
        released = cleanup_stale_tasks()
        report["steps"]["cleanup"] = {"released": released}

        # Etape 2: Generation des prix
        num_logements, num_updates = generate_prices()
        report["steps"]["generate"] = {
            "logements": num_logements,
            "updates": num_updates
        }

        # Etape 3: Workers
        if num_updates > 0:
            worker_result = run_workers(max_workers, use_groups)
            report["steps"]["workers"] = worker_result
        else:
            report["steps"]["workers"] = {"status": "skipped", "reason": "no_updates"}

        # Etape 4: Stats finales
        final_stats = get_final_stats()
        report["steps"]["final_stats"] = final_stats

        # Resultat
        end_time = datetime.now()
        duration = (end_time - start_time).total_seconds()

        report["status"] = "completed"
        report["ended_at"] = end_time.isoformat()
        report["duration_seconds"] = duration

        logger.info("=" * 60)
        logger.info("RESUME")
        logger.info(f"  Duree: {duration:.1f}s")
        logger.info(f"  Logements: {num_logements}")
        logger.info(f"  Mises a jour: {num_updates}")
        logger.info(f"  Stats finales: {final_stats}")
        logger.info("=" * 60)

        # SMS de confirmation
        completed = final_stats.get("completed", 0)
        failed = final_stats.get("failed", 0)
        pending = final_stats.get("pending", 0)

        if failed == 0:
            send_sms(f"Superhote OK: {completed} maj appliquees, {pending} en attente")
        else:
            send_sms(f"Superhote: {completed} OK, {failed} echecs, {pending} en attente")

    except Exception as e:
        report["status"] = "error"
        report["error"] = str(e)
        logger.error(f"Erreur fatale: {e}")
        send_sms(f"ERREUR Superhote: {e}")

    write_status(report)
    return report


def show_status():
    """Affiche le statut actuel."""
    print("=== STATUT SUPERHOTE ===\n")

    # Dernier run
    status = read_status()
    print(f"Dernier run: {status.get('updated_at', 'Jamais')}")
    print(f"Statut: {status.get('status', 'Inconnu')}")

    if "duration_seconds" in status:
        print(f"Duree: {status['duration_seconds']:.1f}s")

    if "steps" in status:
        steps = status["steps"]
        if "generate" in steps:
            print(f"Logements: {steps['generate'].get('logements', 0)}")
            print(f"Mises a jour: {steps['generate'].get('updates', 0)}")
        if "final_stats" in steps:
            print(f"Stats: {steps['final_stats']}")

    print()

    # Queue actuelle
    db = get_db_connection()
    if db:
        try:
            with db.cursor() as cursor:
                cursor.execute("""
                    SELECT status, COUNT(*) as count
                    FROM superhote_price_updates
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    GROUP BY status
                """)

                print("=== QUEUE (24h) ===")
                for row in cursor.fetchall():
                    print(f"  {row['status']}: {row['count']}")
        finally:
            db.close()

    # Prochaine execution
    settings = get_settings()
    scheduled_time = settings.get("scheduled_time", "07:00")
    print(f"\nProchaine execution: {scheduled_time}")


def main():
    parser = argparse.ArgumentParser(
        description="Mise a jour quotidienne planifiee des prix Superhote",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Exemples:
  python run_scheduled_update.py              # Execution complete
  python run_scheduled_update.py --status     # Affiche le statut
  python run_scheduled_update.py -w 3         # 3 workers max
        """
    )

    parser.add_argument(
        "--generate-only", "-g",
        action="store_true",
        help="Genere les prix sans lancer les workers"
    )
    parser.add_argument(
        "--workers-only", "-o",
        action="store_true",
        help="Lance les workers sans regenerer les prix"
    )
    parser.add_argument(
        "--status", "-s",
        action="store_true",
        help="Affiche le statut et quitte"
    )
    parser.add_argument(
        "--workers", "-w",
        type=int,
        default=2,
        help="Nombre maximum de workers (defaut: 2)"
    )
    parser.add_argument(
        "--no-groups",
        action="store_true",
        help="Desactive le mode groupe"
    )

    args = parser.parse_args()

    # Mode status
    if args.status:
        show_status()
        return

    # Gerer les signaux avec cleanup propre
    def signal_handler(signum, frame):
        sig_name = "SIGINT" if signum == signal.SIGINT else "SIGTERM"
        logger.info(f"Signal {sig_name} recu - arret en cours...")

        # Tenter de tuer les processus Chromium orphelins
        try:
            import subprocess
            # Trouver les chromium lances par ce script
            result = subprocess.run(
                ["pkill", "-f", "chromium.*--headless"],
                capture_output=True,
                timeout=5
            )
            if result.returncode == 0:
                logger.info("Processus Chromium orphelins termines")
        except Exception as e:
            logger.warning(f"Cleanup Chromium: {e}")

        logger.info("Arret propre termine")
        sys.exit(1)

    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    # Mode generation seule
    if args.generate_only:
        cleanup_stale_tasks()
        num_logements, num_updates = generate_prices()
        print(f"Genere: {num_updates} mises a jour pour {num_logements} logements")
        return

    # Mode workers seuls
    if args.workers_only:
        cleanup_stale_tasks()
        result = run_workers(args.workers, not args.no_groups)
        print(f"Workers: {result}")
        return

    # Execution complete
    report = run_full_update(args.workers, not args.no_groups)

    # Code de sortie
    if report.get("status") == "completed":
        sys.exit(0)
    else:
        sys.exit(1)


if __name__ == "__main__":
    main()
