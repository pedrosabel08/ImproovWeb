import os
import hashlib
import json
import pymysql
import subprocess
from datetime import datetime
from ftplib import FTP
import requests
import logging
import re
import locale
from logging.handlers import TimedRotatingFileHandler
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


EXCLUDE_KEYWORD = "ANIMA"
DEADLINE_COMMAND = "deadlinecommand"
DEADLINE_COMMAND_TIMEOUT = int(os.getenv("DEADLINE_COMMAND_TIMEOUT", "60"))
DEADLINE_DELETE_STATUSES = ("Aprovado", "Finalizado", "Reprovado")
DEADLINE_REWORK_STATUS = "Refazendo"
DEADLINE_REOPEN_STATUSES = ("Reprovado", DEADLINE_REWORK_STATUS)
DEADLINE_CLOSED_STATUSES = DEADLINE_DELETE_STATUSES + (DEADLINE_REWORK_STATUS,)

# Filtros opcionais (por ambiente) para evitar varredura completa
FILTER_IMAGES = [
    s.strip() for s in os.getenv("FILTER_IMAGES", "").split(",") if s.strip()
]
DEADLINE_JOB_IDS = [
    s.strip() for s in os.getenv("DEADLINE_JOB_IDS", "").split(",") if s.strip()
]
try:
    MAX_JOBS = int(os.getenv("MAX_JOBS", "0"))
except ValueError:
    MAX_JOBS = 0

# Arquivo de log
LOG_FILE = os.path.join(script_dir, "deadline_monitor.log")
LEGACY_LOGGER = logging.getLogger("deadline_monitor_legacy")
if not LEGACY_LOGGER.handlers:
    legacy_handler = TimedRotatingFileHandler(
        LOG_FILE,
        when="midnight",
        backupCount=int(os.getenv("DEADLINE_LOG_RETENTION_DAYS", "30")),
        encoding="utf-8",
    )
    legacy_handler.setFormatter(
        logging.Formatter("%(asctime)s - %(levelname)s - %(message)s")
    )
    LEGACY_LOGGER.addHandler(legacy_handler)
LEGACY_LOGGER.setLevel(logging.INFO)
LEGACY_LOGGER.propagate = False
EXTERNAL_LOGGER = None


def log_and_print(msg, level="info"):
    """Função para logar e imprimir no console"""
    if EXTERNAL_LOGGER is not None:
        log_method = getattr(EXTERNAL_LOGGER, level, EXTERNAL_LOGGER.info)
        log_method(str(msg), extra={"routine": "business"})
        return
    # Tentar imprimir no console; em ambientes agendados (Windows Task Scheduler)
    # a codificação do stdout pode não suportar emojis/unicode — capturamos e
    # reescrevemos usando replacement para evitar crash.
    try:
        print(msg)
    except Exception:
        try:
            import sys

            enc = sys.stdout.encoding or "utf-8"
            safe = msg.encode(enc, errors="replace").decode(enc, errors="replace")
            print(safe)
        except Exception:
            # Fallback agressivo: remover caracteres não-ASCII
            try:
                safe = msg.encode("ascii", errors="ignore").decode("ascii")
                print(safe)
            except Exception:
                pass

    # Registrar no arquivo de log (arquivo usa encoding utf-8)
    if level == "info":
        LEGACY_LOGGER.info(msg)
    elif level == "error":
        LEGACY_LOGGER.error(msg)
    elif level == "warning":
        LEGACY_LOGGER.warning(msg)
    elif level == "debug":
        LEGACY_LOGGER.debug(msg)


def send_webhook_message(message):
    slack_webhook_url = os.getenv("SLACK_WEBHOOK_URL")
    if not slack_webhook_url:
        log_and_print("Webhook Slack nao configurado.", "error")
        return False
    payload = {"text": message}
    try:
        response = requests.post(slack_webhook_url, json=payload, timeout=15)
        if response.status_code == 200:
            log_and_print("Mensagem enviada para o canal de renders!")
            return True
        else:
            log_and_print(
                f"Erro ao enviar para o canal de renders: {response.text}", "error"
            )
    except Exception as e:
        log_and_print(f"Excecao ao enviar webhook: {e}", "error")
    return False


def get_user_id_by_name(user_name):
    flow_token = os.getenv("FLOW_TOKEN")
    if not flow_token:
        log_and_print("FLOW_TOKEN nao configurado para notificacao Slack.", "error")
        return None
    url = "https://slack.com/api/users.list"
    headers = {"Authorization": f"Bearer {flow_token}"}
    try:
        response = requests.get(url, headers=headers, timeout=15)
        data = response.json()
        if not data.get("ok"):
            log_and_print(
                f"Erro na API users.list: {data.get('error')}", "error"
            )
            return None
        for member in data.get("members", []):
            if (
                "real_name" in member
                and member["real_name"].lower() == user_name.lower()
            ):
                return member["id"]
        log_and_print(f"Usuario {user_name} nao encontrado no Slack.", "warning")
        return None
    except Exception as e:
        log_and_print(f"Excecao ao buscar usuario {user_name}: {e}", "error")
        return None


def send_dm_to_user(user_id, message):
    flow_token = os.getenv("FLOW_TOKEN")
    if not flow_token:
        log_and_print("FLOW_TOKEN nao configurado para notificacao Slack.", "error")
        return False
    url = "https://slack.com/api/chat.postMessage"
    headers = {
        "Authorization": f"Bearer {flow_token}",
        "Content-Type": "application/json",
    }
    payload = {"channel": user_id, "text": message}
    try:
        response = requests.post(url, json=payload, headers=headers, timeout=15)
        data = response.json()
        if response.status_code == 200 and data.get("ok"):
            log_and_print(f"DM enviada para {user_id} com sucesso!")
            return True
        else:
            log_and_print(
                f"Erro ao enviar DM para {user_id}: {data.get('error', response.text)}",
                "error",
            )
    except Exception as e:
        log_and_print(f"Excecao ao enviar DM para {user_id}: {e}", "error")
    return False


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
            (os.getenv("DB_NAME"),),
        )
        row = cursor.fetchone()
        NOTIFICACOES_COL = row[0] if row else None
    except Exception as e:
        log_and_print(
            f"⚠ Não foi possível detectar coluna de colaborador em notificacoes: {e}",
            "warning",
        )
        NOTIFICACOES_COL = None

    if NOTIFICACOES_COL is None:
        log_and_print(
            "⚠ Tabela notificacoes não possui coluna colaborador_id/usuario_id/user_id",
            "warning",
        )
    return NOTIFICACOES_COL


