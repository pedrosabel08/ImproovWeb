<?php
include 'conexao.php';

$imagens = [
    // ======================
    // HAA_HOR (obra_id = 53, cliente_id = 37)
    // ======================
    // ['nome' => '11. HAA_HOR Salao de festas_EF', 'obra_id' => 53, 'cliente_id' => 37, 'colaborador_id' => 13],
    // ['nome' => '13. HAA_HOR Living_Apato_frente_tres_suites_detalhe', 'obra_id' => 53, 'cliente_id' => 37, 'colaborador_id' => 13],
    // ['nome' => '15. HAA_HOR Suite Apto tipo frente Diurna Vista', 'obra_id' => 53, 'cliente_id' => 37, 'colaborador_id' => 13],
    // ['nome' => 'HAA_HOR_Tracking_0005', 'obra_id' => 53, 'cliente_id' => 37, 'colaborador_id' => 13],

    // // ======================
    // // MSA_HYD (obra_id = 66, cliente_id = 1)
    // // ======================
    // ['nome' => '10. MSA_HYD Academia_Geral', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 13],
    // ['nome' => '10. MSA_HYD Academia_Detalhe', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 13],
    // ['nome' => '15. MSA_HYD_Deck_SPA_EF_2_1', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 13],
    // ['nome' => '16. MSA_HYD_Piscinas_EF_2_1', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 13],
    // ['nome' => '19. MSA_HYD Suite master Apto tipo 1_EF_Detalhe', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 13],
    // ['nome' => '35. MSA_HYD_Fachada_Baixo', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 13],
    // ['nome' => 'MSA_HYD_Tracking_0016', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 13],
    // ['nome' => 'MSA_HYD_tracking_0023', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 13],
    // ['nome' => 'MSA_HYD_Tracking_0028', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 13],
    // ['nome' => '13. MSA_HYD_Salao_de_festas_EF_2_1', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 13],
    // ['nome' => '18. MSA_HYD_Living_do_apartamento_tipo_Studio_GERAL', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 13],
    // ['nome' => '18. MSA_HYD_Living_do_apartamento_tipo_Studio_VISTA', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 13],

    // ======================
    // LD_URB (obra_id = 57, cliente_id = 38)
    // ======================
    // ['nome' => '42_LD9_URB_Living Apto 4301_VISTA_VERT - HOR', 'obra_id' => 57, 'cliente_id' => 38, 'colaborador_id' => 13],
    ['nome' => '42_LD9_URB_Living Apto 4301_VISTA - HOR', 'obra_id' => 57, 'cliente_id' => 38, 'colaborador_id' => 13],
    ['nome' => '42.LD9_URB Living do Apto 4301 - SUITE - HOR', 'obra_id' => 57, 'cliente_id' => 38, 'colaborador_id' => 13],
    // ['nome' => '42.LD9_URB Living do Apto 4301 - SUITE VERT - HOR', 'obra_id' => 57, 'cliente_id' => 38, 'colaborador_id' => 13],
    // ['nome' => '25_LD9_URB_Living Apto 4301_Geral_VERT - HOR', 'obra_id' => 57, 'cliente_id' => 38, 'colaborador_id' => 13],
    // ['nome' => '25_LD9_URB_Living Apto 4301_Geral - VERT', 'obra_id' => 57, 'cliente_id' => 38, 'colaborador_id' => 13],
    // ['nome' => '24.LD9_URB_Living_do_apartamento_Tipo_unidade_4402A_GERAL VERT - HOR', 'obra_id' => 57, 'cliente_id' => 38, 'colaborador_id' => 13],
    // ['nome' => '24.LD9_URB_Living_do_apartamento_Tipo_unidade_4402A_VISTA VERT - HOR', 'obra_id' => 57, 'cliente_id' => 38, 'colaborador_id' => 13],
    // ['nome' => '23.LD9_URB_Suite_do_apartamento_Duplex_unidade_4301_B_R01_4_1 VERT - HOR', 'obra_id' => 57, 'cliente_id' => 38, 'colaborador_id' => 13],
    // ['nome' => '27.LD9_URB_Living_do_apartamento_Studio_lateral unidade 4404B_GERAL - VERT - HOR', 'obra_id' => 57, 'cliente_id' => 38, 'colaborador_id' => 13],
    // ['nome' => '27.LD9_URB_Living_do_apartamento_Studio_lateral unidade 4404B_VISTA - VERT - HOR', 'obra_id' => 57, 'cliente_id' => 38, 'colaborador_id' => 13],

    // // ======================
    // // ALPS (obra_id = 80, cliente_id = 51)
    // // ======================
    // ['nome' => '2.ALP_SC_Conceito_1', 'obra_id' => 80, 'cliente_id' => 51, 'colaborador_id' => 13],
    // ['nome' => 'ALP_SC_tracking_0001', 'obra_id' => 80, 'cliente_id' => 51, 'colaborador_id' => 13],
    // ['nome' => 'ALP_SC_Tracking_0027', 'obra_id' => 80, 'cliente_id' => 51, 'colaborador_id' => 13],
];

$status = 'Finalizado';
$data = '2025-09-30';

foreach ($imagens as $img) {
    // Inserir na tabela imagem_animacao
    $stmt = $conn->prepare("INSERT INTO imagem_animacao (imagem_nome, obra_id) VALUES (?, ?)");
    $stmt->bind_param("si", $img['nome'], $img['obra_id']);
    $stmt->execute();

    $imagem_id = $conn->insert_id;

    // Inserir na tabela animacao
    $stmt2 = $conn->prepare("INSERT INTO animacao (imagem_id, colaborador_id, cliente_id, obra_id, status_anima, data_anima) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt2->bind_param("iiiiss", $imagem_id, $img['colaborador_id'], $img['cliente_id'], $img['obra_id'], $status, $data);
    $stmt2->execute();

    echo "Inserido: {$img['nome']} (imagem_id = $imagem_id, colaborador_id = {$img['colaborador_id']})\n";
}

echo "Processo concluído.";
