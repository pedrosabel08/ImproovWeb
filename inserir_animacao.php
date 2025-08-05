<?php
include 'conexao.php';

$imagens = [
    // GES_ELI
    ['nome' => 'GES_ELI_Embasamento', 'obra_id' => 48, 'cliente_id' => 36],
    ['nome' => 'GES_ELI_Tracking_Horizonte', 'obra_id' => 48, 'cliente_id' => 36],
    ['nome' => 'GES_ELI_Tracking_Fachada Sul', 'obra_id' => 48, 'cliente_id' => 36],
    ['nome' => 'GES_ELI_Tracking_Aerea Norte', 'obra_id' => 48, 'cliente_id' => 36],

    // TER_ALT
    ['nome' => 'Tracking', 'obra_id' => 61, 'cliente_id' => 33],

    // OTT_EKO
    ['nome' => '2. OTT_EKO_Fachada Fora', 'obra_id' => 17, 'cliente_id' => 13],
    ['nome' => '3. OTT_EKO Fachada diurna no angulo do observador', 'obra_id' => 17, 'cliente_id' => 13],
    ['nome' => '9. OTT_EKO Academia_Vista', 'obra_id' => 17, 'cliente_id' => 13],
    ['nome' => '13. OTT_EKO Living apto tipo 01_Geral', 'obra_id' => 17, 'cliente_id' => 13],
    ['nome' => '13. OTT_EKO Living apto tipo 01_Vista', 'obra_id' => 17, 'cliente_id' => 13],
    ['nome' => '17. OTT_EKO_Festas_Apto_Cobertura_Geral', 'obra_id' => 17, 'cliente_id' => 13],
    ['nome' => '17. OTT_EKO_Festas_Apto_Cobertura_Vista', 'obra_id' => 17, 'cliente_id' => 13],
    ['nome' => 'OTT_EKO_tracking_0055', 'obra_id' => 17, 'cliente_id' => 13],
    ['nome' => 'OTT_EKO_tracking_0058', 'obra_id' => 17, 'cliente_id' => 13],
    ['nome' => 'OTT_EKO_tracking_0063', 'obra_id' => 17, 'cliente_id' => 13],

    // AYA_KAR
    ['nome' => '2.AYA_KAR Fotomontagem aérea Drone', 'obra_id' => 45, 'cliente_id' => 5],
    ['nome' => '4.AYA_KAR_Rampa_da_garagem_com_rasgo_e_Arvore_EF_2_1', 'obra_id' => 45, 'cliente_id' => 5],
    ['nome' => '6._AYA_KAR_Piscina_maior_EF_1_1', 'obra_id' => 45, 'cliente_id' => 5],
    ['nome' => '8. AYA_KAR Sauna_EF', 'obra_id' => 45, 'cliente_id' => 5],
    ['nome' => '7.AYA_KAR Piscina infantil', 'obra_id' => 45, 'cliente_id' => 5],
    ['nome' => '11_AYA_KAR Living Manaca 301 Geral', 'obra_id' => 45, 'cliente_id' => 5],
    ['nome' => '24.AYA_KAR Playground com teto verde', 'obra_id' => 45, 'cliente_id' => 5],
    ['nome' => '30. AYA_KAR Gourmet 2_EF_002', 'obra_id' => 45, 'cliente_id' => 5],

];

$colaborador_id = 13;
$status = 'Finalizado';
$data = '2025-07-31';

foreach ($imagens as $img) {
    // Inserir na tabela imagem_animacao
    $stmt = $conn->prepare("INSERT INTO imagem_animacao (imagem_nome, obra_id) VALUES (?, ?)");
    $stmt->bind_param("si", $img['nome'], $img['obra_id']);
    $stmt->execute();

    $imagem_id = $conn->insert_id;

    // Inserir na tabela animacao
    $stmt2 = $conn->prepare("INSERT INTO animacao (imagem_id, colaborador_id, cliente_id, obra_id, status_anima, data_anima) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt2->bind_param("iiiiss", $imagem_id, $colaborador_id, $img['cliente_id'], $img['obra_id'], $status, $data);
    $stmt2->execute();

    echo "Inserido: {$img['nome']} (imagem_id = $imagem_id)\n";
}

echo "Processo concluído.";
