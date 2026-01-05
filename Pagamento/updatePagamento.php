<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!empty($data['ids'])) {
        include '../conexao.php';

        // If caller provided collaborator/month, we'll group the selected ids into a pagamentos record
        $colaborador_id = isset($data['colaborador_id']) ? intval($data['colaborador_id']) : null;
        $mes = isset($data['mes']) ? intval($data['mes']) : null;
        $ano = isset($data['ano']) ? intval($data['ano']) : null;
        $usuario_id = isset($data['usuario_id']) ? intval($data['usuario_id']) : null;

        // We'll still accept the simple flow (no pagamentos logging) if mes/ano not provided,
        // but prefer to create pagamentos and pagamento_itens when mes+ano+colaborador present.
        $use_pagamentos = ($colaborador_id && $mes && $ano);

        if ($use_pagamentos) {
            $mes_ref = sprintf('%04d-%02d', $ano, $mes);
            $conn->begin_transaction();
            try {
                // Ensure pagamentos row exists
                $stmt = $conn->prepare("SELECT idpagamento FROM pagamentos WHERE colaborador_id = ? AND mes_ref = ? FOR UPDATE");
                $stmt->bind_param('is', $colaborador_id, $mes_ref);
                $stmt->execute();
                $res = $stmt->get_result();
                $pagamento = $res->fetch_assoc();
                $stmt->close();

                if (!$pagamento) {
                    $ins = $conn->prepare("INSERT INTO pagamentos (colaborador_id, mes_ref, status, criado_por) VALUES (?,?, 'pendente_envio', ?)");
                    $ins->bind_param('isi', $colaborador_id, $mes_ref, $usuario_id);
                    $ins->execute();
                    $pagamento_id = $ins->insert_id;
                    $ins->close();
                    $ev = $conn->prepare("INSERT INTO pagamento_eventos (pagamento_id, tipo, descricao, usuario_id) VALUES (?,?,?,?)");
                    $t = 'created';
                    $d = 'Pagamento criado automaticamente';
                    $ev->bind_param('issi', $pagamento_id, $t, $d, $usuario_id);
                    $ev->execute();
                    $ev->close();
                } else {
                    $pagamento_id = (int)$pagamento['idpagamento'];
                }

                // Prepare inserts and lookups
                $hasObservacao = false;
                $colChk = $conn->query("SHOW COLUMNS FROM pagamento_itens LIKE 'observacao'");
                if ($colChk && $colChk->num_rows > 0) $hasObservacao = true;

                if ($hasObservacao) {
                    $insItem = $conn->prepare("INSERT INTO pagamento_itens (pagamento_id, origem, origem_id, valor, observacao) VALUES (?,?,?,?,?)");
                } else {
                    $insItem = $conn->prepare("INSERT INTO pagamento_itens (pagamento_id, origem, origem_id, valor) VALUES (?,?,?,?)");
                }

                $valor_total = 0.0;
                $col_orig_ids = [];
                $nullObs = null;

                foreach ($data['ids'] as $item) {
                    $id = intval($item['id']);
                    $origem = $item['origem'];
                    $funcao_name = isset($item['funcao_name']) ? trim($item['funcao_name']) : '';

                    // fetch valor and any meta we need per origin
                    if ($origem === 'funcao_imagem') {
                        $s = $conn->prepare("SELECT idfuncao_imagem, IFNULL(valor,0) AS valor, funcao_id, imagem_id, pagamento FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1");
                        $s->bind_param('i', $id);
                        $s->execute();
                        $r = $s->get_result();
                        $row = $r ? $r->fetch_assoc() : null;
                        $s->close();
                        $valor = $row ? (float)$row['valor'] : 0.0;
                        $funcao_id_db = ($row && isset($row['funcao_id'])) ? (int)$row['funcao_id'] : null;
                        $imagem_id_db = ($row && isset($row['imagem_id'])) ? (int)$row['imagem_id'] : null;
                        $ja_pago = ($row && isset($row['pagamento'])) ? (int)$row['pagamento'] === 1 : false;

                        // If it is already paid, do not re-register item/events.
                        if ($ja_pago) {
                            continue;
                        }

                        // mark origin as paid
                        $u = $conn->prepare("UPDATE funcao_imagem SET pagamento = 1, data_pagamento = NOW() WHERE idfuncao_imagem = ?");
                        $u->bind_param('i', $id);
                        $u->execute();
                        $u->close();

                        // observation: use supplied funcao_name to detect 'Finalização parcial'
                        $obs = null;
                        if ($hasObservacao) {
                            if (mb_strtolower($funcao_name, 'UTF-8') === mb_strtolower('Finalização parcial', 'UTF-8')) {
                                $obs = 'Finalização Parcial';
                            }
                        }

                        // If user is paying Finalização Completa and there was historic Finalização Parcial for the same imagem,
                        // register audit events and mark this item as 'Pago Completa'.
                        $parcialInfo = null;
                        $isFinalizacao = ($funcao_id_db !== null && (int)$funcao_id_db === 4);
                        $isCompleta = (mb_strtolower($funcao_name, 'UTF-8') === mb_strtolower('Finalização completa', 'UTF-8'));
                        if ($hasObservacao && $isFinalizacao && $isCompleta && $imagem_id_db) {
                            // If a full payment has already been recorded for this imagem, ignore.
                            $dup = $conn->prepare(
                                "SELECT COUNT(1) AS cnt\n" .
                                "FROM pagamento_itens pi\n" .
                                "JOIN funcao_imagem fi2 ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi2.idfuncao_imagem\n" .
                                "WHERE fi2.imagem_id = ? AND fi2.funcao_id = 4 AND pi.observacao = 'Pago Completa'"
                            );
                            if ($dup) {
                                $dup->bind_param('i', $imagem_id_db);
                                $dup->execute();
                                $dr = $dup->get_result();
                                $drow = $dr ? $dr->fetch_assoc() : null;
                                $dup->close();
                                if (!empty($drow) && intval($drow['cnt']) > 0) {
                                    continue;
                                }
                            }

                            $ps = $conn->prepare(
                                "SELECT pi.pagamento_id, pi.origem_id, pi.criado_em AS data_parcial\n" .
                                "FROM pagamento_itens pi\n" .
                                "JOIN funcao_imagem fi2 ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi2.idfuncao_imagem\n" .
                                "WHERE fi2.imagem_id = ? AND fi2.funcao_id = 4 AND pi.observacao = 'Finalização Parcial'\n" .
                                "ORDER BY pi.criado_em ASC LIMIT 1"
                            );
                            if ($ps) {
                                $ps->bind_param('i', $imagem_id_db);
                                $ps->execute();
                                $rr = $ps->get_result();
                                $parcialInfo = $rr ? $rr->fetch_assoc() : null;
                                $ps->close();
                            }
                            if ($parcialInfo) {
                                $obs = 'Pago Completa';
                            }
                        }

                        if ($hasObservacao) {
                            $insItem->bind_param('isids', $pagamento_id, $origem, $id, $valor, $obs);
                        } else {
                            $insItem->bind_param('isid', $pagamento_id, $origem, $id, $valor);
                        }
                        $insItem->execute();
                        $valor_total += $valor;

                        if ($parcialInfo) {
                            $evx = $conn->prepare("INSERT INTO pagamento_eventos (pagamento_id, tipo, descricao, usuario_id) VALUES (?,?,?,?)");
                            if ($evx) {
                                $tipo1 = 'finalizacao_parcial';
                                $desc1 = 'Finalização Parcial registrada em ' . ($parcialInfo['data_parcial'] ?? '') .
                                    ' (pagamento_id=' . ($parcialInfo['pagamento_id'] ?? '') .
                                    ', funcao_imagem_id=' . ($parcialInfo['origem_id'] ?? '') .
                                    ', imagem_id=' . $imagem_id_db . ')';
                                $evx->bind_param('issi', $pagamento_id, $tipo1, $desc1, $usuario_id);
                                $evx->execute();

                                $tipo2 = 'finalizacao_completa';
                                $desc2 = 'Finalização Completa registrada em ' . date('Y-m-d H:i:s') .
                                    ' (funcao_imagem_id=' . $id . ', imagem_id=' . $imagem_id_db .
                                    ', historico_parcial=' . ($parcialInfo['data_parcial'] ?? '') . ')';
                                $evx->bind_param('issi', $pagamento_id, $tipo2, $desc2, $usuario_id);
                                $evx->execute();
                                $evx->close();
                            }
                        }
                    } elseif ($origem === 'acompanhamento') {
                        $s = $conn->prepare("SELECT idacompanhamento, IFNULL(valor,0) AS valor FROM acompanhamento WHERE idacompanhamento = ? LIMIT 1");
                        $s->bind_param('i', $id);
                        $s->execute();
                        $r = $s->get_result();
                        $row = $r ? $r->fetch_assoc() : null;
                        $s->close();
                        $valor = $row ? (float)$row['valor'] : 0.0;
                        $u = $conn->prepare("UPDATE acompanhamento SET pagamento = 1, data_pagamento = NOW() WHERE idacompanhamento = ?");
                        $u->bind_param('i', $id);
                        $u->execute();
                        $u->close();
                        if ($hasObservacao) {
                            $insItem->bind_param('isids', $pagamento_id, $origem, $id, $valor, $nullObs);
                        } else {
                            $insItem->bind_param('isid', $pagamento_id, $origem, $id, $valor);
                        }
                        $insItem->execute();
                        $valor_total += $valor;
                    } elseif ($origem === 'animacao') {
                        $s = $conn->prepare("SELECT idanimacao, IFNULL(valor,0) AS valor FROM animacao WHERE idanimacao = ? LIMIT 1");
                        $s->bind_param('i', $id);
                        $s->execute();
                        $r = $s->get_result();
                        $row = $r ? $r->fetch_assoc() : null;
                        $s->close();
                        $valor = $row ? (float)$row['valor'] : 0.0;
                        $u = $conn->prepare("UPDATE animacao SET pagamento = 1, data_pagamento = NOW() WHERE idanimacao = ?");
                        $u->bind_param('i', $id);
                        $u->execute();
                        $u->close();
                        if ($hasObservacao) {
                            $insItem->bind_param('isids', $pagamento_id, $origem, $id, $valor, $nullObs);
                        } else {
                            $insItem->bind_param('isid', $pagamento_id, $origem, $id, $valor);
                        }
                        $insItem->execute();
                        $valor_total += $valor;
                    }
                }

                // finalize pagamentos
                $upd = $conn->prepare("UPDATE pagamentos SET status='pago', valor_total = ?, data_pagamento = NOW(), pago_em = NOW() WHERE idpagamento = ?");
                $upd->bind_param('di', $valor_total, $pagamento_id);
                $upd->execute();
                $upd->close();

                $ev = $conn->prepare("INSERT INTO pagamento_eventos (pagamento_id, tipo, descricao, usuario_id) VALUES (?,?,?,?)");
                $t = 'pago';
                $d = 'Pagamento confirmado via UI (itens selecionados)';
                $ev->bind_param('issi', $pagamento_id, $t, $d, $usuario_id);
                $ev->execute();
                $ev->close();

                $conn->commit();
                echo json_encode(['success' => true, 'pagamento_id' => $pagamento_id]);
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        } else {
            // fallback: simple per-item updates (no pagamentos/itens created)
            foreach ($data['ids'] as $item) {
                $id = intval($item['id']);
                $origem = $item['origem'];
                if ($origem === 'funcao_imagem') {
                    $sql = "UPDATE funcao_imagem SET pagamento = 1, data_pagamento = NOW() WHERE idfuncao_imagem = ?";
                } elseif ($origem === 'acompanhamento') {
                    $sql = "UPDATE acompanhamento SET pagamento = 1, data_pagamento = NOW() WHERE idacompanhamento = ?";
                } elseif ($origem === 'animacao') {
                    $sql = "UPDATE animacao SET pagamento = 1, data_pagamento = NOW() WHERE idanimacao = ?";
                } else {
                    continue;
                }
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
            }
            echo json_encode(['success' => true]);
            exit;
        }
    } else {
        // Support batch payment by collaborator/month when not sending explicit ids
        // Expected payload: { colaborador_id: int, ano: int, mes: int, usuario_id: int }
        if (!empty($data['colaborador_id']) && !empty($data['ano']) && !empty($data['mes'])) {
            include '../conexao.php';
            $colaborador_id = intval($data['colaborador_id']);
            $ano = intval($data['ano']);
            $mes = intval($data['mes']);
            $usuario_id = isset($data['usuario_id']) ? intval($data['usuario_id']) : null;

            $mes_ref = sprintf('%04d-%02d', $ano, $mes);

            $conn->begin_transaction();
            try {
                // Ensure pagamentos row exists
                $stmt = $conn->prepare("SELECT idpagamento, status FROM pagamentos WHERE colaborador_id = ? AND mes_ref = ? FOR UPDATE");
                $stmt->bind_param('is', $colaborador_id, $mes_ref);
                $stmt->execute();
                $res = $stmt->get_result();
                $pagamento = $res->fetch_assoc();
                $stmt->close();

                if (!$pagamento) {
                    // create pagamentos row with initial status 'pendente_envio'
                    $ins = $conn->prepare("INSERT INTO pagamentos (colaborador_id, mes_ref, status, criado_por) VALUES (?,?, 'pendente_envio', ?)");
                    $ins->bind_param('isi', $colaborador_id, $mes_ref, $usuario_id);
                    $ins->execute();
                    $pagamento_id = $ins->insert_id;
                    $ins->close();
                    // log evento created
                    $ev = $conn->prepare("INSERT INTO pagamento_eventos (pagamento_id, tipo, descricao, usuario_id) VALUES (?,?,?,?)");
                    $t = 'created';
                    $d = 'Pagamento criado automaticamente';
                    $ev->bind_param('issi', $pagamento_id, $t, $d, $usuario_id);
                    $ev->execute();
                    $ev->close();
                } else {
                    $pagamento_id = (int)$pagamento['idpagamento'];
                }

                // Collect unpaid items for the month (funcao_imagem, acompanhamento, animacao)
                // First, try to resolve the function id for 'Pré-Finalização' using flexible matching
                $prefinal_funcao_id = null;
                $try_patterns = [
                    '%pré%finaliz%',
                    '%pre%finaliz%',
                    '%pré-finaliz%',
                    '%pre-finaliz%'
                ];
                foreach ($try_patterns as $pat) {
                    $s = $conn->prepare("SELECT idfuncao FROM funcao WHERE LOWER(nome_funcao) LIKE ? LIMIT 1");
                    if ($s) {
                        $p = mb_strtolower($pat, 'UTF-8');
                        $s->bind_param('s', $p);
                        $s->execute();
                        $r = $s->get_result();
                        if ($rowf = $r->fetch_assoc()) {
                            $prefinal_funcao_id = intval($rowf['idfuncao']);
                            $s->close();
                            break;
                        }
                        $s->close();
                    }
                }

                $idsFI = [];
                $idsAC = [];
                $idsAN = [];
                $valor_total = 0.0;
                // Fetch basic funcao_imagem rows; we'll determine presence of prefinação separately (more robust)
                $q = $conn->prepare("SELECT fi.idfuncao_imagem, IFNULL(fi.valor,0) AS valor, i.status_id, fi.funcao_id, fi.imagem_id FROM funcao_imagem fi LEFT JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra WHERE fi.colaborador_id = ? AND fi.pagamento = 0 AND YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?");
                $q->bind_param('iii', $colaborador_id, $ano, $mes);
                $q->execute();
                $rs = $q->get_result();
                while ($row = $rs->fetch_assoc()) {
                    $idsFI[] = [
                        'id' => (int)$row['idfuncao_imagem'],
                        'valor' => (float)$row['valor'],
                        'status_id' => isset($row['status_id']) ? intval($row['status_id']) : null,
                        'funcao_id' => isset($row['funcao_id']) ? intval($row['funcao_id']) : null,
                        'imagem_id' => isset($row['imagem_id']) ? intval($row['imagem_id']) : null,
                        'has_prefinalizacao' => 0
                    ];
                    $valor_total += (float)$row['valor'];
                }
                $q->close();

                // For each funcao_imagem row, check if the same imagem has a pre-finalizacao funcao (if we resolved an id)
                if ($prefinal_funcao_id !== null && !empty($idsFI)) {
                    $chk = $conn->prepare("SELECT COUNT(1) AS cnt FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = ? LIMIT 1");
                    foreach ($idsFI as $k => $item) {
                        if (isset($item['imagem_id']) && $item['imagem_id']) {
                            $chk->bind_param('ii', $item['imagem_id'], $prefinal_funcao_id);
                            $chk->execute();
                            $reschk = $chk->get_result();
                            $rc = $reschk ? $reschk->fetch_assoc() : null;
                            $idsFI[$k]['has_prefinalizacao'] = (!empty($rc) && intval($rc['cnt']) > 0) ? 1 : 0;
                        }
                    }
                    $chk->close();
                }
                // acompanhamento by data
                $q = $conn->prepare("SELECT idacompanhamento, IFNULL(valor,0) AS valor FROM acompanhamento WHERE colaborador_id = ? AND pagamento = 0 AND YEAR(data) = ? AND MONTH(data) = ?");
                $q->bind_param('iii', $colaborador_id, $ano, $mes);
                $q->execute();
                $rs = $q->get_result();
                while ($row = $rs->fetch_assoc()) {
                    $idsAC[] = ['id' => (int)$row['idacompanhamento'], 'valor' => (float)$row['valor']];
                    $valor_total += (float)$row['valor'];
                }
                $q->close();
                // animacao by data_anima
                $q = $conn->prepare("SELECT idanimacao, IFNULL(valor,0) AS valor FROM animacao WHERE colaborador_id = ? AND pagamento = 0 AND YEAR(data_anima) = ? AND MONTH(data_anima) = ?");
                $q->bind_param('iii', $colaborador_id, $ano, $mes);
                $q->execute();
                $rs = $q->get_result();
                while ($row = $rs->fetch_assoc()) {
                    $idsAN[] = ['id' => (int)$row['idanimacao'], 'valor' => (float)$row['valor']];
                    $valor_total += (float)$row['valor'];
                }
                $q->close();

                // Update origin tables: mark as paid
                if (!empty($idsFI)) {
                    $ids = implode(',', array_map(function ($x) {
                        return intval($x['id']);
                    }, $idsFI));
                    $conn->query("UPDATE funcao_imagem SET pagamento = 1, data_pagamento = NOW() WHERE idfuncao_imagem IN ($ids)");
                }
                if (!empty($idsAC)) {
                    $ids = implode(',', array_map(function ($x) {
                        return intval($x['id']);
                    }, $idsAC));
                    $conn->query("UPDATE acompanhamento SET pagamento = 1, data_pagamento = NOW() WHERE idacompanhamento IN ($ids)");
                }
                if (!empty($idsAN)) {
                    $ids = implode(',', array_map(function ($x) {
                        return intval($x['id']);
                    }, $idsAN));
                    $conn->query("UPDATE animacao SET pagamento = 1, data_pagamento = NOW() WHERE idanimacao IN ($ids)");
                }

                // Insert items rows with valor and observacao (if applicable)
                $hasObservacao = false;
                $colChk = $conn->query("SHOW COLUMNS FROM pagamento_itens LIKE 'observacao'");
                if ($colChk && $colChk->num_rows > 0) $hasObservacao = true;

                if ($hasObservacao) {
                    $insItem = $conn->prepare("INSERT INTO pagamento_itens (pagamento_id, origem, origem_id, valor, observacao) VALUES (?,?,?,?,?)");
                    if (!$insItem) throw new Exception('Prepare failed (pagamento_itens with observacao): ' . $conn->error);
                    foreach ($idsFI as $item) {
                        $o = 'funcao_imagem';
                        $id = $item['id'];
                        $v = $item['valor'];
                        $isFinalizacaoFunc = (isset($item['funcao_id']) && intval($item['funcao_id']) === 4);
                        $hasPrefinal = (isset($item['has_prefinalizacao']) && intval($item['has_prefinalizacao']) > 0);
                        // If this is a finalização and flagged as partial finalization, set observation
                        $obs = ($isFinalizacaoFunc && ((isset($item['status_id']) && intval($item['status_id']) === 1) || $hasPrefinal)) ? 'Finalização Parcial' : null;

                        // If it is Finalização Completa (obs null) but has historic Finalização Parcial for the same imagem,
                        // mark as 'Pago Completa' and register audit events.
                        $parcialInfo = null;
                        if ($isFinalizacaoFunc && $obs === null && !empty($item['imagem_id'])) {
                            $imagem_id_db = intval($item['imagem_id']);
                            $ps = $conn->prepare(
                                "SELECT pi.pagamento_id, pi.origem_id, pi.criado_em AS data_parcial\n" .
                                "FROM pagamento_itens pi\n" .
                                "JOIN funcao_imagem fi2 ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi2.idfuncao_imagem\n" .
                                "WHERE fi2.imagem_id = ? AND fi2.funcao_id = 4 AND pi.observacao = 'Finalização Parcial'\n" .
                                "ORDER BY pi.criado_em ASC LIMIT 1"
                            );
                            if ($ps) {
                                $ps->bind_param('i', $imagem_id_db);
                                $ps->execute();
                                $rr = $ps->get_result();
                                $parcialInfo = $rr ? $rr->fetch_assoc() : null;
                                $ps->close();
                            }
                            if ($parcialInfo) {
                                $obs = 'Pago Completa';
                            }
                        }
                        $insItem->bind_param('isids', $pagamento_id, $o, $id, $v, $obs);
                        $insItem->execute();

                        if ($parcialInfo) {
                            $evx = $conn->prepare("INSERT INTO pagamento_eventos (pagamento_id, tipo, descricao, usuario_id) VALUES (?,?,?,?)");
                            if ($evx) {
                                $tipo1 = 'finalizacao_parcial';
                                $desc1 = 'Finalização Parcial registrada em ' . ($parcialInfo['data_parcial'] ?? '') .
                                    ' (pagamento_id=' . ($parcialInfo['pagamento_id'] ?? '') .
                                    ', funcao_imagem_id=' . ($parcialInfo['origem_id'] ?? '') .
                                    ', imagem_id=' . $imagem_id_db . ')';
                                $evx->bind_param('issi', $pagamento_id, $tipo1, $desc1, $usuario_id);
                                $evx->execute();

                                $tipo2 = 'finalizacao_completa';
                                $desc2 = 'Finalização Completa registrada em ' . date('Y-m-d H:i:s') .
                                    ' (funcao_imagem_id=' . $id . ', imagem_id=' . $imagem_id_db .
                                    ', historico_parcial=' . ($parcialInfo['data_parcial'] ?? '') . ')';
                                $evx->bind_param('issi', $pagamento_id, $tipo2, $desc2, $usuario_id);
                                $evx->execute();
                                $evx->close();
                            }
                        }
                    }
                    foreach ($idsAC as $item) {
                        $o = 'acompanhamento';
                        $id = $item['id'];
                        $v = $item['valor'];
                        $obs = null;
                        $insItem->bind_param('isids', $pagamento_id, $o, $id, $v, $obs);
                        $insItem->execute();
                    }
                    foreach ($idsAN as $item) {
                        $o = 'animacao';
                        $id = $item['id'];
                        $v = $item['valor'];
                        $obs = null;
                        $insItem->bind_param('isids', $pagamento_id, $o, $id, $v, $obs);
                        $insItem->execute();
                    }
                    $insItem->close();
                } else {
                    // Fallback: table has no 'observacao' column; insert without it
                    $insItem = $conn->prepare("INSERT INTO pagamento_itens (pagamento_id, origem, origem_id, valor) VALUES (?,?,?,?)");
                    if (!$insItem) throw new Exception('Prepare failed (pagamento_itens without observacao): ' . $conn->error);
                    foreach ($idsFI as $item) {
                        $o = 'funcao_imagem';
                        $id = $item['id'];
                        $v = $item['valor'];
                        $insItem->bind_param('isid', $pagamento_id, $o, $id, $v);
                        $insItem->execute();
                    }
                    foreach ($idsAC as $item) {
                        $o = 'acompanhamento';
                        $id = $item['id'];
                        $v = $item['valor'];
                        $insItem->bind_param('isid', $pagamento_id, $o, $id, $v);
                        $insItem->execute();
                    }
                    foreach ($idsAN as $item) {
                        $o = 'animacao';
                        $id = $item['id'];
                        $v = $item['valor'];
                        $insItem->bind_param('isid', $pagamento_id, $o, $id, $v);
                        $insItem->execute();
                    }
                    $insItem->close();
                }

                // Update aggregate pagamento (use new lowercase status and set data_pagamento)
                $upd = $conn->prepare("UPDATE pagamentos SET status='pago', valor_total = ?, data_pagamento = NOW(), pago_em = NOW() WHERE idpagamento = ?");
                $upd->bind_param('di', $valor_total, $pagamento_id);
                $upd->execute();
                $upd->close();

                // Log evento
                $ev = $conn->prepare("INSERT INTO pagamento_eventos (pagamento_id, tipo, descricao, usuario_id) VALUES (?,?,?,?)");
                $t = 'pago';
                $d = 'Pagamento marcado como PAGO e itens confirmados (' . (count($idsFI) + count($idsAC) + count($idsAN)) . ' itens)';
                $ev->bind_param('issi', $pagamento_id, $t, $d, $usuario_id);
                $ev->execute();
                $ev->close();

                $conn->commit();
                echo json_encode(['success' => true, 'pagamento_id' => $pagamento_id]);
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        }

        echo json_encode(['success' => false, 'error' => 'IDs inválidos.']);
    }
}
