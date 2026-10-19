<?php

/** Uploads GLB autocontidos, com histórico e acesso pelo contexto da obra. */
function mp3d_ini_bytes(string $value): int
{
    $value = trim($value);
    $bytes = (float)$value;
    return (int)($bytes * match (strtolower(substr($value, -1))) {
        'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1
    });
}

function mp3d_upload_limit(): int
{
    $limits = [500 * 1024 * 1024];
    foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
        $bytes = mp3d_ini_bytes((string)ini_get($setting));
        if ($bytes > 0) {
            $limits[] = max(0, $bytes - ($setting === 'post_max_size' ? 65536 : 0));
        }
    }
    return min($limits);
}

function mp3d_validate_upload(string $path, string $original): array
{
    if (strtolower(pathinfo($original, PATHINFO_EXTENSION)) !== 'glb') {
        throw new InvalidArgumentException('Selecione um arquivo .glb.');
    }
    $size = filesize($path);
    if (!$size || $size > mp3d_upload_limit()) {
        throw new InvalidArgumentException('O GLB excede o limite de envio deste servidor.');
    }
    if ($size < 20) {
        throw new InvalidArgumentException('GLB incompleto.');
    }
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new RuntimeException('Não foi possível ler o GLB recebido.');
    }
    try {
        $header = fread($handle, 12);
        if (strlen($header) !== 12 || substr($header, 0, 4) !== 'glTF') {
            throw new InvalidArgumentException('O arquivo não é um GLB válido.');
        }
        $info = unpack('Vversion/Vlength', substr($header, 4));
        if ($info['version'] !== 2 || $info['length'] !== $size) {
            throw new InvalidArgumentException('GLB incompleto ou versão não suportada. Exporte em glTF 2.0.');
        }
        $data = null;
        $binLength = 0;
        $hasBin = false;
        $offset = 12;
        while ($offset < $size) {
            $chunk = fread($handle, 8);
            if (strlen($chunk) !== 8) {
                throw new InvalidArgumentException('GLB incompleto.');
            }
            $part = unpack('Vlength/Vtype', $chunk);
            if ($part['length'] % 4 !== 0 || $offset + 8 + $part['length'] > $size) {
                throw new InvalidArgumentException('Estrutura GLB inválida.');
            }
            if ($offset === 12 && $part['type'] !== 0x4e4f534a) {
                throw new InvalidArgumentException('Estrutura GLB inválida.');
            }
            if ($part['type'] === 0x4e4f534a) {
                if ($data !== null || $part['length'] > 32 * 1024 * 1024) {
                    throw new InvalidArgumentException('Estrutura GLB não suportada.');
                }
                try {
                    $data = json_decode(stream_get_contents($handle, $part['length']), true, 128, JSON_THROW_ON_ERROR);
                } catch (JsonException $error) {
                    throw new InvalidArgumentException('Os dados internos do GLB são inválidos.');
                }
                if (!is_array($data) || ($data['asset']['version'] ?? '') !== '2.0') {
                    throw new InvalidArgumentException('Exporte o GLB em glTF 2.0.');
                }
            } else {
                if ($part['type'] === 0x004e4942) {
                    if ($hasBin) {
                        throw new InvalidArgumentException('Estrutura GLB inválida.');
                    }
                    $hasBin = true;
                    $binLength = $part['length'];
                }
                fseek($handle, $part['length'], SEEK_CUR);
            }
            $offset += 8 + $part['length'];
        }
        foreach (array_merge($data['buffers'] ?? [], $data['images'] ?? []) as $resource) {
            if (isset($resource['uri']) && !str_starts_with($resource['uri'], 'data:')) {
                throw new InvalidArgumentException('Exporte um GLB autocontido, com texturas e materiais incorporados ao arquivo.');
            }
        }
        foreach ($data['buffers'] ?? [] as $index => $buffer) {
            if (!isset($buffer['uri']) && ($index !== 0 || !$hasBin || ($buffer['byteLength'] ?? PHP_INT_MAX) > $binLength)) {
                throw new InvalidArgumentException('Os dados binários do GLB estão incompletos.');
            }
        }
        $floors = mp3d_floor_names_from_data($data);
        if (!$floors) {
            throw new InvalidArgumentException('Nenhum pavimento identificado. Nomeie os grupos como PAVTO_LAZER_01, PAVTO_TIPO_01 etc.');
        }
        foreach ($floors as $floor) {
            if (mb_strlen($floor) > 160) {
                throw new InvalidArgumentException('Use nomes de pavimentos com até 160 caracteres.');
            }
        }
        return ['pavimentos' => $floors, 'tamanho_bytes' => $size, 'sha256' => hash_file('sha256', $path)];
    } finally {
        fclose($handle);
    }
}

