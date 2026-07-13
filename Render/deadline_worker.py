from __future__ import annotations

import json
import hashlib
import logging
import os
import signal
import socket
import sys
import time
from logging.handlers import TimedRotatingFileHandler
from pathlib import Path

from deadline_client import DeadlineClient
from deadline_config import SETTINGS
from deadline_db import Database
from deadline_domain import (
    backoff_seconds,
    choose_unambiguous_candidates,
    state_from_deadline,
)
from deadline_repository import DeadlineRepository

BASE_DIR = Path(__file__).resolve().parent
LOG_DIR = BASE_DIR / "logs"
PID_FILE = BASE_DIR / "deadline_worker.pid"
LOCK_FILE = BASE_DIR / "deadline_worker.lock"
STOP_FILE = BASE_DIR / "deadline_worker.stop"
STOP_REQUESTED = False


def first_submission_plan(target: str) -> dict:
    """Plano do primeiro envio, cujo estado anterior e externo ao Flow."""
    return {
        "state_changed": True,
        "process": True,
        "process_preview": target == "EM_APROVACAO",
        "process_post": target == "EM_APROVACAO",
        "send_notifications": True,
        "state_event_key": f"PRIMEIRO_ENVIO->{target}",
        "previous_status": "PRIMEIRO_ENVIO",
        "target": target,
    }


class JsonFormatter(logging.Formatter):
    EXTRA_FIELDS = (
        "worker_id",
        "routine",
        "command_id",
        "attempt_id",
        "render_id",
        "job_id",
        "current",
        "target",
        "duration_ms",
        "result",
        "job_name",
        "reason",
        "candidates",
    )

    def format(self, record):
        payload = {
            "timestamp": self.formatTime(record, "%Y-%m-%dT%H:%M:%S"),
            "level": record.levelname,
            "message": record.getMessage(),
        }
        for field in self.EXTRA_FIELDS:
            payload[field] = getattr(record, field, None)
        if record.exc_info:
            payload["exception"] = self.formatException(record.exc_info)
        return json.dumps(payload, ensure_ascii=False)


class ContextAdapter(logging.LoggerAdapter):
    def process(self, msg, kwargs):
        extra = dict(self.extra)
        extra.update(kwargs.get("extra") or {})
        kwargs["extra"] = extra
        return msg, kwargs


def configure_logging(worker_id: str) -> logging.Logger:
    LOG_DIR.mkdir(exist_ok=True)
    logger = logging.getLogger("deadline_worker")
    configured_level = getattr(
        logging, os.getenv("DEADLINE_LOG_LEVEL", "INFO").upper(), logging.INFO
    )
    logger.setLevel(configured_level)
    logger.handlers.clear()
    formatter = JsonFormatter()
    file_handler = TimedRotatingFileHandler(
        LOG_DIR / "deadline_worker.log",
        when="midnight",
        backupCount=SETTINGS.log_retention_days,
        encoding="utf-8",
    )
    file_handler.setFormatter(formatter)
    console = logging.StreamHandler()
    console.setFormatter(formatter)
    logger.addHandler(file_handler)
    logger.addHandler(console)
    return ContextAdapter(logger, {"worker_id": worker_id})


