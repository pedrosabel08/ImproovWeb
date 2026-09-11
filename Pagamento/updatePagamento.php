<?php
require_once __DIR__ . '/pagamento_auth.php';
pagamento_require_gestor(true);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') pagamento_json(['success' => false, 'error' => 'Use POST.'], 405);
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/financeiro_v2.php';
try {
    $items = financeiro_pagar($conn, pagamento_request_json(), pagamento_current_user_id());
    $pid = null;
    foreach ($items as $i) if (isset($i['pagamento_id'])) $pid = $i['pagamento_id'];
    pagamento_json(['success' => true, 'pagamento_id' => $pid, 'itens' => $items]);
} catch (InvalidArgumentException | DomainException $e) {
    pagamento_json(['success' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('Pagamento V2: ' . $e->getMessage());
    pagamento_json(['success' => false, 'error' => 'Não foi possível registrar o pagamento. Nenhum item foi alterado.'], 500);
}
