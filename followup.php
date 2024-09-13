<?php
header('Content-Type: application/json');

// Conectar ao banco de dados
$conn = new mysqli('192.168.0.202', 'admin', 'admin', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$obraId = intval($_GET['obra_id']);

// Consulta SQL atualizada
$sql = "SELECT
    ico.imagem_nome,
    s.nome_status AS imagem_status,
    MAX(CASE WHEN fi.funcao_id = 1 THEN fi.status END) AS caderno_status,
    MAX(CASE WHEN fi.funcao_id = 1 THEN s.nome_status END) AS caderno_revisao,
    MAX(CASE WHEN fi.funcao_id = 2 THEN fi.status END) AS modelagem_status,
    MAX(CASE WHEN fi.funcao_id = 3 THEN fi.status END) AS composicao_status,
    MAX(CASE WHEN fi.funcao_id = 4 THEN fi.status END) AS finalizacao_status,
    MAX(CASE WHEN fi.funcao_id = 5 THEN fi.status END) AS pos_producao_status,
    MAX(CASE WHEN fi.funcao_id = 6 THEN fi.status END) AS alteracao_status
FROM funcao_imagem fi
JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
JOIN status_imagem s ON ico.status_id = s.idstatus
WHERE ico.obra_id = ?
GROUP BY ico.imagem_nome, ico.status_id, s.nome_status;";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $obraId);
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
