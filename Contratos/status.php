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
require_once __DIR__ . '/services/ContratoDateService.php';
require_once __DIR__ . '/services/ContratoManagementService.php';

$conn = conectarBanco();
$service = new ContratoManagementService($conn);
$mode = (string) ($_GET['mode'] ?? 'single');

try {
    if ($mode === 'dashboard') {
        $competencia = trim((string) ($_GET['competencia'] ?? $service->getCompetenciaAtual()));
        $dashboard = $service->getDashboard($competencia);
        echo json_encode([
            'success' => true,
            'competencias' => $service->getCompetenciasDisponiveis(),
            ...$dashboard,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($mode === 'historico') {
        $contratoId = (int) ($_GET['contrato_id'] ?? 0);
        if (!$contratoId) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'contrato_id obrigatório.']);
            exit;
        }
        $data = $service->getHistorico($contratoId);
        echo json_encode(['success' => true, ...($data ?: ['contrato' => null, 'historico' => []])], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Compatibilidade com os consumidores antigos do endpoint, agora sempre
    // buscando a competência solicitada (ou a competência vigente).
    $competencia = trim((string) ($_GET['competencia'] ?? $service->getCompetenciaAtual()));
    $dashboard = $service->getDashboard($competencia);
    $itemsById = [];
    foreach ($dashboard['items'] as $item) {
        $itemsById[$item['colaborador_id']] = $item;
    }

    $colaboradorIdsRaw = trim((string) ($_GET['colaborador_ids'] ?? ''));
    if ($colaboradorIdsRaw !== '') {
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $colaboradorIdsRaw)))));
        $items = [];
        foreach ($ids as $id) {
            $items[$id] = $itemsById[$id] ?? [
                'colaborador_id' => $id,
                'competencia' => $competencia,
                'status' => 'nao_gerado',
                'download_url' => null,
                'arquivo_nome' => null,
            ];
        }
        echo json_encode(['success' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $colaboradorId = (int) ($_GET['colaborador_id'] ?? 0);
    if (!$colaboradorId) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'colaborador_id ou colaborador_ids obrigatório.']);
        exit;
    }
    echo json_encode(['success' => true, ...($itemsById[$colaboradorId] ?? [
        'colaborador_id' => $colaboradorId,
        'competencia' => $competencia,
        'status' => 'nao_gerado',
        'download_url' => null,
        'arquivo_nome' => null,
    ])], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível carregar os contratos.']);
} finally {
    $conn->close();
}
