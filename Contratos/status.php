<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

include __DIR__ . '/../conexao.php';
include __DIR__ . '/../conexaoMain.php';

// Support single collaborator via ?colaborador_id=ID or multiple via ?colaborador_ids=1,2,3
$colaboradorId = isset($_GET['colaborador_id']) ? (int)$_GET['colaborador_id'] : 0;
$colaboradorIdsRaw = isset($_GET['colaborador_ids']) ? trim((string)$_GET['colaborador_ids']) : '';
$competencia = isset($_GET['competencia']) ? trim((string)$_GET['competencia']) : null;

$conn = conectarBanco();

// Helper to build download_url from arquivo_path or arquivo_nome
function build_download_url(array $row): ?string
{
    $baseDir = realpath(__DIR__ . '/gerados');
    // prefer arquivo_path if available
    $arquivoPath = $row['arquivo_path'] ?? null;
    if ($arquivoPath) {
        $real = realpath($arquivoPath);
        if ($real && $baseDir && strpos($real, $baseDir) === 0) {
            $rel = ltrim(str_replace($baseDir, '', $real), DIRECTORY_SEPARATOR);
            return './download.php?arquivo=' . rawurlencode($rel);
        }
    }
    $arquivoNome = $row['arquivo_nome'] ?? null;
    if ($arquivoNome) {
        return './download.php?arquivo=' . rawurlencode($arquivoNome);
    }
    return null;
}

if ($colaboradorIdsRaw !== '') {
    // parse ids
    $ids = array_values(array_filter(array_map('intval', explode(',', $colaboradorIdsRaw))));
    if (empty($ids)) {
        echo json_encode(['success' => true, 'items' => []]);
        $conn->close();
        exit;
    }

    // build place-holders for prepared statement
    $in = implode(',', array_fill(0, count($ids), '?'));
    // select latest contract per colaborador using a derived table of max id per collaborator
    $sql = "SELECT c1.* FROM contratos c1 JOIN (SELECT colaborador_id, MAX(id) as maxid FROM contratos WHERE colaborador_id IN ($in) GROUP BY colaborador_id) c2 ON c1.colaborador_id = c2.colaborador_id AND c1.id = c2.maxid";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Falha na preparação da consulta.']);
        $conn->close();
        exit;
    }
    // bind params dynamically
    $types = str_repeat('i', count($ids));
    // bind params by reference for mysqli
    $bindParams = array_merge([$types], $ids);
    $refs = [];
    foreach ($bindParams as $k => $v) {
        $refs[$k] = &$bindParams[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[$r['colaborador_id']] = [
            'success' => true,
            'competencia' => $r['competencia'] ?? null,
            'status' => $r['status'] ?? 'nao_gerado',
            'zapsign_doc_token' => $r['zapsign_doc_token'] ?? null,
            'arquivo_nome' => $r['arquivo_nome'] ?? null,
            'download_url' => build_download_url($r)
        ];
    }
    // ensure all requested ids present
    $result = [];
    foreach ($ids as $id) {
        if (isset($rows[$id])) {
            $result[$id] = $rows[$id];
        } else {
            $result[$id] = [
                'success' => true,
                'competencia' => null,
                'status' => 'nao_gerado',
                'zapsign_doc_token' => null,
                'arquivo_nome' => null,
                'download_url' => null
            ];
        }
    }
    echo json_encode(['success' => true, 'items' => $result]);
    $stmt->close();
    $conn->close();
    exit;

} elseif ($colaboradorId) {
    // single collaborator (backwards compatible)
    if ($competencia) {
        $sql = "SELECT * FROM contratos WHERE colaborador_id = ? AND competencia = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $colaboradorId, $competencia);
    } else {
        $sql = "SELECT * FROM contratos WHERE colaborador_id = ? ORDER BY competencia DESC, id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $colaboradorId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $conn->close();

    if (!$row) {
        echo json_encode([
            'success' => true,
            'competencia' => null,
            'status' => 'nao_gerado',
            'zapsign_doc_token' => null,
            'arquivo_nome' => null,
            'download_url' => null
        ]);
        exit;
    }

    $downloadUrl = build_download_url($row);

    echo json_encode([
        'success' => true,
        'competencia' => $row['competencia'],
        'status' => $row['status'],
        'zapsign_doc_token' => $row['zapsign_doc_token'],
        'arquivo_nome' => $row['arquivo_nome'] ?? null,
        'download_url' => $downloadUrl
    ]);
    exit;

} else {
    echo json_encode(['success' => false, 'message' => 'colaborador_id ou colaborador_ids obrigatório.']);
    $conn->close();
    exit;
}
