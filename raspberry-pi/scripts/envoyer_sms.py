import serial
import pymysql
import time
import logging
import configparser
import signal
import sys

running = True

def signal_handler(signum, frame):
    global running
    sig_name = "SIGINT" if signum == signal.SIGINT else "SIGTERM"
    logging.info(f"Signal {sig_name} recu - arret en cours...")
    running = False

signal.signal(signal.SIGINT, signal_handler)
signal.signal(signal.SIGTERM, signal_handler)

import os
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LOG_DIR = os.path.join(BASE_DIR, "logs")
os.makedirs(LOG_DIR, exist_ok=True)

logging.basicConfig(
    filename=os.path.join(LOG_DIR, "envoyer_sms.log"),
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s"
)

config = configparser.ConfigParser()
config.read(os.path.join(BASE_DIR, "config", "config.ini"))

DB_HOST = config["DATABASE"]["host"]
DB_USER = config["DATABASE"]["user"]
DB_PASSWORD = config["DATABASE"]["password"]
DB_NAME = config["DATABASE"]["database"]
BAUDRATE = int(config["MODEMS"]["baudrate"])
PIN_CODE = config["MODEM"].get("pin", None)
SMSC_NUMBER = config["MODEM"].get("smsc", "").strip() or None

modems = {key: config["MODEMS"][key] for key in config["MODEMS"] if key.startswith("modem")}

def get_db_connection():
    try:
        db = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASSWORD, database=DB_NAME)
        cursor = db.cursor()
        logging.info("Connexion a la base de donnees reussie")
        return db, cursor
    except Exception as e:
        logging.error(f"Erreur de connexion a la base de donnees : {str(e)}")
        return None, None


def _at_cmd(ser, cmd, timeout=5, expect=b"OK"):
    ser.reset_input_buffer()
    ser.write((cmd + "\r").encode())
    deadline = time.time() + timeout
    buf = b""
    while time.time() < deadline:
        chunk = ser.read(ser.in_waiting or 1)
        if chunk:
            buf += chunk
            if expect and expect in buf:
                return buf.decode("utf-8", errors="ignore")
            if b"ERROR" in buf:
                resp = buf.decode("utf-8", errors="ignore")
                raise RuntimeError(f"AT error for '{cmd}': {resp.strip()}")
        else:
            time.sleep(0.1)
    resp = buf.decode("utf-8", errors="ignore")
    if expect is None:
        return resp
    raise TimeoutError(f"Timeout for '{cmd}': {resp.strip()}")


def _wait_for_sms_ready(ser, timeout=20):
    deadline = time.time() + timeout
    buf = b""
    while time.time() < deadline:
        chunk = ser.read(ser.in_waiting or 1)
        if chunk:
            buf += chunk
            if b"SMS Ready" in buf:
                logging.info("SIM800C: SMS Ready received")
                return True
        else:
            time.sleep(0.2)
    logging.warning(f"SIM800C: SMS Ready not seen within {timeout}s")
    return False


def _init_modem(ser):
    """Full modem init sequence matching working minicom sequence."""
    time.sleep(1)
    ser.reset_input_buffer()

    _at_cmd(ser, "ATZ", timeout=3)
    time.sleep(1)

    resp = _at_cmd(ser, 'AT+CPIN?', timeout=5)

    if "SIM PIN" in resp and PIN_CODE:
        _at_cmd(ser, f'AT+CPIN="{PIN_CODE}"', timeout=10)
        _wait_for_sms_ready(ser, timeout=20)
    elif "READY" in resp:
        logging.info("SIM already unlocked, issuing AT+CFUN=1,1 for full re-init")
        try:
            ser.write(b"AT+CFUN=1,1\r")
            time.sleep(8)
            ser.close()
            time.sleep(3)
            ser.port = ser.port  # preserve port name
            ser.open()
            time.sleep(1)
            ser.reset_input_buffer()
            _wait_for_sms_ready(ser, timeout=25)
            resp2 = _at_cmd(ser, 'AT+CPIN?', timeout=5)
            if "SIM PIN" in resp2 and PIN_CODE:
                _at_cmd(ser, f'AT+CPIN="{PIN_CODE}"', timeout=10)
                _wait_for_sms_ready(ser, timeout=20)
        except Exception as e:
            logging.warning(f"CFUN reset failed, continuing anyway: {e}")
            time.sleep(5)

    _at_cmd(ser, "AT+CMEE=2", timeout=3)
    _at_cmd(ser, 'AT+CSCS="GSM"', timeout=3)
    _at_cmd(ser, "AT+CMGF=1", timeout=3)

    if SMSC_NUMBER:
        _at_cmd(ser, f'AT+CSCA="{SMSC_NUMBER}"', timeout=3)

    resp = _at_cmd(ser, "AT+CREG?", timeout=5)
    logging.info(f"Network registration: {resp.strip()}")


