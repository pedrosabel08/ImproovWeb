<?php
include '../conexao.php'; // Conexão com o banco

$id_obra = $_GET['id_obra'] ?? null;

if (!$id_obra) {
    http_response_code(400);
    echo json_encode(['erro' => 'ID da obra não fornecido.']);
    exit;
}

// Query para buscar as imagens
$sqlImagens = "SELECT img.idimagens_cliente_obra, img.tipo_imagem, img.imagem_nome
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
    c.nome_colaborador AS nome_etapa_colaborador,
    ec.colaborador_id AS etapa_colaborador_id,
    COALESCE(fi_data.total_funcoes, 0) AS total_funcoes,
    COALESCE(fi_data.total_finalizadas, 0) AS total_finalizadas,
    COALESCE(fi_data.porcentagem_conclusao, 0) AS porcentagem_conclusao,
    f.idfuncao AS funcao_id
FROM 
    gantt_prazos gp

LEFT JOIN 
    etapa_colaborador ec ON ec.gantt_id = gp.id

LEFT JOIN 
    colaborador c ON c.idcolaborador = ec.colaborador_id

LEFT JOIN funcao f 
    ON f.idfuncao = (
        CASE gp.etapa
            WHEN 'Caderno' THEN 1
            WHEN 'Modelagem' THEN 2
            WHEN 'Composição' THEN 3
            WHEN 'Finalização' THEN 4
            WHEN 'Pós-Produção' THEN 5
            WHEN 'Filtro de assets' THEN 8
            ELSE NULL
        END
    )

-- Subquery com agregação isolada
LEFT JOIN (
    SELECT 
        gp_sub.id AS gantt_id,
        COUNT(fi.status) AS total_funcoes,
        SUM(CASE WHEN fi.status = 'Finalizado' THEN 1 ELSE 0 END) AS total_finalizadas,
        ROUND(
            (SUM(CASE WHEN fi.status = 'Finalizado' THEN 1 ELSE 0 END) / COUNT(fi.status)) * 100, 
            2
        ) AS porcentagem_conclusao
    FROM gantt_prazos gp_sub
    LEFT JOIN imagens_cliente_obra ico ON ico.obra_id = gp_sub.obra_id AND ico.tipo_imagem = gp_sub.tipo_imagem
    LEFT JOIN funcao_imagem fi ON fi.imagem_id = ico.idimagens_cliente_obra 
        AND fi.funcao_id = (
            CASE gp_sub.etapa
                WHEN 'Caderno' THEN 1
                WHEN 'Modelagem' THEN 2
                WHEN 'Composição' THEN 3
                WHEN 'Finalização' THEN 4
                WHEN 'Pós-Produção' THEN 5
                ELSE NULL
            END
        )
    WHERE gp_sub.obra_id = ?
    GROUP BY gp_sub.id
) fi_data ON fi_data.gantt_id = gp.id

WHERE 
    gp.obra_id = ?

ORDER BY 
    gp.tipo_imagem,
    FIELD(gp.etapa, 'Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Finalização', 'Pós-Produção')


";

$stmtEtapas = $conn->prepare($sqlEtapas);
$stmtEtapas->bind_param("ii", $id_obra, $id_obra);
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
    $imagens[$row['tipo_imagem']][] = [
        'imagem_id' => $row['idimagens_cliente_obra'],
        'nome' => $row['imagem_nome']
    ];
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
