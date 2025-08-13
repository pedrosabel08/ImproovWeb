<?php
header('Content-Type: application/json');
require '../../conexao.php'; // conexão mysqli

// --------------------
// Query 1 - Tempo médio de aprovação
// --------------------
$sql1 = "SELECT 
    ROUND(AVG(DATEDIFF(final.data_final, inicio.data_inicio) + 1), 2) AS duracao_dias
FROM funcao_imagem fi
JOIN (
    SELECT funcao_imagem_id, MIN(data) AS data_inicio
    FROM log_alteracoes
    WHERE status_novo = 'Em andamento'
    GROUP BY funcao_imagem_id
) AS inicio
    ON fi.idfuncao_imagem = inicio.funcao_imagem_id
JOIN (
    SELECT funcao_imagem_id, MIN(data) AS data_final
    FROM log_alteracoes
    WHERE status_novo IN ('Aprovado com Ajustes', 'Finalizado', 'Aprovado')
    GROUP BY funcao_imagem_id
) AS final
    ON fi.idfuncao_imagem = final.funcao_imagem_id
JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
JOIN obra o ON o.idobra = i.obra_id
WHERE o.status_obra = 0 
  AND o.idobra NOT IN (16, 27)
  AND fi.funcao_id = 2
";
$tempoAprovacao = $conn->query($sql1)->fetch_all(MYSQLI_ASSOC)[0]['duracao_dias'] ?? null;

// --------------------
// Query 2 - Taxa de aprovação
// --------------------
$sql2 = "SELECT 
    ROUND(
        (SUM(CASE WHEN status_novo IN ('Aprovado', 'Aprovado com Ajustes') THEN 1 ELSE 0 END) / COUNT(*)) * 100
    ) AS taxa_aprovacao
FROM log_alteracoes la
JOIN funcao_imagem fi ON fi.idfuncao_imagem = la.funcao_imagem_id
JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
JOIN obra o ON o.idobra = i.obra_id
WHERE o.status_obra = 0 
  AND o.idobra NOT IN (16, 27)
";
$taxaAprovacao = $conn->query($sql2)->fetch_all(MYSQLI_ASSOC)[0]['taxa_aprovacao'] ?? null;

// --------------------
// Query 3 - Tempo médio por função
// --------------------
$sql3 = "SELECT 
    c.nome_colaborador,
    AVG(DATEDIFF(final.data_final, inicio.data_inicio) + 1) AS duracao_dias
FROM funcao_imagem fi
JOIN (
    SELECT funcao_imagem_id, MIN(data) AS data_inicio
    FROM log_alteracoes
    WHERE status_novo = 'Em andamento'
    GROUP BY funcao_imagem_id
) AS inicio
    ON fi.idfuncao_imagem = inicio.funcao_imagem_id
JOIN (
    SELECT funcao_imagem_id, MIN(data) AS data_final
    FROM log_alteracoes
    WHERE status_novo IN ('Aprovado com Ajustes', 'Finalizado', 'Aprovado')
    GROUP BY funcao_imagem_id
) AS final
    ON fi.idfuncao_imagem = final.funcao_imagem_id
JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
JOIN obra o ON o.idobra = i.obra_id
JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
WHERE o.status_obra = 0 
  AND o.idobra NOT IN (16, 27)
  AND fi.funcao_id = 4
GROUP BY fi.colaborador_id
ORDER BY fi.colaborador_id;
";
$tempoFuncao = $conn->query($sql3)->fetch_all(MYSQLI_ASSOC);

// --------------------
// Query 4 - Colaborador mais produtivo
// --------------------
$sql4 = "SELECT 
    c.nome_colaborador,
    COUNT(*) AS total_finalizadas
FROM funcao_imagem fi
JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
JOIN obra o ON o.idobra = i.obra_id
JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
WHERE fi.status IN ('Aprovado com Ajustes', 'Finalizado', 'Aprovado')
  AND MONTH(fi.prazo) = MONTH(CURRENT_DATE())
  AND YEAR(fi.prazo) = YEAR(CURRENT_DATE())
  AND o.status_obra = 0 
  AND o.idobra NOT IN (16, 27)
  AND fi.funcao_id = 2
LIMIT 1
";
$produtivo = $conn->query($sql4)->fetch_assoc(); // <<< fetch_assoc para objeto único


// --------------------
// Monta JSON final
// --------------------
echo json_encode([
    "tempoAprovacao" => $tempoAprovacao,
    "taxaAprovacao" => $taxaAprovacao,
    "tempoFuncao" => $tempoFuncao,
    "produtividade" => $produtivo,
    "colaboradores" => [
        "produtivo" => $produtivo['nome_colaborador'] ?? null
    ]
]);
