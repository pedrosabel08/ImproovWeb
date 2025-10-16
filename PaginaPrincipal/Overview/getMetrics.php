<?php
include '../../conexao.php';
header('Content-Type: application/json');

$response = [];

// ===============================
// 🔹 1. Taxa de aprovação
// ===============================
$sqlTaxa = "WITH funcoes_finalizadas AS (
    SELECT idfuncao_imagem
    FROM funcao_imagem
    WHERE colaborador_id = 20
      AND status = 'Finalizado'
      " . (24 == 24 ? "AND funcao_id = 4" : "") . "
),
hist_por_funcao AS (
    SELECT
        h.funcao_imagem_id,
        MAX(CASE WHEN LOWER(TRIM(h.status_novo)) = 'aprovado' THEN 1 ELSE 0 END) AS aprovado,
        MAX(CASE WHEN LOWER(TRIM(h.status_novo)) = 'aprovado com ajustes' THEN 1 ELSE 0 END) AS aprovado_com_ajustes,
        MAX(CASE WHEN LOWER(TRIM(h.status_novo)) = 'ajuste' THEN 1 ELSE 0 END) AS teve_ajuste
    FROM historico_aprovacoes h
    WHERE h.funcao_imagem_id IN (SELECT idfuncao_imagem FROM funcoes_finalizadas)
    GROUP BY h.funcao_imagem_id
)
SELECT
    COUNT(*) AS total_com_historico,
    SUM(CASE WHEN aprovado = 1 AND teve_ajuste = 0 THEN 1 ELSE 0 END) AS total_aprovadas_de_primeira,
    ROUND(SUM(CASE WHEN aprovado = 1 AND teve_ajuste = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS pct_aprovadas_de_primeira,
    SUM(CASE WHEN aprovado_com_ajustes = 1 AND teve_ajuste = 0 THEN 1 ELSE 0 END) AS total_aprovadas_com_ajustes_de_primeira,
    ROUND(SUM(CASE WHEN aprovado_com_ajustes = 1 AND teve_ajuste = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS pct_aprovadas_com_ajustes_de_primeira,
    SUM(teve_ajuste) AS total_que_tiveram_ajuste,
    ROUND(SUM(teve_ajuste) * 100.0 / COUNT(*), 2) AS pct_que_tiveram_ajuste
FROM hist_por_funcao
";

$resultTaxa = $conn->query($sqlTaxa);
if ($resultTaxa && $resultTaxa->num_rows > 0) {
    $response['taxa_aprovacao'] = $resultTaxa->fetch_assoc();
} else {
    $response['taxa_aprovacao'] = null;
}

// ===============================
// 🔹 2. Média de tempo de conclusão
// ===============================
$sqlTempo = "WITH eventos_trabalho AS (
    SELECT 
        fi.funcao_id,
        f.nome_funcao,
        l.funcao_imagem_id,
        l.data AS inicio,
        LEAD(l.data) OVER (PARTITION BY l.funcao_imagem_id ORDER BY l.data) AS termino,
        l.status_anterior,
        l.status_novo
    FROM log_alteracoes l
    JOIN funcao_imagem fi ON fi.idfuncao_imagem = l.funcao_imagem_id
    JOIN funcao f ON f.idfuncao = fi.funcao_id
    JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    JOIN obra o ON o.idobra = ico.obra_id
    WHERE l.colaborador_id = 20
      AND o.status_obra = 0
)
SELECT 
    nome_funcao,
    ROUND(AVG(TIMESTAMPDIFF(HOUR, inicio, termino)), 2) AS tempo_medio_horas
FROM eventos_trabalho
WHERE termino IS NOT NULL
  AND (
      (status_anterior = 'Não iniciado' AND status_novo = 'Em andamento') OR
      (status_anterior = 'Em aprovação' AND status_novo = 'Ajuste')
  )
GROUP BY nome_funcao
ORDER BY tempo_medio_horas DESC
";

$resultTempo = $conn->query($sqlTempo);
$response['tempo_medio_conclusao'] = $resultTempo ? $resultTempo->fetch_all(MYSQLI_ASSOC) : [];


// ===============================
// 🔹 3. Quantidade de funções finalizadas por mês
// ===============================
$sqlFinalizadas = "SELECT f.nome_funcao, COUNT(DISTINCT fi.idfuncao_imagem) AS total_finalizadas FROM funcao_imagem fi
JOIN funcao f ON f.idfuncao = fi.funcao_id
WHERE colaborador_id = 20
AND YEAR(fi.prazo) = YEAR(CURDATE())
AND MONTH(fi.prazo) = MONTH(CURDATE())
AND fi.status = 'Finalizado'
GROUP BY f.nome_funcao
ORDER BY total_finalizadas DESC";

$resultFinalizadas = $conn->query($sqlFinalizadas);
$response['funcoes_finalizadas_mes_atual'] = $resultFinalizadas->fetch_all(MYSQLI_ASSOC);



$conn->close();

// 🧩 Retorna tudo como JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE);
