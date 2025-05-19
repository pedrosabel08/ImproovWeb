<?php
$tempoSessao = 3600; // 1 hora

session_set_cookie_params([
    'lifetime' => $tempoSessao,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax' // ou 'Strict' se não estiver usando links externos
]);

session_start();

// Salva o momento em que a sessão foi renovada
$_SESSION['ultimo_renovado'] = date('H:i:s');

// Renova o cookie
setcookie(session_name(), session_id(), [
    'expires' => time() + $tempoSessao,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

echo 'Sessão renovada às ' . $_SESSION['ultimo_renovado'];
