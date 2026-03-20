<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
include '../conexao.php';

$idColaborador = $_SESSION['idcolaborador'];

$response = [
    'total_mencoes'              => 0,
    'mencoes_por_obra'           => [],
    'mencoes_por_funcao_imagem'  => [],
    'comentarios_mencionados'    => [],
    'respostas_mencionadas'      => [],
];

// ── Contagem por obra (não vistas) ──────────────────────────────────────────
$stmt = $conn->prepare("SELECT 
    o.nome_obra,
    COUNT(*) AS qtd_mencoes
FROM 
    mencoes m
INNER JOIN comentarios_imagem c ON c.id = m.comentario_id
INNER JOIN historico_aprovacoes_imagens hai ON hai.id = c.ap_imagem_id
INNER JOIN funcao_imagem fi ON fi.idfuncao_imagem = hai.funcao_imagem_id
INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
INNER JOIN obra o ON o.idobra = ico.obra_id
WHERE 
    m.mencionado_id = ?
    AND m.visto = 0
GROUP BY 
    o.nome_obra");
$stmt->bind_param("i", $idColaborador);
$stmt->execute();
$result = $stmt->get_result();

$total = 0;
while ($row = $result->fetch_assoc()) {
    $obra = $row['nome_obra'];
    $qtd  = (int)$row['qtd_mencoes'];
    $total += $qtd;
    $response['mencoes_por_obra'][$obra] = ($response['mencoes_por_obra'][$obra] ?? 0) + $qtd;
}
$response['total_mencoes'] = $total;

// ── Contagem por funcao_imagem_id (não vistas) ──────────────────────────────
$stmt2 = $conn->prepare("SELECT 
    hai.funcao_imagem_id,
    COUNT(*) AS qtd_mencoes
FROM 
    mencoes m
INNER JOIN comentarios_imagem c ON c.id = m.comentario_id
INNER JOIN historico_aprovacoes_imagens hai ON hai.id = c.ap_imagem_id
WHERE 
    m.mencionado_id = ?
    AND m.visto = 0
GROUP BY 
    hai.funcao_imagem_id");
$stmt2->bind_param("i", $idColaborador);
$stmt2->execute();
$result2 = $stmt2->get_result();

while ($row = $result2->fetch_assoc()) {
    $response['mencoes_por_funcao_imagem'][(string)$row['funcao_imagem_id']] = (int)$row['qtd_mencoes'];
}

// ── IDs dos comentários com menções não vistas ──────────────────────────────
$stmt3 = $conn->prepare("SELECT comentario_id FROM mencoes WHERE mencionado_id = ? AND visto = 0 AND comentario_id IS NOT NULL");
$stmt3->bind_param("i", $idColaborador);
$stmt3->execute();
$result3 = $stmt3->get_result();

while ($row = $result3->fetch_assoc()) {
    $response['comentarios_mencionados'][] = (int)$row['comentario_id'];
}

// ── Menções em respostas: contagem por obra ──────────────────────────────────
$stmt4 = $conn->prepare("SELECT
    o.nome_obra,
    COUNT(*) AS qtd_mencoes
FROM
    mencoes m
INNER JOIN respostas_comentario rc ON rc.id = m.resposta_id
INNER JOIN comentarios_imagem c ON c.id = rc.comentario_id
INNER JOIN historico_aprovacoes_imagens hai ON hai.id = c.ap_imagem_id
INNER JOIN funcao_imagem fi ON fi.idfuncao_imagem = hai.funcao_imagem_id
INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
INNER JOIN obra o ON o.idobra = ico.obra_id
WHERE
    m.mencionado_id = ?
    AND m.visto = 0
    AND m.resposta_id IS NOT NULL
GROUP BY
    o.nome_obra");
$stmt4->bind_param("i", $idColaborador);
$stmt4->execute();
$result4 = $stmt4->get_result();

while ($row = $result4->fetch_assoc()) {
    $obra = $row['nome_obra'];
    $qtd  = (int)$row['qtd_mencoes'];
    $total += $qtd;
    $response['mencoes_por_obra'][$obra] = ($response['mencoes_por_obra'][$obra] ?? 0) + $qtd;
}
$response['total_mencoes'] = $total;

// ── Menções em respostas: contagem por funcao_imagem_id ──────────────────────
$stmt5 = $conn->prepare("SELECT
    hai.funcao_imagem_id,
    COUNT(*) AS qtd_mencoes
FROM
    mencoes m
INNER JOIN respostas_comentario rc ON rc.id = m.resposta_id
INNER JOIN comentarios_imagem c ON c.id = rc.comentario_id
INNER JOIN historico_aprovacoes_imagens hai ON hai.id = c.ap_imagem_id
WHERE
    m.mencionado_id = ?
    AND m.visto = 0
    AND m.resposta_id IS NOT NULL
GROUP BY
    hai.funcao_imagem_id");
$stmt5->bind_param("i", $idColaborador);
$stmt5->execute();
$result5 = $stmt5->get_result();

while ($row = $result5->fetch_assoc()) {
    $key = (string)$row['funcao_imagem_id'];
    $response['mencoes_por_funcao_imagem'][$key] = ($response['mencoes_por_funcao_imagem'][$key] ?? 0) + (int)$row['qtd_mencoes'];
}

// ── IDs das respostas com menções não vistas ────────────────────────────────
$stmt6 = $conn->prepare("SELECT resposta_id FROM mencoes WHERE mencionado_id = ? AND visto = 0 AND resposta_id IS NOT NULL");
$stmt6->bind_param("i", $idColaborador);
$stmt6->execute();
$result6 = $stmt6->get_result();

while ($row = $result6->fetch_assoc()) {
    $response['respostas_mencionadas'][] = (int)$row['resposta_id'];
}

header('Content-Type: application/json');
echo json_encode($response);
