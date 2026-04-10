<?php
// PreAlteracao/update_complexidade.php
// Atualiza a complexidade e o flag necessita_retorno de uma análise já salva.
// Re-dispara a verificação READY_FOR_PLANNING.
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';
require_once '../config/session_bootstrap.php';
require_once '../config/secure_env.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$analise_id        = isset($data['analise_id'])   && is_numeric($data['analise_id'])   ? intval($data['analise_id'])   : 0;
$complexidade      = in_array($data['complexidade'] ?? '', ['S', 'M', 'C', 'TA']) ? $data['complexidade'] : null;
$necessita_retorno = isset($data['necessita_retorno']) && $data['necessita_retorno'] ? 1 : 0;

if ($analise_id <= 0 || !$complexidade) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']);
    exit;
}

// Atualiza complexidade e necessita_retorno
$stmt = $conn->prepare("UPDATE pre_alt_analise SET complexidade = ?, necessita_retorno = ?, updated_at = NOW() WHERE id = ?");
$stmt->bind_param('sii', $complexidade, $necessita_retorno, $analise_id);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}
$stmt->close();

// Busca obra_id para disparar verificação
$stmtObra = $conn->prepare(
    "SELECT i.obra_id
     FROM pre_alt_analise pa
     INNER JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = pa.imagem_id
     WHERE pa.id = ?"
);
$stmtObra->bind_param('i', $analise_id);
$stmtObra->execute();
$rowObra = $stmtObra->get_result()->fetch_assoc();
$stmtObra->close();

$ready = false;
if ($rowObra) {
    $obra_id = intval($rowObra['obra_id']);
    $ready   = verificarReadyForPlanning($conn, $obra_id);
}

echo json_encode(['success' => true, 'ready_for_planning' => $ready]);

// -----------------------------------------------------------------------
function verificarReadyForPlanning(mysqli $conn, int $obra_id): bool
{
    $stmtPend = $conn->prepare(
        'SELECT COUNT(*) AS cnt FROM imagens_cliente_obra WHERE obra_id = ? AND substatus_id = 10'
    );
    $stmtPend->bind_param('i', $obra_id);
    $stmtPend->execute();
    $cntRvwDone = (int)$stmtPend->get_result()->fetch_assoc()['cnt'];
    $stmtPend->close();
    if ($cntRvwDone > 0) return false;

    $stmtRet = $conn->prepare(
        "SELECT COUNT(*) AS cnt
         FROM imagens_cliente_obra i
         INNER JOIN pre_alt_analise pa ON pa.imagem_id = i.idimagens_cliente_obra
         WHERE i.obra_id = ? AND i.substatus_id = 11 AND pa.necessita_retorno = 1"
    );
    $stmtRet->bind_param('i', $obra_id);
    $stmtRet->execute();
    $cntRet = (int)$stmtRet->get_result()->fetch_assoc()['cnt'];
    $stmtRet->close();
    if ($cntRet > 0) return false;

    $stmtAny = $conn->prepare(
        'SELECT COUNT(*) AS cnt FROM imagens_cliente_obra WHERE obra_id = ? AND substatus_id = 11'
    );
    $stmtAny->bind_param('i', $obra_id);
    $stmtAny->execute();
    $cntPreAlt = (int)$stmtAny->get_result()->fetch_assoc()['cnt'];
    $stmtAny->close();
    if ($cntPreAlt === 0) return false;

    $stmtUp = $conn->prepare(
        'UPDATE imagens_cliente_obra SET substatus_id = 12 WHERE obra_id = ? AND substatus_id = 11'
    );
    $stmtUp->bind_param('i', $obra_id);
    $stmtUp->execute();
    $stmtUp->close();

    $stmtNome = $conn->prepare('SELECT nomenclatura FROM obra WHERE idobra = ?');
    $stmtNome->bind_param('i', $obra_id);
    $stmtNome->execute();
    $nomeObra = $stmtNome->get_result()->fetch_assoc()['nomenclatura'] ?? "Obra #$obra_id";
    $stmtNome->close();

    $mensagem = "✅ *READY_FOR_PLANNING* — Todas as imagens de *$nomeObra* foram analisadas e estão prontas para planejamento.";
    notificarPedroSlack($conn, $mensagem);

    return true;
}

function notificarPedroSlack(mysqli $conn, string $mensagem): void
{
    improov_load_env_once();
    $slackToken = getenv('SLACK_TOKEN') ?: ($_ENV['SLACK_TOKEN'] ?? '');
    if (empty($slackToken)) return;

    $stmt = $conn->prepare(
        "SELECT u.nome_slack FROM usuario u
         JOIN colaborador c ON c.idcolaborador = u.idcolaborador
         WHERE c.nome_colaborador LIKE '%Sabel%'
           AND u.nome_slack IS NOT NULL AND u.nome_slack != ''
         LIMIT 1"
    );
    if (!$stmt) return;
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $slackId = $row['nome_slack'] ?? null;
    if (!$slackId) return;

    $ch = curl_init('https://slack.com/api/chat.postMessage');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$slackToken}", "Content-Type: application/json"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['channel' => $slackId, 'text' => $mensagem]),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

