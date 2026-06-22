<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nao autenticado.']);
    exit;
}

$allowedLevels = [1, 5];
if (!isset($_SESSION['nivel_acesso']) || !in_array((int) $_SESSION['nivel_acesso'], $allowedLevels, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissao.']);
    exit;
}

require_once __DIR__ . '/../conexao.php';

function onboarding_check_sigla_exists(mysqli $conn, string $table, string $column, string $value, ?string $idColumn = null, ?int $excludeId = null): bool
{
    if ($value === '') {
        return false;
    }

    $where = "UPPER(TRIM({$column})) = UPPER(TRIM(?))";
    $types = 's';
    $params = [$value];

    if ($idColumn && $excludeId && $excludeId > 0) {
        $where .= " AND {$idColumn} <> ?";
        $types .= 'i';
        $params[] = $excludeId;
    }

    $sql = "SELECT 1 FROM {$table} WHERE {$where} LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar consulta.');
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

$clienteSigla = strtoupper(trim((string) ($_GET['cliente_sigla'] ?? '')));
$obraSigla = strtoupper(trim((string) ($_GET['obra_sigla'] ?? '')));
$nomenclatura = strtoupper(trim((string) ($_GET['nomenclatura'] ?? '')));
$clienteId = isset($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : 0;

try {
    echo json_encode([
        'success' => true,
        'cliente_sigla_exists' => onboarding_check_sigla_exists($conn, 'cliente', 'nome_cliente', $clienteSigla, 'idcliente', $clienteId > 0 ? $clienteId : null),
        'obra_sigla_exists' => onboarding_check_sigla_exists($conn, 'obra', 'nome_obra', $obraSigla),
        'nomenclatura_exists' => onboarding_check_sigla_exists($conn, 'obra', 'nomenclatura', $nomenclatura),
    ]);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $throwable->getMessage()]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
