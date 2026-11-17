"""Read-only SDK bridge, executed by DeadlineCommand like the snapshot export."""
import hashlib
import json
from datetime import datetime
from System import DateTime, DateTimeKind

from Deadline.Scripting import RepositoryUtils


def utc(value, unspecified_utc=False):
    # An unspecified .NET DateTime has no source timezone. Preserve that gap.
    if value is None or value.Year < 2000:
        return None
    if str(value.Kind) == "Unspecified":
        if not unspecified_utc:
            return None
        value = DateTime.SpecifyKind(value, DateTimeKind.Utc)
    return str(value.ToUniversalTime().ToString("yyyy-MM-dd HH:mm:ss.ffffff"))


def positive(value):
    return int(value) if value is not None and value > 0 else None


def worker_capacity(worker, job):
    if not worker:
        return {}
    result = {"required_bytes": positive_requirement(job)}
    try:
        info = RepositoryUtils.GetSlaveInfo(worker, True)
        settings = RepositoryUtils.GetSlaveSettings(worker, True)
        if info is not None:
            result.update(physical_bytes=positive(info.MachineMemory),
                          free_bytes=positive(info.MachineFreeMemory))
        if settings is not None:
            result.update(groups=sorted(map(str, settings.SlaveGroups)),
                          pools=sorted(map(str, settings.SlavePools)))
    except Exception:
        result["lookup_unavailable"] = True
    return result


def positive_requirement(job):
    # Optional, explicitly declared Flow convention. Group names do not encode requirements.
    value = str(job.GetJobExtraInfoKeyValue("FlowRequiredRamBytes"))
    return int(value) if value.isdigit() and 0 < int(value) <= 2**60 else None


def recovery_evidence(job, reports, failures, unspecified_utc):
    if not failures:
        return []
    tasks = {str(task.TaskId): task for task in RepositoryUtils.GetJobTasks(job, True).TaskCollectionAllTasks
             if str(task.TaskStatus) == "Completed"}
    success = []
    for report in reports.GetLogReports():
        task_id = str(report.ReportTaskID)
        task = tasks.get(task_id)
        worker = str(report.ReportSlaveName).lower()
        complete = utc(report.ReportDateTimeOf, unspecified_utc)
        if task is None or not complete or not worker or (task_id, worker) not in failures:
            continue
        start = utc(task.TaskRenderStartTime, unspecified_utc)
        end = utc(task.TaskCompletedDateTime, unspecified_utc)
        if (start and end and start <= complete and start <= end and worker == str(task.TaskSlaveName).lower()
                and abs((datetime.fromisoformat(complete) - datetime.fromisoformat(end)).total_seconds()) <= 120
                and task.TaskRenderTime.TotalSeconds > 0 and report.ReportTaskSecondsElapsed > 0):
            success.append({"task_id": task_id, "worker": worker, "started_at": start, "completed_at": end,
                            "report_id": str(report.ReportID)})
    return success


def export(job_id, request):
    job = RepositoryUtils.GetJob(job_id, True)
    deleted = job is None
    if deleted:
        job = RepositoryUtils.GetDeletedJob(job_id)
    if job is None or str(job.JobId) != job_id:
        return {"job_id": job_id, "missing": True}
    reports = RepositoryUtils.GetJobReports(job_id)
    selected = []
    capacities = {}
    errors = []
    failures = {tuple(item) for item in request["failures"]}
    unspecified_utc = request.get("unspecified_utc", False)
    for report in reports.GetErrorReports():
        worker = str(report.ReportSlaveName or "")[:160] or None
        task_id = str(report.ReportTaskID) if report.ReportTaskID >= 0 else None
        metadata = {
            "report_id": str(report.ReportID), "job_id": job_id, "job_name": str(report.ReportJobName)[:255],
            "task_id": task_id, "frame": str(report.ReportFrameString or "")[:255] or None,
            "worker": worker, "occurred_at": utc(report.ReportDateTimeOf, unspecified_utc),
            "message": str(report.ReportMessage or "")[:4000], "plugin": str(report.ReportPluginName or "")[:128] or None,
            "peak_ram": positive(report.ReportPeakRam), "save_error": bool(report.ReportError),
        }
        version = hashlib.sha256(json.dumps(metadata, sort_keys=True).encode("utf-8")).hexdigest()
        known = request["known"].get(metadata["report_id"], [])
        if version != (known[0] if known else None) or not known[1]:
            errors.append((report, metadata, version, bool(known)))
    # Fresh failures take precedence over retrying old reports with inaccessible bodies.
    errors.sort(key=lambda entry: (entry[3], entry[1]["occurred_at"] or "", entry[1]["report_id"]))
    for report, metadata, version, _ in errors[:request["limit"]]:
        if metadata["task_id"] is not None and metadata["worker"]:
            failures.add((metadata["task_id"], metadata["worker"].lower()))
        if metadata["worker"] not in capacities:
            capacities[metadata["worker"]] = worker_capacity(metadata["worker"], job)
        try:
            body = str(RepositoryUtils.GetJobReportLog(report) or "")
        except Exception:
            body = ""
        # Preserve both ends when the application emits very large logs.
        truncated = len(body) > 300_000
        if truncated:
            body = body[:70_000] + "\n[trecho omitido]\n" + body[-230_000:]
        metadata.update(body=body, truncated=truncated, metadata_hash=version,
                        job_group=str(job.JobGroup)[:160], job_pool=str(job.JobPool)[:160],
                        capacity=capacities[metadata["worker"]])
        selected.append(metadata)
    try:
        recovery = [] if deleted else recovery_evidence(job, reports, failures, unspecified_utc)
    except Exception:
        recovery = None
    return {"job_id": job_id, "reports": selected, "backlog": len(errors) > len(selected),
            "recovery": recovery}


def __main__(*args):
    if len(args) != 3:
        raise RuntimeError("Expected Job ID, request and response file paths")
    job_id, request_path, response_path = args
    with open(request_path, encoding="utf-8") as source:
        request = json.load(source)
    with open(response_path, "w", encoding="utf-8") as output:
        json.dump(export(job_id, request), output, ensure_ascii=False)
