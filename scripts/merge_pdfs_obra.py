#!/usr/bin/env python3
"""
merge_pdfs_obra.py
==================
Baixa todos os PDFs de uma obra via SFTP e gera um único PDF unificado.

Uso básico:
    python merge_pdfs_obra.py --obra_id 42

Com filtro no tipo de imagem e nome de saída personalizado:
    python merge_pdfs_obra.py --obra_id 42 --filtro "Arquitetônico" --saida merged_arq.pdf

Ordenar por nome original:
    python merge_pdfs_obra.py --obra_id 42 --sort nome

Dependências (pip):
    pip install pypdf paramiko mysql-connector-python python-dotenv

Variaveis de ambiente (.env na raiz do projeto):
    DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD, DB_DATABASE
    IMPROOV_SFTP_HOST, IMPROOV_SFTP_PORT, IMPROOV_SFTP_USER, IMPROOV_SFTP_PASS
"""

import argparse
import os
import sys
import tempfile
import re
from pathlib import Path
from typing import Optional, List, Dict, Any

# Carrega a configuracao do .env da raiz do projeto.
from database_config import database_config

# ── Dependências obrigatórias ────────────────────────────────────────────────
try:
    import mysql.connector
except ImportError:
    sys.exit("❌  Instale mysql-connector-python:  pip install mysql-connector-python")

try:
    import paramiko
except ImportError:
    sys.exit("❌  Instale paramiko:  pip install paramiko")

try:
    from pypdf import PdfWriter, PdfReader
except ImportError:
    try:
        from PyPDF2 import PdfWriter, PdfReader  # fallback para PyPDF2
    except ImportError:
        sys.exit("❌  Instale pypdf:  pip install pypdf")


# ─────────────────────────────────────────────────────────────────────────────
# Config
# ─────────────────────────────────────────────────────────────────────────────

DB_CONFIG = {
    **database_config(),
    "use_pure": True,
    "connection_timeout": 10,
}

SFTP_HOST = os.getenv("IMPROOV_SFTP_HOST", "")
SFTP_PORT = int(os.getenv("IMPROOV_SFTP_PORT", "22"))
SFTP_USER = os.getenv("IMPROOV_SFTP_USER", "")
SFTP_PASS = os.getenv("IMPROOV_SFTP_PASS", "")


# ─────────────────────────────────────────────────────────────────────────────
# DB
# ─────────────────────────────────────────────────────────────────────────────

def buscar_pdfs(
    obra_id: int,
    filtro: Optional[str],
    tipo_id: Optional[int],
    categoria_id: Optional[int],
    excluir: Optional[str],
) -> List[Dict[str, Any]]:
    """Retorna lista de arquivos PDF da obra, com filtros opcionais."""
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)

    sql = """
        SELECT
            a.idarquivo,
            a.nome_original,
            a.nome_interno,
            a.caminho,
            a.tipo,
            a.versao,
            a.recebido_em,
            COALESCE(ti.nome, '') AS tipo_imagem
        FROM arquivos a
        LEFT JOIN tipo_imagem ti ON ti.id_tipo_imagem = a.tipo_imagem_id
        WHERE a.obra_id = %s
          AND a.status != 'antigo'
          AND LOWER(a.tipo) = 'pdf'
          AND LOWER(a.nome_original) LIKE '%%.pdf'
    """
    params: List[Any] = [obra_id]

    if filtro:
        sql += " AND ti.nome LIKE %s"
        params.append(f"%{filtro}%")

    if tipo_id is not None:
        sql += " AND ti.id_tipo_imagem = %s"
        params.append(tipo_id)

    if categoria_id is not None:
        sql += " AND a.categoria_id = %s"
        params.append(categoria_id)

    if excluir:
        sql += " AND LOWER(a.nome_interno) NOT LIKE %s"
        params.append(f"%{excluir.lower()}%")

    sql += " ORDER BY a.recebido_em ASC, a.nome_original ASC"

    cursor.execute(sql, params)
    rows = cursor.fetchall()
    cursor.close()
    conn.close()
    return rows


# ─────────────────────────────────────────────────────────────────────────────
# SFTP
# ─────────────────────────────────────────────────────────────────────────────

def conectar_sftp():
    """Retorna (sftp, transport) para poder fechar ao final."""
    if not SFTP_HOST:
        sys.exit("❌  IMPROOV_SFTP_HOST não configurado no .env")
    if not SFTP_USER:
        sys.exit("❌  IMPROOV_SFTP_USER não configurado no .env")

    transport = paramiko.Transport((SFTP_HOST, SFTP_PORT))
    transport.connect(username=SFTP_USER, password=SFTP_PASS)
    sftp = paramiko.SFTPClient.from_transport(transport)
    print(f"✅  Conectado ao SFTP {SFTP_HOST}:{SFTP_PORT}")
    return sftp, transport


def baixar_pdf(sftp: paramiko.SFTPClient, caminho_remoto: str, destino_local: str) -> bool:
    """Baixa um arquivo do SFTP. Retorna True se ok."""
    try:
        sftp.get(caminho_remoto, destino_local)
        return True
    except FileNotFoundError:
        print(f"   ⚠  Arquivo não encontrado no servidor: {caminho_remoto}")
        return False
    except Exception as e:
        print(f"   ⚠  Erro ao baixar {caminho_remoto}: {e}")
        return False


# ─────────────────────────────────────────────────────────────────────────────
# Merge
# ─────────────────────────────────────────────────────────────────────────────

