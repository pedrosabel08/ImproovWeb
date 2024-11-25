<?php
session_start();

// Função para verificar o nível de acesso exato
function verificaNivelAcesso($nivelExato)
{
    // Verifica se o usuário está logado e se possui um nível de acesso
    if (!isset($_SESSION['logado']) || !$_SESSION['logado'] || !isset($_SESSION['nivel_acesso'])) {
        header("Location: login.php"); // Redireciona para a página de login
        exit;
    }

    // Verifica se o nível de acesso é exatamente o permitido
    if ($_SESSION['nivel_acesso'] != $nivelExato) {
        header("Location: acesso_negado.php"); // Redireciona para a página de "Acesso Negado"
        exit;
    }
}
