from __future__ import annotations

import base64
import json
import os
import re
import subprocess
import time
from pathlib import Path

from deadline_client import parse_output, parse_task_render_status
from deadline_domain import valid_job_id

POLL_SECONDS = 10
OFFLINE_GRACE = 120
WORKER_NAME = re.compile(r"[a-z0-9_][a-z0-9_.-]*", re.IGNORECASE | re.ASCII)
WORKER_STATES = {
    "1": "rendering",
    "2": "idle",
    "3": "offline",
    "4": "stalled",
    "rendering": "rendering",
    "idle": "idle",
    "offline": "offline",
    "stalled": "stalled",
    "disabled": "disabled",
}


def worker_fields(output: str) -> dict:
    sections = re.split(r"(?m)^\s*\[[^\]\r\n]+\]\s*$", output)
    workers = {}
    for section in sections:
        fields = parse_output(section)
        name = fields.get("SlaveName")
        if isinstance(name, str) and WORKER_NAME.fullmatch(name):
            workers[name.lower()] = fields
    return workers


def worker_snapshot(fields: dict) -> dict:
    def value(*keys):
        for key in keys:
            field = fields.get(key)
            if isinstance(field, str) and field and field not in {"-", "?"}:
                return field
        return None

    def number(key):
        field = value(key)
        return int(field) if field and field.isdecimal() else None

    def members(*keys):
        return [
            item.strip() for item in (value(*keys) or "").split(",") if item.strip()
        ]

    state = WORKER_STATES.get(
        (value("SlaveState", "SlaveStatus") or "").lower(), "unknown"
    )
    if (
        state not in {"idle", "rendering", "stalled"}
        and (value("Enabled") or "").lower() == "false"
    ):
        state = "disabled"
    job = value("CurrentJobId")
    job = job if valid_job_id(job) else None
    elapsed = value("CurrentTaskRenderTimes")
    task_status = value("CurrentTaskStatus")
    task = parse_task_render_status(task_status)
    percentages = [
        float(match.replace(",", "."))
        for match in re.findall(
            r"(\d+(?:[.,]\d+)?)\s*%", value("CurrentTaskProgresses") or ""
        )
    ]
    percentages = [percent for percent in percentages if 0 <= percent <= 100]
    return {
        "name": value("SlaveName"),
        "state": state,
        "status": value("SlaveStatus"),
        "host": value("HostName"),
        "ip": value("IPAddress"),
        "description": value("Description", "Comment"),
        "job_id": job,
        "job_name": value("CurrentJobName") if job else None,
        "pool": value("CurrentJobPool") if job else None,
        "group": value("CurrentJobGroup") if job else None,
        "plugin": value("CurrentPlugin") if job else None,
        "job_user": value("CurrentJobUser") if job else None,
        "priority": number("CurrentJobPriority") if job else None,
        "task_names": value("CurrentTaskNames") if job else None,
        "task_status": task_status if job else None,
        "progress": (
            round(sum(percentages) / len(percentages), 2)
            if job and percentages
            else None
        ),
        "elapsed": (task["task_elapsed"] or elapsed) if job else None,
        "remaining": task["task_time_remaining"] if job else None,
        "cpu": number("CPUUsage"),
        "threads": number("CPUs"),
        "memory": number("Memory"),
        "memory_free": number("FreeMemory"),
        "disk": value("DiskSpaceString"),
        "mac": value("MacAddress", "MACAddress"),
        "os": value("OperatingSystem", "OS"),
        "gpu": value("VideoCard"),
        "version": value("Version"),
        "uptime": number("UpTimeSeconds"),
        "state_since": value("StateDateTime"),
        "last_write": value("LastWriteTime"),
        "updated": value("UpdateDateTime"),
        "completed": number("CompletedTasks"),
        "failed": number("FailedTasks"),
        "pools": members("Pools", "SlavePools"),
        "groups": members("Groups", "SlaveGroups"),
    }


def collect_workers(client) -> tuple[str, list[dict]]:
    names_result = client.run(["-GetSlaveNames"])
    if not names_result.success:
        return "unavailable", []
    names = {
        line.strip().lower(): line.strip()
        for line in names_result.output.splitlines()
        if line.strip()
    }
    if any(not WORKER_NAME.fullmatch(name) for name in names.values()):
        return "unavailable", []
    workers = {}
    # Limita o tamanho da linha de comando no Windows.
    registered = list(names.values())
    for offset in range(0, len(registered), 40):
        batch = registered[offset : offset + 40]
        response = client.run(["-GetSlave", ",".join(batch), "True"])
        if response.success:
            workers.update(worker_fields(response.output))
    snapshots = [
        (
            worker_snapshot(workers[name.lower()])
            if name.lower() in workers
            else {"name": name, "state": "unknown", "unavailable": True}
        )
        for name in registered
    ]
    if names and not workers:
        return "unavailable", []
    return (
        "partial" if any(worker.get("unavailable") for worker in snapshots) else "ok"
    ), snapshots


