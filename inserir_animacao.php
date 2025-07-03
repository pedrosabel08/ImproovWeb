<?php
include 'conexao.php';

$imagens = [
    // IDE_LAU
    ['nome' => 'IDE_LAU_Piscina Geral', 'obra_id' => 48, 'cliente_id' => 35],
    ['nome' => 'IDE_LAU_Piscina Detalhe', 'obra_id' => 48, 'cliente_id' => 35],

    // STR_SPA
    ['nome' => 'STR_SPA_Intro', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA_Cena interna Alto', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA Quadras Interna Baixo', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA_Quadra_Rede', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA Cena Saque 1', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA Cena Saque 2', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA_Quadra_Spadel_Geral', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA_Quadra_Beach_1', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA_Quadra_Beach_2', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA_Bar Interno', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA_Corredor', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA_Mulher Beach', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA_Mulher_Entrada', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA_Tracking_0051', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA_Tracking_0050', 'obra_id' => 26, 'cliente_id' => 22],
    ['nome' => 'STR_SPA_Tracking_0116', 'obra_id' => 26, 'cliente_id' => 22],

    // OTT_EKO
    ['nome' => 'OTT_EKO_Piscina', 'obra_id' => 17, 'cliente_id' => 13],
    ['nome' => 'OTT_EKO Vista Living Tipo Final 2 ou 3', 'obra_id' => 17, 'cliente_id' => 13],
    ['nome' => 'OTT_EKO Vista da Tipo final 1', 'obra_id' => 17, 'cliente_id' => 13],
    ['nome' => 'OTT_EKO Living_Apto 2 ou 3 vista mar', 'obra_id' => 17, 'cliente_id' => 13],
];

$colaborador_id = 13;
$status = 'Finalizado';
$data = '2025-06-30';

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
?>
