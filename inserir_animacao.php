<?php
include 'conexao.php';

$imagens = [

    // ======================
    // MUL_SPL (obra_id = 80, cliente_id = 52)
    // ======================
    ['nome' => '10.MUL_SPL Salao de festas 02- tem vista para o mar', 'obra_id' => 80, 'cliente_id' => 52, 'colaborador_id' => 13],
    ['nome' => '14.MUL_SPL_Academia_CAM03', 'obra_id' => 80, 'cliente_id' => 52, 'colaborador_id' => 13],
    ['nome' => '17.MUL_SPL Area externa', 'obra_id' => 80, 'cliente_id' => 52, 'colaborador_id' => 13],
    ['nome' => '20.MUL_SPL_Suite_unidade_06-_tem_vista_para_o_mar_EF_2_1 POS', 'obra_id' => 80, 'cliente_id' => 52, 'colaborador_id' => 13],
    ['nome' => '20.MUL_SPL_Suite_unidade_06-_tem_vista_para_o_mar_EF_2_1', 'obra_id' => 80, 'cliente_id' => 52, 'colaborador_id' => 37],
    ['nome' => '21.MUL_SPL_Living_unidade_03-_tem_vista_para_a_mata_EF_2_1', 'obra_id' => 80, 'cliente_id' => 52, 'colaborador_id' => 13],
    ['nome' => '23.MUL_SPL Living unidade_GERAL', 'obra_id' => 80, 'cliente_id' => 52, 'colaborador_id' => 13],
    ['nome' => '23.MUL_SPL Living unidade_DETALHE', 'obra_id' => 80, 'cliente_id' => 52, 'colaborador_id' => 13],
    ['nome' => 'MUL_SPL_Tracking_0141', 'obra_id' => 80, 'cliente_id' => 52, 'colaborador_id' => 13],
    ['nome' => 'MUL_SPL_Tracking_0168', 'obra_id' => 80, 'cliente_id' => 52, 'colaborador_id' => 13],

    // ======================
    // ARS_VIE (obra_id = 55, cliente_id = 32)
    // (these were already present; including full list provided)
    // ======================
    ['nome' => '7. ARS_VIE Embasamento mostrando o acesso', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '8. ARS_VIE Corredor comercial 1 - Terreo', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '22.ARS_VIE Redario_', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '29. ARS_VIE Piscina aquecida', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '30. ARS_VIE Espaço wellness', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '31. ARS_VIE Sauna seca_001', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '34. ARS_VIE Living Apto tipo 1_Torre 1_Angulo01_GERAL', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '35. ARS_VIE Living Apto tipo 1_Torre 1_Angulo02_VISTA', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '36. ARS_VIE Suite Master Apto tipo 1_Torre 1_Geral', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '36. ARS_VIE Suite Master Apto tipo 1_Torre 1_Vista', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '38. ARS_VIE Living Apto tipo 3_Torre 3_001', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '44. ARS_VIE Living do apto tipo 3 da torre_GERAL_', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => 'Tracking_0036', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => 'Tracking_0040', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => 'Tracking_0122', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => 'Tracking_0138', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => 'POV', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => 'POV', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],

];

$status = 'Finalizado';
$data = '2025-11-30';

foreach ($imagens as $img) {
    // Inserir na tabela imagem_animacao
    $stmt = $conn->prepare("INSERT INTO imagem_animacao (imagem_nome, obra_id) VALUES (?, ?)");
    $stmt->bind_param("si", $img['nome'], $img['obra_id']);
    $stmt->execute();

    $imagem_id = $conn->insert_id;

    // Inserir na tabela animacao
    // data_pagamento must be a valid DATE (schema requires NOT NULL). Use data_anima as placeholder when payment not done yet.
    $stmt2 = $conn->prepare("INSERT INTO animacao (imagem_id, colaborador_id, cliente_id, obra_id, status_anima, data_anima, pagamento, valor, data_pagamento) VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?)");
    $dataPagamento = $data; // placeholder valid date; change if you prefer another placeholder
    $stmt2->bind_param("iiiisss", $imagem_id, $img['colaborador_id'], $img['cliente_id'], $img['obra_id'], $status, $data, $dataPagamento);
    $stmt2->execute();

    echo "Inserido: {$img['nome']} (imagem_id = $imagem_id, colaborador_id = {$img['colaborador_id']})\n";
}

echo "Processo concluído.";
