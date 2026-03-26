import os
import socket
import time
import xml.etree.ElementTree as ET
import pymysql
import subprocess
from datetime import datetime
from ftplib import FTP
import requests
import logging
import re
from dotenv import load_dotenv

# Carrega as variáveis do arquivo .env
# Preferir um `.env` localizado ao lado deste script, senão usar o caminho antigo.
script_dir = os.path.dirname(os.path.abspath(__file__))
local_env = os.path.join(script_dir, ".env")
if os.path.exists(local_env):
    load_dotenv(local_env)
else:
    # fallback para caminho histórico
    load_dotenv(r"C:\xampp\htdocs\ScriptsFlow\.env")


PARENT_FOLDER = r"C:\Backburner_Job"
EXCLUDE_KEYWORD = "ANIMA"

# Filtros opcionais (por ambiente) para evitar varredura completa
FILTER_IMAGES = [s.strip() for s in os.getenv("FILTER_IMAGES", "").split(",") if s.strip()]
try:
    MAX_FOLDERS = int(os.getenv("MAX_FOLDERS", "0"))
except ValueError:
    MAX_FOLDERS = 0

# Arquivo de log
LOG_FILE = os.path.join(PARENT_FOLDER, "processamento2.log")
logging.basicConfig(
    filename=LOG_FILE,
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s",
    encoding="utf-8"
)

def log_and_print(msg, level="info"):
    """Função para logar e imprimir no console"""
    # Tentar imprimir no console; em ambientes agendados (Windows Task Scheduler)
    # a codificação do stdout pode não suportar emojis/unicode — capturamos e
    # reescrevemos usando replacement para evitar crash.
    try:
        print(msg)
    except Exception:
        try:
            import sys
            enc = sys.stdout.encoding or 'utf-8'
            safe = msg.encode(enc, errors='replace').decode(enc, errors='replace')
            print(safe)
        except Exception:
            # Fallback agressivo: remover caracteres não-ASCII
            try:
                safe = msg.encode('ascii', errors='ignore').decode('ascii')
                print(safe)
            except Exception:
                pass

    # Registrar no arquivo de log (arquivo usa encoding utf-8)
    if level == "info":
        logging.info(msg)
    elif level == "error":
        logging.error(msg)
    elif level == "warning":
        logging.warning(msg)


def send_webhook_message(message):
    slack_webhook_url = os.getenv("SLACK_WEBHOOK_URL")
    payload = {"text": message}
    try:
        response = requests.post(slack_webhook_url, json=payload)
        if response.status_code == 200:
            log_and_print("✅ Mensagem enviada para o canal de renders!")
        else:
            log_and_print(f"❌ Erro ao enviar para o canal de renders: {response.text}")
    except Exception as e:
        log_and_print(f"❌ Exceção ao enviar webhook: {e}")


def get_user_id_by_name(user_name):
    flow_token = os.getenv("FLOW_TOKEN")
    url = "https://slack.com/api/users.list"
    headers = {"Authorization": f"Bearer {flow_token}"}
    try:
        response = requests.get(url, headers=headers)
        data = response.json()
        if not data.get("ok"):
            log_and_print(f"❌ Erro na API users.list: {data.get('error')}")
            return None
        for member in data.get("members", []):
            if "real_name" in member and member["real_name"].lower() == user_name.lower():
                return member["id"]
        log_and_print(f"❌ Usuário {user_name} não encontrado no Slack.")
        return None
    except Exception as e:
        log_and_print(f"❌ Exceção ao buscar usuário {user_name}: {e}")
        return None


def send_dm_to_user(user_id, message):
    flow_token = os.getenv("FLOW_TOKEN")
    url = "https://slack.com/api/chat.postMessage"
    headers = {
        "Authorization": f"Bearer {flow_token}",
        "Content-Type": "application/json"
    }
    payload = {
        "channel": user_id,
        "text": message
    }
    try:
        response = requests.post(url, json=payload, headers=headers)
        data = response.json()
        if response.status_code == 200 and data.get("ok"):
            log_and_print(f"✅ DM enviada para {user_id} com sucesso!")
        else:
            log_and_print(f"❌ Erro ao enviar DM para {user_id}: {data.get('error', response.text)}")
    except Exception as e:
        log_and_print(f"❌ Exceção ao enviar DM para {user_id}: {e}")


NOTIFICACOES_COL = None


def resolve_notificacoes_col(cursor):
    global NOTIFICACOES_COL
    if NOTIFICACOES_COL is not None:
        return NOTIFICACOES_COL

    try:
        cursor.execute(
            """
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = %s
              AND TABLE_NAME = 'notificacoes'
              AND COLUMN_NAME IN ('colaborador_id', 'usuario_id', 'user_id')
            LIMIT 1
            """,
            (os.getenv("DB_NAME"),)
        )
        row = cursor.fetchone()
        NOTIFICACOES_COL = row[0] if row else None
    except Exception as e:
        log_and_print(f"⚠ Não foi possível detectar coluna de colaborador em notificacoes: {e}", "warning")
        NOTIFICACOES_COL = None

    if NOTIFICACOES_COL is None:
        log_and_print("⚠ Tabela notificacoes não possui coluna colaborador_id/usuario_id/user_id", "warning")
    return NOTIFICACOES_COL


