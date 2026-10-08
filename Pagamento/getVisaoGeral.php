<?php

require_once __DIR__ . '/pagamento_auth.php';
pagamento_require_gestor(false);
require_once __DIR__ . '/resumo_geral.php';
$prev = new DateTimeImmutable('first day of last month');
$mes = filter_var($_GET['mes'] ?? $prev->format('n'), FILTER_VALIDATE_INT);
$ano = filter_var($_GET['ano'] ?? $prev->format('Y'), FILTER_VALIDATE_INT);
if ($mes === false || $mes < 1 || $mes > 12 || $ano === false || $ano < 2000 || $ano > 2100) {
    pagamento_json(['success' => false, 'error' => 'Competência inválida.'], 422);
}
// Release the session lock while the read-only dashboard is calculated.
session_write_close();
try {
    require __DIR__ . '/../conexao.php';
    $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
    $payload = pagamento_resumo_geral($conn, $mes, $ano);
    $conn->commit();
    $conn->close();
    pagamento_json(['success' => true] + $payload);
} catch (Throwable $e) {
    error_log('Pagamento overview: ' . $e->getMessage());
    pagamento_json(['success' => false, 'error' => 'Não foi possível carregar a visão geral. Tente novamente.'], 500);
}
