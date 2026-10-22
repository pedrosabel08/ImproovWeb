<?php
// Local CLI test fixture only. Never expose session manipulation through HTTP.
if (PHP_SAPI !== 'cli' || getenv('FLOW_AUTH_TEST_LOCAL') !== '1') { http_response_code(404); exit(1); }
$input = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
$id = $input['id'] ?? '';
if (!is_string($id) || !preg_match('/^[A-Za-z0-9,-]{16,128}$/D', $id)) throw new RuntimeException('Invalid test session');
if (!is_file(rtrim(session_save_path(), '/\\') . '/sess_' . $id)) throw new RuntimeException('Test session missing');
session_id($id);
if (!session_start()) throw new RuntimeException('Cannot open test session');
switch ($input['operation'] ?? '') {
    case 'expire':
        if (($_SESSION['logado'] ?? false) !== true) throw new RuntimeException('Test session is not authenticated');
        $_SESSION['login_ts'] = time() - 86400;
        $_SESSION['last_activity_ts'] = time() - 86400;
        break;
    case 'restrict':
        if (($_SESSION['logado'] ?? false) !== true) throw new RuntimeException('Test session is not authenticated');
        $_SESSION['nivel_acesso'] = 0;
        break;
    case 'tamper':
        $context = $input['context'] ?? '';
        if (!isset($_SESSION['_flow_login_returns'][$context])) throw new RuntimeException('Test return context missing');
        $_SESSION['_flow_login_returns'][$context]['url'] = $input['value'] ?? null;
        break;
    default: throw new RuntimeException('Unsupported test fixture operation');
}
session_write_close();
echo '{"ok":true}';
