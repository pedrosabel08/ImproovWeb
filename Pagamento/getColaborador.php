<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/custo_tarefa.php';

$colaboradorId = intval($_GET['colaborador_id']);
$mesNumero = isset($_GET['mes_id']) ? intval($_GET['mes_id']) : null;
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : null;

if ($mesNumero && $ano) {
    $fimMesDia = cal_days_in_month(CAL_GREGORIAN, $mesNumero, $ano);
    $fimMesDataTime = sprintf('%04d-%02d-%02d 23:59:59', $ano, $mesNumero, $fimMesDia);
    $snapJoin = "LEFT JOIN (
        SELECT h1.imagem_id, MAX(h1.status_id) AS status_id
        FROM historico_imagens h1
        INNER JOIN (
            SELECT imagem_id, MAX(data_movimento) AS max_data
            FROM historico_imagens
            WHERE data_movimento <= ?
            GROUP BY imagem_id
        ) hm ON hm.imagem_id = h1.imagem_id AND hm.max_data = h1.data_movimento
        GROUP BY h1.imagem_id
    ) hi_snap ON hi_snap.imagem_id = ico.idimagens_cliente_obra";
    $snapStatusCond = "hi_snap.status_id = 1";
} else {
    $fimMesDataTime = null;
    $snapJoin = "";
    $snapStatusCond = "ico.status_id = 1";
}

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
                        ) OR {$snapStatusCond}
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
    CASE WHEN fi.funcao_id = 4 THEN (
        SELECT COUNT(1)
        FROM pagamento_itens pi
        JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
        WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Finalização Parcial'
    ) ELSE 0 END AS pago_parcial_count,
    CASE WHEN fi.funcao_id = 4 THEN (
        SELECT COUNT(1)
        FROM pagamento_itens pi
        JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
        WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Pago Completa'
    ) ELSE 0 END AS pago_completa_count
    FROM 
        funcao_imagem fi
    JOIN 
        imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    JOIN 
        obra o ON ico.obra_id = o.idobra
    JOIN 
        funcao f ON fi.funcao_id = f.idfuncao
    {$snapJoin}
    WHERE 
        fi.colaborador_id = ?";

    if ($mesNumero && $ano) {
        $sql .= " AND (
            (
                (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
                AND (YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?)
            )
            OR EXISTS (
                SELECT 1 FROM log_alteracoes la
                WHERE la.funcao_imagem_id = fi.idfuncao_imagem
                  AND MONTH(la.data) = ? AND YEAR(la.data) = ?
                  AND LOWER(TRIM(la.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
            )
        )";
    } else {
        $sql .= " AND (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')";
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
} elseif ($colaboradorId == 8) {
    $sql = "SELECT 
    fi.colaborador_id,
    'funcao_imagem' AS origem,
    fi.idfuncao_imagem AS identificador,
    fi.imagem_id,
    ico.imagem_nome,
    ico.tipo_imagem,
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
                    ) OR {$snapStatusCond}
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
    CASE WHEN fi.funcao_id = 4 THEN (
        SELECT COUNT(1)
        FROM pagamento_itens pi
        JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
        WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Finalização Parcial'
    ) ELSE 0 END AS pago_parcial_count,
    CASE WHEN fi.funcao_id = 4 THEN (
        SELECT COUNT(1)
        FROM pagamento_itens pi
        JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
        WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Pago Completa'
    ) ELSE 0 END AS pago_completa_count
FROM 
    funcao_imagem fi
JOIN 
    imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
JOIN 
    obra o ON ico.obra_id = o.idobra
JOIN 
    funcao f ON fi.funcao_id = f.idfuncao
{$snapJoin}
WHERE 
    fi.colaborador_id = ?";

    if ($mesNumero && $ano) {
        $sql .= " AND (
            (
                (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
                AND (YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?)
            )
            OR EXISTS (
                SELECT 1 FROM log_alteracoes la
                WHERE la.funcao_imagem_id = fi.idfuncao_imagem
                  AND MONTH(la.data) = ? AND YEAR(la.data) = ?
                  AND LOWER(TRIM(la.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
            )
        )";
    } else {
        $sql .= " AND (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')";
    }

    $sql .= " 
UNION ALL
SELECT 
    fi.colaborador_id,
    'funcao_imagem' AS origem,
    fi.idfuncao_imagem AS identificador,
    fi.imagem_id,
    ico.imagem_nome,
    ico.tipo_imagem,
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
                    ) OR {$snapStatusCond}
                    THEN CONCAT('Finalização Parcial - ', c.nome_colaborador)
                    ELSE CONCAT('Finalização Completa - ', c.nome_colaborador)
                END 
        ELSE f.nome_funcao 
    END AS nome_funcao,
    fi.status,
    fi.prazo,
    CASE WHEN EXISTS (
        SELECT 1 FROM pagamento_itens pi
        WHERE pi.origem = 'funcao_imagem' AND pi.origem_id = fi.idfuncao_imagem
          AND pi.observacao = 'Comissão Gestor'
    ) THEN 1 ELSE 0 END AS pagamento,
    fi.valor,
    NULL AS data_pagamento,
    0 AS pago_parcial_count,
    CASE WHEN EXISTS (
        SELECT 1 FROM pagamento_itens pi
        WHERE pi.origem = 'funcao_imagem' AND pi.origem_id = fi.idfuncao_imagem
          AND pi.observacao = 'Comissão Gestor'
    ) THEN 1 ELSE 0 END AS pago_completa_count
FROM 
    funcao_imagem fi
JOIN 
    imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
JOIN 
    obra o ON ico.obra_id = o.idobra
JOIN 
    funcao f ON fi.funcao_id = f.idfuncao
JOIN 
    colaborador c ON c.idcolaborador = fi.colaborador_id
{$snapJoin}
WHERE 
    fi.colaborador_id IN (23, 40)
    AND fi.funcao_id = 4
    AND NOT (
        EXISTS (
            SELECT 1 FROM funcao_imagem fi_sub
            JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
            WHERE fi_sub.imagem_id = fi.imagem_id
              AND f_sub.nome_funcao = 'Pré-Finalização'
        )
        OR {$snapStatusCond}
    )";

    if ($mesNumero && $ano) {
        $sql .= " AND (
            (
                (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
                AND (YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?)
            )
            OR EXISTS (
                SELECT 1 FROM log_alteracoes la
                WHERE la.funcao_imagem_id = fi.idfuncao_imagem
                  AND MONTH(la.data) = ? AND YEAR(la.data) = ?
                  AND LOWER(TRIM(la.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
            )
        )";
    } else {
        $sql .= " AND (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')";
    }
} elseif (in_array($colaboradorId, [13, 20, 23, 37, 39, 40, 44])) {
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
                    ) OR {$snapStatusCond}
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
    CASE WHEN fi.funcao_id = 4 THEN (
        SELECT COUNT(1)
        FROM pagamento_itens pi
        JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
        WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Finalização Parcial'
    ) ELSE 0 END AS pago_parcial_count,
    CASE WHEN fi.funcao_id = 4 THEN (
        SELECT COUNT(1)
        FROM pagamento_itens pi
        JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
        WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Pago Completa'
    ) ELSE 0 END AS pago_completa_count,
    o.idobra AS obra_id,
NULL AS animacao_id_ordem,
1 AS tem_par_animacao_pos
FROM 
    funcao_imagem fi
JOIN 
    imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
JOIN 
    obra o ON ico.obra_id = o.idobra
JOIN 
    funcao f ON fi.funcao_id = f.idfuncao
{$snapJoin}
WHERE 
    fi.colaborador_id = ?";

    if ($mesNumero && $ano) {
        $sql .= " AND (
            (
                (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
                AND (YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?)
            )
            OR EXISTS (
                SELECT 1 FROM log_alteracoes la
                WHERE la.funcao_imagem_id = fi.idfuncao_imagem
                  AND MONTH(la.data) = ? AND YEAR(la.data) = ?
                  AND LOWER(TRIM(la.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
            )
        )";
    } else {
        $sql .= " AND (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')";
    }

    $sql .= " 
UNION ALL
SELECT 
    fa.colaborador_id,
    'funcao_animacao' AS origem,
    fa.id AS identificador,
    an.imagem_id,
    CONCAT(
  ico.imagem_nome, 
  ' - ', 
  CONCAT(
    UPPER(LEFT(an.tipo_animacao, 1)), 
    LOWER(SUBSTRING(an.tipo_animacao, 2))
  )
) AS imagem_nome,
    fa.funcao_id,
    f.nome_funcao AS nome_funcao,
    fa.status,
    fa.prazo as prazo,
    fa.pagamento,
    fa.valor,
    fa.data_pagamento,
    NULL AS pago_parcial_count,
    NULL AS pago_completa_count,
    an.obra_id,
    fa.animacao_id AS animacao_id_ordem,
    CASE
    WHEN EXISTS (
        SELECT 1
        FROM funcao_animacao fa_pair
        WHERE fa_pair.animacao_id = fa.animacao_id
          AND fa_pair.colaborador_id = fa.colaborador_id
          AND fa_pair.funcao_id IN (5, 10)
        GROUP BY fa_pair.animacao_id, fa_pair.colaborador_id
        HAVING COUNT(DISTINCT fa_pair.funcao_id) = 2
    )
    THEN 0
    ELSE 1
END AS tem_par_animacao_pos
FROM 
    funcao_animacao fa
JOIN
    animacao an ON fa.animacao_id = an.idanimacao
JOIN
    funcao f ON fa.funcao_id = f.idfuncao
LEFT JOIN 
    imagens_cliente_obra ico ON an.imagem_id = ico.idimagens_cliente_obra
WHERE 
    fa.colaborador_id = ? AND fa.status IN ('Finalizado', 'Em aprovação', 'Ajuste', 'Aprovado com ajustes', 'Aprovado')";

    if ($mesNumero && $ano) {
        $sql .= " AND YEAR(fa.prazo) = ? AND MONTH(fa.prazo) = ?";
    }

    // ORDER BY usa alias de coluna, não de tabela
$sql .= "
ORDER BY
    obra_id,
    tem_par_animacao_pos ASC,
    animacao_id_ordem,
    CASE
        WHEN funcao_id = 10 THEN 1
        WHEN funcao_id = 5 THEN 2
        ELSE 3
    END,
    funcao_id DESC,
    imagem_nome";
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
                        ) OR {$snapStatusCond}
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
        CASE WHEN fi.funcao_id = 4 THEN (
            SELECT COUNT(1)
            FROM pagamento_itens pi
            JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
            WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Finalização Parcial'
        ) ELSE 0 END AS pago_parcial_count,
        CASE WHEN fi.funcao_id = 4 THEN (
            SELECT COUNT(1)
            FROM pagamento_itens pi
            JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
            WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Pago Completa'
        ) ELSE 0 END AS pago_completa_count
    FROM 
        funcao_imagem fi
    JOIN 
        imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    JOIN 
        obra o ON ico.obra_id = o.idobra
    JOIN 
        funcao f ON fi.funcao_id = f.idfuncao
    {$snapJoin}
    WHERE 
        fi.colaborador_id = ?";

    if ($mesNumero && $ano) {
        $sql .= " AND (
            (
                (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
                AND (YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?)
            )
            OR EXISTS (
                SELECT 1 FROM log_alteracoes la
                WHERE la.funcao_imagem_id = fi.idfuncao_imagem
                  AND MONTH(la.data) = ? AND YEAR(la.data) = ?
                  AND LOWER(TRIM(la.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
            )
        ) ORDER BY ico.obra_id, ico.idimagens_cliente_obra, fi.funcao_id";
    } else {
        $sql .= " AND (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado') ORDER BY ico.obra_id, ico.idimagens_cliente_obra, fi.funcao_id";
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
if ($colaboradorId == 1) {
    if ($mesNumero && $ano) {
        // funcao_imagem: s(snap), i(colab), i(ano), i(mes), i(mes_log), i(ano_log) + acompanhamento: i(colab), i(ano), i(mes)
        $stmt->bind_param('siiiiiiii', $fimMesDataTime, $colaboradorId, $ano, $mesNumero, $mesNumero, $ano, $colaboradorId, $ano, $mesNumero);
    } else {
        $stmt->bind_param('ii', $colaboradorId, $colaboradorId);
    }
} elseif (in_array($colaboradorId, [13, 20, 23, 37, 39, 40, 44])) {
    if ($mesNumero && $ano) {
        // funcao_imagem: s(snap), i(colab), i(ano), i(mes), i(mes_log), i(ano_log) + animacao: i(colab), i(ano), i(mes)
        $stmt->bind_param('siiiiiiii', $fimMesDataTime, $colaboradorId, $ano, $mesNumero, $mesNumero, $ano, $colaboradorId, $ano, $mesNumero);
    } else {
        $stmt->bind_param('ii', $colaboradorId, $colaboradorId);
    }
} elseif ($colaboradorId == 8) {
    if ($mesNumero && $ano) {
        // 1st UNION: s(snap), i(colab), i(ano), i(mes), i(mes_log), i(ano_log)
        // 2nd UNION (23,40): s(snap), i(ano), i(mes), i(mes_log), i(ano_log)
        $stmt->bind_param('siiiiisiiii', $fimMesDataTime, $colaboradorId, $ano, $mesNumero, $mesNumero, $ano, $fimMesDataTime, $ano, $mesNumero, $mesNumero, $ano);
    } else {
        $stmt->bind_param('i', $colaboradorId);
    }
} else {
    if ($mesNumero && $ano) {
        // funcao_imagem: s(snap), i(colab), i(ano), i(mes), i(mes_log), i(ano_log)
        $stmt->bind_param('siiiii', $fimMesDataTime, $colaboradorId, $ano, $mesNumero, $mesNumero, $ano);
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

// ─── Pós-processamento: valor_exibido, valor_esperado, tem_divergencia ────────
// Coleta IDs de colaboradores presentes nos resultados (para colabs 8 que vê outros)
$colabIdsNaLista = array_values(array_unique(array_filter(
    array_map(fn($f) => isset($f['colaborador_id']) ? (int)$f['colaborador_id'] : 0, $funcoes)
)));

custo_tarefa_carregar_contexto($conn, $colabIdsNaLista);

// ─── Carregar flags valor_aprovado ────────────────────────────────────────────
$overrideIds = [];
$fiIds = array_values(array_filter(array_map(
    fn($f) => $f['origem'] === 'funcao_imagem' ? (int)$f['identificador'] : null,
    $funcoes
)));
if (!empty($fiIds)) {
    $ph = implode(',', array_fill(0, count($fiIds), '?'));
    $stmtOv = $conn->prepare("SELECT idfuncao_imagem FROM funcao_imagem WHERE idfuncao_imagem IN ($ph) AND valor_aprovado = 1");
    $stmtOv->bind_param(str_repeat('i', count($fiIds)), ...$fiIds);
    $stmtOv->execute();
    $resOv = $stmtOv->get_result();
    while ($rowOv = $resOv->fetch_assoc()) {
        $overrideIds[(int)$rowOv['idfuncao_imagem']] = true;
    }
    $stmtOv->close();
}
// ─────────────────────────────────────────────────────────────────────────────

foreach ($funcoes as &$f) {
    $colabId  = isset($f['colaborador_id']) ? (int)$f['colaborador_id'] : 0;
    $funcId   = isset($f['funcao_id'])      ? (int)$f['funcao_id']      : 0;
    $nomeFn   = $f['nome_funcao'] ?? '';
    $pagoParc = (int)($f['pago_parcial_count']  ?? 0);
    $pagoComp = (int)($f['pago_completa_count'] ?? 0);

    // Colaboradores 24 e 12 fazem Finalização em imagens de Planta Humanizada — diferenciar label
    if (in_array($colabId, [24, 12]) && $funcId === 4) {
        $f['nome_funcao'] = str_replace('Finalização', 'Finalização PH', $f['nome_funcao'] ?? '');
        $nomeFn = $f['nome_funcao'];
    }

    $valorBruto = $f['valor'] !== null ? (float)$f['valor'] : null;
    $tarifado   = calcularCustoTarefa($colabId, $funcId, $f['imagem_nome'] ?? null);

    // Comissão do gestor (colaborador 8) sobre finalizações completas de 23/40
    if ($colaboradorId === 8 && in_array($colabId, [23, 40]) && $funcId === 4) {
        $imgNomeLower = mb_strtolower($f['imagem_nome'] ?? '', 'UTF-8');
        $tipoImg      = $f['tipo_imagem'] ?? '';
        // Fachada = R$100, exceto se o nome contiver 'embasamento' → R$80
        // Imagem Externa e qualquer outro tipo = R$80
        $comissao = ($tipoImg === 'Fachada' && !str_contains($imgNomeLower, 'embasamento'))
            ? 100.00
            : 80.00;
        $f['comissao_gestor'] = true;
        $f['valor_tarifado']  = $comissao;
        $f['valor_esperado']  = $comissao;
        $f['valor_exibido']   = $comissao;
        $f['custo']           = $comissao;
        $f['tem_divergencia'] = false;
        continue; // pula o cálculo geral abaixo
    }

    // valor_esperado = sempre o valor CHEIO de funcao_colaborador
    // O banco deve guardar o valor inteiro; o 50% é só exibição.
    $valorEsperado = $tarifado;

    $estaAprovado = isset($overrideIds[(int)$f['identificador']]);

    // valor_exibido: 50% para Finalização Parcial ou pago-parcial aguardando 2ª parcela
    // Se valor_aprovado=1, respeitar o fi.valor salvo no banco (não substituir pelo tarifado)
    if ($estaAprovado) {
        $valorExibido = $valorBruto;
    } else {
        $valorExibido = $valorBruto;
        if ($tarifado !== null && $funcId === 4) {
            $ehParcial     = stripos($nomeFn, 'Parcial') !== false;
            $pagoSoParcial = $pagoParc > 0 && $pagoComp === 0;

            if ($ehParcial || $pagoSoParcial) {
                $valorExibido = round($tarifado * 0.5, 2);
            } else {
                $valorExibido = $tarifado;
            }
        } elseif ($tarifado !== null) {
            $valorExibido = $tarifado;
        }
    }

    $f['valor_tarifado']  = $tarifado;       // valor cheio de funcao_colaborador
    $f['valor_esperado']  = $valorEsperado;  // o que deve estar salvo em fi.valor (sempre cheio)
    $f['valor_exibido']   = $valorExibido;   // o que a tabela mostra (50% quando parcial)
    $f['custo']           = $valorEsperado;
    $f['valor_aprovado']  = $estaAprovado ? 1 : 0;
    $f['tem_divergencia'] = (
        !$estaAprovado
        && $valorEsperado !== null
        && $valorBruto    !== null
        && abs($valorBruto - $valorEsperado) >= 0.01
    );
}
unset($f);
// ──────────────────────────────────────────────────────────────────────────────

// Remove todas as entradas de Finalização Parcial (incluindo PH Parcial)
$funcoes = array_values(array_filter($funcoes, function ($f) {
    return stripos($f['nome_funcao'] ?? '', 'parcial') === false;
}));

$custoTotal = 0.0;
foreach ($funcoes as $f) {
    $custoTotal += (float) ($f['custo'] ?? 0);
}

$response = [
    "dadosColaborador" => $dadosColaborador,
    "funcoes" => $funcoes,
    "debug_counts_by_origem" => $countsByOrigem,
    "custo_total" => round($custoTotal, 2),
];

echo json_encode($response);

$stmt->close();
$conn->close();
