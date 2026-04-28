<?php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido']);
    exit;
}

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$input = is_array($json) ? $json : $_POST;

$imagem_id      = intval($input['imagem_id']      ?? 0);
$obra_id        = intval($input['obra_id']        ?? 0);
$cliente_id     = intval($input['cliente_id']     ?? 0);
$colaborador_id = intval($input['colaborador_id'] ?? 0);
$tipo_animacao  = trim($input['tipo_animacao']    ?? '');
$duracao        = intval($input['duracao']        ?? 0);
$valor          = floatval($input['valor']        ?? 0);
$data_anima     = !empty($input['data_anima'])    ? $input['data_anima']     : date('Y-m-d');
$prazo          = !empty($input['prazo'])          ? $input['prazo']          : null;
$data_pagamento = !empty($input['data_pagamento']) ? $input['data_pagamento'] : null;

$allowed_tipos = ['vertical', 'horizontal', 'reels'];

if (!in_array($tipo_animacao, $allowed_tipos)) {
    echo json_encode(['success' => false, 'error' => 'Tipo de animação inválido']);
    exit;
}
if (!$imagem_id || !$colaborador_id) {
    echo json_encode(['success' => false, 'error' => 'imagem_id e colaborador_id são obrigatórios']);
    exit;
}

$conn->begin_transaction();
try {
    // 1. Inserir animacao (substatus_id=7 = HOLD por DEFAULT na tabela)
    $stmt = $conn->prepare(
        "INSERT INTO animacao
             (imagem_id, colaborador_id, obra_id, cliente_id, duracao, tipo_animacao,
              data_anima, valor, substatus_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 7)"
    );
    $stmt->bind_param(
        'iiiiissd',
        $imagem_id,
        $colaborador_id,
        $obra_id,
        $cliente_id,
        $duracao,
        $tipo_animacao,
        $data_anima,
        $valor
    );
    $stmt->execute();
    $animacao_id = $conn->insert_id;
    $stmt->close();

    // 2. funcao_animacao: funcao_id=10 (Finalização) — colaborador selecionado pelo usuário
    $stmt4 = $conn->prepare(
        "INSERT INTO funcao_animacao (animacao_id, funcao_id, colaborador_id, prazo)
         VALUES (?, 10, ?, ?)"
    );
    $stmt4->bind_param('iis', $animacao_id, $colaborador_id, $prazo);
    $stmt4->execute();
    $stmt4->close();

    // 3. funcao_animacao: funcao_id=5 (Pós-produção) — sempre colaborador_id=13
    $colab_pos = 13;
    $stmt5 = $conn->prepare(
        "INSERT INTO funcao_animacao (animacao_id, funcao_id, colaborador_id, prazo, status)
         VALUES (?, 5, ?, ?, 'HOLD')"
    );
    $stmt5->bind_param('iis', $animacao_id, $colab_pos, $prazo);
    $stmt5->execute();
    $stmt5->close();

    $conn->commit();
    echo json_encode(['success' => true, 'animacao_id' => $animacao_id]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
