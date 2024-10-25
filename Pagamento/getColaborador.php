<?php
header('Content-Type: application/json');

$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

$colaboradorId = intval($_GET['colaborador_id']);
$mesId = isset($_GET['mes_id']) ? $_GET['mes_id'] : null;

// Converter o mês do nome em português para o número correspondente
$meses = [
    "Janeiro" => "01",
    "Fevereiro" => "02",
    "Março" => "03",
    "Abril" => "04",
    "Maio" => "05",
    "Junho" => "06",
    "Julho" => "07",
    "Agosto" => "08",
    "Setembro" => "09",
    "Outubro" => "10",
    "Novembro" => "11",
    "Dezembro" => "12"
];

$mesNumero = isset($meses[$mesId]) ? $meses[$mesId] : null;

// Construir a consulta SQL
$sql = "SELECT f.idfuncao_imagem, ico.imagem_nome, f.pagamento, f.valor, fun.nome_funcao, f.status
FROM funcao_imagem f 
INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = f.imagem_id 
INNER JOIN colaborador c ON c.idcolaborador = f.colaborador_id 
INNER JOIN funcao fun ON f.funcao_id = fun.idfuncao
WHERE f.status <> 'Não iniciado' 
  AND f.status <> 'Não se aplica' 
  AND c.idcolaborador = ?";

if ($mesNumero) {
    $sql .= " AND MONTH(f.prazo) = ?";
}

$stmt = $conn->prepare($sql);

if ($mesNumero) {
    $stmt->bind_param('ii', $colaboradorId, $mesNumero);
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
