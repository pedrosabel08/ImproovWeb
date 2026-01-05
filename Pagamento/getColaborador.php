<?php
header('Content-Type: application/json');

include '../conexao.php';

$colaboradorId = intval($_GET['colaborador_id']);
$mesNumero = isset($_GET['mes_id']) ? intval($_GET['mes_id']) : null;
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : null;

$dadosColaborador = [];

// Primeira consulta: informações básicas do colaborador
$sqlColaborador = "
    SELECT 
        u.nome_usuario, 
        iu.cnpj, 
        e.rua, e.bairro, e.numero, e.complemento, e.cep, 
        iu.nome_empresarial, iu.estado_civil, iu.cpf, 
        ec.rua_cnpj, ec.numero_cnpj, ec.bairro_cnpj, ec.localidade_cnpj, ec.uf_cnpj, ec.cep_cnpj 
    FROM 
        usuario u 
    LEFT JOIN 
        informacoes_usuario iu ON iu.usuario_id = u.idusuario 
    LEFT JOIN 
        endereco e ON e.usuario_id = u.idusuario 
    LEFT JOIN 
        endereco_cnpj ec ON ec.usuario_id = u.idusuario 
    WHERE 
        u.idcolaborador = ?";

$stmtColaborador = $conn->prepare($sqlColaborador);
if (!$stmtColaborador) {
    die(json_encode(["error" => "Falha ao preparar a consulta de colaborador: " . $conn->error]));
}

$stmtColaborador->bind_param('i', $colaboradorId);
$stmtColaborador->execute();
$resultColaborador = $stmtColaborador->get_result();

if ($resultColaborador->num_rows > 0) {
    $dadosColaborador = $resultColaborador->fetch_assoc();
}

$stmtColaborador->close();

