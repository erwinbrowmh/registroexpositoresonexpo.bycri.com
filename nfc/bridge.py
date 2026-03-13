import asyncio
import json
import logging
import threading
import time
from smartcard.System import readers
from smartcard.util import toHexString
from smartcard.CardMonitoring import CardMonitor, CardObserver
import websockets

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

active_clients = set()
main_loop = None
pending_action = None 
pending_text = None

card_lock = threading.Lock()

def safe_transmit(connection, command):
    try:
        data, s1, s2 = connection.transmit(command)
        return data, s1, s2
    except Exception as e:
        return [], 0x6F, 0x00

def get_card_info(connection):
    try:
        res, s1, s2 = safe_transmit(connection, [0xFF, 0xCA, 0x00, 0x00, 0x00])
        if len(res) == 7:
            return "NXP - NTAG213 / Ultralight EV1", 45
        # Fallback agresivo: Asumimos NTAG213 (45 págs) si falla la detección
        # para intentar desbloqueo en páginas altas (41+)
        return "Posible NTAG213 Bloqueado", 45 
    except:
        return "Desconocido", 45

def get_factory_safe_header(connection):
    """
    Detecta y preserva los bloques de control de fábrica (Lock Control TLV, Memory Control TLV)
    ubicados al inicio de la memoria de usuario (Página 4 en adelante).
    Esto es crucial para no sobrescribir la configuración de fábrica del chip.
    """
    try:
        # Leemos las primeras 4 páginas de usuario (4, 5, 6, 7) = 16 bytes
        data, ok = read_ultralight_pages(connection, 4)
        if not ok or len(data) < 5:
            return []

        header = []
        offset = 0
        
        # Iteramos buscando TLVs de sistema (Lock Control=0x01, Memory Control=0x02)
        # El loop se detiene si encontramos NDEF (0x03), Null (0x00) o Terminator (0xFE)
        while offset < len(data) - 2:
            tag = data[offset]
            if tag == 0x01 or tag == 0x02: # System TLVs
                length = data[offset+1]
                tlv_len = 2 + length
                
                # Verificamos que el TLV esté completo en los datos leídos
                if offset + tlv_len <= len(data):
                    header.extend(data[offset : offset + tlv_len])
                    offset += tlv_len
                else:
                    break # TLV incompleto o corrupto, paramos aquí
            else:
                break # Encontramos datos de usuario o fin de estructura
                
        return header
    except Exception as e:
        logging.error(f"Error reading factory header: {e}")
        return []

def read_ultralight_pages(connection, page):
    cmd = [0xFF, 0x00, 0x00, 0x00, 0x05, 0xD4, 0x40, 0x01, 0x30, page]
    data, s1, s2 = safe_transmit(connection, cmd)
    if s1 != 0x90:
        return None, False
    if len(data) >= 19 and data[0] == 0xD5 and data[1] == 0x41 and data[2] == 0x00:
        return data[3:19], True
    if len(data) >= 16:
        return data[-16:], True
    return None, False

