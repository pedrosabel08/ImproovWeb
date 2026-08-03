<?php

declare(strict_types=1);

namespace FlowConnect\Channels;

final class SlackApiAdapter implements ChannelAdapter
{
    public function __construct(private array $config) {}

    public function send(array $delivery): array
    {
        if (($delivery['destination_kind'] ?? '') === 'WEBHOOK') {
            return $this->sendWebhook($delivery);
        }
        $tokenKey = (string) ($this->config['token_env'] ?? 'SLACK_TOKEN');
        $token = getenv($tokenKey);
        if ($token === false || trim((string) $token) === '') {
            return $this->failure('missing_token', 'Slack token ausente.', true, null);
        }
        $channel = trim((string) ($delivery['slack_user_id'] ?? ''));
        if ($channel === '') {
            return $this->failure('missing_destination', 'Destino Slack não resolvido.', true, null);
        }

        $payload = ['channel' => $channel, 'text' => (string) ($delivery['rendered_text'] ?? '')];
        if (!empty($delivery['rendered_blocks']) && is_array($delivery['rendered_blocks'])) {
            $payload['blocks'] = $delivery['rendered_blocks'];
        }
        $headers = [];
        $url = rtrim((string) ($this->config['api_base_url'] ?? 'https://slack.com/api'), '/') . '/chat.postMessage';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => (int) ($this->config['timeout_seconds'] ?? 10),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$headers): int {
                $pos = strpos($header, ':');
                if ($pos !== false) $headers[strtolower(trim(substr($header, 0, $pos)))] = trim(substr($header, $pos + 1));
                return strlen($header);
            },
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return $this->failure('transport_error', substr($curlError, 0, 180), false, $httpStatus ?: null);
        }
        $data = json_decode((string) $raw, true);
        if ($httpStatus === 429) {
            $result = $this->failure('rate_limited', 'Slack rate limit.', false, 429);
            $result['retry_after'] = max(1, (int) ($headers['retry-after'] ?? 60));
            return $result;
        }
        if (!is_array($data) || empty($data['ok'])) {
            $code = is_array($data) ? (string) ($data['error'] ?? 'invalid_response') : 'invalid_response';
            $permanent = in_array($code, ['invalid_auth', 'account_inactive', 'channel_not_found', 'not_in_channel', 'is_archived', 'msg_too_long'], true);
            return $this->failure($code, 'Slack recusou a entrega: ' . $code, $permanent, $httpStatus ?: null);
        }

        return [
            'ok' => true,
            'http_status' => $httpStatus ?: 200,
            'provider_message_id' => isset($data['ts']) ? (string) $data['ts'] : null,
            'error_code' => null,
            'safe_error' => null,
            'permanent' => false,
        ];
    }

    private function failure(string $code, string $safeError, bool $permanent, ?int $httpStatus): array
    {
        return ['ok' => false, 'http_status' => $httpStatus, 'provider_message_id' => null, 'error_code' => $code, 'safe_error' => $safeError, 'permanent' => $permanent];
    }

    private function sendWebhook(array $delivery): array
    {
        $envKey = self::webhookEnvKey($delivery);
        $url = $envKey === '' ? '' : trim((string) getenv($envKey));
        if ($url === '') return $this->failure('missing_webhook_destination', 'Webhook Slack ausente.', true, null);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'], CURLOPT_POSTFIELDS => json_encode(['text' => (string) ($delivery['rendered_text'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => (int) ($this->config['timeout_seconds'] ?? 10)]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno !== 0) return $this->failure('transport_error', substr($error, 0, 180), false, $status ?: null);
        if ($status < 200 || $status >= 300) return $this->failure('webhook_rejected', 'Webhook Slack recusou a entrega.', $status >= 400 && $status < 500, $status ?: null);
        return ['ok' => true, 'http_status' => $status ?: 200, 'provider_message_id' => null, 'error_code' => null, 'safe_error' => null, 'permanent' => false];
    }

    /**
     * Entregas antigas guardavam a chave do ambiente em slack_user_id e
     * destination_key no formato slack:channel:<CHAVE>. As novas usam
     * destination_key diretamente. Aceitar ambos evita quebrar a fila já
     * persistida e mantém a chave fora do payload da notificação.
     */
    public static function webhookEnvKey(array $delivery): string
    {
        $key = trim((string) ($delivery['destination_key'] ?? ''));
        $legacyPrefix = 'slack:channel:';
        if (str_starts_with($key, $legacyPrefix)) {
            $key = substr($key, strlen($legacyPrefix));
        }

        return $key !== '' ? $key : trim((string) ($delivery['slack_user_id'] ?? ''));
    }
}