def insert_notification(cursor, colaborador_id, msg):
    col = resolve_notificacoes_col(cursor)
    if not col:
        return
    cursor.execute(
        f"INSERT INTO notificacoes ({col}, mensagem) VALUES (%s, %s)",
        (colaborador_id, msg)
    )


# Conexão com banco - AGORA LENDO TUDO DO .ENV
# Abrir a conexão de forma segura e logar erro caso falhe.
conn = None
try:
    conn = pymysql.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASS"),
        database=os.getenv("DB_NAME"),
        charset='utf8mb4'
    )
except Exception as e:
    log_and_print(f"❌ Falha ao conectar no banco: {e}", "error")
    conn = None


log_and_print(f"Usuário: {os.getlogin()}")
log_and_print(f"Diretório atual: {os.getcwd()}")
log_and_print(f".env carregado: {os.getenv('DB_HOST')}")


def get_prefix(name: str) -> str:
    if not name:
        return ""
    
    # Caso padrão: número + ponto + partes com underscore
    m1 = re.match(r'^(\d+\.\s*[A-Za-z0-9]+_[A-Za-z0-9]+)', name)
    if m1:
        return m1.group(1).replace(" ", "")
    
    # Caso nenhum padrão conhecido
    return name


def is_truthy(value: str) -> bool:
    if value is None:
        return False
    return str(value).strip().lower() in {"yes", "true", "1", "sim", "y"}


def upload_to_ftp(local_path, remote_path, ftp_host, ftp_user, ftp_pass):
    try:
        ftp = FTP(ftp_host, timeout=30)
        ftp.login(ftp_user, ftp_pass)
        # usar modo passivo (compatível com NAT/Firewalls)
        ftp.set_pasv(True)
        # opcional: debug level controlável por variável de ambiente
        if os.getenv("FTP_DEBUG") == "1":
            ftp.set_debuglevel(2)

        log_and_print(f"🌐 Conectado ao FTP: {ftp_host}")

        # Normalizar separadores e extrair diretório remoto + nome do arquivo
        remote_path = remote_path.replace('\\', '/')
        remote_dir = os.path.dirname(remote_path)
        remote_name = os.path.basename(remote_path)

        # Tentar mudar para diretório remoto; se não existir, criar recursivamente
        if remote_dir:
            # alguns servidores preferem que mudemos por partes (cwd(part))
            parts = [p for p in remote_dir.split('/') if p]
            try:
                log_and_print(f"🔍 FTP pwd antes da criação: {ftp.pwd()}")
                # listar conteúdo atual (diagnóstico).
                try:
                    listing = ftp.nlst()
                    log_and_print(f"🔍 Listagem inicial remota: {listing[:10]}")
                except Exception:
                    log_and_print("🔍 Falha ao listar diretório remoto (não crítico)")
            except Exception:
                # ftp.pwd() pode falhar em alguns servidores; não bloqueia
                pass

            for part in parts:
                try:
                    ftp.cwd(part)
                except Exception:
                    try:
                        ftp.mkd(part)
                        log_and_print(f"📁 Diretório criado no FTP: {part}")
                        ftp.cwd(part)
                    except Exception as e:
                        log_and_print(f"❌ Falha ao garantir diretório remoto '{part}': {e}", "error")
                        ftp.quit()
                        return False

        # Enviar arquivo usando somente o nome (já estamos no diretório certo)
        with open(local_path, "rb") as file:
            ftp.storbinary(f"STOR {remote_name}", file)

        ftp.quit()
        log_and_print(f"✅ Upload concluído: {remote_path}")
        return True
    except Exception as e:
        log_and_print(f"❌ Erro no upload FTP: {e}", "error")
        try:
            ftp.quit()
        except Exception:
            pass
        return False


