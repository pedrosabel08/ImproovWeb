from __future__ import annotations

import os
import socket
from dataclasses import dataclass
from pathlib import Path

from dotenv import load_dotenv

BASE_DIR = Path(__file__).resolve().parent
LOCAL_ENV = BASE_DIR / ".env"
if LOCAL_ENV.exists():
    load_dotenv(LOCAL_ENV)
else:
    load_dotenv(r"C:\xampp\htdocs\ScriptsFlow\.env")


def _int(name: str, default: int) -> int:
    try:
        return max(1, int(os.getenv(name, str(default))))
    except ValueError:
        return default


def _bool(name: str, default: bool = False) -> bool:
    value = os.getenv(name)
    if value is None:
        return default
    return value.strip().lower() in {"1", "true", "yes", "sim", "on"}


@dataclass(frozen=True)
class Settings:
    db_host: str = os.getenv("DB_HOST", "")
    db_user: str = os.getenv("DB_USER", "")
    db_pass: str = os.getenv("DB_PASS", "")
    db_name: str = os.getenv("DB_NAME", "")
    deadline_command: str = os.getenv("DEADLINE_COMMAND", "deadlinecommand")
    command_timeout: int = _int("DEADLINE_COMMAND_TIMEOUT", 60)
    queue_interval: int = _int("DEADLINE_QUEUE_INTERVAL_SECONDS", 3)
    active_sync_interval: int = _int("DEADLINE_ACTIVE_SYNC_INTERVAL_SECONDS", 15)
    discovery_interval: int = _int("DEADLINE_DISCOVERY_INTERVAL_SECONDS", 30)
    reconciliation_interval: int = _int("DEADLINE_RECONCILIATION_INTERVAL_SECONDS", 300)
    heartbeat_interval: int = _int("DEADLINE_HEARTBEAT_INTERVAL_SECONDS", 60)
    lock_timeout: int = _int("DEADLINE_COMMAND_LOCK_TIMEOUT_SECONDS", 300)
    worker_id: str = (
        os.getenv("DEADLINE_WORKER_ID", "").strip()
        or f"{socket.gethostname()}-{os.getpid()}"
    )
    mode: str = os.getenv("DEADLINE_WORKER_MODE", "continuous").strip().lower()
    legacy_mode: bool = _bool("DEADLINE_LEGACY_MODE", False)
    delete_dry_run: bool = _bool("DEADLINE_DELETE_DRY_RUN", True)
    log_retention_days: int = _int("DEADLINE_LOG_RETENTION_DAYS", 30)


SETTINGS = Settings()
