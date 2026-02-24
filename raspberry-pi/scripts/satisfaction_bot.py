#!/usr/bin/env python3
# coding: utf-8

import serial
import time
import logging
import configparser
import pymysql
import gammu
import binascii
import re
from typing import Optional
from openai import OpenAI
from collections import defaultdict
# (optionnel) Masquer un warning de python-gammu :
# import warnings
# warnings.filterwarnings("ignore",
#                         message=r".*PY_SSIZE_T_CLEAN will be required for '#' formats.*",
#                         category=DeprecationWarning)

# --- Logging ---
logging.basicConfig(
    filename="/home/raphael/sms_project/logs/satisfaction_bot.log",
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s"
)

# --- Config ---
cfg = configparser.ConfigParser()
cfg.read("/home/raphael/sms_project/config/config.ini")

# Base de données
DB_HOST = cfg["DATABASE"]["host"]
DB_USER = cfg["DATABASE"]["user"]
DB_PASSWORD = cfg["DATABASE"]["password"]
DB_NAME = cfg["DATABASE"]["database"]

# OpenAI (Temporairement non utilisé)
OPENAI_KEY = cfg["OPENAI"]["api_key"]
SAT_TABLE = cfg["SATISFACTION"]["table"]

# Fallback admin
FALLBACK_NUMBER = cfg["FALLBACK"]["numero_admin"]
FALLBACK_KEYWORDS = [k.strip().lower() for k in cfg["FALLBACK"]["mots_declencheurs"].split(",") if k.strip()]

# Modem
DEVICE_PORT = cfg["MODEM"]["port"]
BAUDRATE = int(cfg["MODEM"].get("baudrate", "115200"))
PIN_CODE = cfg["MODEM"].get("pin", None)

# -----------------------------------------------------------------------------
# UTILS : normalisation / validation des numéros mobiles FR (06/07)
# -----------------------------------------------------------------------------
def normalize_phone(phone_raw: str) -> Optional[str]:
    """
    Nettoie et normalise les numéros mobiles FR (uniquement 06/07).
    Règles:
      - supprime espaces, points, tirets, parenthèses
      - 0033XXXXXXXXX -> +33XXXXXXXXX
      - 0XXXXXXXXX (10 chiffres) -> +33XXXXXXXXX (en supprimant le 0)
      - +330XXXXXXXX -> +33XXXXXXXX (supprime le 0 après l’indicatif)
      - validation finale stricte: ^\\+33(6|7)\\d{8}$
    Retourne le numéro +336/ +337 si OK, sinon None.
    """
    if not phone_raw:
        return None

    s = phone_raw.strip()

    # retirer séparateurs visuels
    s = re.sub(r"[ \t\.\-\(\)]", "", s)

    # 0033... -> +33...
    if s.startswith("0033"):
        s = "+" + s[2:]  # remplace '00' par '+'

    # +330... -> +33... (supprimer 0 après indicatif)
    if s.startswith("+33") and len(s) >= 4 and s[3] == "0":
        s = "+33" + s[4:]

    # 0XXXXXXXXX (10 chiffres) -> +33XXXXXXXXX
    if re.fullmatch(r"0\d{9}", s):
        s = "+33" + s[1:]

    # Validation finale : uniquement mobiles FR 06/07
    if re.fullmatch(r"\+33(6|7)\d{8}", s):
        return s

    return None

# --- BDD utils ---
def get_db_connection():
    try:
        return pymysql.connect(
            host=DB_HOST, user=DB_USER, password=DB_PASSWORD,
            database=DB_NAME, charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor
        )
    except Exception as e:
        logging.error(f"❌ Erreur connexion BDD: {e}")
        return None

def log_conversation(db, sender, role, message):
    try:
        with db.cursor() as cur:
            cur.execute(
                f"INSERT INTO {SAT_TABLE}(sender, role, message) VALUES(%s, %s, %s)",
                (sender, role, message)
            )
        db.commit()
    except Exception as e:
        logging.error(f"❌ log_conversation({role}): {e}")

# --- Décodage hex UCS-2 ---
def maybe_decode_hex(s):
    m = re.match(r'^(([0-9A-Fa-f\s])+)', s)
    if not m:
        return s
    hp = "".join(m.group(0).split())
    if len(hp) % 4 != 0:
        return s
    try:
        txt = binascii.unhexlify(hp).decode("utf-16-be", errors="ignore")
        rest = s[len(m.group(0)):].strip()
        return txt + (" " + rest if rest else "")
    except:
        return s

