<?php

/**
 * servir_planta_pdf.php
 * Serve o PDF de uma planta do Mapa de Compatibilização.
 *
 * - 1 arquivo_id  → proxy direto dos bytes do PDF
 * - N arquivo_ids → merge real com FPDI e stream do PDF unificado
 *
 * GET params:
 *   planta_id  (int, obrigatório)
 *   download   (int, opcional) → se 1, força attachment
 */

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    exit('Não autenticado.');
}

$plantaId = isset($_GET['planta_id']) ? (int) $_GET['planta_id'] : 0;
if ($plantaId <= 0) {
    http_response_code(400);
    exit('planta_id inválido.');
}

$conn = conectarBanco();
$stmt = $conn->prepare(
    "SELECT pc.arquivo_id, pc.arquivo_ids_json, pc.pdf_unificado_path, pc.versao,
            o.nome_obra
     FROM planta_compatibilizacao pc
     JOIN obra o ON o.idobra = pc.obra_id
     WHERE pc.id = ? AND pc.ativa = 1
     LIMIT 1"
);
$stmt->bind_param('i', $plantaId);
$stmt->execute();
$planta = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$planta) {
    http_response_code(404);
    exit('Planta não encontrada.');
}

// ---------- PDF unificado salvo em disco? Servir direto ----------
if (!empty($planta['pdf_unificado_path'])) {
    $localPath = realpath(__DIR__ . '/../' . ltrim($planta['pdf_unificado_path'], '/'));
    if ($localPath && is_file($localPath) && is_readable($localPath)) {
        $download = isset($_GET['download']) && (int) $_GET['download'] === 1;
        $obraNome = preg_replace('/[^A-Za-z0-9_\-]/u', '_', $planta['nome_obra'] ?? 'planta');
        $versao   = (int) $planta['versao'];
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="planta_' . $obraNome . '_v' . $versao . '.pdf"');
        header('Content-Length: ' . filesize($localPath));
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($localPath);
        exit;
    }
    // Arquivo local não encontrado — cai no fallback abaixo
}

// ---------- Montar lista de IDs ----------
$arquivoIds = [];
if (!empty($planta['arquivo_ids_json'])) {
    $ids = json_decode($planta['arquivo_ids_json'], true);
    if (is_array($ids)) $arquivoIds = array_map('intval', $ids);
}
if (empty($arquivoIds) && !empty($planta['arquivo_id'])) {
    $arquivoIds = [(int) $planta['arquivo_id']];
}
if (empty($arquivoIds)) {
    http_response_code(404);
    exit('Nenhum arquivo PDF associado a esta planta.');
}

// ---------- Buscar caminhos dos arquivos ----------
$conn2 = conectarBanco();
$in    = implode(',', array_fill(0, count($arquivoIds), '?'));
$types = str_repeat('i', count($arquivoIds));
$stmtA = $conn2->prepare(
    "SELECT idarquivo, caminho, nome_original, nome_interno
     FROM arquivos
     WHERE idarquivo IN ($in)
     ORDER BY FIELD(idarquivo, $in)"
);
// bind_param requires exact count
$params = array_merge($arquivoIds, $arquivoIds);
$typesDouble = str_repeat('i', count($params));
$stmtA->bind_param($typesDouble, ...$params);
$stmtA->execute();
$rows = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtA->close();
$conn2->close();

if (empty($rows)) {
    http_response_code(404);
    exit('Arquivos não encontrados no banco.');
}

// ---------- SFTP singleton para reuso entre arquivos ----------
$_sftp_fallback = null;
function fetch_pdf_bytes(array $row): string|false
{
    global $_sftp_fallback;

    $caminho = preg_replace('#/+#', '/', str_replace('\\', '/', $row['caminho'] ?? ''));

    // Tenta local primeiro
    if ($caminho !== '' && @is_file($caminho) && @is_readable($caminho)) {
        return @file_get_contents($caminho);
    }

    // Fallback SFTP (reutiliza conexão aberta)
    if ($_sftp_fallback === null) {
        require_once __DIR__ . '/../config/secure_env.php';
        try {
            $cfg = improov_sftp_config();
        } catch (\RuntimeException $e) {
            return false;
        }
        $sftp = new \phpseclib3\Net\SFTP($cfg['host'], (int) $cfg['port']);
        if (!$sftp->login($cfg['user'], $cfg['pass'])) return false;
        $_sftp_fallback = $sftp;
    }

    $data = $_sftp_fallback->get($caminho);
    return $data === false ? false : $data;
}

// ---------- Preparar nome do arquivo de saída ----------
$obraNome = preg_replace('/[^A-Za-z0-9_\-]/u', '_', $planta['nome_obra'] ?? 'planta');
$versao   = (int) $planta['versao'];
$filename = "planta_{$obraNome}_v{$versao}.pdf";
$download = isset($_GET['download']) && (int) $_GET['download'] === 1;

header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// ---------- Um único arquivo → stream direto ----------
if (count($rows) === 1) {
    $bytes = fetch_pdf_bytes($rows[0]);
    if ($bytes === false) {
        http_response_code(502);
        exit('Falha ao buscar PDF do servidor de arquivos.');
    }
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}

// ---------- Múltiplos → merge com FPDI ----------
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

$pdf = new Fpdi();
// FPDI extends FPDF (sem header/footer por padrão)

$tmpFiles = [];
$mergedAtLeast1 = false;

foreach ($rows as $row) {
    $bytes = fetch_pdf_bytes($row);
    if ($bytes === false) {
        // pular arquivo com falha, não abortar todo o merge
        continue;
    }

    // FPDI precisa ler de arquivo ou StreamReader para PDF não-comprimido
    try {
        $pageCount = $pdf->setSourceFile(StreamReader::createByString($bytes));
        for ($pg = 1; $pg <= $pageCount; $pg++) {
            $tplId = $pdf->importPage($pg);
            $size  = $pdf->getTemplateSize($tplId);

            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);
        }
        $mergedAtLeast1 = true;
    } catch (\Throwable $e) {
        // PDF corrompido ou criptografado — pular
        continue;
    }
}

// Limpar arquivos temporários
foreach ($tmpFiles as $f) {
    @unlink($f);
}

if (!$mergedAtLeast1) {
    http_response_code(502);
    exit('Não foi possível processar nenhum dos arquivos PDF.');
}

// Output('S') retorna os bytes do PDF como string
$pdfString = $pdf->Output('S');

header('Content-Length: ' . strlen($pdfString));
echo $pdfString;
exit;