def delete_backburner_job(job_folder_path: str) -> bool:
    """Remove um job do Backburner via protocolo TCP (porta 3234).
    Extrai o handle hex do nome da pasta, fecha o Backburner Monitor
    temporariamente para adquirir o papel de Queue Controller e emite
    'del job <handle_decimal>'. Reinicia o Monitor após a operação.
    """
    MONITOR_EXE = r"C:\Program Files (x86)\Autodesk\Backburner\monitor.exe"

    folder_name = os.path.basename(job_folder_path)
    m = re.match(r'^([0-9A-Fa-f]{8})\b', folder_name)
    if not m:
        log_and_print(f"⚠ Não foi possível extrair ID do job da pasta: {folder_name}", "warning")
        return False

    # O protocolo Backburner aceita o handle como decimal
    job_handle = int(m.group(1), 16)
    manager = os.getenv("BACKBURNER_MANAGER", "127.0.0.1")
    port = int(os.getenv("BACKBURNER_PORT", "3234"))

    def _send_recv(sock, cmd, wait=0.5):
        sock.sendall((cmd + "\r\n").encode())
        time.sleep(wait)
        buf = b""
        sock.settimeout(1.0)
        try:
            while True:
                chunk = sock.recv(4096)
                if not chunk:
                    break
                buf += chunk
        except socket.timeout:
            pass
        return buf.decode(errors="replace").strip()

    # Verifica se o Backburner Monitor está em execução (ele segura o slot de controller)
    monitor_was_running = False
    try:
        tl = subprocess.run(
            ["tasklist", "/FI", "IMAGENAME eq monitor.exe", "/NH"],
            capture_output=True, text=True, timeout=5
        )
        monitor_was_running = "monitor.exe" in tl.stdout
    except Exception:
        pass

    if monitor_was_running:
        log_and_print("🔄 Encerrando Backburner Monitor para adquirir controle da fila...")
        subprocess.run(["taskkill", "/F", "/IM", "monitor.exe"], capture_output=True, timeout=5)
        time.sleep(0.8)

    success = False
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.settimeout(10)
        s.connect((manager, port))
        s.recv(1024)  # consume banner

        # Solicita o papel de Queue Controller
        ctrl_resp = _send_recv(s, "new controller")
        log_and_print(f"🔧 Backburner new controller ({manager}): {ctrl_resp[:100]}")

        if "201" in ctrl_resp:
            # Controller concedido — emite del job
            del_resp = _send_recv(s, f"del job {job_handle}")
            log_and_print(f"🔧 Backburner del job {job_handle}: {del_resp[:100]}")
            success = "200" in del_resp
            if not success:
                log_and_print(f"⚠ del job não retornou 200: {del_resp[:100]}", "warning")
        else:
            log_and_print(
                f"⚠ Não foi possível adquirir controle do Backburner (resposta: {ctrl_resp[:100]}). "
                "Verifique se outro processo está segurando o controle da fila.",
                "warning"
            )
        s.close()
    except Exception as e:
        log_and_print(f"⚠ Erro ao conectar no Backburner ({manager}:{port}): {e}", "warning")

    # Reinicia o Monitor se estava rodando
    if monitor_was_running:
        try:
            subprocess.Popen(
                [MONITOR_EXE],
                creationflags=subprocess.DETACHED_PROCESS | subprocess.CREATE_NEW_PROCESS_GROUP
            )
            log_and_print("✅ Backburner Monitor reiniciado")
        except Exception as e:
            log_and_print(f"⚠ Não foi possível reiniciar o Backburner Monitor: {e}", "warning")

    return success


def parse_xml(xml_path):
    log_and_print(f"Lendo XML: {xml_path}")
    tree = ET.parse(xml_path)
    root = tree.getroot()
    job_info = root.find("JobInfo")
    job_flags = root.find("JobFlags")
    output = root.find(".//Output/Name")
    if output is not None:
        log_and_print("Caminho EXR encontrado: " + output.text)
    else:
        log_and_print("⚠ EXR não encontrado no XML", "warning")
    data = {
        "Active": job_flags.find("Active").text if job_flags is not None else None,
        "Complete": job_flags.find("Complete").text if job_flags is not None else None,
        "Computer": job_info.find("Computer").text if job_info is not None else None,
        "Name": job_info.find("Name").text if job_info is not None else None,
        "Submitted": job_info.find("Submitted").text if job_info is not None else None,
        "Description": job_info.find("Description").text if job_info is not None else None,
        "LastUpdated": job_info.find("LastUpdated").text if job_info is not None else None,
        "ExrPath": output.text if output is not None else None
    }
    log_and_print(f"Dados XML: {data}")
    return data


def normalize_datetime_for_mysql(s: str):
    """Normaliza várias formatações comuns vindas do XML para 'YYYY-MM-DD HH:MM:SS[.ffffff]'.
    Retorna None se não for possível normalizar.
    """
    if not s:
        return None
    s = str(s).strip()
    # substituir barra por hífen para normalizar data
    s = s.replace('/', '-')

    # Captura data inicial (YYYY-MM-DD) e resto
    m = re.match(r'^(\d{4})-(\d{1,2})-(\d{1,2})[T\s\-]*(.*)$', s)
    if not m:
        return None
    year, mon, day, rest = m.group(1), m.group(2), m.group(3), m.group(4)

    # Remover timezone offset no fim, ex: -03, +0100, -03:00
    tz_match = re.search(r'([+-]\d{1,2}(?::?\d{2})?)\s*$', rest)
    if tz_match:
        rest = rest[:tz_match.start()].rstrip(' -:')

    # Separe componentes de tempo; alguns formatos usam ':' antes da fração
    parts = re.split(r'[:\.]', rest) if rest else []
    if len(parts) >= 3:
        hour = parts[0].zfill(2)
        minute = parts[1].zfill(2)
        second = parts[2].zfill(2)
        frac = ''.join(parts[3:]) if len(parts) > 3 else ''
        if frac:
            # manter apenas dígitos e ajustar para micros (6 dígitos)
            frac = re.sub(r'\D', '', frac)
            frac = (frac + '000000')[:6]
            time_part = f"{hour}:{minute}:{second}.{frac}"
        else:
            time_part = f"{hour}:{minute}:{second}"
    else:
        # se não conseguimos extrair hora, retornar apenas data (MySQL aceita 'YYYY-MM-DD')
        time_part = ''

    date_part = f"{int(year):04d}-{int(mon):02d}-{int(day):02d}"
    if time_part:
        return f"{date_part} {time_part}"
    return date_part

