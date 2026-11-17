"""Incremental report collection on the existing continuous Deadline worker."""
from __future__ import annotations

import json
import os
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from pathlib import Path
from tempfile import TemporaryDirectory

from deadline_client import DeadlineClient
from deadline_config import _bool, _int
from deadline_diagnosis import digest
from deadline_diagnostic_store import publish_alert, read, reconcile_recovery, store_recovery, store_report


@dataclass(frozen=True)
class DiagnosticSettings:
    enabled: bool
    mode: str
    alert_from: datetime
    window: timedelta
    threshold: int
    update_interval: timedelta
    stability: timedelta
    poll_seconds: int
    batch_size: int

    @classmethod
    def from_env(cls):
        mode = os.getenv("DEADLINE_DIAGNOSTIC_NOTIFY_MODE", "off").strip().lower()
        raw_cutoff = os.getenv("DEADLINE_DIAGNOSTIC_ALERT_FROM_UTC", "").strip()
        try:
            if raw_cutoff:
                stamp = datetime.fromisoformat(raw_cutoff.replace("Z", "+00:00"))
                if stamp.tzinfo is None:
                    raise ValueError("missing timezone")
                cutoff = stamp.astimezone(timezone.utc).replace(tzinfo=None)
            elif mode == "active":
                raise ValueError("active alerts require a cutoff")
            else:
                cutoff = datetime.min
        except ValueError:
            # Invalid configuration must leave collection functioning and delivery disabled.
            mode, cutoff = "off", datetime.min
        return cls(
            _bool("DEADLINE_DIAGNOSTIC_ENABLED"), mode if mode in {"shadow", "active"} else "off", cutoff,
            timedelta(minutes=_int("DEADLINE_DIAGNOSTIC_WINDOW_MINUTES", 15)),
            _int("DEADLINE_DIAGNOSTIC_THRESHOLD", 3),
            timedelta(minutes=_int("DEADLINE_DIAGNOSTIC_UPDATE_MINUTES", 60)),
            timedelta(minutes=_int("DEADLINE_DIAGNOSTIC_STABILITY_MINUTES", 30)),
            _int("DEADLINE_DIAGNOSTIC_JOB_POLL_SECONDS", 60),
            min(25, _int("DEADLINE_DIAGNOSTIC_REPORTS_PER_POLL", 8)),
        )


