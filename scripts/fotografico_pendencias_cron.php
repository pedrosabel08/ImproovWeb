<?php

declare(strict_types=1);

// Agendar a cada 5 minutos no mesmo agendador que executa os demais crons PHP.
// A reserva única por pendência/responsável/prazo torna reexecuções seguras.

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../Fotografico/fotografico_service.php';

$conn = conectarBanco();
$conn->set_charset('utf8mb4');

function foto_cobranca_log(string $message): void
{
    error_log('[Fotografico/Cobranca] ' . $message);
}

function foto_cobranca_slack_user_id(mysqli $conn, int $colaboradorId, string $token): ?string
{
    $stmt = $conn->prepare('SELECT nome_slack, nome_usuario FROM usuario WHERE idcolaborador = ? AND ativo = 1 LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $colaboradorId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $identifier = trim((string) ($user['nome_slack'] ?? ''));
    if ($identifier === '') {
        foto_cobranca_log("colaborador={$colaboradorId} sem nome_slack configurado");
        return null;
    }
    if (preg_match('/^[UW][A-Z0-9]+$/', $identifier) === 1) {
        return $identifier;
    }
    if (!function_exists('curl_init')) {
        foto_cobranca_log("curl indisponível para resolver Slack de colaborador={$colaboradorId}");
        return null;
    }
    $ch = curl_init('https://slack.com/api/users.list?limit=200');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT_MS => 800,
        CURLOPT_TIMEOUT_MS => 2500,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    if ($http >= 400 || empty($payload['ok'])) {
        foto_cobranca_log("não foi possível resolver Slack de colaborador={$colaboradorId}");
        return null;
    }
    foreach ((array) ($payload['members'] ?? []) as $member) {
        $display = (string) ($member['profile']['display_name'] ?? '');
        $real = (string) ($member['profile']['real_name'] ?? '');
        if (mb_strtolower($identifier) === mb_strtolower($display) || mb_strtolower($identifier) === mb_strtolower($real)) {
            return (string) ($member['id'] ?? '') ?: null;
        }
    }
    foto_cobranca_log("nome_slack não resolvido para colaborador={$colaboradorId}");
    return null;
}

function foto_cobranca_enviar_dm(string $token, string $slackUserId, string $text): bool
{
    if (!function_exists('curl_init')) {
        foto_cobranca_log('curl indisponível para envio Slack');
        return false;
    }
    $ch = curl_init('https://slack.com/api/chat.postMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['channel' => $slackUserId, 'text' => $text], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT_MS => 800,
        CURLOPT_TIMEOUT_MS => 2500,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    if ($http >= 400 || empty($payload['ok'])) {
        foto_cobranca_log('falha Slack: http=' . $http . ' erro=' . $error);
        return false;
    }
    return true;
}

$token = (string) (getenv('SLACK_TOKEN') ?: '');
if ($token === '') {
    foto_cobranca_log('SLACK_TOKEN ausente; nenhuma cobrança enviada');
    exit(0);
}

$sql = "SELECT pe.id, pe.plano_id, pe.codigo, pe.titulo, pe.detalhes, pe.criado_em, pe.proxima_cobranca_em,
               pe.responsavel_id, pe.responsavel_cobranca_id, p.campanha_numero,
               COALESCE(o.nomenclatura, o.nome_obra) AS obra_nome,
               r.nome_colaborador AS responsavel_nome
          FROM fotografico_pendencia pe
          JOIN fotografico_plano p ON p.id = pe.plano_id
          JOIN obra o ON o.idobra = p.obra_id
     LEFT JOIN colaborador r ON r.idcolaborador = pe.responsavel_id
         WHERE pe.status = 'ABERTA'
           AND (pe.responsavel_id IS NOT NULL OR pe.responsavel_cobranca_id IS NOT NULL)
           AND pe.proxima_cobranca_em IS NOT NULL
           AND pe.proxima_cobranca_em <= NOW()
         ORDER BY pe.proxima_cobranca_em, pe.id
         LIMIT 100";
$result = $conn->query($sql);
if (!$result) {
    foto_cobranca_log('migration de pendências ainda não aplicada: ' . $conn->error);
    exit(0);
}

while ($row = $result->fetch_assoc()) {
    $pendingId = (int) $row['id'];
    // A mesma cobranca precisa chegar a quem resolve, a quem acompanha e ao
    // Pedro. A chave unica da tabela de envios evita DMs duplicadas se um
    // mesmo colaborador ocupar mais de um desses papeis.
    $recipientIds = array_values(array_unique(array_filter([
        (int) ($row['responsavel_id'] ?? 0),
        (int) ($row['responsavel_cobranca_id'] ?? 0),
        FOTOGRAFICO_RESPONSAVEL_ACOMPANHAMENTO_ID,
    ], static fn(int $id): bool => $id > 0)));
    $reference = (string) $row['proxima_cobranca_em'];
    foreach ($recipientIds as $chargeId) {
    // Reserva antes do HTTP: execuções concorrentes do cron não duplicam o DM.
    $reserve = $conn->prepare("INSERT IGNORE INTO fotografico_pendencia_cobranca_envio (pendencia_id, responsavel_cobranca_id, referencia_cobranca_em, status) VALUES (?, ?, ?, 'RESERVADA')");
    if (!$reserve) {
        foto_cobranca_log('migration de cobranças ainda não aplicada: ' . $conn->error);
        break;
    }
    $reserve->bind_param('iis', $pendingId, $chargeId, $reference);
    $reserve->execute();
    $reserved = $reserve->affected_rows === 1;
    $reserve->close();
    if (!$reserved) {
        continue;
    }

    $slackUserId = foto_cobranca_slack_user_id($conn, $chargeId, $token);
    $url = 'https://improov/ImproovWeb/Fotografico/index.php?plano_id=' . (int) $row['plano_id'] . '&pendencia_id=' . $pendingId;
    $openedAt = new DateTimeImmutable((string) $row['criado_em'], new DateTimeZone('America/Sao_Paulo'));
    $elapsed = $openedAt->diff(new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')));
    $elapsedText = $elapsed->days . ' dia(s), ' . $elapsed->h . 'h';
    $message = "*Cobrança · Fotográfico*\n"
        . "Obra: *" . $row['obra_nome'] . "*\n"
        . "Plano: *Campanha " . (int) $row['campanha_numero'] . "*\n"
        . "Tipo: " . $row['codigo'] . "\n"
        . "Pendência: " . $row['titulo'] . "\n"
        . "Descrição: " . ($row['detalhes'] ?: 'Sem detalhes adicionais.') . "\n"
        . "Responsável pela resolução: " . ($row['responsavel_nome'] ?: 'Não definido') . "\n"
        . "Tempo em aberto: " . $elapsedText . "\n"
        . "Cobrança prevista: " . $reference . "\n"
        . "Analisar: " . $url;
    $success = $slackUserId !== null && foto_cobranca_enviar_dm($token, $slackUserId, $message);
    $status = $success ? 'ENVIADA' : 'ERRO';
    $error = $success ? null : 'Slack indisponível ou usuário sem configuração válida.';
    $update = $conn->prepare("UPDATE fotografico_pendencia_cobranca_envio SET status = ?, enviado_em = NOW(), erro = ? WHERE pendencia_id = ? AND responsavel_cobranca_id = ? AND referencia_cobranca_em = ?");
    if ($update) {
        $update->bind_param('ssiis', $status, $error, $pendingId, $chargeId, $reference);
        $update->execute();
        $update->close();
    }
    $updatePending = $conn->prepare('UPDATE fotografico_pendencia SET ultima_cobranca_em = NOW(), erro_cobranca_em = CASE WHEN ? = \'ERRO\' THEN NOW() ELSE NULL END WHERE id = ?');
    if ($updatePending) {
        $updatePending->bind_param('si', $status, $pendingId);
        $updatePending->execute();
        $updatePending->close();
    }
    }
}

$result->free();
$conn->close();