def check_log(log_path):
    log_and_print(f"Lendo log: {log_path}")
    has_error = False
    errors = []
    with open(log_path, "r", encoding="utf-8", errors="ignore") as f:
        for line in f:
            if "ERR" in line:
                has_error = True
                errors.append(line.strip())
    log_and_print(f"Erros encontrados: {len(errors)}")
    return has_error, "\n".join(errors)

def find_imagem_id(cursor, name):
    log_and_print(f"Buscando imagem no banco: {name}")

    # 1. Busca exata
    cursor.execute(
        "SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE imagem_nome=%s",
        (name,)
    )
    result = cursor.fetchone()
    if result:
        log_and_print(f"Imagem encontrada pelo nome exato: {result[0]}")
        return result[0]

    # 2. Busca pelo prefixo normalizado
    prefix = get_prefix(name)
    if prefix:  # só tenta se tiver prefixo válido
        cursor.execute(
        "SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE REPLACE(imagem_nome,' ','') LIKE %s",
        (prefix + '%',)
        )
        result = cursor.fetchone()
        if result:
            log_and_print(f"Imagem encontrada pelo prefixo: {result[0]}")
            return result[0]

    log_and_print("Imagem não encontrada", "warning")
    return None


def find_responsavel_id(cursor, imagem_id):
    log_and_print(f"Buscando imagem no banco: {imagem_id}")
    cursor.execute(
        "SELECT colaborador_id, funcao_id FROM funcao_imagem WHERE funcao_id in (4, 6) AND imagem_id = %s ORDER BY funcao_id DESC LIMIT 1",
        (imagem_id,)
    )
    result = cursor.fetchone()
    if result:
        log_and_print(f"Colaborador encontrado: {result[0]} (função {result[1]})")
        return result  # retorna (colaborador_id, funcao_id)
    log_and_print("Imagem não encontrada", "warning")
    return None, None

def find_status_id(cursor, imagem_id):
    log_and_print(f"Buscando status atual no banco: {imagem_id}")
    cursor.execute(
        "SELECT status_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = %s",
        (imagem_id,)
    )
    result = cursor.fetchone()
    if result:
        log_and_print(f"Status atual: {result[0]}")
        return result[0]
    log_and_print("Imagem não encontrada", "warning")
    return None


