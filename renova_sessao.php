<?php
$tempoSessao = 3600; // 1 hora em segundos

// Reaplica os mesmos parâmetros da sessão
session_set_cookie_params($tempoSessao);
ini_set('session.gc_maxlifetime', $tempoSessao);

session_start();

// Opcional: Atualiza o tempo de expiração do cookie do navegador
setcookie(session_name(), session_id(), time() + $tempoSessao);

echo 'ok';
