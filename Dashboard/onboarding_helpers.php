<?php

function dashboard_table_has_column(mysqli $conn, string $table, string $column): bool
{
    $sql = "SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

function dashboard_ensure_onboarding_project_schema(mysqli $conn): void
{
    if (!dashboard_table_has_column($conn, 'cliente', 'nome_completo')) {
        $conn->query(
            "ALTER TABLE cliente
             ADD COLUMN nome_completo VARCHAR(150) NULL AFTER nome_cliente"
        );
    }

    if (!dashboard_table_has_column($conn, 'obra', 'nome_completo')) {
        $conn->query(
            "ALTER TABLE obra
             ADD COLUMN nome_completo VARCHAR(150) NULL AFTER nome_obra"
        );
    }

    if (!dashboard_table_has_column($conn, 'obra', 'prazo_dias_corridos')) {
        $conn->query(
            "ALTER TABLE obra
             ADD COLUMN prazo_dias_corridos TINYINT(1) NOT NULL DEFAULT 0 AFTER dias_uteis"
        );
    }

    if (!dashboard_table_has_column($conn, 'obra_pacote', 'prazo_dias_corridos')) {
        $conn->query(
            "ALTER TABLE obra_pacote
             ADD COLUMN prazo_dias_corridos TINYINT(1) NOT NULL DEFAULT 0 AFTER prazo_contratual"
        );
    }
}

function dashboard_decode_onboarding_metadata($metadata): array
{
    if (!is_string($metadata) || trim($metadata) === '') {
        return [];
    }

    $decoded = json_decode($metadata, true);
    return is_array($decoded) ? $decoded : [];
}

function dashboard_get_onboarding_progress(mysqli $conn): array
{
    $obraMap = [];
    $obraSql = "SELECT idobra, nomenclatura, nome_obra, status_obra
                FROM obra
                WHERE status_obra IN (0, 2)";
    $obraResult = $conn->query($obraSql);
    if ($obraResult) {
        while ($row = $obraResult->fetch_assoc()) {
            $obraMap[(int) $row['idobra']] = [
                'nome_obra' => (string) ($row['nomenclatura'] ?: $row['nome_obra']),
                'status_obra' => isset($row['status_obra']) ? (int) $row['status_obra'] : null,
            ];
        }
    }

    $progress = [];
    $ensureState = static function (int $obraId) use (&$progress, $obraMap): void {
        if (isset($progress[$obraId])) {
            return;
        }

        $obraInfo = $obraMap[$obraId] ?? ['nome_obra' => '', 'status_obra' => null];
        $progress[$obraId] = [
            'idobra' => $obraId,
            'nome_obra' => $obraInfo['nome_obra'],
            'status_obra' => $obraInfo['status_obra'],
            'checklist' => [
                'grupo_cliente' => false,
                'grupo_interno' => false,
                'imagens_importadas' => false,
                'sla_definido' => false,
                'pacotes_definidos' => false,
            ],
            'completed_items' => 0,
            'pending_items' => 5,
            'is_onboarding' => false,
        ];
    };

    foreach ($obraMap as $obraId => $obraInfo) {
        if ((int) ($obraInfo['status_obra'] ?? -1) === 2) {
            $ensureState((int) $obraId);
            $progress[(int) $obraId]['is_onboarding'] = true;
        }
    }

    $hasMetadata = dashboard_table_has_column($conn, 'acompanhamento_email', 'metadata');
    $metadataSelect = $hasMetadata ? 'ae.metadata' : 'NULL AS metadata';
    $eventSql = "SELECT ae.obra_id, ae.tipo, {$metadataSelect}
                 FROM acompanhamento_email ae
                 INNER JOIN obra o ON o.idobra = ae.obra_id
                 WHERE o.status_obra IN (0, 2)
                   AND ae.tipo IN ('PROJECT_START', 'GROUPS_CREATED', 'IMAGES_IMPORTED', 'SLA_DEFINED', 'ONBOARDING_COMPLETED')
                 ORDER BY ae.obra_id ASC, ae.idacompanhamento_email ASC";
    $eventResult = $conn->query($eventSql);
    if ($eventResult) {
        while ($row = $eventResult->fetch_assoc()) {
            $obraId = (int) ($row['obra_id'] ?? 0);
            if ($obraId <= 0) {
                continue;
            }

            $ensureState($obraId);
            $progress[$obraId]['is_onboarding'] = true;

            $tipo = (string) ($row['tipo'] ?? '');
            $metadata = dashboard_decode_onboarding_metadata($row['metadata'] ?? null);

            if ($tipo === 'PROJECT_START') {
                $progress[$obraId]['checklist']['pacotes_definidos'] =
                    $hasMetadata ? !empty($metadata['pacotes']) : true;
                continue;
            }

            if ($tipo === 'SLA_DEFINED') {
                $progress[$obraId]['checklist']['sla_definido'] = true;
                continue;
            }

            if ($tipo === 'IMAGES_IMPORTED') {
                $progress[$obraId]['checklist']['imagens_importadas'] = true;
                continue;
            }

            if ($tipo === 'GROUPS_CREATED') {
                if ($hasMetadata) {
                    $progress[$obraId]['checklist']['grupo_cliente'] =
                        !empty($metadata['grupo_cliente']) || !empty($metadata['cliente']);
                    $progress[$obraId]['checklist']['grupo_interno'] =
                        !empty($metadata['grupo_interno']) || !empty($metadata['interno']);
                } else {
                    $progress[$obraId]['checklist']['grupo_cliente'] = true;
                    $progress[$obraId]['checklist']['grupo_interno'] = true;
                }
                continue;
            }

            if ($tipo === 'ONBOARDING_COMPLETED') {
                foreach ($progress[$obraId]['checklist'] as $checkKey => $done) {
                    $progress[$obraId]['checklist'][$checkKey] = true;
                }
            }
        }
    }

    foreach ($progress as $obraId => $state) {
        $completed = 0;
        foreach ($state['checklist'] as $done) {
            if ($done) {
                $completed++;
            }
        }

        $progress[$obraId]['completed_items'] = $completed;
        $progress[$obraId]['pending_items'] = max(0, 5 - $completed);
        $progress[$obraId]['is_onboarding'] = $progress[$obraId]['is_onboarding'] || $progress[$obraId]['pending_items'] > 0;
    }

    return $progress;
}

function dashboard_next_acompanhamento_ordem(mysqli $conn, int $obraId): int
{
    $stmt = $conn->prepare('SELECT IFNULL(MAX(ordem), 0) + 1 AS next_ordem FROM acompanhamento_email WHERE obra_id = ?');
    if (!$stmt) {
        return 1;
    }

    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return isset($row['next_ordem']) ? (int) $row['next_ordem'] : 1;
}

function dashboard_insert_onboarding_event(mysqli $conn, int $obraId, ?int $colaboradorId, string $tipo, string $assunto, array $metadata = []): void
{
    $metadataJson = '';
    $hasMetadataColumn = dashboard_table_has_column($conn, 'acompanhamento_email', 'metadata');
    if ($hasMetadataColumn && !empty($metadata)) {
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($metadataJson)) {
            $metadataJson = '';
        }
    }

    $ordem = dashboard_next_acompanhamento_ordem($conn, $obraId);
    $data = date('Y-m-d');

    if ($hasMetadataColumn) {
        $sql = 'INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto, data, ordem, tipo, status, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Erro ao preparar histórico operacional: ' . $conn->error);
        }
        $status = 'pendente';
        $stmt->bind_param('iississs', $obraId, $colaboradorId, $assunto, $data, $ordem, $tipo, $status, $metadataJson);
    } else {
        $sql = 'INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto, data, ordem, tipo, status) VALUES (?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Erro ao preparar histórico operacional: ' . $conn->error);
        }
        $status = 'pendente';
        $stmt->bind_param('iississ', $obraId, $colaboradorId, $assunto, $data, $ordem, $tipo, $status);
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Erro ao registrar histórico operacional: ' . $error);
    }

    $stmt->close();
}