# --- 1) Lecture des SMS entrants via AT ---
def read_incoming_sms():
    logging.info("=== Début lecture SMS entrants ===")
    db = get_db_connection()
    if not db:
        return

    try:
        m = serial.Serial(DEVICE_PORT, BAUDRATE, timeout=5)
        time.sleep(2)
        logging.info(f"Modem connecté sur {DEVICE_PORT}")

        if PIN_CODE:
            m.write(f'AT+CPIN="{PIN_CODE}"\r'.encode())
            time.sleep(2)

        m.write(b'AT+CMGF=1\r'); time.sleep(1)
        m.write(b'AT+CPMS="SM","SM","SM"\r'); time.sleep(1)
        m.reset_input_buffer()
        m.write(b'AT+CMGL="ALL"\r'); time.sleep(5)
        raw = m.readlines()

        sms_blocks = []
        cur_blk = []
        for ln in raw:
            try:
                txt = ln.decode('utf-8', 'ignore').strip()
            except UnicodeDecodeError:
                continue
            if not txt:
                continue
            if txt.startswith("+CMGL:"):
                if cur_blk:
                    sms_blocks.append(cur_blk)
                cur_blk = [txt]
            elif cur_blk and txt != "OK":
                cur_blk.append(txt)
        if cur_blk:
            sms_blocks.append(cur_blk)

        to_delete = []
        grouped = defaultdict(list)
        for block in sms_blocks:
            if not block or not block[0].startswith("+CMGL:"):
                continue
            hdr = block[0]
            parts = hdr.split(",")
            if len(parts) < 3:
                logging.warning(f"En-tête SMS malformé ignoré: {hdr}")
                continue
            sid = parts[0].split()[1]
            sender = parts[2].strip('"')
            body = " ".join(l for l in block[1:] if l and l != "OK")
            body = maybe_decode_hex(body)
            if sender:
                grouped[sender].append(body)
                if sid.isdigit():
                    to_delete.append(int(sid))
        
        if not grouped:
            logging.info("Aucun nouveau SMS à traiter.")

        with db.cursor() as cursor:
            for sender, frags in grouped.items():
                full_message = " ".join(frags)
                try:
                    cursor.execute(
                        "INSERT INTO sms_in(sender, message, modem) VALUES(%s, %s, %s)",
                        (sender, full_message, DEVICE_PORT)
                    )
                    db.commit()
                    # Le log de la conversation user est toujours utile
                    log_conversation(db, sender, "user", full_message)
                    logging.info(f"Inséré sms_in pour {sender}")
                except Exception as e:
                    logging.error(f"Erreur insertion sms_in: {e}")
                    db.rollback()

        for sid in to_delete:
            try:
                m.write(f'AT+CMGD={sid}\r'.encode())
                time.sleep(0.5)
                m.read_until(b'OK\r\n')
                logging.info(f"Supprimé SMS ID={sid}")
            except Exception as e:
                logging.error(f"Erreur suppression SMS ID={sid}: {e}")

        m.close()
    except Exception as e:
        logging.error(f"Erreur lecture modem: {e}")
    finally:
        if db and db.open:
            db.close()
        logging.info("=== Fin lecture SMS entrants ===")

# --- 2) Génération IA via OpenAI (TEMPORAIREMENT NON UTILISÉE) ---
def generate_ai_reply(db, sender):
    # Cette fonction n'est plus appelée dans le mode "Fallback Uniquement"
    client = OpenAI(api_key=OPENAI_KEY)
    try:
        with db.cursor() as cur:
            cur.execute("SELECT content FROM ai_prompts WHERE `key`=%s", ("satisfaction",))
            r = cur.fetchone()
            rules = r["content"] if r else "Tu es un assistant pour une enquête de satisfaction."
            
            cur.execute(f"SELECT role, message FROM {SAT_TABLE} WHERE sender=%s ORDER BY id", (sender,))
            history_messages = [{"role": row["role"], "content": row["message"]} for row in cur.fetchall()]
            
            system_prompt = {"role": "system", "content": f"{rules}\nRéponds de manière concise et humaine."}
            messages = [system_prompt] + history_messages

            resp = client.chat.completions.create(
                model="gpt-4o",
                messages=messages,
                max_tokens=100,
                temperature=0.5
            )
            return resp.choices[0].message.content.strip()
    except Exception as e:
        logging.error(f"❌ OpenAI error: {e}")
        return "Merci de votre message. Nous revenons vers vous rapidement."

