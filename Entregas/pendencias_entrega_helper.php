<?php

function entregas_pendencias_ensure_schema(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS entregas_pendencias (
        id INT NOT NULL AUTO_INCREMENT,
        obra_id INT NOT NULL,
        status_id INT NOT NULL,
        imagem_id INT NOT NULL,
        funcao_imagem_id INT NOT NULL,
        historico_id INT DEFAULT NULL,
        motivo VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        status ENUM('aberta','resolvida','ignorada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aberta',
        entrega_id INT DEFAULT NULL,
        entrega_item_id INT DEFAULT NULL,
        criada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        resolvida_em TIMESTAMP NULL DEFAULT NULL,
        resolvida_por INT DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_entregas_pendencias_status (status),
        KEY idx_entregas_pendencias_obra_status (obra_id, status_id),
        KEY idx_entregas_pendencias_imagem_status (imagem_id, status_id),
        KEY idx_entregas_pendencias_funcao (funcao_imagem_id),
        KEY idx_entregas_pendencias_entrega (entrega_id, entrega_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Falha ao garantir tabela entregas_pendencias: ' . $conn->error);
    }

    $ensured = true;
}

function registrar_pendencia_entrega(
    mysqli $conn,
    int $obraId,
    int $statusId,
    int $imagemId,
    int $funcaoImagemId,
    ?int $historicoId = null,
    string $motivo = 'Entrega ausente para imagem aprovada',
    ?array &$logs = null
): int {
    entregas_pendencias_ensure_schema($conn);

    $pendenciaId = 0;
    $stmt = $conn->prepare("SELECT id FROM entregas_pendencias
        WHERE status = 'aberta'
          AND obra_id = ?
          AND status_id = ?
          AND imagem_id = ?
          AND funcao_imagem_id = ?
        ORDER BY id DESC
        LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException('Falha ao preparar busca de pendencia: ' . $conn->error);
    }

    $stmt->bind_param('iiii', $obraId, $statusId, $imagemId, $funcaoImagemId);
    $stmt->execute();
    $stmt->bind_result($pendenciaId);
    $found = $stmt->fetch();
    $stmt->close();

    if ($found && $pendenciaId > 0) {
        $stmtUpd = $conn->prepare("UPDATE entregas_pendencias
            SET historico_id = ?, motivo = ?
            WHERE id = ?");
        if (!$stmtUpd) {
            throw new RuntimeException('Falha ao preparar atualizacao de pendencia: ' . $conn->error);
        }
        $stmtUpd->bind_param('isi', $historicoId, $motivo, $pendenciaId);
        $stmtUpd->execute();
        $stmtUpd->close();

        if (is_array($logs)) {
            $logs[] = "Pendencia de entrega id={$pendenciaId} atualizada para imagem_id={$imagemId}.";
        }
        return (int) $pendenciaId;
    }

    $stmtIns = $conn->prepare("INSERT INTO entregas_pendencias
        (obra_id, status_id, imagem_id, funcao_imagem_id, historico_id, motivo)
        VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmtIns) {
        throw new RuntimeException('Falha ao preparar criacao de pendencia: ' . $conn->error);
    }

    $stmtIns->bind_param('iiiiis', $obraId, $statusId, $imagemId, $funcaoImagemId, $historicoId, $motivo);
    $stmtIns->execute();
    $pendenciaId = (int) $stmtIns->insert_id;
    $stmtIns->close();

    if (is_array($logs)) {
        $logs[] = "Pendencia de entrega id={$pendenciaId} criada para imagem_id={$imagemId}.";
    }

    return $pendenciaId;
}

function resolver_pendencias_entrega(
    mysqli $conn,
    int $entregaId,
    int $imagemId,
    int $entregaItemId,
    ?int $resolvidaPor = null,
    ?array &$logs = null
): int {
    entregas_pendencias_ensure_schema($conn);

    $obraId = 0;
    $statusId = 0;
    $stmtEnt = $conn->prepare("SELECT obra_id, status_id FROM entregas WHERE id = ? LIMIT 1");
    if (!$stmtEnt) {
        throw new RuntimeException('Falha ao preparar busca de entrega: ' . $conn->error);
    }
    $stmtEnt->bind_param('i', $entregaId);
    $stmtEnt->execute();
    $stmtEnt->bind_result($obraId, $statusId);
    $hasEntrega = $stmtEnt->fetch();
    $stmtEnt->close();

    if (!$hasEntrega) {
        return 0;
    }

    $stmtPend = $conn->prepare("SELECT id, historico_id FROM entregas_pendencias
        WHERE status = 'aberta'
          AND obra_id = ?
          AND status_id = ?
          AND imagem_id = ?
        ORDER BY id ASC");
    if (!$stmtPend) {
        throw new RuntimeException('Falha ao preparar busca de pendencias: ' . $conn->error);
    }
    $stmtPend->bind_param('iii', $obraId, $statusId, $imagemId);
    $stmtPend->execute();
    $resPend = $stmtPend->get_result();

    $pendenciaIds = [];
    $historicoId = null;
    while ($resPend && ($row = $resPend->fetch_assoc())) {
        $pendenciaIds[] = (int) $row['id'];
        if (!empty($row['historico_id'])) {
            $historicoId = (int) $row['historico_id'];
        }
    }
    $stmtPend->close();

    if (empty($pendenciaIds)) {
        return 0;
    }

    if ($historicoId) {
        $stmtItem = $conn->prepare("UPDATE entregas_itens
            SET historico_id = ?, status = 'Entrega pendente'
            WHERE id = ?");
        if ($stmtItem) {
            $stmtItem->bind_param('ii', $historicoId, $entregaItemId);
            $stmtItem->execute();
            $stmtItem->close();
        }
    } else {
        $stmtItem = $conn->prepare("UPDATE entregas_itens
            SET status = 'Entrega pendente'
            WHERE id = ?");
        if ($stmtItem) {
            $stmtItem->bind_param('i', $entregaItemId);
            $stmtItem->execute();
            $stmtItem->close();
        }
    }

    $resolved = 0;
    $stmtResolve = $conn->prepare("UPDATE entregas_pendencias
        SET status = 'resolvida',
            entrega_id = ?,
            entrega_item_id = ?,
            resolvida_em = NOW(),
            resolvida_por = ?
        WHERE id = ? AND status = 'aberta'");
    if (!$stmtResolve) {
        throw new RuntimeException('Falha ao preparar resolucao de pendencia: ' . $conn->error);
    }

    foreach ($pendenciaIds as $pendenciaId) {
        $stmtResolve->bind_param('iiii', $entregaId, $entregaItemId, $resolvidaPor, $pendenciaId);
        $stmtResolve->execute();
        $resolved += max(0, (int) $stmtResolve->affected_rows);
    }
    $stmtResolve->close();

    if ($resolved > 0 && is_array($logs)) {
        $logs[] = "{$resolved} pendencia(s) de entrega resolvida(s) para imagem_id={$imagemId}.";
    }

    return $resolved;
}

function contar_pendencias_entrega(mysqli $conn): array
{
    entregas_pendencias_ensure_schema($conn);

    $rows = [];
    $res = $conn->query("SELECT obra_id, COUNT(*) AS total
        FROM entregas_pendencias
        WHERE status = 'aberta'
        GROUP BY obra_id");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[(string) $row['obra_id']] = (int) $row['total'];
        }
    }

    return $rows;
}
