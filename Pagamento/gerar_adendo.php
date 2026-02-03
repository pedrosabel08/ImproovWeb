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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$colaboradorId = isset($data['colaborador_id']) ? (int)$data['colaborador_id'] : 0;
$mes = isset($data['mes']) ? (int)$data['mes'] : 0;
$ano = isset($data['ano']) ? (int)$data['ano'] : 0;
$valorFixo = isset($data['valor_fixo']) ? (float)str_replace(',', '.', (string)$data['valor_fixo']) : 0.0;
$funcoes = isset($data['funcoes']) && is_array($data['funcoes']) ? $data['funcoes'] : [];
$extras = isset($data['extras']) && is_array($data['extras']) ? $data['extras'] : [];
$itens = isset($data['itens']) && is_array($data['itens']) ? $data['itens'] : [];

if (!$colaboradorId || $mes < 1 || $mes > 12 || $ano < 2000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}


require_once __DIR__ . '/../Contratos/services/ContratoDataService.php';
require_once __DIR__ . '/../Contratos/services/ContratoDateService.php';
require_once __DIR__ . '/../Contratos/services/ContratoQualificacaoService.php';
require_once __DIR__ . '/../Contratos/services/ContratoPdfService.php';
require_once __DIR__ . '/../Contratos/services/AdendoLocalService.php';

$conn = conectarBanco();

try {
    $dt = DateTimeImmutable::createFromFormat('Y-m', sprintf('%04d-%02d', $ano, $mes)) ?: new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    $mesNome = $meses[(int)$dt->format('m')] ?? $dt->format('m');

    $pdfDir = __DIR__ . '/../Contratos/gerados/adendos/' . $dt->format('Y') . '_' . $dt->format('m') . '_' . $mesNome;
    if (!is_dir($pdfDir)) {
        if (!mkdir($pdfDir, 0775, true) && !is_dir($pdfDir)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Não foi possível criar pasta para PDFs: ' . $pdfDir]);
            exit;
        }
    }

    $templatePath = __DIR__ . '/../Contratos/templates/adendo_modelo.html';
    $service = new AdendoLocalService(
        $conn,
        new ContratoDataService($conn),
        new ContratoDateService(),
        new ContratoQualificacaoService(),
        new ContratoPdfService($pdfDir, $templatePath)
    );

    $resp = $service->gerarAdendo($colaboradorId, $mes, $ano, $valorFixo, $funcoes, $extras, $itens);

    $baseGerados = realpath(__DIR__ . '/../Contratos/gerados');
    $arquivoPath = $resp['arquivo_path'] ?? '';
    if ($baseGerados && $arquivoPath) {
        $rel = ltrim(str_replace($baseGerados, '', realpath($arquivoPath) ?: $arquivoPath), DIRECTORY_SEPARATOR);
        $resp['download_url'] = '../Contratos/download.php?arquivo=' . rawurlencode($rel);
    }

    echo json_encode($resp);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
