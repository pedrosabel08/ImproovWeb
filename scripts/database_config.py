"""Database settings shared by CLI scripts, loaded from the project root .env."""

import os
from pathlib import Path

from dotenv import load_dotenv


def database_config():
    load_dotenv(Path(__file__).resolve().parents[1] / ".env")

    def required(key):
        value = os.getenv(key)
        if value is None or value == "":
            raise RuntimeError(f"Variavel de ambiente obrigatoria ausente: {key}")
        return value

    port_value = os.getenv("DB_PORT", "3306")
    if not port_value.isascii() or not port_value.isdecimal():
        raise RuntimeError("Variavel de ambiente invalida: DB_PORT")
    port = int(port_value)
    if not 1 <= port <= 65535:
        raise RuntimeError("Variavel de ambiente invalida: DB_PORT")

    return {
        "host": required("DB_HOST"),
        "port": port,
        "user": required("DB_USERNAME"),
        "password": required("DB_PASSWORD"),
        "database": required("DB_DATABASE"),
        "charset": "utf8mb4",
    }
