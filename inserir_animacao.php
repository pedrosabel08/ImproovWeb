<?php
include 'conexao.php';

$imagens = [

    // ======================
    // ARS_VIE (obra_id = 55, cliente_id = 32)
    // (these were already present; including full list provided)
    // ======================
    ['nome' => 'ARS_VIE_Piscina Externa', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '34. ARS_VIE Living Apto tipo 1_Torre 1_Angulo01_GERAL_IMM_001', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '31.ARS_VIE_Sauna_seca', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '29. ARS_VIE Piscina aquecida', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],



    ['nome' => '16.JON_LIN_Loft_unidade_16_angulo_1', 'obra_id' => 73, 'cliente_id' => 45, 'colaborador_id' => 13],
    ['nome' => '17.JON_LIN-Loft Ângulo_2', 'obra_id' => 73, 'cliente_id' => 45, 'colaborador_id' => 13],
    ['nome' => 'JON_LIN_Brinquedoteca', 'obra_id' => 73, 'cliente_id' => 45, 'colaborador_id' => 13],
    ['nome' => 'JON_LIN_Espaço Beauty', 'obra_id' => 73, 'cliente_id' => 45, 'colaborador_id' => 13],
    ['nome' => 'JON_LIN_Petstore', 'obra_id' => 73, 'cliente_id' => 45, 'colaborador_id' => 13],
    // ======================
    // RDO_VAL (obra_id = 70, cliente_id = 3)
    // ======================
    ['nome' => '1.RDO_VAL_Fotomontagem_aerea_com_insercao_do_empreendimento_em_terreno_real_angulo_1_EF', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '7.RDO_VAL_Embasamento_mostrando_mall_e_calcadas_1_da_Rua_A_EF', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '9.RDO_VAL_Corredor_do_mall_EF', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '9.RDO_VAL_Corredor_do_mall_Detalhe', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '13.RDO_VAL Piscina coberta Geral', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '13.RDO_VAL Piscina coberta Detalhe 1', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '13.RDO_VAL Piscina coberta Detalhe 2', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '20.RDO_VAL_Salao_de_festas_1_EF', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => 'RDO_VAL_Tracking_0019', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => 'RDO_VAL_Playground', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => 'RDO_VAL_Brinquedoteca', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => 'RDO_VAL_Brinquedoteca', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '4.RDO_VAL_Fotomontagem_aerea_com_insercao_do_empreendimento_em_terreno_real_angulo_4_EF', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '6.RDO_VAL_Fachada_no_angulo_do_observador_da_rua_2_EF', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],

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
