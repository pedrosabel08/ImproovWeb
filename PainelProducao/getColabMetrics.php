<?php
include '../conexao.php';
header('Content-Type: application/json');

// Espera { tipo: 'Fachada' }
$input = json_decode(file_get_contents('php://input'), true);
$tipo = $input['tipo'] ?? null;
if (!$tipo) {
    echo json_encode(['error' => 'tipo missing']);
    exit;
}

// Retorna métricas por colaborador para imagens deste tipo
// 1) taxa de aprovação (aprovado na primeira vez) — similar a getMetrics
// 2) tarefas alocadas (count de funcao_imagem com funcao_id = 4)
// 3) disponibilidade estimada: menor prazo (fi.prazo) de tarefas em andamento ou null

$sql = "SELECT
    c.idcolaborador,
    c.nome_colaborador AS nome,
    -- total de aprovações sem ajustes
    ROUND(SUM(CASE WHEN aprovado = 1 AND teve_ajuste = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS pct_aprovadas_de_primeira,
    -- total de tarefas alocadas
    COUNT(DISTINCT fi.idfuncao_imagem) AS tarefas_alocadas,
    -- próxima entrega
    MAX(fi.prazo) AS ultima_entrega
FROM colaborador c
LEFT JOIN funcao_imagem fi 
    ON fi.colaborador_id = c.idcolaborador 
    AND fi.funcao_id = 4
LEFT JOIN (
    SELECT 
        h.funcao_imagem_id,
        MAX(CASE WHEN LOWER(TRIM(h.status_novo)) = 'aprovado' THEN 1 ELSE 0 END) AS aprovado,
        MAX(CASE WHEN LOWER(TRIM(h.status_novo)) = 'aprovado com ajustes' THEN 1 ELSE 0 END) AS aprovado_com_ajustes,
        MAX(CASE WHEN LOWER(TRIM(h.status_novo)) = 'ajuste' THEN 1 ELSE 0 END) AS teve_ajuste
    FROM historico_aprovacoes h
    GROUP BY h.funcao_imagem_id
) hist 
    ON hist.funcao_imagem_id = fi.idfuncao_imagem
LEFT JOIN imagens_cliente_obra ico 
    ON ico.idimagens_cliente_obra = fi.imagem_id
LEFT JOIN obra o 
    ON o.idobra = ico.obra_id
WHERE ico.tipo_imagem = ?
  AND o.status_obra = 0
GROUP BY c.idcolaborador, c.nome_colaborador
ORDER BY tarefas_alocadas ASC, ultima_entrega ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $tipo);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);

// montar resposta simples — o cálculo de porcentagem pode ser feito no frontend facilmente
foreach ($rows as &$r) {
    $r['pct_aprovadas_de_primeira'] = (float)$r['pct_aprovadas_de_primeira']; // deixar decimal
    $r['tarefas_alocadas'] = (int)$r['tarefas_alocadas'];
    
    // Formatar ultima_entrega como 'YYYY-MM-DD' ou 'DD/MM/YYYY'
    if ($r['ultima_entrega']) {
        $r['ultima_entrega'] = date('d/m/Y', strtotime($r['ultima_entrega']));
    } else {
        $r['ultima_entrega'] = null; // caso não haja nenhuma entrega
    }
}


echo json_encode(['tipo' => $tipo, 'colaboradores' => $rows], JSON_UNESCAPED_UNICODE);

$conn->close();
