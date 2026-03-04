#!/bin/bash
# ═══════════════════════════════════════════════════════
# Script de déploiement — Nouveau logement
# Crée une copie du site prête à l'emploi pour un nouveau logement.
#
# Usage : ./install.sh /chemin/vers/nouveau-site
# ═══════════════════════════════════════════════════════

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

echo -e "${CYAN}${BOLD}══════════════════════════════════════${NC}"
echo -e "${CYAN}${BOLD}  Déploiement nouveau logement${NC}"
echo -e "${CYAN}${BOLD}══════════════════════════════════════${NC}"
echo ""

# Vérifier l'argument
DEST="$1"
if [ -z "$DEST" ]; then
    echo -e "${RED}Usage: $0 /chemin/vers/nouveau-site${NC}"
    exit 1
fi

if [ -d "$DEST" ]; then
    echo -e "${RED}Le dossier $DEST existe déjà. Abandon.${NC}"
    exit 1
fi

# Source = répertoire du script
SRC="$(cd "$(dirname "$0")" && pwd)"

echo -e "${BOLD}Source :${NC} $SRC"
echo -e "${BOLD}Destination :${NC} $DEST"
echo ""

# Demander les infos du logement
read -p "Nom du logement (ex: Maison des Lilas) : " PROP_NAME
read -p "Monogramme / initiales (ex: ML) : " PROP_MONO
read -p "Tagline (ex: Charme & Sérénité) : " PROP_TAG
read -p "Ville · Département (ex: Lyon · Rhône) : " PROP_LOC
read -p "Téléphone (ex: +33 6 00 00 00 00) : " PROP_PHONE
read -p "Email (ex: contact@maisondeslilas.fr) : " PROP_EMAIL

# Générer le préfixe BDD (3 lettres + 2 chiffres)
PREFIX_LETTERS=$(echo "$PROP_MONO" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z' | head -c2)
if [ ${#PREFIX_LETTERS} -lt 2 ]; then
    PREFIX_LETTERS=$(echo "$PROP_NAME" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z' | head -c3)
fi
PREFIX_LETTERS=$(echo "$PREFIX_LETTERS" | head -c3)
PREFIX_NUM=$(printf "%02d" $((RANDOM % 100)))
DB_PREFIX="${PREFIX_LETTERS}${PREFIX_NUM}_"

echo ""
echo -e "${BOLD}Préfixe BDD généré :${NC} ${DB_PREFIX}"
echo ""

# Demander les infos BDD
read -p "Hôte BDD (defaut: localhost) : " DB_HOST
DB_HOST=${DB_HOST:-localhost}
read -p "Nom de la base de données : " DB_NAME
read -p "Utilisateur BDD : " DB_USER
read -sp "Mot de passe BDD : " DB_PASS
echo ""

read -p "Utilisateur admin (defaut: admin) : " ADM_USER
ADM_USER=${ADM_USER:-admin}
read -sp "Mot de passe admin : " ADM_PASS
echo ""
echo ""

# Copier les fichiers
echo -e "${CYAN}Copie des fichiers...${NC}"
mkdir -p "$DEST"
rsync -a --exclude='.git' --exclude='.env' --exclude='uploads/' "$SRC/" "$DEST/"

# Créer le .env
echo -e "${CYAN}Création du .env...${NC}"
cat > "$DEST/.env" << ENVEOF
DB_HOST=$DB_HOST
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS

ADMIN_USER=$ADM_USER
ADMIN_PASS=$ADM_PASS
ENVEOF

# Mettre à jour config/property.php depuis le template
echo -e "${CYAN}Configuration du logement...${NC}"
cp "$DEST/config/property.php.example" "$DEST/config/property.php"

# Remplacer les valeurs dans property.php
sed -i "s/'db_prefix' => 'xx01_'/'db_prefix' => '${DB_PREFIX}'/" "$DEST/config/property.php"
sed -i "s/'name'      => 'Nom du logement'/'name'      => '$(echo "$PROP_NAME" | sed "s/'/\\\\'/g")'/" "$DEST/config/property.php"
sed -i "s/'monogram'  => 'XX'/'monogram'  => '${PROP_MONO}'/" "$DEST/config/property.php"
sed -i "s/'tagline'   => 'Votre slogan'/'tagline'   => '$(echo "$PROP_TAG" | sed "s/'/\\\\'/g")'/" "$DEST/config/property.php"
sed -i "s/'location'  => 'Ville · Département'/'location'  => '$(echo "$PROP_LOC" | sed "s/'/\\\\'/g")'/" "$DEST/config/property.php"
sed -i "s/'phone'     => '+33 6 00 00 00 00'/'phone'     => '${PROP_PHONE}'/" "$DEST/config/property.php"
sed -i "s/'email'     => 'contact@example.com'/'email'     => '${PROP_EMAIL}'/" "$DEST/config/property.php"

# Créer les dossiers de photos
mkdir -p "$DEST/assets/photos/hero"
mkdir -p "$DEST/assets/photos/galerie"
mkdir -p "$DEST/assets/photos/experience"

# Permissions
chmod 755 "$DEST/assets/photos" "$DEST/assets/photos/hero" "$DEST/assets/photos/galerie" "$DEST/assets/photos/experience"

echo ""
echo -e "${GREEN}${BOLD}══════════════════════════════════════${NC}"
echo -e "${GREEN}${BOLD}  Installation terminée !${NC}"
echo -e "${GREEN}${BOLD}══════════════════════════════════════${NC}"
echo ""
echo -e "${BOLD}Logement :${NC}    $PROP_NAME"
echo -e "${BOLD}Préfixe BDD :${NC} $DB_PREFIX"
echo -e "${BOLD}Dossier :${NC}     $DEST"
echo ""
echo -e "${CYAN}Prochaines étapes :${NC}"
echo "  1. Configurez le serveur web pour pointer vers $DEST"
echo "  2. Ouvrez /admin.php — le schéma BDD s'installe automatiquement"
echo "  3. Personnalisez textes, photos et couleurs via l'admin"
echo ""
