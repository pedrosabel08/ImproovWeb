<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['entrega_id'], $input['imagens_entregues'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

$entrega_id = intval($input['entrega_id']);
$imagens_entregues = $input['imagens_entregues'];

try {
    $conn->begin_transaction();

    $hoje = date('Y-m-d');

    // Atualiza o status de cada item individualmente e coleta imagem_id processadas
    $processed_image_ids = array();
    if (!empty($imagens_entregues)) {
        $stmtSelect = $conn->prepare("SELECT ei.id, ei.imagem_id, e.data_prevista 
                                      FROM entregas_itens ei 
                                      JOIN entregas e ON ei.entrega_id = e.id 
                                      WHERE ei.id = ? AND ei.entrega_id = ?");
        $stmtUpdate = $conn->prepare("UPDATE entregas_itens SET status=?, data_entregue=NOW() WHERE id=?");

        foreach ($imagens_entregues as $item_id) {
            $item_id = intval($item_id);
            $stmtSelect->bind_param('ii', $item_id, $entrega_id);
            $stmtSelect->execute();
            $res = $stmtSelect->get_result()->fetch_assoc();
            if (!$res) continue;

            $status_item = ($hoje <= $res['data_prevista']) ? 'Entregue no prazo' : 'Entregue com atraso';
            $stmtUpdate->bind_param('si', $status_item, $item_id);
            $stmtUpdate->execute();

            if (isset($res['imagem_id']) && !empty($res['imagem_id'])) {
                $processed_image_ids[] = intval($res['imagem_id']);
            }
        }
        if ($stmtSelect) $stmtSelect->close();
        if ($stmtUpdate) $stmtUpdate->close();
    }

    // Verificar total de imagens, quantas já estão entregues e obter obra_id/data_prevista
    // Use agregação para evitar ONLY_FULL_GROUP_BY: data_prevista/obra_id são constantes por entrega, então
    // MAX() retorna o valor correto sem precisar de GROUP BY.
    $stmt = $conn->prepare("SELECT COUNT(*) AS total, 
                    SUM(CASE WHEN ei.status LIKE 'Entregue%' THEN 1 ELSE 0 END) AS entregues,
                    MAX(e.data_prevista) AS data_prevista,
                    MAX(e.obra_id) AS obra_id
                FROM entregas_itens ei
                JOIN entregas e ON ei.entrega_id = e.id
                WHERE ei.entrega_id=?");
    $stmt->bind_param('i', $entrega_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    $total = intval($res['total']);
    $entregues = intval($res['entregues']);
    $data_prevista = $res['data_prevista'];
    $obra_id = isset($res['obra_id']) ? intval($res['obra_id']) : null;

    // Determinar novo status da entrega
    if ($entregues === 0) {
        $novo_status = 'Pendente';
    } elseif ($entregues < $total) {
        $novo_status = 'Parcial';
    } elseif ($entregues === $total && $hoje < $data_prevista) {
        $novo_status = 'Entrega antecipada';
    } elseif ($entregues === $total && $hoje > $data_prevista) {
        $novo_status = 'Entregue com atraso';
    } elseif ($entregues === $total && $hoje == $data_prevista) {
        $novo_status = 'Entregue no prazo';
    } else {
        $novo_status = 'Concluída';
    }


    // Fetch previous status (and status_id) so we can detect transitions
    $old_status = null;
    $old_status_id = null;
    $stmtOld = $conn->prepare("SELECT status, status_id FROM entregas WHERE id = ?");
    $stmtOld->bind_param('i', $entrega_id);
    $stmtOld->execute();
    $rOld = $stmtOld->get_result()->fetch_assoc();
    if ($rOld) {
        $old_status = $rOld['status'];
        $old_status_id = isset($rOld['status_id']) ? intval($rOld['status_id']) : null;
    }

    // If certain transitions happen, also insert an entry into acompanhamento_email
    // Compute next ordem for this obra (simple MAX+1)
    $next_ordem = 1;
    if ($obra_id) {
        $stmtOrdem = $conn->prepare("SELECT IFNULL(MAX(ordem),0)+1 AS next_ordem FROM acompanhamento_email WHERE obra_id = ?");
        $stmtOrdem->bind_param('i', $obra_id);
        $stmtOrdem->execute();
        $rOrd = $stmtOrdem->get_result()->fetch_assoc();
        if ($rOrd && isset($rOrd['next_ordem'])) $next_ordem = intval($rOrd['next_ordem']);
        $stmtOrdem->close();
    }

    // Get obra nomenclatura for description
    $obra_nome = '';
    if ($obra_id) {
        $stmtObra = $conn->prepare("SELECT nomenclatura FROM obra WHERE idobra = ? LIMIT 1");
        $stmtObra->bind_param('i', $obra_id);
        $stmtObra->execute();
        $rObra = $stmtObra->get_result()->fetch_assoc();
        if ($rObra) $obra_nome = $rObra['nomenclatura'];
        $stmtObra->close();
    }

    // Calcular contagem total por obra (todas as entregas da obra)
    $total_obra = 0;
    $entregues_obra = 0;
    if ($obra_id) {
        $stmtObraCounts = $conn->prepare("SELECT COUNT(*) AS total_obra, SUM(CASE WHEN ei.status LIKE 'Entregue%' THEN 1 ELSE 0 END) AS entregues_obra
            FROM entregas_itens ei
            JOIN entregas e ON ei.entrega_id = e.id
            WHERE e.obra_id = ?");
        $stmtObraCounts->bind_param('i', $obra_id);
        $stmtObraCounts->execute();
        $rCounts = $stmtObraCounts->get_result()->fetch_assoc();
        if ($rCounts) {
            $total_obra = intval($rCounts['total_obra']);
            $entregues_obra = intval($rCounts['entregues_obra']);
        }
        $stmtObraCounts->close();
    }


    // Prepare insert into acompanhamento_email (we'll use it conditionally)
    $insertAcompStmt = $conn->prepare("INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto, data, ordem, entrega_id, tipo, status) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)");

    // Helper: procura acompanhamento pendente existente para a obra e tipo 'entrega'
    $findPendingAcompStmt = $conn->prepare("SELECT idacompanhamento_email FROM acompanhamento_email WHERE obra_id = ? AND tipo = 'entrega' AND status = 'pendente' ORDER BY data DESC LIMIT 1");
    $updateAcompStmt = $conn->prepare("UPDATE acompanhamento_email SET assunto = ?, data = ?, entrega_id = ? WHERE idacompanhamento_email = ?");

    // Resolve nome_status using the entrega's status_id if available; otherwise try to match novo_status text.
    $status_nome = $novo_status;
    $novo_status_id = null;
    if (!empty($old_status_id)) {
        $stmtStatus = $conn->prepare("SELECT idstatus, nome_status FROM status_imagem WHERE idstatus = ? LIMIT 1");
        if ($stmtStatus) {
            $stmtStatus->bind_param('i', $old_status_id);
            $stmtStatus->execute();
            $rStat = $stmtStatus->get_result()->fetch_assoc();
            if ($rStat && isset($rStat['nome_status'])) {
                $status_nome = $rStat['nome_status'];
                $novo_status_id = intval($rStat['idstatus']);
            }
            $stmtStatus->close();
        }
    }
    // If not found by id, try matching by the computed novo_status text
    if (empty($novo_status_id)) {
        $stmtStatusName = $conn->prepare("SELECT idstatus, nome_status FROM status_imagem WHERE nome_status COLLATE utf8mb4_unicode_ci = ? LIMIT 1");
        if ($stmtStatusName) {
            $stmtStatusName->bind_param('s', $novo_status);
            $stmtStatusName->execute();
            $rStatName = $stmtStatusName->get_result()->fetch_assoc();
            if ($rStatName && isset($rStatName['nome_status'])) {
                $status_nome = $rStatName['nome_status'];
                $novo_status_id = intval($rStatName['idstatus']);
            }
            $stmtStatusName->close();
        }
    }

    // Evento de entrega parcial
    // Atualiza acompanhamento sempre que a entrega estiver em estado 'Parcial',
    // inclusive quando já estava parcial e recebe novas imagens.
    if ($novo_status === 'Parcial') {
        // Usar contagem pela entrega + contagem por obra para refletir entregas acumuladas
        $assunto = 'Entrega parcialmente concluída ('. $entregues_obra . ' de ' . $total_obra . ' imagens entregues) com status ' . $status_nome;
        $tipo = 'entrega';
        $status_acomp = 'pendente';
        $data_today = date('Y-m-d');

        // Se existir acompanhamento pendente para esta obra, atualiza em vez de inserir
        if ($obra_id && $findPendingAcompStmt) {
            $findPendingAcompStmt->bind_param('i', $obra_id);
            $findPendingAcompStmt->execute();
            $rFind = $findPendingAcompStmt->get_result()->fetch_assoc();
            if ($rFind && isset($rFind['idacompanhamento_email'])) {
                $acomp_id = intval($rFind['idacompanhamento_email']);
                if ($updateAcompStmt) {
                    $updateAcompStmt->bind_param('ssii', $assunto, $data_today, $entrega_id, $acomp_id);
                    $updateAcompStmt->execute();
                }
            } else {
                if ($insertAcompStmt) $insertAcompStmt->bind_param('issiiss', $obra_id, $assunto, $data_today, $next_ordem, $entrega_id, $tipo, $status_acomp);
                if ($insertAcompStmt) $insertAcompStmt->execute();
                $next_ordem++;
            }
        } else {
            if ($insertAcompStmt) $insertAcompStmt->bind_param('issiiss', $obra_id, $assunto, $data_today, $next_ordem, $entrega_id, $tipo, $status_acomp);
            if ($insertAcompStmt) $insertAcompStmt->execute();
            $next_ordem++;
        }
    }

    // Evento de entrega concluída
    $concluido_set = array('Entregue no prazo','Entregue com atraso','Entrega antecipada');
    if (in_array($novo_status, $concluido_set) && !in_array($old_status, $concluido_set)) {
        $assunto = 'Entrega ' . $status_nome . ' concluída na obra ' . $obra_nome;
        $tipo = 'entrega';
        $status_acomp = 'pendente';
        $data_today = date('Y-m-d');

        // Para conclusão também atualizamos acompanhamento pendente se existir (mantendo comportamento consistente)
        if ($obra_id && $findPendingAcompStmt) {
            $findPendingAcompStmt->bind_param('i', $obra_id);
            $findPendingAcompStmt->execute();
            $rFind = $findPendingAcompStmt->get_result()->fetch_assoc();
                if ($rFind && isset($rFind['idacompanhamento_email'])) {
                    $acomp_id = intval($rFind['idacompanhamento_email']);
                    if ($updateAcompStmt) {
                        $updateAcompStmt->bind_param('ssii', $assunto, $data_today, $entrega_id, $acomp_id);
                        $updateAcompStmt->execute();
                    }
            } else {
                if ($insertAcompStmt) $insertAcompStmt->bind_param('issiiss', $obra_id, $assunto, $data_today, $next_ordem, $entrega_id, $tipo, $status_acomp);
                if ($insertAcompStmt) $insertAcompStmt->execute();
            }
        } else {
            if ($insertAcompStmt) $insertAcompStmt->bind_param('issiiss', $obra_id, $assunto, $data_today, $next_ordem, $entrega_id, $tipo, $status_acomp);
            if ($insertAcompStmt) $insertAcompStmt->execute();
        }
    }

    if ($insertAcompStmt) $insertAcompStmt->close();
    if ($findPendingAcompStmt) $findPendingAcompStmt->close();
    if ($updateAcompStmt) $updateAcompStmt->close();

    // Atualizar status da entrega (inclui status_id quando disponível)
    if ($novo_status_id !== null) {
        $stmt = $conn->prepare("UPDATE entregas SET status=?, status_id=?, data_conclusao=NOW() WHERE id=?");
        $stmt->bind_param('sii', $novo_status, $novo_status_id, $entrega_id);
    } else {
        $stmt = $conn->prepare("UPDATE entregas SET status=?, data_conclusao=NOW() WHERE id=?");
        $stmt->bind_param('si', $novo_status, $entrega_id);
    }
    $stmt->execute();

    // Atualizar substatus_id nas imagens vinculadas aos itens desta entrega.
    // Regra: se a entrega tiver status_id = 6 ou 1 => substatus_id = 9, senão => substatus_id = 6.
    $substatus_to_set = 6;
    if (!empty($novo_status_id) && ($novo_status_id === 6 || $novo_status_id === 1)) {
        $substatus_to_set = 9;
    }

    // Atualiza apenas as imagens que foram marcadas como entregues (itens processados)
    if (!empty($processed_image_ids)) {
        $stmtUpdateImg = $conn->prepare("UPDATE imagens_cliente_obra SET substatus_id = ? WHERE idimagens_cliente_obra = ?");
        if ($stmtUpdateImg) {
            foreach ($processed_image_ids as $img_id) {
                $stmtUpdateImg->bind_param('ii', $substatus_to_set, $img_id);
                $stmtUpdateImg->execute();
            }
            $stmtUpdateImg->close();
        }
    }

    $conn->commit();

    // === Envio de e-mail com resumo da entrega (teste) ===
    // Se existir token do Mailtrap, usa a API de envio; caso contrário, usa PHPMailer/SMTP ou mail().
    $mail_log = '';
    $mailtrap_token = getenv('MAILTRAP_API_TOKEN') ?: null;
    $to = 'giovanasabel24@gmail.com';
    $subject = "Resumo de entrega - {$obra_nome}";
    $link = (isset($_SERVER['HTTP_HOST']) ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . 
            "/ImproovWeb/Entregas/?entrega_id={$entrega_id}";
    $html_body = "<p>Olá,</p><p>Foram postadas <strong>{$entregues}</strong> imagens da obra <strong>{$obra_nome}</strong> com status <strong>{$status_nome}</strong>.</p><p>Consulte a entrega aqui: <a href=\"{$link}\">{$link}</a></p><p>Atenciosamente,<br>Equipe</p>";

    if ($mailtrap_token) {
        // Envia via Mailtrap Send API
        $payload = [
            'from' => ['email' => getenv('MAIL_FROM') ?: 'hello@demomailtrap.co', 'name' => getenv('MAIL_FROM_NAME') ?: 'Improov'],
            'to' => [['email' => $to]],
            'subject' => $subject,
            'html' => $html_body,
            'text' => strip_tags(str_replace(['<br>', '<br/>', '<p>','</p>'], "\n", $html_body)),
            'category' => 'Entrega'
        ];

        $ch = curl_init('https://send.api.mailtrap.io/api/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $mailtrap_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $resp = curl_exec($ch);
        if (curl_errno($ch)) {
            $mail_log = 'Curl error: ' . curl_error($ch);
        } else {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http_code >= 200 && $http_code < 300) {
                $mail_log = 'Email enviado via Mailtrap API para ' . $to;
            } else {
                $mail_log = 'Mailtrap API retornou HTTP ' . $http_code . ' resposta: ' . substr($resp, 0, 1000);
            }
        }
        curl_close($ch);
    } else {
        // Apenas Mailtrap suportado para testes locais; se não houver token, não envia.
        $mail_log = 'MAILTRAP_API_TOKEN não definido — email não enviado.';
    }

    echo json_encode([
        'success' => true,
        'novo_status' => $novo_status,
        'total_imagens' => $total,
        'entregues' => $entregues,
        'mail_log' => $mail_log ?? 'mail não executado'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
