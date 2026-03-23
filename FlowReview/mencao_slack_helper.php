<?php
/**
 * Shared Slack DM helper for mention notifications.
 * Functions are prefixed to avoid conflicts when other files also define
 * normalize_name / slack_post_message in the same request.
 */

require_once __DIR__ . '/../config/secure_env.php';
improov_load_env_once();

if (!function_exists('_mencao_normalize_name')) {
    function _mencao_normalize_name($s)
    {
        if (!$s) return '';
        $s = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        $s = strtolower((string)$s);
        $s = preg_replace('/[^a-z0-9\s]/', '', $s);
        $s = preg_replace('/\s+/', ' ', trim($s));
        return $s;
    }
}

if (!function_exists('_mencao_slack_post_message')) {
    function _mencao_slack_post_message($token, $channel, $text, &$logs)
    {
        if (!$token || !$channel) {
            $logs[] = 'slack_skip_missing_token_or_channel';
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
            $logs[] = 'slack_curl_error=' . curl_error($ch);
            curl_close($ch);
            return false;
        }
        curl_close($ch);

        $data = json_decode((string)$resp, true);
        if (!is_array($data) || empty($data['ok'])) {
            $logs[] = 'slack_not_ok=' . (is_array($data) ? ($data['error'] ?? 'unknown') : 'invalid_json');
            return false;
        }

        $logs[] = 'slack_ok';
        return true;
    }
}

if (!function_exists('_mencao_resolve_slack_user')) {
    function _mencao_resolve_slack_user($conn, $colaborador_id, $token, &$logs)
    {
        $nomeSlack = null;
        $nomeColab = null;

        if ($st = $conn->prepare('SELECT nome_slack FROM usuario WHERE idcolaborador = ? LIMIT 1')) {
            $st->bind_param('i', $colaborador_id);
            $st->execute();
            $st->bind_result($nomeSlack);
            $st->fetch();
            $st->close();
        }

        if ($st2 = $conn->prepare('SELECT nome_colaborador FROM colaborador WHERE idcolaborador = ? LIMIT 1')) {
            $st2->bind_param('i', $colaborador_id);
            $st2->execute();
            $st2->bind_result($nomeColab);
            $st2->fetch();
            $st2->close();
        }

        $nomeSlack = trim((string)$nomeSlack);
        if ($nomeSlack !== '' && preg_match('/^U[A-Z0-9]+$/', $nomeSlack)) {
            return $nomeSlack;
        }

        $target     = $nomeSlack !== '' ? $nomeSlack : (string)$nomeColab;
        $targetNorm = _mencao_normalize_name($target);
        if (!$token || $targetNorm === '') {
            return null;
        }

        $ch = curl_init('https://slack.com/api/users.list');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        if (curl_errno($ch)) {
            $logs[] = 'slack_users_list_curl_error=' . curl_error($ch);
            curl_close($ch);
            return null;
        }
        curl_close($ch);

        $data = json_decode((string)$resp, true);
        if (!is_array($data) || empty($data['ok']) || empty($data['members'])) {
            return null;
        }

        foreach ($data['members'] as $member) {
            if (!empty($member['deleted'])) continue;
            $realNorm    = _mencao_normalize_name($member['profile']['real_name'] ?? '');
            $displayNorm = _mencao_normalize_name($member['profile']['display_name'] ?? '');
            if ($realNorm === $targetNorm || $displayNorm === $targetNorm) {
                return $member['id'];
            }
        }

        return null;
    }
}

/**
 * Send a Slack DM to every mentioned collaborator.
 *
 * @param mysqli   $conn          Active DB connection
 * @param array    $mencionados   Array of idcolaborador integers
 * @param string   $nomeRemetente Display name of the person who wrote the comment/reply
 * @param string   $nomeFuncao    Name of the funcao (task type)
 * @param string   $nomeImagem    Image file name
 * @param string   $nomeObra      Obra name
 * @param int|null $remetente_id  idcolaborador of sender (skipped to avoid self-DM)
 * @return array   ['enviados' => int, 'ignorados' => int, 'logs' => string[]]
 */
function enviarSlackMencoes($conn, $mencionados, $nomeRemetente, $nomeFuncao, $nomeImagem, $nomeObra, $remetente_id = null): array
{
    if (empty($mencionados)) return ['enviados' => 0, 'ignorados' => 0, 'logs' => ['mencoes_vazias']];

    $token = getenv('SLACK_TOKEN') ?: null;
    if (!$token) return ['enviados' => 0, 'ignorados' => 0, 'logs' => ['sem_slack_token']];

    $link = 'https://improov.com.br/flow/ImproovWeb/FlowReview/index.php'
        . ($nomeObra ? '?obra_nome=' . rawurlencode($nomeObra) : '');

    $msg  = "📌 Você foi mencionado por *{$nomeRemetente}*";
    if ($nomeFuncao) $msg .= " na *{$nomeFuncao}*";
    if ($nomeImagem) $msg .= " da imagem _{$nomeImagem}_";
    if ($nomeObra)   $msg .= " (obra: {$nomeObra})";
    $msg .= ". Confira: {$link}";

    $logs     = [];
    $sent     = [];
    $enviados = 0;
    $ignorados = 0;
    foreach ($mencionados as $mid) {
        $mid = intval($mid);
        if (!$mid || in_array($mid, $sent, true)) { $ignorados++; continue; }

        $userId = _mencao_resolve_slack_user($conn, $mid, $token, $logs);
        if ($userId) {
            $ok = _mencao_slack_post_message($token, $userId, $msg, $logs);
            if ($ok) $enviados++;
        } else {
            $logs[] = "user_nao_encontrado_colab={$mid}";
        }
        $sent[] = $mid;
    }

    return ['enviados' => $enviados, 'ignorados' => $ignorados, 'logs' => $logs];
}
