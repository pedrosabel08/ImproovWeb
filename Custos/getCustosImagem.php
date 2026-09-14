<?php
require_once __DIR__ . '/custos_auth.php';
custos_auth();
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/custos_helper.php';
try {
    $obra = (int)($_GET['obra_id'] ?? 0);
    custos_obra($conn, $obra);
    $id = (int)($_GET['imagem_id'] ?? 0);
    $valid = custos_query($conn, 'SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE obra_id=? AND idimagens_cliente_obra=?', 'ii', [$obra, $id]);
    if (!$valid) custos_json(['error' => 'Imagem não pertence à obra.'], 404);
    $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
    $config = require __DIR__ . '/../config/custos.php';
    $data = custos_calcular(custos_carregar($conn, $obra), $config['meta_margem_percentual']);
    $conn->commit();
    foreach ($data['imagens'] as $i) if ((int)$i['id'] === $id) {
        $pids = [];
        foreach ($i['producao'] as $t) foreach ($t['lancamentos'] as $l) $pids[(int)$l['pagamento_id']] = true;
        $i['eventos'] = array_values(array_filter($data['eventos'], fn($e) => isset($pids[(int)$e['pagamento_id']])));
        custos_json($i);
    }
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Custos imagem: ' . $e->getMessage());
    custos_json(['error' => 'Não foi possível carregar o detalhe.'], 500);
}
