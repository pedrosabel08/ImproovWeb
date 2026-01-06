<?php
include 'conexao.php';

$imagens = [

    // ======================
    // ARS_VIE (obra_id = 55, cliente_id = 32)
    // (these were already present; including full list provided)
    // ======================

    ['nome' => 'ARS_VIE_Tracking_Stand up', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '19.ARS_VIE Piscina_Geral VERTICAL', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '44. ARS_VIE Living do apto tipo 3 da torre VERTICAL', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '45.ARS_VIE Jardim das Vieiras', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],
    ['nome' => '46.ARS_VIE Jardim_cinema', 'obra_id' => 55, 'cliente_id' => 32, 'colaborador_id' => 13],

    // ======================
    // RDO_VAL (obra_id = 70, cliente_id = 3)
    // ======================
    ['nome' => '2.RDO_VAL_Fotomontagem_aerea_com_insercao_do_empreendimento_em_terreno_real_angulo', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '3.RDO_VAL_VAL 360', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '3.RDO_VAL_Fachada Parque', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '5.RDO_VAL_Fachada_no_angulo_do_observador_da_rua_1_EF', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '26.RDO_VAL Piscina externa angulo 1', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '26.RDO_VAL Piscina externa angulo 2', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '30.RDO_VAL Piscina geral', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '30.RDO_VAL piscina_Detalhe Topo', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '34.RDO_VAL_Sacada_unid.1704_Sunset', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '37_RDO_VAL Living com vista real fotografica_Geral', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '37_RDO_VAL Living com vista real fotografica_Detalhe', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],
    ['nome' => '37_RDO_VAL Living com vista real fotografica_Sacada', 'obra_id' => 70, 'cliente_id' => 3, 'colaborador_id' => 13],

];

$status = 'Finalizado';
$data = '2025-12-31';

foreach ($imagens as $img) {
    // Inserir na tabela imagem_animacao
    $stmt = $conn->prepare("INSERT INTO imagem_animacao (imagem_nome, obra_id) VALUES (?, ?)");
    $stmt->bind_param("si", $img['nome'], $img['obra_id']);
    $stmt->execute();

    $imagem_id = $conn->insert_id;

    // Inserir na tabela animacao
    // data_pagamento must be a valid DATE (schema requires NOT NULL). Use data_anima as placeholder when payment not done yet.
    $stmt2 = $conn->prepare("INSERT INTO animacao (imagem_id, colaborador_id, cliente_id, obra_id, status_anima, data_anima, pagamento, valor, data_pagamento) VALUES (?, ?, ?, ?, ?, ?, 0, 0)");
    $dataPagamento = $data; // placeholder valid date; change if you prefer another placeholder
    $stmt2->bind_param("iiiiss", $imagem_id, $img['colaborador_id'], $img['cliente_id'], $img['obra_id'], $status, $data);
    $stmt2->execute();

    echo "Inserido: {$img['nome']} (imagem_id = $imagem_id, colaborador_id = {$img['colaborador_id']})\n";
}

echo "Processo concluído.";