function dashboard_get_onboarding_progress_for_obra(mysqli $conn, int $obraId): ?array
{
    $progress = dashboard_get_onboarding_progress($conn);
    return $progress[$obraId] ?? null;
}

function dashboard_finalize_onboarding_if_ready(mysqli $conn, int $obraId, ?int $colaboradorId = null): array
{
    $progress = dashboard_get_onboarding_progress_for_obra($conn, $obraId);
    if (!$progress) {
        return ['completed' => false, 'pending_items' => 5];
    }

    if ((int) ($progress['pending_items'] ?? 0) > 0) {
        return ['completed' => false, 'pending_items' => (int) $progress['pending_items']];
    }

    $existsStmt = $conn->prepare("SELECT 1 FROM acompanhamento_email WHERE obra_id = ? AND tipo = 'ONBOARDING_COMPLETED' LIMIT 1");
    $alreadyCompleted = false;
    if ($existsStmt) {
        $existsStmt->bind_param('i', $obraId);
        $existsStmt->execute();
        $existsResult = $existsStmt->get_result();
        $alreadyCompleted = $existsResult && $existsResult->num_rows > 0;
        $existsStmt->close();
    }

    if (!$alreadyCompleted) {
        dashboard_insert_onboarding_event(
            $conn,
            $obraId,
            $colaboradorId,
            'ONBOARDING_COMPLETED',
            'Onboarding concluído e projeto ativado.',
            ['status_obra' => 'ACTIVE']
        );
    }

    if (dashboard_table_has_column($conn, 'obra', 'status_obra')) {
        $updateStmt = $conn->prepare('UPDATE obra SET status_obra = 0 WHERE idobra = ?');
        if ($updateStmt) {
            $updateStmt->bind_param('i', $obraId);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }

    return ['completed' => true, 'pending_items' => 0];
}