def insert_notification(cursor, colaborador_id, msg):
    col = resolve_notificacoes_col(cursor)
    if not col:
        return
    cursor.execute(
        f"INSERT INTO notificacoes ({col}, mensagem) VALUES (%s, %s)",
        (colaborador_id, msg),
    )


# Conexão com banco - AGORA LENDO TUDO DO .ENV
# Abrir a conexão de forma segura e logar erro caso falhe.
conn = None


def claim_attempt_event(cursor, attempt_id, event_type, key, data=None):
    """Reserva uma operacao externa para uma tentativa, de forma idempotente."""
    if not attempt_id:
        return True
    cursor.execute(
        """
        INSERT IGNORE INTO render_tentativa_eventos
            (tentativa_id, tipo, chave, dados_json)
        VALUES (%s, %s, %s, %s)
        """,
        (attempt_id, event_type, str(key)[:120], json.dumps(data or {}, ensure_ascii=False)),
    )
    return cursor.rowcount == 1


def release_attempt_event(cursor, attempt_id, event_type, key):
    """Permite retry somente quando a chamada externa nao foi concluida."""
    if not attempt_id:
        return
    cursor.execute(
        """
        DELETE FROM render_tentativa_eventos
        WHERE tentativa_id = %s AND tipo = %s AND chave = %s
        """,
        (attempt_id, event_type, str(key)[:120]),
    )


def preview_fingerprint(local_path):
    stat = os.stat(local_path)
    identity = "|".join(
        (
            os.path.normcase(os.path.abspath(local_path)),
            str(stat.st_size),
            str(stat.st_mtime_ns),
        )
    )
    return hashlib.sha256(identity.encode("utf-8")).hexdigest()


def upload_previews_once(cursor, attempt_id, folder, filenames, remote_base_path):
    """Envia somente arquivos cujo fingerprint ainda nao foi confirmado na tentativa."""
    if not folder or not filenames:
        return []
    ftp_host = os.getenv("FTP_HOST")
    ftp_user = os.getenv("FTP_USER")
    ftp_pass = os.getenv("FTP_PASS")
    uploaded = []
    for filename in filenames:
        local_path = os.path.join(folder, filename)
        if not os.path.isfile(local_path):
            continue
        fingerprint = preview_fingerprint(local_path)
        event_type = "PREVIEW_UPLOAD"
        if not claim_attempt_event(
            cursor,
            attempt_id,
            event_type,
            fingerprint,
            {"filename": filename, "path": local_path},
        ):
            log_and_print(f"Preview sem alteracao: {filename}", "debug")
            continue
        if upload_to_ftp(
            local_path, remote_base_path + filename, ftp_host, ftp_user, ftp_pass
        ):
            uploaded.append(filename)
        else:
            release_attempt_event(cursor, attempt_id, event_type, fingerprint)
    return uploaded


def send_transition_notifications(
    cursor, attempt_id, event_key, responsavel_id, image_name, message
):
    """Canal, DM e notificacao interna protegidos por eventos independentes."""
    channel_key = f"{event_key}:CANAL"
    if claim_attempt_event(
        cursor, attempt_id, "SLACK_CANAL", channel_key, {"message": message}
    ):
        if send_webhook_message(message):
            log_and_print(f"Slack enviado para o canal ({image_name}).")
        else:
            release_attempt_event(cursor, attempt_id, "SLACK_CANAL", channel_key)

    if not responsavel_id:
        return
    cursor.execute(
        "SELECT nome_slack FROM usuario WHERE idcolaborador = %s", (responsavel_id,)
    )
    for slack_name_tuple in cursor.fetchall():
        slack_name = slack_name_tuple[0]
        user_id = get_user_id_by_name(slack_name)
        if not user_id:
            continue
        dm_key = f"{event_key}:DM:{user_id}"
        if not claim_attempt_event(
            cursor,
            attempt_id,
            "SLACK_DM",
            dm_key,
            {"message": message, "user_id": user_id},
        ):
            continue
        if send_dm_to_user(user_id, message):
            log_and_print(f"Slack DM enviada para colaborador {responsavel_id}.")
        else:
            release_attempt_event(cursor, attempt_id, "SLACK_DM", dm_key)

    flow_key = f"{event_key}:FLOW"
    if claim_attempt_event(
        cursor, attempt_id, "NOTIFICACAO_FLOW", flow_key, {"message": message}
    ):
        insert_notification(cursor, responsavel_id, message)


def create_legacy_connection():
    """Conexao usada somente pelo modo periodico de compatibilidade."""
    return pymysql.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASS"),
        database=os.getenv("DB_NAME"),
        charset="utf8mb4",
        autocommit=False,
    )


def get_prefix(name: str) -> str:
    if not name:
        return ""

    # Caso padrão: número + ponto + partes com underscore
    m1 = re.match(r"^(\d+\.\s*[A-Za-z0-9]+_[A-Za-z0-9]+)", name)
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
        remote_path = remote_path.replace("\\", "/")
        remote_dir = os.path.dirname(remote_path)
        remote_name = os.path.basename(remote_path)

        # Tentar mudar para diretório remoto; se não existir, criar recursivamente
        if remote_dir:
            # alguns servidores preferem que mudemos por partes (cwd(part))
            parts = [p for p in remote_dir.split("/") if p]
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
                        log_and_print(
                            f"❌ Falha ao garantir diretório remoto '{part}': {e}",
                            "error",
                        )
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


def run_deadline_command(args):
    command = [DEADLINE_COMMAND] + args
    encoding = locale.getpreferredencoding(False) or "utf-8"
    try:
        result = subprocess.run(
            command,
            capture_output=True,
            text=True,
            encoding=encoding,
            errors="replace",
            timeout=DEADLINE_COMMAND_TIMEOUT,
            check=False,
        )
    except FileNotFoundError:
        log_and_print("❌ deadlinecommand não encontrado no PATH.", "error")
        return None, ""
    except subprocess.TimeoutExpired:
        log_and_print(f"❌ Timeout ao executar: {' '.join(command)}", "error")
        return None, ""
    except Exception as e:
        log_and_print(f"❌ Falha ao executar deadlinecommand: {e}", "error")
        return None, ""

    output = ((result.stdout or "") + "\n" + (result.stderr or "")).strip()
    if result.returncode != 0:
        log_and_print(
            f"❌ deadlinecommand retornou código {result.returncode}: {' '.join(command)}",
            "error",
        )
        if output:
            log_and_print(output, "error")
        return None, output

    return result, output