function mp3d_upload_filename(int $obra, int $model, int $version): string
{
    return sprintf('uploads/model-3d/obra-%d/obra-%d_modelo-%d_v%04d_%s_%s.glb', $obra, $obra, $model, $version, gmdate('Ymd\THis\Z'), bin2hex(random_bytes(6)));
}

function mp3d_check_linked_floors(mysqli $conn, int $model, array $floors): void
{
    $linked = array_column(mp_rows($conn, 'SELECT object_name FROM mapa_modelo_pavimentos WHERE modelo_id=?', 'i', [$model]), 'object_name');
    $missing = array_values(array_diff($linked, $floors));
    if ($missing) {
        throw new InvalidArgumentException('Esta versão não contém os pavimentos vinculados: ' . implode(', ', $missing) . '. Preserve esses nomes no GLB ou envie como um novo modelo.');
    }
}

function mp3d_upload(mysqli $conn, int $obra, int $author, array $input, ?array $file): array
{
    if (!$file || !isset($file['error']) || is_array($file['error'])) {
        throw new InvalidArgumentException('Selecione o GLB que deseja enviar.');
    }
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new InvalidArgumentException('O GLB excede o limite de envio deste servidor.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('O envio do GLB não foi concluído. Tente novamente.');
    }
    $original = mb_substr(basename(str_replace('\\', '/', (string)$file['name'])), 0, 255);
    $meta = mp3d_validate_upload($file['tmp_name'], $original);
    $name = trim((string)($input['nome'] ?? ''));
    $modelId = (int)($input['modelo_id'] ?? 0);
    if (!$modelId && ($name === '' || mb_strlen($name) > 150)) {
        throw new InvalidArgumentException('Informe um nome para o modelo, com até 150 caracteres.');
    }
    $destination = null;
    $saved = false;
    $published = false;
    $conn->begin_transaction();
    try {
        // Serializa uploads da obra e garante nome/versionamento sem colisões.
        mp_row($conn, 'SELECT idobra FROM obra WHERE idobra=? FOR UPDATE', 'i', [$obra]);
        if ($modelId) {
            $model = mp3d_model($conn, $obra, $modelId, true);
            if (($input['arquivo_atual'] ?? '') !== $model['arquivo']) {
                throw new RuntimeException('O modelo foi atualizado por outra pessoa. Reabra o envio antes de tentar novamente.', 409);
            }
            mp3d_check_linked_floors($conn, $modelId, $meta['pavimentos']);
            // O modelo legado entra no histórico na primeira atualização.
            if (!mp_row($conn, 'SELECT id FROM mapa_modelo_3d_versoes WHERE modelo_id=? LIMIT 1', 'i', [$modelId])) {
                $path = mp3d_file($model['arquivo']);
                mp_query($conn, 'INSERT INTO mapa_modelo_3d_versoes(modelo_id,numero,arquivo,nome_original,tamanho_bytes,sha256,criado_em) SELECT id,1,arquivo,?, ?, ?,criado_em FROM mapa_modelos_3d WHERE id=?', 'sisi', [basename($model['arquivo']), filesize($path), hash_file('sha256', $path), $modelId])->close();
            }
        } else {
            mp_query($conn, 'INSERT INTO mapa_modelos_3d(obra_id,nome,arquivo) VALUES(?,?,?)', 'iss', [$obra, $name, 'pending-' . bin2hex(random_bytes(12))])->close();
            $modelId = (int)$conn->insert_id;
        }
        $existing = mp_row($conn, 'SELECT id,numero FROM mapa_modelo_3d_versoes WHERE modelo_id=? AND sha256=?', 'is', [$modelId, $meta['sha256']]);
        if ($existing) {
            $conn->commit();
            return ['id' => $modelId, 'duplicado' => true, 'numero' => (int)$existing['numero'], 'pavimentos' => $meta['pavimentos']];
        }
        $version = 1 + (int)(mp_row($conn, 'SELECT MAX(numero) AS n FROM mapa_modelo_3d_versoes WHERE modelo_id=?', 'i', [$modelId])['n'] ?? 0);
        $relative = mp3d_upload_filename($obra, $modelId, $version);
        $destination = __DIR__ . '/../' . $relative;
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new RuntimeException('Não foi possível preparar a pasta de modelos 3D. Verifique as permissões de uploads/model-3d.');
        }
        $root = realpath(__DIR__ . '/../uploads/model-3d');
        if (!$root || !str_starts_with(realpath($directory) . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Pasta de armazenamento inválida.');
        }
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('Não foi possível salvar o GLB no servidor.');
        }
        $saved = true;
        $published = mp3d_publish($relative, $destination);
        mp_query($conn, 'INSERT INTO mapa_modelo_3d_versoes(modelo_id,numero,arquivo,nome_original,tamanho_bytes,sha256,criado_por) VALUES(?,?,?,?,?,?,?)', 'iissisi', [$modelId, $version, $relative, $original, $meta['tamanho_bytes'], $meta['sha256'], $author])->close();
        mp_query($conn, 'UPDATE mapa_modelos_3d SET arquivo=? WHERE id=? AND obra_id=?', 'sii', [$relative, $modelId, $obra])->close();
        $conn->commit();
        return ['id' => $modelId, 'duplicado' => false, 'numero' => $version, 'pavimentos' => $meta['pavimentos']];
    } catch (Throwable $error) {
        $conn->rollback();
        if ($saved && $destination && is_file($destination)) {
            unlink($destination);
        }
        if ($published) {
            try {
                mp3d_remove_published($relative);
            } catch (Throwable $cleanupError) {
                error_log('Modelo3D: falha ao limpar upload sem cadastro: ' . $relative);
            }
        }
        throw $error;
    }
}

