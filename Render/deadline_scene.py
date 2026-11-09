"""NAS allowlist and file integrity. Scenes are never opened by this module."""
from __future__ import annotations

import hashlib
import ntpath
import os
from pathlib import Path

CLIENTS_ROOT = r"\\192.168.0.250\clientes"


def nas_scene_path(value: str) -> str:
    raw = str(value or "").replace("/", "\\")
    if raw[:2].upper() == "Z:":
        raw = CLIENTS_ROOT + raw[2:]
    if any(ord(c) < 32 for c in raw) or any(p in {".", ".."} for p in raw.split("\\")):
        raise ValueError("Caminho da cena inválido.")
    path = ntpath.normpath(raw)
    root = ntpath.normcase(CLIENTS_ROOT) + "\\"
    if not ntpath.normcase(path).startswith(root) or ":" in path or not path.lower().endswith(".max"):
        raise ValueError("A cena deve ser um .max do compartilhamento clientes autorizado.")
    return path


def fingerprint(path: str) -> dict:
    stat = os.stat(path)
    if not os.path.isfile(path) or stat.st_size <= 0:
        raise RuntimeError("O arquivo .max está vazio ou não é um arquivo regular.")
    digest = hashlib.sha256()
    with open(path, "rb") as scene:
        for block in iter(lambda: scene.read(4 * 1024 * 1024), b""):
            digest.update(block)
    after=os.stat(path)
    if (after.st_size,after.st_mtime_ns)!=(stat.st_size,stat.st_mtime_ns):
        raise RuntimeError('O arquivo mudou durante a verificação de integridade.')
    return {"size": stat.st_size, "mtime_ns": stat.st_mtime_ns, "sha256": digest.hexdigest()}


def assert_scene_access(path: str) -> dict:
    path = nas_scene_path(path)
    resolved = os.path.realpath(path)
    # Junctions/symlinks cannot escape the allowlisted share.
    nas_scene_path(resolved)
    try:
        return fingerprint(path)
    except PermissionError as exc:
        raise RuntimeError("A conta do worker não tem permissão para ler o .max no NAS.") from exc
    except FileNotFoundError as exc:
        raise RuntimeError("O .max registrado não foi encontrado no NAS.") from exc


def inspect_scene(*_args, **_kwargs):
    """Compatibility guard: local Batch inspection is permanently forbidden."""
    raise RuntimeError("Inspeção de cenas desativada. Utilize o snapshot do job aprovado.")


