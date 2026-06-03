<?php
header('Content-Type: application/json; charset=utf-8');

if (file_exists(__DIR__ . '/conexaoMain.php')) {
    include_once __DIR__ . '/conexaoMain.php';
} elseif (file_exists(__DIR__ . '/conexao.php')) {
    include_once __DIR__ . '/conexao.php';
}

function responder_pacote_status(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function carregar_pacotes_obra(mysqli $conn, int $obraId): array
{
    $sql = "SELECT idobra_pacote, tipo, status
            FROM obra_pacote
            WHERE obra_id = ?
            ORDER BY FIELD(tipo, 'STILL', 'ANIMACAO', 'FILME'), idobra_pacote ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        responder_pacote_status([
            'success' => false,
            'message' => 'Erro ao preparar consulta de pacotes: ' . $conn->error,
        ]);
    }

    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $result = $stmt->get_result();
    $pacotes = [];
    while ($row = $result->fetch_assoc()) {
        $pacotes[] = [
            'idobra_pacote' => (int) $row['idobra_pacote'],
            'tipo' => (string) $row['tipo'],
            'status' => (string) $row['status'],
        ];
    }
    $stmt->close();

    return $pacotes;
}

function tem_pacote_tipo(array $pacotes, string $tipo): bool
{
    foreach ($pacotes as $pacote) {
        if (strtoupper((string) ($pacote['tipo'] ?? '')) === $tipo) {
            return true;
        }
    }
    return false;
}

function pacote_label(string $tipo): string
{
    $labels = [
        'STILL' => 'imagens',
        'ANIMACAO' => 'animacao',
        'FILME' => 'filme',
    ];

    return $labels[$tipo] ?? strtolower($tipo);
}

function todos_pacotes_concluidos(mysqli $conn, int $obraId): bool
{
    $sql = "SELECT COUNT(*) AS total,
                   SUM(CASE WHEN status <> 'CONCLUIDO' THEN 1 ELSE 0 END) AS pendentes
            FROM obra_pacote
            WHERE obra_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar verificacao de pacotes: ' . $conn->error);
    }

    $stmt->bind_param('i', $obraId);
    if (!$stmt->execute()) {
        throw new RuntimeException('Erro ao verificar pacotes concluidos: ' . $stmt->error);
    }

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0) > 0 && (int) ($row['pendentes'] ?? 0) === 0;
}

function marcar_obra_inativa(mysqli $conn, int $obraId): void
{
    $statusInativo = 1;
    $stmt = $conn->prepare("UPDATE obra SET status_obra = ? WHERE idobra = ?");
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar inativacao da obra: ' . $conn->error);
    }

    $stmt->bind_param('ii', $statusInativo, $obraId);
    if (!$stmt->execute()) {
        throw new RuntimeException('Erro ao marcar obra como inativa: ' . $stmt->error);
    }
    $stmt->close();
}

if (!function_exists('conectarBanco')) {
    responder_pacote_status([
        'success' => false,
        'message' => 'Funcao conectarBanco() nao encontrada.',
    ]);
}

$raw = file_get_contents('php://input');
$input = $raw ? json_decode($raw, true) : null;
if (!is_array($input)) {
    $input = $_POST;
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$obraId = 0;
if ($requestMethod === 'GET') {
    $obraId = isset($_GET['obra_id']) ? (int) $_GET['obra_id'] : 0;
} else {
    $obraId = isset($input['obra_id']) ? (int) $input['obra_id'] : 0;
}

if ($obraId <= 0) {
    responder_pacote_status([
        'success' => false,
        'message' => 'ID da obra invalido.',
    ]);
}

$conn = conectarBanco();
if (!$conn) {
    responder_pacote_status([
        'success' => false,
        'message' => 'Erro ao conectar ao banco.',
    ]);
}

if ($requestMethod === 'GET') {
    $pacotes = carregar_pacotes_obra($conn, $obraId);
    responder_pacote_status([
        'success' => true,
        'packages' => $pacotes,
        'has_still' => tem_pacote_tipo($pacotes, 'STILL'),
        'has_animation' => tem_pacote_tipo($pacotes, 'ANIMACAO'),
    ]);
}

$concluirPacote = !empty($input['concluir_pacote']) || !empty($input['concluir_imagens']);
$ativarAnimacao = !empty($input['ativar_animacao']);
$pacoteTipo = strtoupper(trim((string) ($input['pacote_tipo'] ?? 'STILL')));
$tiposPermitidos = ['STILL', 'ANIMACAO', 'FILME'];
if (!$concluirPacote || !in_array($pacoteTipo, $tiposPermitidos, true)) {
    responder_pacote_status([
        'success' => false,
        'message' => 'Acao invalida.',
    ]);
}

$pacotesAntes = carregar_pacotes_obra($conn, $obraId);
if (!tem_pacote_tipo($pacotesAntes, $pacoteTipo)) {
    responder_pacote_status([
        'success' => false,
        'message' => 'Esta obra nao possui o pacote informado.',
    ]);
}

$conn->begin_transaction();
try {
    $obraInativada = false;
    $statusConcluido = 'CONCLUIDO';
    $stmtPacote = $conn->prepare(
        "UPDATE obra_pacote SET status = ? WHERE obra_id = ? AND tipo = ?"
    );
    if (!$stmtPacote) {
        throw new RuntimeException('Erro ao preparar atualizacao do pacote: ' . $conn->error);
    }
    $stmtPacote->bind_param('sis', $statusConcluido, $obraId, $pacoteTipo);
    if (!$stmtPacote->execute()) {
        throw new RuntimeException('Erro ao concluir pacote: ' . $stmtPacote->error);
    }
    $stmtPacote->close();

    $animacaoAtivada = false;
    if ($pacoteTipo === 'STILL' && $ativarAnimacao && tem_pacote_tipo($pacotesAntes, 'ANIMACAO')) {
        $tipoAnimacao = 'ANIMACAO';
        $statusAtivo = 'ATIVO';
        $stmtAnimacao = $conn->prepare(
            "UPDATE obra_pacote SET status = ? WHERE obra_id = ? AND tipo = ?"
        );
        if (!$stmtAnimacao) {
            throw new RuntimeException('Erro ao preparar atualizacao do pacote de animacao: ' . $conn->error);
        }
        $stmtAnimacao->bind_param('sis', $statusAtivo, $obraId, $tipoAnimacao);
        if (!$stmtAnimacao->execute()) {
            throw new RuntimeException('Erro ao ativar pacote de animacao: ' . $stmtAnimacao->error);
        }
        $animacaoAtivada = $stmtAnimacao->affected_rows >= 0;
        $stmtAnimacao->close();
    }

    if (todos_pacotes_concluidos($conn, $obraId)) {
        marcar_obra_inativa($conn, $obraId);
        $obraInativada = true;
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    responder_pacote_status([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

$pacotesDepois = carregar_pacotes_obra($conn, $obraId);
$message = 'Pacote de ' . pacote_label($pacoteTipo) . ' marcado como concluido.';
if (!empty($animacaoAtivada)) {
    $message .= ' Pacote de animacao marcado como ativo.';
}
if (!empty($obraInativada)) {
    $message .= ' Todos os pacotes estao concluidos; obra marcada como inativa.';
}

responder_pacote_status([
    'success' => true,
    'message' => $message,
    'packages' => $pacotesDepois,
    'pacote_tipo' => $pacoteTipo,
    'obra_inativada' => !empty($obraInativada),
]);
