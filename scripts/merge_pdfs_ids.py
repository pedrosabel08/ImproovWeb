#!/usr/bin/env python3
"""
merge_pdfs_ids.py
=================
Baixa PDFs específicos (por idarquivo) via SFTP e gera um único PDF unificado.
Chamado pelo PHP criar_planta_pdf.php quando múltiplos arquivos são selecionados.

Uso:
    python merge_pdfs_ids.py --ids "2114,2115,2116" --saida /caminho/saida.pdf

Saída em stdout:
    OK:<numero_de_paginas>      em caso de sucesso
    ERR:<mensagem>              em caso de falha

Dependências (pip):
    pip install pypdf paramiko mysql-connector-python python-dotenv
"""

import argparse
import os
import sys
import tempfile
from io import BytesIO
from pathlib import Path

# ── Carrega .env ─────────────────────────────────────────────────────────────
try:
    from dotenv import load_dotenv
    load_dotenv(Path(__file__).parent.parent / ".env")
except ImportError:
    pass

try:
    import mysql.connector
except ImportError:
    print("ERR:mysql-connector-python não instalado")
    sys.exit(1)

try:
    import paramiko
except ImportError:
    print("ERR:paramiko não instalado")
    sys.exit(1)

try:
    from pypdf import PdfWriter, PdfReader
except ImportError:
    try:
        from PyPDF2 import PdfWriter, PdfReader
    except ImportError:
        print("ERR:pypdf não instalado")
        sys.exit(1)

# ── Config DB / SFTP ─────────────────────────────────────────────────────────
DB_CONFIG = {
    "host":               os.getenv("DB_HOST",     "72.60.137.192"),
    "port":               int(os.getenv("DB_PORT", "3306")),
    "user":               os.getenv("DB_USERNAME", "improov"),
    "password":           os.getenv("DB_PASSWORD", "Impr00v@"),
    "database":           os.getenv("DB_DATABASE", "flowdb"),
    "charset":            "utf8mb4",
    "use_pure":           True,
    "connection_timeout": 10,
}

SFTP_HOST = os.getenv("IMPROOV_SFTP_HOST", "")
SFTP_PORT = int(os.getenv("IMPROOV_SFTP_PORT", "22"))
SFTP_USER = os.getenv("IMPROOV_SFTP_USER", "")
SFTP_PASS = os.getenv("IMPROOV_SFTP_PASS", "")


def buscar_caminhos(ids: list[int]) -> dict[int, str]:
    """Retorna {idarquivo: caminho} para os IDs solicitados."""
    conn   = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)
    placeholders = ",".join(["%s"] * len(ids))
    cursor.execute(
        f"SELECT idarquivo, caminho FROM arquivos WHERE idarquivo IN ({placeholders})",
        ids,
    )
    rows = {r["idarquivo"]: r["caminho"] for r in cursor.fetchall()}
    cursor.close()
    conn.close()
    return rows


def conectar_sftp():
    transport = paramiko.Transport((SFTP_HOST, SFTP_PORT))
    transport.connect(username=SFTP_USER, password=SFTP_PASS)
    sftp = paramiko.SFTPClient.from_transport(transport)
    return sftp, transport


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--ids",  required=True, help='IDs separados por vírgula: "2114,2115,2116"')
    parser.add_argument("--saida", required=True, help="Caminho completo do arquivo PDF de saída")
    args = parser.parse_args()

    ids = [int(x.strip()) for x in args.ids.split(",") if x.strip()]
    if not ids:
        print("ERR:Nenhum ID fornecido")
        sys.exit(1)

    saida = Path(args.saida)
    saida.parent.mkdir(parents=True, exist_ok=True)

    # Buscar caminhos no banco
    try:
        caminhos = buscar_caminhos(ids)
    except Exception as e:
        print(f"ERR:DB:{e}")
        sys.exit(1)

    if not caminhos:
        print("ERR:Nenhum arquivo encontrado no banco para os IDs informados")
        sys.exit(1)

    # Conectar SFTP
    try:
        sftp, transport = conectar_sftp()
    except Exception as e:
        print(f"ERR:SFTP_CONN:{e}")
        sys.exit(1)

    writer      = PdfWriter()
    total_pags  = 0
    erros       = []

    # Baixar e mesclar na ordem dos IDs fornecidos
    with tempfile.TemporaryDirectory() as tmpdir:
        for idarq in ids:
            if idarq not in caminhos:
                erros.append(f"id={idarq}:não encontrado no banco")
                continue

            caminho = caminhos[idarq]
            local   = os.path.join(tmpdir, f"{idarq}.pdf")

            try:
                sftp.get(caminho, local)
            except FileNotFoundError:
                erros.append(f"id={idarq}:arquivo não encontrado no SFTP ({caminho})")
                continue
            except Exception as e:
                erros.append(f"id={idarq}:erro SFTP:{e}")
                continue

            try:
                reader = PdfReader(local)
                for page in reader.pages:
                    writer.add_page(page)
                total_pags += len(reader.pages)
            except Exception as e:
                erros.append(f"id={idarq}:erro ao ler PDF:{e}")
                continue

    try:
        transport.close()
    except Exception:
        pass

    if total_pags == 0:
        print(f"ERR:Nenhuma página processada. Detalhes: {' | '.join(erros)}")
        sys.exit(1)

    try:
        with open(saida, "wb") as f:
            writer.write(f)
    except Exception as e:
        print(f"ERR:Falha ao salvar arquivo de saída:{e}")
        sys.exit(1)

    # Avisos não-fatais para stderr (não afetam o OK no stdout)
    if erros:
        for e in erros:
            print(f"WARN:{e}", file=sys.stderr)

    print(f"OK:{total_pags}")


if __name__ == "__main__":
    main()
