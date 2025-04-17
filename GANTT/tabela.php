<?php
include '../conexao.php'; // Conexão com o banco

// Query para buscar as imagens
$sqlImagens = "SELECT img.tipo_imagem, img.imagem_nome
FROM imagens_cliente_obra img
JOIN obra o ON img.obra_id = o.idobra
WHERE o.idobra = 55
  AND EXISTS (
      SELECT 1
      FROM arquivos a
      WHERE a.obra_id = o.idobra
        AND a.tipo = img.tipo_imagem
  )
ORDER BY img.tipo_imagem, img.imagem_nome";
$resultImagens = $conn->query($sqlImagens);

// Query para buscar as etapas
$sqlEtapas = "SELECT etapa, tipo_imagem, data_inicio, data_fim 
              FROM gantt_prazos 
              WHERE obra_id = 55";
$resultEtapas = $conn->query($sqlEtapas);

// Query para determinar o intervalo de datas
$sqlDatas = "SELECT MIN(data_inicio) as primeira_data, MAX(data_fim) as ultima_data 
             FROM gantt_prazos 
             WHERE obra_id = 55 AND data_inicio <> '0000-00-00' AND data_fim <> '0000-00-00'";
$resultDatas = $conn->query($sqlDatas);
$rowDatas = $resultDatas->fetch_assoc();
$primeiraData = $rowDatas['primeira_data'];
$ultimaData = $rowDatas['ultima_data'];

$sqlObra = "SELECT * FROM obra WHERE idobra = 55";
$resultObra = $conn->query($sqlObra);
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
