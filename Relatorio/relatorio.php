<?php
header('Content-Type: application/json');
require '../conexao.php'; // arquivo com conexão $conn

// --- Ordem de execução padrão
$ordemFuncoes = [1, 8, 2, 3, 9, 4, 5, 6, 7];

// --- 1. Imagens com HOLD (substatus_id = 7)
$sqlHold = "
SELECT 
    i.idimagens_cliente_obra,
    i.obra_id,
    o.nome_obra
FROM imagens_cliente_obra i
JOIN obra o ON o.idobra = i.obra_id
WHERE i.substatus_id = 7
";
$dadosHold = $conn->query($sqlHold)->fetch_all(MYSQLI_ASSOC);

// --- 2. Primeira função pendente por imagem com substatus_id = 2
$sqlTodo = "SELECT t.*
FROM (
    SELECT 
        fi.idfuncao_imagem,
        fi.colaborador_id,
        fi.funcao_id,
        fi.imagem_id,
        i.obra_id,
        i.imagem_nome,
        o.nome_obra,
        ROW_NUMBER() OVER (
            PARTITION BY i.idimagens_cliente_obra
            ORDER BY FIELD(fi.funcao_id, " . implode(',', $ordemFuncoes) . ")
        ) AS rn
    FROM funcao_imagem fi
    JOIN imagens_cliente_obra i 
        ON i.idimagens_cliente_obra = fi.imagem_id
    JOIN obra o 
        ON o.idobra = i.obra_id
    WHERE i.substatus_id = 2
      AND fi.status NOT IN ('Aprovado', 'Aprovado com ajustes', 'Finalizado')       
      AND (fi.colaborador_id IS NULL OR fi.colaborador_id <> 15)
AND o.idobra = 55
) AS t
WHERE t.rn = 1
";
$dadosTodo = $conn->query($sqlTodo)->fetch_all(MYSQLI_ASSOC);

// --- 3. Últimos 3 acompanhamentos por obra
$sqlAcomp = "
WITH ultimos AS (
    SELECT 
        a.idacompanhamento_email,
        a.obra_id,
        o.nome_obra,
        a.colaborador_id,
        a.assunto,
        a.data,
        ROW_NUMBER() OVER (PARTITION BY a.obra_id ORDER BY a.data DESC) AS rn
    FROM acompanhamento_email a
    JOIN obra o ON o.idobra = a.obra_id
)
SELECT *
FROM ultimos
WHERE rn <= 3
ORDER BY obra_id, data DESC
";
$dadosAcomp = $conn->query($sqlAcomp)->fetch_all(MYSQLI_ASSOC);

// --- Retorno unificado
echo json_encode([
    'hold' => $dadosHold,
    'todo' => $dadosTodo,
    'acompanhamentos' => $dadosAcomp
]);
