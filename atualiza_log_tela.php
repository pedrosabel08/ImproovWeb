<?php
// Atualiza o log de tela e insere histórico em logs_usuarios_historico
// Uso: include_once 'atualiza_log_tela.php'; ou chamado via AJAX recebendo POST { title, url }

include 'conexao.php';

// Evita produzir saída
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Só procede se usuário autenticado
if (empty($_SESSION['idusuario'])) {
    // Se for chamada via AJAX, devolve JSON
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'not_logged']);
        exit;
    }
    return;
}

$usuario_id = intval($_SESSION['idusuario']);

// Se vier via POST (AJAX) permite que o cliente envie um title amigável
$tela = null;
$url = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Primeiro tente obter via form-encoded (normalmente preenchido em $_POST)
    if (!empty($_POST['title'])) {
        $tela = trim($_POST['title']);
        $url = isset($_POST['url']) ? trim($_POST['url']) : ($_SERVER['REQUEST_URI'] ?? null);
    } else {
        // Se não há $_POST (fetch com application/json), tente decodificar o input cru
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $data = json_decode($raw, true);
            if (is_array($data) && !empty($data['title'])) {
                $tela = trim($data['title']);
                $url = isset($data['url']) ? trim($data['url']) : ($_SERVER['REQUEST_URI'] ?? null);
            }
        }
    }
}

// Fallback quando não foi possível obter via POST/JSON
if (empty($tela)) {
    $tela = isset($_SERVER['PHP_SELF']) ? basename($_SERVER['PHP_SELF']) : null;
    $url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

// Insere no histórico (prepared statement)
$sql_hist = "INSERT INTO logs_usuarios_historico (usuario_id, tela, url, ip, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
$inserted = false;
if ($stmt = $conn->prepare($sql_hist)) {
    $stmt->bind_param('issss', $usuario_id, $tela, $url, $ip, $user_agent);
    $inserted = (bool) @$stmt->execute();
    $stmt->close();
}

$db_error = null;
if (!$inserted) {
    // capture last error for debugging (only returned on POST responses)
    $db_error = $conn->error ?? null;
}

// Atualiza a linha atual em logs_usuarios (se existir)
if ($stmt2 = $conn->prepare("UPDATE logs_usuarios SET tela_atual = ?, ultima_atividade = NOW() WHERE usuario_id = ?")) {
    $stmt2->bind_param('si', $tela, $usuario_id);
    @$stmt2->execute();
    $stmt2->close();
}

// Se chamada via POST, responde JSON e encerra
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $resp = ['ok' => $inserted];
    if (!$inserted) $resp['error'] = $db_error ?: 'insert_failed';
    echo json_encode($resp);
    exit;
}

// não fecha a conexão para não interferir com o fluxo do app
?>