def process_job_folder(cursor, job_folder, p00_rollup=None):
    folder_name = os.path.basename(job_folder)
    if FILTER_IMAGES:
        if not any(f.lower() in folder_name.lower() for f in FILTER_IMAGES):
            return
    if EXCLUDE_KEYWORD in folder_name.upper():
        log_and_print(f"Ignorado (ANIMA): {job_folder}")
        return

    log_and_print(f"\nProcessando pasta: {job_folder}")

    xml_file = None
    log_file = None
    for f in os.listdir(job_folder):
        if f.lower().endswith(".xml"):
            xml_file = os.path.join(job_folder, f)
        if f.lower().endswith(".txt") or f.lower().endswith(".log"):
            log_file = os.path.join(job_folder, f)

    if not xml_file:
        log_and_print(f"⚠ Nenhum XML encontrado em {job_folder}")
        return
    if not log_file:
        log_and_print(f"⚠ Nenhum log encontrado em {job_folder}")
        return

    xml_data = parse_xml(xml_file)
    has_error, errors = check_log(log_file)

    image_name_xml = xml_data.get("Name")
    if not image_name_xml:
        log_and_print("⚠ Campo <Name> não encontrado no XML", "warning")
        return

    if FILTER_IMAGES:
        if not any(f.lower() in image_name_xml.lower() for f in FILTER_IMAGES):
            log_and_print(f"⏭ Ignorado por filtro: {image_name_xml}")
            return

    imagem_id = find_imagem_id(cursor, image_name_xml)
    if not imagem_id:
        log_and_print(f"Imagem não encontrada para {image_name_xml}")
        return

    exr_path = xml_data.get("ExrPath")
    if exr_path:
        # Normaliza e trata drives diferentes:
        # - M: -> \\192.168.0.250\renders2
        # - Y: -> \\192.168.0.250\renders (comportamento anterior)
        # Caso não seja um drive conhecido, usa o caminho original.
        if re.match(r'^[Mm]:', exr_path):
            caminho_pasta = os.path.dirname(exr_path.replace(exr_path[:2], r"\\192.168.0.250\renders2"))
        elif re.match(r'^[Yy]:', exr_path):
            caminho_pasta = os.path.dirname(exr_path.replace(exr_path[:2], r"\\192.168.0.250\renders"))
        elif re.match(r'^[Nn]:', exr_path):
            caminho_pasta = os.path.dirname(exr_path.replace(exr_path[:2], r"\\192.168.0.250\exchange"))
        else:
            caminho_pasta = os.path.dirname(exr_path)
    else:
        caminho_pasta = None

    # Buscar informações complementares
    cursor.execute("SELECT imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra = %s", (imagem_id,))
    row = cursor.fetchone()
    image_name_db = row[0] if row else None

    resp_id, funcao_id = find_responsavel_id(cursor, imagem_id)
    status_id = find_status_id(cursor, imagem_id)

    # ─────────────────────────────────────────────────────────────────────
    # Proteção contra reprocessamento de jobs antigos
    # Cenário: render P00 foi aprovado → imagem avançou para R00.
    # A pasta do Backburner ainda existe. Quando o script roda de novo,
    # find_status_id devolve o status ATUAL da imagem (R00) mas os dados
    # do XML ainda são do job P00, causando um registro cruzado errado.
    #
    # Solução: se este job_folder (caminho_pasta) já foi gravado em
    # render_alta com um status_id DIFERENTE do status atual da imagem,
    # significa que o job pertence a um ciclo de render já superado → pular.
    # ─────────────────────────────────────────────────────────────────────
    if caminho_pasta:
        cursor.execute(
            "SELECT status_id FROM render_alta WHERE job_folder = %s LIMIT 1",
            (caminho_pasta,)
        )
        prev_record = cursor.fetchone()
        if prev_record is not None and prev_record[0] != status_id:
            log_and_print(
                f"⏭ Job folder já foi processado para status_id={prev_record[0]} "
                f"mas a imagem está agora em status_id={status_id}. "
                f"Pulando para evitar reprocessamento de job antigo: {caminho_pasta}",
                "warning"
            )
            return

    # Buscar status existente
    cursor.execute("""
        SELECT idrender_alta, status, previa_jpg
        FROM render_alta
        WHERE imagem_id = %s AND status_id = %s
        ORDER BY idrender_alta DESC
        LIMIT 1
    """, (imagem_id, status_id))
    existing_status = cursor.fetchone()

    render_id = existing_status[0] if existing_status else None
    ultimo_status = existing_status[1] if existing_status else None
    existing_preview = existing_status[2] if existing_status else None

    # Determinar status customizado
    active = is_truthy(xml_data.get("Active"))
    complete = is_truthy(xml_data.get("Complete"))

    if complete:
        status_custom = "Em aprovação"
    elif has_error:
        status_custom = "Erro"
    elif active and not complete:
        status_custom = "Em andamento"
    else:
        status_custom = (xml_data.get("Complete") or "Desconhecido")

    # Acumular status do P00 por imagem (para notificação única)
    if status_id == 1 and p00_rollup is not None:
        roll = p00_rollup.get(imagem_id)
        if not roll:
            roll = {
                "image_name_db": image_name_db,
                "resp_id": resp_id,
                "funcao_id": funcao_id,
                "total_jobs": 0,
                "completed_jobs": 0,
                "any_error": False,
                "any_incomplete": False,
                "all_complete": True
            }
            p00_rollup[imagem_id] = roll

        roll["total_jobs"] += 1
        if resp_id and not roll.get("resp_id"):
            roll["resp_id"] = resp_id

        complete_val = is_truthy(xml_data.get("Complete"))
        if has_error:
            roll["any_error"] = True
        if complete_val:
            roll["completed_jobs"] += 1
        else:
            roll["all_complete"] = False
            roll["any_incomplete"] = True

    # Caminho remoto fixo para todas as imagens
    remote_base_path = "/web/improov.com.br/public_html/flow/ImproovWeb/uploads/renders/"

    # 1️⃣ Se status atual = Em aprovação → atualizar previa_jpg (substitui no VPS mesmo se mesmo nome)
    if ultimo_status == "Em aprovação":
        if caminho_pasta and os.path.exists(caminho_pasta):
            jpgs = [f for f in os.listdir(caminho_pasta) if f.lower().endswith(".jpg")]
            if jpgs:
                preview_name = jpgs[0]

                # Upload da prévia — substitui no VPS mesmo que o nome seja igual
                local_path = os.path.join(caminho_pasta, preview_name)
                remote_path = remote_base_path + preview_name
                ftp_host = os.getenv("FTP_HOST")
                ftp_user = os.getenv("FTP_USER")
                ftp_pass = os.getenv("FTP_PASS")
                upload_ok = upload_to_ftp(local_path, remote_path, ftp_host, ftp_user, ftp_pass)

                if upload_ok:
                    # Atualiza banco apenas se o upload teve sucesso
                    cursor.execute("""
                        UPDATE render_alta
                        SET previa_jpg = %s
                        WHERE idrender_alta = %s
                    """, (preview_name, render_id))
                    log_and_print(f"🖼️ Previa JPG atualizada para {preview_name} (status já era 'Em aprovação')")
                else:
                    log_and_print(f"⚠ Upload falhou — nenhuma alteração foi feita no banco para {preview_name}", "warning")
        return  # não faz mais nada

    # 2️⃣ Se status atual = Aprovado ou Finalizado → remover do Backburner
    # Se status = Reprovado/Refazendo: o job foi reprovado/refazendo e um NOVO job foi submetido.
    # Só removemos o job do Backburner quando o novo render COMPLETAR (complete=True).
    # Enquanto o novo job está rodando (active=True, complete=False), deixamos ele finalizar.
    if ultimo_status in ("Aprovado", "Finalizado"):
        log_and_print(f"⏭ Status '{ultimo_status}' detectado — removendo job do Backburner: {job_folder}")
        delete_backburner_job(job_folder)
        return

    if ultimo_status in ("Reprovado", "Refazendo"):
        if complete:
            # Novo render completou após reprovação → tratar como "Em aprovação" (novo ciclo)
            log_and_print(f"🔄 Job completou após reprovação — promovendo para 'Em aprovação': {job_folder}")
            ultimo_status = None   # força prosseguir pelo fluxo normal
            status_custom = "Em aprovação"
        elif active and not complete:
            # Ainda renderizando — deixar finalizar normalmente
            log_and_print(f"⏩ Render reprovado sendo refeito (Em andamento) — aguardando conclusão: {job_folder}")
            ultimo_status = None   # força prosseguir pelo fluxo normal (atualiza status no banco)
            status_custom = "Em andamento"
        else:
            # Inativo e incompleto — estado indefinido, processar normalmente
            log_and_print(f"⏩ Render reprovado com estado indefinido (active=False, complete=False) — processando normalmente: {job_folder}")
            ultimo_status = None

    # 3️⃣ Se status estava como Erro e Complete=Yes → mudar para Em aprovação
    if ultimo_status == "Erro" and complete:
        status_custom = "Em aprovação"
        log_and_print("🔄 Status alterado de 'Erro' para 'Em aprovação' pois Complete=Yes")


    log_and_print(f"Procurando JPGs em: {caminho_pasta}")

    # 4️⃣ Fluxo normal para Em andamento ou novo registro
    previa_val = None
    if caminho_pasta and os.path.exists(caminho_pasta):
        # Collect all JPGs (angles). We'll upload each and store in render_previews.
        jpgs = [f for f in os.listdir(caminho_pasta) if f.lower().endswith(".jpg")]
        if jpgs:
            # Sort to have deterministic order (e.g., LD1_RES_001, LD1_RES_002 ...)
            jpgs.sort()
            previa_val = jpgs[0]  # legacy: store the first one in render_alta.prevista_jpg

            ftp_host = os.getenv("FTP_HOST")
            ftp_user = os.getenv("FTP_USER")
            ftp_pass = os.getenv("FTP_PASS")

            uploaded_previews = []
            for jpg in jpgs:
                local_path = os.path.join(caminho_pasta, jpg)
                remote_path = remote_base_path + jpg
                upload_ok = upload_to_ftp(local_path, remote_path, ftp_host, ftp_user, ftp_pass)
                if upload_ok:
                    uploaded_previews.append(jpg)

            # After uploading all previews, we'll insert them into render_previews once render_id is known.


    # Normalizar datas vindas do XML para um formato aceito pelo MySQL
    submitted_raw = xml_data.get("Submitted")
    last_updated_raw = xml_data.get("LastUpdated")
    submitted_dt = normalize_datetime_for_mysql(submitted_raw)
    last_updated_dt = normalize_datetime_for_mysql(last_updated_raw)
    if submitted_raw and not submitted_dt:
        log_and_print(f"⚠ Não foi possível normalizar Submitted='{submitted_raw}' — será gravado NULL", "warning")
    if last_updated_raw and not last_updated_dt:
        log_and_print(f"⚠ Não foi possível normalizar LastUpdated='{last_updated_raw}' — será gravado NULL", "warning")

    status_to_write = status_custom
    if status_id == 1 and ultimo_status is not None:
        status_to_write = ultimo_status

    cursor.execute("""
        INSERT INTO render_alta
        (imagem_id, responsavel_id, status_id, status, data, computer, submitted, last_updated, has_error, errors, job_folder, previa_jpg, numero_bg)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            status=VALUES(status),
            computer=VALUES(computer),
            submitted=VALUES(submitted),
            last_updated=VALUES(last_updated),
            has_error=VALUES(has_error),
            errors=VALUES(errors),
            job_folder=VALUES(job_folder),
            previa_jpg=IFNULL(VALUES(previa_jpg), previa_jpg)
    """, (
        imagem_id,
        resp_id,
        status_id,
        status_to_write,
        datetime.now(),
        xml_data.get("Computer"),
        submitted_dt,
        last_updated_dt,
        has_error,
        errors,
        caminho_pasta,
        previa_val,
        xml_data.get("Description")
    ))

    log_and_print(f"✅ Render atualizado/inserido — status={status_custom}, previa_jpg={previa_val}")


    
    # 🔹 Buscar render_id com imagem_id e status_id
    cursor.execute(
        "SELECT idrender_alta FROM render_alta WHERE imagem_id = %s AND status_id = %s",
        (imagem_id, status_id)
    )
    render_row = cursor.fetchone()
    render_id = render_row[0] if render_row else None

    # 🔹 Buscar obra_id pela imagem_id (não precisa mudar nada aqui)
    cursor.execute(
        "SELECT obra_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = %s",
        (imagem_id,)
    )
    obra_row = cursor.fetchone()
    obra_id = obra_row[0] if obra_row else None

    # 🔹 Buscar responsável da pós-produção (funcao_id = 5)
    cursor.execute(
        "SELECT colaborador_id FROM funcao_imagem WHERE funcao_id = 5 AND imagem_id = %s",
        (imagem_id,)
    )
    pos_row = cursor.fetchone()
    responsavel_pos_id = pos_row[0] if pos_row else None

    # 🔹 Inserir ou atualizar na tabela pós-produção
    # Não criar registro de pós-produção quando status_id == 1
    if responsavel_pos_id and resp_id and status_id != 1:
        cursor.execute("""
            INSERT INTO pos_producao
            (render_id, imagem_id, obra_id, colaborador_id, caminho_pasta, numero_bg, status_id, responsavel_id)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                obra_id = VALUES(obra_id),
                colaborador_id = VALUES(colaborador_id),
                caminho_pasta = VALUES(caminho_pasta),
                numero_bg = VALUES(numero_bg),
                status_id = VALUES(status_id),
                responsavel_id = VALUES(responsavel_id)
        """, (
            render_id,
            imagem_id,
            obra_id,
            resp_id,
            caminho_pasta,
            xml_data.get("Description"),
            status_id,
            responsavel_pos_id
        ))
        log_and_print(f"📌 Pós-produção vinculada: render_id={render_id}, imagem_id={imagem_id}, obra_id={obra_id}")
    else:
        if responsavel_pos_id and status_id == 1:
            log_and_print(f"⚠ Pos-produção não criada pois status_id == 1 para imagem_id {imagem_id}")
        elif responsavel_pos_id and not resp_id:
            log_and_print(f"⚠ Pos-produção não criada pois responsável de render (resp_id) é nulo para imagem_id {imagem_id}")
        else:
            log_and_print(f"⚠ Imagem {imagem_id} não possui pós-produção, pulando inserção na pos_producao")
    

    # -------------------------------
    # Lógica de notificação
    # -------------------------------
    # Notificar canal quando não há colaborador atribuído (sempre que processar)
    if not resp_id:
        msg_sem_colab = f"⚠ Render da imagem *{image_name_db}* processado (status: *{status_custom}*), mas *não há colaborador atribuído* (sem responsável de render)."
        send_webhook_message(msg_sem_colab)
        log_and_print(f"🔔 Notificação de sem colaborador enviada para o canal de renders ({image_name_db}).")

    if status_id != 1 and resp_id and status_custom != ultimo_status:
        if status_custom == "Erro":
            msg = f"O render da imagem: {image_name_db} deu erro, favor verificar!"
        elif status_custom == "Em aprovação":
            msg = f"O render da imagem: {image_name_db} foi concluído com sucesso, favor aprovar!"
        elif status_custom == "Em andamento":
            msg = f"O render da imagem: {image_name_db} está em andamento."
        else:
            msg = None

        if msg:
            # Enviar para canal de renders
            send_webhook_message(msg)

            # Buscar usuário e enviar DM
            cursor.execute("SELECT nome_slack FROM usuario WHERE idcolaborador = %s", (resp_id,))
            slack_names = cursor.fetchall()
            for slack_name_tuple in slack_names:
                slack_name = slack_name_tuple[0]
                user_id = get_user_id_by_name(slack_name)
                if user_id:
                    send_dm_to_user(user_id, msg)

            # Inserir notificação no banco
            insert_notification(cursor, resp_id, msg)
            log_and_print(f"🔔 Notificação enviada para colaborador {resp_id} e canal de renders.")

        # Atualizar função e imagem
    if status_custom == "Em aprovação" and funcao_id:
        # cursor.execute("""
        #     UPDATE funcao_imagem
        #     SET status = 'Finalizado', prazo = NOW()
        #     WHERE imagem_id = %s AND funcao_id = %s
        # """, (imagem_id, funcao_id))
        # log_and_print(f"Função atualizada para Finalizado para imagem_id {imagem_id}")

        cursor.execute("""
            UPDATE imagens_cliente_obra
            SET substatus_id = 5
            WHERE idimagens_cliente_obra = %s
        """, (imagem_id,))
        log_and_print(f"Imagem atualizada para status = REN na imagem_id {imagem_id}")

    # -------------------------------
    # Salvar previews múltiplos (angles) na tabela render_previews
    # -------------------------------
    try:
        # Apenas registre previews múltiplos se o status da imagem for P00 (status_id == 1)
        if 'uploaded_previews' in locals() and uploaded_previews and render_id and status_id == 1:
            # Use INSERT ... ON DUPLICATE KEY UPDATE noop para evitar duplicatas.
            for filename in uploaded_previews:
                try:
                    cursor.execute(
                        "INSERT INTO render_previews (render_id, filename) VALUES (%s, %s) ON DUPLICATE KEY UPDATE filename=filename",
                        (render_id, filename)
                    )
                    log_and_print(f"➕ Preview registrado: {filename} -> render_id {render_id}")
                except Exception as e:
                    log_and_print(f"⚠ Falha ao inserir preview {filename}: {e}", "warning")
        else:
            if 'uploaded_previews' in locals() and uploaded_previews:
                log_and_print(f"ℹ Previews encontrados, mas não registrados (status_id={status_id})")
    except Exception as e:
        log_and_print(f"⚠ Erro ao processar previews: {e}", "warning")

