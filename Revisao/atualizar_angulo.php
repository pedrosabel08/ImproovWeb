<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

include_once __DIR__ . '/../conexao.php';

// Slack helpers (optional)
$slackToken = getenv('SLACK_TOKEN') ?: null;
try {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        if (class_exists('Dotenv\\Dotenv')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
            $dotenv->safeLoad();
            $slackToken = $_ENV['SLACK_TOKEN'] ?? $slackToken;
        }
    }
} catch (Throwable $e) {
    // ignore slack init errors
}

function normalize_name($s)
{
    if (!$s) {
        return '';
    }
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    $s = strtolower((string)$s);
    $s = preg_replace('/[^a-z0-9\s]/', '', $s);
    $s = preg_replace('/\s+/', ' ', trim($s));
    return $s;
}

function slack_post_message($token, $channel, $text, &$logs)
{
    if (!$token) {
        $logs[] = 'slack_token_missing';
        return false;
    }
    if (!$channel) {
        $logs[] = 'slack_channel_missing';
        return false;
    }

    $payload = ['channel' => $channel, 'text' => $text];
    $ch = curl_init('https://slack.com/api/chat.postMessage');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $resp = curl_exec($ch);
    if (curl_errno($ch)) {
        $logs[] = 'slack_curl_error: ' . curl_error($ch);
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    $data = json_decode((string)$resp, true);
    if (!is_array($data) || empty($data['ok'])) {
        $logs[] = 'slack_not_ok: ' . (is_array($data) ? ($data['error'] ?? 'unknown') : 'invalid_json');
        return false;
    }

    $logs[] = 'slack_ok';
    return true;
}

function resolve_slack_user_id_by_colaborador($conn, $colaborador_id, $token, &$logs)
{
    $slack = null;
    $nome = null;

    if ($st = $conn->prepare('SELECT nome_slack FROM usuario WHERE idcolaborador = ? LIMIT 1')) {
        $st->bind_param('i', $colaborador_id);
        $st->execute();
        $st->bind_result($slack);
        $st->fetch();
        $st->close();
    }

    if ($st2 = $conn->prepare('SELECT nome_colaborador FROM colaborador WHERE idcolaborador = ? LIMIT 1')) {
        $st2->bind_param('i', $colaborador_id);
        $st2->execute();
        $st2->bind_result($nome);
        $st2->fetch();
        $st2->close();
    }

    $slack = trim((string)$slack);
    if ($slack !== '' && preg_match('/^U[A-Z0-9]+$/', $slack)) {
        $logs[] = 'slack_id_from_usuario=' . $slack;
        return $slack;
    }

    // Fallback: resolve via users.list using either usuario.nome_slack (as a name) or colaborador.nome_colaborador
    $target = $slack !== '' ? $slack : (string)$nome;
    $targetNorm = normalize_name($target);
    if (!$token || $targetNorm === '') {
        $logs[] = 'slack_resolve_skip: token_missing_or_empty_target';
        return null;
    }

    $ch = curl_init('https://slack.com/api/users.list');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    if (curl_errno($ch)) {
        $logs[] = 'slack_users_list_curl_error: ' . curl_error($ch);
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $data = json_decode((string)$resp, true);
    if (!is_array($data) || empty($data['ok']) || !is_array($data['members'] ?? null)) {
        $logs[] = 'slack_users_list_not_ok';
        return null;
    }

    foreach ($data['members'] as $m) {
        $candidates = [];
        if (!empty($m['real_name'])) $candidates[] = $m['real_name'];
        if (!empty($m['profile']['real_name_normalized'])) $candidates[] = $m['profile']['real_name_normalized'];
        if (!empty($m['profile']['display_name'])) $candidates[] = $m['profile']['display_name'];
        if (!empty($m['profile']['display_name_normalized'])) $candidates[] = $m['profile']['display_name_normalized'];

        foreach ($candidates as $c) {
            if (normalize_name($c) === $targetNorm) {
                $uid = $m['id'] ?? null;
                if ($uid) {
                    $logs[] = 'slack_id_from_users_list=' . $uid;
                    return $uid;
                }
            }
        }
    }

    $logs[] = 'slack_resolve_failed';
    return null;
}

$data = json_decode(file_get_contents('php://input'), true);

$imagem_id = isset($data['imagem_id']) ? intval($data['imagem_id']) : 0;
$funcao_imagem_id = isset($data['funcao_imagem_id']) ? intval($data['funcao_imagem_id']) : 0;
$historico_id = isset($data['historico_id']) ? intval($data['historico_id']) : 0;
$acao = isset($data['acao']) ? trim((string) $data['acao']) : '';
$motivo = isset($data['motivo']) ? trim((string) $data['motivo']) : '';

$debug = isset($_GET['debug']) && (string)$_GET['debug'] === '1';
$logs = [];
$logs[] = 'acao=' . $acao;

// Responsável (colaborador que está fazendo a ação)
$responsavel_idcolaborador = isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : 0;
if ($responsavel_idcolaborador <= 0 && isset($_SESSION['idusuario'])) {
    $idusuarioSess = (int)$_SESSION['idusuario'];
    if ($idusuarioSess > 0 && ($stResp = $conn->prepare('SELECT idcolaborador FROM usuario WHERE idusuario = ? LIMIT 1'))) {
        $stResp->bind_param('i', $idusuarioSess);
        $stResp->execute();
        $stResp->bind_result($tmpColab);
        if ($stResp->fetch()) {
            $responsavel_idcolaborador = (int)$tmpColab;
        }
        $stResp->close();
    }
}

if (!$imagem_id || !$funcao_imagem_id || !$historico_id || !in_array($acao, ['aprovar', 'ajuste'], true)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

// Confirma que historico pertence à função
$okHist = false;
if ($st = $conn->prepare('SELECT 1 FROM historico_aprovacoes_imagens WHERE id = ? AND funcao_imagem_id = ? LIMIT 1')) {
    $st->bind_param('ii', $historico_id, $funcao_imagem_id);
    $st->execute();
    $res = $st->get_result();
    $okHist = $res && $res->num_rows > 0;
    $st->close();
}
if (!$okHist) {
    echo json_encode([
        'success' => false,
        'message' => 'Imagem/Histórico inválido para esta função.',
        'debug' => [
            'imagem_id' => $imagem_id,
            'funcao_imagem_id' => $funcao_imagem_id,
            'historico_id' => $historico_id
        ]
    ]);
    exit;
}

// Confirma que é Finalização (funcao_id=4) e imagem está em P00
$funcao_id = null;
$status_nome = null;
$colaborador_id = null;
$imagem_nome = null;
$path_angulo = null;
$status_funcao_atual = null;

if (
    $st = $conn->prepare("SELECT f.funcao_id, f.colaborador_id, f.status, i.imagem_nome, s.nome_status
    FROM funcao_imagem f
    JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
    JOIN status_imagem s ON s.idstatus = i.status_id
    WHERE f.idfuncao_imagem = ? AND f.imagem_id = ?
    LIMIT 1")
) {
    $st->bind_param('ii', $funcao_imagem_id, $imagem_id);
    $st->execute();
    $st->bind_result($funcao_id, $colaborador_id, $status_funcao_atual, $imagem_nome, $status_nome);
    $st->fetch();
    $st->close();
}

$status_nome_norm = mb_strtolower(trim((string) $status_nome), 'UTF-8');
$isP00 = ($status_nome_norm === 'p00');

if ((int) $funcao_id !== 4 || !$isP00) {
    echo json_encode([
        'success' => false,
        'message' => 'Aprovação de ângulos disponível apenas para P00 + Finalização.',
        'debug' => [
            'funcao_id' => $funcao_id,
            'status_nome' => $status_nome
        ]
    ]);
    exit;
}

if ($st = $conn->prepare('SELECT imagem FROM historico_aprovacoes_imagens WHERE id = ? LIMIT 1')) {
    $st->bind_param('i', $historico_id);
    $st->execute();
    $st->bind_result($path_angulo);
    $st->fetch();
    $st->close();
}

// Garante existência do registro em angulos_imagens
if (
    $ins = $conn->prepare("INSERT IGNORE INTO angulos_imagens (imagem_id, historico_id, entrega_item_id, liberada, sugerida, motivo_sugerida)
    VALUES (?, ?, NULL, 0, 0, '')")
) {
    $ins->bind_param('ii', $imagem_id, $historico_id);
    $ins->execute();
    $ins->close();
}

if ($acao === 'aprovar') {
    if ($up = $conn->prepare("UPDATE angulos_imagens SET liberada = 1, sugerida = 0, motivo_sugerida = '' WHERE imagem_id = ? AND historico_id = ?")) {
        $up->bind_param('ii', $imagem_id, $historico_id);
        $ok = $up->execute();
        $up->close();

        // Se todos ângulos estiverem aprovados, notifica o finalizador
        if ($ok) {
            $total = 0;
            $aprovados = 0;
            $sql = "SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN ai.liberada = 1 AND ai.sugerida = 0 THEN 1 ELSE 0 END) AS aprovados
                    FROM angulos_imagens ai
                    JOIN historico_aprovacoes_imagens hi ON hi.id = ai.historico_id
                    WHERE ai.imagem_id = ? AND hi.funcao_imagem_id = ?";
            if ($stCnt = $conn->prepare($sql)) {
                $stCnt->bind_param('ii', $imagem_id, $funcao_imagem_id);
                $stCnt->execute();
                $row = $stCnt->get_result()->fetch_assoc();
                $total = (int)($row['total'] ?? 0);
                $aprovados = (int)($row['aprovados'] ?? 0);
                $stCnt->close();
            }
            $logs[] = "angulo_aprovado: {$aprovados}/{$total}";
            if ($total > 0 && $aprovados >= $total) {
                // Todos os ângulos aprovados => função vira Aprovado e registra histórico
                $conn->begin_transaction();
                try {
                    $logs[] = 'trx_begin_status_aprovado';

                    $statusAnterior = $status_funcao_atual;
                    if ($stStatus = $conn->prepare('SELECT status FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1 FOR UPDATE')) {
                        $stStatus->bind_param('i', $funcao_imagem_id);
                        $stStatus->execute();
                        $stStatus->bind_result($statusAnteriorDb);
                        if ($stStatus->fetch()) {
                            $statusAnterior = $statusAnteriorDb;
                        }
                        $stStatus->close();
                    }

                    $novoStatus = 'Aprovado';

                    // calcula quem será o responsável do histórico (sessão -> usuario -> fallback colaborador da função)
                    $respHist = $responsavel_idcolaborador > 0 ? $responsavel_idcolaborador : (int)$colaborador_id;
                    if ($respHist <= 0) {
                        throw new Exception('responsavel_idcolaborador_missing');
                    }

                    // Atualiza status somente se necessário
                    if ((string)$statusAnterior !== $novoStatus) {
                        if ($stFi = $conn->prepare('UPDATE funcao_imagem SET status = ? WHERE idfuncao_imagem = ?')) {
                            $stFi->bind_param('si', $novoStatus, $funcao_imagem_id);
                            if (!$stFi->execute()) {
                                throw new Exception('Erro ao atualizar funcao_imagem para Ajuste: ' . $stFi->error);
                            }
                            $stFi->close();
                            $logs[] = 'funcao_imagem: status=Ajuste ok';
                        } else {
                            throw new Exception('Erro prepare funcao_imagem: ' . $conn->error);
                        }
                    } else {
                        $logs[] = 'funcao_imagem: status já era Ajuste';
                    }

                    // Insere histórico sempre que ação 'ajuste' for executada (mesmo que status já fosse Ajuste)
                    if ($insHist = $conn->prepare('INSERT INTO historico_aprovacoes (funcao_imagem_id, status_anterior, status_novo, colaborador_id, responsavel) VALUES (?, ?, ?, ?, ?)')) {
                        $sa = $statusAnterior ?? '';
                        $colabHistInt = $colaborador_id ? (int)$colaborador_id : 0;
                        $insHist->bind_param('issii', $funcao_imagem_id, $sa, $novoStatus, $colabHistInt, $respHist);
                        if (!$insHist->execute()) {
                            throw new Exception('Erro ao inserir historico_aprovacoes (Ajuste): ' . $insHist->error);
                        }
                        $insHist->close();
                        $logs[] = "historico_aprovacoes: '{$sa}' => '{$novoStatus}'";
                    } else {
                        throw new Exception('Erro prepare historico_aprovacoes: ' . $conn->error);
                    }

                    $conn->commit();
                    $logs[] = 'trx_commit_status_aprovado';
                } catch (Exception $e) {
                    $conn->rollback();
                    $logs[] = 'trx_rollback_status_aprovado: ' . $e->getMessage();
                }

                $uid = resolve_slack_user_id_by_colaborador($conn, (int)$colaborador_id, $slackToken, $logs);
                $msg = "✅ Todos os ângulos da imagem {$imagem_nome} (P00) foram aprovados. Parabéns!";
                slack_post_message($slackToken, $uid, $msg, $logs);
            }
        }

        $resp = ['success' => (bool) $ok];
        if ($debug) $resp['debug'] = $logs;
        echo json_encode($resp);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Erro ao aprovar ângulo.']);
    exit;
}

// ajuste: apenas 1 ângulo sugerido por vez
if ($motivo === '') {
    echo json_encode(['success' => false, 'message' => 'Informe o motivo do ajuste.']);
    exit;
}

$conn->begin_transaction();
try {
    $logs[] = 'trx_begin';

    // Marca APENAS este ângulo como ajuste (permite múltiplos ângulos em ajuste)
    if ($up = $conn->prepare('UPDATE angulos_imagens SET liberada = 0, sugerida = 1, motivo_sugerida = ? WHERE imagem_id = ? AND historico_id = ?')) {
        $up->bind_param('sii', $motivo, $imagem_id, $historico_id);
        if (!$up->execute()) {
            throw new Exception('Erro ao atualizar angulos_imagens: ' . $up->error);
        }
        $up->close();
        $logs[] = 'angulos_imagens: set sugerida ok';
    } else {
        throw new Exception('Erro prepare update: ' . $conn->error);
    }

    // Atualiza a função para Ajuste (Finalização) + histórico
    $statusAnterior = $status_funcao_atual;
    if ($stStatus = $conn->prepare('SELECT status FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1 FOR UPDATE')) {
        $stStatus->bind_param('i', $funcao_imagem_id);
        $stStatus->execute();
        $stStatus->bind_result($statusAnteriorDb);
        if ($stStatus->fetch()) {
            $statusAnterior = $statusAnteriorDb;
        }
        $stStatus->close();
    }

    $novoStatus = 'Ajuste';
    if ((string)$statusAnterior !== $novoStatus) {
        if ($stFi = $conn->prepare('UPDATE funcao_imagem SET status = ? WHERE idfuncao_imagem = ?')) {
            $stFi->bind_param('si', $novoStatus, $funcao_imagem_id);
            if (!$stFi->execute()) {
                throw new Exception('Erro ao atualizar funcao_imagem para Ajuste: ' . $stFi->error);
            }
            $stFi->close();
            $logs[] = 'funcao_imagem: status=Ajuste ok';
        } else {
            throw new Exception('Erro prepare funcao_imagem: ' . $conn->error);
        }

        $respHist = $responsavel_idcolaborador > 0 ? $responsavel_idcolaborador : (int)$colaborador_id;
        if ($respHist <= 0) {
            throw new Exception('responsavel_idcolaborador_missing');
        }

        if ($insHist = $conn->prepare('INSERT INTO historico_aprovacoes (funcao_imagem_id, status_anterior, status_novo, colaborador_id, responsavel) VALUES (?, ?, ?, ?, ?)')) {
            $sa = $statusAnterior ?? '';
            $colabHistInt = $colaborador_id ? (int)$colaborador_id : 0;
            $insHist->bind_param('issii', $funcao_imagem_id, $sa, $novoStatus, $colabHistInt, $respHist);
            if (!$insHist->execute()) {
                throw new Exception('Erro ao inserir historico_aprovacoes (Ajuste): ' . $insHist->error);
            }
            $insHist->close();
            $logs[] = "historico_aprovacoes: '{$sa}' => '{$novoStatus}'";
        } else {
            throw new Exception('Erro prepare historico_aprovacoes: ' . $conn->error);
        }
    } else {
        $logs[] = 'funcao_imagem: status já era Ajuste';
    }

    $conn->commit();
    $logs[] = 'trx_commit';

    // Conta quantos ângulos estão em ajuste e notifica o finalizador no Slack
    $qtdAjuste = 0;
    $sqlCnt = "SELECT COUNT(*) AS qtd
              FROM angulos_imagens ai
              JOIN historico_aprovacoes_imagens hi ON hi.id = ai.historico_id
              WHERE ai.imagem_id = ? AND hi.funcao_imagem_id = ? AND ai.sugerida = 1";
    if ($stCnt = $conn->prepare($sqlCnt)) {
        $stCnt->bind_param('ii', $imagem_id, $funcao_imagem_id);
        $stCnt->execute();
        $row = $stCnt->get_result()->fetch_assoc();
        $qtdAjuste = (int)($row['qtd'] ?? 0);
        $stCnt->close();
    }
    $logs[] = 'qtd_ajuste=' . $qtdAjuste;

    $uid = null;
    if ($colaborador_id) {
        $uid = resolve_slack_user_id_by_colaborador($conn, (int)$colaborador_id, $slackToken, $logs);
    }

    if ($uid) {
        if ($qtdAjuste <= 1) {
            $msgSlack = "⚠️ Foi solicitado ajuste em 1 dos ângulos da imagem {$imagem_nome} (P00). Favor verificar.";
        } else {
            $msgSlack = "⚠️ Foram solicitados ajustes em {$qtdAjuste} ângulos da imagem {$imagem_nome} (P00). Favor verificar.";
        }
        slack_post_message($slackToken, $uid, $msgSlack, $logs);
    }

    $resp = ['success' => true];
    if ($debug) $resp['debug'] = $logs;
    echo json_encode($resp);
} catch (Exception $e) {
    $conn->rollback();
    $logs[] = 'trx_rollback: ' . $e->getMessage();
    $resp = ['success' => false, 'message' => 'Erro ao solicitar ajuste.'];
    if ($debug) $resp['debug'] = $logs;
    echo json_encode($resp);
}

$conn->close();
