<?php
include '../conexao.php'; // Conexão com o banco

$id_obra = $_GET['id_obra'] ?? null;

if (!$id_obra) {
    http_response_code(400);
    echo json_encode(['erro' => 'ID da obra não fornecido.']);
    exit;
}

// Query para buscar as imagens
$sqlImagens = "SELECT img.tipo_imagem, img.imagem_nome
FROM imagens_cliente_obra img
JOIN obra o ON img.obra_id = o.idobra
WHERE o.idobra = ?
  AND (
      EXISTS (
          SELECT 1
          FROM arquivos a
          WHERE a.obra_id = o.idobra
            AND a.tipo = img.tipo_imagem
      )
      OR img.tipo_imagem = 'Planta Humanizada'
  )
ORDER BY FIELD(
    img.tipo_imagem,
    'Fachada',
    'Imagem Interna',
    'Unidade',
    'Imagem Externa',
    'Planta Humanizada'
), img.imagem_nome";

$stmtImagens = $conn->prepare($sqlImagens);
$stmtImagens->bind_param("i", $id_obra);
$stmtImagens->execute();
$resultImagens = $stmtImagens->get_result();

// Query para buscar as etapas
$sqlEtapas = "SELECT 
    gp.*, 
    c.nome_colaborador AS nome_colaborador,
    ec.colaborador_id,

    COUNT(fi.status) AS total_funcoes,
    
    SUM(CASE WHEN fi.status = 'Finalizado' THEN 1 ELSE 0 END) AS total_finalizadas,

    ROUND(
        (SUM(CASE WHEN fi.status = 'Finalizado' THEN 1 ELSE 0 END) / COUNT(fi.status)) * 100, 
        2
    ) AS porcentagem_conclusao

FROM 
    gantt_prazos gp

LEFT JOIN 
    etapa_colaborador ec ON ec.gantt_id = gp.id

LEFT JOIN 
    colaborador c ON c.idcolaborador = ec.colaborador_id

LEFT JOIN 
    imagens_cliente_obra ico 
    ON ico.obra_id = gp.obra_id 
    AND ico.tipo_imagem = gp.tipo_imagem

-- JOIN com função, filtrando conforme a etapa correspondente
LEFT JOIN 
    funcao_imagem fi 
    ON fi.imagem_id = ico.idimagens_cliente_obra 
    AND fi.funcao_id = (
        CASE gp.etapa
            WHEN 'Caderno' THEN 1
            WHEN 'Modelagem' THEN 2
            WHEN 'Composição' THEN 3
            WHEN 'Finalização' THEN 4
            WHEN 'Pós-Produção' THEN 5
            ELSE NULL
        END
    )

WHERE 
    gp.obra_id = ?

GROUP BY 
    gp.id, c.idcolaborador

ORDER BY 
    gp.tipo_imagem,
    FIELD(gp.etapa, 'Caderno', 'Modelagem', 'Composição', 'Finalização', 'Pós-Produção') -- ajuste conforme suas etapas reais
";

$stmtEtapas = $conn->prepare($sqlEtapas);
$stmtEtapas->bind_param("i", $id_obra);
$stmtEtapas->execute();
$resultEtapas = $stmtEtapas->get_result();

// Query para determinar o intervalo de datas
$sqlDatas = "SELECT MIN(data_inicio) as primeira_data, MAX(data_fim) as ultima_data 
             FROM gantt_prazos 
             WHERE obra_id = ? AND data_inicio <> '0000-00-00' AND data_fim <> '0000-00-00'";
$stmtDatas = $conn->prepare($sqlDatas);
$stmtDatas->bind_param("i", $id_obra);
$stmtDatas->execute();
$resultDatas = $stmtDatas->get_result();
$rowDatas = $resultDatas->fetch_assoc();
$primeiraData = $rowDatas['primeira_data'];
$ultimaData = $rowDatas['ultima_data'];

$sqlObra = "SELECT * FROM obra WHERE idobra = ?";
$stmtObra = $conn->prepare($sqlObra);
$stmtObra->bind_param("i", $id_obra);
$stmtObra->execute();
$resultObra = $stmtObra->get_result();
$rowObra = $resultObra->fetch_assoc();

// Organizar os dados
$imagens = [];
while ($row = $resultImagens->fetch_assoc()) {
    $imagens[$row['tipo_imagem']][] = $row['imagem_nome'];
}

$etapas = [];
while ($row = $resultEtapas->fetch_assoc()) {
    $etapas[$row['tipo_imagem']][] = $row;
}

// Retornar os dados em JSON
header('Content-Type: application/json');
echo json_encode([
    'imagens' => $imagens,
    'etapas' => $etapas,
    'primeiraData' => $primeiraData,
    'ultimaData' => $ultimaData,
    'obra' => $rowObra,
]);

$conn->close();