def parse_deadline_output(output):
    data = {}
    raw_lines = []
    for original_line in output.splitlines():
        line = original_line.strip()
        if not line:
            continue
        if "=" not in line:
            raw_lines.append(line)
            continue
        key, value = line.split("=", 1)
        key = key.strip()
        value = value.strip()
        if not key:
            raw_lines.append(line)
            continue
        if key in data:
            if not isinstance(data[key], list):
                data[key] = [data[key]]
            data[key].append(value)
        else:
            data[key] = value
    if raw_lines:
        data["_raw_lines"] = raw_lines
    return data


def first_value(data, keys, default=None):
    for key in keys:
        value = data.get(key)
        if isinstance(value, list):
            for item in value:
                if item not in (None, ""):
                    return item
        elif value not in (None, ""):
            return value
    return default


def values_list(data, key):
    value = data.get(key)
    if value is None:
        return []
    if isinstance(value, list):
        return value
    return [value]


def get_deadline_job(job_id):
    result, output = run_deadline_command(["-GetJob", job_id, "True"])
    if not result:
        return None, output
    parsed = parse_deadline_output(output)
    if not parsed:
        log_and_print(f"⚠ Job Deadline sem dados parseáveis: {job_id}", "warning")
        return None, output
    return parsed, output


def get_deadline_tasks(job_id):
    result, output = run_deadline_command(["-GetJobTasks", job_id])
    if not result:
        log_and_print(f"⚠ Não foi possível consultar tasks do job {job_id}", "warning")
        return {}, output
    return parse_deadline_output(output), output


def get_deadline_report_output(job_id, command_name):
    result, output = run_deadline_command([command_name, job_id])
    if not result:
        return output
    return output


def clean_render_output_value(value):
    if not value:
        return None

    value = str(value).strip().strip("'\"")
    if "RenderOutput=" in value:
        value = value.split("RenderOutput=", 1)[1].strip().strip("'\"")

    # Deadline/3ds Max can append the full renderer settings after the output
    # file, usually as ",SaveFile=true,ShowFrameBuffer=...". Keep only the path.
    extension_match = re.search(
        r"((?:[A-Za-z]:|\\\\)[^,\r\n]+?\.(?:jpg|jpeg|png|exr|tif|tiff|tga|bmp|cxr))(?=,|;|\r|\n|$)",
        value,
        re.IGNORECASE,
    )
    if extension_match:
        return extension_match.group(1).strip().strip("'\"")

    value = re.split(r",(?=[A-Za-z_][A-Za-z0-9_]*=)", value, maxsplit=1)[0]
    return value.strip().strip("'\"") or None


def extract_render_output(job_data, raw_output):
    render_output = clean_render_output_value(
        first_value(job_data, ("RenderOutput",), "")
    )
    if render_output:
        return render_output

    plugin_chunks = values_list(job_data, "PluginInfoDictionary")
    plugin_text = "\n".join(plugin_chunks + [raw_output or ""])
    match = re.search(r"RenderOutput\s*=\s*([^;\n\r}]+)", plugin_text)
    if match:
        return clean_render_output_value(match.group(1))
    return None


def deadline_status_to_legacy(job_data, task_data, render_output):
    status = (
        str(first_value(job_data, ("Status", "JobStatus"), "") or "").strip().lower()
    )
    task_statuses = [
        str(v).strip().lower() for v in values_list(task_data, "TaskStatus")
    ]

    failed = status in {"failed", "error"} or any(v == "failed" for v in task_statuses)
    complete = status in {"completed", "complete", "succeeded"} and not failed
    active = not complete and status not in {
        "failed",
        "suspended",
        "deleted",
        "archived",
    }

    return {
        "Active": "Yes" if active else "No",
        "Complete": "Yes" if complete else "No",
        "Computer": first_value(
            task_data, ("TaskSlaveName", "SlaveName", "WorkerName", "MachineName")
        )
        or first_value(job_data, ("MachineName", "WorkerName")),
        "Name": first_value(job_data, ("Name", "JobName")),
        "Submitted": first_value(
            job_data, ("SubmitDateTime", "SubmitDate", "JobSubmitDateTime")
        ),
        "Description": first_value(
            job_data, ("Description", "Comment", "JobComment", "ExtraInfo0")
        ),
        "LastUpdated": first_value(
            job_data,
            (
                "CompletedDateTime",
                "LastWriteTime",
                "LastUpdatedDateTime",
                "LastUpdated",
            ),
        ),
        "ExrPath": render_output,
    }


def deadline_error_info(job_id, job_data, task_data):
    error_count = 0
    for key in ("TaskErrorCount", "JobErrorCount", "ErrorCount", "Errors"):
        for value in values_list(task_data, key) + values_list(job_data, key):
            try:
                error_count = max(error_count, int(value))
            except Exception:
                pass

    failed = any(
        str(v).strip().lower() == "failed" for v in values_list(task_data, "TaskStatus")
    )
    failed = failed or str(
        first_value(job_data, ("Status", "JobStatus"), "")
    ).strip().lower() in {"failed", "error"}
    has_error = error_count > 0 or failed
    if not has_error:
        return False, ""

    parts = []
    error_reports = get_deadline_report_output(job_id, "GetJobErrorReportFilenames")
    log_reports = get_deadline_report_output(job_id, "GetJobLogReportFilenames")
    if error_reports:
        parts.append("Error reports:\n" + error_reports)
    if log_reports:
        parts.append("Log reports:\n" + log_reports)
    if not parts:
        parts.append(
            f"Deadline indicou erro no job {job_id}, mas não retornou relatórios."
        )
    return True, "\n\n".join(parts)


def convert_deadline_render_path(render_output):
    if not render_output:
        return None
    path = clean_render_output_value(render_output)
    if not path:
        return None
    if re.match(r"^[Mm]:", path):
        path = path.replace(path[:2], r"\\192.168.0.250\renders2", 1)
    elif re.match(r"^[Yy]:", path):
        path = path.replace(path[:2], r"\\192.168.0.250\renders", 1)
    elif re.match(r"^[Nn]:", path):
        path = path.replace(path[:2], r"\\192.168.0.250\exchange", 1)

    base = os.path.basename(path)
    if os.path.splitext(base)[1] or any(
        token in base for token in ("#", "%0", "<FRAME>", "$F")
    ):
        return os.path.dirname(path)
    return path


