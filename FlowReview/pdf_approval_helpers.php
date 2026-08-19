<?php

/**
 * Helpers compartilhados do fluxo de PDFs de Caderno/Filtro.
 *
 * O schema é atualizado de forma compatível para não exigir uma janela
 * separada de migration no deploy. Todas as colunas novas são opcionais e
 * preservam os registros antigos, que continuam apontando para o NAS.
 */

if (!function_exists('pdf_approval_normalize_name')) {
    function pdf_approval_normalize_name($value): string
    {
        $value = trim((string)$value);
        $value = strtr($value, [
            'á' => 'a',
            'à' => 'a',
            'ã' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'Á' => 'A',
            'À' => 'A',
            'Ã' => 'A',
            'Â' => 'A',
            'Ä' => 'A',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'É' => 'E',
            'È' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'í' => 'i',
            'ì' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'Í' => 'I',
            'Ì' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'ó' => 'o',
            'ò' => 'o',
            'õ' => 'o',
            'ô' => 'o',
            'ö' => 'o',
            'Ó' => 'O',
            'Ò' => 'O',
            'Õ' => 'O',
            'Ô' => 'O',
            'Ö' => 'O',
            'ú' => 'u',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'Ú' => 'U',
            'Ù' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'ç' => 'c',
            'Ç' => 'C'
        ]);
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($ascii !== false) {
                $value = $ascii;
            }
        }
        $value = strtolower($value);
        return preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
    }
}

if (!function_exists('pdf_approval_is_deferred_function')) {
    function pdf_approval_is_deferred_function($functionName): bool
    {
        $name = pdf_approval_normalize_name($functionName);
        return $name === 'caderno'
            || strpos($name, 'caderno ') === 0
            || $name === 'filtro'
            || strpos($name, 'filtro ') === 0;
    }
}

if (!function_exists('pdf_approval_is_deferred_pdf')) {
    function pdf_approval_is_deferred_pdf($functionName, $typeOrExtension): bool
    {
        $type = strtolower(trim((string)$typeOrExtension, ". \t\r\n"));
        return pdf_approval_is_deferred_function($functionName) && ($type === 'pdf' || $type === 'application/pdf');
    }
}

if (!function_exists('pdf_approval_ensure_schema')) {
    function pdf_approval_ensure_schema(mysqli $conn): bool
    {
        static $checked = [];
        $key = spl_object_id($conn);
        if (!empty($checked[$key])) {
            return true;
        }

        $definitions = [
            ['arquivo_log', 'caminho_vps', "ALTER TABLE arquivo_log ADD COLUMN caminho_vps VARCHAR(1024) NULL AFTER caminho"],
            ['arquivo_log', 'caminho_nas', "ALTER TABLE arquivo_log ADD COLUMN caminho_nas VARCHAR(1024) NULL AFTER caminho_vps"],
            ['arquivo_log', 'preview_path', "ALTER TABLE arquivo_log ADD COLUMN preview_path VARCHAR(1024) NULL AFTER caminho_nas"],
            ['arquivo_log', 'publicado_em', "ALTER TABLE arquivo_log ADD COLUMN publicado_em DATETIME NULL AFTER status"],
            ['historico_aprovacoes', 'arquivo_log_id', "ALTER TABLE historico_aprovacoes ADD COLUMN arquivo_log_id INT NULL AFTER funcao_animacao_id"],
            ['log_alteracoes', 'arquivo_log_id', "ALTER TABLE log_alteracoes ADD COLUMN arquivo_log_id INT NULL AFTER funcao_imagem_id"],
        ];

        foreach ($definitions as [$table, $column, $alterSql]) {
            $stmt = $conn->prepare(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
            );
            if (!$stmt) {
                error_log("[pdf_approval] falha ao preparar consulta de schema {$table}.{$column}: {$conn->error}");
                return false;
            }
            $stmt->bind_param('ss', $table, $column);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$exists && !@$conn->query($alterSql)) {
                error_log("[pdf_approval] falha ao adicionar {$table}.{$column}: {$conn->error}");
                return false;
            }
        }

        $checked[$key] = true;
        return true;
    }
}

