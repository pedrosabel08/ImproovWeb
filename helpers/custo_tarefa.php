<?php

if (!isset($GLOBALS['_custo_tarefa_contexto'])) {
    $GLOBALS['_custo_tarefa_contexto'] = [
        'funcao_map' => [],
        'valor_fixo_map' => [],
    ];
}

function custo_tarefa_normalizar_ids(array $colaboradorIds): array
{
    $ids = array_map('intval', $colaboradorIds);
    $ids = array_filter($ids, static fn($id) => $id > 0);
    $ids = array_values(array_unique($ids));

    return $ids;
}

function custo_tarefa_carregar_contexto(mysqli $conn, array $colaboradorIds): void
{
    $ids = custo_tarefa_normalizar_ids($colaboradorIds);
    $contexto = [
        'funcao_map' => [],
    ];

    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $conn->prepare(
            "SELECT colaborador_id, funcao_id, valor FROM funcao_colaborador WHERE colaborador_id IN ($placeholders)"
        );
        if ($stmt) {
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $colaboradorId = (int) $row['colaborador_id'];
                $funcaoId = (int) $row['funcao_id'];
                if (!isset($contexto['funcao_map'][$colaboradorId])) {
                    $contexto['funcao_map'][$colaboradorId] = [];
                }
                $contexto['funcao_map'][$colaboradorId][$funcaoId] = $row['valor'] !== null ? (float) $row['valor'] : null;
            }
            $stmt->close();
        }
    }

    $GLOBALS['_custo_tarefa_contexto'] = $contexto;
}

function custo_tarefa_obter_contexto(): array
{
    return $GLOBALS['_custo_tarefa_contexto'] ?? [
        'funcao_map' => [],
    ];
}

function custo_tarefa_obter_valor_base(int $colaboradorId, int $funcaoId): float
{
    $contexto = custo_tarefa_obter_contexto();
    $valorFuncao = $contexto['funcao_map'][$colaboradorId][$funcaoId] ?? null;
    if ($valorFuncao !== null && (float) $valorFuncao > 0) {
        return round((float) $valorFuncao, 2);
    }

    return 0.0;
}

function calcularCustoTarefa(int $colaboradorId, int $funcaoId, ?string $imagemNome = null): float
{
    if ($funcaoId === 4 && in_array($colaboradorId, [12, 24], true)) {
        $imgNomeLower = mb_strtolower((string) $imagemNome, 'UTF-8');

        if (str_contains($imgNomeLower, 'lazer') || str_contains($imgNomeLower, 'implanta')) {
            return 200.00;
        }

        if (
            str_contains($imgNomeLower, 'pavimento')
            && (str_contains($imgNomeLower, 'repeti') || str_contains($imgNomeLower, 'varia'))
        ) {
            return 80.00;
        }

        if (str_contains($imgNomeLower, 'pavimento') || str_contains($imgNomeLower, 'garagem')) {
            return 150.00;
        }

        if (str_contains($imgNomeLower, 'varia')) {
            return 80.00;
        }

        return 130.00;
    }

    return custo_tarefa_obter_valor_base($colaboradorId, $funcaoId);
}

function custo_tarefa_chave_finalizacao(int $colaboradorId, int $imagemId): string
{
    return $colaboradorId . ':' . $imagemId;
}

function custo_tarefa_carregar_status_finalizacao(mysqli $conn, array $tarefas, string $dataLimite): array
{
    $pares = [];
    foreach ($tarefas as $tarefa) {
        $colaboradorId = (int) ($tarefa['colaborador_id'] ?? 0);
        $imagemId = (int) ($tarefa['imagem_id'] ?? 0);
        if ($colaboradorId <= 0 || $imagemId <= 0) {
            continue;
        }
        $pares[custo_tarefa_chave_finalizacao($colaboradorId, $imagemId)] = [
            'colaborador_id' => $colaboradorId,
            'imagem_id' => $imagemId,
        ];
    }

    if (!$pares) {
        return [];
    }

    $condicoes = [];
    $tipos = 'ss';
    $params = [$dataLimite, $dataLimite];
    foreach ($pares as $par) {
        $condicoes[] = '(fi_pi.colaborador_id = ? AND fi_pi.imagem_id = ?)';
        $tipos .= 'ii';
        $params[] = $par['colaborador_id'];
        $params[] = $par['imagem_id'];
    }

    $sql = "SELECT
            fi_pi.colaborador_id,
            fi_pi.imagem_id,
            MAX(CASE
                WHEN DATE(pi.criado_em) <= ?
                  AND fi_pi.funcao_id = 4
                  AND (pi.observacao IS NULL OR TRIM(pi.observacao) = '' OR TRIM(pi.observacao) = 'Pago Completa')
                THEN 1 ELSE 0
            END) AS completo_pago,
            MAX(CASE
                WHEN DATE(pi.criado_em) <= ?
                  AND TRIM(pi.observacao) = 'Finalização Parcial'
                THEN 1 ELSE 0
            END) AS parcial_pago
        FROM pagamento_itens pi
        JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
        WHERE pi.origem = 'funcao_imagem'
          AND (" . implode(' OR ', $condicoes) . ")
        GROUP BY fi_pi.colaborador_id, fi_pi.imagem_id";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($tipos, ...$params);
    $stmt->execute();

    $status = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $key = custo_tarefa_chave_finalizacao((int) $row['colaborador_id'], (int) $row['imagem_id']);
        $completoPago = (int) ($row['completo_pago'] ?? 0) === 1;
        $parcialPago = (int) ($row['parcial_pago'] ?? 0) === 1;
        $status[$key] = [
            'completo_pago' => $completoPago,
            'parcial_pendente' => $parcialPago && !$completoPago,
        ];
    }
    $stmt->close();

    return $status;
}
function custo_tarefa_sql_expressao(
    string $colaboradorExpr,
    string $funcaoExpr,
    string $imagemExpr,
    string $valorFuncaoExpr,
    string $valorFixoExpr
): string {
    $imagemLower = "LOWER(COALESCE($imagemExpr, ''))";

    return "CASE
        WHEN $funcaoExpr = 4 AND $colaboradorExpr IN (12, 24) THEN
            CASE
                WHEN $imagemLower LIKE '%lazer%' OR $imagemLower LIKE '%implanta%' THEN 200.00
                WHEN $imagemLower LIKE '%pavimento%' AND ($imagemLower LIKE '%repeti%' OR $imagemLower LIKE '%varia%') THEN 80.00
                WHEN $imagemLower LIKE '%pavimento%' OR $imagemLower LIKE '%garagem%' THEN 150.00
                WHEN $imagemLower LIKE '%varia%' THEN 80.00
                ELSE 130.00
            END
        ELSE COALESCE(NULLIF($valorFuncaoExpr, 0), NULLIF($valorFixoExpr, 0), 0)
    END";
}
