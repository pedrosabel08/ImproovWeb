<?php

require_once __DIR__ . '/modelo_3d_storage_helper.php';
/** Integração 3D: identifica nós e resolve vínculos. Não renderiza nem duplica o mapa. */
function mp3d_file(string $file): string
{
    if (str_starts_with($file, 'uploads/model-3d/')) {
        if (!preg_match('~^uploads/model-3d/obra-([1-9][0-9]*)/obra-\1_modelo-[1-9][0-9]*_v[0-9]{4,}_[0-9]{8}T[0-9]{6}Z_[a-f0-9]{12}\.glb$~D', $file)) {
            throw new InvalidArgumentException('Caminho do modelo 3D inválido.');
        }
        if (!is_file(__DIR__ . '/../' . $file)) {
            mp3d_cache_from_vps($file);
        }
        $root = realpath(__DIR__ . '/../uploads/model-3d');
        $path = realpath(__DIR__ . '/../' . $file);
        if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new RuntimeException('Arquivo do modelo 3D não encontrado.', 404);
        }
        return $path;
    }
    if ($file !== basename($file) || !preg_match('/^[\p{L}\p{N}_. -]+\.glb$/u', $file)) {
        throw new InvalidArgumentException('Informe um arquivo GLB do diretório predio-3d.');
    }
    $root = realpath(__DIR__ . '/../MapaPavimento/predio-3d');
    $path = realpath($root . DIRECTORY_SEPARATOR . $file);
    if (!$path || dirname($path) !== $root || !is_file($path)) {
        throw new RuntimeException('Arquivo do modelo 3D não encontrado.', 404);
    }
    return $path;
}

function mp3d_floor_name(string $name): ?string
{
    $name = mb_strtoupper(trim($name), 'UTF-8');
    if (preg_match('/^(PAV(?:TO)?_.+_[0-9]+)_([0-9]+)$/D', $name, $match)) {
        return $match[1];
    }
    // Nó único/grupo: o último número identifica o pavimento, não uma peça.
    if (preg_match('/^PAV(?:TO)?_.+_[0-9]{1,3}$/D', $name)) {
        return $name;
    }
    return null;
}

function mp3d_floor_names(string $path): array
{
    $file = fopen($path, 'rb');
    if (!$file) {
        throw new RuntimeException('Não foi possível ler o modelo 3D.');
    }
    try {
        $header = fread($file, 20);
        if (strlen($header) !== 20 || substr($header, 0, 4) !== 'glTF') {
            throw new RuntimeException('GLB inválido.');
        }
        $info = unpack('Vversion/Vlength/VjsonLength/VjsonType', substr($header, 4));
        if ($info['version'] !== 2 || $info['jsonType'] !== 0x4e4f534a || $info['jsonLength'] > 32 * 1024 * 1024) {
            throw new RuntimeException('Estrutura GLB não suportada.');
        }
        $json = stream_get_contents($file, $info['jsonLength']);
        if (strlen($json) !== $info['jsonLength']) {
            throw new RuntimeException('GLB incompleto.');
        }
        $data = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        return mp3d_floor_names_from_data($data);
    } finally {
        fclose($file);
    }
}

function mp3d_floor_names_from_data(array $data): array
{
    $nodes = $data['nodes'] ?? [];
    $meshes = $data['meshes'] ?? [];
    $pending = $data['scenes'][$data['scene'] ?? 0]['nodes'] ?? [];
    if (!is_array($nodes) || !is_array($meshes) || !is_array($pending)) {
        throw new InvalidArgumentException('Cena GLB inválida.');
    }
    $seen = [];
    $floors = [];
    while ($pending) {
        $id = (int)array_pop($pending);
        if (isset($seen[$id]) || !isset($nodes[$id])) {
            continue;
        }
        $seen[$id] = true;
        $node = $nodes[$id];
        if (!is_array($node) || (isset($node['name']) && !is_string($node['name'])) || (isset($node['children']) && !is_array($node['children']))) {
            throw new InvalidArgumentException('Nó GLB inválido.');
        }
        $names = [(string)($node['name'] ?? '')];
        if (isset($node['mesh']) && is_int($node['mesh']) && isset($meshes[$node['mesh']]['name']) && is_string($meshes[$node['mesh']]['name'])) {
            $names[] = $meshes[$node['mesh']]['name'];
        }
        if (isset($node['mesh']) || !empty($node['children'])) {
            foreach (array_unique($names) as $name) {
                $floor = mp3d_floor_name($name);
                if ($floor) {
                    $floors[$floor] = true;
                }
            }
        }
        foreach ($node['children'] ?? [] as $child) {
            $pending[] = (int)$child;
        }
    }
    $names = array_keys($floors);
    sort($names, SORT_NATURAL);
    return $names;
}

