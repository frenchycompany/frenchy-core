#!/usr/bin/env python3
# coding: utf-8
"""
Script de tarification personnalise pour: Hutte Gauloise
ID Superhote: TCSKFssN3g (a adapter)

Ce script definit les regles de tarification specifiques pour ce logement.
Executez-le pour creer les mises a jour de prix dans la file d'attente.
"""

import sys
from pathlib import Path

# Ajouter le repertoire parent au path
sys.path.insert(0, str(Path(__file__).parent.parent))

from superhote_price_updater import LogementPriceScript
from datetime import datetime

# Configuration du logement
LOGEMENT_ID = 1  # ID dans la table liste_logements (a adapter)
SUPERHOTE_PROPERTY_ID = "TCSKFssN3g"  # ID Superhote (a adapter)


def configure_hutte_gauloise():
    """Configure les prix pour la Hutte Gauloise."""

    script = LogementPriceScript(LOGEMENT_ID, SUPERHOTE_PROPERTY_ID)

    print("Configuration des prix pour: Hutte Gauloise")
    print("=" * 50)

    # 1. Prix de base pour les 90 prochains jours
    print("1. Application du prix de base (90 jours)...")
    script.set_base_price(price=95.0, days_ahead=90)

    # 2. Prix weekend (samedi-dimanche)
    print("2. Application du prix weekend (12 semaines)...")
    script.set_weekend_price(price=120.0, weeks_ahead=12)

    # 3. Prix haute saison ete (juillet-aout)
    print("3. Application du prix haute saison ete...")
    script.set_seasonal_price(
        price=150.0,
        start_month=7, start_day=1,
        end_month=8, end_day=31
    )

    # 4. Prix vacances de Noel
    print("4. Application du prix vacances de Noel...")
    script.set_seasonal_price(
        price=140.0,
        start_month=12, start_day=20,
        end_month=1, end_day=5
    )

    # 5. Prix pour les ponts et jours feries
    print("5. Application du prix jours feries...")
    jours_feries = [
        (1, 1),    # Jour de l'an
        (5, 1),    # Fete du travail
        (5, 8),    # Victoire 1945
        (7, 14),   # Fete nationale
        (8, 15),   # Assomption
        (11, 1),   # Toussaint
        (11, 11),  # Armistice
        (12, 25),  # Noel
    ]
    script.set_holiday_price(price=130.0, holiday_dates=jours_feries)

    print("=" * 50)
    print("Configuration terminee!")
    print("Les mises a jour ont ete ajoutees a la file d'attente.")
    print("Executez 'python superhote_daemon.py' ou utilisez l'interface web")
    print("pour appliquer ces changements sur Superhote.")


if __name__ == "__main__":
    configure_hutte_gauloise()
