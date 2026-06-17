<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/alteracoes_helper.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['idcolaborador']) && !isset($_SESSION['idusuario'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sessao expirada. Faca login novamente.']);
        exit;
    }
}

function manutencao_arg(string $name, $default = null)
{
    global $argv, $isCli;

    if ($isCli) {
        foreach (array_slice($argv ?? [], 1) as $arg) {
            if (str_starts_with($arg, '--' . $name . '=')) {
                return substr($arg, strlen('--' . $name . '='));
            }
            if ($arg === '--' . $name) {
                return '1';
            }
        }
        return $default;
    }

    return $_GET[$name] ?? $_POST[$name] ?? $default;
}

$obraId = (int) manutencao_arg('obra_id', 0);
$apply = in_array((string) manutencao_arg('apply', '0'), ['1', 'true', 'sim', 'yes'], true);
$dataRecebimento = manutencao_arg('data_recebimento', null);

if ($obraId <= 0) {
    $payload = ['success' => false, 'message' => 'Informe obra_id.'];
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit($isCli ? 1 : 0);
}

try {
    alteracoes_ensure_schema($conn);

    if ($apply) {
        $conn->begin_transaction();
    }

    $result = alteracoes_reconciliar_obra($conn, $obraId, $apply, $dataRecebimento);

    if ($apply) {
        $conn->commit();
    }

    echo json_encode([
        'success' => true,
        'mode' => $apply ? 'apply' : 'dry-run',
        'obra_id' => $obraId,
        'total_reconciliado' => $result['total'],
        'missing' => $result['missing'],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    if ($apply) {
        $conn->rollback();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit($isCli ? 1 : 0);
} finally {
    $conn->close();
}
