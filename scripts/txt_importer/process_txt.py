#!/usr/bin/env python3
"""
process_txt.py

Reads a TXT file with lines containing: cliente_id,obra_id,imagem_nome (comma-separated or tab)
Or a header line with cliente_id and obra_id in the first line and subsequent lines with image names.

It queries the `obra` table to get `nomenclatura` for the given obra_id and inserts formatted image names into `imagens_cliente_obra`.

Behavior:
- If image name starts with a numeric prefix plus a dot (e.g., "1. Fachada"), it preserves the numeric prefix and injects nomenclatura after the dot: "1.TES_TES Fachada".
- If no numeric prefix: "TES_TES Fachada"

Usage:
    python process_txt.py images_input.txt

"""
import sys
import os
import re
import argparse
import mysql.connector
from mysql.connector import errorcode
from dotenv import load_dotenv
load_dotenv()

# Configuration - prefer environment variables for safety in different environments
DB_CONFIG = {
    'host': os.getenv('IMPORT_DB_HOST', '72.60.137.192'),
    'port': int(os.getenv('IMPORT_DB_PORT', '3306')),
    'user': os.getenv('IMPORT_DB_USER', 'improov'),
    'password': os.getenv('IMPORT_DB_PASSWORD', 'Impr00v@'),
    'database': os.getenv('IMPORT_DB_NAME', 'flowdb'),
    'charset': 'utf8mb4'
}

INPUT_ENCODING = os.getenv('IMPORT_INPUT_ENCODING', 'utf-8')

def get_nomenclatura(conn, obra_id):
    cursor = conn.cursor()
    query = "SELECT nomenclatura FROM obra WHERE idobra = %s LIMIT 1"
    cursor.execute(query, (obra_id,))
    row = cursor.fetchone()
    cursor.close()
    return row[0] if row else None


def _remove_accents(s: str) -> str:
    import unicodedata
    if not s:
        return s
    nkfd_form = unicodedata.normalize('NFKD', s)
    return ''.join([c for c in nkfd_form if not unicodedata.combining(c)])


def sanitize_text(s: str) -> str:
    """Remove accents and replace special characters with underscore, leaving letters, numbers, spaces, dot, dash and underscore.

    Collapses multiple spaces and strips leading/trailing spaces.
    """
    if not s:
        return s
    # remove accents first
    s = _remove_accents(s)
    # replace any character that is not alnum, space, dot, dash or underscore with '_'
    s = re.sub(r"[^A-Za-z0-9 \._\-]", "_", s)
    # collapse multiple spaces / underscores
    s = re.sub(r"\s+", " ", s).strip()
    s = re.sub(r"_+", "_", s)
    # remove spaces around underscores ("a / b" -> "a_b")
    s = re.sub(r"\s*_\s*", "_", s)
    s = re.sub(r"\s+", " ", s).strip()
    return s


def detect_tipo_imagem(imagem_nome: str) -> str:
    """Return a tipo_imagem label based on keywords in imagem_nome.

    Mapping (case-insensitive, accent-insensitive):
    - Fachada: fotomontagem, fachada, embasamento
    - Unidade: living, suite, teraco, duplex
    - Planta humanizada: planta humanizada
    - Imagem Interna: academia, hall de entrada, salao de jogos, salao de festas, piscina aquecida, jogos, coworking, lavanderia, gourmet, interno
    - Imagem Externa: piscina, playground, externo
    """
    if not imagem_nome:
        return ''
    s = imagem_nome.lower()
    s = _remove_accents(s)

    # priority checks (phrases first)
    if 'planta humanizada' in s:
        return 'Planta Humanizada'
    if 'piscina aquecida' in s:
        return 'Imagem Interna'

    # Fachada group
    for kw in ['fotomontagem', 'fachada', 'embasamento', 'foto inserção']:
        if kw in s:
            return 'Fachada'

    # Unidade group
    for kw in ['living', 'suite', 'suíte', 'teraco', 'terraço', 'duplex']:
        if _remove_accents(kw) in s:
            return 'Unidade'

    # Imagem Interna group
    for kw in ['academia', 'hall de entrada', 'salao de jogos', 'salon de jogos', 'salao de festas', 'salon de festas', 'jogos', 'coworking', 'lavanderia', 'gourmet', 'interno', 'grill']:
        if kw in s:
            return 'Imagem Interna'

    # Imagem Externa group (generic piscina after piscina aquecida handled)
    for kw in ['piscina', 'playground', 'externo']:
        if kw in s:
            return 'Imagem Externa'

    return ''

