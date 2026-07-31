<?php

function sire_require_auth(): void
{
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Não autorizado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function sire_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function sire_session_user_id(): ?int
{
    return isset($_SESSION['idusuario']) && (int) $_SESSION['idusuario'] > 0
        ? (int) $_SESSION['idusuario']
        : null;
}

/** A administração do vocabulário é sempre validada no servidor. */
function sire_is_admin(mysqli $conn, ?int $userId = null): bool
{
    $userId = $userId ?? sire_session_user_id();
    if (!$userId) {
        return false;
    }

    $stmt = $conn->prepare('SELECT LOWER(login) AS login FROM usuario WHERE idusuario = ? AND ativo = 1 LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row && in_array($row['login'], [
        'nicolle_imp',
        'andre_imp',
        'diogo_imp',
        'pedro_imp',
    ], true);
}

function sire_require_admin(mysqli $conn): void
{
    if (!sire_is_admin($conn)) {
        sire_json(['success' => false, 'message' => 'Você não tem permissão para administrar os valores do SIRE.'], 403);
    }
}

function sire_normalize_value_name(string $name): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $name));
}

function sire_public_file_url(string $path): string
{
    return '../' . ltrim(str_replace('\\', '/', $path), '/');
}

function sire_reference_image_url(array $row): string
{
    $origem = $row['origem'] ?? 'Flow';
    if ($origem === 'URL') {
        return (string) ($row['url_externa'] ?? '');
    }
    if ($origem === 'Upload') {
        return !empty($row['caminho_arquivo']) ? sire_public_file_url((string) $row['caminho_arquivo']) : '../assets/logo.jpg';
    }

    $nome = (string) ($row['flow_nome_arquivo'] ?? $row['nome_arquivo'] ?? '');
    return $nome !== ''
        ? 'https://improov.com.br/flow/ImproovWeb/uploads/' . rawurlencode($nome) . '.jpg'
        : '../assets/logo.jpg';
}

function sire_reference_thumbnail_url(array $row, int $width = 360, int $quality = 75): string
{
    $width = max(80, min(1200, $width));
    $quality = max(40, min(95, $quality));
    $origin = $row['origem'] ?? 'Flow';

    if ($origin === 'Flow') {
        $name = (string) ($row['flow_nome_arquivo'] ?? $row['nome_arquivo'] ?? '');
        if ($name === '') {
            return '../assets/logo.jpg';
        }

        return 'https://improov.com.br/flow/ImproovWeb/thumb.php?'
            . http_build_query([
                'path' => 'uploads/' . $name . '.jpg',
                'w' => $width,
                'q' => $quality,
            ], '', '&', PHP_QUERY_RFC3986);
    }

    $source = $origin === 'URL'
        ? (string) ($row['url_externa'] ?? '')
        : (string) ($row['caminho_arquivo'] ?? '');

    if ($source === '') {
        return '../assets/logo.jpg';
    }

    return '../thumb.php?' . http_build_query([
        'path' => $source,
        'w' => $width,
        'q' => $quality,
    ], '', '&', PHP_QUERY_RFC3986);
}

function sire_bind_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '') {
        return;
    }
    $bind = [$types];
    foreach ($params as $key => &$value) {
        $bind[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}
