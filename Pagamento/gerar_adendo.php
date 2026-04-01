<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
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

    // Generate to a temp dir first — the user must approve before we save to the final location
    $pdfDirFinal = __DIR__ . '/../Contratos/gerados/adendos/' . $dt->format('Y') . '_' . $dt->format('m') . '_' . $mesNome;
    $pdfDir = __DIR__ . '/../Contratos/gerados/adendos/temp';
    if (!is_dir($pdfDir)) {
        if (!mkdir($pdfDir, 0775, true) && !is_dir($pdfDir)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Não foi possível criar pasta temporária.']);
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

    $arquivoPath = $resp['arquivo_path'] ?? '';
    $baseGerados  = realpath(__DIR__ . '/../Contratos/gerados');
    $realArquivo  = $arquivoPath ? (realpath($arquivoPath) ?: $arquivoPath) : '';

    if ($baseGerados && $realArquivo) {
        $tempRel = ltrim(str_replace($baseGerados, '', $realArquivo), DIRECTORY_SEPARATOR);
        $tempRel = str_replace('\\', '/', $tempRel);  // always forward slashes

        // Store pending adendo in session so confirmar_adendo.php can validate & move it
        $_SESSION['adendo_pendente'] = [
            'temp_rel'    => $tempRel,
            'final_dir'   => $pdfDirFinal,
            'colaborador' => $colaboradorId,
            'mes'         => $mes,
            'ano'         => $ano,
        ];

        $resp['temp_rel']   = $tempRel;
        $resp['preview_url'] = 'ver_adendo.php'; // served inline via PHP (no path traversal risk)
    }

    // Remove direct download_url — the file is still in temp and must be approved first
    unset($resp['download_url']);

    echo json_encode($resp);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