// Consultas adicionais de acordo com o colaboradorId
if ($colaboradorId == 1) {
    // Consulta para colaborador 1 usando funcao_imagem e acompanhamento
    $sql = "SELECT 
        fi.colaborador_id,
        'funcao_imagem' AS origem,
        fi.idfuncao_imagem AS identificador,
        fi.imagem_id,
        ico.imagem_nome,
        fi.funcao_id,
        CASE 
            WHEN fi.funcao_id = 4 THEN 
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM funcao_imagem fi_sub
                            JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                            WHERE fi_sub.imagem_id = fi.imagem_id 
                            AND f_sub.nome_funcao = 'Pré-Finalização'
                        ) OR ico.status_id = 1
                        THEN 'Finalização Parcial'
                        ELSE 'Finalização Completa'
                    END 
            ELSE f.nome_funcao 
        END AS nome_funcao,
        fi.status,
        fi.prazo,
        fi.pagamento,
        fi.valor,
    fi.data_pagamento,
    (SELECT COUNT(1)
        FROM pagamento_itens pi
        JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
        WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Finalização Parcial'
    ) AS pago_parcial_count,
    (SELECT COUNT(1)
        FROM pagamento_itens pi
        JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
        WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Pago Completa'
    ) AS pago_completa_count
    FROM 
        funcao_imagem fi
    JOIN 
        imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    JOIN 
        obra o ON ico.obra_id = o.idobra
    JOIN 
        funcao f ON fi.funcao_id = f.idfuncao
    WHERE 
        fi.colaborador_id = ?
        AND (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')";

    if ($mesNumero && $ano) {
        $sql .= "AND YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?";
    }

    $sql .= " UNION ALL
    SELECT 
        ac.colaborador_id,
        'acompanhamento' AS origem,
        ac.idacompanhamento AS identificador,
        ico.idimagens_cliente_obra AS imagem_id,
        ico.imagem_nome AS imagem_nome,
        NULL AS funcao_id,
        'Acompanhamento' AS nome_funcao,
        NULL AS status,
        NULL AS prazo,
        ac.pagamento,
        ac.valor,
        ac.data_pagamento,
        NULL AS pago_parcial_count,
        NULL AS pago_completa_count
    FROM 
        acompanhamento ac
    JOIN 
        obra o ON o.idobra = ac.obra_id
    JOIN 
        imagens_cliente_obra ico ON ico.idimagens_cliente_obra = ac.imagem_id
    WHERE 
        ac.colaborador_id = ?";

    if ($mesNumero && $ano) {
        $sql .= " AND YEAR(ac.data) = ? AND MONTH(ac.data) = ?";
    }
} elseif (in_array($colaboradorId, [13, 20, 23, 37])) {
    $sql = "SELECT 
    fi.colaborador_id,
    'funcao_imagem' AS origem,
    fi.idfuncao_imagem AS identificador,
    fi.imagem_id,
    ico.imagem_nome,
    fi.funcao_id,
    CASE 
        WHEN fi.funcao_id = 4 THEN 
                CASE 
                    WHEN EXISTS (
                        SELECT 1 
                        FROM funcao_imagem fi_sub
                        JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                        WHERE fi_sub.imagem_id = fi.imagem_id 
                        AND f_sub.nome_funcao = 'Pré-Finalização'
                    ) OR ico.status_id = 1
                    THEN 'Finalização Parcial'
                    ELSE 'Finalização Completa'
                END 
        ELSE f.nome_funcao 
    END AS nome_funcao,
    fi.status,
    fi.prazo,
    fi.pagamento,
    fi.valor,
    fi.data_pagamento,
    (SELECT COUNT(1)
        FROM pagamento_itens pi
        JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
        WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Finalização Parcial'
    ) AS pago_parcial_count,
    (SELECT COUNT(1)
        FROM pagamento_itens pi
        JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
        WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Pago Completa'
    ) AS pago_completa_count,
    o.idobra AS obra_id   
FROM 
    funcao_imagem fi
JOIN 
    imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
JOIN 
    obra o ON ico.obra_id = o.idobra
JOIN 
    funcao f ON fi.funcao_id = f.idfuncao
WHERE 
    fi.colaborador_id = ?
    AND (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')";

    if ($mesNumero && $ano) {
        $sql .= " AND YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?";
    }

    $sql .= " 
UNION ALL
SELECT 
    an.colaborador_id,
    'animacao' AS origem,
    an.idanimacao AS identificador,
    an.imagem_id,
    ico.imagem_nome,
    NULL AS funcao_id,
    'Animação' AS nome_funcao,
    an.status_anima as status,
    an.data_anima as prazo,
    an.pagamento,
    an.valor,
    an.data_pagamento,
    NULL AS pago_parcial_count,
    NULL AS pago_completa_count,
    an.obra_id  
FROM 
    animacao an
JOIN 
    imagem_animacao ico ON an.imagem_id = ico.idimagem_animacao
WHERE 
    an.colaborador_id = ?";

    if ($mesNumero && $ano) {
        $sql .= " AND YEAR(an.data_anima) = ? AND MONTH(an.data_anima) = ?";
    }

    // 👇 agora o ORDER BY usa o alias de coluna, não de tabela
    $sql .= " ORDER BY obra_id, imagem_nome";
} else {
    // Consulta padrão para outros colaboradores
    $sql = "SELECT 
        fi.colaborador_id,
        'funcao_imagem' AS origem,
        fi.idfuncao_imagem AS identificador,
        fi.imagem_id,
        ico.imagem_nome,
        fi.funcao_id,
        CASE 
            WHEN fi.funcao_id = 4 THEN 
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM funcao_imagem fi_sub
                            JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                            WHERE fi_sub.imagem_id = fi.imagem_id 
                            AND f_sub.nome_funcao = 'Pré-Finalização'
                        ) OR ico.status_id = 1
                        THEN 'Finalização Parcial'
                        ELSE 'Finalização Completa'
                    END 
            ELSE f.nome_funcao 
        END AS nome_funcao,
        fi.status,
        fi.prazo,
        fi.pagamento,
        fi.valor,
        fi.data_pagamento,
        (SELECT COUNT(1)
            FROM pagamento_itens pi
            JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
            WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Finalização Parcial'
        ) AS pago_parcial_count,
        (SELECT COUNT(1)
            FROM pagamento_itens pi
            JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
            WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Pago Completa'
        ) AS pago_completa_count
    FROM 
        funcao_imagem fi
    JOIN 
        imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    JOIN 
        obra o ON ico.obra_id = o.idobra
    JOIN 
        funcao f ON fi.funcao_id = f.idfuncao
    WHERE 
        fi.colaborador_id = ?
        AND (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')";

    if ($mesNumero && $ano) {
        $sql .= " AND YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ? ORDER BY ico.obra_id, ico.idimagens_cliente_obra, fi.funcao_id";
    }
}

// Preparar a consulta adicional
$stmt = $conn->prepare($sql);


if (!$stmt) {
    die("Erro ao preparar a consulta: " . $conn->error);
}

// Log para depuração
error_log("SQL Gerado: " . $sql);
error_log("Parâmetros: " . json_encode([$colaboradorId, $ano, $mesNumero]));

// Bind de parâmetros conforme necessário
// include collaborator 37 in the same bind pattern so UNION queries receive the correct params
if (in_array($colaboradorId, [1, 13, 20, 23, 37])) {
    if ($mesNumero && $ano) {
        $stmt->bind_param('iiiiii',   $colaboradorId, $ano, $mesNumero, $colaboradorId, $ano, $mesNumero);
    } else {
        $stmt->bind_param('ii', $colaboradorId, $colaboradorId);
    }
} else {
    if ($mesNumero && $ano) {
        $stmt->bind_param('iii', $colaboradorId, $ano, $mesNumero);
    } else {
        $stmt->bind_param('i', $colaboradorId);
    }
}

$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Erro ao executar a consulta: " . $stmt->error);
}

$funcoes = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $funcoes[] = $row;
    }
}

// Combinar dados do colaborador com as outras funções

// Build simple counts by origem to help debug missing animacao rows
$countsByOrigem = [];
foreach ($funcoes as $f) {
    $orig = $f['origem'] ?? 'unknown';
    if (!isset($countsByOrigem[$orig])) $countsByOrigem[$orig] = 0;
    $countsByOrigem[$orig]++;
}

$response = [
    "dadosColaborador" => $dadosColaborador,
    "funcoes" => $funcoes,
    "debug_counts_by_origem" => $countsByOrigem
];

echo json_encode($response);

$stmt->close();
$conn->close();
