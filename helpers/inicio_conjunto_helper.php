<?php

require_once __DIR__ . '/motor_requisitos_helper.php';
require_once __DIR__ . '/unidade_trabalho_helper.php';
require_once __DIR__ . '/funcao_imagem_prazo_helper.php';

function flow_inicio_conjunto_tarefa(mysqli $conn, int $funcaoImagemId, bool $forUpdate = false): ?array
{
    $sql = "SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.colaborador_id, fi.status, fi.prazo,
                   f.nome_funcao, ico.imagem_nome, ico.obra_id, o.liberar_modelagem
              FROM funcao_imagem fi
              JOIN funcao f ON f.idfuncao = fi.funcao_id
              JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
              JOIN obra o ON o.idobra = ico.obra_id
             WHERE fi.idfuncao_imagem = ? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $funcaoImagemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function flow_inicio_conjunto_tem_flow_block(mysqli $conn, array $ids): bool
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return false;
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare(
        "SELECT COUNT(*) total FROM flow_issue
          WHERE funcao_imagem_id IN ($marks)
            AND bloqueante = 1
            AND (status IN ('ABERTA','AGUARDANDO_ACAO','PAUSADA') OR (status = 'RESOLVIDA' AND confirmada_em IS NULL))"
    );
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total > 0;
}

function flow_inicio_conjunto_resultado(bool $available, string $reason, array $extra = []): array
{
    return array_merge([
        'joint_start_available' => $available,
        'joint_start_type' => FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO,
        'joint_start_reason' => $reason,
        'joint_start_partner_id' => null,
    ], $extra);
}

/**
 * A Composição pode ignorar somente a pendência produtiva da Modelagem durante
 * esta avaliação. Requisitos de projeto, aprovação, arquivo ou Flow Block
 * continuam bloqueantes.
 */
