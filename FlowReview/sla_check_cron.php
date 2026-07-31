<?php
/**
 * sla_check_cron.php
 *
 * Checks which tasks in "Em aprovação" have exceeded their SLA limit
 * and sends a Slack notification to all approvers.
 *
 * Intended to be called by a scheduled task (cron job / Windows Task Scheduler)
 * every hour.
 *
 * Security: restrict to CLI execution only.
 * To call via HTTPS add CRON_SECRET to .env and pass as query param ?token=...
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

if (php_sapi_name() !== 'cli') {
    // Allow HTTP calls only if CRON_SECRET is configured and matches
    require_once __DIR__ . '/../config/secure_env.php';

    $cronSecret    = getenv('CRON_SECRET') ?: ($_ENV['CRON_SECRET'] ?? null);
    $providedToken = $_SERVER['HTTP_X_CRON_TOKEN'] ?? ($_GET['token'] ?? null);

    if (!$cronSecret || !hash_equals((string)$cronSecret, (string)$providedToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');

// Load credentials from env (populated by secure_env.php or .env)
$slackToken = getenv('SLACK_TOKEN') ?: ($_ENV['SLACK_TOKEN'] ?? null);

require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../FlowConnect/bootstrap.php';
$conn = conectarBanco();

$log = [];

// ── 1. Find breached tasks ──────────────────────────────────────────────────
$sqlBreach = "
    SELECT
        fi.idfuncao_imagem,
        fi.funcao_id,
        fi.imagem_id,
        fun.nome_funcao,
        i.imagem_nome,
        o.nome_obra,
        o.nomenclatura,
        o.idobra AS obra_id,
        sf.limite_horas,
        TIMESTAMPDIFF(HOUR, ha.data_aprovacao, NOW()) AS horas_em_aprovacao,
        ha.data_aprovacao AS sla_inicio
    FROM funcao_imagem fi
    JOIN sla_funcao sf ON sf.funcao_id = fi.funcao_id
    LEFT JOIN funcao fun ON fun.idfuncao = fi.funcao_id
    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
    LEFT JOIN obra o ON o.idobra = i.obra_id
    JOIN (
        SELECT funcao_imagem_id, MAX(id) AS max_id
        FROM historico_aprovacoes
        WHERE status_novo = 'Em aprovação'
        GROUP BY funcao_imagem_id
    ) ha_latest ON ha_latest.funcao_imagem_id = fi.idfuncao_imagem
    JOIN historico_aprovacoes ha ON ha.id = ha_latest.max_id
    WHERE fi.status = 'Em aprovação'
      AND o.status_obra = 0
      AND TIMESTAMPDIFF(HOUR, ha.data_aprovacao, NOW()) >= sf.limite_horas
    ORDER BY horas_em_aprovacao DESC
";

$result = $conn->query($sqlBreach);
if (!$result) {
    $log[] = 'Erro na query de breach: ' . $conn->error;
    echo json_encode(['success' => false, 'log' => $log]);
    exit;
}

$breachedTasks = [];
while ($row = $result->fetch_assoc()) {
    $breachedTasks[] = $row;
}

if (empty($breachedTasks)) {
    $log[] = 'Nenhuma tarefa em breach de SLA no momento.';
    echo json_encode(['success' => true, 'breached' => 0, 'log' => $log]);
    $conn->close();
    exit;
}

$log[] = count($breachedTasks) . ' tarefa(s) em breach de SLA.';

// ── 2. Get approver Slack IDs (users 1, 2, 9) ───────────────────────────────
$sqlSlack = "
    SELECT u.nome_slack
    FROM usuario u
    WHERE u.idusuario IN (1)
      AND u.nome_slack IS NOT NULL
      AND u.nome_slack != ''
";
$resSlack = $conn->query($sqlSlack);
$approverSlackIds = [];
if ($resSlack) {
    while ($r = $resSlack->fetch_assoc()) {
        $approverSlackIds[] = $r['nome_slack'];
    }
}

if (empty($approverSlackIds)) {
    $log[] = 'Nenhum aprovador com Slack configurado.';
}

// ── 3. Send Slack notifications and record them ─────────────────────────────

/**
 * Resolves real_name → Slack member ID via users.list.
 * Returns null on failure.
 */
