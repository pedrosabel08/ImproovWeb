<?php
require_once __DIR__ . '/custos_auth.php';
custos_auth();
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/custos_helper.php';
try {
    $id = (int)($_GET['obra_id'] ?? 0);
    $obra = custos_obra($conn, $id);
    $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
    $config = require __DIR__ . '/../config/custos.php';
    $data = custos_calcular(custos_carregar($conn, $id), $config['meta_margem_percentual']);
    $conn->commit();
    $data['obra'] = $obra;
    // Detail is lazy; keep the main response compact.
    foreach ($data['imagens'] as &$i) unset($i['producao']);
    unset($i);
    custos_json($data);
} catch (Throwable $e) {
    if (isset($conn)) $conn->rollback();
    error_log('Custos obra: ' . $e->getMessage());
    custos_json(['error' => 'Não foi possível calcular os custos. Confira os vínculos financeiros da obra.'], 500);
}
