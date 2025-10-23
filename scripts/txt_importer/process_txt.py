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

# Configuration - prefer environment variables for safety in different environments
DB_CONFIG = {
    'host': os.getenv('IMPORT_DB_HOST', 'mysql.improov.com.br'),
    'user': os.getenv('IMPORT_DB_USER', 'improov'),
    'password': os.getenv('IMPORT_DB_PASSWORD', 'Impr00v'),
    'database': os.getenv('IMPORT_DB_NAME', 'improov'),
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

def insert_imagem(conn, cliente_id, obra_id, imagem_nome, recebimento_arquivos='', data_inicio='', prazo='', tipo_imagem='', dry_run=False):
    sql = ("INSERT INTO imagens_cliente_obra (cliente_id, obra_id, imagem_nome, recebimento_arquivos, data_inicio, prazo, tipo_imagem) "
           "VALUES (%s, %s, %s, %s, %s, %s, %s)")
    params = (cliente_id, obra_id, imagem_nome, recebimento_arquivos, data_inicio, prazo, tipo_imagem)
    if dry_run:
        print("[DRY-RUN] SQL:", sql)
        print("[DRY-RUN] Params:", params)
        return
    cursor = conn.cursor()
    cursor.execute(sql, params)
    conn.commit()
    cursor.close()


def process_file(path, dry_run=False):
    if not os.path.isfile(path):
        print(f"Arquivo não encontrado: {path}")
        return

    with open(path, 'r', encoding=INPUT_ENCODING) as f:
        raw_lines = [line.rstrip('\n') for line in f]

    # Remove empty lines and comment lines (starting with #)
    lines = [line.strip() for line in raw_lines if line.strip() and not line.strip().startswith('#')]

    if not lines:
        print("Arquivo vazio")
        return

    # Two supported formats:
    # 1) First line: cliente_id,obra_id
    #    Following lines: image names (like "1. Fachada")
    # 2) Each line: cliente_id,obra_id,imagem_nome

    # Detect format
    first_parts = re.split(r"[\t,;]+", lines[0])
    use_header = False
    if len(first_parts) >= 2 and all(p.isdigit() for p in first_parts[:2]):
        # ambiguous: could be format 2 if line has 3 parts. We'll check length.
        if len(first_parts) == 2:
            use_header = True
        else:
            use_header = False

    conn = None
    if not dry_run:
        try:
            conn = mysql.connector.connect(**DB_CONFIG)
        except mysql.connector.Error as err:
            if err.errno == errorcode.ER_ACCESS_DENIED_ERROR:
                print("Erro de autenticação com o banco de dados")
            else:
                print(f"Erro ao conectar ao banco: {err}")
            return

    if use_header:
        cliente_id = int(first_parts[0])
        obra_id = int(first_parts[1])
        nomenclatura = get_nomenclatura(conn, obra_id)
        if not nomenclatura:
            print(f"Nenhuma nomenclatura encontrada para obra_id={obra_id}")
            conn.close()
            return

        for line in lines[1:]:
            # treat the whole line as imagem_nome
            imagem_nome = line
            imagem_nome = format_name(imagem_nome, nomenclatura)
            try:
                insert_imagem(conn, cliente_id, obra_id, imagem_nome, dry_run=dry_run)
                print(f"Inserida: {imagem_nome}")
            except Exception as e:
                print(f"Erro ao inserir {imagem_nome}: {e}")
    else:
        # each line is cliente_id,obra_id,imagem_nome
        for line in lines:
            parts = re.split(r"[\t,;]+", line, maxsplit=2)
            if len(parts) < 3:
                print(f"Linha inválida (esperado 3 colunas): {line}")
                continue
            cliente_id = int(parts[0])
            obra_id = int(parts[1])
            imagem_nome_raw = parts[2]
            nomenclatura = get_nomenclatura(conn, obra_id)
            if not nomenclatura:
                print(f"Nenhuma nomenclatura para obra_id={obra_id} (linha: {line})")
                continue
            imagem_nome = format_name(imagem_nome_raw, nomenclatura)
            try:
                insert_imagem(conn, cliente_id, obra_id, imagem_nome, dry_run=dry_run)
                print(f"Inserida: {imagem_nome}")
            except Exception as e:
                print(f"Erro ao inserir {imagem_nome}: {e}")

    if conn:
        conn.close()


def format_name(imagem_nome, nomenclatura):
    """If imagem_nome starts with number+dot (e.g. "1. Fachada"), return "1.NOME Rest" else "NOME Rest"""
    imagem_nome = imagem_nome.strip()
    m = re.match(r'^(\d+\.)\s*(.*)$', imagem_nome)
    if m:
        prefix = m.group(1)
        rest = m.group(2)
        return f"{prefix}{nomenclatura} {rest}"
    else:
        return f"{nomenclatura} {imagem_nome}"


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Import images from a TXT file into imagens_cliente_obra')
    parser.add_argument('file', nargs='?', default=os.path.join(os.path.dirname(__file__), 'images_input.txt'),
                        help='Path to input TXT file (default: scripts/txt_importer/images_input.txt)')
    parser.add_argument('--dry-run', action='store_true', help='Do not execute DB inserts; print SQL and params instead')
    args = parser.parse_args()
    process_file(args.file, dry_run=args.dry_run)
