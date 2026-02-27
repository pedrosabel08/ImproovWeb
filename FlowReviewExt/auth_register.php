<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
session_start();
require_once __DIR__ . '/../conexao.php';

// Espera POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit();
}

$nome = trim($_POST['nome_usuario'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';
$cargo = trim($_POST['cargo'] ?? '');

if ($nome === '' || $email === '' || $senha === '' || $cargo === '') {
    die('Todos os campos são obrigatórios. Volte e tente novamente.');
}

// Verifica se e-mail já existe
$stmt = $conn->prepare('SELECT idusuario FROM usuario_externo WHERE email = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->fetch_assoc()) {
        $stmt->close();
        die('E-mail já cadastrado.');
    }
    $stmt->close();
}

$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $conn->prepare('INSERT INTO usuario_externo (nome_usuario, email, senha, cargo) VALUES (?, ?, ?, ?)');
if (!$stmt) die('Erro no banco: ' . $conn->error);
$stmt->bind_param('ssss', $nome, $email, $senha_hash, $cargo);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    // Auto-login: cria sessão e token persistente como no login
    $idusuario = $conn->insert_id;
    $_SESSION['logado'] = true;
    $_SESSION['idusuario'] = $idusuario;

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 86400 * 2);
    $stmt2 = $conn->prepare('INSERT INTO login_tokens (user_id, token_hash, expires_at) VALUES (?, SHA2(?,256), ?)');
    if ($stmt2) {
        $stmt2->bind_param('iss', $idusuario, $token, $expiresAt);
        $stmt2->execute();
        $stmt2->close();
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('flow_auth', $token, time() + 86400 * 2, '/', '', $secure, true);

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'idusuario' => $idusuario]);
        exit();
    }

    // Fallback: redirect to login page
    header('Location: login.php?registered=1');
    exit();
} else {
    die('Falha ao criar usuário.');
}
