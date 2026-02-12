<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$allowedEditors = [1, 2, 9];
$userId = isset($_SESSION['idusuario']) ? intval($_SESSION['idusuario']) : 0;
if (!$userId || !in_array($userId, $allowedEditors, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão']);
    exit;
}

require '../conexao.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$obraId = isset($data['obra_id']) ? intval($data['obra_id']) : 0;
$tipoImagem = isset($data['tipo_imagem']) ? trim((string)$data['tipo_imagem']) : '';
$categoria = isset($data['categoria']) ? trim((string)$data['categoria']) : '';
$tipoArquivo = isset($data['tipo_arquivo']) ? trim((string)$data['tipo_arquivo']) : '';

if (!$obraId || $tipoImagem === '' || $categoria === '' || $tipoArquivo === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
    exit;
}

try {
    // 1) resolve briefing_tipo_imagem
    $stmtTipo = $conn->prepare('SELECT id FROM briefing_tipo_imagem WHERE obra_id = ? AND tipo_imagem = ? LIMIT 1');
    if ($stmtTipo === false) {
        throw new RuntimeException('Erro ao preparar select tipo: ' . $conn->error);
    }
    $stmtTipo->bind_param('is', $obraId, $tipoImagem);
    $stmtTipo->execute();
    $resTipo = $stmtTipo->get_result();
    $rowTipo = $resTipo ? $resTipo->fetch_assoc() : null;
    $stmtTipo->close();

    $tipoId = $rowTipo ? intval($rowTipo['id']) : 0;
    if (!$tipoId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Tipo de imagem não encontrado no briefing']);
        exit;
    }

    // 2) obtém requisito atual
    $stmtSel = $conn->prepare(
        "SELECT id, status FROM briefing_requisitos_arquivo
         WHERE briefing_tipo_imagem_id = ?
           AND categoria = ?
           AND tipo_arquivo = ?
           AND origem = 'cliente'
         LIMIT 1"
    );
    if ($stmtSel === false) {
        throw new RuntimeException('Erro ao preparar select requisito: ' . $conn->error);
    }
    $stmtSel->bind_param('iss', $tipoId, $categoria, $tipoArquivo);
    $stmtSel->execute();
    $resReq = $stmtSel->get_result();
    $rowReq = $resReq ? $resReq->fetch_assoc() : null;
    $stmtSel->close();

    if (!$rowReq) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Requisito não encontrado']);
        exit;
    }

    $reqId = intval($rowReq['id']);
    $fromStatus = strtolower((string)($rowReq['status'] ?? ''));
    if ($fromStatus !== 'recebido') {
        echo json_encode(['success' => true, 'data' => ['updated' => 0]]);
        exit;
    }

    // 3) valida (somente RECEBIDO -> VALIDADO)
    $stmtUp = $conn->prepare(
        "UPDATE briefing_requisitos_arquivo
         SET status = 'validado', updated_at = CURRENT_TIMESTAMP
         WHERE id = ? AND status = 'recebido'"
    );
    if ($stmtUp === false) {
        throw new RuntimeException('Erro ao preparar update: ' . $conn->error);
    }
    $stmtUp->bind_param('i', $reqId);
    $stmtUp->execute();
    $affected = $stmtUp->affected_rows;
    $stmtUp->close();

    if ($affected > 0) {
        // 4) log
        $stmtLog = $conn->prepare(
            "INSERT INTO briefing_requisitos_arquivo_log
                (requisito_id, obra_id, tipo_imagem, categoria, tipo_arquivo, from_status, to_status, action, arquivo_id, usuario_id)
             VALUES (?, ?, ?, ?, ?, 'recebido', 'validado', 'validar', NULL, ?)"
        );
        if ($stmtLog) {
            $stmtLog->bind_param('iisssi', $reqId, $obraId, $tipoImagem, $categoria, $tipoArquivo, $userId);
            $stmtLog->execute();
            $stmtLog->close();
        }
    }

    echo json_encode(['success' => true, 'data' => ['updated' => $affected]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno', 'details' => $e->getMessage()]);
}
