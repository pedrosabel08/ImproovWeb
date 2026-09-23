<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/secure_env.php';
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['logado'] ?? false) !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}
if (!in_array((int) ($_SESSION['nivel_acesso'] ?? 0), [1, 5], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Use POST.']);
    exit;
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/image_import_helpers.php';
require_once __DIR__ . '/onboarding_helpers.php';
require_once __DIR__ . '/onboarding_commercial_helpers.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
    exit;
}

$obraId = (int) ($payload['obra_id'] ?? 0);
$rawImages = is_array($payload['images'] ?? null) ? $payload['images'] : [];
$photoServiceValue = trim((string) ($payload['servico_fotografico_valor'] ?? ''));
$conn->begin_transaction();

try {
    if ($obraId <= 0 || !improov_usuario_pode_acessar_obra($conn, $obraId)) {
        throw new DomainException('Sem acesso ao projeto selecionado.');
    }

    $obraStmt = $conn->prepare('SELECT idobra, cliente, nomenclatura, nome_obra, status_obra FROM obra WHERE idobra=? FOR UPDATE');
    if (!$obraStmt) {
        throw new RuntimeException('Não foi possível consultar o projeto.');
    }
    $obraStmt->bind_param('i', $obraId);
    $obraStmt->execute();
    $obra = $obraStmt->get_result()->fetch_assoc();
    $obraStmt->close();
    if (!$obra) {
        throw new DomainException('Projeto não encontrado.');
    }
    if (!in_array((int) ($obra['status_obra'] ?? -1), [0, 2], true)) {
        throw new DomainException('Só é possível adicionar extras a projetos ativos ou em onboarding.');
    }

    $nomenclatura = trim((string) ($obra['nomenclatura'] ?? $obra['nome_obra'] ?? ''));
    $prepared = dashboard_prepare_image_entries($rawImages, $nomenclatura);
    if (!$prepared['entries']) {
        throw new InvalidArgumentException('Informe ao menos uma imagem nova.');
    }
    if ($prepared['duplicates']) {
        throw new InvalidArgumentException('A lista contém nomes de imagem repetidos. Remova as duplicatas antes de continuar.');
    }
    dashboard_onboarding_validate_commercial_images($prepared['entries']);
    if ($photoServiceValue !== '') {
        custos_decimal($photoServiceValue);
    }

    $existingStmt = $conn->prepare('SELECT imagem_nome FROM imagens_cliente_obra WHERE obra_id=?');
    if (!$existingStmt) {
        throw new RuntimeException('Não foi possível conferir as imagens existentes.');
    }
    $existingStmt->bind_param('i', $obraId);
    $existingStmt->execute();
    $existingResult = $existingStmt->get_result();
    $existingNames = [];
    while ($row = $existingResult->fetch_assoc()) {
        $existingNames[dashboard_normalize_for_search((string) $row['imagem_nome'])] = true;
    }
    $existingStmt->close();

    $conflicts = [];
    foreach ($prepared['entries'] as $entry) {
        $key = dashboard_normalize_for_search((string) $entry['imagem_nome']);
        if (isset($existingNames[$key])) {
            $conflicts[] = (string) $entry['imagem_nome'];
        }
    }
    if ($conflicts) {
        throw new DomainException('Estas imagens já existem no projeto: ' . implode(', ', array_slice($conflicts, 0, 8)) . (count($conflicts) > 8 ? '…' : ''));
    }

    $imageInsert = dashboard_insert_image_entries($conn, (int) $obra['cliente'], $obraId, $prepared['entries']);
    if (count($imageInsert['images'] ?? []) !== count($prepared['entries'])) {
        throw new RuntimeException('Não foi possível incluir todas as imagens e seus valores. Nenhuma alteração foi confirmada.');
    }
    $commercialImagesSaved = dashboard_onboarding_save_image_commercial($conn, $obraId, $imageInsert['images']);
    $photoServiceSaved = dashboard_onboarding_save_photo_service($conn, $obraId, $photoServiceValue);

    dashboard_insert_onboarding_event(
        $conn,
        $obraId,
        isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null,
        'IMAGES_EXTRAS_ADDED',
        'Imagens extras e valores comerciais adicionados ao projeto.',
        [
            'total_adicionado' => $imageInsert['inserted'],
            'valores_comerciais' => $commercialImagesSaved,
            'servico_fotografico' => $photoServiceSaved,
            'arquivo' => (string) (($payload['image_import']['file_name'] ?? '') ?: ''),
        ]
    );

    $conn->commit();
    echo json_encode([
        'success' => true,
        'obra_id' => $obraId,
        'images_inserted' => $imageInsert['inserted'],
        'commercial_images_saved' => $commercialImagesSaved,
        'photo_service_saved' => $photoServiceSaved,
        'message' => 'Imagens extras adicionadas ao projeto.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    $conn->rollback();
    $status = $error instanceof InvalidArgumentException || $error instanceof DomainException ? 422 : 500;
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
    $conn->close();
}
