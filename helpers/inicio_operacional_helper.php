<?php

require_once __DIR__ . '/janela_operacional_helper.php';
require_once __DIR__ . '/inicio_conjunto_helper.php';
require_once __DIR__ . '/motor_requisitos_helper.php';
require_once __DIR__ . '/funcao_imagem_prazo_helper.php';

function flow_inicio_operacional_validar_permissao(array $tarefa, ?int $atorColaboradorId, int $nivelAcesso): void
{
    $responsavel = (int) ($tarefa['colaborador_id'] ?? 0);
    $gestor = in_array($nivelAcesso, [1, 5], true) || in_array((int) $atorColaboradorId, [9, 21], true);
    if ($responsavel <= 0) {
        throw new DomainException('A tarefa precisa possuir um colaborador responsavel.');
    }
    if ($atorColaboradorId && $responsavel !== $atorColaboradorId && !$gestor) {
        throw new DomainException('Voce nao tem permissao para iniciar esta unidade de trabalho.');
    }
}

function flow_inicio_operacional_validar_requisitos(mysqli $conn, array $tarefa, bool $confirmarPendencias): array
{
    $avaliacao = motor_requisitos_avaliar_funcao_imagem($conn, (int) $tarefa['idfuncao_imagem']);
    if (motor_requisitos_tem_bloqueio_producao($avaliacao)) {
        throw new DomainException('Conclua todas as pendencias de Producao antes de iniciar a tarefa.');
    }
    // Pendências de Projeto e demais requisitos informativos não bloqueiam o
    // início. A regra de negócio para puxar uma tarefa é exclusivamente a
    // ausência de pendências ativas de Produção.
    return $avaliacao;
}

/**
 * Mantem as tabelas de previsao e funcao_imagem.prazo sincronizadas somente
 * por compatibilidade. A fonte da janela e o ciclo operacional.
 */
