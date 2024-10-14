<?php
header('Content-Type: application/json');

$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

$colaboradorId = intval($_GET['colaborador_id']);
$obraId = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : null;

$sql = "SELECT f.idfuncao_imagem, ico.imagem_nome, f.pagamento, f.valor, fun.nome_funcao, f.status
FROM funcao_imagem f 
INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = f.imagem_id 
INNER JOIN colaborador c ON c.idcolaborador = f.colaborador_id 
INNER JOIN funcao fun ON f.funcao_id = fun.idfuncao
WHERE f.status <> 'Não iniciado' AND f.status <> 'Não se aplica' AND c.idcolaborador = ?";

if ($obraId) {
    $sql .= " AND ico.obra_id = ?";
}

$stmt = $conn->prepare($sql);

if ($obraId) {
    $stmt->bind_param('ii', $colaboradorId, $obraId);
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
