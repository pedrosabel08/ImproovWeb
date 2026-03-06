<?php
include 'conexao.php';

$imagens = [

    // ======================
    // ARS_VIE (obra_id = 55, cliente_id = 32)
    // (these were already present; including full list provided)
    // ======================
    ['nome' => '10.ROM_MAE_Piscina_EF_3_1_POS', 'obra_id' => 63, 'cliente_id' => 41, 'colaborador_id' => 13],
    ['nome' => '13.ROM_MAE_Prainha_deck_molhado_EF_3_1_POS', 'obra_id' => 63, 'cliente_id' => 41, 'colaborador_id' => 13],
    ['nome' => '22.ROM_MAE_Wine_garden_sacada_EF_4_1 Vista', 'obra_id' => 63, 'cliente_id' => 41, 'colaborador_id' => 13],
    ['nome' => '22.ROM_MAE_Wine_garden_sacada_EF_4_1 Geral', 'obra_id' => 63, 'cliente_id' => 41, 'colaborador_id' => 13],
    ['nome' => '1.CEG_RES Fachada embasamento_Hall_POS', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 13],
    ['nome' => '1.CEG_RES Fachada embasamento_Lojas_POS', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 13],
    ['nome' => '10.CEG_RES_Gourmet_externo_EF_3_1_POS', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 13],
    ['nome' => '12.CEG_RES_Piscina_EF_2_1_POS', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 13],
    ['nome' => '13.CEG_RES_Piscina_infantil_+_Piscina_adulto_EF_2_1_POS', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 13],
    ['nome' => '14.CEG_RES_Suite_1_apto_tipo_-_Final_01_EF_2_1 Geral', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 13],
    ['nome' => '14.CEG_RES_Suite_1_apto_tipo_-_Final_01_EF_2_1 Detalhe', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 13],
    ['nome' => '17.CEG_RES_Living_apto_tipo_-_Final_01_EF_2_1', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 13],
    ['nome' => '50.CEG_RES_Sacada_apartamento_tipo_mostrando_vista_EF_2_1', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 13],
    ['nome' => 'CEG_RES_Tracking_0029', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 13],
    ['nome' => 'CEG_RES_Tracking_0035', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 13],
    ['nome' => 'CEG_RES_Tracking_0039', 'obra_id' => 76, 'cliente_id' => 47, 'colaborador_id' => 13],
    ['nome' => '17.JON_LIN Loft unidade_16_Angulo_1', 'obra_id' => 73, 'cliente_id' => 40, 'colaborador_id' => 13],
    ['nome' => '17.JON_LIN Loft unidade_16_Angulo_2', 'obra_id' => 73, 'cliente_id' => 40, 'colaborador_id' => 13],
    ['nome' => '9.JON_LIN Petstore', 'obra_id' => 73, 'cliente_id' => 40, 'colaborador_id' => 13],
    ['nome' => 'JON_LIN_Brinquedoteca', 'obra_id' => 73, 'cliente_id' => 40, 'colaborador_id' => 13],
    ['nome' => '9.JON_LIN_Area_interna_do_patio_coberto Foco no palco', 'obra_id' => 73, 'cliente_id' => 40, 'colaborador_id' => 13],
    ['nome' => '5.JON_LIN Lobby', 'obra_id' => 73, 'cliente_id' => 40, 'colaborador_id' => 13],
    ['nome' => '7.JON_LIN Docas para motos na garagem', 'obra_id' => 73, 'cliente_id' => 40, 'colaborador_id' => 13],
    ['nome' => 'JON_LIN - Espaco_Beauty', 'obra_id' => 73, 'cliente_id' => 40, 'colaborador_id' => 13],
    ['nome' => '11.JON_LIN Area interna do patio coberto', 'obra_id' => 73, 'cliente_id' => 40, 'colaborador_id' => 13],
    ['nome' => '10.JON_LIN Area interna do patio coberto - 2 - Foco nas lojas', 'obra_id' => 73, 'cliente_id' => 40, 'colaborador_id' => 13],
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







