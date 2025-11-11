<?php
header('Content-Type: application/json; charset=utf-8');

// Ajuste o include conforme seu projeto
include __DIR__ . '/../conexao.php';

// Parâmetros opcionais: funcao_id (int) e colaboradores (csv de ids)
$funcao_id = isset($_GET['funcao_id']) ? (int)$_GET['funcao_id'] : 4;
$colabs = isset($_GET['colaboradores']) ? $_GET['colaboradores'] : '6,8,23,20,33';
// $colabs = isset($_GET['colaboradores']) ? $_GET['colaboradores'] : '4,5,27';

// Sanitiza a lista de colaboradores (mantém apenas números e vírgulas)
$colabs = preg_replace('/[^0-9,]/', '', $colabs);
if (!$colabs) {
    echo json_encode(['error' => 'Lista de colaboradores inválida']);
    exit;
}

// Query: mês atual conta por prazo; meses anteriores (2 meses) contam por data_pagamento
$sql = "SELECT
  fi.colaborador_id,
  COALESCE(c.nome_colaborador, CONCAT('id:', fi.colaborador_id)) AS nome_colaborador,
  DATE_FORMAT(
    CASE
      WHEN YEAR(fi.prazo) = YEAR(CURDATE()) AND MONTH(fi.prazo) = MONTH(CURDATE()) THEN fi.prazo
      ELSE fi.data_pagamento
    END, '%Y-%m') AS ano_mes,
  COUNT(*) AS quantidade
FROM funcao_imagem fi
LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
WHERE fi.funcao_id IN (?)
  AND fi.colaborador_id IN ($colabs)
  AND (
    (YEAR(fi.prazo) = YEAR(CURDATE()) AND MONTH(fi.prazo) = MONTH(CURDATE()))
    OR
    (fi.data_pagamento BETWEEN DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), '%Y-%m-01')
                         AND LAST_DAY(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)))
  )
GROUP BY fi.colaborador_id, ano_mes
ORDER BY fi.colaborador_id, ano_mes";

$out = [];
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $funcao_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        // força tipos
        $row['colaborador_id'] = (int)$row['colaborador_id'];
        $row['quantidade'] = (int)$row['quantidade'];
        $out[] = $row;
    }
    $stmt->close();
} else {
    echo json_encode(['error' => "Falha na preparação da query: " . $conn->error]);
    exit;
}

echo json_encode($out);