def write_ultralight_page(connection, page, data_bytes):
    """Escribe 4 bytes en una página Ultralight usando Direct Transmit y Fallback"""
    if len(data_bytes) != 4:
        logging.error(f"Write Page {page}: Data length error ({len(data_bytes)})")
        return False
    
    # 1. Intento con Direct Transmit (D4 40 01 A2)
    # Algunos lectores/drivers prefieren esto para control total
    cmd_direct = [0xFF, 0x00, 0x00, 0x00, 0x09, 0xD4, 0x40, 0x01, 0xA2, page] + list(data_bytes)
    
    # 2. Intento con PCSC Standard Update Binary (FF D6 00)
    # Funciona mejor si el driver maneja el timing
    cmd_pcsc = [0xFF, 0xD6, 0x00, page, 0x04] + list(data_bytes)
    
    # 3. Intento agresivo con espera larga (100ms timeout en controlador)
    # D4 40 01 A2 ... con timeout extendido si el lector lo soporta
    
    # Estrategia: Probar Directo, si falla probar PCSC, con reintentos
    for attempt in range(4):
        try:
            # Preferencia: Directo primero (2 intentos), luego PCSC
            cmd = cmd_direct if attempt < 2 else cmd_pcsc
            method = "Direct" if attempt < 2 else "PCSC"
            
            res, s1, s2 = safe_transmit(connection, cmd)
            
            success = False
            if method == "Direct":
                # Esperamos D5 41 00
                # IMPORTANTE: D5 41 01 es ERROR (Timeout / NAK)
                if s1 == 0x90 and len(res) >= 3 and res[0] == 0xD5 and res[1] == 0x41 and res[2] == 0x00:
                    success = True
            else:
                # Esperamos 90 00
                if s1 == 0x90 and s2 == 0x00:
                    success = True
            
            if success:
                # Éxito confirmado
                time.sleep(0.02) 
                return True
            else:
                # Verificación de escritura (Read-After-Write)
                # Si el comando falló (Timeout/NAK), leemos la página para ver si se grabó igual.
                # Esto es común en NTAG213 con timings ajustados.
                try:
                    time.sleep(0.02)
                    read_cmd = [0xFF, 0x00, 0x00, 0x00, 0x05, 0xD4, 0x40, 0x01, 0x30, page]
                    r_data, r_s1, _ = safe_transmit(connection, read_cmd)
                    # Respuesta Read: D5 41 00 [16 bytes]
                    if r_s1 == 0x90 and len(r_data) >= 19 and r_data[0] == 0xD5:
                        # Extraer los 4 bytes de la página escrita (están al inicio del dump de 16)
                        page_content = list(r_data[3:7])
                        if page_content == list(data_bytes):
                            # logging.info(f"Write Page {page} Verified OK despite error")
                            return True
                except: pass

                # Si fallamos en Direct con D5 41 01, es probable que necesitemos Autenticación
                # O que el chip esté protegido (Password PWD en pág 43/44 para NTAG213)
                logging.warning(f"Write Page {page} ({method}) Attempt {attempt+1} Failed: SW={toHexString([s1, s2])} Res={toHexString(res)}")
                
                # Si es el primer fallo, intentamos autenticación por defecto (PWD default FF FF FF FF)
                if attempt == 0 and page >= 4:
                     try_auth_default(connection)
                     
                time.sleep(0.1) 
        except Exception as e:
            logging.error(f"Write Page {page} Attempt {attempt+1} Exception: {e}")
            time.sleep(0.1)
            
    return False

def try_auth_default(connection):
    """Intenta autenticar con passwords comunes (FF.. y 00..)"""
    passwords = [
        [0xFF, 0xFF, 0xFF, 0xFF], # Default NXP
        [0x00, 0x00, 0x00, 0x00], # Default Clean
        [0xD3, 0xF7, 0xD3, 0xF7], # Common NDEF
        [0xA0, 0xA1, 0xA2, 0xA3]  # Common Test
    ]
    
    for i, pwd in enumerate(passwords):
        # PWD Auth command (1B) -> Wrapped in InDataExchange
        cmd = [0xFF, 0x00, 0x00, 0x00, 0x09, 0xD4, 0x40, 0x01, 0x1B] + pwd
        try:
            res, s1, s2 = safe_transmit(connection, cmd)
            # Success response: D5 41 00 [PACK 2 bytes]
            if s1 == 0x90 and len(res) >= 3 and res[0] == 0xD5 and res[2] == 0x00:
                logging.info(f"Auth Success with Password {i} ({toHexString(pwd)})")
                return True
        except Exception as e:
            logging.debug(f"Auth attempt {i} failed: {e}")
            
    return False

