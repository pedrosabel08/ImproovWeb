<?php
header('Content-Type: application/json');

// Conexão com o banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

// Captura o ID da obra
$obraId = intval($_GET['obra_id']);

$sql = "SELECT
            s.nome_status AS imagem_status,
            COUNT(CASE WHEN fi.funcao_id = 1 THEN 1 END) AS caderno_count,
            COUNT(CASE WHEN fi.funcao_id = 2 THEN 1 END) AS modelagem_count,
            COUNT(CASE WHEN fi.funcao_id = 3 THEN 1 END) AS composicao_count,
            COUNT(CASE WHEN fi.funcao_id = 4 THEN 1 END) AS finalizacao_count,
            COUNT(CASE WHEN fi.funcao_id = 5 THEN 1 END) AS pos_producao_count,
            COUNT(CASE WHEN fi.funcao_id = 6 THEN 1 END) AS alteracao_count
        FROM funcao_imagem fi
        JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
        JOIN status_imagem s ON ico.status_id = s.idstatus
        WHERE ico.obra_id = ?
        GROUP BY s.nome_status";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $obraId);
$stmt->execute();
$result = $stmt->get_result();

$statusFuncoes = array();
while ($row = $result->fetch_assoc()) {
    $statusFuncoes[] = $row;
}

echo json_encode($statusFuncoes);

$stmt->close();
$conn->close();
