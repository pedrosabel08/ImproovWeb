<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexaoMain.php';

$conn = conectarBanco();

$obras_inativas = obterObras($conn, 1);

$sql = "SELECT
    f.nome_funcao,
    fi.funcao_id,
    ROUND(AVG(TIMESTAMPDIFF(HOUR, h1.data_aprovacao, (
        SELECT MIN(h2.data_aprovacao)
        FROM historico_aprovacoes h2
        WHERE h2.funcao_imagem_id = h1.funcao_imagem_id
          AND h2.status_anterior = 'Em aprovação'
          AND h2.data_aprovacao > h1.data_aprovacao
          AND h2.status_novo IN ('Aprovado', 'Aprovado com ajustes', 'Ajuste')
    ))), 2) AS media_horas_em_aprovacao,
    COUNT(*) AS total_tarefas,
    sf.limite_horas AS sla_limite_horas
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
LEFT JOIN
    sla_funcao sf ON sf.funcao_id = fi.funcao_id
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
        $r['media_horas_em_aprovacao'] = $r['media_horas_em_aprovacao'] !== null ? (float)$r['media_horas_em_aprovacao'] : null;
        $r['total_tarefas'] = isset($r['total_tarefas']) ? (int)$r['total_tarefas'] : 0;
        $r['sla_limite_horas'] = $r['sla_limite_horas'] !== null ? (int)$r['sla_limite_horas'] : null;
        $r['funcao_id'] = (int)$r['funcao_id'];
        $rows[] = $r;
    }
}

// Count tasks currently in SLA breach per function
$sqlBreach = "
    SELECT fi2.funcao_id, COUNT(*) AS em_breach
    FROM funcao_imagem fi2
    JOIN sla_funcao sf2 ON sf2.funcao_id = fi2.funcao_id
    JOIN imagens_cliente_obra i2 ON i2.idimagens_cliente_obra = fi2.imagem_id
    JOIN obra o2 ON o2.idobra = i2.obra_id
    JOIN historico_aprovacoes hb ON hb.funcao_imagem_id = fi2.idfuncao_imagem
        AND hb.status_novo = 'Em aprovação'
        AND hb.data_aprovacao = (
            SELECT MAX(hb2.data_aprovacao)
            FROM historico_aprovacoes hb2
            WHERE hb2.funcao_imagem_id = fi2.idfuncao_imagem
              AND hb2.status_novo = 'Em aprovação'
        )
    WHERE fi2.status = 'Em aprovação'
      AND o2.status_obra = 0
      AND fi2.funcao_id <> 7
      AND TIMESTAMPDIFF(HOUR, hb.data_aprovacao, NOW()) >= sf2.limite_horas
    GROUP BY fi2.funcao_id
";
$breachResult = $conn->query($sqlBreach);
$breachMap = [];
if ($breachResult) {
    while ($br = $breachResult->fetch_assoc()) {
        $breachMap[(int)$br['funcao_id']] = (int)$br['em_breach'];
    }
}

foreach ($rows as &$row) {
    $row['em_breach'] = $breachMap[$row['funcao_id']] ?? 0;
}
unset($row);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
