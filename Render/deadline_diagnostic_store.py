"""Persistence and alert policy. Called inside the collector's MySQL transaction."""
from __future__ import annotations

import json
from datetime import timedelta
from uuid import NAMESPACE_URL, uuid5

from deadline_diagnosis import CATEGORIES, classify, digest, extract, redact


def encode(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True)


def read(cursor, sql, args=()):
    cursor.execute(sql, args)
    return cursor.fetchall()


def history(cursor, now, kind, data, incident_id=None, episode=None, occurrence_id=None):
    cursor.execute(
        """INSERT INTO deadline_diagnostic_history
           (incident_id, occurrence_id, episode, kind, recorded_at, detail_json)
           VALUES (%s,%s,%s,%s,%s,%s)""",
        (incident_id, occurrence_id, episode, kind, now, encode(data)),
    )


def incident_for(cursor, report, diagnosis, now):
    worker, stamp = report["worker"], report["occurred_at"]
    identity = f'worker:{worker.lower()}' if worker else f'job:{report["job_id"]}'
    key = digest(f'{identity}:{diagnosis["signature"]}')
    cursor.execute(
        """INSERT INTO deadline_diagnostic_incidents
           (correlation_key, signature, worker, state) VALUES (%s,%s,%s,'open')
           ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)""",
        (key, diagnosis["signature"], worker),
    )
    incident_id = cursor.lastrowid
    incident = read(cursor, "SELECT * FROM deadline_diagnostic_incidents WHERE id=%s FOR UPDATE", (incident_id,))[0]
    episode = incident["episode"]
    previous = read(cursor, """SELECT episode FROM deadline_diagnostic_history
                              WHERE incident_id=%s AND kind='resolved' AND recorded_at >= %s
                              ORDER BY episode LIMIT 1""", (incident_id, stamp)) if stamp else []
    if previous:
        return incident_id, previous[0]["episode"]
    if incident["state"] == "resolved":
        if not stamp:
            return incident_id, episode
        episode += 1
        cursor.execute(
            """UPDATE deadline_diagnostic_incidents SET episode=%s, state='open', first_at=NULL,
               last_at=NULL, resolved_at=NULL, resolution_reason=NULL WHERE id=%s""", (episode, incident_id)
        )
        history(cursor, now, "reopened", {"report_id": report["report_id"]}, incident_id, episode)
    elif incident["state"] == "monitoring":
        set_state(cursor, incident, now, "open", "Nova ocorrência registrada.")
    if not incident["last_at"] and episode == 1:
        opened = read(cursor, """SELECT id FROM deadline_diagnostic_history
                                 WHERE incident_id=%s AND kind='opened' LIMIT 1""", (incident_id,))
        if not opened:
            history(cursor, now, "opened", {"report_id": report["report_id"]}, incident_id, episode)
    cursor.execute(
        """UPDATE deadline_diagnostic_incidents SET
           first_at=COALESCE(LEAST(first_at,%s),first_at,%s),
           last_at=COALESCE(GREATEST(last_at,%s),last_at,%s) WHERE id=%s""",
        (stamp, stamp, stamp, stamp, incident_id),
    )
    return incident_id, episode


