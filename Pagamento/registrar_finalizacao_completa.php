<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método inválido. Use POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['ids']) || !is_array($input['ids'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload inválido. Esperado: { ids: [int], data_pagamento?: YYYY-MM-DD, usuario_id?: int }']);
    exit;
}

$data_pagamento = isset($input['data_pagamento']) ? trim((string)$input['data_pagamento']) : '';
$usuario_id = isset($input['usuario_id']) ? intval($input['usuario_id']) : null;

if ($data_pagamento === '') {
    $data_pagamento = date('Y-m-d');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_pagamento)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Formato de data inválido. Use YYYY-MM-DD']);
    exit;
}

require_once __DIR__ . '/../conexao.php';

$year = intval(substr($data_pagamento, 0, 4));
$month = intval(substr($data_pagamento, 5, 2));
$mes_ref = sprintf('%04d-%02d', $year, $month);

try {
    $conn->begin_transaction();

    $hasObservacao = false;
    $colChk = $conn->query("SHOW COLUMNS FROM pagamento_itens LIKE 'observacao'");
    if ($colChk && $colChk->num_rows > 0) $hasObservacao = true;

    // Prepare statements
    $selFI = $conn->prepare(
        "SELECT idfuncao_imagem, colaborador_id, funcao_id, imagem_id, IFNULL(valor,0) AS valor, pagamento, data_pagamento\n" .
        "FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1"
    );
    if (!$selFI) throw new Exception('Prepare select funcao_imagem failed: ' . $conn->error);

    $ensurePag = $conn->prepare("SELECT idpagamento FROM pagamentos WHERE colaborador_id = ? AND mes_ref = ? FOR UPDATE");
    if (!$ensurePag) throw new Exception('Prepare select pagamentos failed: ' . $conn->error);

    $insPag = $conn->prepare("INSERT INTO pagamentos (colaborador_id, mes_ref, status, criado_por) VALUES (?,?, 'pendente_envio', ?)");
    if (!$insPag) throw new Exception('Prepare insert pagamentos failed: ' . $conn->error);

    $updFI = $conn->prepare("UPDATE funcao_imagem SET pagamento = 1, data_pagamento = COALESCE(data_pagamento, ?) WHERE idfuncao_imagem = ?");
    if (!$updFI) throw new Exception('Prepare update funcao_imagem failed: ' . $conn->error);

    $chkDup = $conn->prepare(
        "SELECT COUNT(1) AS cnt FROM pagamento_itens WHERE origem = 'funcao_imagem' AND origem_id = ? AND observacao = 'Pago Completa'"
    );
    if (!$chkDup) throw new Exception('Prepare duplicate check failed: ' . $conn->error);

    if ($hasObservacao) {
        $insItem = $conn->prepare("INSERT INTO pagamento_itens (pagamento_id, origem, origem_id, valor, observacao) VALUES (?,?,?,?,?)");
    } else {
        $insItem = $conn->prepare("INSERT INTO pagamento_itens (pagamento_id, origem, origem_id, valor) VALUES (?,?,?,?)");
    }
    if (!$insItem) throw new Exception('Prepare insert pagamento_itens failed: ' . $conn->error);

    $insEv = $conn->prepare("INSERT INTO pagamento_eventos (pagamento_id, tipo, descricao, usuario_id) VALUES (?,?,?,?)");
    if (!$insEv) throw new Exception('Prepare insert pagamento_eventos failed: ' . $conn->error);

    $pagamentoByColab = [];

    $created = [];
    $skipped = [];
    $errors = [];

    foreach ($input['ids'] as $rawId) {
        $id = intval($rawId);
        if ($id <= 0) continue;

        $selFI->bind_param('i', $id);
        $selFI->execute();
        $rs = $selFI->get_result();
        $fi = $rs ? $rs->fetch_assoc() : null;

        if (!$fi) {
            $errors[] = ['id' => $id, 'error' => 'idfuncao_imagem não encontrado'];
            continue;
        }

        $colab = intval($fi['colaborador_id']);
        $funcao_id = isset($fi['funcao_id']) ? intval($fi['funcao_id']) : null;
        $imagem_id = isset($fi['imagem_id']) ? intval($fi['imagem_id']) : null;
        $valor = isset($fi['valor']) ? (float)$fi['valor'] : 0.0;

        if ($funcao_id !== 4) {
            $errors[] = ['id' => $id, 'error' => 'funcao_id != 4 (não é Finalização)'];
            continue;
        }

        // Prevent duplicates: if this origem_id already has Pago Completa recorded, skip.
        if ($hasObservacao) {
            $chkDup->bind_param('i', $id);
            $chkDup->execute();
            $cr = $chkDup->get_result();
            $c = $cr ? $cr->fetch_assoc() : null;
            if ($c && intval($c['cnt']) > 0) {
                $skipped[] = ['id' => $id, 'reason' => 'Já existe pagamento_itens com observacao=Pago Completa'];
                continue;
            }
        }

        // Ensure pagamentos (one per collaborator/month)
        $key = $colab . '|' . $mes_ref;
        if (!isset($pagamentoByColab[$key])) {
            $ensurePag->bind_param('is', $colab, $mes_ref);
            $ensurePag->execute();
            $pr = $ensurePag->get_result();
            $p = $pr ? $pr->fetch_assoc() : null;

            if (!$p) {
                $insPag->bind_param('isi', $colab, $mes_ref, $usuario_id);
                $insPag->execute();
                $pagamento_id = $insPag->insert_id;

                $t = 'created';
                $d = 'Pagamento criado automaticamente (registro manual Pago Completa)';
                $insEv->bind_param('issi', $pagamento_id, $t, $d, $usuario_id);
                $insEv->execute();
            } else {
                $pagamento_id = (int)$p['idpagamento'];
            }

            $pagamentoByColab[$key] = $pagamento_id;
        } else {
            $pagamento_id = $pagamentoByColab[$key];
        }

        // Mark origin row as paid if not already
        $updFI->bind_param('si', $data_pagamento, $id);
        $updFI->execute();

        // Insert pagamento_itens as 'Pago Completa'
        $origem = 'funcao_imagem';
        if ($hasObservacao) {
            $obs = 'Pago Completa';
            $insItem->bind_param('isids', $pagamento_id, $origem, $id, $valor, $obs);
        } else {
            $insItem->bind_param('isid', $pagamento_id, $origem, $id, $valor);
        }
        $insItem->execute();

        // Event
        $t = 'finalizacao_completa';
        $d = 'Registro manual: Pago Completa em ' . $data_pagamento . ' (funcao_imagem_id=' . $id . ', imagem_id=' . $imagem_id . ')';
        $insEv->bind_param('issi', $pagamento_id, $t, $d, $usuario_id);
        $insEv->execute();

        $created[] = ['id' => $id, 'pagamento_id' => $pagamento_id];
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'mes_ref' => $mes_ref,
        'data_pagamento' => $data_pagamento,
        'created' => $created,
        'skipped' => $skipped,
        'errors' => $errors
    ]);
} catch (Throwable $e) {
    if (isset($conn)) $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
