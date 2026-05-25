<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

// session_start();

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/onboarding_helpers.php';
require_once __DIR__ . '/../contact_architecture.php';

function onboarding_package_label(string $tipo): string
{
    $normalized = strtoupper(trim($tipo));
    if ($normalized === 'STILL') {
        return 'Still';
    }
    if ($normalized === 'ANIMACAO') {
        return 'Animacao';
    }
    if ($normalized === 'FILME') {
        return 'Filme';
    }

    return $tipo;
}

function onboarding_fetch_project_start_metadata(mysqli $conn, int $obraId): array
{
    if (!dashboard_table_has_column($conn, 'acompanhamento_email', 'metadata')) {
        return [];
    }

    $stmt = $conn->prepare("SELECT metadata FROM acompanhamento_email WHERE obra_id = ? AND tipo = 'PROJECT_START' ORDER BY idacompanhamento_email DESC LIMIT 1");
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return dashboard_decode_onboarding_metadata($row['metadata'] ?? null);
}

function onboarding_fetch_project_summary(mysqli $conn, int $obraId): array
{
    $obraColumns = ['idobra', 'nome_obra', 'status_obra'];
    if (dashboard_table_has_column($conn, 'obra', 'nomenclatura')) {
        $obraColumns[] = 'nomenclatura';
    }
    if (dashboard_table_has_column($conn, 'obra', 'nome_real')) {
        $obraColumns[] = 'nome_real';
    }

    $summary = [
        'client_name' => '',
        'project_internal' => '',
        'project_commercial' => '',
        'code' => '',
        'contacts_count' => 0,
        'images_count' => 0,
        'packages' => [],
        'notes' => '',
    ];

    $obraSql = 'SELECT ' . implode(', ', $obraColumns) . ' FROM obra WHERE idobra = ? LIMIT 1';
    $obraStmt = $conn->prepare($obraSql);
    if ($obraStmt) {
        $obraStmt->bind_param('i', $obraId);
        $obraStmt->execute();
        $obraResult = $obraStmt->get_result();
        $obraRow = $obraResult ? $obraResult->fetch_assoc() : null;
        $obraStmt->close();

        if ($obraRow) {
            $summary['project_internal'] = (string) ($obraRow['nome_obra'] ?? '');
            $summary['project_commercial'] = (string) ($obraRow['nome_real'] ?? '');
            $summary['code'] = (string) ($obraRow['nomenclatura'] ?? '');
        }
    }

    $metadata = onboarding_fetch_project_start_metadata($conn, $obraId);
    if (!empty($metadata)) {
        $summary['client_name'] = (string) ($metadata['cliente_nome'] ?? '');
        $summary['notes'] = (string) ($metadata['observacoes'] ?? '');
        $summary['contacts_count'] = is_array($metadata['contatos'] ?? null)
            ? count($metadata['contatos'])
            : 0;
    }

    if (dashboard_table_has_column($conn, 'imagens_cliente_obra', 'obra_id')) {
        $imagesStmt = $conn->prepare('SELECT COUNT(*) AS total_images FROM imagens_cliente_obra WHERE obra_id = ?');
        if ($imagesStmt) {
            $imagesStmt->bind_param('i', $obraId);
            $imagesStmt->execute();
            $imagesResult = $imagesStmt->get_result();
            $imagesRow = $imagesResult ? $imagesResult->fetch_assoc() : null;
            $imagesStmt->close();
            $summary['images_count'] = isset($imagesRow['total_images']) ? (int) $imagesRow['total_images'] : 0;
        }
    }

    $linkedContactsCount = contact_arch_contacts_count_for_obra($conn, $obraId);
    if ($linkedContactsCount > 0) {
        $summary['contacts_count'] = $linkedContactsCount;
    }

    if (dashboard_table_has_column($conn, 'obra_pacote', 'obra_id')) {
        $packageStmt = $conn->prepare("SELECT tipo, status, quantidade, segundos, prazo_contratual FROM obra_pacote WHERE obra_id = ? ORDER BY FIELD(tipo, 'STILL', 'ANIMACAO', 'FILME'), idobra_pacote ASC");
        if ($packageStmt) {
            $packageStmt->bind_param('i', $obraId);
            $packageStmt->execute();
            $packageResult = $packageStmt->get_result();
            while ($packageResult && ($packageRow = $packageResult->fetch_assoc())) {
                $summary['packages'][] = [
                    'tipo' => (string) ($packageRow['tipo'] ?? ''),
                    'label' => onboarding_package_label((string) ($packageRow['tipo'] ?? '')),
                    'status' => (string) ($packageRow['status'] ?? ''),
                    'quantidade' => isset($packageRow['quantidade']) ? (int) $packageRow['quantidade'] : null,
                    'segundos' => isset($packageRow['segundos']) ? (int) $packageRow['segundos'] : null,
                    'prazo_contratual' => isset($packageRow['prazo_contratual']) ? (int) $packageRow['prazo_contratual'] : null,
                ];
            }
            $packageStmt->close();
        }
    }

    if (empty($summary['packages']) && is_array($metadata['pacotes'] ?? null)) {
        foreach ($metadata['pacotes'] as $packageType) {
            $summary['packages'][] = [
                'tipo' => strtoupper((string) $packageType),
                'label' => onboarding_package_label((string) $packageType),
                'status' => '',
                'quantidade' => null,
                'segundos' => null,
                'prazo_contratual' => null,
            ];
        }
    }

    return $summary;
}

$obraId = isset($_GET['obra_id']) ? (int) $_GET['obra_id'] : 0;
if ($obraId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Obra inválida.']);
    exit;
}

$progress = dashboard_get_onboarding_progress_for_obra($conn, $obraId);
if (!$progress) {
    echo json_encode(['success' => true, 'is_onboarding' => false, 'obra_id' => $obraId]);
    exit;
}

$hasPendingItems = (int) ($progress['pending_items'] ?? 0) > 0;

echo json_encode([
    'success' => true,
    'is_onboarding' => $hasPendingItems,
    'obra_id' => $obraId,
    'status_obra' => $progress['status_obra'],
    'pending_items' => $progress['pending_items'],
    'completed_items' => $progress['completed_items'],
    'checklist' => $progress['checklist'],
    'summary' => onboarding_fetch_project_summary($conn, $obraId),
]);
