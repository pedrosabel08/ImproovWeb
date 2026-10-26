<?php

require_once __DIR__ . '/database.php';

function improov_db_version_connect(): ?mysqli
{
    try {
        return improov_db_connect();
    } catch (Throwable $e) {
        return null;
    }
}