function flow_inicio_operacional_salvar_previsao_legada(
    mysqli $conn,
    array $membros,
    string $previsao,
    ?array $motivo,
    ?int $atorColaboradorId,
    ?int $atorUsuarioId,
    string $evento = 'PREVISAO_INFORMADA'
): void {
    $justificativa = null;
    if ($motivo) {
        $justificativa = '[' . $motivo['codigo'] . '] ' . ($motivo['texto'] ?: $motivo['label']);
    }
    foreach ($membros as $membro) {
        $tarefaId = (int) $membro['idfuncao_imagem'];
        if (flow_tarefa_planejamento_persistencia_disponivel($conn)) {
            $stmt = $conn->prepare('SELECT previsao_conclusao FROM funcao_imagem_previsao_conclusao WHERE funcao_imagem_id = ? FOR UPDATE');
            $stmt->bind_param('i', $tarefaId);
            $stmt->execute();
            $anterior = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $stmt = $conn->prepare('INSERT INTO funcao_imagem_previsao_conclusao (funcao_imagem_id, previsao_conclusao, justificativa, criado_por_colaborador_id, criado_por_usuario_id) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE previsao_conclusao = VALUES(previsao_conclusao), justificativa = VALUES(justificativa)');
            $stmt->bind_param('issii', $tarefaId, $previsao, $justificativa, $atorColaboradorId, $atorUsuarioId);
            $stmt->execute();
            $stmt->close();

            $ctx = flow_tarefa_contexto_planejamento($conn, $membro);
            $prazo = $ctx['prazo_necessario'] ?? null;
            $diferenca = $prazo ? flow_tarefa_planejamento_desvio($prazo, $previsao) : null;
            $versao = $ctx['versao_id'] ?? null;
            $previsaoAnterior = $anterior['previsao_conclusao'] ?? null;
            $stmt = $conn->prepare('INSERT INTO funcao_imagem_previsao_historico (funcao_imagem_id, evento, prazo_necessario, previsao_anterior, previsao_conclusao, diferenca_dias_uteis, justificativa, versao_planejamento_id, ator_colaborador_id, ator_usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('issssisiii', $tarefaId, $evento, $prazo, $previsaoAnterior, $previsao, $diferenca, $justificativa, $versao, $atorColaboradorId, $atorUsuarioId);
            $stmt->execute();
            $stmt->close();
        }
        funcao_imagem_prazo_atualizar($conn, $tarefaId, $previsao, [
            'origem' => 'janela_operacional_compatibilidade',
            'motivo' => $justificativa,
            'alterado_por_colaborador_id' => $atorColaboradorId,
            'alterado_por_usuario_id' => $atorUsuarioId,
        ]);
    }
}

function flow_inicio_operacional_transferir(mysqli $conn, array $entrada): array
{
    $resultado = flow_janela_transferir_responsavel(
        $conn,
        (int) ($entrada['funcao_imagem_id'] ?? 0),
        (int) ($entrada['novo_responsavel_id'] ?? 0),
        $entrada['nova_previsao'] ?? null,
        (string) ($entrada['motivo_transferencia'] ?? ''),
        $entrada['ator_colaborador_id'] ?? null,
        $entrada['ator_usuario_id'] ?? null,
        $entrada['motivo_operacional_codigo'] ?? null,
        $entrada['motivo_operacional_texto'] ?? null
    );
    if (!empty($resultado['aplicada'])) {
        $unidade = flow_janela_resolver_unidade($conn, (int) $entrada['funcao_imagem_id'], true);
        flow_inicio_operacional_salvar_previsao_legada(
            $conn,
            $unidade['membros'],
            (string) $resultado['avaliacao']['previsao'],
            null,
            $entrada['ator_colaborador_id'] ?? null,
            $entrada['ator_usuario_id'] ?? null,
            'TRANSFERENCIA_RESPONSAVEL'
        );
    }
    return $resultado;
}

/** Deve ser executado dentro de uma transacao aberta pelo endpoint. */
function flow_inicio_operacional_iniciar(mysqli $conn, array $entrada): array
{
    if (!flow_janela_schema_disponivel($conn)) {
        throw new RuntimeException('A migration da Janela Operacional V1 ainda nao foi aplicada.');
    }
    $tarefaId = (int) ($entrada['funcao_imagem_id'] ?? 0);
    $previsao = flow_janela_data_valida($entrada['previsao'] ?? null);
    if ($tarefaId <= 0 || !$previsao) {
        throw new DomainException('Informe a tarefa e uma previsao de conclusao valida.');
    }
    $atorColaboradorId = !empty($entrada['ator_colaborador_id']) ? (int) $entrada['ator_colaborador_id'] : null;
    $atorUsuarioId = !empty($entrada['ator_usuario_id']) ? (int) $entrada['ator_usuario_id'] : null;
    $nivelAcesso = (int) ($entrada['nivel_acesso'] ?? 0);
    $confirmarPendencias = !empty($entrada['confirmar_pendencias']);
    $iniciarConjunto = !empty($entrada['iniciar_modelagem_composicao']);

    $joint = null;
    if ($iniciarConjunto) {
        $joint = flow_inicio_conjunto_avaliar_modelagem_composicao($conn, $tarefaId, true, true);
        if (empty($joint['joint_start_available'])) {
            throw new DomainException('O inicio conjunto nao esta disponivel: ' . ($joint['joint_start_reason'] ?? 'regra nao atendida') . '.');
        }
    }
    $unidade = flow_janela_resolver_unidade($conn, $tarefaId, true, $iniciarConjunto);
    flow_inicio_operacional_validar_permissao($unidade['tarefa_principal'], $atorColaboradorId, $nivelAcesso);

    $statusMembros = array_values(array_unique(array_map(static fn (array $m): string => (string) $m['status'], $unidade['membros'])));
    $primeiroInicio = count($statusMembros) === 1 && $statusMembros[0] === 'Não iniciado';
    $statusReabriveis = ['Aprovado', 'Aprovado com ajustes', 'Finalizado'];
    $ultimoCicloExistente = flow_janela_ultimo_ciclo_por_tarefa($conn, $tarefaId, true);
    $reabertura = (!$primeiroInicio && !array_diff($statusMembros, $statusReabriveis))
        || ($primeiroInicio && !empty($ultimoCicloExistente));
    if (!$primeiroInicio && !$reabertura) {
        throw new DomainException('A unidade mudou desde que o modal foi aberto. Atualize a tela e tente novamente.');
    }
    if ($iniciarConjunto && (!$primeiroInicio || $reabertura)) {
        throw new DomainException('O inicio conjunto explicito e permitido somente no primeiro ciclo.');
    }
    if (!$iniciarConjunto) {
        flow_wip_assert_novo_inicio($conn, (int) $unidade['tarefa_principal']['colaborador_id'], $unidade['tarefa_principal']);
        // Caderno + Filtro e uma unidade legada: o requisito do Caderno e
        // validado uma vez e a dependencia interna nao cria um segundo inicio.
        $validar = $unidade['tipo_unidade'] === 'CADERNO_FILTRO_LEGADO'
            ? [$unidade['membros'][0]]
            : $unidade['membros'];
        foreach ($validar as $membro) {
            flow_inicio_operacional_validar_requisitos($conn, $membro, $confirmarPendencias);
        }
    }

    $avaliacao = flow_janela_avaliar($conn, $unidade, $previsao);
    $motivo = flow_janela_validar_justificativa(
        $conn,
        $avaliacao['estado'],
        $entrada['motivo_codigo'] ?? null,
        $entrada['motivo_texto'] ?? null
    );

    if ($iniciarConjunto) {
        $unit = flow_inicio_conjunto_registrar($conn, $joint, $previsao, $atorColaboradorId, $atorUsuarioId);
        $unidade['unidade_trabalho_id'] = (int) $unit['unit_id'];
        $unidade['chave_referencia'] = 'UT:' . (int) $unit['unit_id'];
    } else {
        $stmtStatus = $conn->prepare("UPDATE funcao_imagem SET status = 'Em andamento' WHERE idfuncao_imagem = ? AND status = ?");
        foreach ($unidade['membros'] as $membro) {
            $memberId = (int) $membro['idfuncao_imagem'];
            $statusAnterior = (string) $membro['status'];
            $stmtStatus->bind_param('is', $memberId, $statusAnterior);
            $stmtStatus->execute();
            if ($stmtStatus->affected_rows !== 1) {
                throw new RuntimeException('Uma tarefa mudou durante o inicio. Atualize a tela e tente novamente.');
            }
        }
        $stmtStatus->close();
    }

    if (array_key_exists('observacao', $entrada)) {
        $observacao = trim((string) $entrada['observacao']);
        $observacaoDb = $observacao !== '' ? $observacao : null;
        $principalId = (int) $unidade['tarefa_principal']['idfuncao_imagem'];
        $stmtObs = $conn->prepare('UPDATE funcao_imagem SET observacao = ? WHERE idfuncao_imagem = ?');
        $stmtObs->bind_param('si', $observacaoDb, $principalId);
        $stmtObs->execute();
        $stmtObs->close();
    }

    flow_inicio_operacional_salvar_previsao_legada($conn, $unidade['membros'], $previsao, $motivo, $atorColaboradorId, $atorUsuarioId);
    $cicloAnteriorId = null;
    if ($reabertura) {
        $anterior = $ultimoCicloExistente;
        if ($anterior) {
            $cicloAnteriorId = (int) $anterior['id'];
            if (!empty($anterior['ativo_token'])) {
                flow_janela_encerrar_ciclo($conn, $tarefaId, 'REABERTURA', $atorColaboradorId, $atorUsuarioId);
            }
        }
    }
    $ciclo = flow_janela_criar_ciclo(
        $conn,
        $unidade,
        $avaliacao,
        $motivo,
        $atorColaboradorId,
        $atorUsuarioId,
        $cicloAnteriorId,
        $reabertura ? 'REABERTURA' : 'PRIMEIRO_INICIO'
    );
    return [
        'success' => true,
        'message' => count($unidade['membros']) > 1 ? 'Unidade de trabalho iniciada.' : 'Tarefa iniciada.',
        'cycle' => $ciclo,
        'evaluation' => $avaliacao,
        'work_unit' => [
            'type' => $unidade['tipo_unidade'],
            'unit_id' => $unidade['unidade_trabalho_id'],
            'member_ids' => array_map(static fn (array $m): int => (int) $m['idfuncao_imagem'], $unidade['membros']),
        ],
        'reopened' => $reabertura,
    ];
}
