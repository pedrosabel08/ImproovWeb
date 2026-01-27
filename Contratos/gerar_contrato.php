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
require_once __DIR__ . '/services/ZapSignConfig.php';

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

$zapsignToken = ZapSignConfig::getToken();
$zapsignTemplateId = ZapSignConfig::getTemplateId();

if ($zapsignToken === '' || $zapsignTemplateId === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuração ZapSign ausente.']);
    exit;
}

require_once __DIR__ . '/services/ContratoDataService.php';
require_once __DIR__ . '/services/ContratoDateService.php';
require_once __DIR__ . '/services/ContratoQualificacaoService.php';
require_once __DIR__ . '/services/Clausula17Service.php';
require_once __DIR__ . '/services/ZapSignClient.php';
require_once __DIR__ . '/services/ContratoService.php';

$conn = conectarBanco();

try {
    $zapApiUrl = ZapSignConfig::getApiUrl();
    $service = new ContratoService(
        $conn,
        new ContratoDataService($conn),
        new ContratoDateService(),
        new ContratoQualificacaoService(),
        new Clausula17Service(),
        new ZapSignClient($zapsignToken, $zapApiUrl),
        $zapsignTemplateId,
        ZapSignConfig::isSandbox()
    );

    $resp = $service->gerarContrato($colaboradorId, $competencia);
    echo json_encode($resp);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