function mp3d_model(mysqli $conn, int $obra, int $model, bool $lock = false): array
{
    $row = mp_row($conn, 'SELECT id,obra_id,nome,arquivo FROM mapa_modelos_3d WHERE id=? AND obra_id=?' . ($lock ? ' FOR UPDATE' : ''), 'ii', [$model, $obra]);
    if (!$row) {
        throw new RuntimeException('Modelo 3D indisponível nesta obra.', 404);
    }
    mp3d_file($row['arquivo']);
    $row['id'] = (int) $row['id'];
    $row['obra_id'] = (int) $row['obra_id'];
    return $row;
}

function mp3d_subtypes(mysqli $conn, int $obra): array
{
    // O catálogo é global; pertencimento à obra vem das imagens, mapas ou vínculos existentes.
    $rows = mp_rows($conn, 'SELECT s.id,s.nome FROM subtipo_imagem s WHERE
        EXISTS(SELECT 1 FROM imagens_cliente_obra i WHERE i.obra_id=? AND i.subtipo_id=s.id)
        OR EXISTS(SELECT 1 FROM referencias_arquitetonicas r WHERE r.obra_id=? AND r.subtipo_id=s.id)
        OR EXISTS(SELECT 1 FROM mapa_modelo_pavimentos v JOIN mapa_modelos_3d m ON m.id=v.modelo_id WHERE m.obra_id=? AND v.subtipo_id=s.id)
        ORDER BY s.nome', 'iii', [$obra, $obra, $obra]);
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
    }
    return $rows;
}

function mp3d_links(mysqli $conn, int $model): array
{
    $rows = mp_rows($conn, 'SELECT v.object_name,v.subtipo_id,v.versao,s.nome AS subtipo_nome,r.id AS referencia_id,
        (r.documento_id IS NOT NULL) AS mapa_configurado,
        (SELECT COUNT(*) FROM ambientes a WHERE a.referencia_id=r.id) AS total_ambientes,
        (SELECT COUNT(*) FROM imagens_cliente_obra i WHERE i.obra_id=m.obra_id AND i.subtipo_id=v.subtipo_id) AS total_imagens
        FROM mapa_modelo_pavimentos v JOIN mapa_modelos_3d m ON m.id=v.modelo_id
        JOIN subtipo_imagem s ON s.id=v.subtipo_id
        LEFT JOIN referencias_arquitetonicas r ON r.obra_id=m.obra_id AND r.subtipo_id=v.subtipo_id
        WHERE v.modelo_id=?', 'i', [$model]);
    foreach ($rows as &$row) {
        $row['subtipo_id'] = (int) $row['subtipo_id'];
        $row['versao'] = (int) $row['versao'];
        $row['referencia_id'] = $row['referencia_id'] ? (int) $row['referencia_id'] : null;
        $row['mapa_configurado'] = (bool) $row['mapa_configurado'];
        $row['total_ambientes'] = (int) $row['total_ambientes'];
        $row['total_imagens'] = (int) $row['total_imagens'];
    }
    return $rows;
}

function mp3d_save_link(mysqli $conn, int $obra, int $modelId, string $object, int $subtype, int $version, int $author): void
{
    $conn->begin_transaction();
    try {
        // Trava o modelo inclusive para serializar duas criações do mesmo vínculo.
        $model = mp3d_model($conn, $obra, $modelId, true);
        if (!in_array($object, mp3d_floor_names(mp3d_file($model['arquivo'])), true)) {
            throw new InvalidArgumentException('Pavimento 3D não encontrado neste modelo.');
        }
        if (!in_array($subtype, array_column(mp3d_subtypes($conn, $obra), 'id'), true)) {
            throw new InvalidArgumentException('Selecione um pavimento pertencente a esta obra.');
        }
        $current = mp_row($conn, 'SELECT versao FROM mapa_modelo_pavimentos WHERE modelo_id=? AND object_name=? FOR UPDATE', 'is', [$modelId, $object]);
        if ((int) ($current['versao'] ?? 0) !== $version) {
            throw new RuntimeException('Este vínculo foi alterado. Recarregue a página antes de tentar novamente.', 409);
        }
        if ($current) {
            mp_query($conn, 'UPDATE mapa_modelo_pavimentos SET subtipo_id=?,versao=versao+1,atualizado_por=? WHERE modelo_id=? AND object_name=?', 'iiis', [$subtype, $author, $modelId, $object])->close();
        } else {
            mp_query($conn, 'INSERT INTO mapa_modelo_pavimentos(modelo_id,object_name,subtipo_id,atualizado_por) VALUES(?,?,?,?)', 'isii', [$modelId, $object, $subtype, $author])->close();
        }
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}
