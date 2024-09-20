<?php
header('Content-Type: application/json');

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

$colaboradorId = intval($_GET['colaborador_id']);
$dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$obraId = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : '';

// Consulta para obter funções e status da imagem para o colaborador selecionado
$sql = "SELECT
            ico.imagem_nome,
            fi.status,
            fi.prazo,
			f.nome_funcao
            FROM funcao_imagem fi
        JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
		JOIN obra o on ico.obra_id = o.idobra
		JOIN funcao f on fi.funcao_id = f.idfuncao
        WHERE fi.colaborador_id = ?";

if ($dataInicio) {
    $sql .= " AND fi.prazo >= ?";
}
if ($dataFim) {
    $sql .= " AND fi.prazo <= ?";
}
if ($obraId) {
    $sql .= " AND o.idobra  = ?";
}

$stmt = $conn->prepare($sql);
if ($dataInicio && $dataFim && $obraId) {
    $stmt->bind_param('isss', $colaboradorId, $dataInicio, $dataFim, $obraId);
} elseif ($dataInicio && $obraId) {
    $stmt->bind_param('iss', $colaboradorId, $dataInicio, $obraId);
} elseif ($dataFim && $obraId) {
    $stmt->bind_param('iss', $colaboradorId, $dataFim, $obraId);
} elseif ($dataInicio) {
    $stmt->bind_param('is', $colaboradorId, $dataInicio);
} elseif ($dataFim) {
    $stmt->bind_param('is', $colaboradorId, $dataFim);
} elseif ($obraId) {
    $stmt->bind_param('is', $colaboradorId, $obraId);
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