def insert_imagem(conn, cliente_id, obra_id, imagem_nome, recebimento_arquivos=None, data_inicio=None, prazo=None, tipo_imagem='', antecipada=0, dias_trabalhados=0, clima='', dry_run=False):
    # Convert empty/blank date strings to None so MySQL INSERT will store NULL
    # (empty strings cause "Incorrect date value" errors when sql_mode disallows
    # implicit conversion). Accept None as default for date fields.
    def _coerce_date(v):
        if v is None:
            return None
        if isinstance(v, str) and v.strip() == '':
            return None
        return v

    recebimento_arquivos_db = _coerce_date(recebimento_arquivos)
    data_inicio_db = _coerce_date(data_inicio)
    prazo_db = _coerce_date(prazo)

    sql = ("INSERT INTO imagens_cliente_obra "
           "(cliente_id, obra_id, imagem_nome, recebimento_arquivos, data_inicio, prazo, tipo_imagem, antecipada, dias_trabalhados, clima) "
           "VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)")
    params = (cliente_id, obra_id, imagem_nome, recebimento_arquivos_db, data_inicio_db, prazo_db, tipo_imagem, antecipada, dias_trabalhados, clima)
    if dry_run:
        print("[DRY-RUN] SQL:", sql)
        print("[DRY-RUN] Params:", params)
        return
    cursor = conn.cursor()
    cursor.execute(sql, params)
    conn.commit()
    cursor.close()


def process_file(path, dry_run=False, nomenclatura_override=None):
    if not os.path.isfile(path):
        print(f"❌ Arquivo não encontrado: {path}")
        return

    print(f"📂 Lendo arquivo: {path}")

    with open(path, 'r', encoding=INPUT_ENCODING) as f:
        raw_lines = [line.rstrip('\n') for line in f]

    lines = [line.strip() for line in raw_lines if line.strip() and not line.strip().startswith('#')]

    if not lines:
        print("⚠️  Arquivo vazio, nada a processar.")
        return

    print(f"📄 {len(lines)} linhas encontradas (incluindo cabeçalho, se houver).")

    # Detectar formato
    # split and strip parts so values like "55, 84" are detected as digits
    first_parts = [p.strip() for p in re.split(r"[\t,;]+", lines[0]) if p.strip() != '']
    use_header = False
    if len(first_parts) >= 2 and first_parts[0].isdigit() and first_parts[1].isdigit():
        # treat as header only when first line contains exactly two numeric ids (cliente_id, obra_id)
        if len(first_parts) == 2:
            use_header = True

    conn = None
    # Attempt to connect to DB when possible. In dry-run we will try but not abort on failure
    try_connect = True
    if dry_run:
        print("🧪 Modo DRY-RUN: nenhuma inserção real será feita.")
        if nomenclatura_override:
            print(f"� Usando nomenclatura fornecida: {nomenclatura_override}")

    if try_connect:
        try:
            print("🔌 Tentando conectar ao banco (apenas para leitura/nomenclatura quando em dry-run)...")
            # Print target host/user (but don't print password)
            target_host = DB_CONFIG.get('host')
            target_port = DB_CONFIG.get('port', 3306)
            target_user = DB_CONFIG.get('user')
            print(f"🔎 Tentando {target_user}@{target_host}:{target_port}")

            # Quick TCP reachability test to fail fast if host/port are blocked
            import socket
            try:
                sock_timeout = 5
                with socket.create_connection((target_host, int(target_port)), timeout=sock_timeout):
                    print(f"🔗 Porta {target_port} acessível (teste TCP OK).")
            except Exception as sock_err:
                print(f"⚠️  Falha no teste TCP {target_host}:{target_port} — {sock_err}")
                print("⚠️  Verifique DNS/firewall/porta. Tentando conexão MySQL mesmo assim (com timeout).")

            # Ensure small timeouts for connector so it doesn't hang
            conn_cfg = DB_CONFIG.copy()
            # mysql.connector accepts connection_timeout / connect_timeout in different versions; set both
            conn_cfg.setdefault('connection_timeout', 10)
            conn_cfg.setdefault('connect_timeout', 10)
            # use pure Python implementation for more consistent timeout behavior
            conn_cfg.setdefault('use_pure', True)

            print("⏱️  Iniciando mysql.connector.connect()...", flush=True)
            import time
            t0 = time.time()
            conn = mysql.connector.connect(**conn_cfg)
            t1 = time.time()
            print(f"✅ Conectado com sucesso (tempo {t1-t0:.2f}s).", flush=True)
        except mysql.connector.Error as err:
            if getattr(err, 'errno', None) == errorcode.ER_ACCESS_DENIED_ERROR:
                print("❌ Erro de autenticação com o banco de dados (credenciais rejeitadas)")
            else:
                print(f"⚠️  Erro ao conectar ao banco (continuando sem DB): {err}")
            conn = None
        except Exception as e:
            import traceback
            print("⚠️  Erro inesperado ao tentar conectar ao banco (continuando sem DB):")
            traceback.print_exc()
            conn = None

    total_inseridas = 0

    if use_header:
        print("📋 Detectado formato: cabeçalho (cliente_id, obra_id) + nomes de imagens.")
        cliente_id = int(first_parts[0])
        obra_id = int(first_parts[1])

        # Try to fetch nomenclatura from DB when connected; otherwise use provided override
        if conn:
            nomenclatura = get_nomenclatura(conn, obra_id)
        else:
            nomenclatura = nomenclatura_override

        # sanitize nomenclatura to remove accents/special chars
        if nomenclatura:
            nomenclatura = sanitize_text(nomenclatura)

        # Fallback: when running in dry-run without a DB and no override was provided,
        # synthesize a sensible nomenclatura so the header mode can still be tested.
        if not nomenclatura:
            if dry_run:
                nomenclatura = f"OBRA{obra_id}"
                print(f"⚠️  Dry-run fallback: usando nomenclatura sintética '{nomenclatura}' for obra_id={obra_id}")
            else:
                print(f"❌ Nenhuma nomenclatura encontrada para obra_id={obra_id}")
                if conn: conn.close()
                return

        for line in lines[1:]:
            imagem_nome = format_name(line, nomenclatura)
            tipo_imagem = detect_tipo_imagem(imagem_nome)
            print(f"➡️  Inserindo imagem: {imagem_nome} (tipo_imagem='{tipo_imagem}')")
            try:
                insert_imagem(conn, cliente_id, obra_id, imagem_nome, dry_run=dry_run, tipo_imagem=tipo_imagem)
                total_inseridas += 1
            except Exception as e:
                print(f"❌ Erro ao inserir {imagem_nome}: {e}")
    else:
        print("📋 Detectado formato: cada linha com cliente_id, obra_id, imagem_nome")
        for line in lines:
            parts = re.split(r"[\t,;]+", line, maxsplit=2)
            if len(parts) < 3:
                print(f"⚠️  Linha inválida: {line}")
                continue
            cliente_id, obra_id, imagem_nome_raw = int(parts[0]), int(parts[1]), parts[2]

            nomenclatura = get_nomenclatura(conn, obra_id) if conn else nomenclatura_override
            if nomenclatura:
                nomenclatura = sanitize_text(nomenclatura)
            if not nomenclatura:
                print(f"⚠️  Nenhuma nomenclatura encontrada para obra_id={obra_id}")
                continue

            imagem_nome = format_name(imagem_nome_raw, nomenclatura)
            tipo_imagem = detect_tipo_imagem(imagem_nome)
            print(f"➡️  Inserindo imagem: {imagem_nome} (tipo_imagem='{tipo_imagem}')")
            try:
                insert_imagem(conn, cliente_id, obra_id, imagem_nome, dry_run=dry_run, tipo_imagem=tipo_imagem)
                total_inseridas += 1
            except Exception as e:
                print(f"❌ Erro ao inserir {imagem_nome}: {e}")

    if conn:
        conn.close()
        print("🔒 Conexão encerrada.")


    print(f"✅ Processo concluído. Total de imagens inseridas: {total_inseridas}")