def main():
    log_and_print(f"Iniciando processamento da pasta: {PARENT_FOLDER}")
    with conn.cursor() as cursor:
        p00_rollup = {}
        processed_folders = 0
        stop_scan = False
        # Percorre todas as subpastas dentro da pasta raiz
        for root, dirs, files in os.walk(PARENT_FOLDER):
            for d in dirs:
                job_folder = os.path.join(root, d)
                try:
                    process_job_folder(cursor, job_folder, p00_rollup)
                    processed_folders += 1
                    conn.commit()
                    if MAX_FOLDERS and processed_folders >= MAX_FOLDERS:
                        log_and_print(f"⛔ Limite MAX_FOLDERS atingido ({MAX_FOLDERS}). Encerrando varredura.")
                        stop_scan = True
                        break
                except Exception as e:
                    log_and_print(f"❌ Erro ao processar a pasta {job_folder}: {e}", "error")
                    conn.rollback()
                    # continua para a próxima pasta sem parar tudo
                    continue  

            if stop_scan:
                break

        log_and_print(f"Total de pastas processadas: {processed_folders}")

        # Notificação agregada para P00 (status_id = 1)
        if p00_rollup:
            for imagem_id, roll in p00_rollup.items():
                total_jobs = roll.get("total_jobs", 0)
                completed_jobs = roll.get("completed_jobs", 0)
                any_error = roll.get("any_error", False)
                any_incomplete = roll.get("any_incomplete", False)
                all_complete = roll.get("all_complete", False)

                if any_error:
                    status_agg = "Erro"
                elif any_incomplete:
                    status_agg = "Em andamento"
                elif all_complete and total_jobs > 0:
                    status_agg = "Em aprovação"
                else:
                    status_agg = "Desconhecido"

                cursor.execute(
                    "SELECT idrender_alta, status FROM render_alta WHERE imagem_id = %s AND status_id = 1 ORDER BY idrender_alta DESC LIMIT 1",
                    (imagem_id,)
                )
                row = cursor.fetchone()
                render_id = row[0] if row else None
                ultimo_status = row[1] if row else None

                # Atualiza status agregado no render_alta (mantém estado único por imagem)
                # Não sobrescreve Aprovado/Finalizado quando P00 já foi aprovado
                if render_id and ultimo_status not in ("Aprovado", "Finalizado"):
                    cursor.execute(
                        "UPDATE render_alta SET status = %s WHERE idrender_alta = %s",
                        (status_agg, render_id)
                    )

                resp_id = roll.get("resp_id")
                image_name_db = roll.get("image_name_db")

                # Enviar notificação apenas quando houver mudança real no status agregado
                # e não houve status aprovado/finalizado anteriormente
                if resp_id and status_agg != ultimo_status and ultimo_status not in ("Aprovado", "Finalizado"):
                    if status_agg == "Erro":
                        msg = f"O render da imagem: {image_name_db} deu erro, favor verificar!"
                    elif status_agg == "Em aprovação":
                        msg = f"O render da imagem: {image_name_db} foi concluído com sucesso, favor aprovar!"
                    elif status_agg == "Em andamento":
                        msg = f"O render da imagem: {image_name_db} está em andamento."
                    else:
                        msg = None

                    if msg:
                        send_webhook_message(msg)

                        cursor.execute("SELECT nome_slack FROM usuario WHERE idcolaborador = %s", (resp_id,))
                        slack_names = cursor.fetchall()
                        for slack_name_tuple in slack_names:
                            slack_name = slack_name_tuple[0]
                            user_id = get_user_id_by_name(slack_name)
                            if user_id:
                                send_dm_to_user(user_id, msg)

                        insert_notification(cursor, resp_id, msg)
                        log_and_print(f"🔔 Notificação P00 enviada para colaborador {resp_id} e canal de renders.")
        conn.commit()
    log_and_print("Processamento concluído!")

if __name__ == "__main__":
    main()