<?php
require_once __DIR__ . '/config/session_bootstrap.php';
improov_end_session();

$base = improov_app_base_path();
header('Location: ' . $base . '/index.html');
exit;
