<?php
session_start();

include_once __DIR__ . '/conexao.php';

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

// Bloqueio global por contrato não assinado
function bloquearAcessoSeContratoPendente(): void
{
    if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
        return;
    }

    $idcolaborador = isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : 0;
    if (!$idcolaborador) {
        return;
    }

    $conn = conectarBanco();
    $sql = "SELECT status FROM contratos WHERE colaborador_id = ? ORDER BY competencia DESC, id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $idcolaborador);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row || $row['status'] !== 'assinado') {
            $conn->close();
            header("Location: acesso_negado.php");
            exit;
        }
    }
    $conn->close();
}
