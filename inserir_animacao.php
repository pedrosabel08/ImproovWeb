<?php
include 'conexao.php';

$imagens = [

    // ======================
    // ARS_VIE (obra_id = 55, cliente_id = 32)
    // (these were already present; including full list provided)
    // ======================

    ['nome' => '16.JON_LIN_Loft_unidade_16_angulo_1', 'obra_id' => 73, 'cliente_id' => 45, 'colaborador_id' => 35],
    ['nome' => '17.JON_LIN-Loft Ângulo_2', 'obra_id' => 73, 'cliente_id' => 45, 'colaborador_id' => 35],
    ['nome' => 'JON_LIN_Brinquedoteca', 'obra_id' => 73, 'cliente_id' => 45, 'colaborador_id' => 35],
    ['nome' => 'JON_LIN_Espaço Beauty', 'obra_id' => 73, 'cliente_id' => 45, 'colaborador_id' => 35],
    ['nome' => 'JON_LIN_Petstore', 'obra_id' => 73, 'cliente_id' => 45, 'colaborador_id' => 35],
    // ======================
    // RDO_VAL (obra_id = 70, cliente_id = 3)
    // ======================
    ['nome' => '6.RDO_VAL Fachada no ângulo do observador da rua 2', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 35],
    ['nome' => '4.RDO_VAL Com foco na área de lazer 2', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 35],

];

$status = 'Finalizado';
$data = '2026-01-31';

foreach ($imagens as $img) {
    // Inserir na tabela imagem_animacao
    $stmt = $conn->prepare("INSERT INTO imagem_animacao (imagem_nome, obra_id) VALUES (?, ?)");
    $stmt->bind_param("si", $img['nome'], $img['obra_id']);
    $stmt->execute();

    $imagem_id = $conn->insert_id;

    // Inserir na tabela animacao
    // data_pagamento must be a valid DATE (schema requires NOT NULL). Use data_anima as placeholder when payment not done yet.
    $stmt2 = $conn->prepare("INSERT INTO animacao (imagem_id, colaborador_id, cliente_id, obra_id, status_anima, data_anima, pagamento, valor) VALUES (?, ?, ?, ?, ?, ?, 0, 0)");
    $dataPagamento = $data; // placeholder valid date; change if you prefer another placeholder
    $stmt2->bind_param("iiiiss", $imagem_id, $img['colaborador_id'], $img['cliente_id'], $img['obra_id'], $status, $data);
    $stmt2->execute();

    echo "Inserido: {$img['nome']} (imagem_id = $imagem_id, colaborador_id = {$img['colaborador_id']})\n";
}

echo "Processo concluído.";
