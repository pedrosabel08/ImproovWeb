<?php

/** Rollout técnico server-side. Ausência de configuração mantém o legado. */
function pagamento_fechamento_enabled(): bool
{
    return getenv('PAGAMENTO_FECHAMENTO_V2_ENABLED') === '1';
}

function pagamento_fechamento_connection(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    if (getenv('PAGAMENTO_FECHAMENTO_DB_HOST') !== false) {
        foreach (['HOST', 'NAME', 'USER'] as $field) {
            if (!getenv('PAGAMENTO_FECHAMENTO_DB_' . $field)) throw new RuntimeException('Configuração de fechamento incompleta.');
        }
        $conn = new mysqli(getenv('PAGAMENTO_FECHAMENTO_DB_HOST'), getenv('PAGAMENTO_FECHAMENTO_DB_USER'),
            getenv('PAGAMENTO_FECHAMENTO_DB_PASSWORD') ?: '', getenv('PAGAMENTO_FECHAMENTO_DB_NAME'),
            (int)(getenv('PAGAMENTO_FECHAMENTO_DB_PORT') ?: 3306));
    } else {
        // Conexão própria deste request; não reutilizar uma transação do legado.
        require __DIR__ . '/../conexao.php';
    }
    $conn->set_charset('utf8mb4');
    if (!preg_match('/^8\./', $conn->query('SELECT VERSION() v')->fetch_assoc()['v'])) {
        $conn->close();
        throw new RuntimeException('Implantação do fechamento exige MySQL 8 homologado.');
    }
    return $conn;
}

function pagamento_fechamento_storage(): string
{
    $configured = getenv('PAGAMENTO_FECHAMENTO_STORAGE_ROOT') ?: '';
    $real = $configured === '' ? false : realpath($configured);
    if (!$real || !is_dir($real) || is_link($configured) || !is_writable($real)) {
        throw new RuntimeException('Storage privado não provisionado.');
    }
    $root = strtolower(str_replace('\\', '/', $real));
    $public = [realpath(__DIR__ . '/..'), realpath(__DIR__ . '/../..'), realpath($_SERVER['DOCUMENT_ROOT'] ?? '')];
    foreach ($public as $path) {
        if (!$path) continue;
        $path = strtolower(rtrim(str_replace('\\', '/', $path), '/'));
        if ($root === $path || str_starts_with($root, $path . '/')) throw new RuntimeException('Storage deve ficar fora da raiz pública.');
    }
    return $real;
}
