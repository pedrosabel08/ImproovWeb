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
$mes = isset($_GET['mes']) ? $_GET['mes'] : '';
$ano = isset($_GET['ano']) ? $_GET['ano'] : '';
$obraId = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : '';
$funcaoId = isset($_GET['funcao_id']) ? intval($_GET['funcao_id']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$prioridade = isset($_GET['prioridade']) ? $_GET['prioridade'] : '';

$sql = "SELECT
            ico.idimagens_cliente_obra AS imagem_id,
            ico.imagem_nome,
            fi.status,
            fi.prazo,
            f.nome_funcao,
            pc.prioridade
        FROM funcao_imagem fi
        JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
        JOIN obra o on ico.obra_id = o.idobra
        JOIN funcao f on fi.funcao_id = f.idfuncao
        JOIN prioridade_funcao pc ON fi.idfuncao_imagem = pc.funcao_imagem_id
        WHERE fi.colaborador_id = ? AND o.status_obra = 0";

if ($mes) {
    $sql .= " AND MONTH(fi.prazo) = ?";
}
if ($ano) {
    $sql .= " AND YEAR(fi.prazo) = ?";
}
if ($obraId) {
    $sql .= " AND o.idobra = ?";
}
if ($funcaoId) {
    $sql .= " AND f.idfuncao = ?";
}
if ($status) {
    $sql .= " AND fi.status = ?";
}
if ($prioridade) {
    $sql .= " AND pc.prioridade = ?";
}

$sql .= " ORDER BY pc.prioridade ASC, imagem_id";


$stmt = $conn->prepare($sql);

$bindParams = [$colaboradorId];
$types = 'i';

if ($mes) {
    $types .= 's';
    $bindParams[] = $mes;
}
if ($ano) {
    $types .= 's';
    $bindParams[] = $ano;
}
if ($obraId) {
    $types .= 'i';
    $bindParams[] = $obraId;
}
if ($funcaoId) {
    $types .= 'i';
    $bindParams[] = $funcaoId;
}
if ($status) {
    $types .= 's';
    $bindParams[] = $status;
}
if ($prioridade) {
    $types .= 's';
    $bindParams[] = $prioridade;
}

$stmt->bind_param($types, ...$bindParams);

$stmt->execute();
$result = $stmt->get_result();

$funcoes = [];
while ($row = $result->fetch_assoc()) {
    $funcoes[] = $row;
}

echo json_encode($funcoes);

$stmt->close();
$conn->close();
