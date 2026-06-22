#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Teste independente de leitura do Amazon Thinkbox Deadline.

Este script e somente leitura: nao acessa banco, FTP ou qualquer sistema do Flow.
Ele consulta o Deadline via `deadlinecommand`, resume o job informado e lista JPGs
encontrados na pasta de saida convertida para o compartilhamento de rede.
"""

from __future__ import annotations

import glob
import locale
import os
import re
import subprocess
from dataclasses import dataclass
from datetime import datetime
from typing import Any, Iterable


JOB_ID = "6a313eb09b5d2b1b57a93ee5"

COMMAND_TIMEOUT_SECONDS = 60
OK_MARK = "\u2713"
FAIL_MARK = "\u2717"

DRIVE_MAP = {
    "M:": r"\\192.168.0.250\renders2",
    "Y:": r"\\192.168.0.250\renders",
    "N:": r"\\192.168.0.250\exchange",
}


@dataclass
class CommandResult:
    """Resultado controlado de uma chamada ao deadlinecommand."""

    ok: bool
    command: list[str]
    stdout: str = ""
    stderr: str = ""
    returncode: int | None = None
    error_message: str = ""


def run_deadline_command(args: list[str], timeout: int = COMMAND_TIMEOUT_SECONDS) -> CommandResult:
    """Executa `deadlinecommand` com timeout e sem abrir shell."""

    command = ["deadlinecommand", *args]
    encoding = locale.getpreferredencoding(False) or "utf-8"

    try:
        completed = subprocess.run(
            command,
            capture_output=True,
            text=True,
            encoding=encoding,
            errors="replace",
            timeout=timeout,
            check=False,
        )
    except FileNotFoundError:
        return CommandResult(
            ok=False,
            command=command,
            error_message=(
                "deadlinecommand nao foi encontrado no PATH. "
                "Confirme o terminal/VS Code usado para executar o teste."
            ),
        )
    except subprocess.TimeoutExpired as exc:
        stdout = exc.stdout if isinstance(exc.stdout, str) else ""
        stderr = exc.stderr if isinstance(exc.stderr, str) else ""
        return CommandResult(
            ok=False,
            command=command,
            stdout=stdout,
            stderr=stderr,
            error_message=f"Tempo limite excedido apos {timeout} segundos.",
        )
    except OSError as exc:
        return CommandResult(
            ok=False,
            command=command,
            error_message=f"Falha ao executar deadlinecommand: {exc}",
        )

    stdout = completed.stdout or ""
    stderr = completed.stderr or ""
    combined = f"{stdout}\n{stderr}".strip().lower()

    if completed.returncode != 0:
        return CommandResult(
            ok=False,
            command=command,
            stdout=stdout,
            stderr=stderr,
            returncode=completed.returncode,
            error_message=f"deadlinecommand retornou codigo {completed.returncode}.",
        )

    if "could not find job" in combined or "job does not exist" in combined or "invalid job" in combined:
        return CommandResult(
            ok=False,
            command=command,
            stdout=stdout,
            stderr=stderr,
            returncode=completed.returncode,
            error_message="Job nao encontrado no Deadline.",
        )

    return CommandResult(
        ok=True,
        command=command,
        stdout=stdout,
        stderr=stderr,
        returncode=completed.returncode,
    )


def parse_deadline_output(output: str) -> dict[str, Any]:
    """
    Converte linhas `Chave=Valor` em dicionario.

    Se uma chave aparecer mais de uma vez, os valores sao preservados em lista.
    Linhas inesperadas ficam em `_raw_lines` para facilitar diagnostico.
    """

    data: dict[str, Any] = {}
    raw_lines: list[str] = []

    for original_line in output.splitlines():
        line = original_line.strip()
        if not line:
            continue

        if "=" not in line:
            raw_lines.append(line)
            continue

        key, value = line.split("=", 1)
        key = key.strip()
        value = value.strip()

        if not key:
            raw_lines.append(line)
            continue

        if key in data:
            current = data[key]
            if isinstance(current, list):
                current.append(value)
            else:
                data[key] = [current, value]
        else:
            data[key] = value

    if raw_lines:
        data["_raw_lines"] = raw_lines

    return data


def get_job(job_id: str) -> tuple[CommandResult, dict[str, Any]]:
    """Busca os dados gerais do job."""

    result = run_deadline_command(["-GetJob", job_id, "True"])
    return result, parse_deadline_output(result.stdout) if result.stdout else {}


def get_task(job_id: str) -> tuple[CommandResult, dict[str, Any]]:
    """Busca as tarefas do job."""

    result = run_deadline_command(["-GetJobTasks", job_id])
    return result, parse_deadline_output(result.stdout) if result.stdout else {}


def _as_list(value: Any) -> list[str]:
    if value is None:
        return []
    if isinstance(value, list):
        return [str(item) for item in value]
    return [str(value)]


def _first_value(data: dict[str, Any], keys: Iterable[str], default: str = "-") -> str:
    for key in keys:
        values = _as_list(data.get(key))
        for value in values:
            if value:
                return value
    return default


def _format_deadline_datetime(value: str) -> str:
    if not value or value == "-":
        return "-"

    candidates = [
        "%Y-%m-%d %H:%M:%S",
        "%Y-%m-%d %H:%M:%S.%f",
        "%m/%d/%Y %H:%M:%S",
        "%m/%d/%Y %I:%M:%S %p",
        "%d/%m/%Y %H:%M:%S",
    ]

    cleaned = value.replace("T", " ").strip()
    for fmt in candidates:
        try:
            return datetime.strptime(cleaned, fmt).strftime("%d/%m/%Y %H:%M:%S")
        except ValueError:
            continue

    return value


def _has_errors(job_data: dict[str, Any], task_data: dict[str, Any]) -> tuple[bool, int]:
    counts: list[int] = []

    for key in ("TaskErrorCount", "JobErrorCount", "ErrorCount", "Errors"):
        for value in _as_list(task_data.get(key)) + _as_list(job_data.get(key)):
            try:
                counts.append(int(value))
            except (TypeError, ValueError):
                continue

    failed_status = any(value.lower() == "failed" for value in _as_list(task_data.get("TaskStatus")))
    error_count = max(counts) if counts else 0

    return error_count > 0 or failed_status, error_count


def extract_render_output(job_data: dict[str, Any], raw_output: str) -> str | None:
    """Localiza RenderOutput, inclusive quando vier dentro de PluginInfoDictionary."""

    direct_value = _first_value(job_data, ("RenderOutput",), default="")
    if direct_value:
        return direct_value

    plugin_values = _as_list(job_data.get("PluginInfoDictionary"))
    plugin_text = "\n".join(plugin_values + [raw_output])

    for line in plugin_text.splitlines():
        match = re.search(r"(?:^|[;,{]\s*)RenderOutput\s*=\s*(.+)$", line)
        if match:
            value = match.group(1).strip().strip("'\"")
            value = re.split(r"\s*[;,}]\s*[A-Za-z0-9_]+\s*=", value, maxsplit=1)[0].strip()
            return value or None

    return None


def convert_network_path(path: str) -> str:
    """Converte os drives conhecidos para caminhos UNC."""

    if not path:
        return path

    normalized = path.strip().strip("'\"")
    drive = normalized[:2].upper()
    replacement = DRIVE_MAP.get(drive)
    if not replacement:
        return normalized

    remainder = normalized[2:].lstrip("\\/")
    if not remainder:
        return replacement

    return replacement + "\\" + remainder.replace("/", "\\")


def _folder_candidate(path: str) -> str:
    """
    Usa a pasta do RenderOutput quando ele parece apontar para arquivo/padrao.

    Exemplos comuns: image_####.jpg, frame.%04d.exr ou arquivo com extensao.
    """

    if not path:
        return path

    basename = os.path.basename(path)
    has_frame_pattern = any(token in basename for token in ("#", "%0", "<FRAME>", "$F"))
    has_extension = bool(os.path.splitext(basename)[1])

    if has_frame_pattern or has_extension:
        return os.path.dirname(path)

    return path


def find_jpgs(folder_path: str) -> list[str]:
    """Lista JPG/JPEG diretamente na pasta informada."""

    if not folder_path or not os.path.exists(folder_path):
        return []

    jpgs = glob.glob(os.path.join(folder_path, "*.jpg"))
    jpgs.extend(glob.glob(os.path.join(folder_path, "*.jpeg")))

    return sorted({os.path.basename(path) for path in jpgs}, key=str.lower)


def _print_command_failure(title: str, result: CommandResult) -> None:
    print(f"\n{title}:")
    print(result.error_message or "Falha inesperada ao executar o comando.")
    print("Comando:", " ".join(result.command))
    if result.stderr.strip():
        print("Detalhe:", result.stderr.strip())
    elif result.stdout.strip():
        print("Saida:", result.stdout.strip())


def print_summary(
    job_id: str,
    job_data: dict[str, Any],
    task_data: dict[str, Any],
    render_output: str | None,
    converted_path: str | None,
    folder_path: str | None,
    folder_exists: bool,
    jpgs: list[str],
    has_error: bool,
    error_count: int,
) -> None:
    """Imprime o resumo organizado no terminal."""

    print("=" * 57)
    print("DEADLINE JOB")
    print("=" * 57)
    print()
    print("Job ID:")
    print(job_id)
    print()
    print("Nome:")
    print(_first_value(job_data, ("Name", "JobName")))
    print()
    print("Status:")
    print(_first_value(job_data, ("Status", "JobStatus")))
    print()
    print("Submit:")
    print(_format_deadline_datetime(_first_value(job_data, ("SubmitDateTime", "SubmitDate"))))
    print()
    print("Completed:")
    print(_format_deadline_datetime(_first_value(job_data, ("CompletedDateTime", "CompletedDate", "CompletionDate"))))
    print()
    print("Ultima atualizacao:")
    print(_format_deadline_datetime(_first_value(job_data, ("LastWriteTime", "LastUpdatedDateTime", "LastUpdated"))))
    print()
    print("Worker:")
    print(_first_value(task_data, ("TaskSlaveName", "SlaveName", "WorkerName", "MachineName")))
    print()
    print("Erro:")
    print("Sim" if has_error else "Nao")
    print()
    print("Quantidade de erros:")
    print(error_count)
    print()
    print("Render Time:")
    render_time_data = dict(job_data)
    render_time_data.update(task_data)
    print(_first_value(render_time_data, ("RenderTime", "JobRenderTime", "TaskRenderTime", "ElapsedTime", "TimeToComplete")))
    print()
    print("=" * 57)
    print()
    print("RenderOutput:")
    print(render_output or "RenderOutput nao encontrado.")
    print()
    print("Pasta convertida:")
    print(converted_path or "Nao foi possivel converter porque RenderOutput nao foi encontrado.")
    print()

    if folder_path and folder_path != converted_path:
        print("Pasta verificada:")
        print(folder_path)
        print()

    if not folder_path:
        print(f"{FAIL_MARK} Pasta nao verificada")
    elif folder_exists:
        print(f"{OK_MARK} Pasta encontrada")
    else:
        print(f"{FAIL_MARK} Pasta nao encontrada")

    print()

    if folder_exists:
        print(f"{len(jpgs)} JPG(s) encontrados")
        print()
        for jpg in jpgs:
            print(jpg)


def _print_reports(job_id: str, has_error: bool) -> None:
    if not has_error:
        return

    print()
    print("=" * 57)
    print("RELATORIOS DE ERRO/LOG")
    print("=" * 57)

    report_commands = [
        ("Error reports", ["GetJobErrorReportFilenames", job_id]),
        ("Log reports", ["GetJobLogReportFilenames", job_id]),
    ]

    for title, args in report_commands:
        result = run_deadline_command(args)
        print()
        print(f"{title}:")
        if result.ok:
            print(result.stdout.strip() or "(sem resultado)")
        else:
            print(result.error_message or "Nao foi possivel obter os relatorios.")
            if result.stderr.strip():
                print(result.stderr.strip())


def main() -> None:
    print("Iniciando teste de leitura do Deadline...")
    print("Nenhum dado sera alterado no banco, FTP, Flow ou Deadline.")
    print()

    job_result, job_data = get_job(JOB_ID)
    if not job_result.ok:
        _print_command_failure("Falha ao consultar o job", job_result)
        return

    if not job_data:
        print("A consulta do job retornou vazia ou em formato inesperado.")
        print("Saida original:")
        print(job_result.stdout.strip() or "(sem saida)")
        return

    task_result, task_data = get_task(JOB_ID)
    if not task_result.ok:
        _print_command_failure("Falha ao consultar as tasks", task_result)
        task_data = {}
    elif not task_data:
        print("Aviso: a consulta de tasks retornou vazia ou em formato inesperado.")
        task_data = {}

    has_error, error_count = _has_errors(job_data, task_data)
    render_output = extract_render_output(job_data, job_result.stdout)
    converted_path = convert_network_path(render_output) if render_output else None
    folder_path = _folder_candidate(converted_path) if converted_path else None
    folder_exists = bool(folder_path and os.path.exists(folder_path))
    jpgs = find_jpgs(folder_path) if folder_exists and folder_path else []

    print_summary(
        job_id=JOB_ID,
        job_data=job_data,
        task_data=task_data,
        render_output=render_output,
        converted_path=converted_path,
        folder_path=folder_path,
        folder_exists=folder_exists,
        jpgs=jpgs,
        has_error=has_error,
        error_count=error_count,
    )

    _print_reports(JOB_ID, has_error)


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\nExecucao cancelada pelo usuario.")
    except Exception as exc:  # pragma: no cover - protecao final para teste manual.
        print("\nOcorreu uma falha inesperada, mas o script foi encerrado com seguranca.")
        print(f"Detalhe: {exc}")
