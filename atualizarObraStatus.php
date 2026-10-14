<?php

header('Content-Type: application/json; charset=utf-8');

if (file_exists(__DIR__ . '/conexaoMain.php')) {
    include_once __DIR__ . '/conexaoMain.php';
} elseif (file_exists(__DIR__ . '/conexao.php')) {
    include_once __DIR__ . '/conexao.php';
}

function responder_status_obra(array $payload, int $httpStatus = 200): void
{
    http_response_code($httpStatus);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function data_base_valida(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

if (!function_exists('conectarBanco')) {
    responder_status_obra(['success' => false, 'message' => 'Função conectarBanco() não encontrada.'], 500);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    responder_status_obra(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$raw = file_get_contents('php://input');
$input = $raw ? json_decode($raw, true) : null;
if (!is_array($input)) {
    $input = $_POST;
}

$obraId = isset($input['obra_id']) ? (int) $input['obra_id'] : 0;
$status = isset($input['status']) ? (int) $input['status'] : null;
if ($obraId <= 0 || ($status !== 0 && $status !== 1)) {
    responder_status_obra(['success' => false, 'message' => 'Parâmetros inválidos.'], 400);
}

$conn = conectarBanco();
if (!$conn) {
    responder_status_obra(['success' => false, 'message' => 'Erro ao conectar ao banco.'], 500);
}

try {
    $conn->begin_transaction();

    $obraStmt = $conn->prepare('SELECT status_obra FROM obra WHERE idobra = ? FOR UPDATE');
    if (!$obraStmt) {
        throw new RuntimeException('Erro ao consultar a obra: ' . $conn->error);
    }
    $obraStmt->bind_param('i', $obraId);
    if (!$obraStmt->execute()) {
        throw new RuntimeException('Erro ao consultar a obra: ' . $obraStmt->error);
    }
    $obra = $obraStmt->get_result()->fetch_assoc();
    $obraStmt->close();

    if (!$obra) {
        throw new InvalidArgumentException('Obra não encontrada.');
    }

    if ($status === 0) {
        $packageIds = $input['pacote_ids'] ?? null;
        $keepDeadlines = $input['manter_prazos'] ?? null;
        $newStartDate = trim((string) ($input['nova_data_base'] ?? ''));

        if (!is_array($packageIds) || !in_array($keepDeadlines, [true, false, 0, 1, '0', '1'], true)) {
            throw new InvalidArgumentException('Informe os pacotes que deseja reativar e a decisão sobre os prazos.');
        }

        $selectedIds = array_values(array_unique(array_filter(array_map('intval', $packageIds), static function ($id) {
            return $id > 0;
        })));
        if (!$selectedIds) {
            throw new InvalidArgumentException('Selecione ao menos um pacote para reativar.');
        }

        $keepDeadlines = in_array($keepDeadlines, [true, 1, '1'], true);
        if (!$keepDeadlines && !data_base_valida($newStartDate)) {
            throw new InvalidArgumentException('Informe uma nova data-base válida para os prazos.');
        }

        $packageStmt = $conn->prepare(
            "SELECT idobra_pacote, status FROM obra_pacote WHERE obra_id = ? AND status IN ('ATIVO', 'HOLD', 'CONCLUIDO') FOR UPDATE"
        );
        if (!$packageStmt) {
            throw new RuntimeException('Erro ao consultar os pacotes: ' . $conn->error);
        }
        $packageStmt->bind_param('i', $obraId);
        if (!$packageStmt->execute()) {
            throw new RuntimeException('Erro ao consultar os pacotes: ' . $packageStmt->error);
        }
        $result = $packageStmt->get_result();
        $eligiblePackages = [];
        while ($row = $result->fetch_assoc()) {
            $eligiblePackages[(int) $row['idobra_pacote']] = strtoupper((string) $row['status']);
        }
        $packageStmt->close();

        if (array_diff($selectedIds, array_keys($eligiblePackages))) {
            throw new InvalidArgumentException('Um ou mais pacotes selecionados não podem ser reativados. Atualize a página e tente novamente.');
        }

        $packageUpdate = $conn->prepare(
            'UPDATE obra_pacote SET status = ?, data_inicio_sla = CASE WHEN ? = 1 THEN data_inicio_sla ELSE ? END WHERE idobra_pacote = ? AND obra_id = ?'
        );
        if (!$packageUpdate) {
            throw new RuntimeException('Erro ao preparar a atualização dos pacotes: ' . $conn->error);
        }

        $statusAtivo = 'ATIVO';
        $statusHold = 'HOLD';
        foreach ($eligiblePackages as $packageId => $currentPackageStatus) {
            $selected = in_array($packageId, $selectedIds, true);
            if (!$selected && $currentPackageStatus !== 'ATIVO') {
                continue;
            }
            $packageStatus = $selected ? $statusAtivo : $statusHold;
            $preserveDate = $keepDeadlines ? 1 : 0;
            $baseDate = $keepDeadlines ? null : $newStartDate;
            $packageUpdate->bind_param('sisii', $packageStatus, $preserveDate, $baseDate, $packageId, $obraId);
            if (!$packageUpdate->execute()) {
                throw new RuntimeException('Erro ao atualizar pacote: ' . $packageUpdate->error);
            }
        }
        $packageUpdate->close();
    }

    $obraUpdate = $conn->prepare('UPDATE obra SET status_obra = ? WHERE idobra = ?');
    if (!$obraUpdate) {
        throw new RuntimeException('Erro ao preparar atualização da obra: ' . $conn->error);
    }
    $obraUpdate->bind_param('ii', $status, $obraId);
    if (!$obraUpdate->execute()) {
        throw new RuntimeException('Erro ao atualizar obra: ' . $obraUpdate->error);
    }
    $obraUpdate->close();

    $conn->commit();
    responder_status_obra([
        'success' => true,
        'message' => $status === 0 ? 'Obra e pacotes atualizados com sucesso.' : 'Status atualizado com sucesso.',
    ]);
} catch (InvalidArgumentException $e) {
    $conn->rollback();
    responder_status_obra(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    $conn->rollback();
    responder_status_obra(['success' => false, 'message' => $e->getMessage()], 500);
}
