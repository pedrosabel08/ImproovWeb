<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
session_start();
require_once __DIR__ . '/../conexao.php';

// Revoga token no DB se existir cookie
if (isset($_COOKIE['flow_auth']) && !empty($_COOKIE['flow_auth'])) {
    $token = $_COOKIE['flow_auth'];
    $stmt = $conn->prepare('DELETE FROM login_tokens WHERE token_hash = SHA2(?,256)');
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();
    }
    // Expira cookie
    setcookie('flow_auth', '', time() - 3600, '/', '');
}

// Destrói sessão
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: login.php');
exit();
