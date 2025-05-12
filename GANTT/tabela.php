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
        c.nome_colaborador AS nome_colaborador 
    FROM 
        gantt_prazos gp
    LEFT JOIN 
        etapa_colaborador ec ON ec.gantt_id = gp.id
    LEFT JOIN 
        colaborador c ON c.idcolaborador = ec.colaborador_id
    WHERE 
        gp.obra_id = ?
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
