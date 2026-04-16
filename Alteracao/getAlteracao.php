<?php
header('Content-Type: application/json');
include '../conexao.php';

$obraId = isset($_GET['obra_id']) && $_GET['obra_id'] !== '' ? (int)$_GET['obra_id'] : null;
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$statusImagem = isset($_GET['status_imagem']) ? trim((string)$_GET['status_imagem']) : '';
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
    c.nome_colaborador,
    s.nome_status,
    a.status_id AS imagem_status_id
FROM alteracoes a
JOIN funcao_imagem f ON f.idfuncao_imagem = a.funcao_id
JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
JOIN obra o ON o.idobra = i.obra_id
JOIN status_imagem s ON s.idstatus = a.status_id
LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
WHERE f.funcao_id = 6 AND o.status_obra = 0 AND a.status_id = i.status_id
AND (f.status != 'Finalizado' OR (f.status = 'Finalizado' AND i.prazo = CURDATE()))";

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

if ($statusImagem !== '') {
    $sql .= " AND s.nome_status = ?";
    $params[] = $statusImagem;
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
        'status_nome' => (string)$row['nome_status'],
        'prazo' => $row['prazo'] ? date('d/m/Y', strtotime($row['prazo'])) : null,
        'is_ef' => ((int)$row['imagem_status_id'] === 6),
        'idstatus' => (int)$row['imagem_status_id'],
    ];

    $items[] = $item;
    $obrasMap[$item['obra_id']] = $item['obra_nome'];

    if ($item['colaborador_id'] !== null) {
        $colabMap[$item['colaborador_id']] = $item['colaborador_nome'];
    }

    if ($item['idstatus'] !== null) {
        $statusImagemMap[$item['idstatus']] = $item['status_nome'];
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

$statusImagem = [];
foreach ($statusImagemMap as $id => $nome) {
    $statusImagem[] = ['id' => (int)$id, 'nome' => $nome];
}

echo json_encode([
    'success' => true,
    'items' => $items,
    'filtros' => [
        'obras' => $obras,
        'colaboradores' => $colaboradores,
        'status_imagens' => $statusImagem
    ]
]);
exit;
