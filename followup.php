<?php
header('Content-Type: application/json');

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

// Capturar os parâmetros
$obraId = intval($_GET['obra_id']);
$statusImagem = intval($_GET['status_imagem']);

// Montar a consulta SQL com o filtro de status, se necessário
$sql = "SELECT
    ico.imagem_nome,
    s.nome_status AS imagem_status,
    ico.prazo,
    MAX(CASE WHEN fi.funcao_id = 1 THEN fi.status END) AS caderno_status,
    MAX(CASE WHEN fi.funcao_id = 2 THEN fi.status END) AS modelagem_status,
    MAX(CASE WHEN fi.funcao_id = 3 THEN fi.status END) AS composicao_status,
    MAX(CASE WHEN fi.funcao_id = 4 THEN fi.status END) AS finalizacao_status,
    MAX(CASE WHEN fi.funcao_id = 5 THEN fi.status END) AS pos_producao_status,
    MAX(CASE WHEN fi.funcao_id = 6 THEN fi.status END) AS alteracao_status,
    MAX(CASE WHEN fi.funcao_id = 7 THEN fi.status END) AS planta_status,
    
    (SELECT SUM(CASE WHEN lf.status_novo IN (2, 3, 4, 5) THEN 1 ELSE 0 END)
     FROM log_followup lf
     WHERE lf.imagem_id = ico.idimagens_cliente_obra) AS total_revisoes

FROM imagens_cliente_obra ico
LEFT JOIN funcao_imagem fi ON fi.imagem_id = ico.idimagens_cliente_obra
LEFT JOIN status_imagem s ON ico.status_id = s.idstatus
WHERE ico.obra_id = ?";

// Adicionar filtro de status se `status_imagem` for diferente de 0 (selecionado "Todos")
if ($statusImagem !== 0) {
    $sql .= " AND ico.status_id = ?";
}

$sql .= " GROUP BY ico.imagem_nome, ico.status_id, s.nome_status, ico.prazo
           ORDER BY ico.idimagens_cliente_obra";

$stmt = $conn->prepare($sql);

// Vincular parâmetros com base na presença do filtro de status
if ($statusImagem !== 0) {
    $stmt->bind_param('ii', $obraId, $statusImagem);
} else {
    $stmt->bind_param('i', $obraId);
}

$stmt->execute();
$result = $stmt->get_result();

$imagem = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $imagem[] = $row;
    }
}

echo json_encode($imagem);

$stmt->close();
$conn->close();
