<?php
include 'conexao.php';

$imagens = [
    // ======================
    // MOREIRA
    // ======================
    ['nome' => '8. IDE_LAU Living do apto tipo 03 angulo 2', 'obra_id' => 48, 'cliente_id' => 35, 'colaborador_id' => 20],
    ['nome' => '9. IDE_LAU Suite tipo', 'obra_id' => 48, 'cliente_id' => 35, 'colaborador_id' => 20],

    // ======================
    // VITOR
    // ======================
    ['nome' => '7. IDE_LAU Living do apartmento Tipo 3', 'obra_id' => 48, 'cliente_id' => 35, 'colaborador_id' => 23],
    ['nome' => '10. IDE_LAU Living Apto Cobertura Geral', 'obra_id' => 48, 'cliente_id' => 35, 'colaborador_id' => 23],
    ['nome' => '10. IDE_LAU Living Apto Cobertura Detalhe', 'obra_id' => 48, 'cliente_id' => 35, 'colaborador_id' => 23],
    ['nome' => '11. IDE_LAU Varanda Cobertura', 'obra_id' => 48, 'cliente_id' => 35, 'colaborador_id' => 23],

    // ======================
    // HSA_HYD
    // ======================
    ['nome' => '13. MSA_HYD Salao de festas', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 23],
    ['nome' => '18. MSA_HYD Living Apto tipo Studio 8_ Vista', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 23],
    ['nome' => '18. MSA_HYD Living Apto tipo Studio 8 GERAL', 'obra_id' => 66, 'cliente_id' => 1, 'colaborador_id' => 23],
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
