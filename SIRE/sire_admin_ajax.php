<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/sire_helpers.php';

sire_require_auth();
$userId = sire_session_user_id();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
require_once __DIR__ . '/../conexaoMain.php';

$conn = conectarBanco();
sire_require_admin($conn);
$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $_REQUEST['action'] ?? ($payload['action'] ?? '');

function sire_admin_pillars(mysqli $conn): array
{
    $result = $conn->query("SELECT p.id, p.codigo, p.nome, p.ordem,
            COUNT(v.id) AS total_valores,
            COALESCE(SUM(v.ativo = 1), 0) AS valores_ativos
        FROM sire_pilar p
        LEFT JOIN sire_pilar_valor v ON v.pilar_id = p.id
        GROUP BY p.id, p.codigo, p.nome, p.ordem
        ORDER BY p.ordem, p.nome");
    $pillars = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        $row['total_valores'] = (int) $row['total_valores'];
        $row['valores_ativos'] = (int) $row['valores_ativos'];
        $pillars[] = $row;
    }
    return $pillars;
}

function sire_admin_value(mysqli $conn, int $valueId): ?array
{
    $stmt = $conn->prepare("SELECT v.id, v.pilar_id, v.nome, v.descricao, v.ativo,
            COUNT(DISTINCT rv.referencia_id) AS uso
        FROM sire_pilar_valor v
        LEFT JOIN sire_referencia_valor rv ON rv.valor_id = v.id
        WHERE v.id = ?
        GROUP BY v.id, v.pilar_id, v.nome, v.descricao, v.ativo");
    $stmt->bind_param('i', $valueId);
    $stmt->execute();
    $value = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$value) {
        return null;
    }
    $value['id'] = (int) $value['id'];
    $value['pilar_id'] = (int) $value['pilar_id'];
    $value['ativo'] = (bool) $value['ativo'];
    $value['uso'] = (int) $value['uso'];
    $value['caracteristicas'] = [];
    $features = $conn->prepare('SELECT id, descricao, ordem FROM sire_pilar_valor_caracteristica WHERE pilar_valor_id = ? ORDER BY ordem, id');
    $features->bind_param('i', $valueId);
    $features->execute();
    $result = $features->get_result();
    while ($row = $result->fetch_assoc()) {
        $value['caracteristicas'][] = [
            'id' => (int) $row['id'],
            'descricao' => $row['descricao'],
            'ordem' => (int) $row['ordem'],
        ];
    }
    $features->close();
    return $value;
}

