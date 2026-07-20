<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/secure_env.php';
header('Content-Type: application/json; charset=utf-8');

// session_start();

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$allowedLevels = [1, 5];
if (!isset($_SESSION['nivel_acesso']) || !in_array((int) $_SESSION['nivel_acesso'], $allowedLevels, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit;
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/onboarding_helpers.php';
require_once __DIR__ . '/image_import_helpers.php';
require_once __DIR__ . '/../contact_architecture.php';

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Dependência ausente: vendor/autoload.php não encontrada.']);
    exit;
}
require_once $vendorAutoload;

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sem conexão com o banco de dados.']);
    exit;
}

function onboarding_ensure_remote_project_folder(string $nomenclatura): void
{
    $sftpCfg = improov_sftp_config();
    $ftpUser = $sftpCfg['user'];
    $ftpPass = $sftpCfg['pass'];
    $ftpHost = $sftpCfg['host'];
    $ftpPort = $sftpCfg['port'];

    $templateBase = '/mnt/clientes/00.Cliente_Padrao';
    $year = date('Y');
    $yearBase = "/mnt/clientes/{$year}";
    $destination = $yearBase . '/' . $nomenclatura;

    if (!preg_match('/^[A-Za-z0-9._-]{1,32}$/', $nomenclatura)) {
        throw new RuntimeException('Código interno inválido para criação da pasta do projeto.');
    }

    $ssh = new \phpseclib3\Net\SSH2($ftpHost, $ftpPort);
    if (!$ssh->login($ftpUser, $ftpPass)) {
        throw new RuntimeException('Falha na autenticação SSH/SFTP no servidor de arquivos.');
    }

    $commands = [
        'set -e',
        'umask 000',
        'mkdir -p ' . escapeshellarg($yearBase),
        'if [ -d ' . escapeshellarg($destination) . ' ]; then echo "DEST_EXISTS"; exit 2; fi',
        'if [ ! -d ' . escapeshellarg($templateBase) . ' ]; then echo "TEMPLATE_MISSING"; exit 3; fi',
        'mkdir -p ' . escapeshellarg($destination),
        'cp -r ' . escapeshellarg($templateBase . '/.') . ' ' . escapeshellarg($destination . '/'),
        'echo "OK"',
    ];
    $output = $ssh->exec(implode('; ', $commands));

    if (strpos($output, 'DEST_EXISTS') !== false) {
        throw new RuntimeException('A pasta do projeto já existe no servidor.');
    }
    if (strpos($output, 'TEMPLATE_MISSING') !== false) {
        throw new RuntimeException('Template não encontrado no servidor (/mnt/clientes/00.Cliente_Padrao).');
    }
    if (strpos($output, 'OK') === false) {
        throw new RuntimeException('Falha ao criar/copiar a pasta do projeto no servidor.');
    }
}

