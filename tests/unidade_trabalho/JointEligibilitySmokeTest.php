<?php

require_once dirname(__DIR__, 2) . '/conexao.php';
require_once dirname(__DIR__, 2) . '/helpers/inicio_conjunto_helper.php';

$result = $conn->query(
    "SELECT m.idfuncao_imagem
       FROM funcao_imagem m
       JOIN funcao_imagem c ON c.imagem_id = m.imagem_id AND c.funcao_id = 3
      WHERE m.funcao_id = 2
        AND m.status = 'Não iniciado'
        AND c.status = 'Não iniciado'
      ORDER BY m.idfuncao_imagem DESC
      LIMIT 100"
);
$reasons = [];
$available = 0;
while ($row = $result->fetch_assoc()) {
    $evaluation = flow_inicio_conjunto_avaliar_modelagem_composicao(
        $conn,
        (int) $row['idfuncao_imagem'],
        false,
        false
    );
    foreach (['joint_start_available', 'joint_start_type', 'joint_start_reason', 'joint_start_partner_id'] as $key) {
        if (!array_key_exists($key, $evaluation)) {
            throw new RuntimeException('Contrato de elegibilidade incompleto: ' . $key);
        }
    }
    $reason = (string) $evaluation['joint_start_reason'];
    $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
    if ($evaluation['joint_start_available']) {
        $available++;
    }
}

echo 'JointEligibilitySmokeTest: OK available=' . $available . ' reasons=' . json_encode($reasons, JSON_UNESCAPED_UNICODE) . "\n";

