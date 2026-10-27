<?php

require_once __DIR__ . '/planejamento_producao_helper.php';

/** Apenas eventos persistidos de entrega; progresso de função nunca encerra R00. */
function flow_planejamento_entrega_real_com_dados(array $entrega, array $itens): array
{
    $dataValida = static function ($valor): ?string {
        $valor = trim((string) $valor);
        return entregas_valid_date(substr($valor, 0, 10)) ? $valor : null;
    };
    $datas = array_map(static fn(array $item): ?string => $dataValida($item['data_entregue'] ?? null), $itens);
    $real = null;
    $fonte = null;
    if ((int) ($entrega['status_id'] ?? 0) === 2) {
        if ($datas && !in_array(null, $datas, true)) {
            $real = max($datas);
            $fonte = 'ENTREGAS_ITENS';
        } elseif (in_array(flow_planejamento_normalizar((string) ($entrega['status'] ?? '')), ['entregue no prazo', 'entregue com atraso', 'entrega antecipada'], true)) {
            $real = $dataValida($entrega['data_conclusao'] ?? null);
            $fonte = $real ? 'ENTREGAS_STATUS_FINAL' : null;
        }
    }
    return [
        'estado' => $real ? 'CONCLUIDO' : 'EM_ANDAMENTO',
        'entrega_realizada_em' => $real,
        'data_entrega_real' => $real ? substr($real, 0, 10) : null,
        'fonte' => $fonte,
        'prazo_r00' => $entrega['data_prevista'] ?? null,
    ];
}

function flow_planejamento_carregar_entrega_real(mysqli $conn, int $entregaId, bool $bloquear = false): array
{
    $stmt = $conn->prepare('SELECT id, status_id, status, data_prevista, data_conclusao FROM entregas WHERE id = ?' . ($bloquear ? ' FOR UPDATE' : ''));
    $stmt->bind_param('i', $entregaId);
    $stmt->execute();
    $entrega = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$entrega) throw new InvalidArgumentException('Entrega não encontrada.');
    $stmt = $conn->prepare('SELECT data_entregue FROM entregas_itens WHERE entrega_id = ?');
    $stmt->bind_param('i', $entregaId);
    $stmt->execute();
    $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return flow_planejamento_entrega_real_com_dados($entrega, $itens);
}

function flow_planejamento_exigir_entrega_aberta(array $ciclo): void
{
    if (($ciclo['estado'] ?? '') === 'CONCLUIDO') {
        throw new RuntimeException('Esta R00 já foi entregue. O plano é histórico e não permite replanejamento.');
    }
}

/** Mantém o cálculo de dias úteis compartilhado com o planejamento. */
function flow_planejamento_comparar_entrega_real(array $ciclo, array $plano): array
{
    $real = $ciclo['data_entrega_real'];
    $fim = $plano['fim_previsto'] ?? null;
    $prazo = $plano['data_entrega'] ?? null;
    $desvio = static function (?string $inicio, ?string $fim): ?int {
        if (!$inicio || !$fim) return null;
        return $fim >= $inicio ? flow_planejamento_dias_uteis_entre($inicio, $fim) : -flow_planejamento_dias_uteis_entre($fim, $inicio);
    };
    $ciclo['desvio_plano_dias_uteis'] = $desvio($fim, $real);
    $ciclo['antecedencia_prazo_dias_uteis'] = $desvio($real, $prazo);
    $ciclo['antecedencia_prazo_dias_corridos'] = $real && $prazo ? (int) (new DateTimeImmutable($real))->diff(new DateTimeImmutable($prazo))->format('%r%a') : null;
    return $ciclo;
}

function flow_planejamento_historico_projecoes(mysqli $conn, int $entregaId): array
{
    $table = $conn->query("SHOW TABLES LIKE 'entrega_planejamento_projecao_operacional'");
    $disponivel = $table && $table->num_rows > 0;
    if ($table) $table->free();
    if (!$disponivel) return [];
    $stmt = $conn->prepare('SELECT id, versao_id, data_referencia, fim_operacional_projetado, margem_operacional_dias_uteis, status_operacional, confirmado_em FROM entrega_planejamento_projecao_operacional WHERE entrega_id = ? ORDER BY confirmado_em DESC, id DESC');
    $stmt->bind_param('i', $entregaId);
    $stmt->execute();
    $historico = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $historico;
}