function onboarding_client_exists(mysqli $conn, int $clienteId): bool
{
    $stmt = $conn->prepare('SELECT idcliente FROM cliente WHERE idcliente = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar verificação de cliente: ' . $conn->error);
    }
    $stmt->bind_param('i', $clienteId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function onboarding_sigla_exists(mysqli $conn, string $table, string $column, string $value, ?string $idColumn = null, ?int $excludeId = null): bool
{
    if ($value === '') {
        return false;
    }

    $where = "UPPER(TRIM({$column})) = UPPER(TRIM(?))";
    $types = 's';
    $params = [$value];

    if ($idColumn && $excludeId && $excludeId > 0) {
        $where .= " AND {$idColumn} <> ?";
        $types .= 'i';
        $params[] = $excludeId;
    }

    $stmt = $conn->prepare("SELECT 1 FROM {$table} WHERE {$where} LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar validacao de sigla: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

function onboarding_store_contacts(mysqli $conn, int $clienteId, int $obraId, array $selectedContactIds, array $newContacts): array
{
    $contactIds = array_values(array_unique(array_filter(array_map(static function ($value) {
        return (int) $value;
    }, $selectedContactIds), static function (int $value) {
        return $value > 0;
    })));

    foreach ($newContacts as $contact) {
        if (!is_array($contact) || contact_arch_is_empty_contact($contact)) {
            continue;
        }

        $contactIds[] = contact_arch_save_client_contact($conn, $clienteId, $contact);
    }

    $contactIds = array_values(array_unique($contactIds));
    $sync = contact_arch_sync_obra_contacts($conn, $obraId, $contactIds);

    return [
        'contact_ids' => $sync['selected_ids'] ?? $contactIds,
        'linked_count' => (int) ($sync['linked_count'] ?? count($contactIds)),
    ];
}

function onboarding_parse_duration_seconds(?string $value): ?int
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (ctype_digit($value)) {
        return (int) $value;
    }

    if (preg_match('/^(\d+):(\d{1,2})(?::(\d{1,2}))?$/', $value, $matches)) {
        if (isset($matches[3]) && $matches[3] !== '') {
            return ((int) $matches[1] * 3600) + ((int) $matches[2] * 60) + (int) $matches[3];
        }
        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    $normalized = mb_strtolower($value, 'UTF-8');
    $normalized = str_replace(',', '.', $normalized);
    $normalized = preg_replace('/\s+/', '', $normalized);
    if (!is_string($normalized) || $normalized === '') {
        return null;
    }

    preg_match_all('/(\d+)(h|hora|horas|min|mins|minuto|minutos|m|seg|segs|segundo|segundos|s)/u', $normalized, $matches, PREG_SET_ORDER);
    if (empty($matches)) {
        return null;
    }

    $seconds = 0;
    foreach ($matches as $match) {
        $amount = (int) $match[1];
        $unit = $match[2];

        if (in_array($unit, ['h', 'hora', 'horas'], true)) {
            $seconds += $amount * 3600;
            continue;
        }

        if (in_array($unit, ['min', 'mins', 'minuto', 'minutos', 'm'], true)) {
            $seconds += $amount * 60;
            continue;
        }

        $seconds += $amount;
    }

    return $seconds > 0 ? $seconds : null;
}

function onboarding_build_package_rows(array $packages, string $observacoes): array
{
    $today = date('Y-m-d');
    $rows = [];

    if (!empty($packages['still']['enabled'])) {
        $rows[] = [
            'tipo' => 'STILL',
            'quantidade' => max(0, (int) ($packages['still']['quantity'] ?? 0)),
            'segundos' => null,
            'prazo_contratual' => max(0, (int) ($packages['still']['deadline_days'] ?? 0)),
            'prazo_dias_corridos' => !empty($packages['still']['deadline_calendar_days']) ? 1 : 0,
            'data_inicio_sla' => $today,
            'status' => 'ATIVO',
            'observacoes' => $observacoes !== '' ? $observacoes : null,
        ];
    }

    if (!empty($packages['animation']['enabled'])) {
        $rows[] = [
            'tipo' => 'ANIMACAO',
            'quantidade' => null,
            'segundos' => max(0, (int) ($packages['animation']['seconds'] ?? 0)),
            'prazo_contratual' => max(0, (int) ($packages['animation']['deadline_days'] ?? 0)),
            'prazo_dias_corridos' => !empty($packages['animation']['deadline_calendar_days']) ? 1 : 0,
            'data_inicio_sla' => $today,
            'status' => 'HOLD',
            'observacoes' => $observacoes !== '' ? $observacoes : null,
        ];
    }

    if (!empty($packages['film']['enabled'])) {
        $filmDuration = trim((string) ($packages['film']['duration'] ?? ''));
        $filmSeconds = onboarding_parse_duration_seconds($filmDuration);
        $filmObservacoes = [];
        if ($observacoes !== '') {
            $filmObservacoes[] = $observacoes;
        }
        if ($filmDuration !== '') {
            $filmObservacoes[] = 'Duração informada: ' . $filmDuration;
        }

        $rows[] = [
            'tipo' => 'FILME',
            'quantidade' => null,
            'segundos' => $filmSeconds,
            'prazo_contratual' => max(0, (int) ($packages['film']['deadline_days'] ?? 0)),
            'prazo_dias_corridos' => !empty($packages['film']['deadline_calendar_days']) ? 1 : 0,
            'data_inicio_sla' => $today,
            'status' => 'HOLD',
            'observacoes' => !empty($filmObservacoes) ? implode(' | ', $filmObservacoes) : null,
        ];
    }

    return $rows;
}

function onboarding_insert_package_rows(mysqli $conn, int $obraId, array $packageRows): int
{
    if (empty($packageRows)) {
        return 0;
    }

    $sql = 'INSERT INTO obra_pacote (obra_id, tipo, quantidade, segundos, prazo_contratual, prazo_dias_corridos, data_inicio_sla, status, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar INSERT obra_pacote: ' . $conn->error);
    }

    $inserted = 0;
    foreach ($packageRows as $row) {
        $tipo = (string) $row['tipo'];
        $quantidade = isset($row['quantidade']) ? (int) $row['quantidade'] : null;
        $segundos = isset($row['segundos']) ? (int) $row['segundos'] : null;
        $prazoContratual = isset($row['prazo_contratual']) ? (int) $row['prazo_contratual'] : null;
        $prazoDiasCorridos = !empty($row['prazo_dias_corridos']) ? 1 : 0;
        $dataInicioSla = $row['data_inicio_sla'];
        $status = (string) $row['status'];
        $observacoes = $row['observacoes'];

        $stmt->bind_param('isiiiisss', $obraId, $tipo, $quantidade, $segundos, $prazoContratual, $prazoDiasCorridos, $dataInicioSla, $status, $observacoes);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Erro ao inserir pacote contratado: ' . $error);
        }
        $inserted++;
    }

    $stmt->close();

    return $inserted;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
    exit;
}