# --- 3) Envoi SMS via python-gammu ---
def send_sms_via_gammu(to, text, creator="Bot"):
    logging.info(f"Envoi SMS à {to} via {creator}")
    try:
        info = {
            "Class":   -1,
            "Unicode": True,
            "Entries": [{"ID": "ConcatenatedTextLong", "Buffer": text}]
        }
        parts = gammu.EncodeSMS(info)
        
        sm = gammu.StateMachine()
        sm.SetConfig(0, {"Device": DEVICE_PORT, "Connection": "at115200"})
        sm.Init()

        if PIN_CODE:
            if sm.GetSecurityStatus() == "PIN":
                sm.EnterSecurityCode("PIN", PIN_CODE)

        for idx, p in enumerate(parts, 1):
            p["SMSC"] = {"Location": 1}
            p["Number"] = to
            sm.SendSMS(p)
            time.sleep(1)
            logging.info(f"Fragment {idx}/{len(parts)} envoyé à {to}")
            
        sm.Terminate()
        logging.info(f"✅ SMS complet envoyé à {to}")
        return True
    except gammu.GammuException as e:
        logging.error(f"❌ Erreur Gammu lors de l'envoi à {to}: {e} (Code: {e.errorcode})")
        return False
    except Exception as e:
        logging.error(f"❌ Erreur inattendue dans send_sms_via_gammu: {e}")
        return False

# --- 4) Traitement des conversations (MODIFIÉ POUR FALLBACK UNIQUEMENT) ---
def process_conversations():
    """
    Traite les messages de sms_in non encore traités (fallback=0).
    MODIFIÉ : Transfère systématiquement TOUS les messages à l'admin,
    sans appeler l'IA, au format "sender : message".
    """
    logging.info("--- Début traitement des conversations (Mode: Fallback Uniquement) ---")
    db = get_db_connection()
    if not db:
        return

    try:
        with db.cursor() as cur:
            # Sélectionne les messages non encore traités
            cur.execute("SELECT id, sender, message FROM sms_in WHERE fallback = 0")
            messages_to_process = cur.fetchall()

            if not messages_to_process:
                logging.info("Aucun nouveau message à transférer.")
                return

            for sms in messages_to_process:
                sms_id, sender, message = sms['id'], sms['sender'], sms['message']
                
                logging.info(f"Transfert systématique du SMS ID {sms_id} de {sender} à l'admin.")

                # Formatage du message selon le modèle demandé: "sender : message"
                fallback_text = f"{sender} : {message}"

                # Envoi du message formaté au numéro de l'admin
                send_sms_via_gammu(FALLBACK_NUMBER, fallback_text, "Fallback")
                
                # On marque le message comme traité pour ne pas le reprendre en boucle
                cur.execute("UPDATE sms_in SET fallback = 1 WHERE id = %s", (sms_id,))
                db.commit()

    except Exception as e:
        logging.error(f"❌ Erreur dans process_conversations (Mode Fallback): {e}")
        db.rollback()
    finally:
        if db and db.open:
            db.close()
        logging.info("--- Fin traitement des conversations ---")


# --- 5) Traitement de la boîte d'envoi (PHP) ---
def process_outbox():
    db = get_db_connection()
    if not db:
        return
    
    try:
        with db.cursor() as cur:
            cur.execute("SELECT id, receiver, message FROM sms_outbox WHERE status='pending'")
            rows = cur.fetchall()

            if rows:
                logging.info(f"Traitement de {len(rows)} message(s) dans outbox.")

            for row in rows:
                sms_id, to_raw, msg = row['id'], row['receiver'], row['message']

                # Normaliser puis filtrer : n'envoyer que vers +33(6|7)XXXXXXXX ; sinon marquer failed
                to_norm = normalize_phone(to_raw)
                if not to_norm:
                    logging.warning(f"Numéro invalide / non mobile FR, passage en failed : {to_raw}")
                    cur.execute(
                        "UPDATE sms_outbox SET status='failed', sent_at=NOW() WHERE id = %s",
                        (sms_id,)
                    )
                    db.commit()
                    continue

                if send_sms_via_gammu(to_norm, msg, "Outbox"):
                    cur.execute("UPDATE sms_outbox SET status='sent', sent_at=NOW() WHERE id = %s", (sms_id,))
                    db.commit()
    except Exception as e:
        logging.error(f"❌ Erreur dans process_outbox: {e}")
        db.rollback()
    finally:
        if db and db.open:
            db.close()

# --- 6) Boucle principale ---
if __name__ == "__main__":
    logging.info("=== Démarrage démon SMS Python ===")
    try:
        while True:
            read_incoming_sms()
            process_conversations()
            process_outbox()
            logging.info("--- Cycle terminé, attente de 15 secondes. ---")
            time.sleep(15)

    except KeyboardInterrupt:
        logging.info("Arrêt demandé par l'utilisateur (Ctrl+C).")
    except Exception as e:
        logging.critical(f"❌ ERREUR CRITIQUE DANS LA BOUCLE PRINCIPALE: {e}")
    finally:
        logging.info("=== Arrêt du démon SMS Python ===")
