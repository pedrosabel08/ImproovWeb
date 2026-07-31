<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/sire_helpers.php';

sire_require_auth();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

require_once __DIR__ . '/../conexaoMain.php';

if (($_GET['action'] ?? '') !== 'getRefs') {
    sire_json(['success' => false, 'message' => 'Ação inválida.'], 400);
}

$conn = conectarBanco();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(96, max(12, (int) ($_GET['per_page'] ?? 48)));
$offset = ($page - 1) * $perPage;
$search = trim((string) ($_GET['search'] ?? ''));
$obraId = max(0, (int) ($_GET['obra_id'] ?? 0));
$ambiente = trim((string) ($_GET['ambiente'] ?? ''));
$golden = (string) ($_GET['golden'] ?? '') === '1';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(sr.titulo LIKE ? OR sr.descricao LIKE ? OR ri.nome_arquivo LIKE ? OR ri.nomenclatura LIKE ? OR o.nomenclatura LIKE ? OR i.tipo_imagem LIKE ? )";
    $like = '%' . $search . '%';
    for ($i = 0; $i < 6; $i++) {
        $params[] = $like;
        $types .= 's';
    }
}
if ($obraId > 0) {
    $where[] = 'i.obra_id = ?';
    $params[] = $obraId;
    $types .= 'i';
}
if ($ambiente !== '') {
    $where[] = 'i.tipo_imagem = ?';
    $params[] = $ambiente;
    $types .= 's';
}
if ($golden) {
    $where[] = 'sr.golden_sample = 1';
}

$allowedPillars = ['atmosfera', 'luz', 'fotografia', 'arquitetura', 'materialidade', 'composicao', 'lifestyle'];
foreach ($allowedPillars as $codigo) {
    $rawValues = $_GET['pilar_' . $codigo] ?? [];
    if (!is_array($rawValues)) {
        $rawValues = explode(',', (string) $rawValues);
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $rawValues), static fn($id) => $id > 0)));
    if (!$ids) {
        continue;
    }
    $where[] = "EXISTS (
        SELECT 1
        FROM sire_referencia_valor srv
        INNER JOIN sire_pilar_valor spv ON spv.id = srv.valor_id
        INNER JOIN sire_pilar sp ON sp.id = spv.pilar_id
        WHERE srv.referencia_id = sr.id
          AND sp.codigo = '" . $conn->real_escape_string($codigo) . "'
          AND spv.id IN (" . implode(',', $ids) . ")
    )";
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$baseSql = "
    FROM sire_referencia sr
    LEFT JOIN referencias_imagens ri ON ri.id = sr.referencia_imagem_id
    LEFT JOIN funcao_imagem fi ON fi.idfuncao_imagem = ri.funcao_imagem_id
    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
    LEFT JOIN obra o ON o.idobra = i.obra_id
";

$countStmt = $conn->prepare('SELECT COUNT(*) AS total ' . $baseSql . $whereSql);
if (!$countStmt) {
    sire_json(['success' => false, 'message' => 'Erro ao preparar catálogo.'], 500);
}
sire_bind_params($countStmt, $types, $params);
$countStmt->execute();
$total = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

// MantÃ©m os indicadores sincronizados com a mesma busca e os mesmos filtros
// aplicados Ã  grade, sem uma segunda chamada no cliente.
$statsStmt = $conn->prepare("SELECT
    COUNT(*) AS referencias,
    SUM(CASE WHEN sr.origem = 'Flow' THEN 1 ELSE 0 END) AS internas,
    SUM(CASE WHEN sr.origem <> 'Flow' THEN 1 ELSE 0 END) AS externas,
    SUM(CASE WHEN sr.criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS novas_semana
    " . $baseSql . $whereSql);
if (!$statsStmt) {
    sire_json(['success' => false, 'message' => 'Erro ao preparar indicadores do catÃ¡logo.'], 500);
}
sire_bind_params($statsStmt, $types, $params);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc() ?: [];
$statsStmt->close();

$sql = "
    SELECT sr.*, ri.funcao_imagem_id, ri.nomenclatura AS flow_nomenclatura,
           ri.nome_arquivo AS flow_nome_arquivo, ri.importado_em,
           i.obra_id, i.tipo_imagem AS ambiente, o.nomenclatura AS obra_nomenclatura,
           i.imagem_nome,
           SUBSTRING(i.imagem_nome, LOCATE(' ', i.imagem_nome) + 1) AS imagem_nome_curto
    " . $baseSql . $whereSql . "
    ORDER BY sr.golden_sample DESC, sr.criado_em DESC, sr.id DESC
    LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    sire_json(['success' => false, 'message' => 'Erro ao preparar referências.'], 500);
}
$queryParams = $params;
$queryParams[] = $perPage;
$queryParams[] = $offset;
$queryTypes = $types . 'ii';
sire_bind_params($stmt, $queryTypes, $queryParams);
$stmt->execute();
$result = $stmt->get_result();
$refs = [];
while ($row = $result->fetch_assoc()) {
    $row['imagem_url'] = sire_reference_image_url($row);
    $row['thumbnail_url'] = sire_reference_thumbnail_url($row, 360, 75);
    $row['nomenclatura'] = $row['flow_nomenclatura'] ?: $row['titulo'];
    $row['nome_arquivo'] = $row['flow_nome_arquivo'] ?: $row['nome_arquivo'];
    $row['classificacoes'] = [];
    $refs[] = $row;
}
$stmt->close();

// Mantém a grade leve, mas entrega poucos rótulos de classificação já no
// payload da página atual. A classificação completa continua no endpoint da
// referência aberta no modal.
if ($refs) {
    $referenceIds = array_map(static fn($reference) => (int) $reference['id'], $refs);
    $classificationResult = $conn->query(
        "SELECT srv.referencia_id, sp.codigo, spv.nome
         FROM sire_referencia_valor srv
         INNER JOIN sire_pilar_valor spv ON spv.id = srv.valor_id
         INNER JOIN sire_pilar sp ON sp.id = spv.pilar_id
         WHERE srv.referencia_id IN (" . implode(',', $referenceIds) . ")
         ORDER BY sp.ordem, spv.nome"
    );
    if ($classificationResult) {
        $classificationsByReference = [];
        while ($classification = $classificationResult->fetch_assoc()) {
            $classificationsByReference[(int) $classification['referencia_id']][] = [
                'codigo' => $classification['codigo'],
                'nome' => $classification['nome'],
            ];
        }
        foreach ($refs as &$reference) {
            $reference['classificacoes'] = $classificationsByReference[(int) $reference['id']] ?? [];
        }
        unset($reference);
        $classificationResult->free();
    }
}
$conn->close();

sire_json([
    'success' => true,
    'refs' => $refs,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage,
    'total_pages' => (int) ceil($total / $perPage),
    'stats' => [
        'referencias' => (int) ($stats['referencias'] ?? $total),
        'internas' => (int) ($stats['internas'] ?? 0),
        'externas' => (int) ($stats['externas'] ?? 0),
        'novas_semana' => (int) ($stats['novas_semana'] ?? 0),
    ],
]);
