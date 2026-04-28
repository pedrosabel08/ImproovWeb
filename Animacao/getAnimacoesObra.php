<?php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include '../conexao.php';

$obra_id = intval($_GET['obra_id'] ?? 0);

if (!$obra_id) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT
    a.idanimacao,
    a.imagem_id,
    ico.imagem_nome,
    a.tipo_animacao,
    CONCAT('Animação - ', UCASE(SUBSTRING(a.tipo_animacao, 1, 1)),
           LOWER(SUBSTRING(a.tipo_animacao, 2))) AS nome_animacao,
    a.substatus_id,
    su.nome_substatus AS substatus,
    a.duracao,
    a.valor,
    a.data_anima,
    ico.status_id     AS imagem_status_id,
    si.nome_status    AS imagem_status_nome,
    ico.substatus_id  AS imagem_substatus_id,
    sico.nome_substatus AS imagem_substatus_nome,
    c.nome_colaborador  AS responsavel_nome,
    MAX(CASE WHEN fa_all.funcao_id = 10 THEN c_all.nome_colaborador END) AS animacao_colaborador,
    MAX(CASE WHEN fa_all.funcao_id = 10 THEN fa_all.status END) AS animacao_status,
    MAX(CASE WHEN fa_all.funcao_id = 5 THEN c_all.nome_colaborador END) AS pos_producao_colaborador,
    MAX(CASE WHEN fa_all.funcao_id = 5 THEN fa_all.status END) AS pos_producao_status,
        CASE 
        WHEN ico.status_id = 6 AND ico.substatus_id = 9 THEN 1
        ELSE 0
    END AS animacao_pronta
FROM animacao a
JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = a.imagem_id
LEFT JOIN substatus_imagem su   ON su.id      = a.substatus_id
LEFT JOIN status_imagem    si   ON si.idstatus = ico.status_id
LEFT JOIN substatus_imagem sico ON sico.id    = ico.substatus_id
LEFT JOIN funcao_animacao  fa   ON fa.animacao_id = a.idanimacao AND fa.funcao_id = 4
LEFT JOIN colaborador      c    ON c.idcolaborador = fa.colaborador_id
LEFT JOIN funcao_animacao  fa_all ON fa_all.animacao_id = a.idanimacao
LEFT JOIN colaborador      c_all  ON c_all.idcolaborador = fa_all.colaborador_id
WHERE a.obra_id = ?
GROUP BY
    a.idanimacao,
    a.imagem_id,
    ico.imagem_nome,
    a.tipo_animacao,
    a.substatus_id,
    su.nome_substatus,
    a.duracao,
    a.valor,
    a.data_anima,
    ico.status_id,
    si.nome_status,
    ico.substatus_id,
    sico.nome_substatus,
    c.nome_colaborador
ORDER BY animacao_pronta DESC, a.idanimacao DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $obra_id);
$stmt->execute();
$result = $stmt->get_result();
$animacoes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

echo json_encode($animacoes);
