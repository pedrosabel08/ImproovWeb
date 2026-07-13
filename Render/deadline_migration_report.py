from __future__ import annotations

import argparse
import json
import logging

import pymysql
from pymysql.cursors import DictCursor

from deadline_client import DeadlineClient
from deadline_config import SETTINGS


def scalar(cursor, sql: str, params=()) -> int:
    cursor.execute(sql, params)
    row = cursor.fetchone()
    return int(next(iter(row.values())))


def table_exists(cursor, name: str) -> bool:
    return (
        scalar(
            cursor,
            """
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s
        """,
            (name,),
        )
        == 1
    )


def build_report(check_deadline: bool = False) -> dict:
    connection = pymysql.connect(
        host=SETTINGS.db_host,
        user=SETTINGS.db_user,
        password=SETTINGS.db_pass,
        database=SETTINGS.db_name,
        charset="utf8mb4",
        cursorclass=DictCursor,
        autocommit=True,
        connect_timeout=15,
        read_timeout=30,
    )
    report = {"mode": "READ_ONLY", "database": SETTINGS.db_name}
    diagnostic_rows = []
    duplicate_ids = set()
    try:
        with connection.cursor() as cursor:
            cursor.execute("SELECT VERSION() AS version")
            report["mysql_version"] = cursor.fetchone()["version"]
            report["render_alta"] = {
                "total": scalar(cursor, "SELECT COUNT(*) FROM render_alta"),
                "without_attempt_keys": scalar(
                    cursor,
                    "SELECT COUNT(*) FROM render_alta WHERE imagem_id IS NULL OR status_id IS NULL",
                ),
                "with_job_id": scalar(
                    cursor,
                    """
                    SELECT COUNT(*) FROM render_alta
                    WHERE NULLIF(TRIM(deadline_job_id), '') IS NOT NULL
                    """,
                ),
                "invalid_job_id": scalar(
                    cursor,
                    """
                    SELECT COUNT(*) FROM render_alta
                    WHERE NULLIF(TRIM(deadline_job_id), '') IS NOT NULL
                      AND TRIM(deadline_job_id) NOT REGEXP '^[0-9A-Fa-f]{24}$'
                    """,
                ),
            }
            cursor.execute("""
                SELECT deadline_job_id, COUNT(*) AS total
                FROM render_alta
                WHERE NULLIF(TRIM(deadline_job_id), '') IS NOT NULL
                GROUP BY deadline_job_id
                HAVING COUNT(*) > 1
                ORDER BY total DESC, deadline_job_id
                """)
            duplicate_rows = list(cursor.fetchall())
            report["duplicate_legacy_job_ids"] = duplicate_rows
            duplicate_ids = {row["deadline_job_id"] for row in duplicate_rows}
            cursor.execute("""
                SELECT idrender_alta AS render_id, imagem_id, status_id, status,
                       deadline_job_id, submitted
                FROM render_alta
                WHERE NULLIF(TRIM(deadline_job_id), '') IS NOT NULL
                ORDER BY idrender_alta
                """)
            diagnostic_rows = list(cursor.fetchall())
            terminal_statuses = {
                "Aprovado", "Finalizado", "Reprovado", "Refazendo", "Arquivado"
            }
            report["terminal_renders_still_with_job_id"] = [
                row for row in diagnostic_rows if row["status"] in terminal_statuses
            ]

            if table_exists(cursor, "render_tentativas"):
                report["render_tentativas"] = {
                    "total": scalar(cursor, "SELECT COUNT(*) FROM render_tentativas"),
                    "active": scalar(
                        cursor, "SELECT COUNT(*) FROM render_tentativas WHERE ativa = 1"
                    ),
                    "with_job_id": scalar(
                        cursor,
                        "SELECT COUNT(*) FROM render_tentativas WHERE deadline_job_id IS NOT NULL",
                    ),
                }
                cursor.execute("""
                    SELECT deadline_job_id, COUNT(*) AS total
                    FROM render_tentativas
                    WHERE deadline_job_id IS NOT NULL
                    GROUP BY deadline_job_id
                    HAVING COUNT(*) > 1
                    ORDER BY total DESC, deadline_job_id
                    """)
                report["duplicate_attempt_job_ids"] = list(cursor.fetchall())
                cursor.execute("""
                    SELECT render_id, COUNT(*) AS total
                    FROM render_tentativas
                    WHERE ativa = 1
                    GROUP BY render_id
                    HAVING COUNT(*) > 1
                    """)
                report["renders_with_multiple_active_attempts"] = list(
                    cursor.fetchall()
                )
                cursor.execute("""
                    SELECT render_id, deadline_job_id, id AS tentativa_id,
                           numero_tentativa, status AS tentativa_status
                    FROM render_tentativas
                    WHERE deadline_job_id IS NOT NULL
                    """)
                attempts_by_render = {
                    (int(row["render_id"]), row["deadline_job_id"]): row
                    for row in cursor.fetchall()
                }
                for row in diagnostic_rows:
                    attempt = attempts_by_render.get(
                        (int(row["render_id"]), row["deadline_job_id"])
                    )
                    row["tentativa_id"] = attempt["tentativa_id"] if attempt else None
                    row["numero_tentativa"] = attempt["numero_tentativa"] if attempt else None
                    row["tentativa_status"] = attempt["tentativa_status"] if attempt else None
            else:
                report["render_tentativas"] = "TABLE_NOT_INSTALLED"

            if table_exists(cursor, "deadline_comandos"):
                cursor.execute("""
                    SELECT status, COUNT(*) AS total
                    FROM deadline_comandos
                    GROUP BY status ORDER BY status
                    """)
                report["deadline_commands"] = list(cursor.fetchall())
            else:
                report["deadline_commands"] = "TABLE_NOT_INSTALLED"
    finally:
        connection.close()

    if check_deadline:
        result, deadline_ids = DeadlineClient(
            SETTINGS.deadline_command, SETTINGS.command_timeout
        ).list_job_ids()
        report["deadline_read"] = {
            "success": result.success,
            "job_count": len(deadline_ids),
            "error": "" if result.success else result.output[-2000:],
        }
        deadline_id_set = set(deadline_ids) if result.success else None
    else:
        deadline_id_set = None

    terminal_statuses = {
        "Aprovado", "Finalizado", "Reprovado", "Refazendo", "Arquivado"
    }
    operational_statuses = {
        "Nao iniciado", "Não iniciado", "Em andamento",
        "Em aprovacao", "Em aprovação", "Erro",
    }
    for row in diagnostic_rows:
        job_id = row["deadline_job_id"]
        exists = None if deadline_id_set is None else job_id in deadline_id_set
        row["deadline_exists"] = exists
        if job_id in duplicate_ids:
            row["categoria"] = "JOB AMBIGUO"
            row["acao_sugerida"] = "ANALISE_MANUAL_NAO_VINCULAR"
        elif exists is False:
            row["categoria"] = "JOB NAO EXISTE"
            row["acao_sugerida"] = "RECONCILIAR_SEM_APAGAR_HISTORICO"
        elif row["status"] in terminal_statuses:
            row["categoria"] = "TERMINAL AINDA VINCULADO"
            row["acao_sugerida"] = "ANALISE_MANUAL_NAO_EXCLUIR_EM_MASSA"
        elif row["status"] in operational_statuses and exists is not False:
            row["categoria"] = (
                "ATIVO E COERENTE" if exists is True else "SEM DADOS SUFICIENTES"
            )
            row["acao_sugerida"] = (
                "MIGRAR_E_SINCRONIZAR" if exists is True else "CONSULTAR_DEADLINE"
            )
        else:
            row["categoria"] = "SEM DADOS SUFICIENTES"
            row["acao_sugerida"] = "ANALISE_MANUAL"
    report["job_diagnostic"] = diagnostic_rows
    report["category_counts"] = {}
    for row in diagnostic_rows:
        category = row["categoria"]
        report["category_counts"][category] = (
            report["category_counts"].get(category, 0) + 1
        )
    return report


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Relatorio somente de leitura antes/depois da migration Flow x Deadline."
    )
    parser.add_argument(
        "--check-deadline",
        action="store_true",
        help="Tambem executa -GetJobs True; nunca executa DeleteJob.",
    )
    args = parser.parse_args()
    logging.disable(logging.CRITICAL)
    print(
        json.dumps(
            build_report(args.check_deadline), ensure_ascii=False, indent=2, default=str
        )
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
