<?php
header('Content-Type: application/json; charset=utf-8');

/*
  Endpoint para enviar notificação ao Slack sobre itens pendentes de postagem.
  Recebe JSON (opcional) com { pendentes: [ {id, imagem_id, imagem_nome, funcao_imagem_id}, ... ] }
  Se não receber pendentes no POST, o script buscará pendentes diretamente na tabela postagem_pendentes.

  ATENÇÃO: configure a variável $SLACK_WEBHOOK_URL neste arquivo ou, melhor, em um arquivo fora do webroot.
*/

// Configurar aqui o Webhook do Slack (incoming webhook) ou use uma variável de ambiente
$SLACK_WEBHOOK_URL = getenv('SLACK_WEBHOOK_URL') ?: '';

require_once __DIR__ . '/../conexao.php'; // fornece $conn (mysqli)

$input = json_decode(file_get_contents('php://input'), true);

$response = ['success' => false];

try {
    $pendentes = [];

    if (!empty($input['pendentes']) && is_array($input['pendentes'])) {
        $pendentes = $input['pendentes'];
    } else {
        // buscar do DB
        $sql = "SELECT p.id, p.imagem_id, p.funcao_imagem_id, p.criado_em, i.imagem_nome
                FROM postagem_pendentes p
                LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = p.imagem_id
                WHERE p.status = 'pending'
                ORDER BY p.criado_em ASC
                LIMIT 200";

        $res = $conn->query($sql);
        if ($res === false) throw new Exception('Query error: ' . $conn->error);

        while ($row = $res->fetch_assoc()) {
            $pendentes[] = [
                'id' => (int)$row['id'],
                'imagem_id' => (int)$row['imagem_id'],
                'funcao_imagem_id' => (int)$row['funcao_imagem_id'],
                'imagem_nome' => $row['imagem_nome'] ?? null,
                'criado_em' => $row['criado_em'] ?? null
            ];
        }
    }

    if (count($pendentes) === 0) {
        $response['success'] = true;
        $response['message'] = 'Nenhum pendente para enviar.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($SLACK_WEBHOOK_URL)) {
        throw new Exception('SLACK_WEBHOOK_URL não configurado no servidor. Defina a variável de ambiente SLACK_WEBHOOK_URL ou edite este arquivo.');
    }

    // Monta a mensagem (limitando a listagem a 20 items no texto)
    $count = count($pendentes);
    $lines = [];
    $maxShow = 20;
    for ($i = 0; $i < min($count, $maxShow); $i++) {
        $p = $pendentes[$i];
        $nome = isset($p['imagem_nome']) ? $p['imagem_nome'] : ('#' . ($p['imagem_id'] ?? '')); 
        $lines[] = sprintf("• %s (imagem_id: %s)", $nome, $p['imagem_id']);
    }

    if ($count > $maxShow) $lines[] = sprintf("... e mais %d itens", $count - $maxShow);

    $text = sprintf("📢 Há %d imagens pendentes para postagem/entrega:\n%s", $count, implode("\n", $lines));

    // Envia via incoming webhook
    $payload = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);

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
        throw new Exception('Falha ao enviar mensagem ao Slack. HTTP: ' . $httpCode . ' err: ' . $curlErr . ' resp: ' . substr((string)$resp,0,200));
    }

    // Se chegou aqui, marcar os registros como 'notified'
    $ids = array_map(function($p){ return (int)$p['id']; }, $pendentes);
    $idsList = implode(',', $ids);
    if (!empty($idsList)) {
        $upd = "UPDATE postagem_pendentes SET status = 'notified' WHERE id IN ($idsList)";
        $conn->query($upd);
    }

    $response['success'] = true;
    $response['sent_count'] = $count;
    $response['slack_response'] = $resp;

} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

?>
