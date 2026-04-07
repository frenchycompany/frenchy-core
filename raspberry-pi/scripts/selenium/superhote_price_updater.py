#!/usr/bin/env python3
# coding: utf-8
"""
Script de mise a jour des prix Superhote.
Lit les prix a mettre a jour depuis la base de donnees et les applique via Selenium.
"""

import os
import sys
import time
import logging
import configparser
import pymysql
from datetime import datetime, timedelta
from typing import Optional, Dict, List, Tuple
from pathlib import Path

# Import du module de base
from superhote_base import SuperhoteAutomation, logger

# Configuration
CONFIG_DIR = Path(__file__).parent.parent.parent / "config"


class SuperhotePriceUpdater:
    """
    Gestionnaire de mise a jour des prix Superhote.
    Lit les configurations depuis la base de donnees et applique les prix.
    """

    def __init__(self):
        """Initialise le gestionnaire de prix."""
        self.config = configparser.ConfigParser()
        self.config.read(CONFIG_DIR / "config.ini")

        # Configuration BDD
        self.db_host = self.config.get("DATABASE", "host")
        self.db_user = self.config.get("DATABASE", "user")
        self.db_password = self.config.get("DATABASE", "password")
        self.db_name = self.config.get("DATABASE", "database")

        self.automation = SuperhoteAutomation()

    def get_db_connection(self):
        """Obtient une connexion a la base de donnees."""
        try:
            return pymysql.connect(
                host=self.db_host,
                user=self.db_user,
                password=self.db_password,
                database=self.db_name,
                charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor,
                connect_timeout=10
            )
        except Exception as e:
            logger.error(f"Erreur connexion BDD: {e}")
            return None

    def get_logements_config(self) -> List[Dict]:
        """
        Recupere la configuration des logements depuis la BDD.

        Returns:
            Liste des configurations de logements
        """
        db = self.get_db_connection()
        if not db:
            return []

        try:
            with db.cursor() as cursor:
                cursor.execute("""
                    SELECT
                        l.id,
                        l.nom_du_logement,
                        sc.superhote_property_id,
                        sc.is_active,
                        sc.default_price,
                        sc.weekend_price,
                        sc.min_price,
                        sc.max_price
                    FROM liste_logements l
                    LEFT JOIN superhote_config sc ON l.id = sc.logement_id
                    WHERE sc.is_active = 1 AND sc.superhote_property_id IS NOT NULL
                """)
                return cursor.fetchall()
        except pymysql.err.ProgrammingError:
            # Table n'existe pas encore
            logger.warning("Table superhote_config non trouvee")
            return []
        finally:
            db.close()

    def get_pending_price_updates(self) -> List[Dict]:
        """
        Recupere les mises a jour de prix en attente.

        Returns:
            Liste des mises a jour a effectuer
        """
        db = self.get_db_connection()
        if not db:
            return []

        try:
            with db.cursor() as cursor:
                cursor.execute("""
                    SELECT
                        spu.id,
                        spu.logement_id,
                        spu.superhote_property_id,
                        spu.date_start,
                        spu.date_end,
                        spu.price,
                        spu.status,
                        l.nom_du_logement,
                        sc.superhote_property_name
                    FROM superhote_price_updates spu
                    LEFT JOIN liste_logements l ON spu.logement_id = l.id
                    LEFT JOIN superhote_config sc ON spu.logement_id = sc.logement_id
                    WHERE spu.status = 'pending'
                    ORDER BY spu.date_start ASC, spu.logement_id ASC
                """)
                return cursor.fetchall()
        except pymysql.err.ProgrammingError:
            logger.warning("Table superhote_price_updates non trouvee")
            return []
        finally:
            db.close()

    def update_price_status(
        self,
        update_id: int,
        status: str,
        error_message: str = None
    ):
        """
        Met a jour le statut d'une mise a jour de prix.

        Args:
            update_id: ID de la mise a jour
            status: Nouveau statut (pending, processing, completed, failed)
            error_message: Message d'erreur si applicable
        """
        db = self.get_db_connection()
        if not db:
            return

        try:
            with db.cursor() as cursor:
                if error_message:
                    cursor.execute("""
                        UPDATE superhote_price_updates
                        SET status = %s, error_message = %s, updated_at = NOW()
                        WHERE id = %s
                    """, (status, error_message, update_id))
                else:
                    cursor.execute("""
                        UPDATE superhote_price_updates
                        SET status = %s, updated_at = NOW()
                        WHERE id = %s
                    """, (status, update_id))
                db.commit()
        except Exception as e:
            logger.error(f"Erreur mise a jour statut: {e}")
        finally:
            db.close()

    def log_price_history(
        self,
        logement_id: int,
        superhote_property_id: str,
        date_start: datetime,
        date_end: datetime,
        old_price: Optional[float],
        new_price: float,
        success: bool
    ):
        """
        Enregistre l'historique des mises a jour de prix.
        """
        db = self.get_db_connection()
        if not db:
            return

        try:
            with db.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO superhote_price_history
                    (logement_id, superhote_property_id, date_start, date_end,
                     old_price, new_price, success, created_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, NOW())
                """, (
                    logement_id, superhote_property_id,
                    date_start, date_end,
                    old_price, new_price, success
                ))
                db.commit()
        except Exception as e:
            logger.error(f"Erreur log historique: {e}")
        finally:
            db.close()

    def _group_updates_by_price_and_date(self, updates: List[Dict]) -> List[Dict]:
        """
        Regroupe les mises a jour par (prix, date_start, date_end) pour traitement batch.

        Permet d'appliquer le meme prix a plusieurs logements en une seule operation.

        Args:
            updates: Liste des mises a jour individuelles

        Returns:
            Liste de groupes avec leurs updates
        """
        from collections import defaultdict

        # Grouper par (prix, date_start, date_end)
        groups = defaultdict(list)

        for update in updates:
            price = float(update["price"])
            date_start = update["date_start"]
            date_end = update["date_end"]

            # Convertir en string pour la cle si necessaire
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

        # Convertir en liste de groupes
        result = []
        for (price, date_start_str, date_end_str), group_updates in groups.items():
            result.append({
                "price": price,
                "date_start": date_start_str,
                "date_end": date_end_str,
                "updates": group_updates,
                "property_names": [u.get("superhote_property_name") or u.get("nom_du_logement", "Inconnu") for u in group_updates],
                "count": len(group_updates)
            })

        # Trier par date de debut
        result.sort(key=lambda x: x["date_start"])

        logger.info(f"Regroupement: {len(updates)} updates -> {len(result)} groupes")
        for group in result:
            logger.info(f"  Groupe {group['date_start']}: {group['price']}EUR x {group['count']} logements")

        return result

    def _merge_consecutive_dates(self, grouped_updates: List[Dict]) -> List[Dict]:
        """
        Fusionne les groupes avec des dates consecutives, meme prix et memes logements.

        Par exemple, si on a:
        - 2026-02-02: 55EUR pour [Delphin-ZEN, Delphin-BLEU]
        - 2026-02-03: 55EUR pour [Delphin-ZEN, Delphin-BLEU]
        - 2026-02-04: 55EUR pour [Delphin-ZEN, Delphin-BLEU]

        On fusionne en:
        - 2026-02-02 -> 2026-02-05: 55EUR pour [Delphin-ZEN, Delphin-BLEU]

        Args:
            grouped_updates: Liste des groupes deja regroupes par (prix, date)

        Returns:
            Liste de groupes fusionnes
        """
        if not grouped_updates:
            return []

        # Trier par prix, puis par ensemble de logements, puis par date
        def sort_key(g):
            props = tuple(sorted(g["property_names"]))
            return (g["price"], props, g["date_start"])

        sorted_groups = sorted(grouped_updates, key=sort_key)

        merged = []
        current_merge = None

        for group in sorted_groups:
            if current_merge is None:
                current_merge = {
                    "price": group["price"],
                    "date_start": group["date_start"],
                    "date_end": group["date_end"],
                    "updates": list(group["updates"]),
                    "property_names": group["property_names"],
                    "count": group["count"]
                }
                continue

            # Verifier si on peut fusionner avec le groupe actuel
            same_price = current_merge["price"] == group["price"]
            same_props = set(current_merge["property_names"]) == set(group["property_names"])

            # Verifier si les dates sont consecutives
            try:
                current_end = datetime.strptime(current_merge["date_end"], "%Y-%m-%d")
                group_start = datetime.strptime(group["date_start"], "%Y-%m-%d")
                consecutive = (group_start - current_end).days <= 1
            except:
                consecutive = False

            if same_price and same_props and consecutive:
                # Fusionner: etendre la date de fin et ajouter les updates
                current_merge["date_end"] = group["date_end"]
                current_merge["updates"].extend(group["updates"])
                logger.info(f"Fusion: {current_merge['date_start']} -> {current_merge['date_end']}")
            else:
                # Sauvegarder le groupe actuel et commencer un nouveau
                merged.append(current_merge)
                current_merge = {
                    "price": group["price"],
                    "date_start": group["date_start"],
                    "date_end": group["date_end"],
                    "updates": list(group["updates"]),
                    "property_names": group["property_names"],
                    "count": group["count"]
                }

        # Ajouter le dernier groupe
        if current_merge:
            merged.append(current_merge)

        logger.info(f"Apres fusion dates consecutives: {len(grouped_updates)} -> {len(merged)} groupes")
        for group in merged:
            days = 1
            try:
                start = datetime.strptime(group["date_start"], "%Y-%m-%d")
                end = datetime.strptime(group["date_end"], "%Y-%m-%d")
                days = (end - start).days + 1
            except:
                pass
            logger.info(f"  {group['date_start']} -> {group['date_end']} ({days}j): "
                       f"{group['price']}EUR x {len(group['property_names'])} logements")

        return merged

    def process_pending_updates(self) -> Tuple[int, int]:
        """
        Traite toutes les mises a jour de prix en attente.

        OPTIMISE: Regroupe les updates par (prix, date) pour traitement batch
        en utilisant la fonctionnalite multi-logements de Superhote.

        Returns:
            Tuple (nombre de succes, nombre d'echecs)
        """
        updates = self.get_pending_price_updates()

        if not updates:
            logger.info("Aucune mise a jour de prix en attente")
            return (0, 0)

        logger.info(f"Traitement de {len(updates)} mise(s) a jour de prix")

        # Regrouper les updates pour traitement batch
        grouped_updates = self._group_updates_by_price_and_date(updates)

        # Fusionner les dates consecutives avec meme prix/logements
        grouped_updates = self._merge_consecutive_dates(grouped_updates)

        success_count = 0
        failure_count = 0

        try:
            # Demarrer l'automation
            if not self.automation.start():
                logger.error("Impossible de demarrer le navigateur")
                return (0, len(updates))

            # Se connecter
            if not self.automation.login():
                logger.error("Impossible de se connecter a Superhote")
                for update in updates:
                    self.update_price_status(
                        update["id"], "failed", "Echec de connexion"
                    )
                return (0, len(updates))

            # Traiter chaque groupe
            for group in grouped_updates:
                price = group["price"]
                date_start_str = group["date_start"]
                date_end_str = group["date_end"]
                group_updates = group["updates"]
                property_names = group["property_names"]

                logger.info(
                    f"Traitement groupe: {date_start_str} - {date_end_str}, "
                    f"{price}EUR, {len(property_names)} logements: {property_names}"
                )

                # Marquer tous comme en cours
                for update in group_updates:
                    self.update_price_status(update["id"], "processing")

                try:
                    # Convertir les dates
                    date_start = datetime.strptime(date_start_str, "%Y-%m-%d")
                    date_end = datetime.strptime(date_end_str, "%Y-%m-%d")

                    # Utiliser la methode multi-logements si plusieurs logements
                    if len(property_names) > 1:
                        # Methode optimisee: multi-logements en une operation
                        results = self.automation.update_price_for_multiple_properties(
                            property_names, date_start, date_end, price
                        )

                        # Gerer le cas "skipped" (reservation)
                        if isinstance(results, dict) and results.get("status") == "skipped":
                            logger.info(f"Reservation detectee pour le groupe")
                            for update in group_updates:
                                self.update_price_status(update["id"], "completed")
                                success_count += 1
                            continue

                        # Traiter les resultats par logement
                        for update in group_updates:
                            logement_name = update.get("superhote_property_name") or update.get("nom_du_logement", "Inconnu")
                            if results.get(logement_name, False):
                                self.update_price_status(update["id"], "completed")
                                self.log_price_history(
                                    update["logement_id"],
                                    update["superhote_property_id"],
                                    date_start, date_end,
                                    None, price, True
                                )
                                success_count += 1
                                logger.info(f"OK: {logement_name}")
                            else:
                                self.update_price_status(
                                    update["id"], "failed",
                                    "Non selectionne dans le modal"
                                )
                                failure_count += 1
                                logger.warning(f"ECHEC: {logement_name}")

                    else:
                        # Un seul logement: methode classique
                        update = group_updates[0]
                        property_id = update["superhote_property_id"]
                        logement_name = property_names[0]

                        result = self.automation.update_price_for_dates(
                            property_id, date_start, date_end, price,
                            property_name=logement_name
                        )

                        # Gerer le resultat
                        if isinstance(result, dict) and result.get("status") == "skipped":
                            self.update_price_status(update["id"], "completed")
                            success_count += 1
                            logger.info(f"Reservation detectee: {logement_name}")
                        elif result is True or result:
                            self.update_price_status(update["id"], "completed")
                            self.log_price_history(
                                update["logement_id"], property_id,
                                date_start, date_end,
                                None, price, True
                            )
                            success_count += 1
                            logger.info(f"OK: {logement_name}")
                        else:
                            self.update_price_status(
                                update["id"], "failed", "Echec de la mise a jour"
                            )
                            failure_count += 1
                            logger.error(f"ECHEC: {logement_name}")

                except Exception as e:
                    # Marquer tous les updates du groupe comme echec
                    for update in group_updates:
                        self.update_price_status(update["id"], "failed", str(e))
                        failure_count += 1
                    logger.error(f"Exception groupe: {e}")

                # Pause entre les groupes
                time.sleep(2)

        except Exception as e:
            logger.error(f"Erreur generale lors du traitement: {e}")
        finally:
            self.automation.stop()

        logger.info(f"Traitement termine: {success_count} succes, {failure_count} echecs")
        return (success_count, failure_count)

    def create_price_update(
        self,
        logement_id: int,
        superhote_property_id: str,
        date_start: datetime,
        date_end: datetime,
        price: float
    ) -> Optional[int]:
        """
        Cree une nouvelle demande de mise a jour de prix.

        Args:
            logement_id: ID du logement local
            superhote_property_id: ID du logement sur Superhote
            date_start: Date de debut
            date_end: Date de fin
            price: Prix a appliquer

        Returns:
            ID de la mise a jour creee ou None
        """
        db = self.get_db_connection()
        if not db:
            return None

        try:
            with db.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO superhote_price_updates
                    (logement_id, superhote_property_id, date_start, date_end,
                     price, status, created_at)
                    VALUES (%s, %s, %s, %s, %s, 'pending', NOW())
                """, (
                    logement_id, superhote_property_id,
                    date_start, date_end, price
                ))
                db.commit()
                return cursor.lastrowid
        except Exception as e:
            logger.error(f"Erreur creation mise a jour: {e}")
            return None
        finally:
            db.close()


