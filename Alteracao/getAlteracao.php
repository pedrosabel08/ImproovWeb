<?php
// kanban.php
header('Content-Type: application/json');
include '../conexao.php'; // conexao com mysqli

$sql = "SELECT 
    a.idalt,
    f.idfuncao_imagem,
    f.imagem_id,
    f.colaborador_id,
    f.status AS status_funcao,
    i.prazo,
    i.status_id,
    i.imagem_nome,
    c.nome_colaborador AS colaborador_nome,
    s.nome_status AS status_nome,
    o.idobra,
    o.nomenclatura
FROM alteracoes a
JOIN funcao_imagem f ON a.funcao_id = f.idfuncao_imagem
JOIN imagens_cliente_obra i ON f.imagem_id = i.idimagens_cliente_obra
JOIN colaborador c ON f.colaborador_id = c.idcolaborador
JOIN status_imagem s ON a.status_id = s.idstatus
JOIN obra o ON i.obra_id = o.idobra
ORDER BY f.imagem_id, s.nome_status, o.idobra, f.prazo";

$result = $conn->query($sql);

$kanban = [];

while ($row = $result->fetch_assoc()) {
    $status_funcao = strtolower(str_replace(' ', '', $row['status_funcao']));
    $obra = $row['nomenclatura'];

    if (!isset($kanban[$status_funcao])) {
        $kanban[$status_funcao] = [];
    }
    if (!isset($kanban[$status_funcao][$obra])) {
        $kanban[$status_funcao][$obra] = [
            'prazo' => $row['prazo'],
            'imagens' => []
        ];
    }

    $kanban[$status_funcao][$obra]['imagens'][] = [
        'imagem' => $row['imagem_nome'],
        'colaborador' => $row['colaborador_nome'],
        'prazo' => date('d/m/Y', strtotime($row['prazo'])),
        'status_alteracao' => $row['status_nome']
    ];
}

echo json_encode($kanban);
exit;
