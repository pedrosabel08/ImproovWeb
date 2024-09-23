<?php
header('Content-Type: application/json');

// Conexão com o banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

// Capturando os parâmetros da requisição
$colaboradorId = intval($_GET['colaborador_id']);
$clienteId = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : null;
$obraId = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : null;

// Construção da query com condições dinâmicas
$sql = "SELECT f.idfuncao_imagem, ico.imagem_nome, f.pagamento, f.valor 
FROM funcao_imagem f 
INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = f.imagem_id 
INNER JOIN colaborador c ON c.idcolaborador = f.colaborador_id 
WHERE c.idcolaborador = ?";

// Preparando a query
$stmt = $conn->prepare($sql);

// Vinculando os parâmetros de forma dinâmica
if ($clienteId && $obraId) {
    $stmt->bind_param('iii', $colaboradorId, $clienteId, $obraId);
} elseif ($clienteId) {
    $stmt->bind_param('ii', $colaboradorId, $clienteId);
} elseif ($obraId) {
    $stmt->bind_param('ii', $colaboradorId, $obraId);
} else {
    $stmt->bind_param('i', $colaboradorId);
}

// Executando a query
$stmt->execute();
$result = $stmt->get_result();

// Processando os resultados
$funcoes = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $funcoes[] = $row;
    }
}

// Retornando o resultado em JSON
echo json_encode($funcoes);

// Fechando a conexão
$stmt->close();
$conn->close();