$clienteNome = trim((string) ($payload['cliente'] ?? ''));
$clienteNomeCompleto = trim((string) ($payload['cliente_nome_completo'] ?? $payload['cliente_nome'] ?? ''));
$clienteIdFromReq = isset($payload['cliente_id']) ? (int) $payload['cliente_id'] : null;
$obraNome = trim((string) ($payload['obra'] ?? ''));
$obraNomeCompleto = trim((string) ($payload['obra_nome_completo'] ?? $payload['nome_real'] ?? ''));
$nomenclatura = trim((string) ($payload['nomenclatura'] ?? ''));
$nomeReal = trim((string) ($payload['nome_real'] ?? ''));
$observacoes = trim((string) ($payload['observacoes'] ?? ''));
$packages = is_array($payload['packages'] ?? null) ? $payload['packages'] : [];
$selectedContactIds = is_array($payload['selected_contact_ids'] ?? null) ? $payload['selected_contact_ids'] : [];
$newContacts = is_array($payload['new_contacts'] ?? null) ? $payload['new_contacts'] : [];
$legacyContacts = is_array($payload['contacts'] ?? null) ? $payload['contacts'] : [];
$rawImages = is_array($payload['images'] ?? null) ? $payload['images'] : [];
$imageImport = is_array($payload['image_import'] ?? null) ? $payload['image_import'] : [];

if (strlen($clienteNome) > 45) {
    $clienteNome = substr($clienteNome, 0, 45);
}
if (strlen($clienteNomeCompleto) > 150) {
    $clienteNomeCompleto = substr($clienteNomeCompleto, 0, 150);
}
if (strlen($obraNome) > 45) {
    $obraNome = substr($obraNome, 0, 45);
}
if (strlen($obraNomeCompleto) > 150) {
    $obraNomeCompleto = substr($obraNomeCompleto, 0, 150);
}
if (strlen($nomenclatura) > 10) {
    $nomenclatura = substr($nomenclatura, 0, 10);
}
$nomeRealParts = [];
if ($clienteNomeCompleto !== '') {
    $nomeRealParts[] = $clienteNomeCompleto;
}
if ($obraNomeCompleto !== '') {
    $nomeRealParts[] = $obraNomeCompleto;
}
if (!empty($nomeRealParts)) {
    $nomeReal = implode(' - ', $nomeRealParts);
}
if (strlen($nomeReal) > 100) {
    $nomeReal = substr($nomeReal, 0, 100);
}