def render_alta_has_deadline_job_id(cursor):
    cursor.execute(
        """
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = %s
          AND TABLE_NAME = 'render_alta'
          AND COLUMN_NAME = 'deadline_job_id'
        """,
        (os.getenv("DB_NAME"),),
    )
    row = cursor.fetchone()
    return bool(row and row[0])


def get_linked_deadline_jobs(cursor):
    cursor.execute("""
        SELECT idrender_alta, deadline_job_id
        FROM render_alta
        WHERE deadline_job_id IS NOT NULL
          AND deadline_job_id <> ''
        ORDER BY idrender_alta DESC
        """)
    return cursor.fetchall()


def discover_deadline_job_ids():
    job_ids = list(DEADLINE_JOB_IDS)
    result, output = run_deadline_command(["-GetJobs", "True"])
    if result and output:
        for match in re.finditer(r"\b[a-fA-F0-9]{24}\b", output):
            job_ids.append(match.group(0))
        parsed = parse_deadline_output(output)
        for key in ("JobID", "JobId", "ID", "Id"):
            job_ids.extend(values_list(parsed, key))
    else:
        log_and_print(
            "⚠ Não foi possível listar jobs via -GetJobs True; usando apenas DEADLINE_JOB_IDS e jobs já vinculados.",
            "warning",
        )

    seen = set()
    unique_ids = []
    for job_id in job_ids:
        job_id = str(job_id).strip()
        if job_id and job_id not in seen:
            seen.add(job_id)
            unique_ids.append(job_id)
    return unique_ids


def normalize_datetime_for_mysql(s: str):
    """Normaliza várias formatações comuns vindas do Deadline para 'YYYY-MM-DD HH:MM:SS[.ffffff]'.
    Retorna None se não for possível normalizar.
    """
    if not s:
        return None
    s = str(s).strip()
    deadline_formats = (
        "%b %d/%y %H:%M:%S",
        "%b %d/%Y %H:%M:%S",
        "%d/%m/%Y %H:%M:%S",
        "%m/%d/%Y %H:%M:%S",
        "%m/%d/%Y %I:%M:%S %p",
        "%Y-%m-%d %H:%M:%S",
        "%Y-%m-%d %H:%M:%S.%f",
        "%Y-%m-%dT%H:%M:%S",
        "%Y-%m-%dT%H:%M:%S.%f",
    )
    cleaned = re.sub(r"\s+", " ", s)
    cleaned = re.sub(r"\s+[+-]\d{1,2}(?::?\d{2})?$", "", cleaned).strip()
    for fmt in deadline_formats:
        try:
            return datetime.strptime(cleaned, fmt).strftime("%Y-%m-%d %H:%M:%S")
        except ValueError:
            pass

    # substituir barra por hífen para normalizar data
    s = s.replace("/", "-")

    # Captura data inicial (YYYY-MM-DD) e resto
    m = re.match(r"^(\d{4})-(\d{1,2})-(\d{1,2})[T\s\-]*(.*)$", s)
    if not m:
        return None
    year, mon, day, rest = m.group(1), m.group(2), m.group(3), m.group(4)

    # Remover timezone offset no fim, ex: -03, +0100, -03:00
    tz_match = re.search(r"([+-]\d{1,2}(?::?\d{2})?)\s*$", rest)
    if tz_match:
        rest = rest[: tz_match.start()].rstrip(" -:")

    # Separe componentes de tempo; alguns formatos usam ':' antes da fração
    parts = re.split(r"[:\.]", rest) if rest else []
    if len(parts) >= 3:
        hour = parts[0].zfill(2)
        minute = parts[1].zfill(2)
        second = parts[2].zfill(2)
        frac = "".join(parts[3:]) if len(parts) > 3 else ""
        if frac:
            # manter apenas dígitos e ajustar para micros (6 dígitos)
            frac = re.sub(r"\D", "", frac)
            frac = (frac + "000000")[:6]
            time_part = f"{hour}:{minute}:{second}.{frac}"
        else:
            time_part = f"{hour}:{minute}:{second}"
    else:
        # se não conseguimos extrair hora, retornar apenas data (MySQL aceita 'YYYY-MM-DD')
        time_part = ""

    date_part = f"{int(year):04d}-{int(mon):02d}-{int(day):02d}"
    if time_part:
        return f"{date_part} {time_part}"
    return date_part


def find_imagem_id(cursor, name):
    log_and_print(f"Buscando imagem no banco: {name}", "debug")

    # 1. Busca exata
    cursor.execute(
        "SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE imagem_nome=%s",
        (name,),
    )
    result = cursor.fetchone()
    if result:
        log_and_print(f"Imagem encontrada pelo nome exato: {result[0]}", "debug")
        return result[0]

    # 2. Busca pelo prefixo normalizado
    prefix = get_prefix(name)
    if prefix:  # só tenta se tiver prefixo válido
        cursor.execute(
            "SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE REPLACE(imagem_nome,' ','') LIKE %s",
            (prefix + "%",),
        )
        result = cursor.fetchone()
        if result:
            log_and_print(f"Imagem encontrada pelo prefixo: {result[0]}", "debug")
            return result[0]

    log_and_print("Imagem não encontrada", "warning")
    return None


def find_responsavel_id(cursor, imagem_id):
    log_and_print(f"Buscando responsavel no banco: {imagem_id}", "debug")
    cursor.execute(
        "SELECT colaborador_id, funcao_id FROM funcao_imagem WHERE funcao_id in (4, 6) AND imagem_id = %s ORDER BY funcao_id DESC LIMIT 1",
        (imagem_id,),
    )
    result = cursor.fetchone()
    if result:
        log_and_print(
            f"Colaborador encontrado: {result[0]} (função {result[1]})", "debug"
        )
        return result  # retorna (colaborador_id, funcao_id)
    log_and_print("Imagem não encontrada", "warning")
    return None, None


def find_status_id(cursor, imagem_id):
    log_and_print(f"Buscando status atual no banco: {imagem_id}", "debug")
    cursor.execute(
        "SELECT status_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = %s",
        (imagem_id,),
    )
    result = cursor.fetchone()
    if result:
        log_and_print(f"Status atual: {result[0]}", "debug")
        return result[0]
    log_and_print("Imagem não encontrada", "warning")
    return None


