<?php
// Fonte compartilhada da timeline e do PDF. Apenas leitura; nenhuma migração.
function historicoColumns(mysqli $conn, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($row = $result->fetch_assoc()) $columns[] = $row['Field'];
    return $cache[$table] = $columns;
}

function historicoCategoria(array $row): string
{
    $tipo = strtolower(trim((string) ($row['tipo'] ?? '')));
    if (in_array($tipo, ['entrega', 'entregas'], true)) return 'entregas';
    if (in_array($tipo, ['arquivo', 'arquivos', 'file', 'upload', 'anexo'], true)) return 'arquivos';
    return 'manuais';
}

function historicoPublicUrl(?string $path): ?string
{
    $path = trim(str_replace('\\', '/', (string) $path));
    if ($path === '' || preg_match('#(?:^|/)\.\.(?:/|$)#', $path)) return null;
    if (preg_match('#^https?://#i', $path)) return $path;
    $base = improov_app_base_path();
    if (($pos = strpos($path, '/public_html/')) !== false) return '/' . substr($path, $pos + 13);
    if (($pos = strpos($path, '/05.Exchange/01.Input/')) !== false) return $base . '/uploads/' . substr($path, $pos + strlen('/05.Exchange/01.Input/'));
    if (preg_match('#/Angulo_definido/(.*)$#i', $path, $angle) && preg_match('#/mnt/clientes/~?\d*/([^/]+)/#i', $path, $project)) {
        return $base . '/uploads/angulo_definido/' . $project[1] . '/Angulo_definido/' . $angle[1];
    }
    if (preg_match('#(?:^|/)(uploads/.*)$#', $path, $match)) return $base . '/' . $match[1];
    // Flow Review e perfis também armazenam caminhos relativos à aplicação.
    if ($path[0] !== '/' && !preg_match('#^[a-z][a-z0-9+.-]*:#i', $path)) return $base . '/' . (strpos($path, '/') === false ? 'uploads/' : '') . $path;
    if (strpos($path, $base . '/') === 0) return $path;
    return null;
}

function historicoPreview(string $name, ?string $path): ?array
{
    $url = historicoPublicUrl($path);
    if (!$url || !preg_match('/\.(jpe?g|png|webp|gif|avif|bmp)$/i', parse_url($url, PHP_URL_PATH) ?? '')) return null;
    return ['nome_original' => $name, 'url' => $url, 'thumbnail_url' => '../thumb.php?w=208&q=75&path=' . rawurlencode($url)];
}

