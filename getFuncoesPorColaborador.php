<?php
header('Content-Type: application/json');

// Conectar ao banco de dados
$conn = new mysqli('192.168.0.202', 'admin', 'admin', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$colaboradorId = intval($_GET['colaborador_id']);
$dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';

// Consulta para obter funções e status da imagem para o colaborador selecionado
$sql = "SELECT
            ico.imagem_nome,
            fi.status,
            fi.prazo
        FROM funcao_imagem fi
        JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
        WHERE fi.colaborador_id = ?";

if ($dataInicio) {
    $sql .= " AND fi.prazo >= ?";
}
if ($dataFim) {
    $sql .= " AND fi.prazo <= ?";
}

$stmt = $conn->prepare($sql);
if ($dataInicio && $dataFim) {
    $stmt->bind_param('iss', $colaboradorId, $dataInicio, $dataFim);
} elseif ($dataInicio) {
    $stmt->bind_param('is', $colaboradorId, $dataInicio);
} elseif ($dataFim) {
    $stmt->bind_param('is', $colaboradorId, $dataFim);
} else {
    $stmt->bind_param('i', $colaboradorId);
}

$stmt->execute();
$result = $stmt->get_result();

$funcoes = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $funcoes[] = $row;
    }
}

echo json_encode($funcoes);

$stmt->close();
$conn->close();
