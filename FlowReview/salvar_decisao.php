<?php
header('Content-Type: application/json');
require_once '../conexao.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/auth_cookie.php';
if (empty($flow_user_id)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$entrega_item_id = isset($data['entrega_item_id']) ? intval($data['entrega_item_id']) : 0;
$historico_imagem_id = isset($data['historico_imagem_id']) ? intval($data['historico_imagem_id']) : 0;
$decisao = isset($data['decisao']) ? trim($data['decisao']) : '';
$usuario_id = $flow_user_id;
$angulo_id = isset($data['angulo_id']) ? intval($data['angulo_id']) : 0;
$observacao = isset($data['observacao']) ? trim($data['observacao']) : null;

// validação: aceita historico_imagem_id OR angulo_id
if ($entrega_item_id <= 0 || ($historico_imagem_id <= 0 && $angulo_id <= 0) || $decisao === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos']);
    exit();
}

try {
    // Carrega variáveis de ambiente (.env no diretório pai) sem depender de phpdotenv
    function load_dotenv_simple($path)
    {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;
            list($k, $v) = array_map('trim', explode('=', $line, 2));
            $v = trim($v, "\"'");
            // Some PHP installations disable putenv(). Guard against that and
            // always populate superglobals so getenv()/
            // $_ENV/$_SERVER access still works.
            if (function_exists('putenv')) {
                @putenv("$k=$v");
            }
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
    // tenta carregar .env a partir da raiz do projeto (um nível acima de FlowReview)
    load_dotenv_simple(__DIR__ . '/../Revisao/.env');
    $slackToken = $_ENV['SLACK_TOKEN'] ?? getenv('SLACK_TOKEN') ?? null;
    $slackChannel = 'C09V1SES7B2'; // Canal fixo solicitado

    // Insere decisão vinculando o item da entrega e a versão (historico_imagem)
    // Tabela sugerida: imagem_decisoes (id, entrega_item_id, historico_imagem_id, decisao, usuario_id, created_at)
    // Se for decisão normal (versão/historico), grava em imagem_decisoes
    if ($historico_imagem_id > 0) {
        $sql = "INSERT INTO imagem_decisoes (entrega_item_id, historico_imagem_id, decisao, usuario_id, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    decisao = VALUES(decisao),
                    usuario_id = VALUES(usuario_id),
                    created_at = NOW()";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Erro ao preparar statement: ' . $conn->error);
        }
        $stmt->bind_param('iisi', $entrega_item_id, $historico_imagem_id, $decisao, $usuario_id);
        $ok = $stmt->execute();
        if (!$ok) {
            throw new Exception('Erro ao executar statement: ' . $stmt->error);
        }
        $stmt->close();
    }

    // Se for decisão de ângulo (P00), gravar em tabela específica de ângulos
    if ($angulo_id > 0) {
        // Criar/usar tabela: imagem_decisoes_angulos (entrega_item_id, angulo_id, decisao, observacao, usuario_id, created_at)
        $sqlA = "INSERT INTO imagem_decisoes_angulos (entrega_item_id, angulo_id, decisao, observacao, usuario_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    decisao = VALUES(decisao),
                    observacao = VALUES(observacao),
                    usuario_id = VALUES(usuario_id),
                    created_at = NOW()";
        $stmtA = $conn->prepare($sqlA);
        if (!$stmtA) {
            throw new Exception('Erro ao preparar statement angulos: ' . $conn->error);
        }
        $stmtA->bind_param('iissi', $entrega_item_id, $angulo_id, $decisao, $observacao, $usuario_id);
        $okA = $stmtA->execute();
        if (!$okA) {
            throw new Exception('Erro ao executar statement angulos: ' . $stmtA->error);
        }
        $stmtA->close();
        // Removido: qualquer tentativa de localizar e enviar arquivo de ângulo para FTP/NAS
    }

    // Recupera informações adicionais para enriquecer a mensagem
    $usuarioNome = null;
    $stmtUser = $conn->prepare("SELECT u.idusuario, nome_usuario FROM usuario_externo u WHERE u.idusuario = ? LIMIT 1");
    $stmtUser->bind_param('i', $usuario_id);
    if ($stmtUser->execute()) {
        $resUser = $stmtUser->get_result();
        if ($rowU = $resUser->fetch_assoc()) {
            $usuarioNome = $rowU['nome_usuario'];
        }
    }
    $stmtUser->close();

    $imagemNome = null;
    $arquivoVersao = null;
    $indiceEnvio = null;
    $dataEnvio = null;
    $entregaId = null;
    $imagemId = null;
    $revisaoCod = null;
    // junta dados de entrega_item + historico + imagem
    $stmtInfo = $conn->prepare("SELECT ei.entrega_id, ei.imagem_id, i.imagem_nome, hi.nome_arquivo, hi.indice_envio, ei.data_entregue FROM entregas_itens ei LEFT JOIN historico_aprovacoes_imagens hi ON hi.id = ei.historico_id LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = ei.imagem_id WHERE ei.id = ? LIMIT 1");
    $stmtInfo->bind_param('i', $entrega_item_id);
    if ($stmtInfo->execute()) {
        $resInfo = $stmtInfo->get_result();
        if ($rowI = $resInfo->fetch_assoc()) {
            $entregaId = $rowI['entrega_id'];
            $imagemId = $rowI['imagem_id'];
            $imagemNome = $rowI['imagem_nome'];
            $arquivoVersao = $rowI['nome_arquivo'];
            $indiceEnvio = $rowI['indice_envio'];
            $data_entregue = $rowI['data_entregue'];
        }
    }
    $stmtInfo->close();

    // extrai código de revisão do nome do arquivo (ex: _P00 / _R01)
    if ($arquivoVersao) {
        if (preg_match('/_([A-Z]\d{2})/i', $arquivoVersao, $m)) {
            $revisaoCod = strtoupper($m[1]);
        } elseif (preg_match('/_([A-Z]\d{3})/i', $arquivoVersao, $m3)) {
            $revisaoCod = strtoupper($m3[1]);
        }
    }

    $decisaoLabel = match ($decisao) {
        'aprovado' => 'Aprovado',
        'aprovado_com_ajustes' => 'Aprovado com ajustes',
        'ajuste' => 'Ajuste',
        default => ucfirst($decisao)
    };

    date_default_timezone_set('America/Sao_Paulo');
    $hora = date('d/m/Y H:i');
    // garantir variável com nome do usuário responsável pela ação
    $nomeUsuario = $usuarioNome ?? 'Usuário';
    $textoSlack = "Decisão registrada:\n";
    $textoSlack .= "• Imagem: " . ($imagemNome ?? 'sem nome') . "\n";
    if (!empty($data_entregue)) {
        $dataEnvioFmt = date('d/m/Y H:i', strtotime($data_entregue));
        $textoSlack .= "• Data envio versão: $dataEnvioFmt\n";
    }
    $textoSlack .= "• Decisão: $decisaoLabel\n";
    $textoSlack .= "• Responsável: " . ($nomeUsuario) . "\n";
    $textoSlack .= "• Registrado em: $hora";

    // Envia DM no Slack ao finalizador (funcao_id = 4) e mensagem ao canal — logs para diagnóstico
    $slackStatus = null;
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $logFile = $logDir . '/slack_notify.log';
    $now = date('Y-m-d H:i:s');

    // channel send will be executed later depending on decision type
    $chanStatus = null;

    // Descobrir nome_slack do finalizador a partir da imagem
    $finalizadorSlack = null;
    if (!empty($imagemId)) {
        $stmtFin = $conn->prepare("SELECT colaborador_id FROM funcao_imagem WHERE funcao_id = 4 AND imagem_id = ? ORDER BY idfuncao_imagem DESC LIMIT 1");
        if ($stmtFin) {
            $stmtFin->bind_param('i', $imagemId);
            if ($stmtFin->execute()) {
                $resFin = $stmtFin->get_result();
                if ($rowF = $resFin->fetch_assoc()) {
                    $colabId = intval($rowF['colaborador_id']);
                    $stmtUS = $conn->prepare("SELECT nome_slack FROM usuario WHERE idcolaborador = ? LIMIT 1");
                    if ($stmtUS) {
                        $stmtUS->bind_param('i', $colabId);
                        if ($stmtUS->execute()) {
                            $resUS = $stmtUS->get_result();
                            if ($rowUS = $resUS->fetch_assoc()) {
                                $finalizadorSlack = trim((string)$rowUS['nome_slack']);
                            }
                        }
                        $stmtUS->close();
                    }
                }
            }
            $stmtFin->close();
        }
    }

    if ($slackToken && $finalizadorSlack) {
        // 1) Obter user id via users.list procurando por real_name == nome_slack
        $chList = curl_init('https://slack.com/api/users.list');
        curl_setopt($chList, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $slackToken
        ]);
        curl_setopt($chList, CURLOPT_RETURNTRANSFER, true);
        $respList = curl_exec($chList);
        $httpList = curl_getinfo($chList, CURLINFO_HTTP_CODE);
        $userId = null;
        if (!curl_errno($chList)) {
            file_put_contents($logFile, "[$now] Slack: users.list HTTP $httpList\n", FILE_APPEND);
            $dataList = json_decode($respList, true);
            if (!empty($dataList['ok'])) {
                $needle = mb_strtolower($finalizadorSlack);
                foreach (($dataList['members'] ?? []) as $memb) {
                    $rn = isset($memb['real_name']) ? mb_strtolower($memb['real_name']) : '';
                    if ($rn !== '' && $rn === $needle) {
                        $userId = $memb['id'] ?? null;
                        break;
                    }
                }
            } else {
                $err = $dataList['error'] ?? 'desconhecido';
                file_put_contents($logFile, "[$now] Slack: users.list error: $err\n", FILE_APPEND);
            }
        } else {
            $curlErr = curl_error($chList);
            file_put_contents($logFile, "[$now] Slack: users.list curl error: $curlErr\n", FILE_APPEND);
        }
        curl_close($chList);

        if ($userId) {
            $payload = [
                'channel' => $userId,
                'text' => $textoSlack,
            ];
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
            file_put_contents($logFile, "[$now] Slack DM: sending payload to $userId: $payloadJson\n", FILE_APPEND);

            $ch = curl_init('https://slack.com/api/chat.postMessage');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $slackToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (curl_errno($ch)) {
                $curlErr = curl_error($ch);
                $dmStatus = 'erro_curl: ' . $curlErr;
                file_put_contents($logFile, "[$now] Slack DM: curl error: $curlErr\n", FILE_APPEND);
            } else {
                file_put_contents($logFile, "[$now] Slack DM: response HTTP $httpCode: $resp\n", FILE_APPEND);
                $respData = json_decode($resp, true);
                if (isset($respData['ok']) && $respData['ok']) {
                    $dmStatus = 'dm_enviada';
                } else {
                    $err = $respData['error'] ?? 'desconhecido';
                    $dmStatus = 'falha_api: ' . $err;
                    file_put_contents($logFile, "[$now] Slack DM: api error: $err\n", FILE_APPEND);
                }
            }
            curl_close($ch);
        } else {
            $dmStatus = 'usuario_slack_nao_encontrado';
            file_put_contents($logFile, "[$now] Slack DM: user not found for real_name='$finalizadorSlack'\n", FILE_APPEND);
        }
    } else {
        if (!$slackToken) {
            $dmStatus = 'token_indisponivel';
            file_put_contents($logFile, "[$now] Slack: token not available\n", FILE_APPEND);
        } elseif (!$finalizadorSlack) {
            $dmStatus = 'finalizador_slack_vazio';
            file_put_contents($logFile, "[$now] Slack DM: finalizador nome_slack vazio/indisponível (imagem_id=" . ($imagemId ?? 'N/D') . ")\n", FILE_APPEND);
        }
    }
    // Agora decide o tipo de mensagem e envia ao canal / DM conforme solicitado
    // Monta link para FlowReview (ponto de conferência)
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $link = $scheme . '://' . $host . '/sistema/FlowReview/index.php?imagem_id=' . urlencode((string)($imagemId ?? ''));

    // Se for decisão de ângulo, enviar DM ao finalizador e mensagem ao canal com responsável
    if ($angulo_id > 0) {
        $dmText = "Angulo escolhido:\nImagem: " . ($imagemNome ?? 'sem nome') . "\nConfira: $link";
        $chanText = "Angulo escolhido:\nImagem: " . ($imagemNome ?? 'sem nome') . "\nResponsavel: " . ($usuarioNome ?? 'Usuário') . "\nConfira: $link";

        // enviar ao canal
        if ($slackToken) {
            $payloadCh = ['channel' => $slackChannel, 'text' => $chanText];
            $payloadChJson = json_encode($payloadCh, JSON_UNESCAPED_UNICODE);
            file_put_contents($logFile, "[$now] Slack CHANNEL (angle): sending payload: $payloadChJson\n", FILE_APPEND);
            $chChan = curl_init('https://slack.com/api/chat.postMessage');
            curl_setopt($chChan, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $slackToken, 'Content-Type: application/json']);
            curl_setopt($chChan, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chChan, CURLOPT_POST, true);
            curl_setopt($chChan, CURLOPT_POSTFIELDS, $payloadChJson);
            $respChan = curl_exec($chChan);
            $httpChan = curl_getinfo($chChan, CURLINFO_HTTP_CODE);
            if (curl_errno($chChan)) {
                $chanStatus = 'canal_erro_curl: ' . curl_error($chChan);
                file_put_contents($logFile, "[$now] Slack CHANNEL (angle): curl error: " . curl_error($chChan) . "\n", FILE_APPEND);
            } else {
                file_put_contents($logFile, "[$now] Slack CHANNEL (angle): response HTTP $httpChan: $respChan\n", FILE_APPEND);
                $respDataChan = json_decode($respChan, true);
                if (!empty($respDataChan['ok'])) $chanStatus = 'canal_enviado';
                else $chanStatus = 'canal_falha_api: ' . ($respDataChan['error'] ?? 'desconhecido');
            }
            curl_close($chChan);
        } else {
            $chanStatus = 'token_indisponivel';
            file_put_contents($logFile, "[$now] Slack CHANNEL (angle): token not available\n", FILE_APPEND);
        }

        // enviar DM ao finalizador (se possível)
        if (!empty($finalizadorSlack) && $slackToken) {
            // Resolve user id via users.list (re-using earlier approach)
            $userId = null;
            $chList2 = curl_init('https://slack.com/api/users.list');
            curl_setopt($chList2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $slackToken]);
            curl_setopt($chList2, CURLOPT_RETURNTRANSFER, true);
            $respList2 = curl_exec($chList2);
            if (!curl_errno($chList2)) {
                $dataList2 = json_decode($respList2, true);
                if (!empty($dataList2['ok'])) {
                    $needle = mb_strtolower($finalizadorSlack);
                    foreach (($dataList2['members'] ?? []) as $memb) {
                        $rn = isset($memb['real_name']) ? mb_strtolower($memb['real_name']) : '';
                        if ($rn !== '' && $rn === $needle) {
                            $userId = $memb['id'] ?? null;
                            break;
                        }
                    }
                }
            }
            curl_close($chList2);

            if ($userId) {
                $payload = ['channel' => $userId, 'text' => $dmText];
                $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
                file_put_contents($logFile, "[$now] Slack DM (angle): sending payload to $userId: $payloadJson\n", FILE_APPEND);
                $ch = curl_init('https://slack.com/api/chat.postMessage');
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $slackToken, 'Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
                $resp = curl_exec($ch);
                if (curl_errno($ch)) {
                    $dmStatus = 'erro_curl: ' . curl_error($ch);
                    file_put_contents($logFile, "[$now] Slack DM (angle): curl error: " . curl_error($ch) . "\n", FILE_APPEND);
                } else {
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    file_put_contents($logFile, "[$now] Slack DM (angle): response HTTP $httpCode: $resp\n", FILE_APPEND);
                    $respData = json_decode($resp, true);
                    if (!empty($respData['ok'])) $dmStatus = 'dm_enviada';
                    else $dmStatus = 'falha_api: ' . ($respData['error'] ?? 'desconhecido');
                }
                curl_close($ch);
            } else {
                $dmStatus = 'usuario_slack_nao_encontrado';
                file_put_contents($logFile, "[$now] Slack DM (angle): user not found for real_name='$finalizadorSlack'\n", FILE_APPEND);
            }
        }
    } else {
        // Decisão de imagem: apenas enviar ao canal com o texto já preparado ($textoSlack)
        if ($slackToken) {
            $payloadCh = ['channel' => $slackChannel, 'text' => $textoSlack];
            $payloadChJson = json_encode($payloadCh, JSON_UNESCAPED_UNICODE);
            file_put_contents($logFile, "[$now] Slack CHANNEL (image): sending payload: $payloadChJson\n", FILE_APPEND);
            $chChan = curl_init('https://slack.com/api/chat.postMessage');
            curl_setopt($chChan, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $slackToken, 'Content-Type: application/json']);
            curl_setopt($chChan, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chChan, CURLOPT_POST, true);
            curl_setopt($chChan, CURLOPT_POSTFIELDS, $payloadChJson);
            $respChan = curl_exec($chChan);
            $httpChan = curl_getinfo($chChan, CURLINFO_HTTP_CODE);
            if (curl_errno($chChan)) {
                $chanStatus = 'canal_erro_curl: ' . curl_error($chChan);
                file_put_contents($logFile, "[$now] Slack CHANNEL (image): curl error: " . curl_error($chChan) . "\n", FILE_APPEND);
            } else {
                file_put_contents($logFile, "[$now] Slack CHANNEL (image): response HTTP $httpChan: $respChan\n", FILE_APPEND);
                $respDataChan = json_decode($respChan, true);
                if (!empty($respDataChan['ok'])) $chanStatus = 'canal_enviado';
                else $chanStatus = 'canal_falha_api: ' . ($respDataChan['error'] ?? 'desconhecido');
            }
            curl_close($chChan);
        } else {
            $chanStatus = 'token_indisponivel';
            file_put_contents($logFile, "[$now] Slack CHANNEL (image): token not available\n", FILE_APPEND);
        }
        // não enviar DM para decisões de imagem
        $dmStatus = null;
    }

    // Combina status do canal e do DM para retorno
    $slackStatus = trim(implode(';', array_filter([$chanStatus ?? null, $dmStatus ?? null])), ";");

    echo json_encode([
        'success' => true,
        'slack_status' => $slackStatus
    ]);
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