if ((is_null($clienteIdFromReq) && $clienteNome === '') || $clienteNomeCompleto === '' || $obraNome === '' || $nomenclatura === '' || $nomeReal === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preencha cliente, projeto interno, código interno e projeto comercial.']);
    exit;
}

$selectedPackages = [];
$slaMetadata = [];
if (!empty($packages['still']['enabled'])) {
    $selectedPackages[] = 'still';
    $slaMetadata['still_qtd'] = max(0, (int) ($packages['still']['quantity'] ?? 0));
    $slaMetadata['prazo_still'] = max(0, (int) ($packages['still']['deadline_days'] ?? 0));
    $slaMetadata['prazo_still_dias_corridos'] = !empty($packages['still']['deadline_calendar_days']);
}
if (!empty($packages['animation']['enabled'])) {
    $selectedPackages[] = 'animacao';
    $slaMetadata['animacao_segundos'] = max(0, (int) ($packages['animation']['seconds'] ?? 0));
    $slaMetadata['prazo_animacao'] = max(0, (int) ($packages['animation']['deadline_days'] ?? 0));
    $slaMetadata['prazo_animacao_dias_corridos'] = !empty($packages['animation']['deadline_calendar_days']);
}
if (!empty($packages['film']['enabled'])) {
    $selectedPackages[] = 'filme';
    $slaMetadata['filme_duracao'] = trim((string) ($packages['film']['duration'] ?? ''));
    $slaMetadata['prazo_filme'] = max(0, (int) ($packages['film']['deadline_days'] ?? 0));
    $slaMetadata['prazo_filme_dias_corridos'] = !empty($packages['film']['deadline_calendar_days']);
}

if (count($selectedPackages) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Selecione ao menos um pacote contratado.']);
    exit;
}

$normalizedSelectedContactIds = array_values(array_unique(array_filter(array_map(static function ($value) {
    return (int) $value;
}, $selectedContactIds), static function (int $value) {
    return $value > 0;
})));

$validNewContacts = array_values(array_filter(array_map(static function ($contact) {
    return is_array($contact) ? contact_arch_clean_contact($contact) : [];
}, $newContacts), static function ($contact) {
    return is_array($contact) && !contact_arch_is_empty_contact($contact);
}));

$legacyDraftContacts = array_values(array_filter(array_map(static function ($contact) {
    return is_array($contact)
        ? contact_arch_clean_contact([
            'name' => $contact['name'] ?? '',
            'email' => $contact['email'] ?? '',
            'phone' => $contact['phone'] ?? '',
            'role' => $contact['role'] ?? $contact['cargo'] ?? '',
            'type' => $contact['type'] ?? $contact['tipo'] ?? 'OUTRO',
            'notes' => $contact['notes'] ?? $contact['observacoes'] ?? '',
        ])
        : [];
}, $legacyContacts), static function ($contact) {
    return is_array($contact) && !contact_arch_is_empty_contact($contact);
}));

if (count($validNewContacts) === 0 && count($legacyDraftContacts) > 0) {
    $validNewContacts = $legacyDraftContacts;
}

$preparedImages = dashboard_prepare_image_entries($rawImages, $nomenclatura);
$preparedImageEntries = $preparedImages['entries'];
$packageRows = onboarding_build_package_rows($packages, $observacoes);
$stillDeadlineDays = !empty($packages['still']['enabled'])
    ? max(0, (int) ($packages['still']['deadline_days'] ?? 0))
    : 0;
$stillDeadlineCalendarDays = !empty($packages['still']['deadline_calendar_days']) ? 1 : 0;

$colaboradorId = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;

