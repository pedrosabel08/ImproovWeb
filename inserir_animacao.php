<?php
include 'conexao.php';

$imagens = [

    // ======================
    // ROM_MAE (obra_id = 63, cliente_id = 36)
    // ======================
    ['nome' => '5.ROM_MAE_Fachada Baixo', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => '19.ROM_MAE_Pub_tematico_interior_angulo_EF_5_1', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => '33.ROM_MAE Espaco gourmet apto', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => '36.ROM_MAE Suite cobertura duplex_EF', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => '37.ROM_MAE Living cobertura duplex_CAM 1', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => '37.ROM_MAE Living cobertura duplex_detalhe', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => 'Tracking_0048', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => 'Tracking_0050', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => 'Tracking_0063', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => 'Tracking_0074', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],

    // ======================
    // LD9_URB (obra_id = 57, cliente_id = 37)
    // ======================
    ['nome' => '3.LD9_URB Embasamento comercial', 'obra_id' => 57, 'cliente_id' => 37, 'colaborador_id' => 13],
    ['nome' => '12.LD9_URB Piscinas angulo', 'obra_id' => 57, 'cliente_id' => 37, 'colaborador_id' => 13],
    ['nome' => '16.LD9_URB Piscina interna aquecida', 'obra_id' => 57, 'cliente_id' => 37, 'colaborador_id' => 13],
    ['nome' => '19.LD9_URB Academia', 'obra_id' => 57, 'cliente_id' => 37, 'colaborador_id' => 13],
    ['nome' => '46.LD9_URB Placemaking', 'obra_id' => 57, 'cliente_id' => 37, 'colaborador_id' => 13],
    ['nome' => '49.LD9_URB Wine club', 'obra_id' => 57, 'cliente_id' => 37, 'colaborador_id' => 13],
    ['nome' => 'LD9_URB_Tracking_0062', 'obra_id' => 57, 'cliente_id' => 37, 'colaborador_id' => 13],
    ['nome' => 'LF9_URB_Tracking_0012', 'obra_id' => 57, 'cliente_id' => 37, 'colaborador_id' => 13],
    ['nome' => 'LF9_URB_Tracking_0058_v1', 'obra_id' => 57, 'cliente_id' => 37, 'colaborador_id' => 13],
    ['nome' => 'LF9_URB_Tracking_0042', 'obra_id' => 57, 'cliente_id' => 37, 'colaborador_id' => 13],

    // ======================
    // HAB_AVT (obra_id = 101, cliente_id = 69)
    // ======================
    ['nome' => 'Tracking', 'obra_id' => 101, 'cliente_id' => 69, 'colaborador_id' => 13],

    // ======================
    // GES_CHA (obra_id = 63, cliente_id = 36)
    // ======================
    ['nome' => '6. GES_CHA-Embasamento', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => '11. GES_CHA Piscina angulo geral', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => '15. GES_CHA -Piscina Raia', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => '39.GES_CHA_Academia Geral', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => '39.GES_CHA_Academia Vista', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => 'GES_CHA_Tracking_0001', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => 'GES_CHA_Tracking_0002', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],
    ['nome' => 'GES_CHA_Tracking_0003', 'obra_id' => 63, 'cliente_id' => 36, 'colaborador_id' => 13],

    // ======================
    // ARS_VIE (obra_id = 55, cliente_id = 32)
    // ======================
    ['nome' => 'ARS_VIE_Redario', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => 'ARS_VIE_Fireplace', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
];

$status = 'Finalizado';
$data = '2026-03-31';

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