function flow_inicio_conjunto_avaliar_modelagem_composicao(
    mysqli $conn,
    int $modelagemId,
    bool $forUpdate = false,
    bool $validateWip = true
): array {
    $modelagem = flow_inicio_conjunto_tarefa($conn, $modelagemId, $forUpdate);
    if (!$modelagem || (int) $modelagem['funcao_id'] !== FLOW_FUNCAO_MODELAGEM) {
        return flow_inicio_conjunto_resultado(false, 'MODELAGEM_INVALIDA');
    }

    $stmt = $conn->prepare(
        'SELECT idfuncao_imagem FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $imagemId = (int) $modelagem['imagem_id'];
    $funcaoComposicao = FLOW_FUNCAO_COMPOSICAO;
    $stmt->bind_param('ii', $imagemId, $funcaoComposicao);
    $stmt->execute();
    $composicaoId = (int) ($stmt->get_result()->fetch_assoc()['idfuncao_imagem'] ?? 0);
    $stmt->close();
    $composicao = $composicaoId > 0 ? flow_inicio_conjunto_tarefa($conn, $composicaoId, $forUpdate) : null;
    if (!$composicao) {
        return flow_inicio_conjunto_resultado(false, 'COMPOSICAO_INEXISTENTE');
    }

    $extra = [
        'joint_start_partner_id' => $composicaoId,
        'modelagem_id' => (int) $modelagem['idfuncao_imagem'],
        'composicao_id' => $composicaoId,
        'imagem_id' => $imagemId,
        'colaborador_id' => (int) $modelagem['colaborador_id'],
    ];
    if ((int) $modelagem['colaborador_id'] <= 0 || (int) $modelagem['colaborador_id'] !== (int) $composicao['colaborador_id']) {
        return flow_inicio_conjunto_resultado(false, 'RESPONSAVEIS_DIVERGENTES', $extra);
    }
    if ($modelagem['status'] !== 'Não iniciado' || $composicao['status'] !== 'Não iniciado') {
        return flow_inicio_conjunto_resultado(false, 'STATUS_INCOMPATIVEL', $extra);
    }
    if (flow_inicio_conjunto_tem_flow_block($conn, [(int) $modelagem['idfuncao_imagem'], $composicaoId])) {
        return flow_inicio_conjunto_resultado(false, 'FLOW_BLOCK_ATIVO', $extra);
    }

    // liberar_modelagem jamais é herdado pela Composição: o filtro precisa
    // estar concluído mesmo que a Modelagem esteja excepcionalmente liberada.
    $filtro = motor_requisitos_predecessora($conn, $imagemId, FLOW_FUNCAO_FILTRO_ASSETS);
    if (!$filtro || !in_array(motor_requisitos_estado_predecessora($filtro), ['ATENDIDO', 'DISPENSADO'], true)) {
        return flow_inicio_conjunto_resultado(false, 'FILTRO_NAO_CONCLUIDO', $extra);
    }

    $avaliacaoModelagem = motor_requisitos_avaliar_funcao_imagem($conn, (int) $modelagem['idfuncao_imagem']);
    if (empty($avaliacaoModelagem['elegivel'])) {
        return flow_inicio_conjunto_resultado(false, 'MODELAGEM_NAO_LIBERADA', array_merge($extra, ['avaliacao_modelagem' => $avaliacaoModelagem]));
    }

    $avaliacaoComposicao = motor_requisitos_avaliar_funcao_imagem($conn, $composicaoId);
    $bloqueios = (array) ($avaliacaoComposicao['bloqueios'] ?? []);
    $bloqueioModelagemEncontrado = false;
    foreach ($bloqueios as $block) {
        $isModelagem = (int) ($block['origem_id'] ?? 0) === (int) $modelagem['idfuncao_imagem']
            && (string) ($block['codigo'] ?? '') === 'FUNCAO_ANTERIOR_CONCLUIDA';
        if ($isModelagem) {
            $bloqueioModelagemEncontrado = true;
            continue;
        }
        return flow_inicio_conjunto_resultado(false, 'COMPOSICAO_POSSUI_OUTRAS_PENDENCIAS', array_merge($extra, ['avaliacao_composicao' => $avaliacaoComposicao]));
    }
    if (!$bloqueioModelagemEncontrado || !empty($avaliacaoComposicao['elegivel'])) {
        return flow_inicio_conjunto_resultado(false, 'COMPOSICAO_NAO_BLOQUEADA_SOMENTE_PELA_MODELAGEM', array_merge($extra, ['avaliacao_composicao' => $avaliacaoComposicao]));
    }

    if ($validateWip) {
        if ($forUpdate) {
            $wip = flow_wip_assert_novo_inicio($conn, (int) $modelagem['colaborador_id']);
        } else {
            $wip = flow_wip_resumo($conn, (int) $modelagem['colaborador_id']);
            if (!$wip['can_start_new']) {
                return flow_inicio_conjunto_resultado(false, FLOW_WIP_ERROR_CODE, array_merge($extra, ['wip' => $wip]));
            }
        }
        $extra['wip'] = $wip;
    }

    return flow_inicio_conjunto_resultado(true, 'AVAILABLE', array_merge($extra, [
        'avaliacao_modelagem' => $avaliacaoModelagem,
        'avaliacao_composicao' => $avaliacaoComposicao,
    ]));
}

function flow_inicio_conjunto_registrar(
    mysqli $conn,
    array $evaluation,
    ?string $prazoUnidade,
    ?int $actorColaboradorId,
    ?int $actorUsuarioId
): array {
    if (empty($evaluation['joint_start_available'])) {
        throw new DomainException('O início conjunto não está disponível: ' . ($evaluation['joint_start_reason'] ?? 'regra não atendida') . '.');
    }
    if (!flow_unidade_schema_disponivel($conn)) {
        throw new RuntimeException('A migration de unidade de trabalho ainda não foi aplicada.');
    }
    $modelagemId = (int) $evaluation['modelagem_id'];
    $composicaoId = (int) $evaluation['composicao_id'];
    $imagemId = (int) $evaluation['imagem_id'];
    $colaboradorId = (int) $evaluation['colaborador_id'];
    $tipo = FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO;

    $stmtExisting = $conn->prepare('SELECT id FROM unidade_trabalho WHERE tipo = ? AND imagem_id = ? LIMIT 1 FOR UPDATE');
    $stmtExisting->bind_param('si', $tipo, $imagemId);
    $stmtExisting->execute();
    $unitId = (int) ($stmtExisting->get_result()->fetch_assoc()['id'] ?? 0);
    $stmtExisting->close();
    if ($unitId <= 0) {
        $stmtUnit = $conn->prepare(
            "INSERT INTO unidade_trabalho (tipo, imagem_id, colaborador_id, estado, criado_por_colaborador_id, criado_por_usuario_id)
             VALUES (?, ?, ?, 'ATIVA', ?, ?)"
        );
        $stmtUnit->bind_param('siiii', $tipo, $imagemId, $colaboradorId, $actorColaboradorId, $actorUsuarioId);
        $stmtUnit->execute();
        $unitId = (int) $conn->insert_id;
        $stmtUnit->close();
    } else {
        $stmtReactivate = $conn->prepare("UPDATE unidade_trabalho SET colaborador_id = ?, estado = 'ATIVA' WHERE id = ?");
        $stmtReactivate->bind_param('ii', $colaboradorId, $unitId);
        $stmtReactivate->execute();
        $stmtReactivate->close();
    }

    $stmtItem = $conn->prepare('INSERT INTO unidade_trabalho_item (unidade_trabalho_id, funcao_imagem_id, ordem) VALUES (?, ?, ?)');
    foreach ([[$modelagemId, 1], [$composicaoId, 2]] as [$taskId, $order]) {
        $stmtItem->bind_param('iii', $unitId, $taskId, $order);
        $stmtItem->execute();
    }
    $stmtItem->close();

    $stmtUpdate = $conn->prepare("UPDATE funcao_imagem SET status = 'Em andamento' WHERE idfuncao_imagem = ? AND status = 'Não iniciado'");
    foreach ([$modelagemId, $composicaoId] as $taskId) {
        $stmtUpdate->bind_param('i', $taskId);
        $stmtUpdate->execute();
        if ($stmtUpdate->affected_rows !== 1) {
            throw new RuntimeException('Uma das tarefas mudou durante o início conjunto. Atualize a tela e tente novamente.');
        }
    }
    $stmtUpdate->close();

    if ($prazoUnidade !== null && trim($prazoUnidade) !== '') {
        foreach ([$modelagemId, $composicaoId] as $taskId) {
            funcao_imagem_prazo_atualizar($conn, $taskId, trim($prazoUnidade), [
                'origem' => 'inicio_conjunto',
                'alterado_por_colaborador_id' => $actorColaboradorId,
                'alterado_por_usuario_id' => $actorUsuarioId,
            ]);
        }
    }

    $details = json_encode([
        'imagem_id' => $imagemId,
        'colaborador_id' => $colaboradorId,
        'modelagem_id' => $modelagemId,
        'composicao_id' => $composicaoId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $event = 'INICIO_CONJUNTO';
    $stmtEvent = $conn->prepare('INSERT INTO unidade_trabalho_evento (unidade_trabalho_id, evento, ator_colaborador_id, ator_usuario_id, detalhes) VALUES (?, ?, ?, ?, ?)');
    $stmtEvent->bind_param('isiis', $unitId, $event, $actorColaboradorId, $actorUsuarioId, $details);
    $stmtEvent->execute();
    $stmtEvent->close();

    return ['unit_id' => $unitId, 'type' => $tipo, 'member_ids' => [$modelagemId, $composicaoId], 'prazo_aplicado_a' => [$modelagemId, $composicaoId], 'wip_units' => 1];
}
