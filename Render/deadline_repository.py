from __future__ import annotations

import json
import re
from datetime import datetime, timedelta

from deadline_domain import (
    AGUARDANDO_JOB,
    ENCERRADA,
    EXCLUSAO_PENDENTE,
    OPERATIONAL_STATES,
    transition_allowed,
    valid_job_id,
)

QUEUE_PENDING = "PENDENTE"
QUEUE_RUNNING = "PROCESSANDO"
QUEUE_DONE = "CONCLUIDO"
QUEUE_FAILED = "ERRO"


def build_observation_plan(
    previous_status: str,
    target_status: str,
    previous_fingerprint: str | None,
    fingerprint: str,
) -> dict:
    """Decide, sem efeitos colaterais, se a observacao exige processamento."""
    state_changed = previous_status != target_status
    first_observation = previous_fingerprint is None
    snapshot_changed = first_observation or previous_fingerprint != fingerprint
    process_preview = target_status == "EM_APROVACAO" and (
        state_changed or snapshot_changed
    )
    return {
        "state_changed": state_changed,
        "first_observation": first_observation,
        "snapshot_changed": snapshot_changed,
        "process": state_changed or process_preview,
        "process_preview": process_preview,
        "process_post": state_changed and target_status == "EM_APROVACAO",
        "send_notifications": state_changed,
        "state_event_key": f"{previous_status}->{target_status}",
    }