if (!function_exists('pdf_approval_update_log_rows')) {
    function pdf_approval_update_log_rows(mysqli $conn, array $logIds, string $status, ?string $currentPath = null, ?string $vpsPath = null, ?string $nasPath = null, ?string $fileName = null, ?string $fileType = null, ?string $publishedAt = null): bool
    {
        if (empty($logIds) || !pdf_approval_ensure_schema($conn)) {
            return false;
        }

        $sql = "UPDATE arquivo_log
                   SET status = ?,
                       caminho = COALESCE(?, caminho),
                       caminho_vps = COALESCE(?, caminho_vps),
                       caminho_nas = COALESCE(?, caminho_nas),
                       nome_arquivo = COALESCE(?, nome_arquivo),
                       tipo = COALESCE(?, tipo),
                       publicado_em = COALESCE(?, publicado_em)
                 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('[pdf_approval] falha ao preparar atualização de arquivo_log: ' . $conn->error);
            return false;
        }

        foreach ($logIds as $logId) {
            $id = (int)$logId;
            $stmt->bind_param('sssssssi', $status, $currentPath, $vpsPath, $nasPath, $fileName, $fileType, $publishedAt, $id);
            if (!$stmt->execute()) {
                error_log("[pdf_approval] falha ao atualizar arquivo_log id={$id}: " . $stmt->error);
            }
        }
        $ok = $stmt->errno === 0;
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('pdf_approval_update_preview_path')) {
    function pdf_approval_update_preview_path(mysqli $conn, array $logIds, ?string $previewPath): bool
    {
        if (empty($logIds) || !pdf_approval_ensure_schema($conn)) {
            return false;
        }

        $stmt = $conn->prepare("UPDATE arquivo_log SET preview_path = ? WHERE id = ?");
        if (!$stmt) {
            error_log('[pdf_approval] falha ao preparar atualização do preview: ' . $conn->error);
            return false;
        }

        $ok = true;
        foreach ($logIds as $logId) {
            $id = (int)$logId;
            $stmt->bind_param('si', $previewPath, $id);
            if (!$stmt->execute()) {
                $ok = false;
                error_log("[pdf_approval] falha ao atualizar preview_path do arquivo_log id={$id}: " . $stmt->error);
            }
        }
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('pdf_approval_latest_log')) {
    function pdf_approval_latest_log(mysqli $conn, int $funcaoImagemId): ?array
    {
        if ($funcaoImagemId <= 0 || !pdf_approval_ensure_schema($conn)) {
            return null;
        }

        $stmt = $conn->prepare(
            "SELECT id, nome_arquivo, tipo, status, caminho, caminho_vps, caminho_nas, preview_path
               FROM arquivo_log
              WHERE funcao_imagem_id = ?
                AND UPPER(tipo) = 'PDF'
              ORDER BY id DESC
              LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $funcaoImagemId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $row;
    }
}

if (!function_exists('pdf_approval_link_history')) {
    function pdf_approval_link_history(mysqli $conn, int $funcaoImagemId, int $arquivoLogId, ?int $colaboradorId = null): ?int
    {
        if ($funcaoImagemId <= 0 || $arquivoLogId <= 0 || !pdf_approval_ensure_schema($conn)) {
            return null;
        }

        // O trigger de status normalmente cria esta linha. Apenas completamos
        // o vínculo quando ela existe, evitando duplicidade.
        $stmt = $conn->prepare(
            "SELECT id
               FROM historico_aprovacoes
              WHERE funcao_imagem_id = ?
                AND status_novo = 'Em aprovação'
                AND (arquivo_log_id IS NULL OR arquivo_log_id = ?)
                AND data_aprovacao >= COALESCE(
                    (SELECT criado_em FROM arquivo_log WHERE id = ?),
                    NOW()
                ) - INTERVAL 1 HOUR
              ORDER BY id DESC
              LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('iii', $funcaoImagemId, $arquivoLogId, $arquivoLogId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $historyId = (int)$row['id'];
                $update = $conn->prepare("UPDATE historico_aprovacoes SET arquivo_log_id = COALESCE(arquivo_log_id, ?) WHERE id = ?");
                if ($update) {
                    $update->bind_param('ii', $arquivoLogId, $historyId);
                    $update->execute();
                    $update->close();
                }
                return $historyId;
            }
        }

        // Reenvio enquanto a função já está em aprovação: é uma nova submissão
        // de PDF, mas não uma nova transição em log_alteracoes.
        $statusAnterior = 'Em aprovação';
        $responsavel = 1;
        $hasColaborador = $colaboradorId !== null && $colaboradorId > 0;
        $stmt = $conn->prepare(
            $hasColaborador
                ? "INSERT INTO historico_aprovacoes
                    (funcao_imagem_id, status_anterior, status_novo, colaborador_id, responsavel, arquivo_log_id)
               VALUES (?, ?, 'Em aprovação', ?, ?, ?)"
                : "INSERT INTO historico_aprovacoes
                    (funcao_imagem_id, status_anterior, status_novo, colaborador_id, responsavel, arquivo_log_id)
               VALUES (?, ?, 'Em aprovação', NULL, ?, ?)"
        );
        if (!$stmt) {
            error_log('[pdf_approval] falha ao criar histórico de submissão: ' . $conn->error);
            return null;
        }
        if ($hasColaborador) {
            $colaborador = (int)$colaboradorId;
            $stmt->bind_param('isiii', $funcaoImagemId, $statusAnterior, $colaborador, $responsavel, $arquivoLogId);
        } else {
            $stmt->bind_param('isii', $funcaoImagemId, $statusAnterior, $responsavel, $arquivoLogId);
        }
        if (!$stmt->execute()) {
            error_log('[pdf_approval] falha ao inserir histórico de submissão: ' . $stmt->error);
            $stmt->close();
            return null;
        }
        $historyId = (int)$stmt->insert_id;
        $stmt->close();
        return $historyId;
    }
}

if (!function_exists('pdf_approval_link_status_log')) {
    function pdf_approval_link_status_log(mysqli $conn, int $funcaoImagemId, int $arquivoLogId): void
    {
        if ($funcaoImagemId <= 0 || $arquivoLogId <= 0 || !pdf_approval_ensure_schema($conn)) {
            return;
        }
        $stmt = $conn->prepare(
            "SELECT id
               FROM log_alteracoes
              WHERE funcao_imagem_id = ?
                AND status_novo = 'Em aprovação'
                AND data >= COALESCE(
                    (SELECT criado_em FROM arquivo_log WHERE id = ?),
                    NOW()
                ) - INTERVAL 1 HOUR
              ORDER BY id DESC
              LIMIT 1"
        );
        if (!$stmt) return;
        $stmt->bind_param('ii', $funcaoImagemId, $arquivoLogId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return;

        $update = $conn->prepare("UPDATE log_alteracoes SET arquivo_log_id = COALESCE(arquivo_log_id, ?) WHERE id = ?");
        if ($update) {
            $id = (int)$row['id'];
            $update->bind_param('ii', $arquivoLogId, $id);
            $update->execute();
            $update->close();
        }
    }
}

if (!function_exists('pdf_approval_kick_worker')) {
    function pdf_approval_kick_worker(): void
    {
        $script = dirname(__DIR__) . '/scripts/upload_worker.php';
        if (!is_file($script)) return;
        $php = PHP_BINARY ?: 'php';
        if (DIRECTORY_SEPARATOR === '\\') {
            @pclose(@popen('start /B "" ' . escapeshellarg($php) . ' ' . escapeshellarg($script), 'r'));
            return;
        }
        @exec('nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . ' >/dev/null 2>&1 &');
    }
}
