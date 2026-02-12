<?php
// session_debug.php - temporary debug endpoint. Remove when done.
require_once __DIR__ . '/config/session_bootstrap.php';
header('Content-Type: text/plain');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Tue, 01 Jan 2000 00:00:00 GMT');

// Harden session behavior for debug too
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);

session_start();

echo "session.save_path: " . ini_get('session.save_path') . "\n";
echo "session_id(): " . session_id() . "\n\n";

echo "\$_SESSION:\n";
print_r($_SESSION);

echo "\nSession file info:\n";
$path = ini_get('session.save_path');
$sessFile = rtrim($path,'/') . '/sess_' . session_id();
if (file_exists($sessFile)) {
    echo basename($sessFile) . " - " . date('Y-m-d H:i:s', filemtime($sessFile)) . "\n";
    echo "--- contents ---\n";
    echo file_get_contents($sessFile);
} else {
    echo "Session file not found: $sessFile\n";
}

echo "\n--- End debug ---\n";
?>