function resolverSlackUserId(string $realName, string $token): ?string
{
    $ch = curl_init('https://slack.com/api/users.list');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $res   = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno || !$res) {
        return null;
    }

    $data   = json_decode((string)$res, true);
    if (empty($data['ok'])) {
        return null;
    }

    $needle = mb_strtolower(trim($realName));
    foreach ($data['members'] ?? [] as $member) {
        $rn = mb_strtolower(trim($member['real_name'] ?? ''));
        if ($rn !== '' && $rn === $needle) {
            return $member['id'] ?? null;
        }
    }

    return null;
}

function enviarSlack(string $channel, string $mensagem, string $token): array
{
    $ch = curl_init('https://slack.com/api/chat.postMessage');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'channel' => $channel,
        'text'    => $mensagem,
    ]));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $res   = curl_exec($ch);
    $errno = curl_errno($ch);
    $cerr  = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        return ['ok' => false, 'error' => 'curl: ' . $cerr];
    }

    $data = json_decode((string)$res, true);
    return $data ?? ['ok' => false, 'error' => 'empty response'];
}

// Cache legado inicializado apenas se algum evento não estiver em active.
$slackIdCache = [];
$slackCacheReady = false;

$notificados = 0;
foreach ($breachedTasks as $task) {
    $horas      = (int)$task['horas_em_aprovacao'];
    $limite     = (int)$task['limite_horas'];
    $nomeFuncao = $task['nome_funcao'];
    $imagemNome = $task['imagem_nome'];
    $nomeObra   = $task['nomenclatura'] ?: $task['nome_obra'];

    $mensagem = "🚨 *SLA de aprovação excedido* — {$nomeFuncao} | _{$imagemNome}_ ({$nomeObra})"
              . "\n> Há *{$horas}h* em aprovação (limite: {$limite}h). Por favor, revise a tarefa no FlowReview.";

    $flowConnectSlaEvent = \FlowConnect\Application\FlowReviewEventFactory::slaExceeded([
        'funcao_imagem_id' => (int)$task['idfuncao_imagem'],
        'funcao_id' => (int)$task['funcao_id'],
        'imagem_id' => (int)$task['imagem_id'],
        'obra_id' => (int)$task['obra_id'],
        'funcao_nome' => (string)$nomeFuncao,
        'imagem_nome' => (string)$imagemNome,
        'obra_nome' => (string)$nomeObra,
        'tempo_em_aprovacao' => $horas,
        'limite_sla' => $limite,
        'nivel' => 1,
        'janela_referencia' => gmdate('Y-m-d'),
        'flow_review_url' => flow_connect_config()['flow_review']['url'],
        'producer' => 'FlowReview/sla_check_cron.php',
    ]);
    $flowConnectSlaEventId = flow_connect_publish_if_enabled($conn, 'sla', $flowConnectSlaEvent, $log);
    if (flow_connect_should_bypass_legacy('sla', $flowConnectSlaEventId)) {
        $log[] = "Slack SLA legado ignorado pelo Flow Connect event={$flowConnectSlaEventId}.";
        $notificados++;
        continue;
    }

    if (!$slackCacheReady && $slackToken) {
        foreach ($approverSlackIds as $realName) {
            $memberId = resolverSlackUserId($realName, $slackToken);
            $slackIdCache[$realName] = $memberId;
            $log[] = 'Lookup "' . $realName . '" → ' . ($memberId ?? 'NÃO ENCONTRADO');
        }
        $slackCacheReady = true;
    }

    foreach ($approverSlackIds as $realName) {
        $memberId = $slackIdCache[$realName] ?? null;
        if (!$memberId) {
            $log[] = "⚠ Sem member ID para \"{$realName}\" — mensagem não enviada.";
            continue;
        }
        if ($slackToken) {
            $resp = enviarSlack($memberId, $mensagem, $slackToken);
            $ok   = !empty($resp['ok']);
            $log[] = ($ok ? '✓' : '✗') . " Slack → {$realName} ({$memberId}): {$nomeFuncao} / {$imagemNome}"
                   . ($ok ? '' : ' — erro: ' . ($resp['error'] ?? 'desconhecido'));
        } else {
            $log[] = "Slack token ausente — mensagem não enviada para {$realName}.";
        }
    }

    $notificados++;
}

$log[] = "{$notificados} notificação(ões) registrada(s).";

echo json_encode([
    'success'  => true,
    'breached' => count($breachedTasks),
    'notified' => $notificados,
    'log'      => $log,
], JSON_UNESCAPED_UNICODE);

$conn->close();
