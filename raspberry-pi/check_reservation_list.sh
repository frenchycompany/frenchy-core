#!/bin/bash
# Script pour vérifier et corriger reservation_list.php

echo "=== Vérification de reservation_list.php ==="
echo ""

FILE="/home/raphael/sms_project/web/pages/reservation_list.php"

if [ ! -f "$FILE" ]; then
    echo "❌ Fichier non trouvé: $FILE"
    exit 1
fi

echo "Recherche du LEFT JOIN..."
if grep -q "LEFT JOIN liste_logements" "$FILE"; then
    echo "✅ Le fichier contient déjà le LEFT JOIN - il est à jour!"
    echo ""
    echo "Lignes 128-135:"
    sed -n '128,135p' "$FILE"
else
    echo "❌ Le LEFT JOIN est manquant - le fichier doit être mis à jour"
    echo ""
    echo "Exécutez: cd /home/raphael/sms_project && git pull"
fi
