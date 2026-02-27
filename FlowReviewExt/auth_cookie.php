<?php
// helper to validate `flow_auth` cookie and expose $flow_user_id, $flow_user_name, $flow_is_admin
require_once __DIR__ . '/../conexao.php';

$flow_user_id = null;
$flow_user_name = null;
$flow_is_admin = false;
$flow_idcolaborador = null;

if (isset($_COOKIE['flow_auth']) && !empty($_COOKIE['flow_auth'])) {
    $token = $_COOKIE['flow_auth'];
    $stmt = $conn->prepare("SELECT lt.user_id, lt.expires_at, u.nome_usuario, u.cargo FROM login_tokens lt JOIN usuario_externo u ON u.idusuario = lt.user_id WHERE lt.token_hash = SHA2(?,256) LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (strtotime($row['expires_at']) > time()) {
                $flow_user_id = (int)$row['user_id'];
                $flow_user_name = $row['nome_usuario'] ?? null;
                // attempt to resolve idcolaborador from legacy `usuario` table
                $stmt2 = $conn->prepare("SELECT idcolaborador, nome_usuario FROM usuario WHERE idusuario = ? LIMIT 1");
                if ($stmt2) {
                    $stmt2->bind_param('i', $flow_user_id);
                    $stmt2->execute();
                    $r2 = $stmt2->get_result();
                    if ($r2 && ($rr = $r2->fetch_assoc())) {
                        $flow_idcolaborador = isset($rr['idcolaborador']) ? intval($rr['idcolaborador']) : null;
                        // prefer legacy nome if available
                        if (!empty($rr['nome_usuario'])) $flow_user_name = $rr['nome_usuario'];
                    }
                    $stmt2->close();
                }
                // simple admin check: cargo or specific ids (customize as needed)
                if (in_array($flow_user_id, [1,2]) || stripos($row['cargo'] ?? '', 'admin') !== false) {
                    $flow_is_admin = true;
                }
                // renew cookie
                $expires = time() + 86400 * 2;
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                setcookie('flow_auth', $token, $expires, '/', '', $secure, true);
            }
        }
        $stmt->close();
    }
}