def incident_observations(
    health, workers, previous, active, now, dedicated_groups, dedicated_names
):
    desired = {("", "communication")} if health != "ok" else set()
    held = set()
    old_workers = {worker["name"].lower(): worker for worker in previous}
    for worker in workers:
        name = worker["name"].lower()
        old = old_workers.get(name, {})
        state = worker["state"]
        if worker.get("unavailable") or state == "unknown":
            held.add(name)
            continue
        dedicated = name in dedicated_names or any(
            group.lower() in dedicated_groups for group in worker["groups"]
        )
        if dedicated and state == "offline":
            desired.add((name, "offline"))
        if state == "stalled" and (dedicated or worker["job_id"] or old.get("job_id")):
            desired.add((name, "stalled"))
        failed = worker["failed"]
        if (
            old.get("failed") is not None
            and failed is not None
            and failed > old["failed"]
        ):
            desired.add((name, "task_failure"))
    closing = {
        key
        for key, entry in active.items()
        if key not in desired
        and key[0] not in held
        and (health != "unavailable" or not key[0])
        and (key[1] != "task_failure" or now - entry["last_seen_at"] >= OFFLINE_GRACE)
    }
    return desired, closing


def incident_opened_at(kind, detected, now):
    return now if kind != "offline" or now - detected >= OFFLINE_GRACE else None


