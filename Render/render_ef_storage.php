<?php

// Bridge interno: credenciais e mapeamento NAS são resolvidos por secure_env.php.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/secure_env.php';
try {
    $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $cfg = improov_sftp_config();
    $source = str_replace('\\', '/', trim((string) ($input['source'] ?? '')));
    if (!preg_match('#^//([^/]+)/(renders2|renders)/(.+)$#i', $source, $parts) || strcasecmp($parts[1], $cfg['host']) !== 0) {
        throw new RuntimeException('A pasta de origem deve pertencer ao NAS configurado em IMPROOV_SFTP_HOST e ao compartilhamento renders ou renders2.');
    }
    $segments = explode('/', trim($parts[3], '/'));
    if (count($segments) < 3 || array_intersect($segments, ['.', '..', '']) || preg_match('/[\x00-\x1f]/', $source)) {
        throw new RuntimeException('Caminho de render inválido.');
    }
    $oldName = array_pop($segments);
    $stage = array_pop($segments);
    if (!preg_match('/^(?:[PR]\d{2}|EF)$/i', $stage)) {
        throw new RuntimeException('Não foi possível identificar a pasta da etapa anterior.');
    }
    if (strtoupper($stage) === 'EF') {
        throw new RuntimeException('Selecione o render anterior à etapa EF.');
    }
    $imageName = trim((string) ($input['image_name'] ?? ''));
    if ($imageName === '' || preg_match('/[<>:"\/\\\\|?*\x00-\x1f]/u', $imageName) || preg_match('/[. ]$/', $imageName)) {
        throw new RuntimeException('Nome de imagem inválido para criar a pasta EF.');
    }
    $leaf = preg_replace('/_EF$/i', '', $imageName) . '_EF';
    $share = strtolower($parts[2]);
    $root = rtrim(improov_env('IMPROOV_SFTP_' . strtoupper($share) . '_ROOT', '/mnt/' . $share), '/');
    $remoteSource = $root . '/' . $parts[3];
    $parent = $root . '/' . implode('/', $segments);
    $remote = $parent . '/EF/' . $leaf;
    $sftp = new \phpseclib3\Net\SFTP($cfg['host'], (int) $cfg['port'], 20);
    if (!$sftp->login($cfg['user'], $cfg['pass'])) {
        throw new RuntimeException('Não foi possível autenticar no armazenamento dos renders.');
    }
    if (!$sftp->is_dir($remoteSource) || !$sftp->is_dir($parent)) {
        throw new RuntimeException('A pasta do render anterior não foi localizada no NAS. Confira o caminho e o mapeamento IMPROOV_SFTP_' . strtoupper($share) . '_ROOT.');
    }
    $exists = $sftp->is_dir($remote);
    // Uma pasta preenchida pode conter um EF anterior. Nunca sobrescrever por acidente.
    if ($exists) {
        $items = $sftp->nlist($remote);
        if ($items === false || array_diff($items, ['.', '..'])) {
            throw new RuntimeException('A pasta EF já contém arquivos. Verifique o render existente antes de enviar novamente.');
        }
    }
    $action = (string) ($input['action'] ?? 'inspect');
    if (!in_array($action, ['inspect', 'create'], true)) {
        throw new RuntimeException('Operação de armazenamento inválida.');
    }
    if ($action === 'create' && !$exists && !$sftp->mkdir($remote, -1, true)) {
        throw new RuntimeException('Não foi possível criar a pasta EF no NAS.');
    }
    $destination = '\\\\' . $cfg['host'] . '\\' . $share . '\\' . implode('\\', $segments) . '\\EF\\' . $leaf;
    echo json_encode(['ok' => true, 'destination' => $destination, 'exists' => $exists], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
    exit(1);
}
