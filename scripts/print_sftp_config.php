<?php
require __DIR__ . '/../config/secure_env.php';

try {
    $cfg = improov_sftp_config();
    echo json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
