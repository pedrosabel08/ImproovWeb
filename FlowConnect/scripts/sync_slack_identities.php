<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexaoMain.php';

use FlowConnect\Infrastructure\SlackIdentityRepository;

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

function flow_connect_identity_normalize(string $value): string
{
    $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/', '', strtolower($value)) ?? '') ?? '');
}

$token = getenv('SLACK_TOKEN');
if ($token === false || trim((string) $token) === '') {
    fwrite(STDERR, "SLACK_TOKEN ausente.\n");
    exit(1);
}

$config = flow_connect_config();
$members = [];
$cursor = '';
do {
    $url = rtrim($config['slack']['api_base_url'], '/') . '/users.list?limit=200';
    if ($cursor !== '') $url .= '&cursor=' . rawurlencode($cursor);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => (int) $config['slack']['timeout_seconds'],
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    $data = json_decode((string) $raw, true);
    if (!is_array($data) || empty($data['ok'])) {
        fwrite(STDERR, 'Slack users.list falhou: ' . ($data['error'] ?? ($error ?: 'invalid_response')) . PHP_EOL);
        exit(1);
    }
    foreach ($data['members'] ?? [] as $member) {
        if (!empty($member['deleted']) || !empty($member['is_bot'])) continue;
        foreach (array_filter([
            $member['real_name'] ?? null,
            $member['profile']['real_name'] ?? null,
            $member['profile']['display_name'] ?? null,
        ]) as $name) {
            $normalized = flow_connect_identity_normalize((string) $name);
            if ($normalized !== '') $members[$normalized][] = $member;
        }
    }
    $cursor = trim((string) ($data['response_metadata']['next_cursor'] ?? ''));
} while ($cursor !== '');

$conn = conectarBanco();
$repo = new SlackIdentityRepository($conn);
$result = $conn->query("SELECT c.idcolaborador, c.nome_colaborador, u.nome_slack FROM colaborador c LEFT JOIN usuario u ON u.idcolaborador=c.idcolaborador WHERE c.ativo=1 ORDER BY c.idcolaborador");
$counts = ['ACTIVE' => 0, 'UNRESOLVED' => 0, 'CONFLICT' => 0];
while ($row = $result->fetch_assoc()) {
    $collaboratorId = (int) $row['idcolaborador'];
    $legacy = trim((string) ($row['nome_slack'] ?? ''));
    $matches = [];
    if (preg_match('/^U[A-Z0-9]+$/', $legacy)) {
        foreach ($members as $candidateMembers) {
            foreach ($candidateMembers as $member) {
                if (($member['id'] ?? null) === $legacy) $matches[$legacy] = $member;
            }
        }
    } else {
        $target = flow_connect_identity_normalize($legacy !== '' ? $legacy : (string) $row['nome_colaborador']);
        foreach ($members[$target] ?? [] as $member) $matches[$member['id']] = $member;
    }

    $status = count($matches) === 1 ? 'ACTIVE' : (count($matches) > 1 ? 'CONFLICT' : 'UNRESOLVED');
    $member = count($matches) === 1 ? reset($matches) : null;
    $repo->upsert(
        $collaboratorId,
        $member['id'] ?? null,
        $member['profile']['display_name'] ?? null,
        $member['profile']['real_name'] ?? ($member['real_name'] ?? null),
        $status,
        'slack_users_list'
    );
    $counts[$status]++;
}
$conn->close();
echo json_encode(['success' => true, 'counts' => $counts], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
