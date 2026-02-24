import serial
import pymysql
import time
import logging
import configparser
import signal
import sys

# Variable globale pour arrêt propre
running = True

def signal_handler(signum, frame):
    """Gestion des signaux pour arrêt propre."""
    global running
    sig_name = "SIGINT" if signum == signal.SIGINT else "SIGTERM"
    logging.info(f"Signal {sig_name} recu - arret en cours...")
    running = False

signal.signal(signal.SIGINT, signal_handler)
signal.signal(signal.SIGTERM, signal_handler)

# Configuration des logs
logging.basicConfig(
    filename="/home/raphael/sms_project/logs/envoyer_sms.log",
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s"
)

# Charger la configuration
config = configparser.ConfigParser()
config.read("/home/raphael/sms_project/config/config.ini")

DB_HOST = config["DATABASE"]["host"]
DB_USER = config["DATABASE"]["user"]
DB_PASSWORD = config["DATABASE"]["password"]
DB_NAME = config["DATABASE"]["database"]
BAUDRATE = int(config["MODEMS"]["baudrate"])

# Récupérer les modems
modems = {key: config["MODEMS"][key] for key in config["MODEMS"] if key.startswith("modem")}

def get_db_connection():
    """Établit une connexion MySQL et renvoie (db, cursor)."""
    try:
        db = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASSWORD, database=DB_NAME)
        cursor = db.cursor()
        logging.info("✅ Connexion à la base de données réussie")
        return db, cursor
    except Exception as e:
        logging.error(f"❌ Erreur de connexion à la base de données : {str(e)}")
        return None, None

def envoyer_sms(modem_port, destinataire, message):
    """
    Tente d'envoyer un SMS via le modem_port donné.
    Renvoie True si succès, False si échec.
    """
    try:
        modem = serial.Serial(modem_port, BAUDRATE, timeout=5)
        time.sleep(2)
        logging.info(f"📡 Connexion au modem {modem_port} pour envoi de SMS")

        # Mode texte
        modem.write(b'AT+CMGF=1\r')
        time.sleep(1)

        # Envoi du SMS
        at_cmd = f'AT+CMGS="{destinataire}"\r'
        modem.write(at_cmd.encode())
        time.sleep(1)

        # Saisir le message, terminer par Ctrl+Z (ASCII 26)
        sms_body = f'{message}\x1A'
        modem.write(sms_body.encode())
        time.sleep(3)

        logging.info(f"📩 SMS envoyé à {destinataire} via {modem_port} : {message}")
        modem.close()
        return True
    except Exception as e:
        logging.error(f"❌ Erreur d'envoi de SMS via {modem_port} : {str(e)}")
        return False

def traiter_sms_a_envoyer():
    """
    Lit la table sms_outbox (status='pending'), tente d'envoyer sur le modem indiqué,
    sinon fallback sur tous les modems. Marque 'sent' dès qu'un envoi réussit.
    """
    db, cursor = get_db_connection()
    if not db or not cursor:
        return

    # Récupérer les SMS en attente, y compris la colonne 'modem'
    cursor.execute("SELECT id, receiver, message, modem FROM sms_outbox WHERE status='pending'")
    messages = cursor.fetchall()
    logging.info(f"📤 {len(messages)} SMS en attente trouvés.")

    for sms_id, destinataire, message, desired_modem in messages:
        logging.info(f"🔎 Traitement SMS ID={sms_id}, modem desire={desired_modem}")

        envoi_reussi = False

        # 1) Si la colonne 'modem' est renseignée, on tente ce modem en priorité
        if desired_modem and desired_modem.strip():
            logging.info(f"➡️ Tentative d'envoi du SMS {sms_id} via {desired_modem}")
            if envoyer_sms(desired_modem, destinataire, message):
                cursor.execute("""
                    UPDATE sms_outbox
                    SET status='sent', sent_at=NOW()
                    WHERE id=%s
                """, (sms_id,))
                db.commit()
                logging.info(f"✅ SMS ID {sms_id} envoyé via {desired_modem}.")
                envoi_reussi = True
            else:
                logging.warning(f"❌ Échec de l'envoi via {desired_modem} pour SMS ID {sms_id}.")

        # 2) Si le modem désiré est vide ou a échoué, fallback sur tous les modems
        if not envoi_reussi:
            logging.info(f"🔄 Fallback : tentative sur tous les modems pour SMS {sms_id}")
            for m_name, m_port in modems.items():
                # Si c'est le même que desired_modem, on retente ou on skip ? À toi de voir
                # if m_port == desired_modem:
                #     continue  # ne retente pas si déjà échoué
                if envoyer_sms(m_port, destinataire, message):
                    cursor.execute("""
                        UPDATE sms_outbox
                        SET status='sent', sent_at=NOW(), modem=%s
                        WHERE id=%s
                    """, (m_port, sms_id))
                    db.commit()
                    logging.info(f"✅ SMS ID {sms_id} envoyé via fallback {m_port}.")
                    envoi_reussi = True
                    break

        if not envoi_reussi:
            logging.error(f"❌ Aucun modem n'a pu envoyer le SMS ID {sms_id}.")

    db.close()

if __name__ == '__main__':
    logging.info("=== Demarrage du daemon d'envoi SMS ===")
    while running:
        logging.info("Verification des SMS en attente...")
        try:
            traiter_sms_a_envoyer()
        except Exception as e:
            logging.error(f"Erreur dans le traitement: {e}")

        # Attente interruptible
        for _ in range(30):
            if not running:
                break
            time.sleep(1)

    logging.info("=== Arret du daemon d'envoi SMS ===")
