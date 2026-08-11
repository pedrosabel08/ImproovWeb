<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/prazo_entrega_helper.php';

$conn = conectarBanco();

$obra_id = isset($_GET['obra_id']) ? (int) $_GET['obra_id'] : 0;
$status_id = isset($_GET['status_id']) ? (int) $_GET['status_id'] : 0;
$data_recebimento = isset($_GET['data_recebimento']) ? trim((string) $_GET['data_recebimento']) : '';

if ($obra_id <= 0 || $status_id <= 0) {
    echo json_encode(['success' => false, 'msg' => 'Obra e etapa sao obrigatorias para consultar o prazo.']);
    $conn->close();
    exit;
}

if ($data_recebimento !== '' && !entregas_valid_date($data_recebimento)) {
    echo json_encode(['success' => false, 'msg' => 'Data de recebimento invalida.']);
    $conn->close();
    exit;
}

if ($data_recebimento === '') {
    $regra = entregas_obter_regra_prazo($conn, $obra_id, $status_id);
    $conn->close();

    if (!$regra) {
        echo json_encode(['success' => false, 'msg' => 'Prazo nao configurado para esta obra e etapa.']);
        exit;
    }

    echo json_encode(['success' => true, 'data_prevista' => null] + $regra);
    exit;
}

$calculo = entregas_calcular_prazo_previsto($conn, $obra_id, $status_id, $data_recebimento);
$conn->close();

if (!$calculo) {
    echo json_encode(['success' => false, 'msg' => 'Nao foi possivel calcular uma previsao para esta etapa.']);
    exit;
}

echo json_encode(['success' => true] + $calculo);