def envoyer_sms(modem_port, destinataire, message):
    """Send SMS via raw AT commands on the given modem port."""
    ser = None
    try:
        ser = serial.Serial(modem_port, BAUDRATE, timeout=3)
        _init_modem(ser)

        ser.reset_input_buffer()
        ser.write(f'AT+CMGS="{destinataire}"\r'.encode())

        deadline = time.time() + 10
        buf = b""
        got_prompt = False
        while time.time() < deadline:
            chunk = ser.read(ser.in_waiting or 1)
            if chunk:
                buf += chunk
                if b">" in buf:
                    got_prompt = True
                    break
                if b"ERROR" in buf:
                    raise RuntimeError(f"CMGS prompt error: {buf.decode('utf-8', errors='ignore')}")
            else:
                time.sleep(0.1)

        if not got_prompt:
            raise TimeoutError(f"No '>' prompt: {buf.decode('utf-8', errors='ignore')}")

        ser.write(message.encode("ascii", errors="replace"))
        ser.write(bytes([26]))

        deadline = time.time() + 30
        buf = b""
        while time.time() < deadline:
            chunk = ser.read(ser.in_waiting or 1)
            if chunk:
                buf += chunk
                resp_str = buf.decode("utf-8", errors="ignore")
                if "+CMGS:" in resp_str:
                    logging.info(f"SMS envoye a {destinataire} via {modem_port}: {resp_str.strip()}")
                    return True
                if "ERROR" in resp_str:
                    raise RuntimeError(f"SMS send error: {resp_str.strip()}")
            else:
                time.sleep(0.2)

        raise TimeoutError(f"No +CMGS confirmation: {buf.decode('utf-8', errors='ignore')}")

    except Exception as e:
        logging.error(f"Erreur d'envoi de SMS via {modem_port} : {str(e)}")
        return False
    finally:
        if ser and ser.is_open:
            try:
                ser.close()
            except Exception:
                pass


def traiter_sms_a_envoyer():
    db, cursor = get_db_connection()
    if not db or not cursor:
        return

    cursor.execute("SELECT id, receiver, message, modem FROM sms_outbox WHERE status='pending'")
    messages = cursor.fetchall()
    logging.info(f"{len(messages)} SMS en attente trouves.")

    for sms_id, destinataire, message, desired_modem in messages:
        logging.info(f"Traitement SMS ID={sms_id}, modem desire={desired_modem}")

        envoi_reussi = False

        if desired_modem and desired_modem.strip():
            modem_port = modems.get(desired_modem.strip(), desired_modem.strip())
            logging.info(f"Tentative d'envoi du SMS {sms_id} via {modem_port} (demande: {desired_modem})")
            if envoyer_sms(modem_port, destinataire, message):
                cursor.execute("""
                    UPDATE sms_outbox
                    SET status='sent', sent_at=NOW(), modem=%s
                    WHERE id=%s
                """, (modem_port, sms_id))
                db.commit()
                logging.info(f"SMS ID {sms_id} envoye via {modem_port}.")
                envoi_reussi = True
            else:
                logging.warning(f"Echec de l'envoi via {modem_port} pour SMS ID {sms_id}.")

        if not envoi_reussi:
            logging.info(f"Fallback : tentative sur tous les modems pour SMS {sms_id}")
            tried_port = modems.get(desired_modem.strip(), desired_modem.strip()) if desired_modem and desired_modem.strip() else None
            for m_name, m_port in modems.items():
                if m_port == tried_port:
                    continue
                if envoyer_sms(m_port, destinataire, message):
                    cursor.execute("""
                        UPDATE sms_outbox
                        SET status='sent', sent_at=NOW(), modem=%s
                        WHERE id=%s
                    """, (m_port, sms_id))
                    db.commit()
                    logging.info(f"SMS ID {sms_id} envoye via fallback {m_port}.")
                    envoi_reussi = True
                    break

        if not envoi_reussi:
            logging.error(f"Aucun modem n'a pu envoyer le SMS ID {sms_id}.")

    db.close()

if __name__ == '__main__':
    logging.info("=== Demarrage du daemon d'envoi SMS ===")
    while running:
        logging.info("Verification des SMS en attente...")
        try:
            traiter_sms_a_envoyer()
        except Exception as e:
            logging.error(f"Erreur dans le traitement: {e}")

        for _ in range(30):
            if not running:
                break
            time.sleep(1)

    logging.info("=== Arret du daemon d'envoi SMS ===")