def store_report(cursor, report, now, observed_since):
    body = report.pop("body")
    previous = read(cursor, """SELECT * FROM deadline_diagnostic_occurrences
                               WHERE job_id=%s AND report_id=%s FOR UPDATE""",
                    (report["job_id"], report["report_id"]))
    previous = previous[0] if previous else None
    body_state = "available" if body else "unavailable" if report.pop("save_error", False) else "pending"
    report.pop("save_error", None)
    report["body_hash"] = digest(body) if body else None
    if (previous and previous["metadata_hash"] == report["metadata_hash"] and body_state == previous["body_state"]
            and body_state != "pending" and json.loads(previous["report_json"]).get("body_hash") == report["body_hash"]):
        return
    if previous and previous["body_state"] != "pending" and body_state == "pending":
        return
    worker = report["worker"]
    stamp = report["occurred_at"]
    capacity = report.get("capacity") or {}
    if worker and stamp and any(key in capacity for key in ("physical_bytes", "groups", "pools")):
        prior = read(cursor, """SELECT report_json FROM deadline_diagnostic_occurrences
                               WHERE worker=%s AND occurred_at < %s ORDER BY occurred_at DESC LIMIT 1""",
                     (worker, stamp))
        before = json.loads(prior[0]["report_json"]).get("capacity", {}) if prior else {}
        capacity["configuration_changed"] = any(key in before and key in capacity and before[key] != capacity[key]
                                                for key in ("physical_bytes", "groups", "pools"))
    evidence = extract(body, report)
    if body_state != "available":
        evidence["unavailable"].append("report_body")
    if report["truncated"]:
        evidence["unavailable"].append("full_excerpt")
    diagnosis = classify(evidence, report)
    if body_state == "pending" and diagnosis["confidence"] != "confirmado":
        diagnosis = None
    report["message"] = redact(report["message"])
    report["job_name"] = redact(report["job_name"])[:255]
    incident_id = previous["incident_id"] if previous else None
    episode = previous["episode"] if previous else None
    if diagnosis and incident_id is None:
        incident_id, episode = incident_for(cursor, report, diagnosis, now)
    retry_at = now + timedelta(minutes=5) if body_state == "pending" else None
    cursor.execute(
        """INSERT INTO deadline_diagnostic_occurrences
           (job_id, report_id, metadata_hash, body_state, next_body_retry, incident_id, episode,
            job_name, task_id, frame, worker, renderer, occurred_at, collected_at, observed_since, signature, catalog_version,
            report_json, evidence_json, diagnosis_json)
           VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
           ON DUPLICATE KEY UPDATE metadata_hash=VALUES(metadata_hash), body_state=VALUES(body_state),
             next_body_retry=VALUES(next_body_retry), incident_id=VALUES(incident_id), episode=VALUES(episode),
             job_name=VALUES(job_name), task_id=VALUES(task_id), frame=VALUES(frame), worker=VALUES(worker),
             renderer=VALUES(renderer), occurred_at=VALUES(occurred_at), signature=VALUES(signature),
             catalog_version=VALUES(catalog_version), report_json=VALUES(report_json), evidence_json=VALUES(evidence_json),
             diagnosis_json=VALUES(diagnosis_json), id=LAST_INSERT_ID(id)""",
        (report["job_id"], report["report_id"], report["metadata_hash"], body_state, retry_at, incident_id, episode,
         report["job_name"], report["task_id"], report["frame"], worker, evidence["renderer"], stamp, now, observed_since,
         diagnosis["signature"] if diagnosis else None, diagnosis["catalog_version"] if diagnosis else None,
         encode(report), encode(evidence), encode(diagnosis) if diagnosis else None),
    )
    old = json.loads(previous["diagnosis_json"]) if previous and previous["diagnosis_json"] else None
    if diagnosis and diagnosis != old:
        history(cursor, now, "classified", diagnosis, incident_id, episode, cursor.lastrowid)


def store_recovery(cursor, job_id, candidates):
    for item in candidates:
        cursor.execute(
            """INSERT INTO deadline_diagnostic_recovery (job_id,task_id,worker,report_id,started_at,completed_at)
               VALUES (%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE
               report_id=IF(VALUES(completed_at)>completed_at,VALUES(report_id),report_id),
               started_at=IF(VALUES(completed_at)>completed_at,VALUES(started_at),started_at),
               completed_at=GREATEST(completed_at,VALUES(completed_at))""",
            (job_id, item["task_id"], item["worker"], item["report_id"], item["started_at"], item["completed_at"]),
        )


def set_state(cursor, incident, now, state, reason, recovery=()):
    if state == incident["state"]:
        return
    resolved = state == "resolved"
    cursor.execute(
        """UPDATE deadline_diagnostic_incidents SET state=%s, resolved_at=%s,
           resolution_reason=%s WHERE id=%s""",
        (state, now if resolved else None, reason if resolved else None, incident["id"]),
    )
    history(cursor, now, state, {"reason": reason, "recovery": list(recovery)}, incident["id"], incident["episode"])
    incident["state"] = state


def reconcile_recovery(cursor, incident, now, stability):
    candidates = read(cursor, """
        SELECT DISTINCT o.job_id,o.task_id,o.worker,r.report_id,o.occurred_at,
               r.started_at,r.completed_at,j.last_scan_at,j.last_error
        FROM deadline_diagnostic_occurrences o
        LEFT JOIN deadline_diagnostic_recovery r ON r.job_id=o.job_id AND r.task_id=o.task_id AND r.worker=o.worker
        LEFT JOIN deadline_diagnostic_jobs j ON j.job_id=o.job_id
        WHERE o.incident_id=%s AND o.episode=%s
        """, (incident["id"], incident["episode"]))
    if not candidates or any(not row["occurred_at"] or not row["started_at"] or row["started_at"] <= row["occurred_at"]
                             or row["completed_at"] > now for row in candidates):
        return
    stable_by = max(row["completed_at"] for row in candidates) + stability
    recovery = [{"job_id": row["job_id"], "task_id": row["task_id"], "worker": row["worker"],
                 "report_id": row["report_id"], "completed_at": str(row["completed_at"])} for row in candidates]
    if now >= stable_by and all(not row["last_error"] and row["last_scan_at"] and row["last_scan_at"] >= stable_by for row in candidates):
        set_state(cursor, incident, now, "resolved", "Tarefas afetadas concluídas no mesmo Worker e período de estabilidade observado.", recovery)
    else:
        set_state(cursor, incident, now, "monitoring", "Conclusão comprovada das tarefas afetadas. Aguardando estabilidade.", recovery)