class SingleInstance:
    def __init__(self, path: Path):
        self.path = path
        self.handle = None

    def __enter__(self):
        self.handle = self.path.open("a+")
        if self.path.stat().st_size == 0:
            self.handle.write("0")
            self.handle.flush()
        self.handle.seek(0)
        try:
            if os.name == "nt":
                import msvcrt

                msvcrt.locking(self.handle.fileno(), msvcrt.LK_NBLCK, 1)
            else:
                import fcntl

                fcntl.flock(self.handle.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
        except (OSError, BlockingIOError) as exc:
            self.handle.close()
            raise RuntimeError(
                "Ja existe uma instancia do worker em execucao."
            ) from exc
        return self

    def __exit__(self, exc_type, exc, tb):
        if self.handle:
            try:
                if os.name == "nt":
                    import msvcrt

                    self.handle.seek(0)
                    msvcrt.locking(self.handle.fileno(), msvcrt.LK_UNLCK, 1)
                else:
                    import fcntl

                    fcntl.flock(self.handle.fileno(), fcntl.LOCK_UN)
            finally:
                self.handle.close()


class DeadlineWorker:
    def __init__(self, settings=SETTINGS):
        self.settings = settings
        self.logger = configure_logging(settings.worker_id)
        self.database = Database(settings, self.logger)
        self.repository = DeadlineRepository(self.database, self.logger)
        self.client = DeadlineClient(
            settings.deadline_command, settings.command_timeout
        )
        self.hostname = socket.gethostname()
        self.unlinked_jobs_seen: set[str] = set()

        import deadline_monitor

        deadline_monitor.DEADLINE_COMMAND = settings.deadline_command
        deadline_monitor.DEADLINE_COMMAND_TIMEOUT = settings.command_timeout
        deadline_monitor.EXTERNAL_LOGGER = self.logger
        self.business = deadline_monitor

    def observation_fingerprint(
        self, target: str, job_data: dict, task_data: dict
    ) -> str:
        """Resumo estavel da resposta Deadline usado para decidir se ha evento."""
        payload = {
            "target": target,
            "job_status": job_data.get("Status") or job_data.get("JobStatus"),
            "active": job_data.get("Active"),
            "complete": job_data.get("Complete"),
            "last_updated": job_data.get("LastUpdated")
            or job_data.get("JobLastUpdated"),
            "render_output": self.business.extract_render_output(job_data, ""),
            "task_status": task_data.get("TaskStatus"),
            "task_error_count": task_data.get("TaskErrorCount")
            or task_data.get("ErrorCount"),
        }
        serialized = json.dumps(payload, sort_keys=True, default=str, ensure_ascii=False)
        return hashlib.sha256(serialized.encode("utf-8")).hexdigest()

    def process_one_command(self) -> bool:
        command = self.repository.reserve_command(self.settings.worker_id)
        if not command:
            return False
        context = {
            "routine": "queue",
            "command_id": command["id"],
            "job_id": command["deadline_job_id"],
            "attempt_id": command.get("tentativa_id"),
            "render_id": command.get("render_id"),
        }
        self.logger.info("command reserved", extra={**context, "result": "RESERVED"})
        if command["tipo"] not in {"DELETE_JOB"}:
            self.repository.reschedule_command(
                command["id"],
                self.settings.worker_id,
                "TIPO_DE_COMANDO_NAO_SUPORTADO",
                backoff_seconds(command["tentativas_execucao"]),
            )
            self.logger.error("unsupported command", extra=context)
            return True
        if self.settings.delete_dry_run:
            self.repository.reschedule_command(
                command["id"],
                self.settings.worker_id,
                "DRY_RUN: DeleteJob nao executado",
                max(60, self.settings.reconciliation_interval),
                dry_run=True,
            )
            self.logger.warning("delete dry-run", extra=context)
            return True

        started = time.monotonic()
        result = self.client.delete_job(command["deadline_job_id"])
        duration_ms = int((time.monotonic() - started) * 1000)
        if result.success:
            self.repository.finish_delete(
                command["id"], self.settings.worker_id, result.output
            )
            message = (
                "job already absent; command completed"
                if result.not_found
                else "job deleted"
            )
            self.logger.info(
                message,
                extra={**context, "duration_ms": duration_ms, "result": "CONCLUIDO"},
            )
        else:
            delay = backoff_seconds(command["tentativas_execucao"])
            self.repository.reschedule_command(
                command["id"], self.settings.worker_id, result.output, delay
            )
            log = (
                self.logger.critical
                if int(command["tentativas_execucao"]) >= int(command["max_tentativas"])
                else self.logger.error
            )
            log(
                "delete failed; command scheduled for retry",
                extra={**context, "duration_ms": duration_ms, "result": "ERRO"},
            )
        return True

    def sync_active(self) -> None:
        attempts = self.repository.active_attempts()
        for attempt in attempts:
            context = {
                "routine": "active_sync",
                "attempt_id": attempt["id"],
                "render_id": attempt["render_id"],
                "job_id": attempt["deadline_job_id"],
            }
            job_result, job_data = self.client.get_job(attempt["deadline_job_id"])
            if not job_result.success:
                self.logger.warning("active job could not be read", extra=context)
                continue
            task_result, task_data = self.client.get_tasks(attempt["deadline_job_id"])
            if not task_result.success:
                self.logger.warning("active job tasks could not be read", extra=context)
                task_data = {}
            target = state_from_deadline(job_data, task_data)
            plan = self.repository.observe_deadline_state(
                attempt,
                target,
                self.observation_fingerprint(target, job_data, task_data),
            )
            if plan is None:
                self.logger.debug(
                    "attempt changed before observation", extra={**context, "target": target}
                )
                continue
            if plan["ignored"]:
                self.logger.warning(
                    "observation would reopen or regress attempt; ignored",
                    extra={
                        **context,
                        "current": plan["previous_status"],
                        "target": target,
                    },
                )
                continue
            if not plan["process"]:
                self.logger.debug(
                    "job sem alteracoes desde a ultima sincronizacao",
                    extra={**context, "target": target},
                )
                continue
            try:
                if target == "ERRO" and plan["state_changed"]:
                    error_info = self.business.deadline_error_info(
                        attempt["deadline_job_id"], job_data, task_data
                    )
                else:
                    error_info = (False, "")
                with self.database.transaction(dict_rows=False) as cursor:
                    if not self.repository.lock_attempt_context_tuple(cursor, attempt):
                        raise RuntimeError(
                            "Tentativa mudou antes do inicio da sincronizacao."
                        )
                    p00_rollup = {}
                    processed = self.business.process_deadline_job(
                        cursor,
                        p00_rollup=p00_rollup,
                        deadline_job_id=attempt["deadline_job_id"],
                        job_data=job_data,
                        task_data=task_data,
                        raw_job_output=job_result.output,
                        attempt_context=attempt,
                        notifications_enabled=plan["send_notifications"],
                        error_info=error_info,
                        processing_plan=plan,
                    )
                    if processed is False:
                        raise RuntimeError(
                            "Vinculo estrito deixou de ser valido durante a sincronizacao."
                        )
                    self.business.finalize_p00_rollup(
                        cursor,
                        p00_rollup,
                        notifications_enabled=plan["send_notifications"],
                    )
                self.logger.info(
                    "deadline event processed",
                    extra={
                        **context,
                        "current": plan["previous_status"],
                        "target": target,
                        "result": "STATE_CHANGE" if plan["state_changed"] else "PREVIEW_CHANGE",
                    },
                )
            except Exception:
                self.logger.exception("active synchronization failed", extra=context)

    def discover(self) -> None:
        result, job_ids = self.client.list_job_ids()
        if not result.success:
            self.logger.warning(
                "Deadline job discovery failed", extra={"routine": "discovery"}
            )
            return
        known_job_ids = self.repository.known_job_ids()
        unknown = [job_id for job_id in job_ids if job_id not in known_job_ids]
        resend_proposals = []
        for job_id in unknown:
            job_result, job_data = self.client.get_job(job_id)
            if not job_result.success:
                continue
            job_name = str(
                job_data.get("Name") or job_data.get("JobName") or ""
            ).strip()
            if not job_name or "ANIMA" in job_name.upper():
                continue
            target_info = self.repository.discovery_target(job_id, job_name)
            outcome = target_info["outcome"]
            if outcome == "FIRST_RENDER":
                task_result, task_data = self.client.get_tasks(job_id)
                if not task_result.success:
                    task_data = {}
                target = state_from_deadline(job_data, task_data)
                created = self.repository.create_first_render(
                    target_info, job_id, job_name, target
                )
                outcome = created["outcome"]
                if outcome == "FIRST_RENDER_CREATED":
                    self.logger.info(
                        "first render created from discovered job",
                        extra={
                            "routine": "discovery",
                            "attempt_id": created["id"],
                            "render_id": created["render_id"],
                            "job_id": job_id,
                            "job_name": job_name,
                            "reason": "FIRST_RENDER_CREATED",
                        },
                    )
                    self.process_first_render(
                        created,
                        job_result,
                        job_data,
                        task_data,
                        target,
                    )
                    continue
                if outcome == "RACE_RENDER_EXISTS":
                    # Rele a realidade apos o lock/UNIQUE detectar outro
                    # escritor. Somente o caminho seguro de reenvio prossegue.
                    target_info = self.repository.discovery_target(job_id, job_name)
                    outcome = target_info["outcome"]
                else:
                    self.log_discovery_outcome(job_id, job_name, outcome, created)
                    continue

            if outcome == "RESEND":
                # O 3ds Max pode enviar o re-render antes de o usuario
                # registrar a reprovacao no Flow. A data de submissao nao e
                # uma relacao causal confiavel; Job ID, imagem e tentativa
                # AGUARDANDO_JOB sao as protecoes de vinculo.
                proposal = dict(target_info)
                proposal.update({"job_id": job_id, "job_name": job_name})
                resend_proposals.append(proposal)
                continue

            self.log_discovery_outcome(job_id, job_name, outcome, target_info)

        selected, conflicts = choose_unambiguous_candidates(resend_proposals)
        for attempt_id, items in conflicts.items():
            self.logger.warning(
                "new jobs not linked due to ambiguity",
                extra={
                    "routine": "discovery",
                    "attempt_id": attempt_id,
                    "reason": "MULTIPLE_UNMATCHED_JOBS",
                    "candidates": [item["job_id"] for item in items],
                },
            )
        for proposal in selected:
            if self.repository.bind_job(
                proposal["tentativa_id"], proposal["job_id"], proposal["job_name"]
            ):
                self.logger.info(
                    "new job linked to waiting attempt",
                    extra={
                        "routine": "discovery",
                        "attempt_id": proposal["tentativa_id"],
                        "render_id": proposal["render_id"],
                        "job_id": proposal["job_id"],
                    },
                )

    def log_discovery_outcome(
        self, job_id: str, job_name: str, reason: str, details: dict | None = None
    ) -> None:
        """Loga um diagnostico de descoberta apenas uma vez por processo/job."""
        log = (
            self.logger.warning
            if job_id not in self.unlinked_jobs_seen
            else self.logger.debug
        )
        log(
            "new job not linked",
            extra={
                "routine": "discovery",
                "job_id": job_id,
                "job_name": job_name,
                "reason": reason,
                "candidates": (details or {}).get("image_ids", []),
            },
        )
        self.unlinked_jobs_seen.add(job_id)

    def process_first_render(
        self, attempt: dict, job_result, job_data: dict, task_data: dict, target: str
    ) -> None:
        """Executa uma vez o comportamento legado apos criar o primeiro envio."""
        context = {
            "routine": "first_render",
            "attempt_id": attempt["id"],
            "render_id": attempt["render_id"],
            "job_id": attempt["deadline_job_id"],
        }
        plan = first_submission_plan(target)
        try:
            error_info = (
                self.business.deadline_error_info(
                    attempt["deadline_job_id"], job_data, task_data
                )
                if target == "ERRO"
                else (False, "")
            )
            with self.database.transaction(dict_rows=False) as cursor:
                if not self.repository.lock_attempt_context_tuple(cursor, attempt):
                    raise RuntimeError("Tentativa inicial mudou antes do processamento.")
                p00_rollup = {}
                processed = self.business.process_deadline_job(
                    cursor,
                    p00_rollup=p00_rollup,
                    deadline_job_id=attempt["deadline_job_id"],
                    job_data=job_data,
                    task_data=task_data,
                    raw_job_output=job_result.output,
                    attempt_context=attempt,
                    notifications_enabled=True,
                    error_info=error_info,
                    processing_plan=plan,
                )
                if processed is False:
                    raise RuntimeError("Vinculo estrito recusou o primeiro envio.")
                self.business.finalize_p00_rollup(
                    cursor, p00_rollup, notifications_enabled=True
                )
            # O primeiro processamento ja aplicou preview/POS/Slack. Registra
            # o snapshot somente apos esse sucesso para que o proximo ciclo
            # ativo seja barato e nao trate a observacao inicial como preview.
            self.repository.observe_deadline_state(
                attempt,
                target,
                self.observation_fingerprint(target, job_data, task_data),
            )
            self.logger.info(
                "first render processed",
                extra={**context, "target": target, "result": "FIRST_RENDER_CREATED"},
            )
        except Exception:
            self.logger.exception("first render processing failed", extra=context)

    def reconcile(self) -> None:
        recovered = self.repository.recover_stale_commands(self.settings.lock_timeout)
        result = self.repository.reconcile()
        for attempt in self.repository.active_attempts():
            read_result, _job_data = self.client.get_job(attempt["deadline_job_id"])
            if read_result.not_found and self.repository.mark_active_job_missing(attempt):
                self.logger.error(
                    "active job is explicitly absent in Deadline; attempt marked inconsistent",
                    extra={
                        "routine": "reconciliation",
                        "attempt_id": attempt["id"],
                        "render_id": attempt["render_id"],
                        "job_id": attempt["deadline_job_id"],
                        "reason": "EXPLICIT_NOT_FOUND",
                    },
                )
            elif not read_result.success and not read_result.not_found:
                self.logger.warning(
                    "Deadline unavailable during reconciliation; state preserved",
                    extra={
                        "routine": "reconciliation",
                        "attempt_id": attempt["id"],
                        "render_id": attempt["render_id"],
                        "job_id": attempt["deadline_job_id"],
                        "reason": "TEMPORARY_READ_FAILURE",
                    },
                )
        if (
            recovered
            or result["cache_fixed"]
            or result["commands_created"]
            or result["multiple_active"]
        ):
            self.logger.warning(
                "reconciliation found work",
                extra={"routine": "reconciliation"},
            )
        for duplicate in result["multiple_active"]:
            self.logger.error(
                "multiple active attempts detected; no automatic choice made",
                extra={
                    "routine": "reconciliation",
                    "render_id": duplicate["render_id"],
                },
            )

    def heartbeat(self, status: str = "ATIVO", details: str = "") -> None:
        self.repository.heartbeat(
            self.settings.worker_id,
            self.hostname,
            os.getpid(),
            status,
            details,
        )

    def run(self) -> None:
        if self.settings.mode != "continuous" or self.settings.legacy_mode:
            raise RuntimeError(
                "Worker continuo exige DEADLINE_WORKER_MODE=continuous e DEADLINE_LEGACY_MODE=0."
            )
        self.repository.require_schema()
        if STOP_FILE.exists():
            STOP_FILE.unlink()
        PID_FILE.write_text(str(os.getpid()), encoding="ascii")
        self.heartbeat("DRY_RUN" if self.settings.delete_dry_run else "ATIVO")
        self.reconcile()
        self.logger.info(
            "continuous worker started",
            extra={"routine": "startup"},
        )
        next_run = {
            "queue": 0.0,
            "active": 0.0,
            "discovery": 0.0,
            "reconciliation": time.monotonic() + self.settings.reconciliation_interval,
            "heartbeat": time.monotonic() + self.settings.heartbeat_interval,
        }
        while not STOP_REQUESTED:
            if STOP_FILE.exists():
                break
            now = time.monotonic()
            if now >= next_run["queue"]:
                try:
                    had_work = self.process_one_command()
                except Exception:
                    had_work = False
                    self.logger.exception(
                        "queue routine failed", extra={"routine": "queue"}
                    )
                    self.database.close()
                next_run["queue"] = now + (
                    0.2 if had_work else self.settings.queue_interval
                )
            if now >= next_run["active"]:
                try:
                    self.sync_active()
                except Exception:
                    self.logger.exception(
                        "active routine failed", extra={"routine": "active_sync"}
                    )
                    self.database.close()
                next_run["active"] = now + self.settings.active_sync_interval
            if now >= next_run["discovery"]:
                try:
                    self.discover()
                except Exception:
                    self.logger.exception(
                        "discovery routine failed", extra={"routine": "discovery"}
                    )
                    self.database.close()
                next_run["discovery"] = now + self.settings.discovery_interval
            if now >= next_run["reconciliation"]:
                try:
                    self.reconcile()
                except Exception:
                    self.logger.exception(
                        "reconciliation failed", extra={"routine": "reconciliation"}
                    )
                    self.database.close()
                next_run["reconciliation"] = now + self.settings.reconciliation_interval
            if now >= next_run["heartbeat"]:
                try:
                    self.heartbeat(
                        "DRY_RUN" if self.settings.delete_dry_run else "ATIVO"
                    )
                except Exception:
                    self.logger.exception(
                        "heartbeat failed", extra={"routine": "heartbeat"}
                    )
                    self.database.close()
                next_run["heartbeat"] = now + self.settings.heartbeat_interval
            time.sleep(0.2)
        self.heartbeat("ENCERRANDO")
        self.logger.info("continuous worker stopping", extra={"routine": "shutdown"})


def request_stop(_signum, _frame):
    global STOP_REQUESTED
    STOP_REQUESTED = True


def main() -> int:
    signal.signal(signal.SIGINT, request_stop)
    if hasattr(signal, "SIGTERM"):
        signal.signal(signal.SIGTERM, request_stop)
    try:
        with SingleInstance(LOCK_FILE):
            worker = DeadlineWorker()
            failed = False
            try:
                worker.run()
            except Exception as exc:
                failed = True
                try:
                    worker.heartbeat("ERRO", str(exc))
                except Exception:
                    pass
                raise
            finally:
                if not failed:
                    try:
                        worker.heartbeat("PARADO")
                    except Exception:
                        pass
                worker.database.close()
                if PID_FILE.exists():
                    PID_FILE.unlink()
                if STOP_FILE.exists():
                    STOP_FILE.unlink()
        return 0
    except Exception as exc:
        print(f"deadline_worker: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
