<?php

if (!function_exists('alteracoes_column_exists')) {
    function alteracoes_column_exists(mysqli $conn, string $tableName, string $columnName): bool
    {
        $stmt = $conn->prepare(
            'SELECT 1
               FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = ?
                AND column_name = ?
              LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $tableName, $columnName);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}

if (!function_exists('alteracoes_ensure_schema')) {
    function alteracoes_ensure_schema(mysqli $conn): void
    {
        if (!alteracoes_column_exists($conn, 'alteracoes', 'nivel_complexidade')) {
            $conn->query('ALTER TABLE alteracoes ADD COLUMN nivel_complexidade TINYINT UNSIGNED NULL AFTER status_id');
        }
    }
}

if (!function_exists('alteracoes_next_status_id')) {
    function alteracoes_next_status_id(int $statusId): ?int
    {
        $map = [
            2 => 3,
            3 => 4,
            4 => 5,
            5 => 14,
            14 => 15,
            15 => 15,
        ];

        return $map[$statusId] ?? null;
    }
}

if (!function_exists('alteracoes_next_status_from_funcao')) {
    function alteracoes_next_status_from_funcao(mysqli $conn, int $funcaoImagemId, ?int $currentStatusId = null): int
    {
        if ($currentStatusId !== null) {
            $nextStatus = alteracoes_next_status_id($currentStatusId);
            if ($nextStatus !== null) {
                return $nextStatus;
            }
        }

        $rankByStatus = [
            3 => 1,
            4 => 2,
            5 => 3,
            14 => 4,
            15 => 5,
        ];
        $statusByRank = array_flip($rankByStatus);
        $maxRank = 0;

        $stmt = $conn->prepare('SELECT status_id FROM alteracoes WHERE funcao_id = ? AND status_id IN (3, 4, 5, 14, 15)');
        if ($stmt) {
            $stmt->bind_param('i', $funcaoImagemId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $statusId = (int) $row['status_id'];
                $maxRank = max($maxRank, $rankByStatus[$statusId] ?? 0);
            }
            $stmt->close();
        }

        if ($maxRank <= 0) {
            return 3;
        }

        $currentHistoricStatus = $statusByRank[$maxRank] ?? 15;
        return alteracoes_next_status_id((int) $currentHistoricStatus) ?? 15;
    }
}

if (!function_exists('alteracoes_current_image_status')) {
    function alteracoes_current_image_status(mysqli $conn, int $imagemId): ?int
    {
        $stmt = $conn->prepare('SELECT status_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $imagemId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row && $row['status_id'] !== null ? (int) $row['status_id'] : null;
    }
}

if (!function_exists('alteracoes_get_funcao_alteracao_id')) {
    function alteracoes_get_funcao_alteracao_id(mysqli $conn, int $imagemId): ?int
    {
        $stmt = $conn->prepare('SELECT idfuncao_imagem FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = 6 LIMIT 1');
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $imagemId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int) $row['idfuncao_imagem'] : null;
    }
}

if (!function_exists('alteracoes_upsert_registro')) {
    function alteracoes_upsert_registro(
        mysqli $conn,
        int $funcaoImagemId,
        int $statusId,
        ?string $dataRecebimento = null,
        ?int $nivelComplexidade = null
    ): void {
        alteracoes_ensure_schema($conn);

        $dataRecebimento = $dataRecebimento !== null && trim($dataRecebimento) !== ''
            ? trim($dataRecebimento)
            : date('Y-m-d');

        $stmt = $conn->prepare(
            'INSERT INTO alteracoes (funcao_id, data_recebimento, status_id, nivel_complexidade)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                data_recebimento = VALUES(data_recebimento),
                nivel_complexidade = VALUES(nivel_complexidade)'
        );
        if (!$stmt) {
            throw new RuntimeException('Nao foi possivel preparar o registro de alteracao.');
        }

        $stmt->bind_param('isii', $funcaoImagemId, $dataRecebimento, $statusId, $nivelComplexidade);
        if (!$stmt->execute()) {
            $message = $stmt->error ?: 'Erro desconhecido ao registrar alteracao.';
            $stmt->close();
            throw new RuntimeException($message);
        }
        $stmt->close();
    }
}

if (!function_exists('alteracoes_reconciliar_imagem_atual')) {
    function alteracoes_reconciliar_imagem_atual(mysqli $conn, int $imagemId, ?string $dataRecebimento = null): bool
    {
        $statusId = alteracoes_current_image_status($conn, $imagemId);
        if (!in_array($statusId, [3, 4, 5, 6, 14, 15], true)) {
            return false;
        }

        $funcaoImagemId = alteracoes_get_funcao_alteracao_id($conn, $imagemId);
        if (!$funcaoImagemId) {
            return false;
        }

        alteracoes_upsert_registro($conn, $funcaoImagemId, (int) $statusId, $dataRecebimento);
        return true;
    }
}

if (!function_exists('alteracoes_reconciliar_obra')) {
    function alteracoes_reconciliar_obra(mysqli $conn, int $obraId, bool $apply = false, ?string $dataRecebimento = null): array
    {
        $stmt = $conn->prepare(
            'SELECT
                i.idimagens_cliente_obra AS imagem_id,
                i.imagem_nome,
                i.status_id,
                fi.idfuncao_imagem AS funcao_imagem_id,
                GROUP_CONCAT(a.status_id ORDER BY a.status_id SEPARATOR ",") AS status_existentes
             FROM imagens_cliente_obra i
             JOIN funcao_imagem fi ON fi.imagem_id = i.idimagens_cliente_obra AND fi.funcao_id = 6
             LEFT JOIN alteracoes a ON a.funcao_id = fi.idfuncao_imagem
             LEFT JOIN alteracoes atual ON atual.funcao_id = fi.idfuncao_imagem AND atual.status_id = i.status_id
             WHERE i.obra_id = ?
               AND i.status_id IN (3, 4, 5, 6, 14, 15)
               AND atual.idalt IS NULL
             GROUP BY i.idimagens_cliente_obra, i.imagem_nome, i.status_id, fi.idfuncao_imagem
             ORDER BY i.status_id, i.imagem_nome'
        );
        if (!$stmt) {
            throw new RuntimeException('Nao foi possivel preparar a reconciliacao de alteracoes.');
        }

        $stmt->bind_param('i', $obraId);
        $stmt->execute();
        $result = $stmt->get_result();

        $missing = [];
        while ($row = $result->fetch_assoc()) {
            $row['imagem_id'] = (int) $row['imagem_id'];
            $row['status_id'] = (int) $row['status_id'];
            $row['funcao_imagem_id'] = (int) $row['funcao_imagem_id'];
            $row['status_existentes'] = (string) ($row['status_existentes'] ?? '');
            $missing[] = $row;
        }
        $stmt->close();

        if ($apply) {
            foreach ($missing as $row) {
                alteracoes_upsert_registro(
                    $conn,
                    (int) $row['funcao_imagem_id'],
                    (int) $row['status_id'],
                    $dataRecebimento
                );
            }
        }

        return [
            'obra_id' => $obraId,
            'apply' => $apply,
            'total' => count($missing),
            'missing' => $missing,
        ];
    }
}