class DiagnosticCollector:
    def __init__(self, worker):
        self.settings = DiagnosticSettings.from_env()
        self.db = worker.database
        self.logger = worker.logger
        self.client = DeadlineClient(worker.settings.deadline_command,
                                     _int("DEADLINE_DIAGNOSTIC_COMMAND_TIMEOUT", 15))
        # Scope the connection lock to this Flow database on the shared MySQL server.
        self.lock_name = "flow_deadline_diagnosis_" + digest(worker.settings.db_name)[:32]

    def discover(self, job_ids):
        if not job_ids:
            return
        with self.db.transaction() as cursor:
            cursor.executemany(
                """INSERT INTO deadline_diagnostic_jobs (job_id,last_seen_at,next_poll_at)
                   VALUES (%s,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))
                   ON DUPLICATE KEY UPDATE last_seen_at=VALUES(last_seen_at)""",
                [(job_id.lower(),) for job_id in job_ids],
            )

    def observe_task_errors(self, job_id, job_failed, task_data):
        counters = task_data.get("TaskErrorCount") or task_data.get("ErrorCount") or []
        if not isinstance(counters, list):
            counters = [counters]
        if not job_failed and not any(str(value).isdigit() and int(value) > 0 for value in counters):
            return
        hint = digest(json.dumps([job_failed, counters]))
        with self.db.transaction() as cursor:
            cursor.execute(
                """INSERT INTO deadline_diagnostic_jobs (job_id,last_seen_at,next_poll_at,failure_hint,priority_poll)
                   VALUES (%s,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),%s,1)
                   ON DUPLICATE KEY UPDATE last_seen_at=VALUES(last_seen_at),
                   next_poll_at=IF(failure_hint <=> VALUES(failure_hint),next_poll_at,VALUES(next_poll_at)),
                   priority_poll=IF(failure_hint <=> VALUES(failure_hint),priority_poll,1),
                   failure_hint=VALUES(failure_hint)""",
                (job_id.lower(), hint),
            )

    def _request(self, job_id, request):
        with TemporaryDirectory(prefix="flow-deadline-report-") as temp:
            input_path = Path(temp) / "request.json"
            result_path = Path(temp) / "response.json"
            input_path.write_text(json.dumps(request), encoding="utf-8")
            result = self.client.run([
                "-ExecuteScriptNoGui", str(Path(__file__).with_name("deadline_report_export.py")),
                job_id, str(input_path), str(result_path),
            ])
            if not result.success:
                return None
            try:
                response = json.loads(result_path.read_text(encoding="utf-8-sig"))
            except (OSError, ValueError):
                return None
            if not isinstance(response, dict) or response.get("job_id") != job_id:
                return None
            if "reports" not in response and response.get("missing") is not True:
                return None
            return response

    def _prepare(self, cursor):
        now = read(cursor, "SELECT UTC_TIMESTAMP(6) AS stamp")[0]["stamp"]
        jobs = read(cursor, """
            SELECT job_id,failure_hint FROM deadline_diagnostic_jobs
            WHERE next_poll_at <= %s AND last_seen_at >= DATE_SUB(%s,INTERVAL 1 DAY)
            ORDER BY priority_poll DESC, next_poll_at, job_id LIMIT 1
            """, (now, now))
        if not jobs:
            return None
        job_id = jobs[0]["job_id"]
        reports = read(cursor, """
            SELECT report_id,metadata_hash,body_state,next_body_retry
            FROM deadline_diagnostic_occurrences WHERE job_id=%s
            """, (job_id,))
        failures = read(cursor, """
            SELECT DISTINCT o.task_id,LOWER(o.worker) AS worker FROM deadline_diagnostic_occurrences o
            JOIN deadline_diagnostic_incidents i ON i.id=o.incident_id AND i.episode=o.episode
            WHERE o.job_id=%s AND o.task_id IS NOT NULL AND o.worker IS NOT NULL AND i.state<>'resolved'
            """, (job_id,))
        return job_id, {
            "known": {row["report_id"]: [row["metadata_hash"],
                                       row["body_state"] != "pending" or row["next_body_retry"] > now] for row in reports},
            "failures": [[row["task_id"], row["worker"]] for row in failures],
            "limit": self.settings.batch_size,
            "unspecified_utc": _bool("DEADLINE_DIAGNOSTIC_UNSPECIFIED_DATETIME_IS_UTC"),
            "failure_hint": jobs[0]["failure_hint"],
        }

    def _finish(self, cursor, job_id, result, scan_started_at, failure_hint=None):
        now = read(cursor, "SELECT UTC_TIMESTAMP(6) AS stamp")[0]["stamp"]
        error = None
        next_poll = self.settings.poll_seconds
        if result is None or result.get("missing"):
            error = "job_unavailable" if result else "report_query_failed"
            if result:
                next_poll = max(next_poll, 300)
            self.logger.warning(error, extra={"routine": "deadline_diagnosis", "job_id": job_id})
        else:
            prior = read(cursor, "SELECT last_scan_at FROM deadline_diagnostic_jobs WHERE job_id=%s", (job_id,))[0]
            for report in result["reports"]:
                if report["job_id"] != job_id or not report["report_id"]:
                    raise ValueError("Invalid report identity")
                if report["occurred_at"]:
                    report["occurred_at"] = datetime.fromisoformat(report["occurred_at"]).isoformat(sep=" ")
                store_report(cursor, report, now, prior["last_scan_at"])
            if result["recovery"] is not None:
                store_recovery(cursor, job_id, result["recovery"])
            else:
                error = "recovery_query_failed"
            if result["backlog"]:
                next_poll = 1
                error = "report_backlog"
            if not error and read(cursor, """SELECT id FROM deadline_diagnostic_occurrences
                                            WHERE job_id=%s AND body_state='pending' LIMIT 1""", (job_id,)):
                error = "report_body_unavailable"
        cursor.execute(
            """UPDATE deadline_diagnostic_jobs SET last_error=%s,
               next_poll_at=IF(failure_hint <=> %s,%s,next_poll_at),
               priority_poll=IF(failure_hint <=> %s,0,priority_poll),
               last_scan_at=IF(%s IS NULL,%s,last_scan_at) WHERE job_id=%s""",
            (error, failure_hint, now + timedelta(seconds=next_poll), failure_hint, error, scan_started_at, job_id),
        )
        if result is None or result.get("missing"):
            return
        incidents = read(cursor, """
            SELECT DISTINCT i.* FROM deadline_diagnostic_occurrences o
            JOIN deadline_diagnostic_incidents i ON i.id=o.incident_id AND i.episode=o.episode
            WHERE o.job_id=%s AND i.state<>'resolved' ORDER BY i.id
            """, (job_id,))
        for incident in incidents:
            if not error:
                reconcile_recovery(cursor, incident, now, self.settings.stability)
            publish_alert(cursor, incident, now, self.settings)

    def tick(self):
        with self.db.transaction() as cursor:
            locked = read(cursor, "SELECT GET_LOCK(%s,0) AS acquired", (self.lock_name,))[0]["acquired"]
        if locked != 1:
            return
        try:
            with self.db.transaction() as cursor:
                work = self._prepare(cursor)
                scan_started_at = read(cursor, "SELECT UTC_TIMESTAMP(6) AS stamp")[0]["stamp"]
            if work is None:
                return
            job_id, request = work
            result = self._request(job_id, request)
            with self.db.transaction() as cursor:
                owns_lock = read(cursor, "SELECT IS_USED_LOCK(%s)=CONNECTION_ID() AS owned", (self.lock_name,))[0]["owned"]
                if owns_lock:
                    self._finish(cursor, job_id, result, scan_started_at, request["failure_hint"])
        finally:
            with self.db.transaction() as cursor:
                cursor.execute("SELECT RELEASE_LOCK(%s)", (self.lock_name,))
