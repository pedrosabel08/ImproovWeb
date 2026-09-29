<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';

$idColaborador = $_SESSION['idcolaborador'];

$response = [
    'total_mencoes'              => 0,
    'mencoes_por_obra'           => [],
    'mencoes_por_funcao_imagem'  => [],
    'comentarios_mencionados'    => [],
    'respostas_mencionadas'      => [],
    'mencoes_detalhadas'         => [],
];

// ── Contagem por obra (não vistas) ──────────────────────────────────────────
$stmt = $conn->prepare("SELECT
    o.nomenclatura,
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
    AND fi.status NOT IN ('Finalizado', 'Aprovado')
GROUP BY
    o.nomenclatura");
$stmt->bind_param("i", $idColaborador);
$stmt->execute();
$result = $stmt->get_result();

$total = 0;
while ($row = $result->fetch_assoc()) {
    $obra = $row['nomenclatura'];
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
INNER JOIN funcao_imagem fi ON fi.idfuncao_imagem = hai.funcao_imagem_id
WHERE 
    m.mencionado_id = ?
    AND m.visto = 0
    AND fi.status NOT IN ('Finalizado', 'Aprovado')
GROUP BY 
    hai.funcao_imagem_id");
$stmt2->bind_param("i", $idColaborador);
$stmt2->execute();
$result2 = $stmt2->get_result();

while ($row = $result2->fetch_assoc()) {
    $response['mencoes_por_funcao_imagem'][(string)$row['funcao_imagem_id']] = (int)$row['qtd_mencoes'];
}

// ── IDs dos comentários com menções não vistas ──────────────────────────────
$stmt3 = $conn->prepare("SELECT comentario_id FROM mencoes m
INNER JOIN comentarios_imagem c ON c.id = m.comentario_id
INNER JOIN historico_aprovacoes_imagens hai ON hai.id = c.ap_imagem_id
INNER JOIN funcao_imagem fi ON fi.idfuncao_imagem = hai.funcao_imagem_id
WHERE m.mencionado_id = ? AND m.visto = 0 AND comentario_id IS NOT NULL AND fi.status NOT IN ('Finalizado', 'Aprovado')");
$stmt3->bind_param("i", $idColaborador);
$stmt3->execute();
$result3 = $stmt3->get_result();

while ($row = $result3->fetch_assoc()) {
    $response['comentarios_mencionados'][] = (int)$row['comentario_id'];
}

// ── Menções em respostas: contagem por obra ──────────────────────────────────
$stmt4 = $conn->prepare("SELECT
    o.nomenclatura,
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
    o.nomenclatura");
$stmt4->bind_param("i", $idColaborador);
$stmt4->execute();
$result4 = $stmt4->get_result();

while ($row = $result4->fetch_assoc()) {
    $obra = $row['nomenclatura'];
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
$stmt6 = $conn->prepare("SELECT resposta_id FROM mencoes m
INNER JOIN respostas_comentario rc ON rc.id = m.resposta_id
INNER JOIN comentarios_imagem c ON c.id = rc.comentario_id
INNER JOIN historico_aprovacoes_imagens hai ON hai.id = c.ap_imagem_id
INNER JOIN funcao_imagem fi ON fi.idfuncao_imagem = hai.funcao_imagem_id
WHERE m.mencionado_id = ? AND m.visto = 0 AND m.resposta_id IS NOT NULL AND fi.status NOT IN ('Finalizado', 'Aprovado')");
$stmt6->bind_param("i", $idColaborador);
$stmt6->execute();
$result6 = $stmt6->get_result();

while ($row = $result6->fetch_assoc()) {
    $response['respostas_mencionadas'][] = (int)$row['resposta_id'];
}

// Dados agrupados em uma consulta para que o modal consiga abrir a tarefa
// mencionada sem buscar cada comentário ou tarefa individualmente.
$sqlDetalhes = "SELECT
    m.comentario_id,
    NULL AS resposta_id,
    hai.funcao_imagem_id AS task_id,
    'imagem' AS tipo_tarefa,
    o.nomenclatura,
    ico.imagem_nome,
    c.texto,
    autor.nome_colaborador AS autor,
    c.data AS data_mencao
FROM mencoes m
INNER JOIN comentarios_imagem c ON c.id = m.comentario_id
INNER JOIN historico_aprovacoes_imagens hai ON hai.id = c.ap_imagem_id
INNER JOIN funcao_imagem fi ON fi.idfuncao_imagem = hai.funcao_imagem_id
INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
INNER JOIN obra o ON o.idobra = ico.obra_id
LEFT JOIN colaborador autor ON autor.idcolaborador = c.responsavel_id
WHERE m.mencionado_id = ?
  AND m.visto = 0
  AND fi.status NOT IN ('Finalizado', 'Aprovado')

UNION ALL

SELECT
    c.id AS comentario_id,
    rc.id AS resposta_id,
    hai.funcao_imagem_id AS task_id,
    'imagem' AS tipo_tarefa,
    o.nomenclatura,
    ico.imagem_nome,
    rc.texto,
    autor.nome_colaborador AS autor,
    rc.data AS data_mencao
FROM mencoes m
INNER JOIN respostas_comentario rc ON rc.id = m.resposta_id
INNER JOIN comentarios_imagem c ON c.id = rc.comentario_id
INNER JOIN historico_aprovacoes_imagens hai ON hai.id = c.ap_imagem_id
INNER JOIN funcao_imagem fi ON fi.idfuncao_imagem = hai.funcao_imagem_id
INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
INNER JOIN obra o ON o.idobra = ico.obra_id
LEFT JOIN colaborador autor ON autor.idcolaborador = rc.responsavel
WHERE m.mencionado_id = ?
  AND m.visto = 0
  AND m.resposta_id IS NOT NULL
ORDER BY data_mencao DESC";
$stmtDetails = $conn->prepare($sqlDetalhes);
if ($stmtDetails) {
    $stmtDetails->bind_param('ii', $idColaborador, $idColaborador);
    $stmtDetails->execute();
    $detailsResult = $stmtDetails->get_result();
    while ($row = $detailsResult->fetch_assoc()) {
        $row['comentario_id'] = (int)$row['comentario_id'];
        $row['resposta_id'] = $row['resposta_id'] !== null ? (int)$row['resposta_id'] : null;
        $row['task_id'] = (int)$row['task_id'];
        $response['mencoes_detalhadas'][] = $row;
    }
    $stmtDetails->close();
}

header('Content-Type: application/json');
echo json_encode($response);
