<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

include __DIR__ . '/../conexao.php';

$mode = isset($_GET['mode']) ? (string) $_GET['mode'] : 'single';
// ── Mode: by_id ─────────────────────────────────────────────────────────────
if ($mode === 'by_id') {
    $adendoId = isset($_GET['adendo_id']) ? intval($_GET['adendo_id']) : 0;
    if (!$adendoId) {
        echo json_encode(['success' => true, 'adendo' => null, 'log' => []]);
        $conn->close();
        exit;
    }
    $stmt = $conn->prepare(
        "SELECT a.id, a.colaborador_id, a.competencia, a.status,
                a.data_envio, a.assinado_em, a.arquivo_nome, a.sign_url,
                a.updated_at, a.created_at,
                c.nome_colaborador
         FROM adendos a
         JOIN colaborador c ON c.idcolaborador = a.colaborador_id
         WHERE a.id = ? LIMIT 1"
    );
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $conn->error]);
        $conn->close();
        exit;
    }
    $stmt->bind_param('i', $adendoId);
    $stmt->execute();
    $adendo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $log = [];
    if ($adendo) {
        $stmtLog = $conn->prepare(
            "SELECT id, status, acao, origem, ip, detalhe, ocorrido_em
             FROM log_adendos WHERE adendo_id = ? ORDER BY ocorrido_em ASC"
        );
        if ($stmtLog) {
            $stmtLog->bind_param('i', $adendo['id']);
            $stmtLog->execute();
            $resLog = $stmtLog->get_result();
            while ($row = $resLog->fetch_assoc())
                $log[] = $row;
            $stmtLog->close();
        }
    }
    echo json_encode(['success' => true, 'adendo' => $adendo, 'log' => $log]);
    $conn->close();
    exit;
}
if ($mode === 'geral') {
    // ── Visão geral: todos os adendos ──────────────────────────────────────
    $compRef = (new DateTime('first day of last month'))->format('Y-m');

    $sql = "SELECT a.id, a.colaborador_id, c.nome_colaborador,
                   a.competencia, a.status,
                   a.data_envio, a.assinado_em, a.arquivo_nome,
                   a.updated_at
            FROM adendos a
            JOIN colaborador c ON c.idcolaborador = a.colaborador_id
            WHERE a.competencia = ?
            ORDER BY a.updated_at DESC";

    $stmtGeral = $conn->prepare($sql);
    $result = false;
    if ($stmtGeral) {
        $stmtGeral->bind_param('s', $compRef);
        $stmtGeral->execute();
        $result = $stmtGeral->get_result();
    }

    $items = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    if ($stmtGeral) {
        $stmtGeral->close();
    }

    $counts = [
        'assinado' => 0,
        'visualizado' => 0,
        'enviado' => 0,
        'nao_enviado' => 0,
        'total' => count($items),
    ];

    foreach ($items as $item) {
        $s = $item['status'] ?? '';
        if ($s === 'assinado')
            $counts['assinado']++;
        elseif ($s === 'visualizado')
            $counts['visualizado']++;
        elseif ($s === 'enviado')
            $counts['enviado']++;
        else
            $counts['nao_enviado']++;
    }

    echo json_encode([
        'success' => true,
        'competencia_ativa' => $compRef,
        'counts' => $counts,
        'items' => $items,
    ]);
    $conn->close();
    exit;
}

// ── Modo single: um colaborador/competência ────────────────────────────────
$colaboradorId = isset($_GET['colaborador_id']) ? intval($_GET['colaborador_id']) : 0;
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : 0;
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : 0;

if (!$colaboradorId || $mes < 1 || $mes > 12 || $ano < 2000) {
    echo json_encode(['success' => true, 'adendo' => null, 'log' => []]);
    $conn->close();
    exit;
}

$competencia = sprintf('%04d-%02d', $ano, $mes);

$stmt = $conn->prepare(
    "SELECT a.id, a.colaborador_id, a.competencia, a.status,
            a.data_envio, a.assinado_em, a.arquivo_nome, a.sign_url,
            a.updated_at, a.created_at,
            c.nome_colaborador
     FROM adendos a
     JOIN colaborador c ON c.idcolaborador = a.colaborador_id
     WHERE a.colaborador_id = ? AND a.competencia = ?
     LIMIT 1"
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Erro de consulta: ' . $conn->error]);
    $conn->close();
    exit;
}
$stmt->bind_param('is', $colaboradorId, $competencia);
$stmt->execute();
$adendo = $stmt->get_result()->fetch_assoc();
$stmt->close();

$log = [];
if ($adendo) {
    $stmtLog = $conn->prepare(
        "SELECT id, status, acao, origem, ip, detalhe, ocorrido_em
         FROM log_adendos
         WHERE adendo_id = ?
         ORDER BY ocorrido_em ASC"
    );
    if ($stmtLog) {
        $stmtLog->bind_param('i', $adendo['id']);
        $stmtLog->execute();
        $resLog = $stmtLog->get_result();
        while ($row = $resLog->fetch_assoc()) {
            $log[] = $row;
        }
        $stmtLog->close();
    }
}

echo json_encode(['success' => true, 'adendo' => $adendo, 'log' => $log]);
$conn->close();
