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
require_once __DIR__ . '/../helpers/obra_itens_helper.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
    exit;
}

$obraId = (int) ($payload['obra_id'] ?? 0);
$rawImages = is_array($payload['images'] ?? null) ? $payload['images'] : [];
$extraPackage = is_array($payload['extra_package'] ?? null) ? $payload['extra_package'] : null;
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

    // O modo "extras" não envia cliente_id: o cliente vem da obra.
    // Obras legadas podem ter obra.cliente vazio; nesse caso, só inferimos
    // o cliente quando as imagens existentes apontam para um único ID válido.
    $clienteId = (int) ($obra['cliente'] ?? 0);
    if ($clienteId > 0) {
        $clienteStmt = $conn->prepare('SELECT idcliente FROM cliente WHERE idcliente = ? LIMIT 1');
        if (!$clienteStmt) {
            throw new RuntimeException('Não foi possível validar o cliente do projeto.');
        }
        $clienteStmt->bind_param('i', $clienteId);
        $clienteStmt->execute();
        $clienteResult = $clienteStmt->get_result();
        $clienteValido = $clienteResult && $clienteResult->num_rows > 0;
        $clienteStmt->close();
        if (!$clienteValido) {
            $clienteId = 0;
        }
    }

    if ($clienteId <= 0) {
        $clientesStmt = $conn->prepare(
            'SELECT DISTINCT cliente_id FROM imagens_cliente_obra WHERE obra_id = ? AND cliente_id IS NOT NULL AND cliente_id > 0 LIMIT 2'
        );
        if (!$clientesStmt) {
            throw new RuntimeException('Não foi possível localizar o cliente associado às imagens do projeto.');
        }
        $clientesStmt->bind_param('i', $obraId);
        $clientesStmt->execute();
        $clientesResult = $clientesStmt->get_result();
        $clientesExistentes = [];
        while ($clienteRow = $clientesResult->fetch_assoc()) {
            $clientesExistentes[] = (int) $clienteRow['cliente_id'];
        }
        $clientesStmt->close();

        if (count($clientesExistentes) !== 1) {
            throw new DomainException('Este projeto não tem um cliente associado de forma única. Corrija o cadastro do cliente antes de adicionar extras.');
        }

        $clienteId = $clientesExistentes[0];
        $clienteStmt = $conn->prepare('SELECT idcliente FROM cliente WHERE idcliente = ? LIMIT 1');
        if (!$clienteStmt) {
            throw new RuntimeException('Não foi possível validar o cliente do projeto.');
        }
        $clienteStmt->bind_param('i', $clienteId);
        $clienteStmt->execute();
        $clienteResult = $clienteStmt->get_result();
        $clienteValido = $clienteResult && $clienteResult->num_rows > 0;
        $clienteStmt->close();
        if (!$clienteValido) {
            throw new DomainException('O cliente associado às imagens deste projeto não existe mais. Corrija o cadastro antes de adicionar extras.');
        }
    }

    $nomenclatura = trim((string) ($obra['nomenclatura'] ?? $obra['nome_obra'] ?? ''));
    $materials = is_array($payload['materiais'] ?? null) ? $payload['materiais'] : [];
    $selectedPackageId = (int)($extraPackage['pacote_id'] ?? 0);
    $selectedPackageType = strtoupper(trim((string)($extraPackage['tipo'] ?? '')));
    if ($selectedPackageId <= 0 || !in_array($selectedPackageType, ['STILL', 'ANIMACAO', 'FILME'], true)) {
        throw new InvalidArgumentException('Selecione um pacote cadastrado para este projeto.');
    }
    $packageRows = custos_query($conn, 'SELECT idobra_pacote,tipo,prazo_contratual,prazo_dias_corridos FROM obra_pacote WHERE idobra_pacote=? AND obra_id=? FOR UPDATE', 'ii', [$selectedPackageId, $obraId]);
    if (!$packageRows || strtoupper((string)$packageRows[0]['tipo']) !== $selectedPackageType) {
        throw new InvalidArgumentException('O pacote selecionado não pertence a este projeto.');
    }
    $sourcePackage = $packageRows[0];
    $prepared = dashboard_prepare_image_entries($rawImages, $nomenclatura, dashboard_next_image_sequence($conn, $obraId));
    foreach ($prepared['entries'] as &$entry) $entry['origem'] = 'EXTRA';
    unset($entry);
    if ($selectedPackageType === 'STILL' && !$prepared['entries'] && !$materials) throw new InvalidArgumentException('Informe ao menos uma imagem Still ou material extra.');
    if ($selectedPackageType !== 'STILL' && $prepared['entries']) throw new InvalidArgumentException('Imagens extras devem ser vinculadas ao pacote Imagens Still.');
    if ($selectedPackageType === 'ANIMACAO' || $selectedPackageType === 'FILME') {
        $packageRevenue = trim((string)($extraPackage['receita'] ?? ''));
        if ($packageRevenue === '') throw new InvalidArgumentException('Informe o valor externo cobrado do cliente para este extra.');
        custos_decimal($packageRevenue);
        if ($selectedPackageType === 'ANIMACAO') {
            $packageSeconds = filter_var($extraPackage['segundos'] ?? null, FILTER_VALIDATE_INT);
            if (!$packageSeconds || $packageSeconds < 1) throw new InvalidArgumentException('Informe os segundos de animação do extra.');
        } else {
            $filmDuration = trim((string)($extraPackage['duracao'] ?? ''));
            if ($filmDuration === '' || mb_strlen($filmDuration) > 60) throw new InvalidArgumentException('Informe a duração do filme extra.');
            $packageSeconds = onboarding_parse_duration_seconds($filmDuration);
        }
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

    $imageInsert = dashboard_insert_image_entries($conn, $clienteId, $obraId, $prepared['entries']);
    if (count($imageInsert['images'] ?? []) !== count($prepared['entries'])) {
        throw new RuntimeException('Não foi possível incluir todas as imagens e seus valores. Nenhuma alteração foi confirmada.');
    }
    $commercialImagesSaved = dashboard_onboarding_save_image_commercial($conn, $obraId, $imageInsert['images']);
    $extraPackageSaved = false;
    if ($selectedPackageType === 'ANIMACAO' || $selectedPackageType === 'FILME') {
        $packageType = $selectedPackageType;
        $packageQuantity = null;
        $packageSlaDays = (int)($sourcePackage['prazo_contratual'] ?? 0);
        $packageCalendarDays = (int)($sourcePackage['prazo_dias_corridos'] ?? 0);
        $packageStart = date('Y-m-d');
        $packageStatus = 'HOLD';
        $packageNotes = 'Pacote extra derivado do pacote #' . $selectedPackageId;
        $insertPackage = $conn->prepare('INSERT INTO obra_pacote (obra_id,tipo,quantidade,segundos,prazo_contratual,prazo_dias_corridos,data_inicio_sla,status,observacoes) VALUES (?,?,?,?,?,?,?,?,?)');
        if (!$insertPackage) throw new RuntimeException('Não foi possível preparar o pacote extra.');
        $insertPackage->bind_param('isiiiisss', $obraId, $packageType, $packageQuantity, $packageSeconds, $packageSlaDays, $packageCalendarDays, $packageStart, $packageStatus, $packageNotes);
        if (!$insertPackage->execute()) throw new RuntimeException('Não foi possível salvar o pacote extra: ' . $insertPackage->error);
        $newPackageId = (int)$insertPackage->insert_id;
        $insertPackage->close();
        obra_item_save($conn, $obraId, [
            'categoria' => $selectedPackageType === 'ANIMACAO' ? 'Animações 3D' : 'Filmes',
            'tipo_item' => 'PACOTE',
            'descricao' => $selectedPackageType === 'ANIMACAO' ? 'Extra de animação 3D (' . $packageSeconds . 's)' : 'Extra de filme (' . trim((string)$extraPackage['duracao']) . ')',
            'quantidade' => 1,
            'origem' => 'EXTRA',
            'pacote_id' => $newPackageId,
            'receita' => $packageRevenue,
            // Production tasks are currently aggregated at project/animation level;
            // do not assign their forecast to this specific extra package.
            'modelo_custo' => 'DIRETO',
        ], isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : null);
        $extraPackageSaved = true;
    }
    $photoServiceId = dashboard_onboarding_save_photo_service($conn, $obraId, $photoServiceValue);
    if ($photoServiceId > 0) {
        obra_item_save($conn, $obraId, [
            'categoria' => 'Fotografia', 'tipo_item' => 'SERVICO', 'descricao' => 'Serviço fotográfico',
            'quantidade' => 1, 'origem' => 'EXTRA', 'servico_foto_id' => $photoServiceId,
            'receita' => $photoServiceValue,
        ], isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : null);
    }
    foreach ($materials as $material) {
        if (!is_array($material)) throw new InvalidArgumentException('Material adicional inválido.');
        $material['origem'] = 'EXTRA';
        $material['tipo_item'] = in_array(strtoupper((string)($material['tipo_item'] ?? '')), ['MATERIAL', 'SERVICO'], true) ? strtoupper($material['tipo_item']) : 'OUTRO';
        obra_item_save($conn, $obraId, $material, isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : null);
    }

    dashboard_insert_onboarding_event(
        $conn,
        $obraId,
        isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null,
        'IMAGES_EXTRAS_ADDED',
        'Itens extras e seus valores financeiros adicionados ao projeto.',
        [
            'total_adicionado' => $imageInsert['inserted'],
            'valores_comerciais' => $commercialImagesSaved,
            'servico_fotografico' => $photoServiceId > 0,
            'pacote_extra' => $extraPackageSaved,
            'arquivo' => (string) (($payload['image_import']['file_name'] ?? '') ?: ''),
        ]
    );

    $conn->commit();
    echo json_encode([
        'success' => true,
        'obra_id' => $obraId,
        'images_inserted' => $imageInsert['inserted'],
        'commercial_images_saved' => $commercialImagesSaved,
        'photo_service_saved' => $photoServiceId > 0,
        'package_extra_saved' => $extraPackageSaved,
        'other_items_saved' => count($materials),
        'message' => 'Extras e valores adicionados ao projeto.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    $conn->rollback();
    $status = $error instanceof InvalidArgumentException || $error instanceof DomainException ? 422 : 500;
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
    $conn->close();
}
