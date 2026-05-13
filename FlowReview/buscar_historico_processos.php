<?php
require_once __DIR__ . '/../config/session_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Sessão expirada.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

include_once __DIR__ . '/../conexao.php';

if (!isset($conn) || (isset($conn->connect_error) && $conn->connect_error)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Falha na conexão com o banco.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

$imagemId = filter_input(INPUT_GET, 'imagem_id', FILTER_VALIDATE_INT);
if (!$imagemId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Imagem inválida.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$sql = "SELECT
            fi.idfuncao_imagem,
            fi.imagem_id,
            fi.funcao_id,
            fi.colaborador_id,
            COALESCE(iu.thumb, c.imagem) AS foto_colaborador,
            fi.status,
            COALESCE(fun.nome_funcao, 'Função não identificada') AS nome_funcao,
            COALESCE(c.nome_colaborador, 'Colaborador não informado') AS colaborador_nome,
            hist.data_processo
        FROM funcao_imagem fi
        LEFT JOIN funcao fun ON fun.idfuncao = fi.funcao_id
        LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
        LEFT JOIN usuario u ON u.idcolaborador = c.idcolaborador
        LEFT JOIN informacoes_usuario iu ON iu.usuario_id = u.idusuario
        INNER JOIN (
            SELECT funcao_imagem_id, MAX(data_aprovacao) AS data_processo
            FROM historico_aprovacoes
            GROUP BY funcao_imagem_id
        ) hist ON hist.funcao_imagem_id = fi.idfuncao_imagem
        WHERE fi.imagem_id = ?
        ORDER BY hist.data_processo DESC, fi.idfuncao_imagem DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível preparar a consulta.'
    ], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit();
}

$stmt->bind_param('i', $imagemId);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'items' => $items
], JSON_UNESCAPED_UNICODE);
