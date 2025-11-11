<?php
// Simple local test for resolve_slack_user_id logic using a mock users.list response.
// Usage: php scripts/test_slack_resolve.php "Pedro Sabel"

if ($argc < 2) {
    echo "Usage: php " . $argv[0] . " \"Identifier to resolve\"\n";
    exit(1);
}

$identifier = $argv[1];
$mockFile = __DIR__ . '/mock_slack_users.json';
$log = [];

function resolve_slack_user_id_mock($identifier, $mockPath, &$log)
{
    if (!file_exists($mockPath)) {
        $log[] = "Mock file not found: $mockPath";
        return null;
    }
    $json = json_decode(file_get_contents($mockPath), true);
    if (!$json || empty($json['members'])) {
        $log[] = "Mock JSON invalid or empty members";
        return null;
    }
    $needle = mb_strtolower(trim($identifier), 'UTF-8');
    foreach ($json['members'] as $m) {
        if (!empty($m['deleted']) || !empty($m['is_bot'])) continue;
        $candidates = [];
        if (!empty($m['name'])) $candidates[] = $m['name'];
        if (!empty($m['profile']['display_name'])) $candidates[] = $m['profile']['display_name'];
        if (!empty($m['profile']['real_name'])) $candidates[] = $m['profile']['real_name'];
        if (!empty($m['profile']['email'])) $candidates[] = $m['profile']['email'];
        foreach ($candidates as $cand) {
            if (mb_strtolower($cand, 'UTF-8') === $needle) {
                $log[] = "Matched mock member: " . ($m['id'] ?? '(no-id)');
                return $m['id'] ?? null;
            }
        }
    }
    $log[] = "No mock match for '$identifier'";
    return null;
}

$res = resolve_slack_user_id_mock($identifier, $mockFile, $log);
echo "Testing resolve for: '$identifier'\n";
foreach ($log as $l) echo " - $l\n";
if ($res) {
    echo "=> Resolved to user id: $res\n";
    exit(0);
} else {
    echo "=> Not resolved\n";
    exit(2);
}
