<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexaoMain.php';

$conn = conectarBanco();

$obras_inativas = obterObras($conn, 1);

$sql = "SELECT
    f.nome_funcao,
    ROUND(AVG(TIMESTAMPDIFF(HOUR, h1.data_aprovacao, (
        SELECT MIN(h2.data_aprovacao)
        FROM historico_aprovacoes h2
        WHERE h2.funcao_imagem_id = h1.funcao_imagem_id
          AND h2.status_anterior = 'Em aprovação'
          AND h2.data_aprovacao > h1.data_aprovacao
          AND h2.status_novo IN ('Aprovado', 'Aprovado com ajustes', 'Ajuste')
    ))), 2) AS media_horas_em_aprovacao,
    COUNT(*) AS total_tarefas
FROM 
    historico_aprovacoes h1
JOIN 
    funcao_imagem fi ON fi.idfuncao_imagem = h1.funcao_imagem_id
JOIN 
    funcao f ON f.idfuncao = fi.funcao_id 
JOIN
    imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
JOIN 
    obra o ON o.idobra = i.obra_id
WHERE 
    h1.status_novo = 'Em aprovação'
    AND o.status_obra = 0 
    AND fi.funcao_id <> 7
    AND h1.data_aprovacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY 
    fi.funcao_id";

$result = $conn->query($sql);

$rows = [];
if ($result) {
    while ($r = $result->fetch_assoc()) {
        // ensure numeric types
        $r['media_horas_em_aprovacao'] = $r['media_horas_em_aprovacao'] !== null ? (float)$r['media_horas_em_aprovacao'] : null;
        $r['total_tarefas'] = isset($r['total_tarefas']) ? (int)$r['total_tarefas'] : 0;
        $rows[] = $r;
    }
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);

?>