class LogementPriceScript:
    """
    Generateur de scripts de prix personnalises par logement.
    Permet de creer des regles de tarification specifiques.
    """

    def __init__(self, logement_id: int, superhote_property_id: str):
        """
        Initialise le script de prix pour un logement.

        Args:
            logement_id: ID du logement local
            superhote_property_id: ID sur Superhote
        """
        self.logement_id = logement_id
        self.superhote_property_id = superhote_property_id
        self.updater = SuperhotePriceUpdater()

    def set_base_price(self, price: float, days_ahead: int = 90):
        """
        Definit le prix de base pour les X prochains jours.

        Args:
            price: Prix par nuit
            days_ahead: Nombre de jours a couvrir
        """
        start = datetime.now()
        end = start + timedelta(days=days_ahead)

        self.updater.create_price_update(
            self.logement_id,
            self.superhote_property_id,
            start, end, price
        )

    def set_weekend_price(self, price: float, weeks_ahead: int = 12):
        """
        Definit le prix pour les weekends.

        Args:
            price: Prix par nuit pour les weekends
            weeks_ahead: Nombre de semaines a couvrir
        """
        today = datetime.now()

        for week in range(weeks_ahead):
            # Trouver le prochain samedi
            days_until_saturday = (5 - today.weekday()) % 7
            if days_until_saturday == 0 and today.weekday() != 5:
                days_until_saturday = 7

            saturday = today + timedelta(days=days_until_saturday + (week * 7))
            sunday = saturday + timedelta(days=1)

            self.updater.create_price_update(
                self.logement_id,
                self.superhote_property_id,
                saturday, sunday, price
            )

    def set_seasonal_price(
        self,
        price: float,
        start_month: int,
        start_day: int,
        end_month: int,
        end_day: int,
        year: Optional[int] = None
    ):
        """
        Definit le prix pour une periode saisonniere.

        Args:
            price: Prix par nuit
            start_month: Mois de debut (1-12)
            start_day: Jour de debut
            end_month: Mois de fin
            end_day: Jour de fin
            year: Annee (defaut: annee courante)
        """
        if year is None:
            year = datetime.now().year

        start = datetime(year, start_month, start_day)
        end = datetime(year, end_month, end_day)

        # Si la periode est dans le passe, utiliser l'annee prochaine
        if end < datetime.now():
            start = datetime(year + 1, start_month, start_day)
            end = datetime(year + 1, end_month, end_day)

        self.updater.create_price_update(
            self.logement_id,
            self.superhote_property_id,
            start, end, price
        )

    def set_holiday_price(self, price: float, holiday_dates: List[Tuple[int, int]]):
        """
        Definit le prix pour des jours feries specifiques.

        Args:
            price: Prix par nuit
            holiday_dates: Liste de tuples (mois, jour)
        """
        year = datetime.now().year

        for month, day in holiday_dates:
            date = datetime(year, month, day)
            if date < datetime.now():
                date = datetime(year + 1, month, day)

            self.updater.create_price_update(
                self.logement_id,
                self.superhote_property_id,
                date, date, price
            )


def run_price_updater():
    """Execute le processus de mise a jour des prix."""
    updater = SuperhotePriceUpdater()
    success, failures = updater.process_pending_updates()

    print(f"Mise a jour terminee: {success} succes, {failures} echecs")
    return success > 0 or failures == 0


if __name__ == "__main__":
    run_price_updater()