class FarmCollector:
    def __init__(self, database, client, logger):
        self.database = database
        self.client = client
        self.logger = logger
        self.dedicated_groups = self._configured_names(
            "DEADLINE_FARM_DEDICATED_GROUPS", "farm_dedicated"
        )
        self.dedicated_names = self._configured_names("DEADLINE_FARM_DEDICATED_WORKERS")

    @staticmethod
    def _configured_names(key, default=""):
        return {
            name.strip().lower()
            for name in os.getenv(key, default).split(",")
            if name.strip()
        }

    def run(self, stop):
        missing_schema = False
        try:
            while not stop.is_set():
                started = time.monotonic()
                try:
                    self.refresh()
                    self.publish_transitions()
                    missing_schema = False
                except Exception as exc:
                    missing_table = bool(exc.args and exc.args[0] == 1146)
                    if not missing_table or not missing_schema:
                        self.logger.exception(
                            "Deadline Farm collection failed", extra={"routine": "farm"}
                        )
                    missing_schema = missing_table
                    self.database.close()
                interval = 60 if missing_schema else POLL_SECONDS
                stop.wait(max(0.2, interval - (time.monotonic() - started)))
        finally:
            self.database.close()

    def refresh(self):
        # Um lock no banco evita comandos duplicados mesmo com serviços em hosts diferentes.
        with self.database.transaction() as cursor:
            cursor.execute(
                "SELECT GET_LOCK(CONCAT('flow-farm:', LEFT(SHA2(DATABASE(), 256), 32)), 0) AS acquired"
            )
            if cursor.fetchone()["acquired"] != 1:
                return
            try:
                self._refresh_locked(cursor)
                self.database.connection.commit()
            except Exception:
                self.database.connection.rollback()
                raise
            finally:
                cursor.execute(
                    "SELECT RELEASE_LOCK(CONCAT('flow-farm:', LEFT(SHA2(DATABASE(), 256), 32)))"
                )

    def _refresh_locked(self, cursor):
        cursor.execute(
            "SELECT workers_json, checked_at, successful_at FROM deadline_farm_cache WHERE id = 1"
        )
        cached = cursor.fetchone()
        if not cached or time.time() - cached["checked_at"] < POLL_SECONDS - 1:
            return
        health, workers = collect_workers(self.client)
        now = int(time.time())
        previous = json.loads(cached["workers_json"])
        cursor.execute(
            "SELECT * FROM deadline_farm_incidents WHERE resolved_at IS NULL"
        )
        active = {(item["worker"], item["kind"]): item for item in cursor.fetchall()}
        desired, closing = incident_observations(
            health,
            workers,
            previous,
            active,
            now,
            self.dedicated_groups,
            self.dedicated_names,
        )
        for worker, kind in desired:
            entry = active.get((worker, kind))
            detected = entry["detected_at"] if entry else now
            opened = incident_opened_at(kind, detected, now)
            cursor.execute(
                """INSERT INTO deadline_farm_incidents (worker, kind, detected_at, opened_at, last_seen_at)
                   VALUES (%s,%s,%s,%s,%s)
                   ON DUPLICATE KEY UPDATE opened_at=COALESCE(opened_at,VALUES(opened_at)),
                   last_seen_at=VALUES(last_seen_at)""",
                (worker, kind, detected, opened, now),
            )
        states = {worker["name"].lower(): worker["state"] for worker in workers}
        for key in closing:
            entry = active[key]
            returned = key[1] in {"offline", "stalled"} and states.get(key[0]) in {
                "rendering",
                "idle",
            }
            cursor.execute(
                "UPDATE deadline_farm_incidents SET resolved_at=%s,resolved_reason=%s WHERE id=%s",
                (now, "worker_available" if returned else "cleared", entry["id"]),
            )
        if health == "partial":
            known = {worker["name"].lower(): worker for worker in previous}
            workers = [
                (
                    {**known.get(worker["name"].lower(), {}), **worker}
                    if worker.get("unavailable")
                    else worker
                )
                for worker in workers
            ]
        elif health == "unavailable":
            workers = previous
        cursor.execute(
            """UPDATE deadline_farm_cache SET workers_json=%s, checked_at=%s, successful_at=%s, health=%s
               WHERE id=1""",
            (
                json.dumps(workers, ensure_ascii=False),
                now,
                now if health != "unavailable" else cached["successful_at"],
                health,
            ),
        )

    def publish_transitions(self):
        mode = os.getenv("FLOW_CONNECT_RENDER_FARM_MODE", "").lower()
        if mode not in {"shadow", "active"}:
            return
        task_failure_enabled = os.getenv(
            "FLOW_CONNECT_RENDER_FARM_TASK_FAILURE_ENABLED", "1"
        ).strip().lower() not in {"0", "false", "no", "off"}
        with self.database.transaction() as cursor:
            cursor.execute(
                """SELECT id,worker,kind,opened_at,resolved_at,resolved_reason,notified_open,notified_resolved
                   FROM deadline_farm_incidents WHERE opened_at IS NOT NULL
                   AND (%s=1 OR kind<>'task_failure')
                   AND (notified_open=0 OR (resolved_at IS NOT NULL AND notified_resolved=0))
                   ORDER BY id LIMIT 4""",
                (int(task_failure_enabled),),
            )
            events = cursor.fetchall()
        label = {
            "offline": "Worker offline",
            "stalled": "Worker em stalled",
            "task_failure": "Falha em tarefa",
            "communication": "Falha de comunicação com o Deadline",
        }
        script = (
            Path(__file__).resolve().parent.parent
            / "FlowConnect/scripts/publish_legacy_event.php"
        )
        for incident in events:
            resolved = bool(incident["notified_open"])
            status = "resolved" if resolved else "opened"
            description = label[incident["kind"]]
            worker = incident["worker"].upper()
            message = f"{description} ({worker})" if worker else description
            if resolved:
                message = (
                    f"{worker} voltou a ficar disponível"
                    if incident["resolved_reason"] == "worker_available"
                    else f"Resolvido: {message}"
                )
            payload = {
                "module": "render-farm",
                "event_type": "render.farm.incidente",
                "entity_type": "deadline_farm_incident",
                "entity_id": incident["id"],
                "message": message,
                "webhook_env": "FLOW_CONNECT_RENDER_FARM_WEBHOOK",
                "idempotency_key": f"render-farm:{incident['id']}:{status}",
            }
            encoded = base64.b64encode(json.dumps(payload).encode()).decode()
            result = subprocess.run(
                [os.getenv("DEADLINE_FARM_PHP", "php"), str(script), encoded],
                capture_output=True,
                timeout=8,
                check=False,
            )
            try:
                published = result.returncode == 0 and json.loads(result.stdout).get(
                    "event_id"
                )
            except ValueError:
                published = False
            if published:
                column = "notified_resolved" if resolved else "notified_open"
                with self.database.transaction() as cursor:
                    cursor.execute(
                        f"UPDATE deadline_farm_incidents SET {column}=1 WHERE id=%s",
                        (incident["id"],),
                    )