def unlock_nfc_tag(connection, max_p):
    """Intenta desbloquear los bytes de bloqueo estáticos y dinámicos"""
    
    # 1. Desbloquear Lock Bytes Estáticos (Página 2, bytes 2-3)
    chunk, ok = read_ultralight_pages(connection, 0) # Lee pag 0-3
    if ok and len(chunk) >= 12:
        pag2 = list(chunk[8:12])
        if pag2[2] != 0x00 or pag2[3] != 0x00:
            pag2[2] = 0x00
            pag2[3] = 0x00
            write_ultralight_page(connection, 2, pag2)

    # 2. Desbloquear Lock Bytes Dinámicos (Solo si es NTAG213/215/216)
    dynamic_lock_page = None
    auth0_page = None
    access_page = None

    if max_p == 45: 
        dynamic_lock_page = 40
        auth0_page = 41
        access_page = 42
    elif max_p == 135: 
        dynamic_lock_page = 130
        auth0_page = 131
        access_page = 132
    elif max_p == 231: 
        dynamic_lock_page = 226
        auth0_page = 227
        access_page = 228
    
    if dynamic_lock_page:
        write_ultralight_page(connection, dynamic_lock_page, [0x00, 0x00, 0x00, 0x00])

    if auth0_page:
        # Restaurar AUTH0 a 0xFF (Deshabilitar protección por contraseña)
        # NTAG213 Pág 41: [MIRROR, RFU, MIRROR_PAGE, AUTH0]
        # Escribimos 00 00 00 FF
        write_ultralight_page(connection, auth0_page, [0x00, 0x00, 0x00, 0xFF])
        
    if access_page:
        # Restaurar ACCESS byte a 0x00 (Acceso libre)
        # NTAG213 Pág 42: [ACCESS, RFU, RFU, RFU]
        write_ultralight_page(connection, access_page, [0x00, 0x00, 0x00, 0x00])

def get_user_memory_limit(card_type, max_p):
    """Retorna la última página de memoria de USUARIO (excluyendo config)"""
    if "NTAG213" in card_type or max_p == 45: return 40 # 0-39 User, 40+ Config
    if "NTAG215" in card_type or max_p == 135: return 130
    if "NTAG216" in card_type or max_p == 231: return 226
    return max_p # Ultralight standard (16 pages)

def extract_printable_text(data):
    if not data or len(data) <= 16:
        return ""
    try:
        payload = bytes(data[16:]).rstrip(b'\x00')
        if not payload:
            return ""

        ndef_message = None
        idx = 0
        while idx < len(payload):
            tag = payload[idx]
            if tag == 0x00:
                idx += 1
                continue
            if tag == 0xFE:
                break
            if idx + 1 >= len(payload):
                break
            tlv_len = payload[idx + 1]
            value_start = idx + 2
            value_end = value_start + tlv_len
            if value_end > len(payload):
                break
            if tag == 0x03 and tlv_len > 0:
                ndef_message = payload[value_start:value_end]
                break
            idx = value_end

        candidates = []
        if ndef_message:
            rec_idx = 0
            while rec_idx < len(ndef_message):
                if rec_idx + 2 >= len(ndef_message):
                    break
                header = ndef_message[rec_idx]
                sr = (header & 0x10) != 0
                il = (header & 0x08) != 0
                tnf = header & 0x07
                type_len = ndef_message[rec_idx + 1]

                if sr:
                    payload_len = ndef_message[rec_idx + 2]
                    cursor = rec_idx + 3
                else:
                    if rec_idx + 5 >= len(ndef_message):
                        break
                    payload_len = int.from_bytes(ndef_message[rec_idx + 2:rec_idx + 6], byteorder='big')
                    cursor = rec_idx + 6

                id_len = 0
                if il:
                    if cursor >= len(ndef_message):
                        break
                    id_len = ndef_message[cursor]
                    cursor += 1

                type_start = cursor
                type_end = type_start + type_len
                id_end = type_end + id_len
                payload_start = id_end
                payload_end = payload_start + payload_len
                if payload_end > len(ndef_message):
                    break

                record_type = ndef_message[type_start:type_end]
                record_payload = ndef_message[payload_start:payload_end]

                if tnf == 0x01 and record_type == b'T' and len(record_payload) >= 1:
                    status = record_payload[0]
                    lang_len = status & 0x3F
                    text_start = 1 + lang_len
                    if text_start <= len(record_payload):
                        text_data = record_payload[text_start:]
                        encoding = 'utf-16' if (status & 0x80) else 'utf-8'
                        try:
                            decoded = text_data.decode(encoding, errors='ignore').strip()
                            if decoded:
                                candidates.append(decoded)
                        except Exception:
                            pass

                rec_idx = payload_end
                if (header & 0x40) != 0:
                    break

        if candidates:
            return max(candidates, key=len)

        ascii_fallback = ''.join(
            chr(b) if ((32 <= b <= 126) or b in (9, 10, 13) or (161 <= b <= 255)) else ' '
            for b in payload
        )
        cleaned = ' '.join(ascii_fallback.replace('\r', '\n').split())
        if cleaned.lower().startswith('en') and len(cleaned) > 2:
            third = cleaned[2]
            if third.isupper() or third.isdigit():
                cleaned = cleaned[2:].lstrip()
        return cleaned if len(cleaned) >= 3 else ""
    except Exception:
        return ""

