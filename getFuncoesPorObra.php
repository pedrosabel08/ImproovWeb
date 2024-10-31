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
$tipoImagem = $_GET['tipo_imagem'] !== '0' && !empty($_GET['tipo_imagem']) ? $_GET['tipo_imagem'] : null;

// Consulta SQL inicial
$sql = "SELECT
    ico.idimagens_cliente_obra AS imagem_id,
    ico.imagem_nome,
    ico.tipo_imagem,
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
    MAX(CASE WHEN fi.funcao_id = 6 THEN fi.status END) AS alteracao_status,
    MAX(CASE WHEN fi.funcao_id = 7 THEN c.nome_colaborador END) AS planta_colaborador,
    MAX(CASE WHEN fi.funcao_id = 7 THEN fi.status END) AS planta_status
    FROM imagens_cliente_obra ico
    LEFT JOIN funcao_imagem fi ON fi.imagem_id = ico.idimagens_cliente_obra
    LEFT JOIN colaborador c ON fi.colaborador_id = c.idcolaborador
    WHERE ico.obra_id = ?";

// Adicionando filtro de tipo de imagem se fornecido
if ($tipoImagem) {
    $sql .= " AND ico.tipo_imagem = ?";
}

$sql .= " GROUP BY ico.imagem_nome
          ORDER BY ico.idimagens_cliente_obra";

// Preparando a consulta
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    // Verifica se a consulta está com algum erro
    die(json_encode(["error" => "Erro ao preparar a consulta: " . $conn->error]));
}

// Bind dos parâmetros com base na existência do filtro tipoImagem
if ($tipoImagem) {
    $stmt->bind_param('is', $obraId, $tipoImagem);
} else {
    $stmt->bind_param('i', $obraId);
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
