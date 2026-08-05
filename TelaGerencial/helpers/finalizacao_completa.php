<?php

/**
 * Fonte única das tarefas novas de Finalização Completa.
 *
 * O recorte é histórico: o status atual da obra e do colaborador não é usado.
 * Uma tarefa concluída em um mês anterior continua pertencendo àquele mês,
 * mesmo que a obra ou o colaborador estejam inativos hoje.
 *
 * @return array<int, array{idfuncao_imagem:int,colaborador_id:int,nome_colaborador:string,imagem_id:int,imagem_nome:string}>
 */
function tela_gerencial_finalizacao_completa_nao_pagas(mysqli $conn, int $mes, int $ano): array
{
    if ($mes < 1 || $mes > 12 || $ano < 2000) {
        return [];
    }

    $ultimoDia = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
    $fimMes = sprintf('%04d-%02d-%02d', $ano, $mes, $ultimoDia);
    $fimMesDataHora = $fimMes . ' 23:59:59';

    $sql = "SELECT DISTINCT
        fi.idfuncao_imagem,
        fi.colaborador_id,
        c.nome_colaborador,
        fi.imagem_id,
        COALESCE(i.imagem_nome, '') AS imagem_nome
      FROM funcao_imagem fi
      JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
      LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
      WHERE fi.funcao_id = 4
        AND LOWER(TRIM(COALESCE(i.tipo_imagem, ''))) <> 'planta humanizada'
        AND (
          EXISTS (
            SELECT 1
            FROM log_alteracoes la
            WHERE la.funcao_imagem_id = fi.idfuncao_imagem
              AND MONTH(la.data) = ?
              AND YEAR(la.data) = ?
              AND LOWER(TRIM(la.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
          )
          OR (
            MONTH(fi.prazo) = ?
            AND YEAR(fi.prazo) = ?
            AND LOWER(TRIM(fi.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
          )
        )
        AND (
          LOWER(TRIM(fi.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
          OR EXISTS (
            SELECT 1
            FROM log_alteracoes la_fin
            WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
              AND la_fin.data <= ?
              AND LOWER(TRIM(la_fin.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
          )
        )
        AND fi.colaborador_id NOT IN (21, 15, 7, 34)
        AND NOT (
          EXISTS (
            SELECT 1
            FROM historico_imagens hi_p
            WHERE hi_p.imagem_id = fi.imagem_id
              AND hi_p.status_id = 1
              AND hi_p.data_movimento = (
                SELECT MAX(hm.data_movimento)
                FROM historico_imagens hm
                WHERE hm.imagem_id = fi.imagem_id
                  AND hm.data_movimento <= ?
              )
          )
          OR (
            NOT EXISTS (
              SELECT 1
              FROM historico_imagens h_any
              WHERE h_any.imagem_id = fi.imagem_id
                AND h_any.data_movimento <= ?
            )
            AND (
              i.status_id = 1
              OR EXISTS (
                SELECT 1
                FROM funcao_imagem fi_sub
                JOIN funcao f_sub ON f_sub.idfuncao = fi_sub.funcao_id
                WHERE fi_sub.imagem_id = fi.imagem_id
                  AND LOWER(f_sub.nome_funcao) LIKE '%pre%'
              )
            )
          )
        )
        AND (
          (
            EXISTS (
              SELECT 1
              FROM pagamento_itens pi_any
              JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi_any.origem_id
              WHERE pi_any.origem = 'funcao_imagem'
                AND fi_pi.colaborador_id = fi.colaborador_id
                AND fi_pi.imagem_id = fi.imagem_id
            )
            AND NOT EXISTS (
              SELECT 1
              FROM pagamento_itens pi_full
              JOIN funcao_imagem fi_pi_full ON fi_pi_full.idfuncao_imagem = pi_full.origem_id
              WHERE pi_full.origem = 'funcao_imagem'
                AND fi_pi_full.colaborador_id = fi.colaborador_id
                AND fi_pi_full.imagem_id = fi.imagem_id
                AND fi_pi_full.funcao_id = 4
                AND DATE(pi_full.criado_em) <= ?
                AND (pi_full.observacao IS NULL OR TRIM(pi_full.observacao) = '' OR TRIM(pi_full.observacao) = 'Pago Completa')
            )
          )
          OR (
            NOT EXISTS (
              SELECT 1
              FROM pagamento_itens pi_any
              JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi_any.origem_id
              WHERE pi_any.origem = 'funcao_imagem'
                AND fi_pi.colaborador_id = fi.colaborador_id
                AND fi_pi.imagem_id = fi.imagem_id
            )
            AND (
              fi.data_pagamento IS NULL
              OR CAST(fi.data_pagamento AS CHAR) = '0000-00-00'
              OR fi.data_pagamento > ?
            )
          )
        )
      ORDER BY c.nome_colaborador, fi.idfuncao_imagem";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param(
        'iiiisssss',
        $mes,
        $ano,
        $mes,
        $ano,
        $fimMesDataHora,
        $fimMesDataHora,
        $fimMesDataHora,
        $fimMes,
        $fimMes
    );
    $stmt->execute();
    $tarefas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static fn(array $tarefa): array => [
        'idfuncao_imagem' => (int) $tarefa['idfuncao_imagem'],
        'colaborador_id' => (int) $tarefa['colaborador_id'],
        'nome_colaborador' => (string) $tarefa['nome_colaborador'],
        'imagem_id' => (int) $tarefa['imagem_id'],
        'imagem_nome' => (string) $tarefa['imagem_nome'],
    ], $tarefas);
}

/** @return array<int, array<string, int|string|array<int, array<string, int|string>>>> */
function tela_gerencial_agrupar_finalizacao_completa_por_colaborador(array $tarefas): array
{
    $porColaborador = [];
    foreach ($tarefas as $tarefa) {
        $id = (int) $tarefa['colaborador_id'];
        if (!isset($porColaborador[$id])) {
            $porColaborador[$id] = [
                'colaborador_id' => $id,
                'nome_colaborador' => (string) $tarefa['nome_colaborador'],
                'funcao_id' => 4,
                'nome_funcao' => 'Finalização Completa',
                'quantidade' => 0,
                'pagas' => 0,
                'nao_pagas' => 0,
                'imagens' => [],
            ];
        }

        $porColaborador[$id]['quantidade']++;
        $porColaborador[$id]['nao_pagas']++;
        $porColaborador[$id]['imagens'][] = [
            'imagem_id' => (int) $tarefa['imagem_id'],
            'imagem_nome' => (string) $tarefa['imagem_nome'],
            'pago' => 0,
        ];
    }

    return array_values($porColaborador);
}
