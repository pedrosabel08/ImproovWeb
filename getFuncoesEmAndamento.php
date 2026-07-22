<?php
require_once __DIR__ . '/config/session_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'conexao.php';

if (!isset($_SESSION['idcolaborador'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Usuário não autenticado.']);
    exit;
}

$sessionColaboradorId = (int) ($_SESSION['idcolaborador'] ?? 0);
$requestedColaboradorId = isset($_POST['idcolaborador'])
    ? (int) $_POST['idcolaborador']
    : $sessionColaboradorId;

$colaboradorId = $sessionColaboradorId;
if (
    $requestedColaboradorId > 0 &&
    $requestedColaboradorId !== $sessionColaboradorId &&
    in_array($sessionColaboradorId, [9, 21], true)
) {
    $colaboradorId = $requestedColaboradorId;
}

$sql = "SELECT fi.idfuncao_imagem,
               fi.funcao_id,
               fi.prazo,
               f.nome_funcao,
               ico.imagem_nome,
               o.nomenclatura
        FROM funcao_imagem fi
        JOIN funcao f ON fi.funcao_id = f.idfuncao
        JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
        JOIN obra o ON ico.obra_id = o.idobra
        WHERE fi.colaborador_id = ?
          AND fi.status = 'Em andamento'
          AND fi.prazo < CURDATE()
        ORDER BY fi.prazo ASC, fi.idfuncao_imagem ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $colaboradorId);
$stmt->execute();
$result = $stmt->get_result();

$funcoes = [];
while ($row = $result->fetch_assoc()) {
    $funcoes[] = $row;
}

echo json_encode($funcoes, JSON_UNESCAPED_UNICODE);
