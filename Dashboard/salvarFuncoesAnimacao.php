<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo invalido.']);
    exit;
}

function anim_empty_to_null($value)
{
    if ($value === null) {
        return null;
    }
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function anim_int_to_null($value)
{
    if ($value === null || $value === '') {
        return null;
    }
    return is_numeric($value) ? (int) $value : null;
}

$animacaoId = isset($_POST['animacao_id']) ? (int) $_POST['animacao_id'] : 0;

if ($animacaoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parametro animacao_id invalido.']);
    exit;
}

$stmtCheck = $conn->prepare('SELECT idanimacao FROM animacao WHERE idanimacao = ? LIMIT 1');
$stmtCheck->bind_param('i', $animacaoId);
$stmtCheck->execute();
$exists = $stmtCheck->get_result()->fetch_assoc();
$stmtCheck->close();

if (!$exists) {
    echo json_encode(['success' => false, 'error' => 'Animacao nao encontrada.']);
    $conn->close();
    exit;
}

$funcoes = [
    [
        'funcao_id' => 10,
        'colaborador_id' => anim_int_to_null($_POST['animacao_colaborador_id'] ?? null),
        'status' => anim_empty_to_null($_POST['status_animacao'] ?? null) ?: 'Não iniciado',
        'prazo' => anim_empty_to_null($_POST['prazo_animacao'] ?? null),
        'observacao' => anim_empty_to_null($_POST['obs_animacao'] ?? null),
    ],
    [
        'funcao_id' => 5,
        'colaborador_id' => anim_int_to_null($_POST['pos_colaborador_id'] ?? null),
        'status' => anim_empty_to_null($_POST['status_pos'] ?? null) ?: 'Não iniciado',
        'prazo' => anim_empty_to_null($_POST['prazo_pos'] ?? null),
        'observacao' => anim_empty_to_null($_POST['obs_pos'] ?? null),
    ],
];

$stmtValor = $conn->prepare(
    'SELECT valor FROM funcao_colaborador WHERE colaborador_id = ? AND funcao_id = ? LIMIT 1'
);
$stmtUpsert = $conn->prepare(
    "INSERT INTO funcao_animacao
        (animacao_id, funcao_id, colaborador_id, status, prazo, observacao, valor)
     VALUES (?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        colaborador_id = VALUES(colaborador_id),
        status = VALUES(status),
        prazo = VALUES(prazo),
        observacao = VALUES(observacao),
        valor = VALUES(valor)"
);

if (!$stmtValor || !$stmtUpsert) {
    echo json_encode(['success' => false, 'error' => 'Erro ao preparar consulta.']);
    $conn->close();
    exit;
}

$conn->begin_transaction();

try {
    foreach ($funcoes as $funcao) {
        $valor = 0.0;
        if ($funcao['colaborador_id'] !== null) {
            $stmtValor->bind_param('ii', $funcao['colaborador_id'], $funcao['funcao_id']);
            $stmtValor->execute();
            $rowValor = $stmtValor->get_result()->fetch_assoc();
            if ($rowValor && $rowValor['valor'] !== null) {
                $valor = (float) $rowValor['valor'];
            }
        }

        $stmtUpsert->bind_param(
            'iiisssd',
            $animacaoId,
            $funcao['funcao_id'],
            $funcao['colaborador_id'],
            $funcao['status'],
            $funcao['prazo'],
            $funcao['observacao'],
            $valor
        );
        $stmtUpsert->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$stmtValor->close();
$stmtUpsert->close();
$conn->close();
