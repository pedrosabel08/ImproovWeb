<?php
// Incluir o arquivo de conexão central
include_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// ── Totais gerais ─────────────────────────────────────────────
$sqlTotais = "SELECT
    ROUND(SUM(fi.valor))                                        AS total_producao,
    (SELECT COUNT(DISTINCT idobra) FROM obra WHERE status_obra = 0) AS obras_ativas,
    ROUND((SELECT SUM(cc.valor) FROM controle_comercial cc))    AS total_orcamento,

    /* produção do mês atual (pelo prazo da funcao_imagem) */
    ROUND(SUM(CASE
        WHEN MONTH(fi.prazo) = MONTH(NOW())
         AND YEAR(fi.prazo)  = YEAR(NOW())
        THEN fi.valor ELSE 0 END))                              AS producao_mes_atual,

    /* produção do mês anterior */
    ROUND(SUM(CASE
        WHEN MONTH(fi.prazo) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
         AND YEAR(fi.prazo)  = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
        THEN fi.valor ELSE 0 END))                              AS producao_mes_anterior

FROM funcao_imagem fi
JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
JOIN obra o ON o.idobra = ico.obra_id";

$result = $conn->query($sqlTotais);

if (!$result || $result->num_rows === 0) {
    echo json_encode(['message' => 'Nenhum dado encontrado']);
    $conn->close();
    exit;
}

$row = $result->fetch_assoc();

// ── Obras ativas há 3 meses (proxy: obras com prazo no período) ─
$sqlObras3m = "SELECT COUNT(DISTINCT ico.obra_id) AS obras_ativas_3m_ago
FROM funcao_imagem fi
JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
WHERE MONTH(fi.prazo) = MONTH(DATE_SUB(NOW(), INTERVAL 3 MONTH))
  AND YEAR(fi.prazo)  = YEAR(DATE_SUB(NOW(), INTERVAL 3 MONTH))";

$result3m   = $conn->query($sqlObras3m);
$row3m      = $result3m ? $result3m->fetch_assoc() : ['obras_ativas_3m_ago' => 0];

$conn->close();

echo json_encode([[
    'total_producao'        => (float) ($row['total_producao']        ?? 0),
    'obras_ativas'          => (int)   ($row['obras_ativas']          ?? 0),
    'total_orcamento'       => (float) ($row['total_orcamento']       ?? 0),
    'producao_mes_atual'    => (float) ($row['producao_mes_atual']    ?? 0),
    'producao_mes_anterior' => (float) ($row['producao_mes_anterior'] ?? 0),
    'obras_ativas_3m_ago'   => (int)   ($row3m['obras_ativas_3m_ago'] ?? 0),
]]);
