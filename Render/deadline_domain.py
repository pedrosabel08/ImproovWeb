from __future__ import annotations

import re

JOB_ID_RE = re.compile(r"^[a-fA-F0-9]{24}$")

AGUARDANDO_JOB = "AGUARDANDO_JOB"
VINCULADA = "VINCULADA"
EM_ANDAMENTO = "EM_ANDAMENTO"
EM_APROVACAO = "EM_APROVACAO"
ERRO = "ERRO"
APROVADA = "APROVADA"
REPROVADA = "REPROVADA"
REFAZENDO = "REFAZENDO"
EXCLUSAO_PENDENTE = "EXCLUSAO_PENDENTE"
ENCERRADA = "ENCERRADA"
CANCELADA = "CANCELADA"
INCONSISTENTE = "INCONSISTENTE"

OPERATIONAL_STATES = {VINCULADA, EM_ANDAMENTO, EM_APROVACAO, ERRO}
TERMINAL_STATES = {APROVADA, REPROVADA, REFAZENDO, ENCERRADA, CANCELADA, INCONSISTENTE}

ALLOWED_TRANSITIONS = {
    AGUARDANDO_JOB: {VINCULADA, EM_ANDAMENTO, EM_APROVACAO, ERRO, CANCELADA},
    VINCULADA: {EM_ANDAMENTO, EM_APROVACAO, ERRO, CANCELADA, INCONSISTENTE},
    EM_ANDAMENTO: {EM_APROVACAO, ERRO, CANCELADA, INCONSISTENTE},
    ERRO: {EM_ANDAMENTO, EM_APROVACAO, CANCELADA, INCONSISTENTE},
    EM_APROVACAO: {APROVADA, REPROVADA, REFAZENDO, EXCLUSAO_PENDENTE, INCONSISTENTE},
    EXCLUSAO_PENDENTE: {ENCERRADA},
}

BACKOFF_SECONDS = (10, 30, 60, 300, 900)


def valid_job_id(value: object) -> bool:
    return bool(JOB_ID_RE.fullmatch(str(value or "").strip()))


def transition_allowed(current: str, target: str) -> bool:
    return current == target or target in ALLOWED_TRANSITIONS.get(current, set())


def backoff_seconds(attempt_number: int) -> int:
    if attempt_number <= 0:
        return BACKOFF_SECONDS[0]
    index = min(attempt_number - 1, len(BACKOFF_SECONDS) - 1)
    return BACKOFF_SECONDS[index]


def state_from_deadline(job_data: dict, task_data: dict) -> str:
    status = (
        str(job_data.get("Status") or job_data.get("JobStatus") or "").strip().lower()
    )
    task_status = task_data.get("TaskStatus", [])
    if not isinstance(task_status, list):
        task_status = [task_status]
    task_status = {str(item).strip().lower() for item in task_status}
    error_count = 0
    for key in ("TaskErrorCount", "JobErrorCount", "ErrorCount", "Errors"):
        values = task_data.get(key, [])
        values = list(values) if isinstance(values, list) else [values]
        values += job_data.get(key, []) if isinstance(job_data.get(key), list) else [job_data.get(key)]
        for value in values:
            try:
                error_count = max(error_count, int(value))
            except (TypeError, ValueError):
                pass
    if status in {"failed", "error"} or "failed" in task_status:
        return ERRO
    if status in {"completed", "complete", "succeeded"}:
        return EM_APROVACAO
    if error_count > 0:
        return ERRO
    if status in {"active", "rendering", "queued", "pending"} or task_status & {
        "rendering",
        "queued",
    }:
        return EM_ANDAMENTO
    return VINCULADA


def choose_unambiguous_candidates(
    candidates: list[dict],
) -> tuple[list[dict], dict[int, list[dict]]]:
    """Return one candidate per attempt; conflicts are never selected implicitly."""
    grouped: dict[int, list[dict]] = {}
    for candidate in candidates:
        grouped.setdefault(int(candidate["tentativa_id"]), []).append(candidate)
    selected = [items[0] for items in grouped.values() if len(items) == 1]
    conflicts = {
        attempt_id: items for attempt_id, items in grouped.items() if len(items) > 1
    }
    return selected, conflicts


def delete_output_means_not_found(output: str) -> bool:
    text = (output or "").lower()
    if "repository path" in text or "deadlineconfigexception" in text:
        return False
    missing = r"(?:not found|does not exist|could not be found|cannot find|nao encontrado|não encontrado|nao existe|não existe)"
    job = r"\bjob(?:\s+id)?\b"
    return bool(
        re.search(job + r".{0,120}" + missing, text, re.DOTALL)
        or re.search(missing + r".{0,120}" + job, text, re.DOTALL)
    )
