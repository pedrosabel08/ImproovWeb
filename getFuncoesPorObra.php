<?php
header('Content-Type: application/json');

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

$obraId = intval($_GET['obra_id']);

// Consulta SQL atualizada
$sql = "SELECT
        ico.imagem_nome,
        MAX(CASE WHEN fi.funcao_id = 1 THEN c.nome_colaborador END) AS caderno_colaborador,
        MAX(CASE WHEN fi.funcao_id = 1 THEN fi.status END) AS caderno_status,
        MAX(CASE WHEN fi.funcao_id = 2 THEN c.nome_colaborador END) AS modelagem_colaborador,
        MAX(CASE WHEN fi.funcao_id = 2 THEN fi.status END) AS modelagem_status,
        MAX(CASE WHEN fi.funcao_id = 3 THEN c.nome_colaborador END) AS composicao_colaborador,
        MAX(CASE WHEN fi.funcao_id = 3 THEN fi.status END) AS composicao_status,
        MAX(CASE WHEN fi.funcao_id = 4 THEN c.nome_colaborador END) AS finalizacao_colaborador,
        MAX(CASE WHEN fi.funcao_id = 4 THEN fi.status END) AS finalizacao_status,
        MAX(CASE WHEN fi.funcao_id = 5 THEN c.nome_colaborador END) AS pos_producao_colaborador,
        MAX(CASE WHEN fi.funcao_id = 5 THEN fi.status END) AS pos_producao_status,
        MAX(CASE WHEN fi.funcao_id = 6 THEN c.nome_colaborador END) AS alteracao_colaborador,
        MAX(CASE WHEN fi.funcao_id = 6 THEN fi.status END) AS alteracao_status
    FROM funcao_imagem fi
    JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    JOIN colaborador c ON fi.colaborador_id = c.idcolaborador
    WHERE ico.obra_id = ?
    GROUP BY ico.imagem_nome
    ORDER BY ico.idimagens_cliente_obra
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $obraId);
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