def process_deadline_job(
    cursor,
    p00_rollup=None,
    deadline_job_id=None,
    job_data=None,
    task_data=None,
    raw_job_output="",
    attempt_context=None,
    notifications_enabled=True,
    error_info=None,
    processing_plan=None,
):
    is_deadline_job = bool(deadline_job_id)
    strict_attempt = attempt_context is not None
    event_mode = strict_attempt and processing_plan is not None
    processing_plan = processing_plan or {}
    process_preview = not event_mode or bool(processing_plan.get("process_preview"))
    process_post = not event_mode or bool(processing_plan.get("process_post"))
    process_state_update = not event_mode or bool(processing_plan.get("state_changed"))
    state_event_key = processing_plan.get("state_event_key", "LEGACY")

    if not is_deadline_job:
        log_and_print(
            "process_deadline_job recebeu chamada sem deadline_job_id; ignorado no monitor Deadline.",
            "warning",
        )
        return False

    render_output = extract_render_output(job_data or {}, raw_job_output)
    xml_data = deadline_status_to_legacy(job_data or {}, task_data or {}, render_output)
    has_error, errors = error_info or deadline_error_info(
        deadline_job_id, job_data or {}, task_data or {}
    )
    job_folder = deadline_job_id
    folder_name = xml_data.get("Name") or deadline_job_id
    log_and_print(
        f"Processando evento Deadline: {deadline_job_id} - {folder_name}", "debug"
    )
    image_name_xml = xml_data.get("Name")
    if not image_name_xml:
        log_and_print("Campo Name/JobName nao encontrado no Deadline Job", "warning")
        return False if strict_attempt else None

    if FILTER_IMAGES:
        if not any(f.lower() in image_name_xml.lower() for f in FILTER_IMAGES):
            log_and_print(f"⏭ Ignorado por filtro: {image_name_xml}")
            return False if strict_attempt else None
    if EXCLUDE_KEYWORD in image_name_xml.upper():
        log_and_print(f"Ignorado (ANIMA): {image_name_xml}")
        return False if strict_attempt else None

    linked_render = None
    if strict_attempt:
        cursor.execute(
            """
            SELECT r.idrender_alta, r.imagem_id, r.status_id, r.status, r.previa_jpg
            FROM render_tentativas rt
            JOIN render_alta r ON r.idrender_alta = rt.render_id
            WHERE rt.id = %s
              AND rt.render_id = %s
              AND rt.deadline_job_id = %s
              AND rt.ativa = 1
              AND r.excluido_em IS NULL
            LIMIT 1
        """,
            (
                attempt_context["id"],
                attempt_context["render_id"],
                deadline_job_id,
            ),
        )
        linked_render = cursor.fetchone()
        if not linked_render:
            log_and_print(
                f"Tentativa ativa nao confirma o vinculo do job {deadline_job_id}; processamento recusado.",
                "warning",
            )
            return False
    elif is_deadline_job:
        cursor.execute(
            """
            SELECT idrender_alta, imagem_id, status_id, status, previa_jpg
            FROM render_alta
            WHERE deadline_job_id = %s
            ORDER BY idrender_alta DESC
            LIMIT 1
        """,
            (deadline_job_id,),
        )
        linked_render = cursor.fetchone()

    if linked_render:
        imagem_id = linked_render[1]
        log_and_print(
            f"Render vinculado encontrado: render_id={linked_render[0]}, imagem_id={imagem_id}"
        )
    else:
        imagem_id = find_imagem_id(cursor, image_name_xml)
        if not imagem_id:
            log_and_print(f"Imagem não encontrada para {image_name_xml}")
            return

    exr_path = xml_data.get("ExrPath")
    caminho_pasta = convert_deadline_render_path(exr_path) if exr_path else None

    # Buscar informações complementares
    cursor.execute(
        "SELECT imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra = %s",
        (imagem_id,),
    )
    row = cursor.fetchone()
    image_name_db = row[0] if row else None

    resp_id, funcao_id = find_responsavel_id(cursor, imagem_id)
    status_id = (
        linked_render[2]
        if linked_render and linked_render[2]
        else find_status_id(cursor, imagem_id)
    )

    # ─────────────────────────────────────────────────────────────────────
    # Proteção contra reprocessamento de jobs antigos
    # Cenário: render P00 foi aprovado → imagem avançou para R00.
    # O job antigo pode continuar existindo no scheduler. Quando o script roda de novo,
    # find_status_id devolve o status ATUAL da imagem (R00) mas os dados
    # do scheduler ainda são do job P00, causando um registro cruzado errado.
    #
    # Solução: se este job_folder (caminho_pasta) já foi gravado em
    # render_alta com um status_id DIFERENTE do status atual da imagem,
    # significa que o job pertence a um ciclo de render já superado → pular.
    # ─────────────────────────────────────────────────────────────────────
    # Buscar status existente
    cursor.execute(
        """
        SELECT idrender_alta, status, previa_jpg, deadline_job_id
        FROM render_alta
        WHERE imagem_id = %s AND status_id = %s
        ORDER BY idrender_alta DESC
        LIMIT 1
    """,
        (imagem_id, status_id),
    )
    existing_status = cursor.fetchone()

    render_id = existing_status[0] if existing_status else None
    ultimo_status = existing_status[1] if existing_status else None
    existing_preview = existing_status[2] if existing_status else None
    existing_deadline_job_id = (
        str(existing_status[3]).strip()
        if existing_status and existing_status[3] is not None
        else None
    )
    is_existing_deadline_job = bool(
        existing_deadline_job_id and existing_deadline_job_id == deadline_job_id
    )

    if is_deadline_job and existing_status:
        if existing_deadline_job_id and existing_deadline_job_id != deadline_job_id:
            log_and_print(
                f"Render {render_id} ja esta vinculado ao Deadline Job {existing_deadline_job_id}; "
                f"job {deadline_job_id} foi ignorado para evitar sobrescrever o vinculo.",
                "warning",
            )
            return False if strict_attempt else None

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
        status_custom = xml_data.get("Complete") or "Desconhecido"

    should_delete_linked_job = (
        not strict_attempt
        and is_existing_deadline_job
        and (
            ultimo_status in DEADLINE_DELETE_STATUSES
            or ultimo_status == DEADLINE_REWORK_STATUS
        )
    )
    should_ignore_closed_unlinked_job = not strict_attempt and (
        existing_status
        and not is_existing_deadline_job
        and ultimo_status in DEADLINE_DELETE_STATUSES
    )

    if should_delete_linked_job:
        log_and_print(
            f"Status '{ultimo_status}' detectado para {deadline_job_id}; "
            "o monitor legado nao exclui nem limpa vinculos Deadline.",
            "warning",
        )
        return

    if should_ignore_closed_unlinked_job:
        log_and_print(
            f"Status '{ultimo_status}' detectado, mas o job {deadline_job_id} nao esta vinculado ao render {render_id}; "
            "ignorado para evitar reabrir um ciclo fechado.",
            "warning",
        )
        return

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
                "all_complete": True,
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
    remote_base_path = (
        "/web/improov.com.br/public_html/flow/ImproovWeb/uploads/renders/"
    )

    # 1️⃣ Se status atual = Em aprovação → atualizar previa_jpg (substitui no VPS mesmo se mesmo nome)
    if ultimo_status == "Em aprovação" and not process_preview:
        return True
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
                uploaded = upload_previews_once(
                    cursor,
                    attempt_context["id"] if strict_attempt else None,
                    caminho_pasta,
                    [preview_name],
                    remote_base_path,
                )
                upload_ok = preview_name in uploaded

                if upload_ok or preview_name != existing_preview:
                    # Atualiza banco apenas se o upload teve sucesso
                    cursor.execute(
                        """
                        UPDATE render_alta
                        SET previa_jpg = %s
                        WHERE idrender_alta = %s
                    """,
                        (preview_name, render_id),
                    )
                    log_and_print(
                        f"🖼️ Previa JPG atualizada para {preview_name} (status já era 'Em aprovação')"
                    )
                elif preview_name == existing_preview:
                    log_and_print(f"Preview sem alteracao: {preview_name}", "debug")
                else:
                    log_and_print(
                        f"⚠ Upload falhou — nenhuma alteração foi feita no banco para {preview_name}",
                        "warning",
                    )
        return True  # não faz mais nada

    # 2. Se status = Refazendo: o job foi refeito e um NOVO job foi submetido.
    # So tratamos Refazendo como novo ciclo quando o novo render COMPLETAR (complete=True).
    # Enquanto o novo job está rodando (active=True, complete=False), deixamos ele finalizar.
    if ultimo_status == DEADLINE_REWORK_STATUS:
        if complete:
            # Novo render completou após refazimento → tratar como "Em aprovação" (novo ciclo)
            log_and_print(
                f"🔄 Job completou após reprovação — promovendo para 'Em aprovação': {job_folder}"
            )
            ultimo_status = None  # força prosseguir pelo fluxo normal
            status_custom = "Em aprovação"
        elif active and not complete:
            # Ainda renderizando — deixar finalizar normalmente
            log_and_print(
                f"⏩ Render reprovado sendo refeito (Em andamento) — aguardando conclusão: {job_folder}"
            )
            ultimo_status = (
                None  # força prosseguir pelo fluxo normal (atualiza status no banco)
            )
            status_custom = "Em andamento"
        else:
            # Inativo e incompleto — estado indefinido, processar normalmente
            log_and_print(
                f"⏩ Render reprovado com estado indefinido (active=False, complete=False) — processando normalmente: {job_folder}"
            )
            ultimo_status = None

    # 3️⃣ Se status estava como Erro e Complete=Yes → mudar para Em aprovação
    if ultimo_status == "Erro" and complete:
        status_custom = "Em aprovação"
        log_and_print(
            "🔄 Status alterado de 'Erro' para 'Em aprovação' pois Complete=Yes"
        )

    if is_deadline_job and existing_status:
        cursor.execute(
            """
            UPDATE render_alta
            SET deadline_job_id = %s
            WHERE idrender_alta = %s
              AND (deadline_job_id IS NULL OR TRIM(deadline_job_id) = "" OR deadline_job_id = %s)
        """,
            (deadline_job_id, render_id, deadline_job_id),
        )
        if cursor.rowcount:
            log_and_print(
                f"Deadline Job vinculado ao render_alta {render_id}: {deadline_job_id}"
            )
        else:
            log_and_print(
                f"Deadline Job ja vinculado ao render_alta {render_id}: {deadline_job_id}",
                "debug",
            )

    log_and_print(f"Procurando JPGs em: {caminho_pasta}", "debug")

    # 4️⃣ Fluxo normal para Em andamento ou novo registro
    previa_val = None
    uploaded_previews = []
    if process_preview and caminho_pasta and os.path.exists(caminho_pasta):
        # Collect all JPGs (angles). We'll upload each and store in render_previews.
        jpgs = [f for f in os.listdir(caminho_pasta) if f.lower().endswith(".jpg")]
        if jpgs:
            # Sort to have deterministic order (e.g., LD1_RES_001, LD1_RES_002 ...)
            jpgs.sort()
            previa_val = jpgs[
                0
            ]  # legacy: store the first one in render_alta.prevista_jpg

            uploaded_previews = upload_previews_once(
                cursor,
                attempt_context["id"] if strict_attempt else None,
                caminho_pasta,
                jpgs,
                remote_base_path,
            )

            # After uploading all previews, we'll insert them into render_previews once render_id is known.

    if event_mode and process_preview and not processing_plan.get("state_changed"):
        preview_changed = bool(uploaded_previews) or previa_val != existing_preview
        if not preview_changed:
            log_and_print(
                f"Job {deadline_job_id} sem alteracoes desde a ultima sincronizacao.",
                "debug",
            )
            return True

    # Normalizar datas vindas do Deadline para um formato aceito pelo MySQL
    submitted_raw = xml_data.get("Submitted")
    last_updated_raw = xml_data.get("LastUpdated")
    submitted_dt = normalize_datetime_for_mysql(submitted_raw)
    last_updated_dt = normalize_datetime_for_mysql(last_updated_raw)
    if submitted_raw and not submitted_dt:
        log_and_print(
            f"⚠ Não foi possível normalizar Submitted='{submitted_raw}' — será gravado NULL",
            "warning",
        )
    if last_updated_raw and not last_updated_dt:
        log_and_print(
            f"⚠ Não foi possível normalizar LastUpdated='{last_updated_raw}' — será gravado NULL",
            "warning",
        )

    status_to_write = status_custom
    if status_id == 1 and ultimo_status is not None:
        status_to_write = ultimo_status

    cursor.execute(
        """
        INSERT INTO render_alta
        (imagem_id, responsavel_id, status_id, status, data, computer, submitted, last_updated, has_error, errors, job_folder, previa_jpg, numero_bg, deadline_job_id)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            status=VALUES(status),
            computer=VALUES(computer),
            submitted=VALUES(submitted),
            last_updated=VALUES(last_updated),
            has_error=VALUES(has_error),
            errors=VALUES(errors),
            job_folder=VALUES(job_folder),
            previa_jpg=IFNULL(VALUES(previa_jpg), previa_jpg),
            deadline_job_id=IF(
                deadline_job_id IS NULL OR TRIM(deadline_job_id) = '' OR deadline_job_id = VALUES(deadline_job_id),
                VALUES(deadline_job_id),
                deadline_job_id
            )
    """,
        (
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
            xml_data.get("Description"),
            deadline_job_id,
        ),
    )

    log_and_print(
        f"✅ Render atualizado/inserido — status={status_custom}, previa_jpg={previa_val}"
    )

    # 🔹 Buscar render_id com imagem_id e status_id
    cursor.execute(
        "SELECT idrender_alta FROM render_alta WHERE imagem_id = %s AND status_id = %s",
        (imagem_id, status_id),
    )
    render_row = cursor.fetchone()
    render_id = render_row[0] if render_row else None

    # 🔹 Buscar obra_id pela imagem_id (não precisa mudar nada aqui)
    cursor.execute(
        "SELECT obra_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = %s",
        (imagem_id,),
    )
    obra_row = cursor.fetchone()
    obra_id = obra_row[0] if obra_row else None

    # 🔹 Buscar responsável da pós-produção (funcao_id = 5)
    cursor.execute(
        "SELECT colaborador_id FROM funcao_imagem WHERE funcao_id = 5 AND imagem_id = %s",
        (imagem_id,),
    )
    pos_row = cursor.fetchone()
    responsavel_pos_id = pos_row[0] if pos_row else None

    # 🔹 Inserir ou atualizar na tabela pós-produção
    # Não criar registro de pós-produção quando status_id == 1
    if process_post and responsavel_pos_id and resp_id and status_id != 1:
        cursor.execute(
            """
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
        """,
            (
                render_id,
                imagem_id,
                obra_id,
                resp_id,
                caminho_pasta,
                xml_data.get("Description"),
                status_id,
                responsavel_pos_id,
            ),
        )
        log_and_print(
            f"📌 Pós-produção vinculada: render_id={render_id}, imagem_id={imagem_id}, obra_id={obra_id}"
        )
    elif process_post:
        if responsavel_pos_id and status_id == 1:
            log_and_print(
                f"⚠ Pos-produção não criada pois status_id == 1 para imagem_id {imagem_id}"
            )
        elif responsavel_pos_id and not resp_id:
            log_and_print(
                f"⚠ Pos-produção não criada pois responsável de render (resp_id) é nulo para imagem_id {imagem_id}"
            )
        else:
            log_and_print(
                f"⚠ Imagem {imagem_id} não possui pós-produção, pulando inserção na pos_producao"
            )

    # -------------------------------
    # Lógica de notificação
    # -------------------------------
    # Notificar canal quando não há colaborador atribuído (sempre que processar)
    if notifications_enabled and not resp_id:
        msg_sem_colab = f"⚠ Render da imagem *{image_name_db}* processado (status: *{status_custom}*), mas *não há colaborador atribuído* (sem responsável de render)."
        send_transition_notifications(
            cursor,
            attempt_context["id"] if strict_attempt else None,
            state_event_key,
            None,
            image_name_db,
            msg_sem_colab,
        )

    if (
        notifications_enabled
        and status_id != 1
        and resp_id
        # O worker ja confirmou a transicao e usa a chave do evento para
        # impedir duplicidade. O monitor legado ainda depende da comparacao
        # com render_alta, pois nao possui um contexto de tentativa estrito.
        and (event_mode or status_custom != ultimo_status)
    ):
        if status_custom == "Erro":
            msg = f"O render da imagem: {image_name_db} deu erro, favor verificar!"
        elif status_custom == "Em aprovação":
            msg = f"O render da imagem: {image_name_db} foi concluído com sucesso, favor aprovar!"
        elif status_custom == "Em andamento":
            msg = f"O render da imagem: {image_name_db} está em andamento."
        else:
            msg = None

        if msg:
            send_transition_notifications(
                cursor,
                attempt_context["id"] if strict_attempt else None,
                state_event_key,
                resp_id,
                image_name_db,
                msg,
            )

        # Atualizar função e imagem
    if process_state_update and status_custom in ("Em aprovação", "Em andamento") and funcao_id:
        # cursor.execute("""
        #     UPDATE funcao_imagem
        #     SET status = 'Finalizado', prazo = NOW()
        #     WHERE imagem_id = %s AND funcao_id = %s
        # """, (imagem_id, funcao_id))
        # log_and_print(f"Função atualizada para Finalizado para imagem_id {imagem_id}")

        cursor.execute(
            """
            UPDATE imagens_cliente_obra
            SET substatus_id = 5
            WHERE idimagens_cliente_obra = %s
        """,
            (imagem_id,),
        )
        log_and_print(f"Imagem atualizada para status = REN na imagem_id {imagem_id}")

    # -------------------------------
    # Salvar previews múltiplos (angles) na tabela render_previews
    # -------------------------------
    try:
        # Apenas registre previews múltiplos se o status da imagem for P00 (status_id == 1)
        if (
            "uploaded_previews" in locals()
            and uploaded_previews
            and render_id
            and status_id == 1
        ):
            # Use INSERT ... ON DUPLICATE KEY UPDATE noop para evitar duplicatas.
            for filename in uploaded_previews:
                try:
                    cursor.execute(
                        "INSERT INTO render_previews (render_id, filename) VALUES (%s, %s) ON DUPLICATE KEY UPDATE filename=filename",
                        (render_id, filename),
                    )
                    log_and_print(
                        f"➕ Preview registrado: {filename} -> render_id {render_id}"
                    )
                except Exception as e:
                    log_and_print(
                        f"⚠ Falha ao inserir preview {filename}: {e}", "warning"
                    )
        else:
            if "uploaded_previews" in locals() and uploaded_previews:
                log_and_print(
                    f"ℹ Previews encontrados, mas não registrados (status_id={status_id})"
                )
    except Exception as e:
        log_and_print(f"⚠ Erro ao processar previews: {e}", "warning")

    return True