def format_name(imagem_nome, nomenclatura):
    """If imagem_nome starts with number+dot (e.g. "1. Fachada"), return "1.NOME Rest" else "NOME Rest".

    The returned string is sanitized (no accents, no special characters).
    """
    imagem_nome = imagem_nome.strip()
    # Normalize a trailing parenthetical note: "X (ambiente interno)" -> "X - ambiente interno"
    mparen = re.match(r"^(.*)\(([^)]*)\)\s*$", imagem_nome)
    if mparen:
        prefix = mparen.group(1).strip()
        inside = mparen.group(2).strip()
        if inside:
            imagem_nome = f"{prefix} - {inside}" if prefix else inside
        else:
            imagem_nome = prefix
    # ensure nomenclatura is sanitized as well
    nomenclatura = sanitize_text(nomenclatura) if nomenclatura else ''
    m = re.match(r'^(\d+\.)\s*(.*)$', imagem_nome)
    if m:
        prefix = m.group(1)
        rest = sanitize_text(m.group(2))
        if rest:
            return f"{prefix}{nomenclatura} {rest}"
        else:
            return f"{prefix}{nomenclatura}"
    else:
        rest = sanitize_text(imagem_nome)
        if rest:
            return f"{nomenclatura} {rest}"
        else:
            return f"{nomenclatura}"


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Import images from a TXT file into imagens_cliente_obra')
    parser.add_argument('file', nargs='?', default=os.path.join(os.path.dirname(__file__), 'images_input.txt'),
                        help='Path to input TXT file (default: scripts/txt_importer/images_input.txt)')
    parser.add_argument('--dry-run', action='store_true', help='Do not execute DB inserts; print SQL and params instead')
    parser.add_argument('--nomenclatura', help='Provide nomenclatura to use instead of querying the DB (useful with --dry-run)')
    args = parser.parse_args()
    process_file(args.file, dry_run=args.dry_run, nomenclatura_override=args.nomenclatura)
