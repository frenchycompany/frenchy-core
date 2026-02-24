#!/usr/bin/env python3
"""
Verification des prerequis pour Superhote Daemon.
Execute ce script pour verifier que tout est correctement configure.
"""

import sys
import os
from pathlib import Path

def check_mark(ok):
    return "✓" if ok else "✗"

def main():
    print("=" * 50)
    print(" VERIFICATION PREREQUIS SUPERHOTE DAEMON")
    print("=" * 50)
    print()

    errors = []

    # 1. Python version
    print("[1] Python version...")
    py_version = sys.version_info
    ok = py_version >= (3, 7)
    print(f"    {check_mark(ok)} Python {py_version.major}.{py_version.minor}.{py_version.micro}")
    if not ok:
        errors.append("Python 3.7+ requis")

    # 2. Modules Python
    print()
    print("[2] Modules Python...")

    modules = {
        "selenium": "pip3 install selenium",
        "pymysql": "pip3 install pymysql",
    }

    for module, install_cmd in modules.items():
        try:
            __import__(module)
            print(f"    {check_mark(True)} {module}")
        except ImportError:
            print(f"    {check_mark(False)} {module} - MANQUANT")
            errors.append(f"Module {module} manquant. Installer: {install_cmd}")

    # 3. Chrome/Chromium
    print()
    print("[3] Navigateur Chrome/Chromium...")

    import shutil
    chrome_paths = ["google-chrome", "chromium", "chromium-browser"]
    chrome_found = None
    for chrome in chrome_paths:
        if shutil.which(chrome):
            chrome_found = chrome
            break

    if chrome_found:
        print(f"    {check_mark(True)} {chrome_found} detecte")
        # Version
        import subprocess
        try:
            result = subprocess.run([chrome_found, "--version"], capture_output=True, text=True)
            print(f"        Version: {result.stdout.strip()}")
        except:
            pass
    else:
        print(f"    {check_mark(False)} Chrome/Chromium NON INSTALLE")
        errors.append("Chrome ou Chromium requis. Installer: sudo apt install chromium-browser")

    # 4. ChromeDriver
    print()
    print("[4] ChromeDriver...")

    chromedriver = shutil.which("chromedriver")
    if chromedriver:
        print(f"    {check_mark(True)} ChromeDriver detecte: {chromedriver}")
    else:
        print(f"    {check_mark(False)} ChromeDriver NON INSTALLE")
        errors.append("ChromeDriver requis. Installer: sudo apt install chromium-chromedriver")

    # 5. Fichiers de configuration
    print()
    print("[5] Configuration...")

    base_dir = Path(__file__).parent.parent.parent
    config_file = base_dir / "config" / "config.ini"
    superhote_config = Path(__file__).parent / "config_superhote.ini"

    if config_file.exists():
        print(f"    {check_mark(True)} config/config.ini")
    else:
        print(f"    {check_mark(False)} config/config.ini MANQUANT")
        errors.append("Fichier config/config.ini manquant")

    if superhote_config.exists():
        print(f"    {check_mark(True)} config_superhote.ini")

        # Verifier les credentials
        import configparser
        cfg = configparser.ConfigParser()
        cfg.read(superhote_config)
        email = cfg.get("SUPERHOTE", "email", fallback="")
        if email and email != "votre_email@example.com":
            print(f"        Email configure: {email[:3]}***")
        else:
            print(f"    {check_mark(False)} Email Superhote non configure!")
            errors.append("Configurer email/password dans config_superhote.ini")
    else:
        print(f"    {check_mark(False)} config_superhote.ini MANQUANT")
        errors.append("Copier config_superhote.ini.example vers config_superhote.ini et configurer")

    # 6. Connexion BDD
    print()
    print("[6] Connexion base de donnees...")

    if config_file.exists():
        try:
            import configparser
            import pymysql

            cfg = configparser.ConfigParser()
            cfg.read(config_file)

            conn = pymysql.connect(
                host=cfg.get("DATABASE", "host"),
                user=cfg.get("DATABASE", "user"),
                password=cfg.get("DATABASE", "password"),
                database=cfg.get("DATABASE", "database")
            )
            print(f"    {check_mark(True)} Connexion MySQL OK")
            conn.close()
        except Exception as e:
            print(f"    {check_mark(False)} Connexion MySQL ECHOUEE: {e}")
            errors.append(f"Erreur BDD: {e}")
    else:
        print(f"    {check_mark(False)} Impossible de tester (config.ini manquant)")

    # 7. Test Selenium (optionnel)
    print()
    print("[7] Test Selenium...")

    if chrome_found and chromedriver:
        try:
            from selenium import webdriver
            from selenium.webdriver.chrome.options import Options

            options = Options()
            options.add_argument("--headless=new")
            options.add_argument("--no-sandbox")
            options.add_argument("--disable-dev-shm-usage")
            options.add_argument("--disable-gpu")

            driver = webdriver.Chrome(options=options)
            driver.get("about:blank")
            print(f"    {check_mark(True)} Selenium fonctionne!")
            driver.quit()
        except Exception as e:
            print(f"    {check_mark(False)} Test Selenium ECHOUE: {e}")
            errors.append(f"Selenium: {e}")
    else:
        print(f"    - Ignore (Chrome/ChromeDriver manquant)")

    # Resume
    print()
    print("=" * 50)
    if errors:
        print(f" {len(errors)} ERREUR(S) DETECTEE(S)")
        print("=" * 50)
        for i, err in enumerate(errors, 1):
            print(f"  {i}. {err}")
        print()
        print("Corriger ces erreurs avant de lancer le daemon.")
        return 1
    else:
        print(" TOUT EST OK!")
        print("=" * 50)
        print()
        print("Lancer le daemon avec:")
        print("  python3 superhote_daemon_v2.py -w 2 -i 30")
        return 0


if __name__ == "__main__":
    sys.exit(main())
