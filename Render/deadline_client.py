from __future__ import annotations

import locale
import re
import subprocess
from dataclasses import dataclass

from deadline_domain import delete_output_means_not_found, valid_job_id


@dataclass
class CommandResult:
    success: bool
    output: str
    returncode: int | None = None
    timed_out: bool = False
    not_found: bool = False


def parse_output(output: str) -> dict:
    data: dict[str, object] = {}
    for raw in (output or "").splitlines():
        line = raw.strip()
        if not line or "=" not in line:
            continue
        key, value = line.split("=", 1)
        key, value = key.strip(), value.strip()
        if key in data:
            current = data[key]
            data[key] = (
                current + [value] if isinstance(current, list) else [current, value]
            )
        else:
            data[key] = value
    return data


def _first_value(data: dict, key: str) -> str | None:
    """Return the first value for a Deadline key, including repeated keys."""
    value = data.get(key)
    if isinstance(value, list):
        value = value[0] if value else None
    if value is None:
        return None
    return str(value).strip() or None


def _known_deadline_value(value: str | None) -> str | None:
    normalized = (value or "").strip()
    return normalized if normalized and "?" not in normalized else None


def parse_task_progress(output: str) -> dict[str, float | str | None]:
    """Normalize the aggregate progress output returned by ``-GetTaskProgress``."""
    data = parse_output(output)
    raw_progress = _first_value(data, "JobProgress")
    progress: float | None = None

    if raw_progress is not None:
        normalized = raw_progress.replace("%", "").strip().replace(",", ".")
        try:
            candidate = float(normalized)
            if 0 <= candidate <= 100:
                progress = candidate
        except ValueError:
            pass

    return {
        "job_progress": progress,
        "estimated_time_remaining": _known_deadline_value(
            _first_value(data, "EstimatedJobTimeRemaining")
        ),
    }


def _parse_percent_values(data: dict, key: str) -> list[float]:
    values = data.get(key)
    if values is None:
        return []
    if not isinstance(values, list):
        values = [values]
    parsed: list[float] = []
    for value in values:
        normalized = str(value).replace("%", "").strip().replace(",", ".")
        try:
            candidate = float(normalized)
        except (TypeError, ValueError):
            continue
        if 0 <= candidate <= 100:
            parsed.append(candidate)
    return parsed


def _first_nonempty_value(data: dict, key: str) -> str | None:
    value = data.get(key)
    values = value if isinstance(value, list) else [value]
    for item in values:
        normalized = str(item or "").strip()
        if normalized:
            return normalized
    return None


def parse_task_render_status(value: str | None) -> dict[str, str | None]:
    """Split Deadline's human-readable render status into tooltip fields."""
    raw = _known_deadline_value(value)
    if not raw:
        return {
            "task_render_status": None,
            "task_render_summary": None,
            "task_elapsed": None,
            "task_time_remaining": None,
        }

    match = re.search(
        r"\(\s*elapsed\s*:\s*([^,)]*?)\s*,\s*left\s*:\s*([^)]*?)\s*\)",
        raw,
        flags=re.IGNORECASE,
    )
    if not match:
        return {
            "task_render_status": raw,
            "task_render_summary": raw,
            "task_elapsed": None,
            "task_time_remaining": None,
        }

    summary = raw[: match.start()].strip(" -") or None
    return {
        "task_render_status": raw,
        "task_render_summary": summary,
        "task_elapsed": _known_deadline_value(match.group(1)),
        "task_time_remaining": _known_deadline_value(match.group(2)),
    }


def parse_job_tasks_progress(output: str) -> dict[str, float | str | None]:
    """Normalize the per-task progress values returned by ``-GetJobTasks``."""
    data = parse_output(output)
    # ``Progress`` is the field exposed by Deadline Monitor for the task row.
    # Some Deadline versions also emit ``TaskProgress``; keep it as a fallback
    # for repositories that omit the former field.
    values = _parse_percent_values(data, "Progress")
    if not values:
        values = _parse_percent_values(data, "TaskProgress")
    return {
        "task_progress": sum(values) / len(values) if values else None,
        **parse_task_render_status(_first_nonempty_value(data, "TaskRenderStatus")),
    }


class DeadlineClient:
    def __init__(self, executable: str = "deadlinecommand", timeout: int = 60):
        self.executable = executable
        self.timeout = timeout

    def run(self, args: list[str]) -> CommandResult:
        command = [self.executable, *args]
        encoding = locale.getpreferredencoding(False) or "utf-8"
        try:
            completed = subprocess.run(
                command,
                capture_output=True,
                text=True,
                encoding=encoding,
                errors="replace",
                timeout=self.timeout,
                check=False,
                shell=False,
            )
        except subprocess.TimeoutExpired as exc:
            return CommandResult(False, str(exc), timed_out=True)
        except (FileNotFoundError, OSError) as exc:
            return CommandResult(False, str(exc))
        output = ((completed.stdout or "") + "\n" + (completed.stderr or "")).strip()
        return CommandResult(
            completed.returncode == 0,
            output,
            completed.returncode,
            not_found=delete_output_means_not_found(output),
        )

    def list_job_ids(self) -> tuple[CommandResult, list[str]]:
        result = self.run(["-GetJobs", "True"])
        ids = []
        if result.success:
            ids = list(dict.fromkeys(re.findall(r"\b[a-fA-F0-9]{24}\b", result.output)))
        return result, ids

    def get_job(self, job_id: str) -> tuple[CommandResult, dict]:
        if not valid_job_id(job_id):
            return CommandResult(False, "invalid job id"), {}
        result = self.run(["-GetJob", job_id, "True"])
        data = parse_output(result.output) if result.success else {}
        if result.success and not data:
            result.success = False
            result.not_found = True
            result.output = result.output or f"Job {job_id} returned no data"
        return result, data

    def get_tasks(self, job_id: str) -> tuple[CommandResult, dict]:
        if not valid_job_id(job_id):
            return CommandResult(False, "invalid job id"), {}
        result = self.run(["-GetJobTasks", job_id])
        return result, parse_output(result.output) if result.success else {}

    def get_task_progress(
        self, job_id: str
    ) -> tuple[CommandResult, dict[str, float | str | None]]:
        """Get the aggregate progress and ETA for a Deadline job's tasks."""
        empty = {"job_progress": None, "estimated_time_remaining": None}
        if not valid_job_id(job_id):
            return CommandResult(False, "invalid job id"), empty

        result = self.run(["-GetTaskProgress", job_id])
        return result, parse_task_progress(result.output) if result.success else empty

    def get_job_tasks_progress(
        self, job_id: str
    ) -> tuple[CommandResult, dict[str, float | str | None]]:
        """Get the Monitor-style per-task progress aggregated for one job."""
        empty = {
            "task_progress": None,
            **parse_task_render_status(None),
        }
        if not valid_job_id(job_id):
            return CommandResult(False, "invalid job id"), empty
        result = self.run(["-GetJobTasks", job_id, "True"])
        return result, (
            parse_job_tasks_progress(result.output) if result.success else empty
        )

    def delete_job(self, job_id: str) -> CommandResult:
        if not valid_job_id(job_id):
            return CommandResult(False, "invalid job id")
        result = self.run(["DeleteJob", job_id])
        if result.not_found:
            result.success = True
        return result