function historicoDeliveryPreviews(mysqli $conn, int $obraId, array $ids, array &$deliveries): void
{
    $idList = implode(',', array_map('intval', $ids));
    if ($idList === '') return;
    // Cada prévia pertence a um item da entrega. A versão é limitada ao envio do item,
    // evitando substituir uma entrega antiga por uma revisão feita posteriormente.
    $stmt = $conn->prepare("SELECT ei.entrega_id, i.imagem_nome, h.imagem
        FROM entregas_itens ei
        JOIN entregas e ON e.id = ei.entrega_id
        JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = ei.imagem_id AND i.obra_id = e.obra_id
        LEFT JOIN historico_aprovacoes_imagens h ON h.id = (
            SELECT hi.id FROM historico_aprovacoes_imagens hi
            JOIN funcao_imagem fi ON fi.idfuncao_imagem = hi.funcao_imagem_id
            WHERE fi.imagem_id = ei.imagem_id AND hi.imagem IS NOT NULL AND hi.imagem <> ''
              AND hi.data_envio < CASE WHEN UNIX_TIMESTAMP(ei.data_entregue) > 0
                  THEN DATE(ei.data_entregue) + INTERVAL 1 DAY ELSE NOW() END
            ORDER BY hi.data_envio DESC, hi.id DESC LIMIT 1
        )
        WHERE e.obra_id = ? AND e.id IN ($idList)
        ORDER BY ei.entrega_id, i.imagem_nome, ei.id");
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $seen = [];
    $append = static function (int $id, ?array $preview) use (&$deliveries, &$seen): void {
        if (!$preview || !isset($deliveries[$id])) return;
        $key = $id . ':' . $preview['url'];
        if (isset($seen[$key])) return;
        $seen[$key] = true;
        $deliveries[$id]['total_previews'] = ($deliveries[$id]['total_previews'] ?? 0) + 1;
        if (count($deliveries[$id]['imagens'] ?? []) < 5) $deliveries[$id]['imagens'][] = $preview;
    };
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $item) {
        $append((int) $item['entrega_id'], historicoPreview($item['imagem_nome'], $item['imagem']));
    }
    $stmt->close();

    $p00Ids = array_keys(array_filter($deliveries, static fn($delivery) => ($delivery['tipo_entrega'] ?? '') === 'P00'));
    if (!$p00Ids) return;
    $p00List = implode(',', array_map('intval', $p00Ids));
    $stmt = $conn->prepare("SELECT v.entrega_id, v.historico_id, v.arquivos_json, v.versao_label, i.imagem_nome
        FROM entregas_p00_versoes v JOIN entregas e ON e.id = v.entrega_id
        LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = v.imagem_id AND i.obra_id = e.obra_id
        WHERE e.obra_id = ? AND e.id IN ($p00List) ORDER BY v.entrega_id, v.versao_num, v.id");
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $references = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $version) {
        $targets = json_decode($version['arquivos_json'] ?? '', true);
        $targets = is_array($targets) ? $targets : [];
        $targets[] = ['historico_id' => $version['historico_id']];
        foreach ($targets as $target) {
            if (!is_array($target) || empty($target['historico_id'])) continue;
            $references[(int) $target['historico_id']][] = [
                'id' => (int) $version['entrega_id'],
                'nome' => $target['file_name'] ?? $version['imagem_nome'] ?? $version['versao_label'],
            ];
        }
    }
    $stmt->close();
    if (!$references) return;
    $historyIds = implode(',', array_keys($references));
    $stmt = $conn->prepare("SELECT h.id, h.imagem FROM historico_aprovacoes_imagens h
        JOIN funcao_imagem fi ON fi.idfuncao_imagem = h.funcao_imagem_id
        JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
        WHERE i.obra_id = ? AND h.id IN ($historyIds)");
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $history) {
        foreach ($references[$history['id']] as $reference) $append($reference['id'], historicoPreview($reference['nome'], $history['imagem']));
    }
    $stmt->close();
}

