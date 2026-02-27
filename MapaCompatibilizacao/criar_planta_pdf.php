<?php
/**
 * criar_planta_pdf.php
 * Cria um registro em planta_compatibilizacao a partir de um ou
 * mais arquivos PDF já existentes na tabela `arquivos`.
 *
 * Quando múltiplos PDFs são selecionados:
 *   - Invoca scripts/merge_pdfs_ids.py para fazer o merge (suporta PDF 1.5+)
 *   - Salva o PDF unificado em uploads/plantas/{obra_id}/
 *   - Grava o caminho em pdf_unificado_path
 *   - servir_planta_pdf.php usa o arquivo local direto, sem SFTP
 *
 * POST JSON body:
 *   obra_id          (int, obrigatório)
 *   arquivo_id       (int, quando apenas 1 PDF selecionado)
 *   arquivo_ids_json (string JSON, ex: "[1,2,3]", quando múltiplos)
 */

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';

header('Content-Type: application/json; charset=utf-8');

// Timeout estendido: merge de 35 PDFs via SFTP pode levar alguns minutos
set_time_limit(600);
ini_set('memory_limit', '256M');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$obraId         = isset($input['obra_id'])         ? (int) $input['obra_id']          : 0;
$arquivoId      = isset($input['arquivo_id'])       ? (int) $input['arquivo_id']       : null;
$arquivoIdsJson = isset($input['arquivo_ids_json']) ? trim($input['arquivo_ids_json'])  : null;

if ($obraId <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'obra_id inválido.']);
    exit();
}
if (!$arquivoId && !$arquivoIdsJson) {
    echo json_encode(['sucesso' => false, 'erro' => 'Informe arquivo_id ou arquivo_ids_json.']);
    exit();
}

// ── Normalizar IDs ─────────────────────────────────────────────────────────
$ids = [];
if ($arquivoIdsJson) {
    $ids = json_decode($arquivoIdsJson, true);
    if (!is_array($ids) || empty($ids)) {
        echo json_encode(['sucesso' => false, 'erro' => 'arquivo_ids_json inválido.']);
        exit();
    }
    $ids            = array_map('intval', $ids);
    $arquivoIdsJson = json_encode($ids);
    $arquivoId      = null;
} else {
    $ids = [$arquivoId];
}

// ── Merge via Python (somente quando múltiplos arquivos) ───────────────────
$pdfUnificadoPath = null;

if (count($ids) > 1) {

    // Garantir diretório de saída
    $uploadDir = __DIR__ . "/../uploads/plantas/{$obraId}";
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível criar o diretório de uploads.']);
        exit();
    }

    $filename   = 'planta_unif_' . time() . '.pdf';
    $filePath   = $uploadDir . '/' . $filename;
    $scriptPath = __DIR__ . '/../scripts/merge_pdfs_ids.py';
    $idsStr     = implode(',', $ids);

    // Montar comando — escapar argumentos
    $cmd = sprintf(
        'py %s --ids %s --saida %s 2>&1',
        escapeshellarg($scriptPath),
        escapeshellarg($idsStr),
        escapeshellarg($filePath)
    );

    $output   = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    $stdout = implode("\n", $output);

    if ($exitCode !== 0 || !str_starts_with(trim($stdout), 'OK:')) {
        echo json_encode([
            'sucesso'  => false,
            'erro'     => 'Falha ao gerar PDF unificado.',
            'detalhes' => $stdout,
        ]);
        exit();
    }

    if (!is_file($filePath) || filesize($filePath) === 0) {
        echo json_encode(['sucesso' => false, 'erro' => 'PDF gerado está vazio ou inexistente.']);
        exit();
    }

    $pdfUnificadoPath = "uploads/plantas/{$obraId}/{$filename}";
}

// ── Inserir registro em planta_compatibilizacao ────────────────────────────
$conn  = conectarBanco();
$stmtV = $conn->prepare(
    "SELECT COALESCE(MAX(versao), 0) + 1 AS prox
     FROM planta_compatibilizacao
     WHERE obra_id = ?"
);
$stmtV->bind_param('i', $obraId);
$stmtV->execute();
$versao = (int) $stmtV->get_result()->fetch_assoc()['prox'];
$stmtV->close();

$criadoPor = $_SESSION['idcolaborador'] ?? null;

$stmt = $conn->prepare(
    "INSERT INTO planta_compatibilizacao
         (obra_id, imagem_id, pagina_pdf, versao, imagem_path,
          arquivo_id, arquivo_ids_json, pdf_unificado_path, ativa, criado_por)
     VALUES (?, NULL, NULL, ?, '', ?, ?, ?, 1, ?)"
);
$stmt->bind_param(
    'iiissi',
    $obraId,
    $versao,
    $arquivoId,
    $arquivoIdsJson,
    $pdfUnificadoPath,
    $criadoPor
);
$stmt->execute();
$newId = $conn->insert_id;
$stmt->close();
$conn->close();

if ($newId) {
    echo json_encode([
        'sucesso'   => true,
        'planta_id' => $newId,
        'versao'    => $versao,
    ]);
} else {
    echo json_encode(['sucesso' => false, 'erro' => 'Falha ao inserir planta.']);
}