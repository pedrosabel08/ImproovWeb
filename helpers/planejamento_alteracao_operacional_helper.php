<?php

require_once __DIR__ . '/alteracoes_helper.php';

function flow_planejamento_alteracao_modelo_disponivel(mysqli $conn): bool
{
    // SHOW COLUMNS also inspects connection-local temporary fixtures correctly.
    try {
        $result = $conn->query("SHOW COLUMNS FROM entrega_planejamento_producao LIKE 'modelo'");
        return $result && $result->num_rows > 0;
    } catch (mysqli_sql_exception $error) {
        if ((int) $error->getCode() === 1146) return false;
        throw $error;
    }
}

/**
 * Resolucao da rodada atual pela liberacao da Pre-alteracao, nunca pela tarefa
 * reutilizada ou pelo seu colaborador anterior. Nao cria registros historicos.
 */
function flow_planejamento_alteracao_contexto_operacional(mysqli $conn, int $funcaoImagemId, bool $forUpdate = false): ?array
{
    if (!flow_planejamento_alteracao_modelo_disponivel($conn)) {
        return null;
    }
    $sql = "SELECT e.id AS entrega_id, e.status_id AS etapa_id, ei.id AS entrega_item_id,
                   p.id AS planejamento_id, p.modelo, p.estado, p.versao_atual_id,
                   pi.responsavel_planejado_id, pi.inicio_planejado, pi.fim_planejado,
                   fi.colaborador_id, fi.imagem_id, fi.status AS tarefa_status
              FROM funcao_imagem fi
              JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
              JOIN pre_alt_itens pai ON pai.imagem_id = fi.imagem_id
              JOIN pre_alt_liberacao_itens pli ON pli.pre_alt_item_id = pai.id
              JOIN entregas e ON e.id = pli.entrega_destino_id AND e.status_id = ico.status_id
              JOIN entregas_itens ei ON ei.entrega_id = e.id AND ei.imagem_id = fi.imagem_id
              LEFT JOIN entrega_planejamento_producao p ON p.entrega_id = e.id
              LEFT JOIN entrega_planejamento_item pi ON pi.versao_id = p.versao_atual_id
                    AND pi.entrega_item_id = ei.id AND pi.funcao_imagem_id = fi.idfuncao_imagem
             WHERE fi.idfuncao_imagem = ? AND fi.funcao_id = 6
             ORDER BY pli.id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $funcaoImagemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row && ($row['modelo'] ?? '') === 'IMAGENS_ALTERACAO' ? $row : null;
}

/** Pure policy used by both legacy and canonical writers. */
function flow_planejamento_alteracao_validar_operacao(?array $contexto, ?int $atorId, ?int $novoResponsavelId = null, bool $iniciar = false): void
{
    if (!$contexto) {
        return;
    }
    if (empty($contexto['versao_atual_id']) || empty($contexto['responsavel_planejado_id']) || empty($contexto['fim_planejado'])) {
        throw new DomainException('Esta entrega aguarda planejamento de producao. Acesse Planejar entrega e confirme todas as imagens antes de executar.');
    }
    $responsavel = (int) $contexto['responsavel_planejado_id'];
    if ($novoResponsavelId !== null && $novoResponsavelId !== $responsavel) {
        throw new DomainException('O responsavel desta rodada foi confirmado no planejamento. Replaneje a entrega para reatribuir a imagem.');
    }
    if ($iniciar && (int) $atorId !== $responsavel) {
        throw new DomainException('Somente o responsavel confirmado pode iniciar esta imagem. Replaneje a entrega para transferir a atribuicao.');
    }
    if ($iniciar && (int) ($contexto['colaborador_id'] ?? 0) !== $responsavel) {
        throw new DomainException('A atribuicao operacional diverge do plano confirmado. Replaneje a entrega antes de iniciar.');
    }
}

/** Attach the round and its confirmed version atomically to any new cycle. */
function flow_planejamento_alteracao_vincular_ciclo(mysqli $conn, int $funcaoImagemId, int $cicloId): void
{
    $contexto = flow_planejamento_alteracao_contexto_operacional($conn, $funcaoImagemId, true);
    if (!$contexto) {
        return;
    }
    flow_planejamento_alteracao_validar_operacao($contexto, null);
    $itemId = (int) $contexto['entrega_item_id'];
    $versaoId = (int) $contexto['versao_atual_id'];
    $prazo = $contexto['fim_planejado'];
    $stmt = $conn->prepare('UPDATE janela_operacional_ciclo SET entrega_item_id = ?, planejamento_versao_id_snapshot = ?, prazo_necessario_snapshot = ? WHERE id = ?');
    $stmt->bind_param('iisi', $itemId, $versaoId, $prazo, $cicloId);
    $stmt->execute();
    $stmt->close();
}
