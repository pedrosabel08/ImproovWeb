<?php

require_once __DIR__ . '/alma_helpers.php';
require_once __DIR__ . '/../conexaoMain.php';

$almaApiLibraryOnly = defined('ALMA_API_LIBRARY_ONLY') && ALMA_API_LIBRARY_ONLY === true;
if (!$almaApiLibraryOnly) {
    alma_require_auth();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $conn = conectarBanco();
    $action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function alma_positive_id(mixed $value, string $label): int
{
    $id = (int) $value;
    if ($id <= 0) {
        throw new InvalidArgumentException($label . ' inválido.');
    }
    return $id;
}

function alma_edit_snapshot(mysqli $conn, int $revisionId): array
{
    $stmt = $conn->prepare('SELECT intencao_geral, sintese_narrativa, lock_version FROM alma_direcao_revisao WHERE id = ?');
    $stmt->bind_param('i', $revisionId);
    $stmt->execute();
    $base = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $base['selecoes'] = [];
    $stmt = $conn->prepare(
        'SELECT d.codigo AS dimensao, s.item_biblioteca_id AS item_id, i.titulo AS item_titulo,
                s.resumo_contextual, s.aplicacao_imagem, s.justificativa, s.observacao_operacional
           FROM alma_revisao_selecao s
           JOIN alma_biblioteca_dimensao d ON d.id = s.dimensao_id
           LEFT JOIN alma_biblioteca_item i ON i.id = s.item_biblioteca_id
          WHERE s.revisao_id = ? ORDER BY d.ordem_jornada, d.ordem_no_pilar, s.ordem, s.id'
    );
    $stmt->bind_param('i', $revisionId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['item_id'] = $row['item_id'] !== null ? (int) $row['item_id'] : null;
        $base['selecoes'][] = $row;
    }
    $stmt->close();
    $base['referencias'] = [];
    $stmt = $conn->prepare(
        'SELECT d.codigo AS dimensao, r.sire_referencia_id,
                COALESCE(sr.titulo, CONCAT("Referência #", sr.id)) AS referencia_titulo,
                r.representa, r.relevancia, r.aplicar, r.nao_copiar, r.observacao
           FROM alma_revisao_referencia r
           JOIN alma_biblioteca_dimensao d ON d.id = r.dimensao_id
           JOIN sire_referencia sr ON sr.id = r.sire_referencia_id
          WHERE r.revisao_id = ? ORDER BY r.id'
    );
    $stmt->bind_param('i', $revisionId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['sire_referencia_id'] = (int) $row['sire_referencia_id'];
        $base['referencias'][] = $row;
    }
    $stmt->close();
    return $base;
}

function alma_clone_revision_graph(mysqli $conn, int $sourceRevisionId, int $targetRevisionId): void
{
    $map = [];
    $stmt = $conn->prepare('SELECT * FROM alma_revisao_selecao WHERE revisao_id = ? ORDER BY id');
    $stmt->bind_param('i', $sourceRevisionId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $insert = $conn->prepare(
            'INSERT INTO alma_revisao_selecao
                (revisao_id, dimensao_id, item_biblioteca_id, principal, resumo_contextual,
                 aplicacao_imagem, justificativa, observacao_operacional, ordem)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $itemId = $row['item_biblioteca_id'] !== null ? (int) $row['item_biblioteca_id'] : null;
        $principal = (int) $row['principal'];
        $ordem = (int) $row['ordem'];
        $insert->bind_param(
            'iiiissssi',
            $targetRevisionId,
            $row['dimensao_id'],
            $itemId,
            $principal,
            $row['resumo_contextual'],
            $row['aplicacao_imagem'],
            $row['justificativa'],
            $row['observacao_operacional'],
            $ordem
        );
        $insert->execute();
        $map[(int) $row['id']] = (int) $conn->insert_id;
        $insert->close();
    }
    $stmt->close();

    $stmt = $conn->prepare('SELECT * FROM alma_revisao_referencia WHERE revisao_id = ? ORDER BY id');
    $stmt->bind_param('i', $sourceRevisionId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $selectionId = $row['selecao_id'] !== null ? ($map[(int) $row['selecao_id']] ?? null) : null;
        $insert = $conn->prepare(
            'INSERT INTO alma_revisao_referencia
                (revisao_id, selecao_id, dimensao_id, sire_referencia_id, representa,
                 relevancia, aplicar, nao_copiar, observacao, criada_por_usuario_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $actor = alma_user_id();
        $insert->bind_param(
            'iiiisssssi',
            $targetRevisionId,
            $selectionId,
            $row['dimensao_id'],
            $row['sire_referencia_id'],
            $row['representa'],
            $row['relevancia'],
            $row['aplicar'],
            $row['nao_copiar'],
            $row['observacao'],
            $actor
        );
        $insert->execute();
        $insert->close();
    }
    $stmt->close();
}

function alma_create_revision(mysqli $conn, array $payload): array
{
    alma_require_capability($conn, ALMA_CAP_EDIT);
    $imageId = alma_positive_id($payload['imagem_id'] ?? 0, 'imagem_id');
    $image = alma_image_context($conn, $imageId);
    if (!$image) {
        throw new RuntimeException('Imagem não encontrada.');
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT * FROM alma_direcao WHERE imagem_id = ? FOR UPDATE');
        $stmt->bind_param('i', $imageId);
        $stmt->execute();
        $direction = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        $actor = alma_user_id();

        if (!$direction) {
            $stmt = $conn->prepare('INSERT INTO alma_direcao (imagem_id, criada_por_usuario_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $imageId, $actor);
            $stmt->execute();
            $directionId = (int) $conn->insert_id;
            $stmt->close();
            alma_event($conn, $directionId, null, 'DIRECAO', $directionId, 'DIRECAO_CRIADA', null, ['imagem_id' => $imageId]);
        } else {
            $directionId = (int) $direction['id'];
            $stmt = $conn->prepare("SELECT id FROM alma_direcao_revisao WHERE direcao_id = ? AND estado = 'RASCUNHO' ORDER BY numero DESC LIMIT 1");
            $stmt->bind_param('i', $directionId);
            $stmt->execute();
            $draft = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($draft && empty($payload['forcar_nova'])) {
                $conn->commit();
                return alma_direction_full($conn, $imageId, (int) $draft['id']);
            }
        }

        $sourceRevisionId = isset($payload['revisao_origem_id']) ? (int) $payload['revisao_origem_id'] : 0;
        if (!$sourceRevisionId && !empty($direction['revisao_ativa_id'])) {
            $sourceRevisionId = (int) $direction['revisao_ativa_id'];
        }
        $source = $sourceRevisionId ? alma_revision_snapshot($conn, $sourceRevisionId) : null;
        if ($source && $source['direcao_id'] !== $directionId) {
            throw new RuntimeException('A revisão de origem não pertence à imagem.');
        }

        $versionId = isset($payload['biblioteca_versao_id']) ? (int) $payload['biblioteca_versao_id'] : 0;
        if (!$versionId && $source) {
            $versionId = (int) $source['biblioteca_versao_id'];
        }
        $library = alma_library_version($conn, $versionId ?: null, false);
        if (!$library) {
            throw new RuntimeException('Nenhuma versão publicada da Biblioteca ALMA está disponível.');
        }
        $versionId = (int) $library['id'];

        $stmt = $conn->prepare('SELECT COALESCE(MAX(numero), 0) + 1 AS proximo FROM alma_direcao_revisao WHERE direcao_id = ?');
        $stmt->bind_param('i', $directionId);
        $stmt->execute();
        $number = (int) $stmt->get_result()->fetch_assoc()['proximo'];
        $stmt->close();
        $intention = $source['intencao_geral'] ?? null;
        $narrative = $source['sintese_narrativa'] ?? null;
        $previousId = $source ? (int) $source['id'] : null;
        $stmt = $conn->prepare(
            "INSERT INTO alma_direcao_revisao
                (direcao_id, numero, biblioteca_versao_id, revisao_anterior_id, estado,
                 intencao_geral, sintese_narrativa, criada_por_usuario_id, atualizada_por_usuario_id)
             VALUES (?, ?, ?, ?, 'RASCUNHO', ?, ?, ?, ?)"
        );
        $stmt->bind_param('iiiissii', $directionId, $number, $versionId, $previousId, $intention, $narrative, $actor, $actor);
        $stmt->execute();
        $revisionId = (int) $conn->insert_id;
        $stmt->close();
        if ($source) {
            alma_clone_revision_graph($conn, $sourceRevisionId, $revisionId);
        }
        alma_event(
            $conn,
            $directionId,
            $revisionId,
            'REVISAO',
            $revisionId,
            'REVISAO_CRIADA',
            null,
            ['numero' => $number, 'revisao_anterior_id' => $previousId, 'biblioteca_versao_id' => $versionId]
        );
        $conn->commit();
        return alma_direction_full($conn, $imageId, $revisionId);
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

function alma_save_revision(mysqli $conn, array $payload): array
{
    alma_require_capability($conn, ALMA_CAP_EDIT);
    $revisionId = alma_positive_id($payload['revisao_id'] ?? 0, 'revisao_id');
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "SELECT r.*, d.imagem_id
               FROM alma_direcao_revisao r
               JOIN alma_direcao d ON d.id = r.direcao_id
              WHERE r.id = ? FOR UPDATE"
        );
        $stmt->bind_param('i', $revisionId);
        $stmt->execute();
        $revision = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$revision) {
            throw new RuntimeException('Revisão não encontrada.');
        }
        if ($revision['estado'] !== 'RASCUNHO') {
            throw new RuntimeException('Somente revisões em rascunho podem ser editadas. Crie uma nova revisão.');
        }
        $clientLock = (int) ($payload['lock_version'] ?? 0);
        if ($clientLock && $clientLock !== (int) $revision['lock_version']) {
            throw new RuntimeException('Esta revisão foi alterada em outra sessão. Recarregue antes de salvar.');
        }
        $before = alma_edit_snapshot($conn, $revisionId);
        $intention = trim((string) ($payload['intencao_geral'] ?? ''));
        $selections = is_array($payload['selecoes'] ?? null) ? $payload['selecoes'] : [];
        $references = is_array($payload['referencias'] ?? null) ? $payload['referencias'] : [];

        $dimensions = [];
        $stmt = $conn->prepare('SELECT * FROM alma_biblioteca_dimensao WHERE versao_id = ? AND ativa = 1');
        $stmt->bind_param('i', $revision['biblioteca_versao_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $dimensions[$row['codigo']] = $row;
        }
        $stmt->close();

        $conn->query('DELETE FROM alma_revisao_referencia WHERE revisao_id = ' . $revisionId);
        $conn->query('DELETE FROM alma_revisao_selecao WHERE revisao_id = ' . $revisionId);
        $selectionMap = [];
        foreach ($selections as $order => $selection) {
            $code = trim((string) ($selection['dimensao_codigo'] ?? ''));
            if (!in_array($code, ALMA_IMAGE_DIMENSIONS, true) || !isset($dimensions[$code])) {
                throw new RuntimeException('Dimensão ALMA inválida: ' . $code);
            }
            $dimension = $dimensions[$code];
            $itemId = !empty($selection['item_biblioteca_id']) ? (int) $selection['item_biblioteca_id'] : null;
            if (!$itemId) {
                continue;
            }
            alma_dimension_and_item($conn, (int) $revision['biblioteca_versao_id'], $code, $itemId);
            $principal = !isset($selection['principal']) || !empty($selection['principal']) ? 1 : 0;
            $sort = (int) ($selection['ordem'] ?? $order);
            $stmt = $conn->prepare(
                'INSERT INTO alma_revisao_selecao (revisao_id, dimensao_id, item_biblioteca_id, principal, ordem)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('iiiii', $revisionId, $dimension['id'], $itemId, $principal, $sort);
            $stmt->execute();
            $selectionMap[$code] = (int) $conn->insert_id;
            $stmt->close();
        }

        foreach ($references as $reference) {
            $code = trim((string) ($reference['dimensao_codigo'] ?? ''));
            if (!in_array($code, ALMA_IMAGE_DIMENSIONS, true) || !isset($dimensions[$code])) {
                throw new RuntimeException('Dimensão da referência inválida: ' . $code);
            }
            $referenceId = alma_positive_id($reference['sire_referencia_id'] ?? 0, 'sire_referencia_id');
            $selectionId = $selectionMap[$code] ?? null;
            if (!$selectionId) {
                throw new RuntimeException('Selecione o item da dimensão antes de vincular referências.');
            }
            $actor = alma_user_id();
            $stmt = $conn->prepare(
                'INSERT INTO alma_revisao_referencia
                    (revisao_id, selecao_id, dimensao_id, sire_referencia_id, representa,
                     relevancia, aplicar, nao_copiar, observacao, criada_por_usuario_id)
                 VALUES (?, ?, ?, ?, "", NULL, "", NULL, NULL, ?)'
            );
            $stmt->bind_param('iiiii', $revisionId, $selectionId, $dimensions[$code]['id'], $referenceId, $actor);
            $stmt->execute();
            $stmt->close();
        }

        foreach ($selectionMap as $code => $selectionId) {
            $itemId = null;
            foreach ($selections as $selection) {
                if (($selection['dimensao_codigo'] ?? '') === $code) {
                    $itemId = (int) ($selection['item_biblioteca_id'] ?? 0);
                    break;
                }
            }
            $referenceIds = [];
            foreach ($references as $reference) {
                if (($reference['dimensao_codigo'] ?? '') === $code) {
                    $referenceIds[] = (int) ($reference['sire_referencia_id'] ?? 0);
                }
            }
            $taxonomy = alma_dimension_and_item($conn, (int) $revision['biblioteca_versao_id'], $code, (int) $itemId);
            alma_classify_references($conn, $taxonomy, $referenceIds);
        }

        $actor = alma_user_id();
        $stmt = $conn->prepare(
            'UPDATE alma_direcao_revisao
                SET intencao_geral = ?, atualizada_por_usuario_id = ?, lock_version = lock_version + 1
              WHERE id = ?'
        );
        $stmt->bind_param('sii', $intention, $actor, $revisionId);
        $stmt->execute();
        $stmt->close();
        $after = alma_edit_snapshot($conn, $revisionId);
        $directionId = (int) $revision['direcao_id'];
        if (($before['intencao_geral'] ?? '') !== $intention) {
            alma_event($conn, $directionId, $revisionId, 'REVISAO', $revisionId, 'INTENCAO_ALTERADA', $before['intencao_geral'] ?? null, $intention);
        }
        if (($before['selecoes'] ?? []) !== ($after['selecoes'] ?? [])) {
            alma_event($conn, $directionId, $revisionId, 'SELECAO', null, 'SELECOES_ALTERADAS', $before['selecoes'] ?? [], $after['selecoes'] ?? []);
        }
        if (($before['referencias'] ?? []) !== ($after['referencias'] ?? [])) {
            $beforeReferences = [];
            foreach (($before['referencias'] ?? []) as $reference) {
                $beforeReferences[$reference['dimensao'] . ':' . $reference['sire_referencia_id']] = $reference;
            }
            $afterReferences = [];
            foreach (($after['referencias'] ?? []) as $reference) {
                $afterReferences[$reference['dimensao'] . ':' . $reference['sire_referencia_id']] = $reference;
            }
            foreach (array_diff_key($afterReferences, $beforeReferences) as $reference) {
                alma_event($conn, $directionId, $revisionId, 'REFERENCIA', (int) $reference['sire_referencia_id'], 'REFERENCIA_VINCULADA', null, $reference);
            }
            foreach (array_diff_key($beforeReferences, $afterReferences) as $reference) {
                alma_event($conn, $directionId, $revisionId, 'REFERENCIA', (int) $reference['sire_referencia_id'], 'REFERENCIA_DESVINCULADA', $reference, null);
            }
        }
        $oldActive = null;
        $stmt = $conn->prepare('SELECT revisao_ativa_id FROM alma_direcao WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $directionId);
        $stmt->execute();
        $oldActive = $stmt->get_result()->fetch_assoc()['revisao_ativa_id'] ?? null;
        $stmt->close();
        if ($oldActive && (int) $oldActive !== $revisionId) {
            $stmt = $conn->prepare("UPDATE alma_direcao_revisao SET estado='SUBSTITUIDA', ativa_token=NULL WHERE id=? AND direcao_id=?");
            $oldActiveId = (int) $oldActive;
            $stmt->bind_param('ii', $oldActiveId, $directionId);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $conn->prepare("UPDATE alma_direcao_revisao SET estado='ATIVA', ativa_token='ATIVA', ativada_em=NOW() WHERE id=?");
        $stmt->bind_param('i', $revisionId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('UPDATE alma_direcao SET revisao_ativa_id=? WHERE id=?');
        $stmt->bind_param('ii', $revisionId, $directionId);
        $stmt->execute();
        $stmt->close();
        alma_event($conn, $directionId, $revisionId, 'REVISAO', $revisionId, 'ALMA_SALVO', ['revisao_ativa_id' => $oldActive], ['revisao_ativa_id' => $revisionId]);
        $conn->commit();
        return alma_direction_full($conn, (int) $revision['imagem_id'], $revisionId);
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

function alma_project_edit_snapshot(mysqli $conn, int $projectDirectionId): array
{
    $snapshot = alma_project_snapshot($conn, $projectDirectionId);
    return [
        'lock_version' => $snapshot['lock_version'] ?? 0,
        'selecoes' => array_map(static function (array $selection): array {
            return [
                'dimensao' => $selection['dimensao_codigo'],
                'item_id' => $selection['item_biblioteca_id'],
                'item_titulo' => $selection['item_titulo'],
                'referencias' => array_map(static fn(array $reference): int => (int) $reference['sire_referencia_id'], $selection['referencias'] ?? []),
            ];
        }, $snapshot['selecoes'] ?? []),
    ];
}

function alma_save_project(mysqli $conn, array $payload): array
{
    alma_require_capability($conn, ALMA_CAP_EDIT);
    $obraId = alma_positive_id($payload['obra_id'] ?? 0, 'obra_id');
    $versionId = alma_positive_id($payload['biblioteca_versao_id'] ?? 0, 'biblioteca_versao_id');
    $selections = is_array($payload['selecoes'] ?? null) ? $payload['selecoes'] : [];
    $references = is_array($payload['referencias'] ?? null) ? $payload['referencias'] : [];
    if (!alma_library_version($conn, $versionId, false)) {
        throw new RuntimeException('A versão publicada da Biblioteca ALMA não existe.');
    }
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT id, lock_version FROM alma_projeto_direcao WHERE obra_id = ? FOR UPDATE');
        $stmt->bind_param('i', $obraId);
        $stmt->execute();
        $project = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $actor = alma_user_id();
        if (!$project) {
            $stmt = $conn->prepare('INSERT INTO alma_projeto_direcao (obra_id, biblioteca_versao_id, criada_por_usuario_id, atualizada_por_usuario_id) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('iiii', $obraId, $versionId, $actor, $actor);
            $stmt->execute();
            $projectDirectionId = (int) $conn->insert_id;
            $stmt->close();
            $before = ['lock_version' => 0, 'selecoes' => []];
        } else {
            $projectDirectionId = (int) $project['id'];
            $clientLock = (int) ($payload['lock_version'] ?? 0);
            if ($clientLock && $clientLock !== (int) $project['lock_version']) {
                throw new RuntimeException('O ALMA do projeto foi alterado em outra sessão. Recarregue antes de salvar.');
            }
            $before = alma_project_edit_snapshot($conn, $projectDirectionId);
        }

        $conn->query('DELETE pr FROM alma_projeto_referencia pr JOIN alma_projeto_selecao ps ON ps.id = pr.selecao_id WHERE ps.projeto_direcao_id = ' . $projectDirectionId);
        $conn->query('DELETE FROM alma_projeto_selecao WHERE projeto_direcao_id = ' . $projectDirectionId);
        $selectionMap = [];
        $taxonomyMap = [];
        foreach ($selections as $order => $selection) {
            $code = trim((string) ($selection['dimensao_codigo'] ?? ''));
            if (!in_array($code, ALMA_PROJECT_DIMENSIONS, true)) {
                throw new RuntimeException('Dimensão global ALMA inválida: ' . $code);
            }
            $itemId = (int) ($selection['item_biblioteca_id'] ?? 0);
            if (!$itemId) {
                continue;
            }
            $taxonomy = alma_dimension_and_item($conn, $versionId, $code, $itemId);
            $dimensionId = (int) $taxonomy['dimensao_id'];
            $stmt = $conn->prepare('INSERT INTO alma_projeto_selecao (projeto_direcao_id, dimensao_id, item_biblioteca_id, ordem) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('iiii', $projectDirectionId, $dimensionId, $itemId, $order);
            $stmt->execute();
            $selectionMap[$code] = (int) $conn->insert_id;
            $taxonomyMap[$code] = $taxonomy;
            $stmt->close();
        }
        $referenceMap = [];
        foreach ($references as $reference) {
            $code = trim((string) ($reference['dimensao_codigo'] ?? ''));
            $referenceId = alma_positive_id($reference['sire_referencia_id'] ?? 0, 'sire_referencia_id');
            if (empty($selectionMap[$code])) {
                throw new RuntimeException('Selecione o item global antes de vincular referências.');
            }
            $stmt = $conn->prepare('INSERT INTO alma_projeto_referencia (selecao_id, sire_referencia_id, criada_por_usuario_id) VALUES (?, ?, ?)');
            $stmt->bind_param('iii', $selectionMap[$code], $referenceId, $actor);
            $stmt->execute();
            $stmt->close();
            $referenceMap[$code][] = $referenceId;
        }
        foreach ($taxonomyMap as $code => $taxonomy) {
            alma_classify_references($conn, $taxonomy, $referenceMap[$code] ?? []);
        }
        $stmt = $conn->prepare('UPDATE alma_projeto_direcao SET biblioteca_versao_id=?, atualizada_por_usuario_id=?, lock_version=lock_version+1 WHERE id=?');
        $stmt->bind_param('iii', $versionId, $actor, $projectDirectionId);
        $stmt->execute();
        $stmt->close();
        $after = alma_project_edit_snapshot($conn, $projectDirectionId);
        $beforeJson = json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $afterJson = json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $conn->prepare('INSERT INTO alma_projeto_evento (projeto_direcao_id, ator_usuario_id, acao, antes_json, depois_json) VALUES (?, ?, "PROJETO_ALMA_SALVO", ?, ?)');
        $stmt->bind_param('iiss', $projectDirectionId, $actor, $beforeJson, $afterJson);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        return alma_project_snapshot($conn, $projectDirectionId);
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

function alma_block_signature(?array $selection): string
{
    if (!$selection) {
        return '';
    }
    $references = array_map(static fn(array $reference): int => (int) $reference['sire_referencia_id'], $selection['referencias'] ?? []);
    sort($references);
    return (string) ($selection['item_codigo'] ?? $selection['item_biblioteca_id'] ?? '') . ':' . implode(',', $references);
}

function alma_new_image_revision(mysqli $conn, int $imageId, ?int $preferredVersionId = null): array
{
    $image = alma_image_context($conn, $imageId);
    if (!$image || $image['tipo_imagem'] === ALMA_EXCLUDED_IMAGE_TYPE) {
        throw new RuntimeException('Imagem de destino inválida para o ALMA.');
    }
    $stmt = $conn->prepare('SELECT * FROM alma_direcao WHERE imagem_id=? FOR UPDATE');
    $stmt->bind_param('i', $imageId);
    $stmt->execute();
    $direction = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $actor = alma_user_id();
    if (!$direction) {
        $stmt = $conn->prepare('INSERT INTO alma_direcao (imagem_id, criada_por_usuario_id) VALUES (?, ?)');
        $stmt->bind_param('ii', $imageId, $actor);
        $stmt->execute();
        $directionId = (int) $conn->insert_id;
        $stmt->close();
        $sourceId = null;
    } else {
        $directionId = (int) $direction['id'];
        $sourceId = !empty($direction['revisao_ativa_id']) ? (int) $direction['revisao_ativa_id'] : null;
        if (!$sourceId) {
            $stmt = $conn->prepare('SELECT id FROM alma_direcao_revisao WHERE direcao_id=? ORDER BY numero DESC LIMIT 1');
            $stmt->bind_param('i', $directionId);
            $stmt->execute();
            $sourceId = (int) ($stmt->get_result()->fetch_assoc()['id'] ?? 0) ?: null;
            $stmt->close();
        }
    }
    $source = $sourceId ? alma_revision_snapshot($conn, $sourceId) : null;
    $versionId = $source['biblioteca_versao_id'] ?? $preferredVersionId;
    $library = alma_library_version($conn, $versionId ? (int) $versionId : null, false);
    if (!$library) {
        throw new RuntimeException('Biblioteca ALMA publicada não encontrada.');
    }
    $versionId = (int) $library['id'];
    $stmt = $conn->prepare('SELECT COALESCE(MAX(numero),0)+1 n FROM alma_direcao_revisao WHERE direcao_id=?');
    $stmt->bind_param('i', $directionId);
    $stmt->execute();
    $number = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
    $intention = $source['intencao_geral'] ?? null;
    $stmt = $conn->prepare("INSERT INTO alma_direcao_revisao (direcao_id,numero,biblioteca_versao_id,revisao_anterior_id,estado,intencao_geral,criada_por_usuario_id,atualizada_por_usuario_id) VALUES (?,?,?,?,'RASCUNHO',?,?,?)");
    $stmt->bind_param('iiiisii', $directionId, $number, $versionId, $sourceId, $intention, $actor, $actor);
    $stmt->execute();
    $revisionId = (int) $conn->insert_id;
    $stmt->close();
    if ($sourceId) {
        alma_clone_revision_graph($conn, $sourceId, $revisionId);
    }
    $allowedCodes = "'" . implode("','", ALMA_IMAGE_DIMENSIONS) . "'";
    $conn->query(
        "DELETE ar FROM alma_revisao_referencia ar
          JOIN alma_biblioteca_dimensao d ON d.id=ar.dimensao_id
         WHERE ar.revisao_id=$revisionId AND d.codigo NOT IN ($allowedCodes)"
    );
    $conn->query(
        "DELETE s FROM alma_revisao_selecao s
          JOIN alma_biblioteca_dimensao d ON d.id=s.dimensao_id
         WHERE s.revisao_id=$revisionId AND d.codigo NOT IN ($allowedCodes)"
    );
    return ['direction_id' => $directionId, 'revision_id' => $revisionId, 'source_revision_id' => $sourceId, 'version_id' => $versionId, 'image' => $image];
}

function alma_copy_block_to_revision(mysqli $conn, array $sourceSelection, array $target, string $dimensionCode): void
{
    $stmt = $conn->prepare(
        'SELECT d.id AS dimensao_id, i.id AS item_id, d.pilar_codigo, d.pilar_nome,
                d.nome AS dimensao_nome, d.codigo AS dimensao_codigo, i.titulo AS item_titulo
           FROM alma_biblioteca_dimensao d
           JOIN alma_biblioteca_item i ON i.dimensao_id=d.id
          WHERE d.versao_id=? AND d.codigo=? AND i.codigo=? AND d.ativa=1 AND i.ativo=1 LIMIT 1'
    );
    $itemCode = (string) $sourceSelection['item_codigo'];
    $stmt->bind_param('iss', $target['version_id'], $dimensionCode, $itemCode);
    $stmt->execute();
    $taxonomy = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$taxonomy) {
        throw new RuntimeException('O item da imagem base não existe na versão ALMA da imagem de destino.');
    }
    foreach (['dimensao_id', 'item_id'] as $field) {
        $taxonomy[$field] = (int) $taxonomy[$field];
    }
    $stmt = $conn->prepare('DELETE FROM alma_revisao_referencia WHERE revisao_id=? AND dimensao_id IN (SELECT id FROM alma_biblioteca_dimensao WHERE versao_id=? AND codigo=?)');
    $stmt->bind_param('iis', $target['revision_id'], $target['version_id'], $dimensionCode);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare('DELETE FROM alma_revisao_selecao WHERE revisao_id=? AND dimensao_id IN (SELECT id FROM alma_biblioteca_dimensao WHERE versao_id=? AND codigo=?)');
    $stmt->bind_param('iis', $target['revision_id'], $target['version_id'], $dimensionCode);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare('INSERT INTO alma_revisao_selecao (revisao_id,dimensao_id,item_biblioteca_id,principal,ordem) VALUES (?,?,?,1,0)');
    $stmt->bind_param('iii', $target['revision_id'], $taxonomy['dimensao_id'], $taxonomy['item_id']);
    $stmt->execute();
    $selectionId = (int) $conn->insert_id;
    $stmt->close();
    $referenceIds = [];
    foreach (($sourceSelection['referencias'] ?? []) as $reference) {
        $referenceId = (int) $reference['sire_referencia_id'];
        $actor = alma_user_id();
        $stmt = $conn->prepare('INSERT INTO alma_revisao_referencia (revisao_id,selecao_id,dimensao_id,sire_referencia_id,representa,aplicar,criada_por_usuario_id) VALUES (?,?,?, ?,"","",?)');
        $stmt->bind_param('iiiii', $target['revision_id'], $selectionId, $taxonomy['dimensao_id'], $referenceId, $actor);
        $stmt->execute();
        $stmt->close();
        $referenceIds[] = $referenceId;
    }
    alma_classify_references($conn, $taxonomy, $referenceIds);
}

function alma_activate_operational_revision(mysqli $conn, array $target, string $action, array $context): void
{
    $directionId = (int) $target['direction_id'];
    $revisionId = (int) $target['revision_id'];
    $oldActive = $target['source_revision_id'] ? (int) $target['source_revision_id'] : null;
    if ($oldActive) {
        $stmt = $conn->prepare("UPDATE alma_direcao_revisao SET estado='SUBSTITUIDA',ativa_token=NULL WHERE id=? AND direcao_id=?");
        $stmt->bind_param('ii', $oldActive, $directionId);
        $stmt->execute();
        $stmt->close();
    }
    $actor = alma_user_id();
    $stmt = $conn->prepare("UPDATE alma_direcao_revisao SET estado='ATIVA',ativa_token='ATIVA',ativada_em=NOW(),atualizada_por_usuario_id=? WHERE id=?");
    $stmt->bind_param('ii', $actor, $revisionId);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare('UPDATE alma_direcao SET revisao_ativa_id=? WHERE id=?');
    $stmt->bind_param('ii', $revisionId, $directionId);
    $stmt->execute();
    $stmt->close();
    alma_event($conn, $directionId, $revisionId, 'COPIA', null, $action, null, $context);
}

function alma_copy_from_image(mysqli $conn, array $payload): array
{
    alma_require_capability($conn, ALMA_CAP_EDIT);
    $sourceImageId = alma_positive_id($payload['imagem_origem_id'] ?? 0, 'imagem_origem_id');
    $targetImageId = alma_positive_id($payload['imagem_destino_id'] ?? 0, 'imagem_destino_id');
    $codes = array_values(array_intersect(ALMA_IMAGE_DIMENSIONS, array_map('strval', $payload['dimensoes'] ?? [])));
    if (!$codes || $sourceImageId === $targetImageId) {
        throw new InvalidArgumentException('Selecione uma imagem base e ao menos uma dimensão válida.');
    }
    $sourceImage = alma_image_context($conn, $sourceImageId);
    $targetImage = alma_image_context($conn, $targetImageId);
    if (!$sourceImage || !$targetImage || (int) $sourceImage['obra_id'] !== (int) $targetImage['obra_id']) {
        throw new RuntimeException('A imagem base deve pertencer à mesma obra.');
    }
    $source = alma_direction_full($conn, $sourceImageId)['revisao'] ?? null;
    if (!$source) {
        throw new RuntimeException('A imagem base não possui ALMA persistido.');
    }
    $sourceMap = [];
    foreach ($source['selecoes'] as $selection) {
        $sourceMap[$selection['dimensao_codigo']] = $selection;
    }
    foreach ($codes as $code) {
        if (empty($sourceMap[$code]['item_biblioteca_id'])) {
            throw new RuntimeException('A imagem base não possui ' . $code . ' definido.');
        }
    }
    $current = alma_direction_full($conn, $targetImageId)['revisao'] ?? null;
    $currentMap = [];
    foreach (($current['selecoes'] ?? []) as $selection) {
        $currentMap[$selection['dimensao_codigo']] = $selection;
    }
    $conflicts = array_values(array_filter($codes, static fn(string $code): bool => !empty($currentMap[$code]) && alma_block_signature($currentMap[$code]) !== alma_block_signature($sourceMap[$code])));
    if ($conflicts && empty($payload['confirmar_conflitos'])) {
        throw new RuntimeException('Confirme explicitamente a substituição das dimensões já configuradas.');
    }
    $conn->begin_transaction();
    try {
        $target = alma_new_image_revision($conn, $targetImageId, (int) $source['biblioteca_versao_id']);
        foreach ($codes as $code) {
            alma_copy_block_to_revision($conn, $sourceMap[$code], $target, $code);
        }
        alma_activate_operational_revision($conn, $target, 'IMAGEM_BASE_APLICADA', ['imagem_origem_id' => $sourceImageId, 'dimensoes' => $codes, 'conflitos' => $conflicts]);
        $conn->commit();
        return alma_direction_full($conn, $targetImageId);
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

function alma_apply_dimension(mysqli $conn, array $payload): array
{
    alma_require_capability($conn, ALMA_CAP_EDIT);
    $sourceImageId = alma_positive_id($payload['imagem_origem_id'] ?? 0, 'imagem_origem_id');
    $code = trim((string) ($payload['dimensao_codigo'] ?? ''));
    if (!in_array($code, ALMA_IMAGE_DIMENSIONS, true)) {
        throw new InvalidArgumentException('Dimensão inválida para aplicação em lote.');
    }
    $targetIds = array_values(array_unique(array_filter(array_map('intval', $payload['imagens_destino_ids'] ?? []), static fn(int $id): bool => $id > 0 && $id !== $sourceImageId)));
    $confirmedIds = array_values(array_unique(array_map('intval', $payload['conflitos_confirmados_ids'] ?? [])));
    if (!$targetIds) {
        throw new InvalidArgumentException('Selecione ao menos uma imagem de destino.');
    }
    $sourceImage = alma_image_context($conn, $sourceImageId);
    $source = alma_direction_full($conn, $sourceImageId)['revisao'] ?? null;
    $sourceSelection = null;
    foreach (($source['selecoes'] ?? []) as $selection) {
        if ($selection['dimensao_codigo'] === $code) {
            $sourceSelection = $selection;
            break;
        }
    }
    if (!$sourceImage || !$sourceSelection || empty($sourceSelection['item_biblioteca_id'])) {
        throw new RuntimeException('A dimensão de origem ainda não foi definida.');
    }
    $targets = [];
    foreach ($targetIds as $targetId) {
        $image = alma_image_context($conn, $targetId);
        if (!$image || (int) $image['obra_id'] !== (int) $sourceImage['obra_id'] || $image['tipo_imagem'] === ALMA_EXCLUDED_IMAGE_TYPE) {
            throw new RuntimeException('Uma imagem de destino não é elegível ou pertence a outra obra.');
        }
        $revision = alma_direction_full($conn, $targetId)['revisao'] ?? null;
        $existing = null;
        foreach (($revision['selecoes'] ?? []) as $selection) {
            if ($selection['dimensao_codigo'] === $code) {
                $existing = $selection;
                break;
            }
        }
        $conflict = $existing && alma_block_signature($existing) !== alma_block_signature($sourceSelection);
        if ($conflict && !in_array($targetId, $confirmedIds, true)) {
            throw new RuntimeException('Confirme explicitamente todas as substituições em conflito.');
        }
        $targets[] = ['id' => $targetId, 'conflict' => (bool) $conflict];
    }
    $conn->begin_transaction();
    try {
        foreach ($targets as $item) {
            $target = alma_new_image_revision($conn, $item['id'], (int) $source['biblioteca_versao_id']);
            alma_copy_block_to_revision($conn, $sourceSelection, $target, $code);
            alma_activate_operational_revision($conn, $target, 'DIMENSAO_APLICADA_EM_LOTE', ['imagem_origem_id' => $sourceImageId, 'dimensao' => $code, 'conflito' => $item['conflict']]);
        }
        $conn->commit();
        return ['imagens' => alma_project_images($conn, (int) $sourceImage['obra_id'])];
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

function alma_activate_revision(mysqli $conn, array $payload): array
{
    alma_require_capability($conn, ALMA_CAP_ACTIVATE);
    $revisionId = alma_positive_id($payload['revisao_id'] ?? 0, 'revisao_id');
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'SELECT r.*, d.imagem_id, d.revisao_ativa_id
               FROM alma_direcao_revisao r JOIN alma_direcao d ON d.id = r.direcao_id
              WHERE r.id = ? FOR UPDATE'
        );
        $stmt->bind_param('i', $revisionId);
        $stmt->execute();
        $revision = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$revision) {
            throw new RuntimeException('Revisão não encontrada.');
        }
        if ($revision['estado'] === 'ATIVA') {
            $conn->commit();
            return alma_direction_full($conn, (int) $revision['imagem_id'], $revisionId);
        }
        if ($revision['estado'] !== 'RASCUNHO') {
            throw new RuntimeException('Somente uma revisão em rascunho pode ser ativada.');
        }
        if (trim((string) $revision['intencao_geral']) === '' || trim((string) $revision['sintese_narrativa']) === '') {
            throw new RuntimeException('Preencha a intenção geral e a síntese narrativa antes de ativar.');
        }

        $stmt = $conn->prepare(
            'SELECT d.codigo, d.pilar_codigo, s.item_biblioteca_id, s.resumo_contextual, s.aplicacao_imagem
               FROM alma_revisao_selecao s
               JOIN alma_biblioteca_dimensao d ON d.id = s.dimensao_id
              WHERE s.revisao_id = ?'
        );
        $stmt->bind_param('i', $revisionId);
        $stmt->execute();
        $selected = [];
        $photoContext = false;
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $selected[$row['codigo']] = true;
            if ($row['pilar_codigo'] === 'fotografia' && trim((string) ($row['resumo_contextual'] ?: $row['aplicacao_imagem'])) !== '') {
                $photoContext = true;
            }
        }
        $stmt->close();
        $required = ['atmosfera', 'arquitetura', 'materialidade', 'luz_momento', 'luz_linguagem', 'lifestyle', 'composicao'];
        $missing = array_values(array_filter($required, static fn(string $code): bool => empty($selected[$code])));
        if (!$photoContext) {
            $missing[] = 'fotografia';
        }
        if ($missing) {
            throw new RuntimeException('Direção incompleta. Revise: ' . implode(', ', $missing) . '.');
        }

        $directionId = (int) $revision['direcao_id'];
        $oldActive = $revision['revisao_ativa_id'] !== null ? (int) $revision['revisao_ativa_id'] : null;
        if ($oldActive) {
            $stmt = $conn->prepare("UPDATE alma_direcao_revisao SET estado = 'SUBSTITUIDA', ativa_token = NULL WHERE id = ? AND direcao_id = ?");
            $stmt->bind_param('ii', $oldActive, $directionId);
            $stmt->execute();
            $stmt->close();
        }
        $actor = alma_user_id();
        $stmt = $conn->prepare(
            "UPDATE alma_direcao_revisao
                SET estado = 'ATIVA', ativa_token = 'ATIVA', ativada_em = NOW(), atualizada_por_usuario_id = ?, lock_version = lock_version + 1
              WHERE id = ?"
        );
        $stmt->bind_param('ii', $actor, $revisionId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('UPDATE alma_direcao SET revisao_ativa_id = ? WHERE id = ?');
        $stmt->bind_param('ii', $revisionId, $directionId);
        $stmt->execute();
        $stmt->close();
        alma_event($conn, $directionId, $revisionId, 'REVISAO', $revisionId, 'REVISAO_ATIVADA', ['revisao_ativa_id' => $oldActive], ['revisao_ativa_id' => $revisionId]);
        if ($oldActive) {
            alma_event($conn, $directionId, $oldActive, 'REVISAO', $oldActive, 'REVISAO_SUBSTITUIDA', ['estado' => 'ATIVA'], ['estado' => 'SUBSTITUIDA', 'substituida_por' => $revisionId]);
        }
        $conn->commit();
        return alma_direction_full($conn, (int) $revision['imagem_id'], $revisionId);
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

function alma_history(mysqli $conn, int $imageId): array
{
    $stmt = $conn->prepare('SELECT id FROM alma_direcao WHERE imagem_id = ? LIMIT 1');
    $stmt->bind_param('i', $imageId);
    $stmt->execute();
    $direction = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$direction) {
        return [];
    }
    $stmt = $conn->prepare(
        'SELECT e.id, e.revisao_id, e.entidade_tipo, e.entidade_id, e.acao,
                e.antes_json, e.depois_json, e.criado_em,
                COALESCE(u.nome_usuario, "Usuário removido") AS ator,
                r.numero AS revisao_numero
           FROM alma_evento e
           LEFT JOIN usuario u ON u.idusuario = e.ator_usuario_id
           LEFT JOIN alma_direcao_revisao r ON r.id = e.revisao_id
          WHERE e.direcao_id = ? ORDER BY e.criado_em DESC, e.id DESC LIMIT 300'
    );
    $stmt->bind_param('i', $direction['id']);
    $stmt->execute();
    $events = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        $row['revisao_id'] = $row['revisao_id'] !== null ? (int) $row['revisao_id'] : null;
        $row['revisao_numero'] = $row['revisao_numero'] !== null ? (int) $row['revisao_numero'] : null;
        $row['antes'] = $row['antes_json'] ? json_decode($row['antes_json'], true) : null;
        $row['depois'] = $row['depois_json'] ? json_decode($row['depois_json'], true) : null;
        unset($row['antes_json'], $row['depois_json']);
        $events[] = $row;
    }
    $stmt->close();
    return $events;
}

function alma_admin_versions(mysqli $conn): array
{
    alma_require_capability($conn, ALMA_CAP_LIBRARY_ADMIN);
    $result = $conn->query(
        'SELECT v.*, COUNT(DISTINCT d.id) AS dimensoes, COUNT(DISTINCT i.id) AS itens
           FROM alma_biblioteca_versao v
           LEFT JOIN alma_biblioteca_dimensao d ON d.versao_id = v.id
           LEFT JOIN alma_biblioteca_item i ON i.dimensao_id = d.id
          GROUP BY v.id ORDER BY v.criada_em DESC, v.id DESC'
    );
    return $result->fetch_all(MYSQLI_ASSOC);
}

function alma_admin_clone_version(mysqli $conn, array $payload): array
{
    alma_require_capability($conn, ALMA_CAP_LIBRARY_ADMIN);
    $sourceId = alma_positive_id($payload['versao_origem_id'] ?? 0, 'versao_origem_id');
    $code = trim((string) ($payload['codigo'] ?? ''));
    $name = trim((string) ($payload['nome'] ?? ''));
    if (!preg_match('/^\d+\.\d+(?:\.\d+)?$/', $code) || $name === '') {
        throw new InvalidArgumentException('Informe código semântico e nome da nova versão.');
    }
    $source = alma_library_version($conn, $sourceId, true);
    if (!$source) {
        throw new RuntimeException('Versão de origem não encontrada.');
    }

    $conn->begin_transaction();
    try {
        $actor = alma_user_id();
        $stmt = $conn->prepare(
            "INSERT INTO alma_biblioteca_versao
                (codigo, nome, estado, origem_documento, checksum_origem, criada_por_usuario_id)
             VALUES (?, ?, 'RASCUNHO', ?, ?, ?)"
        );
        $stmt->bind_param('ssssi', $code, $name, $source['origem_documento'], $source['checksum_origem'], $actor);
        $stmt->execute();
        $newVersionId = (int) $conn->insert_id;
        $stmt->close();

        $dimensionMap = [];
        $stmt = $conn->prepare('SELECT * FROM alma_biblioteca_dimensao WHERE versao_id = ? ORDER BY ordem_jornada, ordem_no_pilar, id');
        $stmt->bind_param('i', $sourceId);
        $stmt->execute();
        $dimensions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($dimensions as $dimension) {
            $parentId = $dimension['dimensao_pai_id'] !== null ? ($dimensionMap[(int) $dimension['dimensao_pai_id']] ?? null) : null;
            $stmt = $conn->prepare(
                'INSERT INTO alma_biblioteca_dimensao
                    (versao_id, dimensao_pai_id, codigo, etapa_codigo, etapa_nome, pilar_codigo, pilar_nome,
                     nome, tipo_conteudo, ordem_jornada, ordem_no_pilar, permite_multiplas, exige_item_biblioteca, ativa)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'iisssssssiiiii',
                $newVersionId,
                $parentId,
                $dimension['codigo'],
                $dimension['etapa_codigo'],
                $dimension['etapa_nome'],
                $dimension['pilar_codigo'],
                $dimension['pilar_nome'],
                $dimension['nome'],
                $dimension['tipo_conteudo'],
                $dimension['ordem_jornada'],
                $dimension['ordem_no_pilar'],
                $dimension['permite_multiplas'],
                $dimension['exige_item_biblioteca'],
                $dimension['ativa']
            );
            $stmt->execute();
            $dimensionMap[(int) $dimension['id']] = (int) $conn->insert_id;
            $stmt->close();
        }

        $itemMap = [];
        $ids = implode(',', array_map('intval', array_keys($dimensionMap)));
        $items = $conn->query("SELECT * FROM alma_biblioteca_item WHERE dimensao_id IN ($ids) ORDER BY id");
        while ($item = $items->fetch_assoc()) {
            $newDimensionId = $dimensionMap[(int) $item['dimensao_id']];
            $stmt = $conn->prepare(
                'INSERT INTO alma_biblioteca_item
                    (dimensao_id, codigo, titulo, resumo, diferenca_principal, descricao,
                     principio_fundamental, diretriz_completa, ordem, ativo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('isssssssii', $newDimensionId, $item['codigo'], $item['titulo'], $item['resumo'], $item['diferenca_principal'], $item['descricao'], $item['principio_fundamental'], $item['diretriz_completa'], $item['ordem'], $item['ativo']);
            $stmt->execute();
            $itemMap[(int) $item['id']] = (int) $conn->insert_id;
            $stmt->close();
        }
        if ($itemMap) {
            $oldItemIds = implode(',', array_map('intval', array_keys($itemMap)));
            $sections = $conn->query("SELECT * FROM alma_biblioteca_item_secao WHERE item_id IN ($oldItemIds) ORDER BY id");
            $sectionMap = [];
            while ($section = $sections->fetch_assoc()) {
                $newItemId = $itemMap[(int) $section['item_id']];
                $stmt = $conn->prepare('INSERT INTO alma_biblioteca_item_secao (item_id, codigo, titulo, conteudo, ordem) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('isssi', $newItemId, $section['codigo'], $section['titulo'], $section['conteudo'], $section['ordem']);
                $stmt->execute();
                $sectionMap[(int) $section['id']] = (int) $conn->insert_id;
                $stmt->close();
            }
            if ($sectionMap) {
                $oldSectionIds = implode(',', array_map('intval', array_keys($sectionMap)));
                $entries = $conn->query("SELECT * FROM alma_biblioteca_secao_entrada WHERE secao_id IN ($oldSectionIds) ORDER BY id");
                while ($entry = $entries->fetch_assoc()) {
                    $newSectionId = $sectionMap[(int) $entry['secao_id']];
                    $stmt = $conn->prepare('INSERT INTO alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('issi', $newSectionId, $entry['tipo'], $entry['texto'], $entry['ordem']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        $conn->commit();
        return alma_library_payload($conn, $newVersionId);
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

function alma_admin_save_item(mysqli $conn, array $payload): array
{
    alma_require_capability($conn, ALMA_CAP_LIBRARY_ADMIN);
    $itemId = alma_positive_id($payload['item_id'] ?? 0, 'item_id');
    $stmt = $conn->prepare(
        'SELECT i.*, d.versao_id, v.estado
           FROM alma_biblioteca_item i
           JOIN alma_biblioteca_dimensao d ON d.id = i.dimensao_id
           JOIN alma_biblioteca_versao v ON v.id = d.versao_id
          WHERE i.id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$item || $item['estado'] !== 'RASCUNHO') {
        throw new RuntimeException('Somente itens de uma versão em rascunho podem ser alterados.');
    }
    $title = trim((string) ($payload['titulo'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Título obrigatório.');
    }
    $fields = ['resumo', 'diferenca_principal', 'descricao', 'principio_fundamental', 'diretriz_completa'];
    $values = [];
    foreach ($fields as $field) {
        $values[$field] = trim((string) ($payload[$field] ?? '')) ?: null;
    }
    $active = !isset($payload['ativo']) || !empty($payload['ativo']) ? 1 : 0;
    $sections = is_array($payload['secoes'] ?? null) ? $payload['secoes'] : [];
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'UPDATE alma_biblioteca_item
                SET titulo=?, resumo=?, diferenca_principal=?, descricao=?, principio_fundamental=?, diretriz_completa=?, ativo=?
              WHERE id=?'
        );
        $stmt->bind_param('ssssssii', $title, $values['resumo'], $values['diferenca_principal'], $values['descricao'], $values['principio_fundamental'], $values['diretriz_completa'], $active, $itemId);
        $stmt->execute();
        $stmt->close();

        if ($sections) {
            $allowed = [];
            $stmt = $conn->prepare('SELECT id, codigo FROM alma_biblioteca_item_secao WHERE item_id = ?');
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($section = $result->fetch_assoc()) {
                $allowed[(int) $section['id']] = $section['codigo'];
            }
            $stmt->close();

            foreach ($sections as $section) {
                $sectionId = (int) ($section['id'] ?? 0);
                if (!$sectionId || !isset($allowed[$sectionId])) {
                    throw new InvalidArgumentException('Seção oficial inválida para este item.');
                }
                if ($allowed[$sectionId] === 'fonte_oficial') {
                    continue;
                }
                $sectionTitle = trim((string) ($section['titulo'] ?? ''));
                if ($sectionTitle === '') {
                    throw new InvalidArgumentException('O título da seção oficial é obrigatório.');
                }
                $sectionContent = trim((string) ($section['conteudo'] ?? '')) ?: null;
                $stmt = $conn->prepare('UPDATE alma_biblioteca_item_secao SET titulo = ?, conteudo = ? WHERE id = ? AND item_id = ?');
                $stmt->bind_param('ssii', $sectionTitle, $sectionContent, $sectionId, $itemId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare('DELETE FROM alma_biblioteca_secao_entrada WHERE secao_id = ?');
                $stmt->bind_param('i', $sectionId);
                $stmt->execute();
                $stmt->close();
                $entries = is_array($section['entradas'] ?? null) ? $section['entradas'] : [];
                foreach ($entries as $order => $entry) {
                    $entryText = trim((string) ($entry['texto'] ?? ''));
                    if ($entryText === '') {
                        continue;
                    }
                    $entryType = trim((string) ($entry['tipo'] ?? 'ITEM')) ?: 'ITEM';
                    $entryOrder = (int) ($entry['ordem'] ?? $order);
                    $stmt = $conn->prepare('INSERT INTO alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('issi', $sectionId, $entryType, $entryText, $entryOrder);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
    return alma_library_payload($conn, (int) $item['versao_id']);
}

function alma_admin_publish_version(mysqli $conn, array $payload): array
{
    alma_require_capability($conn, ALMA_CAP_LIBRARY_ADMIN);
    $versionId = alma_positive_id($payload['versao_id'] ?? 0, 'versao_id');
    $version = alma_library_version($conn, $versionId, true);
    if (!$version || $version['estado'] !== 'RASCUNHO') {
        throw new RuntimeException('Somente uma versão em rascunho pode ser publicada.');
    }
    $required = ['atmosfera', 'arquitetura', 'materialidade', 'luz_momento', 'luz_linguagem', 'lifestyle', 'fotografia', 'composicao'];
    $stmt = $conn->prepare('SELECT codigo FROM alma_biblioteca_dimensao WHERE versao_id = ? AND ativa = 1');
    $stmt->bind_param('i', $versionId);
    $stmt->execute();
    $codes = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'codigo');
    $stmt->close();
    $missing = array_diff($required, $codes);
    if ($missing) {
        throw new RuntimeException('Versão incompleta: ' . implode(', ', $missing));
    }
    $actor = alma_user_id();
    $stmt = $conn->prepare("UPDATE alma_biblioteca_versao SET estado='PUBLICADA', publicada_por_usuario_id=?, publicada_em=NOW() WHERE id=?");
    $stmt->bind_param('ii', $actor, $versionId);
    $stmt->execute();
    $stmt->close();
    return alma_library_payload($conn, $versionId);
}

if ($almaApiLibraryOnly) {
    return;
}

try {
    if ($method === 'GET') {
        switch ($action) {
            case 'permissions':
                alma_json(['success' => true, 'permissions' => alma_permissions($conn)]);
            case 'resumo':
                $imageId = alma_positive_id($_GET['imagem_id'] ?? 0, 'imagem_id');
                alma_json(['success' => true] + alma_summary($conn, $imageId));
            case 'direcao':
                $imageId = alma_positive_id($_GET['imagem_id'] ?? 0, 'imagem_id');
                $revisionId = !empty($_GET['revisao_id']) ? (int) $_GET['revisao_id'] : null;
                alma_json(['success' => true, 'permissions' => alma_permissions($conn)] + alma_direction_full($conn, $imageId, $revisionId));
            case 'biblioteca':
                $version = alma_library_version($conn, !empty($_GET['versao_id']) ? (int) $_GET['versao_id'] : null, alma_can($conn, ALMA_CAP_LIBRARY_ADMIN));
                if (!$version) {
                    throw new RuntimeException('Biblioteca ALMA não encontrada.');
                }
                alma_json(['success' => true, 'biblioteca' => alma_library_payload($conn, (int) $version['id'])]);
            case 'historico':
                $imageId = alma_positive_id($_GET['imagem_id'] ?? 0, 'imagem_id');
                alma_json(['success' => true, 'eventos' => alma_history($conn, $imageId)]);
            case 'sire_busca':
                alma_json(['success' => true, 'referencias' => alma_sire_search($conn, trim((string) ($_GET['q'] ?? '')), (int) ($_GET['page'] ?? 1))]);
            case 'sire_seletor':
                $versionId = alma_positive_id($_GET['biblioteca_versao_id'] ?? 0, 'biblioteca_versao_id');
                $itemId = alma_positive_id($_GET['item_id'] ?? 0, 'item_id');
                $dimensionCode = trim((string) ($_GET['dimensao_codigo'] ?? ''));
                alma_json(['success' => true] + alma_sire_picker(
                    $conn,
                    trim((string) ($_GET['q'] ?? '')),
                    (int) ($_GET['page'] ?? 1),
                    $versionId,
                    $dimensionCode,
                    $itemId,
                    [
                        'golden' => (int) ($_GET['golden'] ?? 0),
                        'selected_ids' => explode(',', (string) ($_GET['selected'] ?? '')),
                    ]
                ));
            case 'obra_contexto':
                $obraId = alma_positive_id($_GET['obra_id'] ?? 0, 'obra_id');
                $stmt = $conn->prepare('SELECT idobra AS obra_id, nomenclatura, nome_obra FROM obra WHERE idobra=? LIMIT 1');
                $stmt->bind_param('i', $obraId);
                $stmt->execute();
                $obra = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$obra) {
                    throw new RuntimeException('Obra não encontrada.');
                }
                alma_json([
                    'success' => true,
                    'obra' => $obra,
                    'projeto' => alma_project_direction($conn, $obraId),
                    'imagens' => alma_project_images($conn, $obraId),
                    'permissions' => alma_permissions($conn),
                ]);
            case 'admin_versoes':
                alma_json(['success' => true, 'versoes' => alma_admin_versions($conn), 'permissions' => alma_permissions($conn)]);
            default:
                alma_json(['success' => false, 'message' => 'Ação GET inválida.'], 400);
        }
    }

    if ($method !== 'POST') {
        alma_json(['success' => false, 'message' => 'Método não permitido.'], 405);
    }
    $payload = alma_input();
    $action = (string) ($payload['action'] ?? $action);
    switch ($action) {
        case 'criar_revisao':
            alma_json(['success' => true] + alma_create_revision($conn, $payload));
        case 'salvar_revisao':
            alma_json(['success' => true] + alma_save_revision($conn, $payload));
        case 'salvar_projeto':
            alma_json(['success' => true, 'projeto' => alma_save_project($conn, $payload)]);
        case 'usar_imagem_base':
            alma_json(['success' => true] + alma_copy_from_image($conn, $payload));
        case 'aplicar_dimensao':
            alma_json(['success' => true] + alma_apply_dimension($conn, $payload));
        case 'ativar_revisao':
            alma_json(['success' => true] + alma_activate_revision($conn, $payload));
        case 'admin_clonar_versao':
            alma_json(['success' => true, 'biblioteca' => alma_admin_clone_version($conn, $payload)]);
        case 'admin_salvar_item':
            alma_json(['success' => true, 'biblioteca' => alma_admin_save_item($conn, $payload)]);
        case 'admin_publicar_versao':
            alma_json(['success' => true, 'biblioteca' => alma_admin_publish_version($conn, $payload)]);
        default:
            alma_json(['success' => false, 'message' => 'Ação POST inválida.'], 400);
    }
} catch (InvalidArgumentException $error) {
    alma_json(['success' => false, 'message' => $error->getMessage()], 422);
} catch (mysqli_sql_exception $error) {
    error_log('[ALMA] DB error: ' . $error->getMessage());
    alma_json(['success' => false, 'message' => 'Não foi possível concluir a operação no ALMA.'], 500);
} catch (Throwable $error) {
    $status = str_contains(mb_strtolower($error->getMessage(), 'UTF-8'), 'não encontr') ? 404 : 422;
    alma_json(['success' => false, 'message' => $error->getMessage()], $status);
}