def finalize_p00_rollup(cursor, p00_rollup, notifications_enabled=True):
    """Aplica o status agregado P00 preservando a regra historica de notificacao."""
    for imagem_id, roll in (p00_rollup or {}).items():
        total_jobs = roll.get("total_jobs", 0)
        if roll.get("any_error"):
            status_agg = "Erro"
        elif roll.get("any_incomplete"):
            status_agg = "Em andamento"
        elif roll.get("all_complete") and total_jobs > 0:
            status_agg = "Em aprovação"
        else:
            status_agg = "Desconhecido"

        cursor.execute(
            """
            SELECT idrender_alta, status
            FROM render_alta
            WHERE imagem_id = %s AND status_id = 1
            ORDER BY idrender_alta DESC
            LIMIT 1
            """,
            (imagem_id,),
        )
        row = cursor.fetchone()
        render_id = row[0] if row else None
        ultimo_status = row[1] if row else None
        if render_id and ultimo_status not in DEADLINE_CLOSED_STATUSES:
            cursor.execute(
                "UPDATE render_alta SET status = %s WHERE idrender_alta = %s",
                (status_agg, render_id),
            )

        resp_id = roll.get("resp_id")
        if (
            not notifications_enabled
            or not resp_id
            or status_agg == ultimo_status
            or ultimo_status in DEADLINE_CLOSED_STATUSES
        ):
            continue

        image_name_db = roll.get("image_name_db")
        messages = {
            "Erro": f"O render da imagem: {image_name_db} deu erro, favor verificar!",
            "Em aprovação": f"O render da imagem: {image_name_db} foi concluído com sucesso, favor aprovar!",
            "Em andamento": f"O render da imagem: {image_name_db} está em andamento.",
        }
        msg = messages.get(status_agg)
        if not msg:
            continue
        send_webhook_message(msg)
        cursor.execute(
            "SELECT nome_slack FROM usuario WHERE idcolaborador = %s", (resp_id,)
        )
        for slack_name_tuple in cursor.fetchall():
            user_id = get_user_id_by_name(slack_name_tuple[0])
            if user_id:
                send_dm_to_user(user_id, msg)
        insert_notification(cursor, resp_id, msg)
        log_and_print(
            f"Notificação P00 enviada para colaborador {resp_id} e canal de renders."
        )