def merge_pdfs(caminhos_locais: List[str], saida: str) -> int:
    """Mescla uma lista de PDFs locais em um único arquivo. Retorna nº de páginas."""
    writer = PdfWriter()
    total_paginas = 0

    for path in caminhos_locais:
        try:
            reader = PdfReader(path)
            for page in reader.pages:
                writer.add_page(page)
                total_paginas += 1
        except Exception as e:
            print(f"   ⚠  Erro ao ler PDF {path}: {e} — pulando.")

    with open(saida, "wb") as f:
        writer.write(f)

    return total_paginas


# ─────────────────────────────────────────────────────────────────────────────
# Main
# ─────────────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(
        description="Mescla todos os PDFs de uma obra em um único arquivo."
    )
    parser.add_argument("--obra_id",  type=int, required=True,
                        help="ID da obra no banco de dados")
    parser.add_argument("--filtro",       type=str, default=None,
                        help='Filtro parcial no nome do tipo de imagem (ex: "Planta Humanizada")')
    parser.add_argument("--tipo_id",      type=int, default=None,
                        help="Filtrar por ti.id_tipo_imagem exato (ex: 6)")
    parser.add_argument("--categoria_id", type=int, default=None,
                        help="Filtrar por a.categoria_id (ex: 1)")
    parser.add_argument("--excluir",      type=str, default=None,
                        help='Excluir arquivos cujo nome_interno contenha este texto (ex: "esquadria")')
    parser.add_argument("--saida",    type=str, default=None,
                        help="Nome do arquivo de saída (padrão: merged_obra_<id>.pdf)")
    parser.add_argument("--sort",     type=str, default="data",
                        choices=["data", "nome"],
                        help="Critério de ordenação: data (padrão) ou nome")
    parser.add_argument("--dry-run",  action="store_true",
                        help="Apenas lista os arquivos sem fazer download ou merge")
    args = parser.parse_args()

    obra_id = args.obra_id
    saida   = args.saida or f"merged_obra_{obra_id}.pdf"

    # ── 1. Busca PDFs no banco ────────────────────────────────────────────────
    filtros_ativos = []
    if args.filtro:       filtros_ativos.append(f'tipo="{args.filtro}"')
    if args.tipo_id:      filtros_ativos.append(f"tipo_id={args.tipo_id}")
    if args.categoria_id: filtros_ativos.append(f"categoria_id={args.categoria_id}")
    if args.excluir:      filtros_ativos.append(f'excluir="{args.excluir}"')
    sufixo = f" [{', '.join(filtros_ativos)}]" if filtros_ativos else ""
    print(f"\n🔍  Buscando PDFs da obra {obra_id}{sufixo}…")

    try:
        arquivos = buscar_pdfs(obra_id, args.filtro, args.tipo_id, args.categoria_id, args.excluir)
    except mysql.connector.Error as e:
        sys.exit(f"❌  Erro de banco de dados: {e}")

    if not arquivos:
        sys.exit("ℹ  Nenhum PDF encontrado para os critérios informados.")

    # Ordena se pedido por nome
    if args.sort == "nome":
        arquivos.sort(key=lambda r: r["nome_original"].lower())

    print(f"\n📄  {len(arquivos)} arquivo(s) encontrado(s):\n")
    for i, arq in enumerate(arquivos, 1):
        print(f"  {i:>3}. [{arq['tipo_imagem'] or 'sem tipo':30s}] "
              f"{arq['nome_original']}  ({arq['caminho']})")

    if args.dry_run:
        print("\n⚠  --dry-run: nenhum arquivo foi baixado ou mesclado.")
        return

    # ── 2. Conecta ao SFTP ───────────────────────────────────────────────────
    sftp, transport = conectar_sftp()

    # ── 3. Baixa os PDFs para pasta temporária ────────────────────────────────
    baixados: List[str] = []
    nao_encontrados = 0

    with tempfile.TemporaryDirectory(prefix="merge_pdfs_") as tmpdir:
        print(f"\n⬇  Baixando para {tmpdir} …\n")

        for i, arq in enumerate(arquivos, 1):
            nome_local = f"{i:04d}_{re.sub(r'[^a-zA-Z0-9._-]', '_', arq['nome_original'])}"
            destino_local = os.path.join(tmpdir, nome_local)

            print(f"  [{i}/{len(arquivos)}] {arq['nome_original']}", end=" … ", flush=True)

            ok = baixar_pdf(sftp, arq["caminho"], destino_local)
            if ok:
                baixados.append(destino_local)
                print("✓")
            else:
                nao_encontrados += 1

        sftp.close()
        transport.close()

        if not baixados:
            sys.exit("❌  Nenhum PDF pôde ser baixado.")

        # ── 4. Mescla ─────────────────────────────────────────────────────────
        saida_abs = os.path.abspath(saida)
        print(f"\n📎  Mesclando {len(baixados)} PDF(s) em '{saida_abs}' …")

        total = merge_pdfs(baixados, saida_abs)

        print(f"\n✅  Concluído!")
        print(f"    Arquivos processados : {len(baixados)}")
        if nao_encontrados:
            print(f"    Não encontrados      : {nao_encontrados}")
        print(f"    Total de páginas     : {total}")
        print(f"    Arquivo de saída     : {saida_abs}")
        tamanho_mb = os.path.getsize(saida_abs) / 1024 / 1024
        print(f"    Tamanho              : {tamanho_mb:.2f} MB")


if __name__ == "__main__":
    main()
