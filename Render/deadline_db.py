from __future__ import annotations

from contextlib import contextmanager

import pymysql
from pymysql.cursors import Cursor, DictCursor


class Database:
    def __init__(self, settings, logger):
        self.settings = settings
        self.logger = logger
        self.connection = None

    def connect(self):
        if self.connection is not None:
            try:
                self.connection.ping(reconnect=False)
                return self.connection
            except Exception:
                self.close()
        self.connection = pymysql.connect(
            host=self.settings.db_host,
            user=self.settings.db_user,
            password=self.settings.db_pass,
            database=self.settings.db_name,
            charset="utf8mb4",
            cursorclass=Cursor,
            autocommit=False,
            connect_timeout=15,
            read_timeout=30,
            write_timeout=30,
        )
        self.logger.info("database connected", extra={"routine": "database"})
        return self.connection

    @contextmanager
    def transaction(self, dict_rows: bool = True):
        conn = self.connect()
        conn.ping(reconnect=False)
        try:
            cursor_class = DictCursor if dict_rows else Cursor
            with conn.cursor(cursor_class) as cursor:
                yield cursor
            conn.commit()
        except Exception:
            conn.rollback()
            raise

    def close(self):
        if self.connection is not None:
            try:
                self.connection.close()
            finally:
                self.connection = None
