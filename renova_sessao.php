<?php
$tempoSessao = 3600; // 1 hora

// Detecta se a conexão é segura (HTTPS) para definir o flag 'secure' corretamente
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

session_set_cookie_params([
    'lifetime' => $tempoSessao,
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax' // ou 'Strict' se não estiver usando links externos
]);

session_start();

// Salva o momento em que a sessão foi renovada
$_SESSION['ultimo_renovado'] = date('H:i:s');

// Renova o cookie de sessão estendendo a validade
setcookie(session_name(), session_id(), [
    'expires' => time() + $tempoSessao,
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

echo 'Sessão renovada às ' . $_SESSION['ultimo_renovado'];
