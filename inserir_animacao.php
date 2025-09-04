<?php
include 'conexao.php';

$imagens = [
    // GES_ELI
    ['nome' => '7. IDE_LAU Living do apto tipo', 'obra_id' => 48, 'cliente_id' => 35],
    ['nome' => '8. IDE_LAU Living do apto tipo 03 a definir angulo 2', 'obra_id' => 48, 'cliente_id' => 35],
    ['nome' => '9. IDE_LAU Suite tipo', 'obra_id' => 48, 'cliente_id' => 35],
    ['nome' => '10. IDE_LAU Living Apto Cobertura', 'obra_id' => 48, 'cliente_id' => 35],
    ['nome' => '11. IDE_LAU Varanda Apto Cobertura', 'obra_id' => 48, 'cliente_id' => 35],

    // HAA_HOR
    ['nome' => '4. HAA_HOR Fachada Baixo', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => '5. HAA_HOR Fachada Fora', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => '6. HAA_HOR Beach Point_Geral', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => '6. HAA_HOR Beach Point_Detalhe', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => '7. HAA_HOR Piscinas com vista fotografica real_Angulo 1', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => '8. HAA_HOR Piscinas com vista fotografica real - Geral', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => '8. HAA_HOR Piscinas com vista fotografica real - Detalhe Topo', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => '12. HAA_HOR Living com quatro suites Corte', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => '13. HAA_HOR Living_Apato_frente_tres_suites_EF', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => '14. HAA_HOR Sacada_Apto_Frente_mar', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => '15. HAA_HOR Suite Apto tipo frente Geral', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => '15. HAA_HOR Suite Apto tipo frente Detalhe', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => 'HAA_MOR_Tracking_001', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => 'HAA_MOR_Tracking_002', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => 'HAA_MOR_Tracking_003', 'obra_id' => 53, 'cliente_id' => 37],
    ['nome' => 'HAA_MOR_Tracking_004', 'obra_id' => 53, 'cliente_id' => 37],

    // AYA_KAR
    ['nome' => 'AYA_KAR_Tracking_001', 'obra_id' => 45, 'cliente_id' => 5],
    ['nome' => 'AYA_KAR_Tracking_007', 'obra_id' => 45, 'cliente_id' => 5],
    ['nome' => 'AYA_KAR_Tracking_011', 'obra_id' => 45, 'cliente_id' => 5],

];


$colaborador_id = 13;
$status = 'Finalizado';
$data = '2025-08-31';

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