function mp3d_versions(mysqli $conn, array $model): array
{
    $rows = mp_rows($conn, 'SELECT id,numero,arquivo,nome_original,tamanho_bytes,criado_em FROM mapa_modelo_3d_versoes WHERE modelo_id=? ORDER BY numero DESC', 'i', [$model['id']]);
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['numero'] = (int)$row['numero'];
        $row['tamanho_bytes'] = (int)$row['tamanho_bytes'];
        $row['ativa'] = $row['arquivo'] === $model['arquivo'];
        unset($row['arquivo']);
    }
    return $rows;
}

function mp3d_activate_version(mysqli $conn, int $obra, array $input): void
{
    $conn->begin_transaction();
    try {
        $model = mp3d_model($conn, $obra, (int)($input['modelo_id'] ?? 0), true);
        if (($input['arquivo_atual'] ?? '') !== $model['arquivo']) {
            throw new RuntimeException('O modelo foi atualizado. Reabra o histórico antes de tentar novamente.', 409);
        }
        $version = mp_row($conn, 'SELECT arquivo FROM mapa_modelo_3d_versoes WHERE id=? AND modelo_id=?', 'ii', [(int)($input['versao_id'] ?? 0), $model['id']]);
        if (!$version) {
            throw new RuntimeException('Versão não encontrada neste modelo.', 404);
        }
        mp3d_check_linked_floors($conn, $model['id'], mp3d_floor_names(mp3d_file($version['arquivo'])));
        mp_query($conn, 'UPDATE mapa_modelos_3d SET arquivo=? WHERE id=?', 'si', [$version['arquivo'], $model['id']])->close();
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

function mp3d_stream(array $model): never
{
    $path = mp3d_file($model['arquivo']);
    header('Content-Type: model/gltf-binary');
    header('Content-Disposition: inline; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $handle = fopen($path, 'rb');
    if ($handle) {
        fpassthru($handle);
        fclose($handle);
    }
    exit;
}