try {
    dashboard_ensure_onboarding_project_schema($conn);
    $conn->begin_transaction();

    if (is_null($clienteIdFromReq) && onboarding_sigla_exists($conn, 'cliente', 'nome_cliente', $clienteNome)) {
        throw new RuntimeException('A sigla do cliente ja existe. Altere antes de continuar.');
    }
    if (onboarding_sigla_exists($conn, 'obra', 'nome_obra', $obraNome)) {
        throw new RuntimeException('A sigla do projeto ja existe. Altere antes de continuar.');
    }
    if (onboarding_sigla_exists($conn, 'obra', 'nomenclatura', $nomenclatura)) {
        throw new RuntimeException('A nomenclatura ja existe. Altere antes de continuar.');
    }

    if (!is_null($clienteIdFromReq) && $clienteIdFromReq > 0) {
        if (!onboarding_client_exists($conn, $clienteIdFromReq)) {
            throw new RuntimeException('Cliente selecionado não existe.');
        }
        $clienteId = $clienteIdFromReq;
        if (dashboard_table_has_column($conn, 'cliente', 'nome_completo') && $clienteNomeCompleto !== '') {
            $updateCliente = $conn->prepare('UPDATE cliente SET nome_completo = ? WHERE idcliente = ?');
            if ($updateCliente) {
                $updateCliente->bind_param('si', $clienteNomeCompleto, $clienteId);
                $updateCliente->execute();
                $updateCliente->close();
            }
        }
    } else {
        $clienteCols = ['nome_cliente'];
        $clientePlaceholders = ['?'];
        $clienteTypes = 's';
        $clienteValues = [$clienteNome];

        if (dashboard_table_has_column($conn, 'cliente', 'nome_completo')) {
            $clienteCols[] = 'nome_completo';
            $clientePlaceholders[] = '?';
            $clienteTypes .= 's';
            $clienteValues[] = $clienteNomeCompleto;
        }

        $stmtCliente = $conn->prepare('INSERT INTO cliente (' . implode(', ', $clienteCols) . ') VALUES (' . implode(', ', $clientePlaceholders) . ')');
        if (!$stmtCliente) {
            throw new RuntimeException('Erro ao preparar INSERT cliente: ' . $conn->error);
        }
        $stmtCliente->bind_param($clienteTypes, ...$clienteValues);
        if (!$stmtCliente->execute()) {
            $error = $stmtCliente->error;
            $stmtCliente->close();
            throw new RuntimeException('Erro ao inserir cliente: ' . $error);
        }
        $clienteId = (int) $stmtCliente->insert_id;
        $stmtCliente->close();
    }

    $cols = ['nome_obra'];
    $placeholders = ['?'];
    $types = 's';
    $values = [$obraNome];

    if (dashboard_table_has_column($conn, 'obra', 'dias_uteis')) {
        $cols[] = 'dias_uteis';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $stillDeadlineDays;
    }
    if (dashboard_table_has_column($conn, 'obra', 'prazo_dias_corridos')) {
        $cols[] = 'prazo_dias_corridos';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $stillDeadlineCalendarDays;
    }
    if (dashboard_table_has_column($conn, 'obra', 'nome_completo')) {
        $cols[] = 'nome_completo';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $obraNomeCompleto !== '' ? $obraNomeCompleto : $nomeReal;
    }
    if (dashboard_table_has_column($conn, 'obra', 'nomenclatura')) {
        $cols[] = 'nomenclatura';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $nomenclatura;
    }
    if (dashboard_table_has_column($conn, 'obra', 'nome_real')) {
        $cols[] = 'nome_real';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $nomeReal;
    }
    if (dashboard_table_has_column($conn, 'obra', 'cliente')) {
        $cols[] = 'cliente';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $clienteId;
    }
    if (dashboard_table_has_column($conn, 'obra', 'status_obra')) {
        $cols[] = 'status_obra';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = 2;
    }
    $sqlObra = 'INSERT INTO obra (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmtObra = $conn->prepare($sqlObra);
    if (!$stmtObra) {
        throw new RuntimeException('Erro ao preparar INSERT obra: ' . $conn->error);
    }
    $stmtObra->bind_param($types, ...$values);
    if (!$stmtObra->execute()) {
        $error = $stmtObra->error;
        $stmtObra->close();
        throw new RuntimeException('Erro ao inserir obra: ' . $error);
    }
    $obraId = (int) $stmtObra->insert_id;
    $stmtObra->close();

    $savedPackages = onboarding_insert_package_rows($conn, $obraId, $packageRows);

    $contactSync = onboarding_store_contacts($conn, $clienteId, $obraId, $normalizedSelectedContactIds, $validNewContacts);
    $linkedContacts = contact_arch_fetch_linked_contacts($conn, $obraId);
    $savedContacts = (int) ($contactSync['linked_count'] ?? count($linkedContacts));
    $imageInsert = dashboard_insert_image_entries($conn, $clienteId, $obraId, $preparedImageEntries);

    onboarding_ensure_remote_project_folder($nomenclatura);

    $projectStartMetadata = array_merge(
        [
            'cliente' => $clienteId,
            'cliente_nome' => $clienteNome !== '' ? $clienteNome : ($payload['cliente_nome'] ?? ''),
            'cliente_nome_completo' => $clienteNomeCompleto,
            'obra_nome_completo' => $obraNomeCompleto !== '' ? $obraNomeCompleto : $nomeReal,
            'pacotes' => $selectedPackages,
            'observacoes' => $observacoes,
            'contatos' => array_map(static function ($contact) {
                return [
                    'id' => (int) ($contact['contact_id'] ?? 0),
                    'nome' => trim((string) ($contact['name'] ?? '')),
                    'cargo' => trim((string) ($contact['role'] ?? '')),
                    'tipo' => trim((string) ($contact['type'] ?? 'OUTRO')),
                    'email' => trim((string) ($contact['email'] ?? '')),
                    'telefone' => trim((string) ($contact['phone'] ?? '')),
                    'observacoes' => trim((string) ($contact['notes'] ?? '')),
                ];
            }, !empty($linkedContacts) ? $linkedContacts : $validNewContacts),
            'status_obra' => 'ONBOARDING',
        ],
        $slaMetadata
    );
    dashboard_insert_onboarding_event($conn, $obraId, $colaboradorId, 'PROJECT_START', 'Projeto iniciado em onboarding operacional.', $projectStartMetadata);

    dashboard_insert_onboarding_event($conn, $obraId, $colaboradorId, 'SLA_DEFINED', 'Pacotes e SLAs definidos no onboarding.', array_merge(['pacotes' => $selectedPackages], $slaMetadata));

    if ($imageInsert['inserted'] > 0 || count($preparedImages['duplicates']) > 0 || count($preparedImages['errors']) > 0 || !empty($imageImport)) {
        dashboard_insert_onboarding_event(
            $conn,
            $obraId,
            $colaboradorId,
            'IMAGES_IMPORTED',
            'Lista de imagens registrada no onboarding.',
            [
                'total_importado' => $imageInsert['inserted'],
                'duplicadas' => count($preparedImages['duplicates']),
                'erros' => count($preparedImages['errors']) + count($imageInsert['errors']),
                'arquivo' => (string) ($imageImport['file_name'] ?? ''),
                'origem' => (string) ($imageImport['source'] ?? 'manual'),
            ]
        );
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'cliente_id' => $clienteId,
        'obra_id' => $obraId,
        'status_obra' => 'ONBOARDING',
        'packages_saved' => $savedPackages,
        'contacts_linked' => count($linkedContacts),
        'contacts_saved' => $savedContacts,
        'images_inserted' => $imageInsert['inserted'],
        'duplicates' => count($preparedImages['duplicates']),
        'errors' => array_merge($preparedImages['errors'], $imageInsert['errors']),
        'message' => 'Projeto iniciado com sucesso no onboarding operacional.',
    ]);
} catch (Throwable $throwable) {
    if ($conn) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $throwable->getMessage()]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
