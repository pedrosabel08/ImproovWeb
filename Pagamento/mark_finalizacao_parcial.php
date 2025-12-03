<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? json_decode(file_get_contents('php://input'), true) : $_GET;
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros ausentes']);
    exit;
}

$colaborador_id = isset($input['colaborador_id']) ? intval($input['colaborador_id']) : 0;
$data_pagamento = isset($input['data_pagamento']) ? trim($input['data_pagamento']) : '';

if (!$colaborador_id || !$data_pagamento) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos: colaborador_id e data_pagamento são obrigatórios (YYYY-MM-DD)']);
    exit;
}

// Validate date format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_pagamento)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Formato de data inválido. Use YYYY-MM-DD']);
    exit;
}

// compute month reference
$year = intval(substr($data_pagamento, 0, 4));
$month = intval(substr($data_pagamento, 5, 2));
$mes_ref = sprintf('%04d-%02d', $year, $month);

$created = 0;
$skipped = 0;
$errors = [];

try {
    $conn->begin_transaction();

    // Ensure pagamentos row exists (similar to updateStatusPagamento.php behavior)
    $stmt = $conn->prepare("SELECT idpagamento FROM pagamentos WHERE colaborador_id = ? AND mes_ref = ? FOR UPDATE");
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('is', $colaborador_id, $mes_ref);
    $stmt->execute();
    $res = $stmt->get_result();
    $pag = $res->fetch_assoc();
    $stmt->close();

    if (!$pag) {
        $ins = $conn->prepare("INSERT INTO pagamentos (colaborador_id, mes_ref, status, criado_por) VALUES (?,?, 'pendente_envio', NULL)");
        if (!$ins) throw new Exception('Prepare insert pagamentos failed: ' . $conn->error);
        $ins->bind_param('is', $colaborador_id, $mes_ref);
        $ins->execute();
        $pagamento_id = $ins->insert_id;
        $ins->close();
    } else {
        $pagamento_id = (int)$pag['idpagamento'];
    }

    // Find funcao_imagem rows for this collaborator and exact data_pagamento
    // Join funcao to detect name (in case funcao_id differs)
    $q = $conn->prepare(
        "SELECT fi.idfuncao_imagem, fi.funcao_id, fi.valor, f.nome_funcao
         FROM funcao_imagem fi
         LEFT JOIN funcao f ON fi.funcao_id = f.idfuncao
         WHERE fi.colaborador_id = ? AND DATE(fi.data_pagamento) = ?"
    );
    if (!$q) throw new Exception('Prepare select funcao_imagem failed: ' . $conn->error);
    $q->bind_param('is', $colaborador_id, $data_pagamento);
    $q->execute();
    $rs = $q->get_result();

    // Detect if pagamento_itens has observacao column
    $hasObservacao = false;
    $colChk = $conn->query("SHOW COLUMNS FROM pagamento_itens LIKE 'observacao'");
    if ($colChk && $colChk->num_rows > 0) $hasObservacao = true;

    if ($hasObservacao) {
        $insItem = $conn->prepare("INSERT INTO pagamento_itens (pagamento_id, origem, origem_id, valor, observacao) VALUES (?,?,?,?,?)");
        if (!$insItem) throw new Exception('Prepare insert pagamento_itens failed: ' . $conn->error);
    } else {
        $insItem = $conn->prepare("INSERT INTO pagamento_itens (pagamento_id, origem, origem_id, valor) VALUES (?,?,?,?)");
        if (!$insItem) throw new Exception('Prepare insert pagamento_itens (no obs) failed: ' . $conn->error);
    }

    // also prepare a check for existing same observacao to avoid duplicates
    if ($hasObservacao) {
        $chk = $conn->prepare("SELECT COUNT(1) AS cnt FROM pagamento_itens WHERE pagamento_id = ? AND origem = 'funcao_imagem' AND origem_id = ? AND observacao = 'Finalização Parcial'");
        if (!$chk) throw new Exception('Prepare check failed: ' . $conn->error);
    } else {
        // if no observacao column, check by origem/origem_id and skip if exists
        $chk = $conn->prepare("SELECT COUNT(1) AS cnt FROM pagamento_itens WHERE pagamento_id = ? AND origem = 'funcao_imagem' AND origem_id = ?");
        if (!$chk) throw new Exception('Prepare check failed: ' . $conn->error);
    }

    while ($row = $rs->fetch_assoc()) {
        $idfi = (int)$row['idfuncao_imagem'];
        $funcao_id = isset($row['funcao_id']) ? intval($row['funcao_id']) : null;
        $valor = isset($row['valor']) ? (float)$row['valor'] : 0.0;
        $nome_funcao = isset($row['nome_funcao']) ? strtolower($row['nome_funcao']) : '';

        // Consider as finalização if funcao_id == 4 OR nome contains 'finaliz'
        $isFinalizacao = ($funcao_id === 4) || (strpos($nome_funcao, 'finaliz') !== false);
        if (!$isFinalizacao) {
            $skipped++;
            continue;
        }

        // check duplicate
        $cnt = 0;
        $chk->bind_param('ii', $pagamento_id, $idfi);
        $chk->execute();
        $cres = $chk->get_result();
        if ($cres && ($c = $cres->fetch_assoc())) $cnt = intval($c['cnt']);

        if ($cnt > 0) {
            $skipped++;
            continue;
        }

        if ($hasObservacao) {
            $obs = 'Finalização Parcial';
            $insItem->bind_param('isids', $pagamento_id, $o = 'funcao_imagem', $idfi, $valor, $obs);
        } else {
            $insItem->bind_param('isid', $pagamento_id, $o = 'funcao_imagem', $idfi, $valor);
        }

        if (!$insItem->execute()) {
            $errors[] = "Falha ao inserir item $idfi: " . $insItem->error;
        } else {
            $created++;
        }
    }

    $q->close();
    if (isset($insItem) && $insItem) $insItem->close();
    if (isset($chk) && $chk) $chk->close();

    $conn->commit();

    echo json_encode(['success' => true, 'created' => $created, 'skipped' => $skipped, 'errors' => $errors]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
