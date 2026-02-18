<?php
header('Content-Type: application/json');
include '../conexao.php';

$obraId = isset($_GET['obra_id']) && $_GET['obra_id'] !== '' ? (int)$_GET['obra_id'] : null;
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$colaboradorId = isset($_GET['colaborador_id']) && $_GET['colaborador_id'] !== '' ? (int)$_GET['colaborador_id'] : null;
$busca = isset($_GET['busca']) ? trim((string)$_GET['busca']) : '';

$sql = "SELECT
    f.idfuncao_imagem,
    f.imagem_id,
    f.colaborador_id,
    COALESCE(NULLIF(TRIM(f.status), ''), 'Não iniciado') AS status_funcao,
    i.prazo,
    i.imagem_nome,
    i.obra_id,
    o.nomenclatura,
    c.nome_colaborador
FROM funcao_imagem f
JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
JOIN obra o ON o.idobra = i.obra_id
LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
WHERE f.funcao_id = 6 AND o.status_obra = 0";

$params = [];
$types = '';

if ($obraId !== null) {
    $sql .= " AND i.obra_id = ?";
    $params[] = $obraId;
    $types .= 'i';
}

if ($status !== '') {
    $sql .= " AND COALESCE(NULLIF(TRIM(f.status), ''), 'Não iniciado') = ?";
    $params[] = $status;
    $types .= 's';
}

if ($colaboradorId !== null) {
    $sql .= " AND f.colaborador_id = ?";
    $params[] = $colaboradorId;
    $types .= 'i';
}

if ($busca !== '') {
    $sql .= " AND (i.imagem_nome LIKE ? OR o.nomenclatura LIKE ? OR COALESCE(c.nome_colaborador, '') LIKE ?)";
    $like = '%' . $busca . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$sql .= " ORDER BY i.prazo IS NULL, i.prazo ASC, i.imagem_nome ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Erro ao preparar consulta.']);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$items = [];
$obrasMap = [];
$colabMap = [];

while ($row = $result->fetch_assoc()) {
    $statusNormalizado = mb_strtolower(trim((string)$row['status_funcao']), 'UTF-8');
    if ($statusNormalizado === '') {
        $statusNormalizado = 'não iniciado';
    }

    $item = [
        'funcao_id' => (int)$row['idfuncao_imagem'],
        'imagem_id' => (int)$row['imagem_id'],
        'imagem_nome' => (string)$row['imagem_nome'],
        'obra_id' => (int)$row['obra_id'],
        'obra_nome' => (string)$row['nomenclatura'],
        'colaborador_id' => $row['colaborador_id'] !== null ? (int)$row['colaborador_id'] : null,
        'colaborador_nome' => $row['nome_colaborador'] !== null ? (string)$row['nome_colaborador'] : null,
        'status_funcao' => (string)$row['status_funcao'],
        'status_key' => $statusNormalizado,
        'prazo' => $row['prazo'] ? date('d/m/Y', strtotime($row['prazo'])) : null,
    ];

    $items[] = $item;
    $obrasMap[$item['obra_id']] = $item['obra_nome'];

    if ($item['colaborador_id'] !== null) {
        $colabMap[$item['colaborador_id']] = $item['colaborador_nome'];
    }
}

$obras = [];
foreach ($obrasMap as $id => $nome) {
    $obras[] = ['id' => (int)$id, 'nome' => $nome];
}

$colaboradores = [];
foreach ($colabMap as $id => $nome) {
    $colaboradores[] = ['id' => (int)$id, 'nome' => $nome];
}

echo json_encode([
    'success' => true,
    'items' => $items,
    'filtros' => [
        'obras' => $obras,
        'colaboradores' => $colaboradores
    ]
]);
exit;
