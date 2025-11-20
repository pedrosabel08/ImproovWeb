<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../conexao.php';

$response = ['authenticated' => false];
if (isset($_COOKIE['flow_auth']) && !empty($_COOKIE['flow_auth'])) {
    $token = $_COOKIE['flow_auth'];
    $stmt = $conn->prepare("SELECT user_id, expires_at FROM login_tokens WHERE token_hash = SHA2(?, 256) LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (strtotime($row['expires_at']) > time()) {
                $response['authenticated'] = true;
                $response['idusuario'] = (int) $row['user_id'];
                // renew cookie
                $expires = time() + 86400 * 2;
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                setcookie('flow_auth', $token, $expires, '/', '', $secure, true);
            }
        }
        $stmt->close();
    }
}

echo json_encode($response);
exit();
