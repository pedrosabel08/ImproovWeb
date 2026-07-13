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

    def delete_job(self, job_id: str) -> CommandResult:
        if not valid_job_id(job_id):
            return CommandResult(False, "invalid job id")
        result = self.run(["DeleteJob", job_id])
        if result.not_found:
            result.success = True
        return result
