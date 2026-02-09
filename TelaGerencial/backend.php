<?php
// backend.php

include '../conexao.php';

header('Content-Type: application/json');
$colaborador = $_GET['colaborador'] ?? '';
$mes = $_GET['mes'] ?? '';
$anoSelecionado = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$mesAtual = (int)date('n');

if (!$colaborador && !$mes) {
    echo json_encode([]);
    exit;
}

if ($mes) {
  $mesBase = (int)$mes;
  $anoBase = $anoSelecionado;
  $mesAnterior = ($mesBase === 1) ? 12 : ($mesBase - 1);
  $anoAnterior = ($mesBase === 1) ? ($anoBase - 1) : $anoBase;

    // Consulta para agrupar por mês e função
    $sql = "SELECT 
      f.nome_funcao, 
      COUNT(*) as quantidade, 
      SUM(fi.valor) AS total_valor,
      (
        SELECT COUNT(*) 
        FROM funcao_imagem fi_sub
        WHERE fi_sub.funcao_id = fi.funcao_id
          AND fi_sub.valor > 0 
          AND fi_sub.pagamento = 1 
          AND fi_sub.data_pagamento <> '0000-00-00'
          AND MONTH(fi_sub.data_pagamento) = ?
          AND YEAR(fi_sub.data_pagamento) = ?
      ) AS quantidade_mes_anterior
    FROM funcao_imagem fi
    JOIN funcao f ON f.idfuncao = fi.funcao_id
    WHERE fi.valor > 0 AND fi.pagamento = 1 AND fi.data_pagamento <> '0000-00-00' 
      AND MONTH(fi.data_pagamento) = ? AND YEAR(fi.data_pagamento) = ?
    GROUP BY f.nome_funcao
    ORDER BY f.nome_funcao;";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $mesAnterior, $anoAnterior, $mesBase, $anoBase);
} else {
    $mesBase = $mesAtual;
    $anoBase = $anoSelecionado;
    $mesAnterior = ($mesBase === 1) ? 12 : ($mesBase - 1);
    $anoAnterior = ($mesBase === 1) ? ($anoBase - 1) : $anoBase;

    // Consulta para buscar por colaborador com quantidade do mês anterior e recorde
    $sql = "SELECT 
      c.nome_colaborador, 
      f.nome_funcao, 
      SUM(fi.valor) AS total_valor, 
      fi.data_pagamento,
      COUNT(*) as quantidade,
      (
        SELECT COUNT(*) 
        FROM funcao_imagem fi_sub
        WHERE fi_sub.colaborador_id = fi.colaborador_id 
          AND MONTH(fi_sub.data_pagamento) = ?
          AND YEAR(fi_sub.data_pagamento) = ?
      ) AS quantidade_mes_anterior,
      (
        SELECT MAX(quantidade_recorde) 
        FROM (
          SELECT COUNT(*) AS quantidade_recorde
          FROM funcao_imagem fi_sub
          WHERE fi_sub.colaborador_id = fi.colaborador_id
          GROUP BY MONTH(fi_sub.data_pagamento), YEAR(fi_sub.data_pagamento)
        ) AS recorde
      ) AS recorde
    FROM funcao_imagem fi
    JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
    JOIN funcao f ON f.idfuncao = fi.funcao_id
    WHERE fi.valor > 0 AND fi.pagamento = 1 AND data_pagamento <> '0000-00-00' AND colaborador_id = ?
    GROUP BY c.nome_colaborador, f.nome_funcao, fi.data_pagamento
    ORDER BY c.nome_colaborador, fi.data_pagamento, f.nome_funcao;";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $mesAnterior, $anoAnterior, $colaborador);
}

$stmt->execute();
$result = $stmt->get_result();

$dados = [];
while ($row = $result->fetch_assoc()) {
    $dados[] = $row;
}

echo json_encode($dados);