function sire_admin_values(mysqli $conn, int $pillarId, string $search = ''): array
{
    $search = sire_normalize_value_name($search);
    $like = '%' . $search . '%';
    $stmt = $conn->prepare("SELECT v.id, v.pilar_id, v.nome, v.descricao, v.ativo,
            COUNT(DISTINCT rv.referencia_id) AS uso,
            COUNT(DISTINCT c.id) AS caracteristicas
        FROM sire_pilar_valor v
        LEFT JOIN sire_referencia_valor rv ON rv.valor_id = v.id
        LEFT JOIN sire_pilar_valor_caracteristica c ON c.pilar_valor_id = v.id
        WHERE v.pilar_id = ? AND (? = '' OR v.nome LIKE ?)
        GROUP BY v.id, v.pilar_id, v.nome, v.descricao, v.ativo
        ORDER BY v.ativo DESC, v.nome");
    $stmt->bind_param('iss', $pillarId, $search, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $values = [];
    while ($row = $result->fetch_assoc()) {
        $values[] = [
            'id' => (int) $row['id'],
            'pilar_id' => (int) $row['pilar_id'],
            'nome' => $row['nome'],
            'descricao' => $row['descricao'] ?? '',
            'ativo' => (bool) $row['ativo'],
            'uso' => (int) $row['uso'],
            'caracteristicas' => (int) $row['caracteristicas'],
        ];
    }
    $stmt->close();
    return $values;
}

function sire_admin_features(array $features): array
{
    $clean = [];
    $seen = [];
    foreach ($features as $feature) {
        $description = sire_normalize_value_name((string) $feature);
        if ($description === '') {
            continue;
        }
        if (mb_strlen($description) > 255) {
            throw new InvalidArgumentException('Cada característica deve ter no máximo 255 caracteres.');
        }
        $key = mb_strtolower($description);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $clean[] = $description;
        }
    }
    if (count($clean) > 30) {
        throw new InvalidArgumentException('Informe no máximo 30 características.');
    }
    return $clean;
}

if ($action === 'getCatalog') {
    $pillars = sire_admin_pillars($conn);
    $conn->close();
    sire_json(['success' => true, 'pilares' => $pillars]);
}

if ($action === 'getValues') {
    $pillarId = (int) ($_GET['pilar_id'] ?? $payload['pilar_id'] ?? 0);
    $search = (string) ($_GET['search'] ?? $payload['search'] ?? '');
    if ($pillarId <= 0) {
        $conn->close();
        sire_json(['success' => false, 'message' => 'Pilar inválido.'], 422);
    }
    $values = sire_admin_values($conn, $pillarId, $search);
    $conn->close();
    sire_json(['success' => true, 'valores' => $values]);
}

if ($action === 'getValue') {
    $valueId = (int) ($_GET['id'] ?? $payload['id'] ?? 0);
    $value = $valueId > 0 ? sire_admin_value($conn, $valueId) : null;
    $conn->close();
    if (!$value) {
        sire_json(['success' => false, 'message' => 'Valor não encontrado.'], 404);
    }
    sire_json(['success' => true, 'valor' => $value]);
}

if ($action === 'saveValue') {
    $valueId = (int) ($payload['id'] ?? 0);
    $pillarId = (int) ($payload['pilar_id'] ?? 0);
    $name = sire_normalize_value_name((string) ($payload['nome'] ?? ''));
    $description = trim((string) ($payload['descricao'] ?? ''));
    $active = !empty($payload['ativo']) ? 1 : 0;
    $features = is_array($payload['caracteristicas'] ?? null) ? $payload['caracteristicas'] : [];
    if ($pillarId <= 0 || $name === '' || mb_strlen($name) > 160 || $description === '') {
        $conn->close();
        sire_json(['success' => false, 'message' => 'Informe pilar, nome e uma descrição do conceito.'], 422);
    }
    if (mb_strlen($description) > 4000) {
        $conn->close();
        sire_json(['success' => false, 'message' => 'A descrição deve ter no máximo 4.000 caracteres.'], 422);
    }
    try {
        $features = sire_admin_features($features);
    } catch (InvalidArgumentException $exception) {
        $conn->close();
        sire_json(['success' => false, 'message' => $exception->getMessage()], 422);
    }

    $pillar = $conn->prepare('SELECT id FROM sire_pilar WHERE id = ? LIMIT 1');
    $pillar->bind_param('i', $pillarId);
    $pillar->execute();
    $pillarExists = (bool) $pillar->get_result()->fetch_assoc();
    $pillar->close();
    if (!$pillarExists) {
        $conn->close();
        sire_json(['success' => false, 'message' => 'Pilar inválido.'], 422);
    }

    $similar = $conn->prepare("SELECT id, nome FROM sire_pilar_valor
        WHERE pilar_id = ? AND id <> ? AND (LOWER(nome) LIKE LOWER(?) OR LOWER(?) LIKE CONCAT('%', LOWER(nome), '%'))
        ORDER BY nome LIMIT 5");
    $contains = '%' . $name . '%';
    $similar->bind_param('iiss', $pillarId, $valueId, $contains, $name);
    $similar->execute();
    $similarRows = $similar->get_result()->fetch_all(MYSQLI_ASSOC);
    $similar->close();
    $normalized = mb_strtolower(preg_replace('/\s+/u', '', $name));
    foreach ($similarRows as $row) {
        if (mb_strtolower(preg_replace('/\s+/u', '', $row['nome'])) === $normalized) {
            $conn->close();
            sire_json(['success' => false, 'message' => 'Já existe um valor com este nome neste pilar.'], 422);
        }
    }
    if ($similarRows && empty($payload['confirmar_semelhante'])) {
        $conn->close();
        sire_json([
            'success' => false,
            'requires_confirmation' => true,
            'message' => 'Já existe um valor semelhante: “' . $similarRows[0]['nome'] . '”. Deseja continuar?',
            'similar_values' => array_column($similarRows, 'nome'),
        ], 409);
    }

    $conn->begin_transaction();
    try {
        if ($valueId > 0) {
            $existing = sire_admin_value($conn, $valueId);
            if (!$existing) {
                throw new RuntimeException('Valor não encontrado.');
            }
            if ($existing['uso'] > 0 && $existing['pilar_id'] !== $pillarId) {
                throw new RuntimeException('Não é possível mover para outro pilar um valor já utilizado.');
            }
            $save = $conn->prepare('UPDATE sire_pilar_valor SET pilar_id = ?, nome = ?, descricao = ?, ativo = ? WHERE id = ?');
            $save->bind_param('issii', $pillarId, $name, $description, $active, $valueId);
            $save->execute();
            $save->close();
        } else {
            $save = $conn->prepare('INSERT INTO sire_pilar_valor (pilar_id, nome, descricao, ativo, criado_por) VALUES (?, ?, ?, ?, ?)');
            $save->bind_param('issii', $pillarId, $name, $description, $active, $userId);
            $save->execute();
            $valueId = (int) $conn->insert_id;
            $save->close();
        }
        $deleteFeatures = $conn->prepare('DELETE FROM sire_pilar_valor_caracteristica WHERE pilar_valor_id = ?');
        $deleteFeatures->bind_param('i', $valueId);
        $deleteFeatures->execute();
        $deleteFeatures->close();
        if ($features) {
            $insertFeature = $conn->prepare('INSERT INTO sire_pilar_valor_caracteristica (pilar_valor_id, descricao, ordem) VALUES (?, ?, ?)');
            foreach ($features as $order => $feature) {
                $position = $order + 1;
                $insertFeature->bind_param('isi', $valueId, $feature, $position);
                $insertFeature->execute();
            }
            $insertFeature->close();
        }
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        $conn->close();
        sire_json(['success' => false, 'message' => $exception->getMessage()], 422);
    }
    $value = sire_admin_value($conn, $valueId);
    $conn->close();
    sire_json(['success' => true, 'valor' => $value]);
}

if ($action === 'toggleStatus') {
    $valueId = (int) ($payload['id'] ?? 0);
    $active = !empty($payload['ativo']) ? 1 : 0;
    $stmt = $conn->prepare('UPDATE sire_pilar_valor SET ativo = ? WHERE id = ?');
    $stmt->bind_param('ii', $active, $valueId);
    $stmt->execute();
    $stmt->close();
    $value = $valueId > 0 ? sire_admin_value($conn, $valueId) : null;
    $conn->close();
    if (!$value) {
        sire_json(['success' => false, 'message' => 'Valor não encontrado.'], 404);
    }
    sire_json(['success' => true, 'valor' => $value]);
}

if ($action === 'deleteValue') {
    $valueId = (int) ($payload['id'] ?? 0);
    $value = $valueId > 0 ? sire_admin_value($conn, $valueId) : null;
    if (!$value) {
        $conn->close();
        sire_json(['success' => false, 'message' => 'Valor não encontrado.'], 404);
    }
    if ($value['uso'] > 0) {
        $conn->close();
        sire_json(['success' => false, 'message' => 'Este valor já está em uso e só pode ser desativado.'], 409);
    }
    $stmt = $conn->prepare('DELETE FROM sire_pilar_valor WHERE id = ?');
    $stmt->bind_param('i', $valueId);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    sire_json(['success' => true]);
}

$conn->close();
sire_json(['success' => false, 'message' => 'Ação inválida.'], 422);
