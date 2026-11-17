"""Fixed Deadline SDK bridge, invoked only by the Flow worker."""

import json
import re
from System import DateTimeKind

from Deadline.Scripting import RepositoryUtils as repo


def job_state(job):
    value = str(job.GetJobExtraInfoKeyValue("FlowRequiredRamBytes"))
    return {
        "status": str(job.JobStatus).lower(),
        "pool": str(job.JobPool),
        "secondary": str(job.JobSecondaryPool),
        "group": str(job.JobGroup),
        "allow": bool(job.JobWhitelistFlag),
        "listed": sorted(map(str, job.JobListedSlaves)),
        "maximum": int(job.JobMachineLimit),
        "release": float(job.JobMachineLimitProgress),
        "required_ram": (
            int(value) if value.isdigit() and 0 < int(value) <= 2**60 else None
        ),
    }


def run(job_id, request):
    job = repo.GetJob(job_id, True)
    if job is None or str(job.JobId) != job_id:
        raise RuntimeError("Job not found")
    state = job_state(job)
    if request["mode"] == "prepare_resubmit":
        if state["status"] != "suspended":
            raise RuntimeError("The new job must be suspended before linking")
        job.JobName = request["name"]
        job.SetJobExtraInfoKeyValue("FlowSubmissionUUID", "")
        job.SetJobExtraInfoKeyValue("FlowRenderEFRequest", "")
        job.SetJobExtraInfoKeyValue("FlowResubmitRequest", str(request["request_id"]))
        repo.SaveJob(job)
        saved = repo.GetJob(job_id, True)
        if (
            saved is None
            or str(saved.JobName) != request["name"]
            or str(saved.JobStatus).lower() != "suspended"
            or str(saved.GetJobExtraInfoKeyValue("FlowResubmitRequest"))
            != str(request["request_id"])
        ):
            raise RuntimeError("The new job settings could not be verified")
        return {"saved": True}
    if request["mode"] == "route":
        if state != request["before"] or state["status"] not in (
            "active",
            "suspended",
            "failed",
            "pending",
        ):
            raise RuntimeError("Job changed before routing update")
        pool, group = request["pool"], request["group"]
        if (
            pool != state["pool"] and pool != "none" and pool not in repo.GetPoolNames()
        ) or (
            group != state["group"]
            and group != "none"
            and group not in repo.GetGroupNames()
        ):
            raise RuntimeError("Unknown pool/group")
        job.JobPool = pool
        job.JobGroup = group
        repo.SaveJob(job)
        return {"saved": True}
    if request["mode"] not in ("state", "inspect"):
        raise RuntimeError("Unknown recovery operation")
    if state["status"] == "completed" and not (job.JobTileJob or job.JobMaintenanceJob):
        state["resubmit"] = {
            "name": str(job.JobName),
            "frames": str(job.JobFrames),
            "chunk": int(job.JobFramesPerTask),
        }
    state["tasks"] = [
        {
            "id": int(t.TaskId),
            "status": str(t.TaskStatus).lower(),
            "worker": str(t.TaskSlaveName or ""),
        }
        for t in repo.GetJobTasks(job, True).TaskCollectionAllTasks
    ]
    if request["mode"] == "inspect":
        state["pools"] = sorted(set(map(str, repo.GetPoolNames())) | {"none"})
        state["groups"] = sorted(set(map(str, repo.GetGroupNames())) | {"none"})
        state["limits"] = []
        for name in sorted(
            set(map(str, job.JobLimitGroups))
            | set(map(str, repo.GetPluginLimitGroups(job.JobPlugin)))
        ):
            limit = repo.GetLimitGroup(name, True)
            if limit is None:
                raise RuntimeError("Unresolved job/plugin limit")
            state["limits"].append(
                {
                    "allow": bool(limit.LimitGroupWhitelistFlag),
                    "listed": list(map(str, limit.LimitGroupListedSlaves)),
                    "excluded": list(map(str, limit.LimitGroupExcludedSlaves)),
                    "available": bool(limit.LimitGroupUnlimitedLimit)
                    or limit.LimitGroupLimit > 0,
                }
            )
        if request.get("reports"):
            reports = repo.GetJobReports(job_id)

            def report(r, kind):
                stamp = r.ReportDateTimeOf
                at = (
                    str(stamp.ToUniversalTime().ToString("yyyy-MM-dd HH:mm:ss.ffffff"))
                    if stamp.Year >= 2000 and stamp.Kind != DateTimeKind.Unspecified
                    else None
                )
                return {
                    "id": str(r.ReportID),
                    "worker": str(r.ReportSlaveName or ""),
                    "kind": kind,
                    "task": int(r.ReportTaskID),
                    "at": at,
                    "message": str(r.ReportMessage or "")[:4000],
                }

            errors = [report(r, "error") for r in reports.GetErrorReports()]
            errors.sort(key=lambda item: (item["at"] or "", item["id"]), reverse=True)
            logs = [report(r, "log") for r in reports.GetLogReports()]
            logs.sort(key=lambda item: (item["at"] or "", item["id"]), reverse=True)
            state["reports"] = errors[:50] + logs[:50]
            state["report_count"] = len(errors) + len(logs)
    return state


def __main__(*args):
    job_id, source, destination = args
    if not re.fullmatch(r"[0-9a-f]{24}", job_id):
        raise ValueError("Invalid job ID")
    with open(source, encoding="utf-8") as handle:
        request = json.load(handle)
    result = run(job_id, request)
    with open(destination, "w", encoding="utf-8") as handle:
        json.dump({"job_id": job_id, "data": result}, handle, ensure_ascii=False)
