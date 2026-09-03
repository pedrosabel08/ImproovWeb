<?php
require_once dirname(__DIR__, 2) . '/config/session_bootstrap.php';

if (empty($_SESSION['logado'])) {
    header('Location: ../../index.html');
    exit;
}

// Compatibilidade com favoritos antigos: a Overview agora é uma seção da home.
header('Location: ../../inicio.php');
exit;
