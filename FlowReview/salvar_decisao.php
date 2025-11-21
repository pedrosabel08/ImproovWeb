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

        // Recupera informações do ângulo para notificação e mover arquivo
        $stmtAng = $conn->prepare("SELECT ai.*, hi.nome_arquivo AS nome_arquivo, i.imagem_nome, i.tipo_imagem, i.idimagens_cliente_obra AS imagem_id, o.nomenclatura, hi.indice_envio
                                    FROM angulos_imagens ai
                                    LEFT JOIN historico_aprovacoes_imagens hi ON hi.id = ai.historico_id
                                    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = ai.imagem_id
                                    LEFT JOIN obra o ON o.idobra = i.obra_id
                                    WHERE ai.id = ? LIMIT 1");
        if ($stmtAng) {
            $stmtAng->bind_param('i', $angulo_id);
            if ($stmtAng->execute()) {
                $resAng = $stmtAng->get_result();
                if ($rowAng = $resAng->fetch_assoc()) {
                    $arquivoAng = $rowAng['nome_arquivo'] ?? null;
                    $imagemNome = $rowAng['imagem_nome'] ?? null;
                    $tipoImagem = $rowAng['tipo_imagem'] ?? null;
                    $nomenclaturaObra = $rowAng['nomenclatura'] ?? null;
                    $indiceEnvioAng = $rowAng['indice_envio'] ?? 1;

                    // NOVO: Buscar arquivo via FTP servidor do sistema (/www/sistema/uploads)
                    $extensionsToTry = ['jpg','jpeg','png','gif','webp'];
                    $basename = $arquivoAng ?? '';
                    $rawExt = pathinfo($basename, PATHINFO_EXTENSION);
                    $validExt = $rawExt !== '' && in_array(strtolower($rawExt), $extensionsToTry, true);
                    $hasExt = $validExt;
                    $ftp_host = 'ftp.improov.com.br';
                    $ftp_port = 21;
                    $ftp_user = 'improov';
                    $ftp_pass = 'Impr00v';
                    $ftp_base_dir = '/www/sistema/uploads/';
                    $localFound = null;
                    $discLog = __DIR__ . '/logs/sftp_upload.log';
                    $discNow = date('Y-m-d H:i:s');
                    if (!is_dir(dirname($discLog))) @mkdir(dirname($discLog), 0755, true);
                    if ($basename !== '') {
                        $ftp_conn = @ftp_connect($ftp_host, $ftp_port, 10);
                        if ($ftp_conn && @ftp_login($ftp_conn, $ftp_user, $ftp_pass)) {
                            @ftp_pasv($ftp_conn, true);
                            $candidatePaths = [];
                            if ($hasExt) {
                                $candidatePaths[] = $ftp_base_dir . $basename;
                            } else {
                                foreach ($extensionsToTry as $eTry) {
                                    $candidatePaths[] = $ftp_base_dir . $basename . '.' . $eTry;
                                }
                            }
                            $candidatePaths[] = $ftp_base_dir . $basename; // sempre tenta sem extensão
                            foreach ($candidatePaths as $remote) {
                                $tmpLocal = sys_get_temp_dir() . '/improov_' . uniqid() . '_' . basename($remote);
                                if (@ftp_get($ftp_conn, $tmpLocal, $remote, FTP_BINARY)) {
                                    if (filesize($tmpLocal) > 200) {
                                        $localFound = $tmpLocal;
                                        $rawExt = pathinfo($tmpLocal, PATHINFO_EXTENSION);
                                        break;
                                    } else { @unlink($tmpLocal); }
                                } else { @unlink($tmpLocal); }
                            }
                            file_put_contents($discLog, "[$discNow] FTP discovery basename='$basename' paths=" . json_encode($candidatePaths) . " found=" . ($localFound ?: 'NONE') . "\n", FILE_APPEND);
                        } else {
                            file_put_contents($discLog, "[$discNow] FTP connect/login FAILED '$ftp_host'\n", FILE_APPEND);
                        }
                        if ($ftp_conn) { @ftp_close($ftp_conn); }
                    }

                    // Descobrir extensão real
                    $foundExt = '';
                    if ($localFound) {
                        $foundExt = pathinfo($localFound, PATHINFO_EXTENSION);
                        if ($foundExt) $foundExt = '.' . $foundExt;
                    }

                    // Montar caminho destino (ex: /2025/MEN_991/05.Exchange/01.Input/Angulo_definido/Imagem Interna/IMG/5.MEN_991 Hall de entrada.jpg)
                    $year = date('Y');
                    $safeImagemNome = preg_replace('/[^A-Za-z0-9 _.-]/', '', ($indiceEnvioAng . '.' . ($nomenclaturaObra ? $nomenclaturaObra : '') . ' ' . ($imagemNome ?? '')));
                    $safeImagemNome = trim(preg_replace('/\s+/', ' ', $safeImagemNome));
                    // If we discovered an extension from the found local file, append it to the remote filename
                    if (!empty($foundExt) && pathinfo($safeImagemNome, PATHINFO_EXTENSION) === '') {
                        $safeImagemNome .= $foundExt;
                    }
                    // Caminho no NAS (/mnt/clientes/<ano>/<obra>/...)
                    $remoteSub = "/mnt/clientes/$year/" . ($nomenclaturaObra ?: 'UNKNOWN') . "/05.Exchange/01.Input/Angulo_definido/" . ($tipoImagem ?: 'IMG') . "/IMG/";

                    // Usar SFTP conforme Arquivos/upload.php (fazer upload para NAS)
                    try {
                        // credenciais SFTP padrão (copiadas de Arquivos/upload.php). Ajuste se necessário.
                        $host = "imp-nas.ddns.net";
                        $port = 2222;
                        $username = "flow";
                        $password = "flow@2025";
                        $sftp = new \phpseclib3\Net\SFTP($host, $port);

                        // prepare sftp log
                        $sftpLog = __DIR__ . '/logs/sftp_upload.log';
                        if (!is_dir(dirname($sftpLog))) @mkdir(dirname($sftpLog), 0755, true);
                        $sftpNow = date('Y-m-d H:i:s');

                        if (!$sftp->login($username, $password)) {
                            file_put_contents($sftpLog, "[$sftpNow] SFTP: login failed for $username@$host:$port\n", FILE_APPEND);
                        } elseif ($localFound) {
                            // Upload direto para remoteSub (já absoluto)
                            $remoteFullDir = trim($remoteSub, '/');

                            // Garante criação recursiva dos diretórios
                            $segments = explode('/', trim($remoteFullDir, '/'));
                            $buildPath = '';
                            foreach ($segments as $seg) {
                                if ($seg === '') continue;
                                $buildPath .= '/' . $seg;
                                if (!$sftp->is_dir($buildPath)) {
                                    if ($sftp->mkdir($buildPath)) {
                                        file_put_contents($sftpLog, "[$sftpNow] SFTP: mkdir $buildPath\n", FILE_APPEND);
                                    } else {
                                        file_put_contents($sftpLog, "[$sftpNow] SFTP: mkdir FAILED $buildPath\n", FILE_APPEND);
                                    }
                                }
                            }
                            $remotePath = '/' . trim($remoteFullDir, '/') . '/' . $safeImagemNome;
                            $remotePath = '/' . preg_replace('#/+#','/', $remotePath);
                            file_put_contents($sftpLog, "[$sftpNow] SFTP: ready upload local='$localFound' => remote='$remotePath'\n", FILE_APPEND);
                            $okPut = $sftp->put($remotePath, $localFound);
                            if ($okPut) {
                                file_put_contents($sftpLog, "[$sftpNow] SFTP: uploaded $localFound => $remotePath\n", FILE_APPEND);
                                // cleanup temp if we downloaded it to sys temp dir
                                $sysTmp = sys_get_temp_dir();
                                if (stripos($localFound, $sysTmp) === 0) {
                                    @unlink($localFound);
                                    file_put_contents($sftpLog, "[$sftpNow] SFTP: removed temp file $localFound\n", FILE_APPEND);
                                }
                            } else {
                                file_put_contents($sftpLog, "[$sftpNow] SFTP: put failed for $localFound => $remotePath\n", FILE_APPEND);
                            }
                        } else {
                            file_put_contents($sftpLog, "[$sftpNow] SFTP: no local file found to upload after enhanced discovery (basename='$basename')\n", FILE_APPEND);
                        }
                    } catch (Exception $e) {
                        // falha em mover arquivo — não interrompe fluxo principal; log para diagnóstico
                        $sftpNow = date('Y-m-d H:i:s');
                        $sftpLog = $sftpLog ?? (__DIR__ . '/logs/sftp_upload.log');
                        if (!is_dir(dirname($sftpLog))) @mkdir(dirname($sftpLog), 0755, true);
                        file_put_contents($sftpLog, "[$sftpNow] SFTP exception: " . $e->getMessage() . "\n", FILE_APPEND);
                    }

                    // (Notificações para colaborador removidas — apenas envio para canal será feito)
                }
            }
            $stmtAng->close();
        }
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
    $textoSlack = "Decisão registrada:\n";
    $textoSlack .= "• Imagem: #" . ($imagemId ?? 'N/D') . " - " . ($imagemNome ?? 'sem nome') . "\n";
    if ($data_entregue) {
        $dataEnvioFmt = date('d/m/Y H:i', strtotime($data_entregue));
        $textoSlack .= "• Data envio versão: $dataEnvioFmt\n";
    }
    $textoSlack .= "• Decisão: $decisaoLabel\n";
    $textoSlack .= "• Responsável: " . ($usuarioNome ?? 'Usuário') . "\n";
    $textoSlack .= "• Registrado em: $hora";

    // Envia para Slack se token disponível — com logs para diagnóstico
    $slackStatus = null;
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $logFile = $logDir . '/slack_notify.log';
    $now = date('Y-m-d H:i:s');
    if ($slackToken) {
        $payload = [
            'channel' => $slackChannel,
            'text' => $textoSlack,
        ];
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        file_put_contents($logFile, "[$now] Slack: sending payload: $payloadJson\n", FILE_APPEND);

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
            $slackStatus = 'erro_curl: ' . $curlErr;
            file_put_contents($logFile, "[$now] Slack: curl error: $curlErr\n", FILE_APPEND);
        } else {
            file_put_contents($logFile, "[$now] Slack: response HTTP $httpCode: $resp\n", FILE_APPEND);
            $respData = json_decode($resp, true);
            if (isset($respData['ok']) && $respData['ok']) {
                $slackStatus = 'enviado';
            } else {
                $err = $respData['error'] ?? 'desconhecido';
                $slackStatus = 'falha_api: ' . $err;
                file_put_contents($logFile, "[$now] Slack: api error: $err\n", FILE_APPEND);
            }
        }
        curl_close($ch);
    } else {
        $slackStatus = 'token_indisponivel';
        file_put_contents($logFile, "[$now] Slack: token not available\n", FILE_APPEND);
    }

    echo json_encode([
        'success' => true,
        'slack_status' => $slackStatus
    ]);
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
