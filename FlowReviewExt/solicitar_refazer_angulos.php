<?php
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Payload inválido']);
    exit;
}

$entrega_item_id = isset($data['entrega_item_id']) ? intval($data['entrega_item_id']) : 0;
$observacao = isset($data['observacao']) ? trim($data['observacao']) : '';
$idusuario = isset($data['idusuario']) ? intval($data['idusuario']) : null;

// Try to use DB if available
$usedDb = false;
$savedDb = false;
try {
    if (file_exists(__DIR__ . '/../conexao.php')) {
        require_once __DIR__ . '/../conexao.php';
        if (isset($conn) && $conn) {
            // ensure table exists
            $createSql = "CREATE TABLE IF NOT EXISTS solicitacoes_refazer_angulos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entrega_item_id INT DEFAULT 0,
                observacao TEXT,
                idusuario INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $conn->query($createSql);

            $stmt = $conn->prepare('INSERT INTO solicitacoes_refazer_angulos (entrega_item_id, observacao, idusuario) VALUES (?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('isi', $entrega_item_id, $observacao, $idusuario);
                $ok = $stmt->execute();
                $stmt->close();
                if ($ok) {
                    $usedDb = true;
                    $savedDb = true;
                }
            }
        }
    }
} catch (Exception $e) {
    // ignore DB errors and fallback to file log
}

// Fallback: append to log file inside FlowReview
$logDir = __DIR__;
$logFile = $logDir . '/refazer_angulos_requests.log';
$entry = [
    'timestamp' => date('c'),
    'entrega_item_id' => $entrega_item_id,
    'observacao' => $observacao,
    'idusuario' => $idusuario
];
file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

// Prepare Slack notification (reuse logic similar to salvar_decisao.php)
$imagemNome = null;
$usuarioNome = null;
try {
    // attempt to load image/entrega info from DB if available
    if (isset($conn) && $conn && $entrega_item_id) {
        $stmtInfo = $conn->prepare("SELECT ei.entrega_id, ei.imagem_id, i.imagem_nome FROM entregas_itens ei LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = ei.imagem_id WHERE ei.id = ? LIMIT 1");
        if ($stmtInfo) {
            $stmtInfo->bind_param('i', $entrega_item_id);
            if ($stmtInfo->execute()) {
                $res = $stmtInfo->get_result();
                if ($row = $res->fetch_assoc()) {
                    $imagemNome = $row['imagem_nome'] ?? null;
                }
            }
            $stmtInfo->close();
        }
        // try to get user name
        if ($idusuario) {
            $stmtU = $conn->prepare('SELECT nome_usuario FROM usuario_externo WHERE idusuario = ? LIMIT 1');
            if ($stmtU) {
                $stmtU->bind_param('i', $idusuario);
                if ($stmtU->execute()) {
                    $rU = $stmtU->get_result();
                    if ($ru = $rU->fetch_assoc()) {
                        $usuarioNome = $ru['nome_usuario'] ?? null;
                    }
                }
                $stmtU->close();
            }
        }
    }
} catch (Exception $e) {
    // ignore
}

// load dotenv simple (if present) like salvar_decisao
function load_dotenv_simple_local($path)
{
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        if (function_exists('putenv')) @putenv("$k=$v");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}
// try to load .env from project root (one level above FlowReviewExt)
load_dotenv_simple_local(__DIR__ . '/../FlowReview/.env');
$slackToken = $_ENV['SLACK_TOKEN'] ?? getenv('SLACK_TOKEN') ?? null;
$slackChannel = 'C09V1SES7B2';

$message = "A imagem " . ($imagemNome ?? ('#' . ($entrega_item_id ?: '')) ) . " foi solicitado novos ângulos\n";
$message .= "Observações:\n" . ($observacao ?: '(sem observação)') . "\n\n";
$message .= "Por quem: " . ($usuarioNome ?? ($idusuario ? "ID $idusuario" : 'Usuário desconhecido'));

// send to slack channel if token available
$logFileSlack = __DIR__ . '/refazer_angulos_notify.log';
if ($slackToken) {
    $payload = ['channel' => $slackChannel, 'text' => $message];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://slack.com/api/chat.postMessage');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $slackToken, 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        file_put_contents($logFileSlack, date('c') . " Slack send error: " . curl_error($ch) . "\n", FILE_APPEND);
    } else {
        file_put_contents($logFileSlack, date('c') . " Slack response ($http): " . $resp . "\n", FILE_APPEND);
    }
    curl_close($ch);
} else {
    file_put_contents($logFileSlack, date('c') . " Slack token not available\n", FILE_APPEND);
}

// final response
if ($savedDb) {
    echo json_encode(['success' => true, 'message' => 'Solicitação registrada na base.']);
} else {
    echo json_encode(['success' => true, 'message' => 'Solicitação registrada (log).']);
}
exit;
