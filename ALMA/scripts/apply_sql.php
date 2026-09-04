<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../conexaoMain.php';

$allowed = [
    realpath(__DIR__ . '/../../sql/2026-09-03_alma_v1.sql'),
    realpath(__DIR__ . '/../../sql/2026-09-03_alma_biblioteca_v1_seed.sql'),
    realpath(__DIR__ . '/../../sql/2026-09-03_alma_biblioteca_v1_import_correction.sql'),
    realpath(__DIR__ . '/../../sql/2026-09-04_alma_operacional_v1.sql'),
    realpath(__DIR__ . '/../../sql/2026-09-04_alma_operacional_fotografia.sql'),
];
$requested = array_slice($argv, 1);
if (!$requested) {
    fwrite(STDERR, "Informe os scripts SQL ALMA a aplicar.\n");
    exit(2);
}

$conn = conectarBanco();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
foreach ($requested as $input) {
    $path = realpath($input);
    if ($path === false || !in_array($path, $allowed, true)) {
        throw new RuntimeException('Script não permitido: ' . $input);
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Não foi possível ler: ' . $path);
    }
    $conn->multi_query($sql);
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo basename($path) . ": aplicado\n";
}
$conn->close();
