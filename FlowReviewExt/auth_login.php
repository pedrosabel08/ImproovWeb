<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
session_start();
require_once __DIR__ . '/../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

if ($email === '' || $senha === '') {
    die('E-mail e senha são obrigatórios.');
}

$stmt = $conn->prepare('SELECT idusuario, senha FROM usuario_externo WHERE email = ? LIMIT 1');
if (!$stmt) die('Erro no banco: ' . $conn->error);
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    die('Usuário não encontrado.');
}

$stored = $row['senha'];
$passwordOk = false;

// Caso ideal: senha armazenada como hash — verifica com password_verify
if (password_verify($senha, $stored)) {
    $passwordOk = true;
} else {
    // Se falhou, pode ser que a senha esteja em texto plano (migração antiga).
    // Detecta correspondência direta e re-hash para migrar para password_hash()
    if (hash_equals($stored, $senha)) {
        // Re-hash e atualiza no banco
        $newHash = password_hash($senha, PASSWORD_DEFAULT);
        $uStmt = $conn->prepare('UPDATE usuario_externo SET senha = ? WHERE idusuario = ?');
        if ($uStmt) {
            $uStmt->bind_param('si', $newHash, $row['idusuario']);
            $uStmt->execute();
            $uStmt->close();
            $passwordOk = true;
        }
    }
}

if (!$passwordOk) {
    die('Senha incorreta.');
}

// Login bem-sucedido: cria sessão e emite token persistente
$_SESSION['logado'] = true;
$_SESSION['idusuario'] = $row['idusuario'];

// Gera token e grava hash no banco
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + 86400 * 2);

$stmt = $conn->prepare('INSERT INTO login_tokens (user_id, token_hash, expires_at) VALUES (?, SHA2(?,256), ?)');
if ($stmt) {
    $stmt->bind_param('iss', $row['idusuario'], $token, $expiresAt);
    $stmt->execute();
    $stmt->close();
}

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie('flow_auth', $token, time() + 86400 * 2, '/', '', $secure, true);

// Decide response type: if request expects JSON or is AJAX, return JSON so frontend can stay
// on the same path (e.g. /FlowReview/<token>) and continue processing the token.
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'idusuario' => $row['idusuario']]);
    exit();
} else {
    header('Location: index.php');
    exit();
}
