<?php
include 'conexao.php';

$imagens = [

    // ======================
    // ARS_VIE (obra_id = 55, cliente_id = 32)
    // (these were already present; including full list provided)
    // ======================
    ['nome' => '15.ROM_MAE_Quiosque_da_piscina', 'obra_id' => 63, 'cliente_id' => 41, 'colaborador_id' => 39],
    ['nome' => '10.ROM_MAE_Piscina', 'obra_id' => 63, 'cliente_id' => 41, 'colaborador_id' => 39],
    ['nome' => '1.CEG_RES_Fachada_embasamento_CAM_02', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 39],
    ['nome' => '1.CEG_RES_Fachada_embasamento_CAM_01', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 39],
    ['nome' => '13.CEG_RES_Piscina_Adulto_e_infantil', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 39],
    ['nome' => '12.CEG_RES_Piscina_Vista', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 39],
    ['nome' => '10.CEG_RES_Gourmet_Externo', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 39],
    ['nome' => 'JON_LIN_Palco', 'obra_id' => 73, 'cliente_id' => 40, 'colaborador_id' => 39],
];

$status = 'Finalizado';
$data = '2026-02-28';

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







