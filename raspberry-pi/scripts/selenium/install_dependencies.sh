#!/bin/bash
#
# Installation des dependances pour Superhote Daemon
#

set -e

echo "=========================================="
echo " Installation Superhote Daemon"
echo "=========================================="

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# 1. Dependances Python
echo ""
echo -e "${YELLOW}[1/4] Installation des dependances Python...${NC}"

pip3 install --user selenium pymysql 2>/dev/null || pip3 install selenium pymysql

python3 -c "import selenium; print('  Selenium:', selenium.__version__)"
python3 -c "import pymysql; print('  PyMySQL: OK')"

echo -e "${GREEN}  OK${NC}"

# 2. Chrome/Chromium
echo ""
echo -e "${YELLOW}[2/4] Verification de Chrome/Chromium...${NC}"

if command -v google-chrome &> /dev/null; then
    echo -e "${GREEN}  Google Chrome detecte${NC}"
    google-chrome --version
elif command -v chromium &> /dev/null; then
    echo -e "${GREEN}  Chromium detecte${NC}"
    chromium --version
elif command -v chromium-browser &> /dev/null; then
    echo -e "${GREEN}  Chromium Browser detecte${NC}"
    chromium-browser --version
else
    echo -e "${RED}  Chrome/Chromium NON INSTALLE${NC}"
    echo ""
    echo "  Installer avec l'une de ces commandes:"
    echo "    Ubuntu/Debian: sudo apt install chromium-browser"
    echo "    CentOS/RHEL:   sudo yum install chromium"
    echo "    MacOS:         brew install --cask chromium"
    echo ""
    echo "  Ou telecharger Google Chrome: https://www.google.com/chrome/"
    exit 1
fi

# 3. ChromeDriver
echo ""
echo -e "${YELLOW}[3/4] Verification de ChromeDriver...${NC}"

if command -v chromedriver &> /dev/null; then
    echo -e "${GREEN}  ChromeDriver detecte${NC}"
    chromedriver --version 2>/dev/null || true
else
    echo -e "${RED}  ChromeDriver NON INSTALLE${NC}"
    echo ""
    echo "  Installer avec:"
    echo "    Ubuntu/Debian: sudo apt install chromium-chromedriver"
    echo "    Ou telecharger: https://chromedriver.chromium.org/downloads"
    exit 1
fi

# 4. Configuration
echo ""
echo -e "${YELLOW}[4/4] Verification de la configuration...${NC}"

CONFIG_FILE="$SCRIPT_DIR/../../config/config.ini"
if [ -f "$CONFIG_FILE" ]; then
    echo -e "${GREEN}  config.ini trouve${NC}"
else
    echo -e "${RED}  config.ini MANQUANT${NC}"
    echo "  Copier depuis l'exemple:"
    echo "    cp config/config.ini.example config/config.ini"
    exit 1
fi

# Test de connexion BDD
echo ""
echo -e "${YELLOW}Test de connexion a la base de donnees...${NC}"

python3 << 'EOF'
import configparser
import pymysql
from pathlib import Path

config = configparser.ConfigParser()
config.read(Path(__file__).parent.parent.parent / "config" / "config.ini")

try:
    conn = pymysql.connect(
        host=config.get("DATABASE", "host"),
        user=config.get("DATABASE", "user"),
        password=config.get("DATABASE", "password"),
        database=config.get("DATABASE", "database")
    )
    print("  Connexion BDD: OK")
    conn.close()
except Exception as e:
    print(f"  Connexion BDD: ECHEC - {e}")
    exit(1)
EOF

echo ""
echo "=========================================="
echo -e "${GREEN} Installation terminee!${NC}"
echo "=========================================="
echo ""
echo "Lancer le daemon avec:"
echo "  cd $SCRIPT_DIR"
echo "  python3 superhote_daemon_v2.py -w 2 -i 30"
echo ""
echo "Ou utiliser le script de controle:"
echo "  ./daemon_ctl.sh start"
echo ""
