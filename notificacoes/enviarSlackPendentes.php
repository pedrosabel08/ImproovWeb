<?php
header('Content-Type: application/json; charset=utf-8');

// Carrega .env local (simples) se existir no mesmo diretório
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = array_map('trim', explode('=', $line, 2));
        // remove possíveis aspas
        $v = preg_replace('/^"|"$/', '', $v);
        putenv("$k=$v");
        $_ENV[$k] = $v;
    }
}

// Configurar Slack: prefer webhook se disponível, caso contrário usa token+channel
$SLACK_WEBHOOK_URL = getenv('SLACK_WEBHOOK_URL') ?: '';
$SLACK_TOKEN = getenv('SLACK_TOKEN') ?: '';
$SLACK_CHANNEL = getenv('SLACK_CHANNEL') ?: '';
$SLACK_API_URL = getenv('SLACK_API_URL') ?: 'https://slack.com/api/chat.postMessage';

require_once __DIR__ . '/../conexao.php'; // fornece $conn (mysqli)

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

$response = ['success' => false];

try {
    $pendentes = [];

    // FIX: ensure $input is an array before trying to access offsets to avoid warnings/notices
    if (is_array($input) && !empty($input['pendentes']) && is_array($input['pendentes'])) {
        $pendentes = $input['pendentes'];
    } else {
        // buscar do DB
        $sql = "SELECT p.id, p.imagem_id, p.funcao_imagem_id, p.criado_em, i.imagem_nome, s.nome_status
                FROM postagem_pendentes p
                LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = p.imagem_id
                LEFT JOIN status_imagem s ON s.idstatus = i.status_id
                WHERE p.status <> 'posted'
                ORDER BY p.criado_em ASC
                LIMIT 200";

        $res = $conn->query($sql);
        if ($res === false) throw new Exception('Query error: ' . $conn->error);

        while ($row = $res->fetch_assoc()) {
            $pendentes[] = array(
                'id' => isset($row['id']) ? (int)$row['id'] : 0,
                'imagem_id' => isset($row['imagem_id']) ? (int)$row['imagem_id'] : 0,
                'funcao_imagem_id' => isset($row['funcao_imagem_id']) ? (int)$row['funcao_imagem_id'] : 0,
                'imagem_nome' => array_key_exists('imagem_nome', $row) ? $row['imagem_nome'] : null,
                'criado_em' => array_key_exists('criado_em', $row) ? $row['criado_em'] : null,
                'status' => array_key_exists('nome_status', $row) ? $row['nome_status'] : null
            );
        }
    }

    if (count($pendentes) === 0) {
        $response['success'] = true;
        $response['message'] = 'Nenhum pendente para enviar.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        if (isset($conn) && $conn instanceof mysqli) $conn->close();
        exit;
    }

    // Monta a mensagem (limitando a listagem a 20 items no texto)
    $count = count($pendentes);
    $lines = [];
    $maxShow = 20;
    for ($i = 0; $i < min($count, $maxShow); $i++) {
        $p = $pendentes[$i];
        $nome = isset($p['imagem_nome']) && $p['imagem_nome'] !== null && $p['imagem_nome'] !== '' ? $p['imagem_nome'] : ('#' . (isset($p['imagem_id']) ? $p['imagem_id'] : ''));
        $lines[] = sprintf("• %s (imagem_id: %s, status: %s)", $nome, $p['imagem_id'], $p['status']);
    }

    if ($count > $maxShow) $lines[] = sprintf("... e mais %d itens", $count - $maxShow);

    $text = sprintf("📢 Há %d imagens pendentes para postagem/entrega:\n%s", $count, implode("\n", $lines));

    // Envia via incoming webhook
    $payload = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);
    // Envia: prefer webhook, se não possível usa API com token
    if (!empty($SLACK_WEBHOOK_URL)) {
        $ch = curl_init($SLACK_WEBHOOK_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $httpCode < 200 || $httpCode >= 300) {
            throw new Exception('Falha ao enviar mensagem ao Slack (webhook). HTTP: ' . $httpCode . ' err: ' . $curlErr . ' resp: ' . substr((string)$resp, 0, 200));
        }
    } else {
        // Uso do token com chat.postMessage
        if (empty($SLACK_TOKEN) || empty($SLACK_CHANNEL)) {
            throw new Exception('SLACK_TOKEN ou SLACK_CHANNEL não configurados. Adicione ao .env ou às variáveis de ambiente.');
        }

        $apiPayload = json_encode([
            'channel' => $SLACK_CHANNEL,
            'text' => $text
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($SLACK_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Bearer ' . $SLACK_TOKEN
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $apiPayload);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $respJson = json_decode($resp, true);
        if ($resp === false || $httpCode < 200 || $httpCode >= 300 || !(isset($respJson['ok']) ? $respJson['ok'] : false)) {
            $errMsg = isset($respJson['error']) ? $respJson['error'] : substr((string)$resp, 0, 200);
            throw new Exception('Falha ao enviar mensagem ao Slack (api). HTTP: ' . $httpCode . ' err: ' . $curlErr . ' resp: ' . $errMsg);
        }
    }

    // Se chegou aqui, marcar os registros como 'notified'
    $ids = array();
    foreach ($pendentes as $p) {
        if (isset($p['id'])) $ids[] = (int)$p['id'];
    }
    // Filtra ids inválidos (0)
    $ids = array_values(array_filter($ids, function ($v) {
        return $v > 0;
    }));
    if (count($ids) > 0) {
        $idsList = implode(',', $ids);
        $upd = "UPDATE postagem_pendentes SET status = 'notified' WHERE id IN ($idsList)";
        $conn->query($upd);
    }

    $response['success'] = true;
    $response['sent_count'] = $count;
    $response['slack_response'] = isset($resp) ? $resp : null;
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

if (isset($conn) && $conn instanceof mysqli) $conn->close();