def window_count(stamps, window):
    """Largest rolling-window count using the original report timestamps."""
    start = maximum = 0
    for end, stamp in enumerate(stamps):
        while stamp - stamps[start] > window:
            start += 1
        maximum = max(maximum, end - start + 1)
    return maximum


def publish_alert(cursor, incident, now, settings):
    if settings.mode == "off":
        return
    incident_id, episode = incident["id"], incident["episode"]
    previous = read(cursor, """
        SELECT detail_json,recorded_at FROM deadline_diagnostic_history
        WHERE incident_id=%s AND episode=%s AND kind=%s ORDER BY id DESC LIMIT 1
        """, (incident_id, episode, f"alert_{settings.mode}"))
    previous_at = previous[0]["recorded_at"] if previous else None
    previous = json.loads(previous[0]["detail_json"]) if previous else {}
    if previous and (previous["action"] == "resolvido" or
                     (incident["state"] != "resolved" and now - previous_at < settings.update_interval)):
        return
    rows = read(cursor, """
        SELECT job_id,job_name,occurred_at,collected_at,observed_since,diagnosis_json FROM deadline_diagnostic_occurrences
        WHERE incident_id=%s AND episode=%s ORDER BY occurred_at,id
        """, (incident_id, episode))
    eligible = []
    for row in rows:
        diagnosis = json.loads(row["diagnosis_json"])
        stamp = row["occurred_at"]
        if stamp and settings.alert_from <= stamp <= now:
            eligible.append((row, diagnosis))
        elif not stamp and diagnosis["severity"] == "CRITICO" and row["observed_since"] and (
            settings.alert_from <= row["observed_since"] <= row["collected_at"] <= now
        ):
            eligible.append((row, diagnosis))
    if not eligible:
        return
    if incident["state"] == "resolved" and not previous:
        return
    count_in_window = window_count([row["occurred_at"] for row, _ in eligible if row["occurred_at"]], settings.window)
    ranks = {"confirmado": 3, "provável": 2, "possível": 1, "indeterminado": 0}
    best, diagnosis = max(reversed(eligible), key=lambda item: (item[1]["severity"] == "CRITICO", ranks[item[1]["confidence"]]))
    if not previous and diagnosis["severity"] != "CRITICO" and count_in_window < settings.threshold:
        return
    state = incident["state"]
    jobs = {row["job_id"]: row["job_name"] for row in rows}
    details = digest(encode([diagnosis, sorted(jobs), state, len(rows)]))
    if state == "resolved":
        action = "resolvido"
    elif not previous:
        action = "aberto"
    elif details != previous["details"] and now - previous_at >= settings.update_interval:
        action = "atualizado"
    else:
        return
    payload = {
        "worker": incident["worker"], "job_name": best["job_name"],
        "job_id": best["job_id"], "jobs_total": len(jobs), "category": CATEGORIES[diagnosis["category"]],
        "diagnosis": diagnosis["diagnosis"], "confidence": diagnosis["confidence"], "severity": diagnosis["severity"],
        "recommendation": diagnosis["recommendation"], "state": state, "occurrences": len(rows),
        "window_count": count_in_window, "window_minutes": int(settings.window.total_seconds() / 60),
        "evidence_summary": diagnosis["technical"], "ram_percent": (
            next((line for line in diagnosis["evidence"] if line.startswith("RAM reportada em ")), None)
        ),
    }
    sequence = previous.get("sequence", 0) + 1
    event_type = f"deadline.incidente.{action}"
    if diagnosis["severity"] == "CRITICO":
        event_type += "_critico"
    key = f"deadline:incident:{incident_id}:{episode}:{settings.mode}:{sequence}"
    event_uuid = str(uuid5(NAMESPACE_URL, key))
    correlation = str(uuid5(NAMESPACE_URL, f"deadline:incident:{incident_id}:{episode}"))
    # Same event envelope as EventPublisher. The occurrence, history and outbox commit together.
    cursor.execute(
        """INSERT INTO flow_connect_events
           (event_uuid,event_type,event_version,source_module,entity_type,entity_id,occurred_at,
            correlation_id,idempotency_key,payload_json,metadata_json,status)
           VALUES (%s,%s,1,'deadline','deadline_incident',%s,%s,%s,%s,%s,%s,'PENDING')""",
        (event_uuid, event_type, str(incident_id), now, correlation, key, encode(payload),
         encode({"flow_connect_mode": settings.mode})),
    )
    history(cursor, now, f"alert_{settings.mode}", {"action": action, "details": details, "sequence": sequence,
                                                   "event_uuid": event_uuid, "window_count": count_in_window},
            incident_id, episode)
