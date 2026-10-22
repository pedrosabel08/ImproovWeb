<?php
require_once dirname(__DIR__, 2) . '/config/session_bootstrap.php';

if (empty($_SESSION['logado'])) {
    flow_auth_redirect_to_login();
    exit;
}




// Compatibilidade com favoritos antigos: a Overview agora é uma seção da home.
header('Location: ../../inicio.php');
exit;
