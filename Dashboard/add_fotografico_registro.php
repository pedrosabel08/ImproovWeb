<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

$obra_id = isset($data['obra_id']) && is_numeric($data['obra_id']) ? intval($data['obra_id']) : null;
$registro_data = isset($data['registro_data']) ? $data['registro_data'] : null;
$observacoes = isset($data['observacoes']) ? $data['observacoes'] : null;
$criado_por = isset($_SESSION['idcolaborador']) ? intval($_SESSION['idcolaborador']) : null;

if (!$obra_id || !$registro_data) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados incompletos']);
    exit;
}

try {
    $sql = "INSERT INTO fotografico_registro (obra_id, registro_data, observacoes, criado_por) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('issi', $obra_id, $registro_data, $observacoes, $criado_por);
    $ok = $stmt->execute();
    if (!$ok) throw new Exception($stmt->error);
    $newId = $stmt->insert_id;

    // --- After insert: notify finalizadores (funcao_id = 4) of this obra via Slack ---
    // minimal dotenv loader (looks for scripts/.env)
    function load_env_from_scripts()
    {
        $envPath = __DIR__ . '/../scripts/.env';
        if (!is_file($envPath)) return;
        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
                $val = $m[2];
                $val = trim($val, " \t\r\n\"'");
                putenv($m[1] . "={$val}");
                $_ENV[$m[1]] = $val;
            }
        }
    }

    function send_simple_slack_dm($colaborador_id, $obra_label, $original_name = null)
    {
        global $conn;
        if (!isset($conn) || !$colaborador_id) return false;
        // load env
        load_env_from_scripts();
        $token = getenv('SLACK_TOKEN') ?: null;
        if (!$token) return false;

        $stmt = $conn->prepare('SELECT nome_slack, nome_usuario FROM usuario WHERE idcolaborador = ? LIMIT 1');
        if (!$stmt) return false;
        $stmt->bind_param('i', $colaborador_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) return false;
        $nome_slack = $row['nome_slack'] ?? null;
        $nome = $row['nome_usuario'] ?? null;
        if (!$nome_slack) return false;

        $looksLikeUserId = preg_match('/^[UW][A-Z0-9]+$/', $nome_slack) === 1;
        $userId = null;
        if ($looksLikeUserId) {
            $userId = $nome_slack;
        } else {
            // resolve via users.list
            $listUrl = 'https://slack.com/api/users.list';
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => "Authorization: Bearer {$token}\r\n",
                    'timeout' => 8
                ]
            ];
            $ctx = stream_context_create($opts);
            $res = @file_get_contents($listUrl, false, $ctx);
            if ($res) {
                $data = json_decode($res, true);
                if ($data && !empty($data['ok']) && !empty($data['members']) && is_array($data['members'])) {
                    foreach ($data['members'] as $m) {
                        $real = ($m['profile']['real_name'] ?? '') ?: '';
                        $display = ($m['profile']['display_name'] ?? '') ?: '';
                        if (mb_strtolower($real) === mb_strtolower($nome_slack) || mb_strtolower($display) === mb_strtolower($nome_slack)) {
                            $userId = $m['id'] ?? null;
                            break;
                        }
                    }
                }
            }
            if (!$userId) return false;
        }

        $original = $original_name ?: '';
        $text = "Novo fotográfico da obra: {$obra_label}, confira!";

        $payload = json_encode(['channel' => $userId, 'text' => $text]);
        $apiUrl = getenv('SLACK_API_URL') ?: 'https://slack.com/api/chat.postMessage';
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$token}\r\n",
                'content' => $payload,
                'timeout' => 8
            ]
        ];
        $ctx = stream_context_create($opts);
        $res = @file_get_contents($apiUrl, false, $ctx);
        if ($res === false) return false;
        $resp = json_decode($res, true);
        return ($resp && !empty($resp['ok']));
    }

    // Fetch finalizadores (funcao_id = 4) for this obra (need idfuncao_imagem and colaborador_id)
    $notify_results = [];
    try {
        // fetch obra nomenclatura for user-friendly messages
        $nomenclatura = null;
        $stm = $conn->prepare("SELECT nomenclatura FROM obra WHERE idobra = ? LIMIT 1");
        if ($stm) {
            $stm->bind_param('i', $obra_id);
            $stm->execute();
            $rstm = $stm->get_result();
            if ($rstm && $rown = $rstm->fetch_assoc()) $nomenclatura = $rown['nomenclatura'];
            $stm->close();
        }
        $sql = "SELECT fi.idfuncao_imagem, fi.colaborador_id FROM funcao_imagem fi JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id WHERE ico.obra_id = ? AND fi.funcao_id = 4 AND fi.colaborador_id IS NOT NULL";
        $s = $conn->prepare($sql);
        if ($s) {
            $s->bind_param('i', $obra_id);
            $s->execute();
            $res = $s->get_result();
            if ($res) {
                // prepare statement to create notificacoes (cards)
                $insNotif = $conn->prepare("INSERT INTO notificacoes (colaborador_id, mensagem, data, lida, funcao_imagem_id) VALUES (?, ?, ?, 0, ?)");
                if (!$insNotif) error_log('[add_fotografico_registro] prepare insNotif failed: ' . $conn->error);

                // prepared stmt to get nome_slack to deduplicate Slack DMs
                $selNomeSlack = $conn->prepare("SELECT nome_slack FROM usuario WHERE idcolaborador = ? LIMIT 1");
                if (!$selNomeSlack) error_log('[add_fotografico_registro] prepare selNomeSlack failed: ' . $conn->error);

                $slackSent = [];
                while ($r = $res->fetch_assoc()) {
                    $colId = intval($r['colaborador_id']);
                    $funcaoImagemId = intval($r['idfuncao_imagem']);
                    if (!$colId || !$funcaoImagemId) continue;

                    // 1) create notificacao (card) for this funcao_imagem (use nomenclatura when available)
                    $obraRef = $nomenclatura ? $nomenclatura : 'Obra ' . $obra_id;
                    $msg = "Novo fotográfico registrado — confira o registro da obra {$obraRef}.";
                    $now = date('Y-m-d H:i:s');
                    if ($insNotif) {
                        $insNotif->bind_param('issi', $colId, $msg, $now, $funcaoImagemId);
                        if (!@$insNotif->execute()) {
                            error_log('[add_fotografico_registro] insNotif execute failed: ' . $insNotif->error);
                        }
                    }

                    // Determine dedupe key: prefer nome_slack when available
                    $nomeSlack = null;
                    if ($selNomeSlack) {
                        $selNomeSlack->bind_param('i', $colId);
                        if ($selNomeSlack->execute()) {
                            $rns = $selNomeSlack->get_result();
                            if ($rns && ($rowns = $rns->fetch_assoc())) {
                                $nomeSlack = trim($rowns['nome_slack'] ?? '');
                            }
                        } else {
                            error_log('[add_fotografico_registro] selNomeSlack execute failed: ' . $selNomeSlack->error);
                        }
                    }

                    $sendKey = $nomeSlack ? ('slack:' . $nomeSlack) : ('col:' . $colId);
                    if (!isset($slackSent[$sendKey])) {
                        $okNotify = @send_simple_slack_dm($colId, $obraRef, $observacoes);
                        $slackSent[$sendKey] = $okNotify ? true : false;
                    } else {
                        $okNotify = $slackSent[$sendKey];
                    }
                    $notify_results[$funcaoImagemId] = $okNotify ? true : false;
                }

                if ($insNotif) $insNotif->close();
                if ($selNomeSlack) $selNomeSlack->close();
            }
            $s->close();
        }
    } catch (Exception $e) {
        // don't break API: just log
        error_log('[add_fotografico_registro] Slack notify error: ' . $e->getMessage());
    }

    // Prepare slack summary for response
    if (empty($notify_results)) {
        $slackSummary = ['notified' => false, 'reason' => 'no_finalizadores', 'details' => []];
    } else {
        $total = count($notify_results);
        $success = count(array_filter($notify_results));
        $slackSummary = [
            'notified' => ($success > 0),
            'total' => $total,
            'succeeded' => $success,
            'failed' => $total - $success,
            'details' => $notify_results
        ];
    }

    // Final response includes slack summary
    // 5) create acompanhamento_email record linking this registro (best-effort)
    try {
        $check = $conn->query("SHOW TABLES LIKE 'acompanhamento_email'");
        if ($check && $check->num_rows > 0) {
            // compute next ordem
            $ord = 1;
            if ($stmtOrd = $conn->prepare("SELECT IFNULL(MAX(ordem),0)+1 AS next_ordem FROM acompanhamento_email WHERE obra_id = ?")) {
                $stmtOrd->bind_param('i', $obra_id);
                $stmtOrd->execute();
                $rOrd = $stmtOrd->get_result()->fetch_assoc();
                if ($rOrd && isset($rOrd['next_ordem'])) $ord = intval($rOrd['next_ordem']);
                $stmtOrd->close();
            }

            $assunto = 'Registrado novo fotográfico';
            // $mensagem = 'Foi registrado um novo fotográfico. ID registro: ' . $newId . '. Observações: ' . ($observacoes ?: '');
            // coluna `data` é do tipo DATE — use apenas a parte de data
            $dataAgora = date('Y-m-d');
            // try insert with common columns — include colaborador_id as NULL (many places expect this column)
            $insA = $conn->prepare("INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto, data, ordem, tipo) VALUES (?, ?, ?, ?, ?, 'fotografico')");
            if (!$insA) {
                error_log('[add_fotografico_registro] prepare acompanhamento_email failed: ' . $conn->error);
            } else {
                $insA->bind_param('iissi', $obra_id, $criado_por, $assunto, $dataAgora, $ord);
                if (!@$insA->execute()) {
                    error_log('[add_fotografico_registro] execute acompanhamento_email failed: ' . $insA->error);
                } else {
                    error_log('[add_fotografico_registro] acompanhamento_email inserted, id=' . $conn->insert_id);
                }
                $insA->close();
            }
        }
    } catch (Exception $e) {
        error_log('[add_fotografico_registro] acompanhamento_email insert failed: ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'id' => $newId, 'slack' => $slackSummary]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
