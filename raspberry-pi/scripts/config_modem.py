#!/usr/bin/python3
import serial
import time
import logging
import configparser

# Configuration des logs
logging.basicConfig(
    filename="/home/raphael/sms_project/logs/config_modem.log",
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s"
)

# Charger la configuration
config = configparser.ConfigParser()
config.read("/home/raphael/sms_project/config/config.ini")

BAUDRATE = int(config["MODEMS"]["baudrate"])
# Récupérer les ports définis pour les modems
modems = {key: config["MODEMS"][key] for key in config["MODEMS"] if key.startswith("modem")}

def configure_modem(port):
    try:
        modem = serial.Serial(port, BAUDRATE, timeout=3)
        time.sleep(2)
        logging.info(f"✅ Modem connecté sur {port}")

        # Configuration de base pour le mode texte
        modem.write(b'AT+CMGF=1\r')
        time.sleep(1)
        response = modem.readlines()
        logging.info(f"Réponse AT+CMGF=1 sur {port}: {response}")

        # Configurer le stockage sur la SIM
        modem.write(b'AT+CPMS="SM","SM","SM"\r')
        time.sleep(1)
        response = modem.readlines()
        logging.info(f"Réponse AT+CPMS sur {port}: {response}")

        # Configurer les notifications des SMS
        modem.write(b'AT+CNMI=2,1,0,0,0\r')
        time.sleep(1)
        response = modem.readlines()
        logging.info(f"Réponse AT+CNMI sur {port}: {response}")

        modem.close()
        logging.info(f"✅ Configuration terminée sur {port}")
    except Exception as e:
        logging.error(f"❌ Erreur de configuration sur {port}: {str(e)}")

if __name__ == '__main__':
    while True:
        for name, port in modems.items():
            configure_modem(port)
        time.sleep(60)  # Vérifier et reconfigurer toutes les 60 secondes