class DeadlineRepository:
    def __init__(self, database, logger):
        self.database = database
        self.logger = logger

    def require_schema(self) -> None:
        with self.database.transaction() as cursor:
            cursor.execute("""
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME IN (
                      'render_tentativas', 'deadline_comandos',
                      'deadline_workers', 'render_tentativa_eventos'
                  )
                """)
            tables = {row["TABLE_NAME"] for row in cursor.fetchall()}
            cursor.execute("""
                SELECT COUNT(*) AS total
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'render_alta'
                  AND COLUMN_NAME = 'excluido_em'
                """)
            has_archive_column = int(cursor.fetchone()["total"]) == 1
            cursor.execute("""
                SELECT TABLE_NAME, COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME IN (
                      'render_tentativas', 'deadline_comandos',
                      'deadline_workers', 'render_tentativa_eventos'
                  )
                """)
            columns = {
                (row["TABLE_NAME"], row["COLUMN_NAME"]) for row in cursor.fetchall()
            }
            cursor.execute("""
                SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND INDEX_NAME IN (
                      'uniq_render_tentativa_ativa', 'uniq_deadline_job_id',
                      'uniq_comando_job_tipo', 'uniq_tentativa_evento'
                  )
                """)
            indexes = {
                (row["TABLE_NAME"], row["INDEX_NAME"], int(row["NON_UNIQUE"]))
                for row in cursor.fetchall()
            }
        expected = {
            "render_tentativas",
            "deadline_comandos",
            "deadline_workers",
            "render_tentativa_eventos",
        }
        missing = sorted(expected - tables)
        required_columns = {
            ("render_tentativas", "ativa_render_id"),
            ("render_tentativas", "deadline_job_id"),
            ("deadline_comandos", "bloqueado_por"),
            ("deadline_comandos", "disponivel_em"),
            ("deadline_workers", "ultimo_heartbeat"),
            ("render_tentativa_eventos", "chave"),
        }
        required_indexes = {
            ("render_tentativas", "uniq_render_tentativa_ativa", 0),
            ("render_tentativas", "uniq_deadline_job_id", 0),
            ("deadline_comandos", "uniq_comando_job_tipo", 0),
            ("render_tentativa_eventos", "uniq_tentativa_evento", 0),
        }
        missing_columns = sorted(required_columns - columns)
        missing_indexes = sorted(required_indexes - indexes)
        if missing or missing_columns or missing_indexes or not has_archive_column:
            details = list(missing)
            details.extend(f"{table}.{column}" for table, column in missing_columns)
            details.extend(f"{table}.{index}" for table, index, _ in missing_indexes)
            if not has_archive_column:
                details.append("render_alta.excluido_em")
            raise RuntimeError(
                "Migration do worker Deadline ausente ou incompleta: "
                + ", ".join(details)
            )

    def heartbeat(
        self, worker_id: str, hostname: str, pid: int, status: str, details: str = ""
    ) -> None:
        with self.database.transaction() as cursor:
            cursor.execute(
                """
                INSERT INTO deadline_workers
                    (worker_id, hostname, pid, versao, iniciado_em,
                     ultimo_heartbeat, status, detalhes)
                VALUES (%s, %s, %s, %s, NOW(), NOW(), %s, %s)
                ON DUPLICATE KEY UPDATE
                    hostname = VALUES(hostname),
                    pid = VALUES(pid),
                    versao = VALUES(versao),
                    iniciado_em = IF(
                        deadline_workers.status IN ('PARADO', 'ERRO'),
                        VALUES(iniciado_em),
                        deadline_workers.iniciado_em
                    ),
                    ultimo_heartbeat = NOW(),
                    status = VALUES(status),
                    detalhes = VALUES(detalhes)
                """,
                (worker_id, hostname, pid, "2.0", status, details[:65535]),
            )

    def reserve_command(self, worker_id: str) -> dict | None:
        with self.database.transaction() as cursor:
            cursor.execute(
                """
                SELECT *
                FROM deadline_comandos
                WHERE status = %s
                  AND disponivel_em <= NOW()
                  AND tentativas_execucao < max_tentativas
                ORDER BY prioridade ASC, id ASC
                LIMIT 1
                FOR UPDATE SKIP LOCKED
                """,
                (QUEUE_PENDING,),
            )
            command = cursor.fetchone()
            if not command:
                return None
            cursor.execute(
                """
                UPDATE deadline_comandos
                SET status = %s,
                    bloqueado_por = %s,
                    bloqueado_em = NOW(),
                    iniciado_em = COALESCE(iniciado_em, NOW()),
                    tentativas_execucao = tentativas_execucao + 1,
                    ultimo_erro = NULL
                WHERE id = %s AND status = %s
                """,
                (QUEUE_RUNNING, worker_id, command["id"], QUEUE_PENDING),
            )
            if cursor.rowcount != 1:
                return None
            command["status"] = QUEUE_RUNNING
            command["bloqueado_por"] = worker_id
            command["tentativas_execucao"] = int(command["tentativas_execucao"]) + 1
            return command

    def finish_delete(self, command_id: int, worker_id: str, output: str) -> bool:
        with self.database.transaction() as cursor:
            cursor.execute(
                """
                SELECT * FROM deadline_comandos WHERE id = %s
                """,
                (command_id,),
            )
            command = cursor.fetchone()
            if (
                not command
                or command["status"] != QUEUE_RUNNING
                or command["bloqueado_por"] != worker_id
            ):
                return False
            if command["render_id"]:
                cursor.execute(
                    "SELECT idrender_alta FROM render_alta WHERE idrender_alta = %s FOR UPDATE",
                    (command["render_id"],),
                )
                cursor.fetchone()
            if command["tentativa_id"]:
                cursor.execute(
                    "SELECT id FROM render_tentativas WHERE id = %s FOR UPDATE",
                    (command["tentativa_id"],),
                )
                cursor.fetchone()
            cursor.execute(
                """
                SELECT * FROM deadline_comandos
                WHERE id = %s AND status = %s AND bloqueado_por = %s
                FOR UPDATE
                """,
                (command_id, QUEUE_RUNNING, worker_id),
            )
            command = cursor.fetchone()
            if not command:
                return False
            cursor.execute(
                """
                UPDATE deadline_comandos
                SET status = %s, concluido_em = NOW(), atualizado_em = NOW(),
                    bloqueado_por = NULL, bloqueado_em = NULL, ultimo_erro = NULL
                WHERE id = %s
                """,
                (QUEUE_DONE, command_id),
            )
            if command["tentativa_id"]:
                cursor.execute(
                    """
                    UPDATE render_tentativas
                    SET status = %s, ativa = 0, encerrado_em = COALESCE(encerrado_em, NOW()),
                        motivo_encerramento = COALESCE(motivo_encerramento, 'DELETE_JOB_CONCLUIDO')
                    WHERE id = %s AND status = %s
                    """,
                    (ENCERRADA, command["tentativa_id"], EXCLUSAO_PENDENTE),
                )
            if command["render_id"]:
                cursor.execute(
                    """
                    UPDATE render_alta
                    SET deadline_job_id = NULL
                    WHERE idrender_alta = %s AND deadline_job_id = %s
                    """,
                    (command["render_id"], command["deadline_job_id"]),
                )
            self._insert_event_dict(
                cursor,
                command.get("tentativa_id"),
                "COMANDO",
                f"DELETE_JOB:{command_id}:CONCLUIDO",
                {"output": (output or "")[-2000:]},
            )
            return True

    def reschedule_command(
        self,
        command_id: int,
        worker_id: str,
        error: str,
        delay_seconds: int,
        dry_run: bool = False,
    ) -> bool:
        with self.database.transaction() as cursor:
            cursor.execute(
                """
                SELECT id, tentativa_id, tentativas_execucao, max_tentativas
                FROM deadline_comandos
                WHERE id = %s AND status = %s AND bloqueado_por = %s
                FOR UPDATE
                """,
                (command_id, QUEUE_RUNNING, worker_id),
            )
            command = cursor.fetchone()
            if not command:
                return False
            exhausted = int(command["tentativas_execucao"]) >= int(
                command["max_tentativas"]
            )
            target = QUEUE_FAILED if exhausted and not dry_run else QUEUE_PENDING
            available = datetime.now() + timedelta(seconds=max(1, delay_seconds))
            cursor.execute(
                """
                UPDATE deadline_comandos
                SET status = %s, disponivel_em = %s,
                    bloqueado_por = NULL, bloqueado_em = NULL,
                    ultimo_erro = %s,
                    tentativas_execucao = IF(%s = 1, GREATEST(tentativas_execucao - 1, 0), tentativas_execucao)
                WHERE id = %s
                """,
                (target, available, error[-65535:], 1 if dry_run else 0, command_id),
            )
            self._insert_event_dict(
                cursor,
                command.get("tentativa_id"),
                "COMANDO",
                f"DELETE_JOB:{command_id}:{target}:{command['tentativas_execucao']}",
                {"erro": error[-2000:], "dry_run": dry_run},
            )
            return True

    def recover_stale_commands(self, lock_timeout_seconds: int) -> int:
        cutoff = datetime.now() - timedelta(seconds=lock_timeout_seconds)
        with self.database.transaction() as cursor:
            cursor.execute(
                """
                UPDATE deadline_comandos
                SET status = %s, bloqueado_por = NULL, bloqueado_em = NULL,
                    disponivel_em = NOW(),
                    tentativas_execucao = GREATEST(tentativas_execucao - 1, 0),
                    ultimo_erro = CONCAT_WS('\n', ultimo_erro, 'LOCK_EXPIRADO_RECUPERADO')
                WHERE status = %s AND bloqueado_em < %s
                """,
                (QUEUE_PENDING, QUEUE_RUNNING, cutoff),
            )
            return cursor.rowcount

    def active_attempts(self) -> list[dict]:
        placeholders = ",".join(["%s"] * len(OPERATIONAL_STATES))
        with self.database.transaction() as cursor:
            cursor.execute(
                f"""
                SELECT rt.id AS id,
                       rt.render_id AS render_id,
                       rt.imagem_id AS imagem_id,
                       rt.status_id AS status_id,
                       rt.numero_tentativa AS numero_tentativa,
                       rt.deadline_job_id AS deadline_job_id,
                       rt.deadline_job_name AS deadline_job_name,
                       rt.status AS status,
                       rt.ativa AS ativa,
                       rt.motivo_encerramento AS motivo_encerramento,
                       rt.criado_em AS tentativa_criado_em,
                       rt.vinculado_em AS vinculado_em,
                       rt.iniciado_em AS iniciado_em,
                       rt.concluido_em AS concluido_em,
                       rt.reprovado_em AS reprovado_em,
                       rt.encerrado_em AS encerrado_em,
                       rt.atualizado_em AS tentativa_atualizado_em,
                       r.status AS flow_status,
                       r.deadline_job_id AS cache_job_id,
                       r.excluido_em AS render_excluido_em,
                       r.errors AS flow_errors
                FROM render_tentativas rt
                JOIN render_alta r ON r.idrender_alta = rt.render_id
                WHERE rt.ativa = 1
                  AND rt.deadline_job_id IS NOT NULL
                  AND rt.status IN ({placeholders})
                  AND r.excluido_em IS NULL
                ORDER BY rt.id
                """,
                tuple(sorted(OPERATIONAL_STATES)),
            )
            return list(cursor.fetchall())

    def known_job_ids(self) -> set[str]:
        with self.database.transaction() as cursor:
            cursor.execute(
                "SELECT deadline_job_id FROM render_tentativas WHERE deadline_job_id IS NOT NULL"
            )
            return {str(row["deadline_job_id"]).strip() for row in cursor.fetchall()}

    @staticmethod
    def _name_prefix(job_name: str) -> str:
        match = re.match(r"^(\d+\.\s*[A-Za-z0-9]+_[A-Za-z0-9]+)", job_name or "")
        return match.group(1).replace(" ", "") if match else ""

    def discovery_candidates(self, job_id: str, job_name: str) -> list[dict]:
        if not valid_job_id(job_id) or not job_name:
            return []
        with self.database.transaction() as cursor:
            cursor.execute(
                """
                SELECT idimagens_cliente_obra
                FROM imagens_cliente_obra
                WHERE imagem_nome = %s
                """,
                (job_name,),
            )
            image_ids = [
                int(row["idimagens_cliente_obra"]) for row in cursor.fetchall()
            ]
            if not image_ids:
                prefix = self._name_prefix(job_name)
                if prefix:
                    cursor.execute(
                        """
                        SELECT idimagens_cliente_obra
                        FROM imagens_cliente_obra
                        WHERE REPLACE(imagem_nome, ' ', '') LIKE %s
                        """,
                        (prefix + "%",),
                    )
                    image_ids = [
                        int(row["idimagens_cliente_obra"]) for row in cursor.fetchall()
                    ]
            if not image_ids:
                return []
            placeholders = ",".join(["%s"] * len(image_ids))
            cursor.execute(
                f"""
                SELECT rt.id AS tentativa_id,
                       rt.render_id AS render_id,
                       rt.imagem_id AS imagem_id,
                       rt.status_id AS status_id,
                       rt.numero_tentativa, rt.criado_em AS tentativa_criada_em,
                       r.status AS flow_status
                FROM render_tentativas rt
                JOIN render_alta r ON r.idrender_alta = rt.render_id
                JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = rt.imagem_id
                WHERE rt.imagem_id IN ({placeholders})
                  AND rt.ativa = 1
                  AND rt.status = %s
                  AND rt.deadline_job_id IS NULL
                  AND r.excluido_em IS NULL
                  AND r.status_id = i.status_id
                """,
                (*image_ids, AGUARDANDO_JOB),
            )
            return list(cursor.fetchall())

    def bind_job(self, attempt_id: int, job_id: str, job_name: str) -> bool:
        if not valid_job_id(job_id):
            return False
        with self.database.transaction() as cursor:
            cursor.execute(
                "SELECT render_id FROM render_tentativas WHERE id = %s",
                (attempt_id,),
            )
            reference = cursor.fetchone()
            if not reference:
                return False
            cursor.execute(
                "SELECT idrender_alta FROM render_alta WHERE idrender_alta = %s FOR UPDATE",
                (reference["render_id"],),
            )
            if not cursor.fetchone():
                return False
            cursor.execute(
                "SELECT * FROM render_tentativas WHERE id = %s FOR UPDATE",
                (attempt_id,),
            )
            attempt = cursor.fetchone()
            if (
                not attempt
                or int(attempt["ativa"]) != 1
                or attempt["status"] != AGUARDANDO_JOB
                or attempt["deadline_job_id"]
            ):
                return False
            cursor.execute(
                "SELECT id FROM render_tentativas WHERE deadline_job_id = %s LIMIT 1 FOR UPDATE",
                (job_id,),
            )
            if cursor.fetchone():
                return False
            cursor.execute(
                """
                UPDATE render_tentativas
                SET deadline_job_id = %s, deadline_job_name = %s,
                    status = 'VINCULADA', vinculado_em = NOW()
                WHERE id = %s
                """,
                (job_id, job_name[:255], attempt_id),
            )
            cursor.execute(
                """
                UPDATE render_alta
                SET deadline_job_id = %s
                WHERE idrender_alta = %s
                """,
                (job_id, attempt["render_id"]),
            )
            self._insert_event_dict(
                cursor,
                attempt_id,
                "VINCULO",
                job_id,
                {"job_name": job_name},
            )
            return True

    def claim_event_tuple(
        self, cursor, attempt_id: int, event_type: str, key: str, data: dict
    ) -> bool:
        cursor.execute(
            """
            INSERT IGNORE INTO render_tentativa_eventos
                (tentativa_id, tipo, chave, dados_json)
            VALUES (%s, %s, %s, %s)
            """,
            (attempt_id, event_type, key[:120], json.dumps(data, ensure_ascii=False)),
        )
        return cursor.rowcount == 1

    @staticmethod
    def release_event_tuple(cursor, attempt_id: int, event_type: str, key: str) -> None:
        """Libera uma reserva somente quando a operacao externa falhou."""
        cursor.execute(
            """
            DELETE FROM render_tentativa_eventos
            WHERE tentativa_id = %s AND tipo = %s AND chave = %s
            """,
            (attempt_id, event_type, key[:120]),
        )

    def observe_deadline_state(
        self, attempt: dict, target: str, fingerprint: str
    ) -> dict | None:
        """Persiste a observacao barata e devolve o plano de processamento.

        A tabela de eventos guarda apenas o ultimo snapshot da consulta Deadline.
        Uma observacao identica nao escreve no banco e nao dispara trabalho pesado.
        """
        with self.database.transaction() as cursor:
            cursor.execute(
                """
                SELECT rt.id AS tentativa_id,
                       rt.render_id AS render_id,
                       rt.status AS tentativa_status,
                       rt.deadline_job_id AS deadline_job_id,
                       rt.ativa AS tentativa_ativa
                FROM render_tentativas rt
                WHERE rt.id = %s
                  AND rt.render_id = %s
                  AND rt.deadline_job_id = %s
                  AND rt.ativa = 1
                FOR UPDATE
                """,
                (attempt["id"], attempt["render_id"], attempt["deadline_job_id"]),
            )
            current = cursor.fetchone()
            if not current:
                return None
            previous_status = str(current["tentativa_status"])
            if not transition_allowed(previous_status, target):
                return {
                    "ignored": True,
                    "previous_status": previous_status,
                    "target": target,
                }

            cursor.execute(
                """
                SELECT dados_json
                FROM render_tentativa_eventos
                WHERE tentativa_id = %s
                  AND tipo = 'OBSERVACAO_DEADLINE'
                  AND chave = 'ATUAL'
                FOR UPDATE
                """,
                (current["tentativa_id"],),
            )
            snapshot_row = cursor.fetchone()
            previous_fingerprint = None
            if snapshot_row and snapshot_row.get("dados_json"):
                try:
                    previous_fingerprint = json.loads(snapshot_row["dados_json"]).get(
                        "fingerprint"
                    )
                except (TypeError, ValueError, json.JSONDecodeError):
                    previous_fingerprint = None

            plan = build_observation_plan(
                previous_status, target, previous_fingerprint, fingerprint
            )
            if plan["state_changed"]:
                cursor.execute(
                    """
                    UPDATE render_tentativas
                    SET status = %s,
                        iniciado_em = IF(%s = 'EM_ANDAMENTO', COALESCE(iniciado_em, NOW()), iniciado_em),
                        concluido_em = IF(%s = 'EM_APROVACAO', COALESCE(concluido_em, NOW()), concluido_em)
                    WHERE id = %s
                    """,
                    (target, target, target, current["tentativa_id"]),
                )
            if plan["snapshot_changed"] or plan["state_changed"]:
                payload = json.dumps(
                    {"fingerprint": fingerprint, "state": target},
                    ensure_ascii=False,
                )
                cursor.execute(
                    """
                    INSERT INTO render_tentativa_eventos
                        (tentativa_id, tipo, chave, dados_json)
                    VALUES (%s, 'OBSERVACAO_DEADLINE', 'ATUAL', %s)
                    ON DUPLICATE KEY UPDATE dados_json = VALUES(dados_json)
                    """,
                    (current["tentativa_id"], payload),
                )
            plan.update(
                {
                    "ignored": False,
                    "attempt_id": current["tentativa_id"],
                    "previous_status": previous_status,
                    "target": target,
                }
            )
            return plan

    def lock_attempt_context_tuple(self, cursor, attempt: dict) -> bool:
        """Usa a mesma ordem de locks do PHP: render e depois tentativa."""
        cursor.execute(
            "SELECT idrender_alta FROM render_alta WHERE idrender_alta = %s FOR UPDATE",
            (attempt["render_id"],),
        )
        if not cursor.fetchone():
            return False
        cursor.execute(
            """
            SELECT id
            FROM render_tentativas
            WHERE id = %s AND render_id = %s AND deadline_job_id = %s AND ativa = 1
            FOR UPDATE
            """,
            (attempt["id"], attempt["render_id"], attempt["deadline_job_id"]),
        )
        return cursor.fetchone() is not None

    def apply_observed_state_tuple(self, cursor, attempt: dict, target: str) -> bool:
        cursor.execute(
            """
            SELECT status, ativa, deadline_job_id
            FROM render_tentativas
            WHERE id = %s
            FOR UPDATE
            """,
            (attempt["id"],),
        )
        row = cursor.fetchone()
        if (
            not row
            or int(row[1]) != 1
            or str(row[2]) != str(attempt["deadline_job_id"])
        ):
            return False
        current = str(row[0])
        if not transition_allowed(current, target):
            self.logger.warning(
                "state transition refused",
                extra={
                    "routine": "sync",
                    "attempt_id": attempt["id"],
                    "current": current,
                    "target": target,
                },
            )
            return False
        cursor.execute(
            """
            UPDATE render_tentativas
            SET status = %s,
                iniciado_em = IF(%s = 'EM_ANDAMENTO', COALESCE(iniciado_em, NOW()), iniciado_em),
                concluido_em = IF(%s = 'EM_APROVACAO', COALESCE(concluido_em, NOW()), concluido_em)
            WHERE id = %s
            """,
            (target, target, target, attempt["id"]),
        )
        return True

    def reconcile(self) -> dict:
        results = {"cache_fixed": 0, "commands_created": 0, "multiple_active": []}
        with self.database.transaction() as cursor:
            cursor.execute("""
                SELECT render_id, COUNT(*) AS total
                FROM render_tentativas
                WHERE ativa = 1
                GROUP BY render_id
                HAVING COUNT(*) > 1
                """)
            results["multiple_active"] = [dict(row) for row in cursor.fetchall()]

            cursor.execute("""
                UPDATE render_alta r
                JOIN render_tentativas rt
                  ON rt.render_id = r.idrender_alta AND rt.ativa = 1
                SET r.deadline_job_id = rt.deadline_job_id
                WHERE rt.deadline_job_id IS NOT NULL
                  AND NOT (r.deadline_job_id <=> rt.deadline_job_id)
                """)
            results["cache_fixed"] = cursor.rowcount

            cursor.execute("""
                INSERT INTO deadline_comandos
                    (tipo, render_id, tentativa_id, imagem_id, deadline_job_id, status, prioridade)
                SELECT 'DELETE_JOB', rt.render_id, rt.id, rt.imagem_id,
                       rt.deadline_job_id, 'PENDENTE', 50
                FROM render_tentativas rt
                WHERE rt.status = 'EXCLUSAO_PENDENTE'
                  AND rt.deadline_job_id IS NOT NULL
                ON DUPLICATE KEY UPDATE
                    atualizado_em = deadline_comandos.atualizado_em
                """)
            results["commands_created"] = cursor.rowcount
        return results

    def mark_active_job_missing(self, attempt: dict) -> bool:
        """Marca somente desaparecimento explícito; falhas temporárias ficam intactas."""
        with self.database.transaction() as cursor:
            cursor.execute(
                "SELECT idrender_alta FROM render_alta WHERE idrender_alta = %s FOR UPDATE",
                (attempt["render_id"],),
            )
            if not cursor.fetchone():
                return False
            cursor.execute(
                """
                SELECT status, ativa, deadline_job_id, iniciado_em
                FROM render_tentativas
                WHERE id = %s
                FOR UPDATE
                """,
                (attempt["id"],),
            )
            current = cursor.fetchone()
            if (
                not current
                or int(current["ativa"]) != 1
                or str(current["deadline_job_id"]) != str(attempt["deadline_job_id"])
                or current["status"] not in OPERATIONAL_STATES
            ):
                return False
            cursor.execute(
                """
                SELECT id FROM deadline_comandos
                WHERE tentativa_id = %s
                  AND tipo = 'DELETE_JOB'
                  AND status IN ('PENDENTE', 'PROCESSANDO', 'CONCLUIDO')
                LIMIT 1
                """,
                (attempt["id"],),
            )
            if cursor.fetchone():
                return False
            reason = (
                "JOB_NUNCA_ENCONTRADO_APOS_VINCULO"
                if current["status"] == "VINCULADA" and not current["iniciado_em"]
                else "JOB_REMOVIDO_EXTERNAMENTE"
            )
            cursor.execute(
                """
                UPDATE render_tentativas
                SET status = 'INCONSISTENTE', motivo_encerramento = %s
                WHERE id = %s
                """,
                (reason, attempt["id"]),
            )
            cursor.execute(
                """
                UPDATE render_alta
                SET has_error = 1,
                    errors = CONCAT_WS('\n', errors, %s)
                WHERE idrender_alta = %s AND deadline_job_id = %s
                """,
                (reason, attempt["render_id"], attempt["deadline_job_id"]),
            )
            self._insert_event_dict(
                cursor,
                attempt["id"],
                "RECONCILIACAO",
                reason,
                {"deadline_job_id": attempt["deadline_job_id"]},
            )
            return True

    @staticmethod
    def _insert_event_dict(
        cursor, attempt_id, event_type: str, key: str, data: dict
    ) -> None:
        if not attempt_id:
            return
        cursor.execute(
            """
            INSERT IGNORE INTO render_tentativa_eventos
                (tentativa_id, tipo, chave, dados_json)
            VALUES (%s, %s, %s, %s)
            """,
            (attempt_id, event_type, key[:120], json.dumps(data, ensure_ascii=False)),
        )
