<?php
include_once __DIR__ . '/../conexao.php';

$sql = "SELECT 
    p.idpos_producao,
    col.nome_colaborador,
    o.nome_obra,
    o.idobra,
    p.data_pos,
    o.nomenclatura,
    i.imagem_nome,
    p.caminho_pasta,
    p.numero_bg,
    p.refs,
    p.obs,
    p.status_pos,
    s.nome_status,
    resp.nome_colaborador AS nome_responsavel,
    r.status AS status_render,
    i.prazo,
    p.prioridade,
    p.flag_urgente,
    ppo.prioridade AS prioridade_obra
FROM pos_producao p
INNER JOIN colaborador col ON p.colaborador_id = col.idcolaborador
INNER JOIN obra o ON p.obra_id = o.idobra
INNER JOIN imagens_cliente_obra i ON p.imagem_id = i.idimagens_cliente_obra
INNER JOIN status_imagem s ON p.status_id = s.idstatus
LEFT JOIN colaborador resp ON p.responsavel_id = resp.idcolaborador
LEFT JOIN render_alta r ON p.render_id = r.idrender_alta
LEFT JOIN pos_prioridade_obra ppo ON o.idobra = ppo.obra_id
ORDER BY
    CASE WHEN p.status_pos = 1 THEN 0 ELSE 1 END,
    CASE WHEN p.status_pos != 0 THEN p.flag_urgente END DESC,
    CASE WHEN p.status_pos != 0 THEN (ppo.prioridade IS NULL) END ASC,
    CASE WHEN p.status_pos != 0 THEN ppo.prioridade END ASC,
    CASE WHEN p.status_pos != 0 THEN p.prioridade END ASC,
    CASE WHEN p.status_pos != 0 THEN (i.prazo IS NULL) END ASC,
    p.data_pos DESC;                                 
";

$result = $conn->query($sql);

$data = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

// Retorna os dados em formato JSON
echo json_encode($data);
