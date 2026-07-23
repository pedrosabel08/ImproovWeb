<?php

declare(strict_types=1);

require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../Fotografico/fotografico_service.php';

$options = getopt('', ['plan:', 'help']);
if (isset($options['help'])) {
    echo "Uso: php scripts/fotografico_pendencias_sync.php [--plan=<id>]\n";
    exit(0);
}
$planId = isset($options['plan'])
    ? filter_var($options['plan'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : null;
if (isset($options['plan']) && $planId === false) {
    fwrite(STDERR, "O parâmetro --plan precisa ser um inteiro positivo.\n");
    exit(1);
}

$conn = conectarBanco();
$result = null;
$stmt = null;
if ($planId !== null) {
    $stmt = $conn->prepare("SELECT id FROM fotografico_plano WHERE id = ? AND status NOT IN ('CONCLUIDO', 'CANCELADO')");
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    $stmt->bind_param('i', $planId);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT id FROM fotografico_plano WHERE status NOT IN ('CONCLUIDO', 'CANCELADO')");
    if (!$result) {
        throw new RuntimeException($conn->error);
    }
}
$total = 0;
while ($row = $result->fetch_assoc()) {
    fotografico_sync_stage_pending($conn, (int) $row['id'], null);
    $total++;
}
$result->free();
$stmt?->close();
$conn->close();
echo "Pendências fotográficas sincronizadas: {$total}\n";
