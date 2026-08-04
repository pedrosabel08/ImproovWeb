"""Small JSON bridge used by the Render listing to read Deadline job progress.

The PHP endpoint starts this once per request, sending all listed job IDs at
once.  Keeping the Deadline interaction here avoids duplicating its parser in
PHP and lets a page request deduplicate IDs before invoking Deadline.
"""

from __future__ import annotations

import json
import logging
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed

from deadline_client import DeadlineClient
from deadline_config import SETTINGS
from deadline_domain import valid_job_id

LOGGER = logging.getLogger("deadline_progress")


def _read_job_progress(
    client: DeadlineClient, job_id: str
) -> tuple[str, dict, str | None]:
    aggregate_result, aggregate = client.get_task_progress(job_id)
    tasks_result, tasks = client.get_job_tasks_progress(job_id)
    if aggregate_result.success or tasks_result.success:
        return (
            job_id,
            {
                "job_progress": aggregate.get("job_progress"),
                "task_progress": tasks.get("task_progress"),
                "task_render_status": tasks.get("task_render_status"),
                "task_render_summary": tasks.get("task_render_summary"),
                "task_elapsed": tasks.get("task_elapsed"),
                "task_time_remaining": tasks.get("task_time_remaining"),
                "estimated_time_remaining": aggregate.get("estimated_time_remaining"),
            },
            None if aggregate_result.success and tasks_result.success else "partial",
        )

    # The page intentionally receives no internal command output. It can render
    # an unavailable state while the server log retains a controlled diagnostic.
    return (
        job_id,
        {
            "job_progress": None,
            "task_progress": None,
            "task_render_status": None,
            "task_render_summary": None,
            "task_elapsed": None,
            "task_time_remaining": None,
            "estimated_time_remaining": None,
        },
        "unavailable",
    )


def main() -> int:
    try:
        request = json.load(sys.stdin)
    except (json.JSONDecodeError, TypeError):
        json.dump({"results": {}, "errors": {}}, sys.stdout)
        return 0

    raw_job_ids = request.get("job_ids", []) if isinstance(request, dict) else []
    job_ids = list(
        dict.fromkeys(
            str(job_id).strip()
            for job_id in raw_job_ids
            if valid_job_id(str(job_id).strip())
        )
    )

    if not job_ids:
        json.dump({"results": {}, "errors": {}}, sys.stdout)
        return 0

    client = DeadlineClient(SETTINGS.deadline_command, SETTINGS.command_timeout)
    results: dict[str, dict] = {}
    errors: dict[str, str] = {}
    max_workers = min(8, len(job_ids))

    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        futures = [
            executor.submit(_read_job_progress, client, job_id) for job_id in job_ids
        ]
        for future in as_completed(futures):
            try:
                job_id, progress, error = future.result()
            except Exception:
                LOGGER.exception("Deadline progress read failed")
                continue
            results[job_id] = progress
            if error:
                errors[job_id] = error

    json.dump({"results": results, "errors": errors}, sys.stdout)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