def main():
    global conn
    mode = os.getenv("DEADLINE_WORKER_MODE", "continuous").strip().lower()
    legacy_enabled = os.getenv("DEADLINE_LEGACY_MODE", "0").strip().lower() in {
        "1",
        "true",
        "yes",
        "sim",
        "on",
    }
    if mode == "continuous" and not legacy_enabled:
        log_and_print(
            "Monitor periodico desativado. Inicie Render/deadline_worker.py como servico.",
            "warning",
        )
        return

    log_and_print("Iniciando monitor Deadline")
    try:
        conn = create_legacy_connection()
    except Exception as exc:
        log_and_print(f"Falha ao conectar no banco: {exc}", "error")
        conn = None
    if conn is None:
        log_and_print(
            "Conexao com banco indisponivel. Encerrando monitor Deadline.", "error"
        )
        return

    with conn.cursor() as cursor:
        if not render_alta_has_deadline_job_id(cursor):
            log_and_print(
                "A coluna render_alta.deadline_job_id nao existe no banco atual. Crie a coluna antes de ativar o monitor Deadline.",
                "error",
            )
            return

        p00_rollup = {}
        processed_jobs = 0
        processed_ids = set()

        linked_jobs = get_linked_deadline_jobs(cursor)
        discovered_job_ids = discover_deadline_job_ids()
        queue = []

        for render_id, job_id in linked_jobs:
            if job_id and job_id not in processed_ids:
                queue.append(job_id)
                processed_ids.add(job_id)

        for job_id in discovered_job_ids:
            if job_id and job_id not in processed_ids:
                queue.append(job_id)
                processed_ids.add(job_id)

        log_and_print(
            f"Jobs Deadline na fila: {len(queue)} (vinculados={len(linked_jobs)}, descobertos={len(discovered_job_ids)})"
        )

        for job_id in queue:
            try:
                job_data, raw_job_output = get_deadline_job(job_id)
                if not job_data:
                    log_and_print(
                        f"Job Deadline nao encontrado ou sem dados: {job_id}", "warning"
                    )
                    continue
                task_data, _raw_task_output = get_deadline_tasks(job_id)
                process_deadline_job(
                    cursor,
                    p00_rollup=p00_rollup,
                    deadline_job_id=job_id,
                    job_data=job_data,
                    task_data=task_data,
                    raw_job_output=raw_job_output,
                )
                processed_jobs += 1
                conn.commit()
                if MAX_JOBS and processed_jobs >= MAX_JOBS:
                    log_and_print(
                        f"Limite MAX_JOBS atingido ({MAX_JOBS}). Encerrando processamento."
                    )
                    break
            except Exception as e:
                log_and_print(f"Erro ao processar Deadline Job {job_id}: {e}", "error")
                conn.rollback()
                continue

        log_and_print(f"Total de jobs Deadline processados: {processed_jobs}")

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
                    (imagem_id,),
                )
                row = cursor.fetchone()
                render_id = row[0] if row else None
                ultimo_status = row[1] if row else None

                # Atualiza status agregado no render_alta (mantém estado único por imagem)
                # Não sobrescreve status que fecha o ciclo no Deadline.
                if render_id and ultimo_status not in DEADLINE_CLOSED_STATUSES:
                    cursor.execute(
                        "UPDATE render_alta SET status = %s WHERE idrender_alta = %s",
                        (status_agg, render_id),
                    )

                resp_id = roll.get("resp_id")
                image_name_db = roll.get("image_name_db")

                # Enviar notificação apenas quando houver mudança real no status agregado
                # e não houve status fechado anteriormente
                if (
                    resp_id
                    and status_agg != ultimo_status
                    and ultimo_status not in DEADLINE_CLOSED_STATUSES
                ):
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

                        cursor.execute(
                            "SELECT nome_slack FROM usuario WHERE idcolaborador = %s",
                            (resp_id,),
                        )
                        slack_names = cursor.fetchall()
                        for slack_name_tuple in slack_names:
                            slack_name = slack_name_tuple[0]
                            user_id = get_user_id_by_name(slack_name)
                            if user_id:
                                send_dm_to_user(user_id, msg)

                        insert_notification(cursor, resp_id, msg)
                        log_and_print(
                            f"🔔 Notificação P00 enviada para colaborador {resp_id} e canal de renders."
                        )
        conn.commit()
    log_and_print("Processamento concluído!")
    conn.close()
    conn = None


if __name__ == "__main__":
    main()
