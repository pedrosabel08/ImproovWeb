<?php
header('Content-Type: application/json');

include 'conexao.php';


$funcoes = [
    'caderno' => 1,
    'modelagem' => 2,
    'composicao' => 3,
    'finalizacao' => 4,
    'pos_producao' => 5,
    'planta_humanizada' => 7,
];

$resultados = [];

foreach ($funcoes as $funcao => $funcao_id) {

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM funcao_imagem WHERE funcao_id = ?");
    $stmt->bind_param('i', $funcao_id);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    // Consulta para obter a meta
    $stmt = $conn->prepare("SELECT meta_imagens FROM meta_funcao WHERE funcao_id = ? AND ano = ?");
    $ano_atual = date('Y'); // Obtém o ano atual
    $stmt->bind_param('ii', $funcao_id, $ano_atual);
    $stmt->execute();
    $stmt->bind_result($meta);
    $stmt->fetch();
    $stmt->close();

    $porcentagem = $meta > 0 ? ($total / $meta) * 100 : 0;

    $resultados[$funcao] = [
        'total' => $total,
        'meta' => $meta,
        'porcentagem' => round($porcentagem, 2)
    ];
}

$conn->close();

echo json_encode(['status' => 'success', 'data' => $resultados]);
