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
$antecipada = $_GET['antecipada'] === "Antecipada" ? 1 : null;

// Consulta SQL para dados principais
$sql1 = "SELECT
    ico.idimagens_cliente_obra AS imagem_id,
    ico.imagem_nome,
    ico.tipo_imagem,
    ico.antecipada,
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
    $sql1 .= " AND ico.tipo_imagem = ?";
}

if ($antecipada !== null) {
    $sql1 .= " AND ico.antecipada = ?";
}

$sql1 .= " GROUP BY ico.imagem_nome
          ORDER BY ico.idimagens_cliente_obra";

// Preparando a consulta
$stmt1 = $conn->prepare($sql1);

if ($stmt1 === false) {
    die(json_encode(["error" => "Erro ao preparar a consulta: " . $conn->error]));
}

// Bind dos parâmetros
if ($tipoImagem && $antecipada !== null) {
    $stmt1->bind_param('isi', $obraId, $tipoImagem, $antecipada);
} elseif ($tipoImagem) {
    $stmt1->bind_param('is', $obraId, $tipoImagem);
} elseif ($antecipada !== null) {
    $stmt1->bind_param('ii', $obraId, $antecipada);
} else {
    $stmt1->bind_param('i', $obraId);
}

$stmt1->execute();
$result1 = $stmt1->get_result();

$funcoes = array();
if ($result1->num_rows > 0) {
    while ($row = $result1->fetch_assoc()) {
        $funcoes[] = $row;
    }
}

// Consulta SQL para calcular total de imagens e porcentagens de cada função
$sql2 = "SELECT
    COUNT(DISTINCT ico.idimagens_cliente_obra) AS total_imagens,

    -- Contagem de funções com status 'Finalizado' para cada tipo de função
    SUM(CASE WHEN fi.funcao_id = 1 AND fi.status = 'Finalizado' THEN 1 ELSE 0 END) AS caderno_finalizado,
    SUM(CASE WHEN fi.funcao_id = 2 AND fi.status = 'Finalizado' THEN 1 ELSE 0 END) AS modelagem_finalizado,
    SUM(CASE WHEN fi.funcao_id = 3 AND fi.status = 'Finalizado' THEN 1 ELSE 0 END) AS composicao_finalizado,
    SUM(CASE WHEN fi.funcao_id = 4 AND fi.status = 'Finalizado' THEN 1 ELSE 0 END) AS finalizacao_finalizado,
    SUM(CASE WHEN fi.funcao_id = 5 AND fi.status = 'Finalizado' THEN 1 ELSE 0 END) AS pos_producao_finalizado,
    SUM(CASE WHEN fi.funcao_id = 6 AND fi.status = 'Finalizado' THEN 1 ELSE 0 END) AS alteracao_finalizado,
    SUM(CASE WHEN fi.funcao_id = 7 AND fi.status = 'Finalizado' THEN 1 ELSE 0 END) AS planta_finalizado,
    SUM(CASE WHEN fi.funcao_id = 8 AND fi.status = 'Finalizado' THEN 1 ELSE 0 END) AS filtro_finalizado,

    -- Quantidade total de funções de imagem para cada funcao_id
    COUNT(CASE WHEN fi.funcao_id = 1 THEN 1 END) AS total_caderno_funcao,
    COUNT(CASE WHEN fi.funcao_id = 2 THEN 1 END) AS total_modelagem_funcao,
    COUNT(CASE WHEN fi.funcao_id = 3 THEN 1 END) AS total_composicao_funcao,
    COUNT(CASE WHEN fi.funcao_id = 4 THEN 1 END) AS total_finalizacao_funcao,
    COUNT(CASE WHEN fi.funcao_id = 5 THEN 1 END) AS total_pos_producao_funcao,
    COUNT(CASE WHEN fi.funcao_id = 6 THEN 1 END) AS total_alteracao_funcao,
    COUNT(CASE WHEN fi.funcao_id = 7 THEN 1 END) AS total_planta_funcao,
    COUNT(CASE WHEN fi.funcao_id = 8 THEN 1 END) AS total_filtro_funcao,

    ico.data_inicio,
    ico.recebimento_arquivos,
    ico.prazo
    FROM imagens_cliente_obra ico
    LEFT JOIN funcao_imagem fi ON fi.imagem_id = ico.idimagens_cliente_obra
    WHERE ico.obra_id = ?
";

// Adicionando filtros para tipoImagem e antecipada se fornecidos
if ($tipoImagem) {
    $sql2 .= " AND ico.tipo_imagem = ?";
}

if ($antecipada !== null) {
    $sql2 .= " AND ico.antecipada = ?";
}

$stmt2 = $conn->prepare($sql2);

if ($stmt2 === false) {
    die(json_encode(["error" => "Erro ao preparar a consulta: " . $conn->error]));
}

// Bind dos parâmetros
if ($tipoImagem && $antecipada !== null) {
    $stmt2->bind_param('isi', $obraId, $tipoImagem, $antecipada);
} elseif ($tipoImagem) {
    $stmt2->bind_param('is', $obraId, $tipoImagem);
} elseif ($antecipada !== null) {
    $stmt2->bind_param('ii', $obraId, $antecipada);
} else {
    $stmt2->bind_param('i', $obraId);
}

$stmt2->execute();
$result2 = $stmt2->get_result();

$totais = array();
if ($result2->num_rows > 0) {
    while ($row = $result2->fetch_assoc()) {
        $totais[] = $row;
    }
}
$sql3 = "SELECT assunto, data FROM acompanhamento_email WHERE obra_id = ?";

$stmt3 = $conn->prepare($sql3);

if ($stmt3 === false) {
    die(json_encode(["error" => "Erro ao preparar a consulta: " . $conn->error]));
}

$stmt3->bind_param('i', $obraId);
$stmt3->execute();
$result3 = $stmt3->get_result();

$acompanhamentoEmails = array();
if ($result3->num_rows > 0) {
    while ($row = $result3->fetch_assoc()) {
        $acompanhamentoEmails[] = $row;
    }
}

// Enviando resposta JSON com todos os resultados
echo json_encode([
    'funcoes' => $funcoes,
    'totais' => $totais,
    'acompanhamento_emails' => $acompanhamentoEmails
]);

$conn->close();
