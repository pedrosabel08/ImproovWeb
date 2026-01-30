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

if (!isset($_SESSION['nivel_acesso']) || (int)$_SESSION['nivel_acesso'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit;
}

include __DIR__ . '/../conexao.php';
include __DIR__ . '/../conexaoMain.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$colaboradorId = isset($data['colaborador_id']) ? (int)$data['colaborador_id'] : 0;
$competencia = isset($data['competencia']) ? trim((string)$data['competencia']) : null;

if (!$colaboradorId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'colaborador_id obrigatório.']);
    exit;
}

require_once __DIR__ . '/services/ContratoDataService.php';
require_once __DIR__ . '/services/ContratoDateService.php';
require_once __DIR__ . '/services/ContratoQualificacaoService.php';
require_once __DIR__ . '/services/Clausula1Service.php';
require_once __DIR__ . '/services/Clausula17Service.php';
require_once __DIR__ . '/services/ContratoPdfService.php';
require_once __DIR__ . '/services/ContratoLocalService.php';

$conn = conectarBanco();

try {
    // Criar pasta por mês/ano de vigência (ex: gerados/2026_01_Janeiro)
    $competenciaForDir = $competencia ?: (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m');
    $dt = DateTimeImmutable::createFromFormat('Y-m', $competenciaForDir) ?: DateTimeImmutable::createFromFormat('Y-m-d', $competenciaForDir) ?: new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    $mesNome = $meses[(int)$dt->format('m')];
    $pdfDir = __DIR__ . '/gerados/' . $dt->format('Y') . '_' . $dt->format('m') . '_' . $mesNome;
    if (!is_dir($pdfDir)) {
        if (!mkdir($pdfDir, 0775, true) && !is_dir($pdfDir)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Não foi possível criar pasta para PDFs: ' . $pdfDir]);
            exit;
        }
    }
    $service = new ContratoLocalService(
        $conn,
        new ContratoDataService($conn),
        new ContratoDateService(),
        new ContratoQualificacaoService(),
        new Clausula1Service(),
        new Clausula17Service(),
        new ContratoPdfService($pdfDir)
    );

    $resp = $service->gerarContrato($colaboradorId, $competencia);
    // Calcular caminho relativo dentro de gerados/ para download seguro
    $baseGerados = realpath(__DIR__ . '/gerados');
    $arquivoPath = $resp['arquivo_path'] ?? '';
    if ($baseGerados && $arquivoPath) {
        $rel = ltrim(str_replace($baseGerados, '', realpath($arquivoPath) ?: $arquivoPath), DIRECTORY_SEPARATOR);
        $resp['download_url'] = './download.php?arquivo=' . rawurlencode($rel);
    } else {
        $resp['download_url'] = './download.php?arquivo=' . rawurlencode(basename($resp['arquivo_nome'] ?? ''));
    }
    echo json_encode($resp);
} catch (Throwable $e) {
    http_response_code(500);
    $resp = [
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ];
    echo json_encode($resp);
}

$conn->close();
