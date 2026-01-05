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
$idfuncao_imagem = isset($input['idfuncao_imagem']) ? intval($input['idfuncao_imagem']) : (isset($input['funcao_imagem_id']) ? intval($input['funcao_imagem_id']) : 0);
$data_pagamento = isset($input['data_pagamento']) ? trim($input['data_pagamento']) : '';
$observacao_input = isset($input['observacao']) ? trim((string)$input['observacao']) : null;
$sem_observacao = false;
if (isset($input['sem_observacao'])) {
    $v = $input['sem_observacao'];
    $sem_observacao = ($v === 1 || $v === '1' || $v === true || $v === 'true' || $v === 'sim');
}

if ($observacao_input !== null) {
    // treat empty and explicit 'null' as no observation
    if ($observacao_input === '' || strtolower($observacao_input) === 'null') {
        $sem_observacao = true;
        $observacao_input = null;
    }
}

// Accept BR format DD/MM/YYYY as well
if ($data_pagamento && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data_pagamento)) {
    [$d, $m, $y] = explode('/', $data_pagamento);
    $data_pagamento = sprintf('%04d-%02d-%02d', intval($y), intval($m), intval($d));
}

if ((!$colaborador_id && !$idfuncao_imagem) || !$data_pagamento) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos: informe idfuncao_imagem OU colaborador_id, e data_pagamento (YYYY-MM-DD ou DD/MM/YYYY)']);
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

    // Detect if pagamento_itens has observacao column (required to mark as "Finalização Parcial")
    $hasObservacao = false;
    $colChk = $conn->query("SHOW COLUMNS FROM pagamento_itens LIKE 'observacao'");
    if ($colChk && $colChk->num_rows > 0) $hasObservacao = true;

    // If caller passed a specific funcao_imagem id, mark it paid on the provided date and register the item.
    if ($idfuncao_imagem) {
        // Fetch funcao_imagem info
        $s = $conn->prepare(
            "SELECT fi.idfuncao_imagem, fi.colaborador_id, fi.funcao_id, IFNULL(fi.valor,0) AS valor, f.nome_funcao
             FROM funcao_imagem fi
             LEFT JOIN funcao f ON fi.funcao_id = f.idfuncao
             WHERE fi.idfuncao_imagem = ?");
        if (!$s) throw new Exception('Prepare select funcao_imagem by id failed: ' . $conn->error);
        $s->bind_param('i', $idfuncao_imagem);
        $s->execute();
        $rr = $s->get_result();
        $fi = $rr ? $rr->fetch_assoc() : null;
        $s->close();

        if (!$fi) {
            throw new Exception('idfuncao_imagem não encontrado');
        }

        $colaborador_id = intval($fi['colaborador_id']);
        $funcao_id = isset($fi['funcao_id']) ? intval($fi['funcao_id']) : null;
        $valor = isset($fi['valor']) ? (float)$fi['valor'] : 0.0;
        $nome_funcao = isset($fi['nome_funcao']) ? strtolower($fi['nome_funcao']) : '';

        // Consider as finalização if funcao_id == 4 OR nome contains 'finaliz'
        $isFinalizacao = ($funcao_id === 4) || (strpos($nome_funcao, 'finaliz') !== false);
        // Keep legacy safety: this endpoint is mainly for finalização items.
        // If you really want to pay a non-finalização item, pass sem_observacao=1.
        if (!$isFinalizacao && !$sem_observacao) {
            throw new Exception('A função informada não parece ser uma Finalização. Para marcar como pago sem observação, use sem_observacao=1');
        }

        // Ensure pagamentos row exists
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

        // Mark the origin row as paid on the requested date
        $u = $conn->prepare("UPDATE funcao_imagem SET pagamento = 1, data_pagamento = ? WHERE idfuncao_imagem = ?");
        if (!$u) throw new Exception('Prepare update funcao_imagem failed: ' . $conn->error);
        $u->bind_param('si', $data_pagamento, $idfuncao_imagem);
        $u->execute();
        $u->close();

        // Determine observation
        // Default behavior (legacy): register as "Finalização Parcial" unless user forces no observation.
        $obs = null;
        if (!$sem_observacao) {
            $obs = ($observacao_input !== null && $observacao_input !== '') ? $observacao_input : 'Finalização Parcial';
        }

        // Check duplicate within same pagamento
        if ($hasObservacao) {
            if ($obs === null) {
                $chk = $conn->prepare("SELECT COUNT(1) AS cnt FROM pagamento_itens WHERE pagamento_id = ? AND origem = 'funcao_imagem' AND origem_id = ?");
            } else {
                $chk = $conn->prepare("SELECT COUNT(1) AS cnt FROM pagamento_itens WHERE pagamento_id = ? AND origem = 'funcao_imagem' AND origem_id = ? AND observacao = ?");
            }
        } else {
            $chk = $conn->prepare("SELECT COUNT(1) AS cnt FROM pagamento_itens WHERE pagamento_id = ? AND origem = 'funcao_imagem' AND origem_id = ?");
        }
        if (!$chk) throw new Exception('Prepare check failed: ' . $conn->error);
        if ($hasObservacao && $obs !== null) {
            $chk->bind_param('iis', $pagamento_id, $idfuncao_imagem, $obs);
        } else {
            $chk->bind_param('ii', $pagamento_id, $idfuncao_imagem);
        }
        $chk->execute();
        $cres = $chk->get_result();
        $cnt = 0;
        if ($cres && ($c = $cres->fetch_assoc())) $cnt = intval($c['cnt']);
        $chk->close();

        if ($cnt > 0) {
            $skipped++;
        } else {
            $o = 'funcao_imagem';
            if ($hasObservacao) {
                $insItem = $conn->prepare("INSERT INTO pagamento_itens (pagamento_id, origem, origem_id, valor, observacao) VALUES (?,?,?,?,?)");
                if (!$insItem) throw new Exception('Prepare insert pagamento_itens failed: ' . $conn->error);
                $insItem->bind_param('isids', $pagamento_id, $o, $idfuncao_imagem, $valor, $obs);
            } else {
                $insItem = $conn->prepare("INSERT INTO pagamento_itens (pagamento_id, origem, origem_id, valor) VALUES (?,?,?,?)");
                if (!$insItem) throw new Exception('Prepare insert pagamento_itens failed: ' . $conn->error);
                $insItem->bind_param('isid', $pagamento_id, $o, $idfuncao_imagem, $valor);
            }

            if (!$insItem->execute()) {
                $errors[] = "Falha ao inserir item $idfuncao_imagem: " . $insItem->error;
            } else {
                $created++;
            }
            $insItem->close();
        }

        // Keep pagamentos.valor_total consistent with items (if column exists)
        $colTot = $conn->query("SHOW COLUMNS FROM pagamentos LIKE 'valor_total'");
        if ($colTot && $colTot->num_rows > 0) {
            $updTot = $conn->prepare("UPDATE pagamentos SET valor_total = (SELECT IFNULL(SUM(valor),0) FROM pagamento_itens WHERE pagamento_id = ?) WHERE idpagamento = ?");
            if ($updTot) {
                $updTot->bind_param('ii', $pagamento_id, $pagamento_id);
                $updTot->execute();
                $updTot->close();
            }
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'mode' => 'idfuncao_imagem',
            'pagamento_id' => $pagamento_id,
            'colaborador_id' => $colaborador_id,
            'data_pagamento' => $data_pagamento,
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors
        ]);
        exit;
    }

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

        $o = 'funcao_imagem';
        if ($hasObservacao) {
            $obs = 'Finalização Parcial';
            $insItem->bind_param('isids', $pagamento_id, $o, $idfi, $valor, $obs);
        } else {
            $insItem->bind_param('isid', $pagamento_id, $o, $idfi, $valor);
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