function historicoRegistros(mysqli $conn, int $obraId): array
{
    $columns = historicoColumns($conn, 'acompanhamento_email');
    $select = ['ae.idacompanhamento_email AS id', 'ae.assunto', 'ae.data', 'ae.ordem', 'ae.tipo'];
    foreach (['status', 'metadata', 'entrega_id', 'arquivo_id', 'colaborador_id'] as $column) {
        if (in_array($column, $columns, true)) $select[] = "ae.`$column`";
    }
    $join = '';
    if (in_array('colaborador_id', $columns, true)) {
        // O formulário manual legado grava colaborador_id=1 fixo. Não atribuir autoria falsa.
        $select[] = "CASE WHEN LOWER(COALESCE(ae.tipo, '')) NOT IN ('', 'manual') THEN c.nome_colaborador ELSE NULL END AS usuario_nome";
        $select[] = "CASE WHEN LOWER(COALESCE(ae.tipo, '')) NOT IN ('', 'manual') THEN
            (SELECT iu.thumb FROM usuario u JOIN informacoes_usuario iu ON iu.usuario_id = u.idusuario
             WHERE u.idcolaborador = ae.colaborador_id AND iu.thumb IS NOT NULL AND iu.thumb <> '' ORDER BY u.idusuario DESC LIMIT 1)
            ELSE NULL END AS usuario_thumb";
        $join = ' LEFT JOIN colaborador c ON c.idcolaborador = ae.colaborador_id';
    }
    $stmt = $conn->prepare('SELECT ' . implode(', ', $select) . ' FROM acompanhamento_email ae' . $join . ' WHERE ae.obra_id = ? ORDER BY ae.data DESC, ae.idacompanhamento_email DESC');
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $deliveryIds = [];
    $fileIds = [];
    foreach ($rows as &$row) {
        $metadata = json_decode($row['metadata'] ?? '', true);
        $row['metadata'] = is_array($metadata) ? $metadata : [];
        $row['categoria'] = historicoCategoria($row);
        $row['usuario_thumb'] = historicoPublicUrl($row['usuario_thumb'] ?? null);
        if (!empty($row['entrega_id'])) $deliveryIds[(int) $row['entrega_id']] = true;
        if (!empty($row['arquivo_id'])) $fileIds[(int) $row['arquivo_id']] = true;
    }
    unset($row);
    if ($fileIds) {
        $ids = implode(',', array_keys($fileIds));
        $stmt = $conn->prepare("SELECT a.idarquivo, a.nome_original, a.nome_interno, a.caminho, a.tipo, ti.nome AS ambiente FROM arquivos a LEFT JOIN tipo_imagem ti ON ti.id_tipo_imagem = a.tipo_imagem_id WHERE a.obra_id = ? AND a.idarquivo IN ($ids)");
        $stmt->bind_param('i', $obraId);
        $stmt->execute();
        $files = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $file) {
            $url = historicoPublicUrl($file['caminho']);
            $file['nome_original'] = $file['nome_original'] ?: $file['nome_interno'];
            $file['url'] = strtoupper($file['tipo']) === 'PDF' ? '../FlowDrive/visualizar_pdf.php?idarquivo=' . (int) $file['idarquivo'] : $url;
            $preview = historicoPreview($file['nome_original'], $file['caminho']);
            if ($preview) $file['thumbnail_url'] = $preview['thumbnail_url'];
            unset($file['caminho']);
            $files[$file['idarquivo']] = $file;
        }
        $stmt->close();
        foreach ($rows as &$row) {
            $file = $files[$row['arquivo_id'] ?? 0] ?? null;
            $row['arquivos'] = $file ? [$file] : [];
            if ($file && !empty($file['ambiente'])) $row['metadata']['ambiente'] = $file['ambiente'];
        }
        unset($row);
    }
    if (!$deliveryIds) return $rows;

    // Uma consulta para todas as entregas vinculadas, limitada à mesma obra.
    $ids = implode(',', array_keys($deliveryIds));
    $deliveryColumns = historicoColumns($conn, 'entregas');
    $fields = ['e.id', 's.nome_status AS etapa'];
    foreach (['status', 'data_recebimento', 'data_conclusao', 'tipo_entrega'] as $column) {
        if (in_array($column, $deliveryColumns, true)) $fields[] = "e.`$column`";
    }
    $fields[] = '(SELECT COUNT(*) FROM entregas_itens ei WHERE ei.entrega_id = e.id) AS quantidade_imagens';
    $stmt = $conn->prepare('SELECT ' . implode(', ', $fields) . " FROM entregas e LEFT JOIN status_imagem s ON s.idstatus = e.status_id WHERE e.obra_id = ? AND e.id IN ($ids)");
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $deliveries = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $delivery) {
        // P00 utiliza versões próprias, não itens: não apresentar zero como contagem real.
        if (($delivery['tipo_entrega'] ?? '') === 'P00') unset($delivery['quantidade_imagens']);
        $deliveries[$delivery['id']] = $delivery;
    }
    $stmt->close();
    try {
        historicoDeliveryPreviews($conn, $obraId, array_keys($deliveryIds), $deliveries);
    } catch (Throwable $e) {
        // A ausência de uma prévia não deve impedir a leitura do histórico.
        error_log('[historico previews] ' . $e->getMessage());
    }
    foreach ($rows as &$row) $row['entrega'] = $deliveries[$row['entrega_id'] ?? 0] ?? null;
    unset($row);
    return $rows;
}
