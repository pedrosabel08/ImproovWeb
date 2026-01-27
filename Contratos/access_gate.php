<?php
// libera rotas públicas
$current = basename($_SERVER['PHP_SELF'] ?? '');
$public = ['index.html', 'login.php', 'logout.php', 'acesso_negado.php', 'contrato_pendente.php'];
if (in_array($current, $public, true)) {
    return;
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    return;
}

$nivelAcesso = isset($_SESSION['nivel_acesso']) ? (int)$_SESSION['nivel_acesso'] : 0;
if ($nivelAcesso === 1) {
    return;
}

$colaboradorId = isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : 0;
if (!$colaboradorId) {
    return;
}

include_once __DIR__ . '/../conexao.php';

$conn = conectarBanco();
$sql = "SELECT status, competencia FROM contratos WHERE colaborador_id = ? ORDER BY competencia DESC, id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $colaboradorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row || $row['status'] !== 'assinado') {
        $conn->close();
        header('Location: ../acesso_negado.php');
        exit;
    }
}
$conn->close();
