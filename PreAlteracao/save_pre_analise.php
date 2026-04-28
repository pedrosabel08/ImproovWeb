<?php
// PreAlteracao/save_pre_analise.php
// Salva (INSERT/UPDATE) a análise de pré-alteração de uma imagem.
// Muda substatus da imagem para PRE_ALT (11).
// Ao final, verifica se todas as imagens da obra estão prontas → READY_FOR_PLANNING (12).
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
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

$imagem_id     = isset($data['imagem_id'])  && is_numeric($data['imagem_id'])  ? intval($data['imagem_id'])  : 0;
$entrega_id    = isset($data['entrega_id']) && is_numeric($data['entrega_id']) ? intval($data['entrega_id']) : 0;
$complexidade  = in_array($data['complexidade'] ?? '', ['S', 'M', 'C', 'TA']) ? $data['complexidade'] : null;
$acao          = trim($data['acao'] ?? '');
$necessita_retorno = isset($data['necessita_retorno']) && $data['necessita_retorno'] ? 1 : 0;

if ($imagem_id <= 0 || !$complexidade) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']);
    exit;
}

$responsavel_id = intval($_SESSION['idcolaborador'] ?? 0) ?: null;

// Obtém obra_id da imagem
$stmtObra = $conn->prepare('SELECT obra_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?');
$stmtObra->bind_param('i', $imagem_id);
$stmtObra->execute();
$rowObra = $stmtObra->get_result()->fetch_assoc();
$stmtObra->close();

if (!$rowObra) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Imagem não encontrada.']);
    exit;
}
$obra_id = intval($rowObra['obra_id']);

// INSERT/UPDATE pre_alt_analise (UNIQUE KEY uq_imagem_entrega)
$entrega_key = $entrega_id > 0 ? $entrega_id : 0;
$sql = "INSERT INTO pre_alt_analise (imagem_id, entrega_id, complexidade, acao, necessita_retorno, responsavel_id)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            complexidade       = VALUES(complexidade),
            acao               = VALUES(acao),
            necessita_retorno  = VALUES(necessita_retorno),
            responsavel_id     = VALUES(responsavel_id),
            updated_at         = NOW()";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iissii', $imagem_id, $entrega_key, $complexidade, $acao, $necessita_retorno, $responsavel_id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}
$stmt->close();

// Muda substatus da imagem para PRE_ALT (11)
$stmtSub = $conn->prepare('UPDATE imagens_cliente_obra SET substatus_id = 11 WHERE idimagens_cliente_obra = ?');
$stmtSub->bind_param('i', $imagem_id);
$stmtSub->execute();
$stmtSub->close();

// Verifica READY_FOR_PLANNING para a obra
$result = verificarReadyForPlanning($conn, $obra_id);

echo json_encode(['success' => true, 'ready_for_planning' => $result]);

// -----------------------------------------------------------------------
// Verifica se todas as imagens da obra estão prontas para planejamento:
//   - Nenhuma imagem com substatus RVW_DONE (10) pendente de análise
//   - Nenhuma imagem PRE_ALT (11) com necessita_retorno = 1
//   - Pelo menos uma imagem PRE_ALT (11) existe
// Se condições OK → muda para READY_FOR_PLANNING (12) + notifica Pedro via Slack
// -----------------------------------------------------------------------
function verificarReadyForPlanning(mysqli $conn, int $obra_id): bool
{
    // Conta imagens RVW_DONE ainda sem análise
    $stmtPend = $conn->prepare(
        'SELECT COUNT(*) AS cnt FROM imagens_cliente_obra WHERE obra_id = ? AND substatus_id = 10'
    );
    $stmtPend->bind_param('i', $obra_id);
    $stmtPend->execute();
    $cntRvwDone = (int)$stmtPend->get_result()->fetch_assoc()['cnt'];
    $stmtPend->close();

    if ($cntRvwDone > 0) {
        return false;
    }

    // Conta imagens PRE_ALT com necessita_retorno = 1 (aguardando retorno do cliente)
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

    if ($cntRet > 0) {
        return false;
    }

    // Confirma que há pelo menos uma imagem PRE_ALT para promover
    $stmtAny = $conn->prepare(
        'SELECT COUNT(*) AS cnt FROM imagens_cliente_obra WHERE obra_id = ? AND substatus_id = 11'
    );
    $stmtAny->bind_param('i', $obra_id);
    $stmtAny->execute();
    $cntPreAlt = (int)$stmtAny->get_result()->fetch_assoc()['cnt'];
    $stmtAny->close();

    if ($cntPreAlt === 0) {
        return false;
    }

    // Promove todas as imagens PRE_ALT para READY_FOR_PLANNING (12)
    $stmtUp = $conn->prepare(
        'UPDATE imagens_cliente_obra SET substatus_id = 12 WHERE obra_id = ? AND substatus_id = 11'
    );
    $stmtUp->bind_param('i', $obra_id);
    $stmtUp->execute();
    $stmtUp->close();

    // Busca nome da obra
    $stmtNome = $conn->prepare('SELECT nomenclatura FROM obra WHERE idobra = ?');
    $stmtNome->bind_param('i', $obra_id);
    $stmtNome->execute();
    $nomeObra = $stmtNome->get_result()->fetch_assoc()['nomenclatura'] ?? "Obra #$obra_id";
    $stmtNome->close();

    // Notifica Pedro A. Sabel via Slack
    $mensagem = "✅ *READY_FOR_PLANNING* — Todas as imagens de *$nomeObra* foram analisadas e estão prontas para planejamento.";
    notificarPedroSlack($conn, $mensagem);

    return true;
}

function notificarPedroSlack(mysqli $conn, string $mensagem): void
{
    improov_load_env_once();
    $slackToken = getenv('SLACK_TOKEN') ?: ($_ENV['SLACK_TOKEN'] ?? '');
    if (empty($slackToken)) return;

    // Busca o Slack ID de Pedro A. Sabel via nome_slack na tabela usuario
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

