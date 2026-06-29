<?php
include_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$idPos = intval($_POST['id_pos'] ?? 0);
if (!$idPos) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

$stmt = $conn->prepare("SELECT status_pos, imagem_id, obra_id FROM pos_producao WHERE idpos_producao = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}
$stmt->bind_param('i', $idPos);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Registro não encontrado']);
    exit;
}

$newStatus = intval($row['status_pos']) === 0 ? 1 : 0;

$stmt = $conn->prepare("UPDATE pos_producao SET status_pos = ?, data_pos = NOW() WHERE idpos_producao = ?");
$stmt->bind_param('ii', $newStatus, $idPos);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    require_once 'ws_notify.php';
    notifyPosProducaoUpdate();

    if ($newStatus === 0) {
        $imagemId = intval($row['imagem_id']);
        $obraId   = intval($row['obra_id']);

        $stmtImg = $conn->prepare("SELECT imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?");
        $stmtImg->bind_param('i', $imagemId);
        $stmtImg->execute();
        $nomeImagem = $stmtImg->get_result()->fetch_assoc()['imagem_nome'] ?? 'Imagem não encontrada';
        $stmtImg->close();

        $stmtObra = $conn->prepare("SELECT nome_obra FROM obra WHERE idobra = ?");
        $stmtObra->bind_param('i', $obraId);
        $stmtObra->execute();
        $stmtObra->get_result()->fetch_assoc();
        $stmtObra->close();

        require_once __DIR__ . '/../FlowReview/vendor/autoload.php';
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../FlowReview');
        $dotenv->load();
        $slackWebhookUrl = $_ENV['SLACK_WEBHOOK_POS_URL'] ?? null;

        if ($slackWebhookUrl) {
            $slackMessage = ['text' => "A imagem $nomeImagem foi feita a pós."];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $slackWebhookUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($slackMessage));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $slackResponse = curl_exec($ch);
            if (curl_errno($ch)) {
                error_log('toggle_status_pos: Erro Slack: ' . curl_error($ch));
            }
            curl_close($ch);
        }
    }
}

$conn->close();

echo json_encode(['success' => $success, 'new_status' => $newStatus]);
