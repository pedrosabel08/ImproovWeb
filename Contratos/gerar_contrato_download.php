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
    $service = new ContratoLocalService(
        $conn,
        new ContratoDataService($conn),
        new ContratoDateService(),
        new ContratoQualificacaoService(),
        new Clausula1Service(),
        new Clausula17Service(),
        new ContratoPdfService(__DIR__ . '/gerados')
    );

    $resp = $service->gerarContrato($colaboradorId, $competencia);
    if (empty($resp['success']) || $resp['success'] !== true) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Falha ao gerar contrato.']);
        exit;
    }

    if (empty($resp['arquivo_path']) || !is_file($resp['arquivo_path'])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Arquivo PDF não encontrado: ' . ($resp['arquivo_path'] ?? 'n/d')]);
        exit;
    }

    // Forçar download
    $filePath = $resp['arquivo_path'];
    $fileName = basename($filePath);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
    header('Content-Length: ' . filesize($filePath));
    // limpar buffer e ler arquivo
    while (ob_get_level()) ob_end_clean();
    readfile($filePath);
    exit;

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