def create_ndef_text_record(text):
    text_bytes = text.encode('utf-8')
    payload = bytes([0x02, 0x65, 0x6E]) + text_bytes 
    ndef_record = bytes([0xD1, 0x01, len(payload), 0x54]) + payload
    tlv = bytes([0x03, len(ndef_record)]) + ndef_record + bytes([0xFE])
    return list(tlv)

# Bloque corregido para bridge.py
class NFCBridgeObserver(CardObserver):
    def update(self, observable, actions):
        global pending_action, pending_text
        (addedcards, removedcards) = actions
        for card in addedcards:
            try:
                with card_lock:
                    connection = card.createConnection()
                    connection.connect()
                    
                    # 1. EJECUCIÓN PRIORITARIA: Reset o Write
                    if pending_action == 'reset':
                        res = self.factory_reset(connection)
                        pending_action = None # Limpiar estado inmediatamente
                        self.send_to_all(res)
                    elif pending_action == 'write':
                        res = self.manual_write(connection, pending_text)
                        pending_action = None
                        pending_text = None
                        self.send_to_all(res)

                    # 2. LECTURA POSTERIOR: Actualizar el mapa de memoria
                    uid_data, _, _ = safe_transmit(connection, [0xFF, 0xCA, 0x00, 0x00, 0x00])
                    uid_str = toHexString(uid_data).upper().replace(" ", ":")
                    card_type, max_p = get_card_info(connection)
                    
                    full_dump = []
                    raw_bytes = []
                    for page in range(0, max_p, 4):
                        chunk, ok = read_ultralight_pages(connection, page)
                        if not ok or not chunk:
                            chunk = [0x00] * 16
                        raw_bytes.extend(chunk)
                        for i in range(0, 16, 4):
                            if page + (i // 4) < max_p:
                                block = list(chunk[i:i+4])
                                full_dump.append(toHexString(block).upper())
                    plain_text = extract_printable_text(raw_bytes)

                    self.send_to_all({
                        "type": "raw_dump",
                        "blocks": full_dump,
                        "uid": uid_str,
                        "chip": card_type,
                        "pages": max_p,
                        "text": plain_text
                    })
            except Exception as e:
                logging.error(f"Error en operación de tarjeta: {e}")

    def factory_reset(self, conn):
        """Reset ultra-rápido: solo páginas críticas para compatibilidad NDEF"""
        try:
            # 0. Autenticación Proactiva (Intento de login antes de tocar nada)
            try_auth_default(conn)
            
            card_type, max_p = get_card_info(conn)
            
            # Intentar desbloquear antes de escribir
            unlock_nfc_tag(conn, max_p)
            
            # Límite de memoria de usuario (evita pisar config)
            user_limit = get_user_memory_limit(card_type, max_p)
            
            # 1. Obtener datos de fábrica para preservarlos
            factory_header = get_factory_safe_header(conn)
            
            # 2. Construir datos de limpieza (Header + Empty NDEF)
            # Empty NDEF TLV: 03 00 FE
            empty_ndef = [0x03, 0x00, 0xFE, 0x00]
            
            # Datos iniciales a escribir en Pág 4 y siguientes
            reset_data = factory_header + empty_ndef
            
            # Escribir el header + empty NDEF
            written_pages = 0
            for i in range(0, len(reset_data), 4):
                page = 4 + (i // 4)
                if page >= user_limit: break
                chunk = (reset_data[i:i+4] + [0x00]*4)[:4]
                write_ultralight_page(conn, page, chunk)
                written_pages = page

            # 3. Limpiar el resto de la memoria de usuario con ceros
            start_clear = written_pages + 1
            if start_clear < 4: start_clear = 4
            
            for p in range(start_clear, user_limit):
                write_ultralight_page(conn, p, [0x00, 0x00, 0x00, 0x00])

            # 4. Configurar Capability Container (CC) en Pág 3
            user_bytes = max(0, (user_limit - 4) * 4)
            cc_size = max(0x06, min(0xFF, user_bytes // 8))
            write_ultralight_page(conn, 3, [0xE1, 0x10, cc_size, 0x00])
            
            return {"type": "success", "message": "Chip restaurado y compatible con NDEF."}
        except Exception as e:
            return {"type": "error", "message": f"Error en reset: {str(e)}"}

    def manual_write(self, conn, text):
        """Escritura optimizada de texto plano"""
        try:
            # 0. Autenticación Proactiva
            try_auth_default(conn)
            
            card_type, max_p = get_card_info(conn)
            
            # Intentar desbloquear antes de escribir
            unlock_nfc_tag(conn, max_p)
            
            # Límite de memoria de usuario
            user_limit = get_user_memory_limit(card_type, max_p)
            
            # 1. Obtener datos de fábrica (Lock Control TLV) para NO borrarlos
            factory_header = get_factory_safe_header(conn)
            if factory_header:
                logging.info(f"Preserving Factory Header: {toHexString(factory_header)}")
            
            # Preparar datos NDEF
            ndef_payload = create_ndef_text_record(text)
            
            # Combinar Header de Fábrica + NDEF Payload
            data = factory_header + ndef_payload
            
            # Configurar CC (Página 3)
            user_bytes = max(0, (user_limit - 4) * 4)
            cc_size = max(0x06, min(0xFF, user_bytes // 8))
            write_ultralight_page(conn, 3, [0xE1, 0x10, cc_size, 0x00])
            
            # Escritura en UNA SOLA PASADA (Datos + Limpieza del resto)
            # Esto evita doble escritura en las mismas páginas y es más rápido
            written_pages = 0
            
            # 2. Escribir datos
            for i in range(0, len(data), 4):
                page = 4 + (i // 4)
                if page >= user_limit: break # Evitar desbordamiento y pisar config
                chunk = (data[i:i+4] + [0x00]*4)[:4]
                if not write_ultralight_page(conn, page, chunk):
                    return {"type": "error", "message": f"Fallo al escribir pág {page}"}
                written_pages = page
            
            # 3. Limpiar páginas restantes
            start_clear = written_pages + 1
            if start_clear < 4: start_clear = 4 # Por seguridad
            
            for p in range(start_clear, user_limit):
                if not write_ultralight_page(conn, p, [0x00, 0x00, 0x00, 0x00]):
                    # No abortamos si falla limpieza, pero logueamos
                    logging.warning(f"Fallo al limpiar pág {p}")
                
            return {"type": "success", "message": "Información grabada correctamente."}
        except Exception as e:
            return {"type": "error", "message": f"Error al grabar: {str(e)}"}

    def send_to_all(self, message):
        if active_clients and main_loop:
            msg = json.dumps(message)
            for c in active_clients:
                asyncio.run_coroutine_threadsafe(c.send(msg), main_loop)

async def handler(ws):
    active_clients.add(ws)
    try:
        await ws.send(json.dumps({"type": "status", "message": "Bridge Conectado."}))
        async for msg in ws:
            data = json.loads(msg)
            global pending_action, pending_text
            if data['type'] == 'write_request':
                pending_action, pending_text = 'write', data['text']
            elif data['type'] == 'reset_request':
                pending_action = 'reset'
    finally: active_clients.remove(ws)

async def main():
    global main_loop
    main_loop = asyncio.get_running_loop()
    monitor = CardMonitor()
    observer = NFCBridgeObserver()
    monitor.addObserver(observer)
    async with websockets.serve(handler, "localhost", 3000):
        await asyncio.Future()

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        logging.info("Servidor detenido por el usuario.")
