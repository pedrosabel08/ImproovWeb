<?php

require_once __DIR__ . '/pendencias_operacionais_helper.php';
require_once __DIR__ . '/flow_block_helper.php';
require_once __DIR__ . '/flow_review_aprovacao_helper.php';

if (!defined('MOTOR_REQUISITOS_VERSAO')) {
    define('MOTOR_REQUISITOS_VERSAO', 'PROJECT_REQUIREMENTS_V1');
}

function motor_requisitos_item(
    string $codigo,
    string $label,
    string $tipo,
    string $estado,
    bool $obrigatorio = true,
    string $origem = '',
    ?int $origemId = null,
    string $urlAcao = '',
    array $metadados = []
): array {
    return array_merge([
        'codigo' => $codigo,
        'label' => $label,
        'tipo' => $tipo,
        'estado' => $estado,
        'obrigatorio' => $obrigatorio,
        'bloqueia_inicio' => $obrigatorio && $estado === 'NAO_ATENDIDO',
        'origem' => $origem,
        'origem_id' => $origemId,
        'url_acao' => $urlAcao,
    ], $metadados);
}

function motor_requisitos_resultado(
    bool $aplicavel,
    array $requisitos,
    bool $legacyLiberada = true,
    ?string $erroConfiguracao = null
): array {
    if ($erroConfiguracao !== null) {
        $requisitos[] = motor_requisitos_item(
            'CONFIGURACAO_AUSENTE',
            'Configuração obrigatória do Motor de Requisitos ausente',
            'CONFIGURACAO',
            'NAO_ATENDIDO',
            true,
            'Motor de Requisitos',
            null,
            '',
            ['flow_block' => ['action' => 'disabled']]
        );
    }
    $bloqueios = array_values(array_filter($requisitos, static function (array $item): bool {
        return !empty($item['bloqueia_inicio']);
    }));
    // Não aplicável não significa liberada. A decisão precisa continuar
    // respeitando a compatibilidade legada ou o bloqueio seguro de configuração.
    $elegivel = $erroConfiguracao === null && empty($bloqueios) && $legacyLiberada;
    $atendidos = count(array_filter($requisitos, static fn (array $i): bool => $i['estado'] === 'ATENDIDO'));
    $pendentes = count(array_filter($requisitos, static fn (array $i): bool => $i['estado'] === 'NAO_ATENDIDO'));

    return [
        'politica_versao' => $aplicavel ? MOTOR_REQUISITOS_VERSAO : null,
        'aplicavel' => $aplicavel,
        'legacy_only' => !$aplicavel,
        'elegivel' => $elegivel,
        'liberada' => $elegivel,
        'decisao' => $erroConfiguracao !== null ? 'CONFIGURACAO_AUSENTE' : ($elegivel ? 'LIBERADA' : 'BLOQUEADA'),
        'erro_configuracao' => $erroConfiguracao,
        'requisitos_avaliados' => array_values($requisitos),
        'bloqueios' => $bloqueios,
        'resumo' => [
            'total' => count($requisitos),
            'atendidos' => $atendidos,
            'pendentes' => $pendentes,
            'bloqueantes' => count($bloqueios),
        ],
    ];
}

function motor_requisitos_checklist_projeto(mysqli $conn, int $obraId): ?array
{
    $stmt = $conn->prepare(
        "SELECT co.id, co.requirements_version, co.responsavel_id,
                c.nome_colaborador AS responsavel_nome
           FROM checklist_operacional
           co
           LEFT JOIN colaborador c ON c.idcolaborador = co.responsavel_id
          WHERE module_key = 'projeto'
            AND entity_type = 'obra'
            AND entity_id = ?
          LIMIT 1"
    );
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function motor_requisitos_itens_projeto(mysqli $conn, int $checklistId): array
{
    $items = [];
    foreach (pendencias_operacionais_fetch_checklist_items($conn, $checklistId) as $row) {
        $items[(string) $row['item_key']] = $row;
    }
    return $items;
}

function motor_requisitos_projeto(
    array $items,
    string $key,
    string $label,
    bool $obrigatorio = true,
    bool $checklistVersionado = true,
    ?array $responsavel = null
): array {
    if (!$checklistVersionado) {
        return motor_requisitos_item(
            $key,
            $label,
            'PROJETO',
            'NAO_APLICAVEL',
            false,
            'Regra legada do projeto',
            null,
            '',
            motor_requisitos_metadados_origem(null, $responsavel)
        );
    }
    $item = $items[$key] ?? null;
    $required = $item ? (int) ($item['required'] ?? 0) === 1 : $obrigatorio;
    $estado = !$required
        ? (!empty($item['done']) ? 'ATENDIDO' : 'NAO_APLICAVEL')
        : (!empty($item['done']) ? 'ATENDIDO' : 'NAO_ATENDIDO');
    return motor_requisitos_item(
        $key,
        $label,
        'PROJETO',
        $estado,
        $obrigatorio && $required,
        'Checklist do projeto',
        null,
        '/ImproovWeb/Dashboard/obra.php',
        motor_requisitos_metadados_origem(null, $responsavel)
    );
}

function motor_requisitos_metadados_origem(?array $tarefa = null, ?array $responsavel = null): array
{
    $responsavelId = (int) ($tarefa['colaborador_id'] ?? ($responsavel['id'] ?? $responsavel['responsavel_id'] ?? 0));
    $responsavelNome = (string) ($tarefa['nome_colaborador'] ?? ($responsavel['nome'] ?? $responsavel['responsavel_nome'] ?? ''));
    $metadados = [
        'origem_responsavel_id' => $responsavelId ?: null,
        'origem_responsavel_nome' => $responsavelNome,
    ];
    if ($tarefa) {
        $metadados['origem_tarefa'] = [
            'id' => (int) ($tarefa['idfuncao_imagem'] ?? 0) ?: null,
            'nome' => (string) ($tarefa['nome_funcao'] ?? 'Tarefa produtiva'),
            'imagem_nome' => (string) ($tarefa['imagem_nome'] ?? ''),
            'responsavel_id' => $responsavelId ?: null,
            'responsavel_nome' => $responsavelNome,
            'url' => !empty($tarefa['idfuncao_imagem'])
                ? '/ImproovWeb/inicio.php?funcao_imagem_id=' . (int) $tarefa['idfuncao_imagem']
                : '',
        ];
    }
    return $metadados;
}

function motor_requisitos_sugestao_flow_block(array $requisito, int $fallbackResponsavelId = 0): array
{
    $mapa = [
        'briefing' => ['DEPENDENCIA_OUTRA_TAREFA', 'ARQUITETURA'],
        'kickoff' => ['DEPENDENCIA_OUTRA_TAREFA', 'ARQUITETURA'],
        'arquivos_tecnicos' => ['ARQUIVO_FALTANTE', 'ARQUITETURA'],
        'referencias_mood' => ['REFERENCIA_NAO_DEFINIDA', 'GESTAO'],
        'fotografico' => ['FOTOGRAFICO_FALTANTE', 'GESTAO'],
        'FUNCAO_ANTERIOR_CONCLUIDA' => ['DEPENDENCIA_OUTRA_TAREFA', 'PRODUCAO'],
        'APROVACAO_ETAPA_ANTERIOR' => ['APROVACAO_PENDENTE', 'PRODUCAO'],
        'ENVIO_APROVACAO_ETAPA_ANTERIOR' => ['DEPENDENCIA_OUTRA_TAREFA', 'PRODUCAO'],
        'ARQUIVO_FINALIZACAO_ENVIADO' => ['ARQUIVO_FALTANTE', 'PRODUCAO'],
        'subtipo_definido' => ['DUVIDA_TECNICA', 'ARQUITETURA'],
        'arquivos_finais_subtipo' => ['ARQUIVO_FALTANTE', 'PRODUCAO'],
        'render_aprovado' => ['APROVACAO_PENDENTE', 'PRODUCAO'],
        'entrega_registrada_etapa_atual' => ['DEPENDENCIA_OUTRA_TAREFA', 'PRODUCAO'],
        'MODELAGEM_FACHADA_BASE_AUSENTE' => ['DEPENDENCIA_OUTRA_TAREFA', 'PRODUCAO'],
        'imagem_base_pronta' => ['DEPENDENCIA_OUTRA_TAREFA', 'PRODUCAO'],
    ];
    $codigo = (string) ($requisito['codigo'] ?? '');
    if (!isset($mapa[$codigo])) {
        return [];
    }
    [$tipoCodigo, $filaCodigo] = $mapa[$codigo];
    $responsavelId = (int) ($requisito['origem_responsavel_id'] ?? 0) ?: $fallbackResponsavelId;
    return [
        'flow_block_sugestao' => [
            'tipo_codigo' => $tipoCodigo,
            'fila_codigo' => $filaCodigo,
            'responsavel_id' => $responsavelId ?: null,
        ],
    ];
}

function motor_requisitos_politica_funcao_imagem(array $context): ?string
{
    $funcaoId = (int) ($context['funcao_id'] ?? 0);
    if ($funcaoId === 1) {
        return 'CADERNO';
    }
    if ($funcaoId === 8) {
        return 'FILTRO';
    }
    if ($funcaoId === 2) {
        return 'MODELAGEM';
    }
    if ($funcaoId === 3) {
        return 'COMPOSICAO';
    }
    if ($funcaoId === 4) {
        return trim((string) ($context['tipo_imagem'] ?? '')) === 'Planta Humanizada'
            ? 'FINALIZACAO_PLANTA_HUMANIZADA'
            : 'FINALIZACAO';
    }
    if ($funcaoId === 7) {
        return 'FINALIZACAO_PLANTA_HUMANIZADA';
    }
    if ($funcaoId === 5) {
        return 'POS_PRODUCAO';
    }
    if ($funcaoId === 6) {
        return 'ALTERACAO';
    }
    if ($funcaoId === 9) {
        return 'LEGADO_PRE_FINALIZACAO';
    }
    return null;
}

function motor_requisitos_flow_block_contexto(array $taskContext, array $requirement): array
{
    return [
        'requirement_code' => (string) ($requirement['codigo'] ?? ''),
        'requirement_name' => (string) ($requirement['label'] ?? ''),
        'requirement_origin' => (string) ($requirement['origem'] ?? ''),
        'requirement_origin_id' => isset($requirement['origem_id']) ? (int) $requirement['origem_id'] : null,
        'requirement_type' => (string) ($requirement['tipo'] ?? ''),
        'requirement_source_url' => (string) ($requirement['url_acao'] ?? ''),
        'requirement_origin_task' => $requirement['origem_tarefa'] ?? null,
        'requirement_origin_responsavel_id' => $requirement['origem_responsavel_id'] ?? null,
        'requirement_origin_responsavel_nome' => (string) ($requirement['origem_responsavel_nome'] ?? ''),
        'approval' => $requirement['aprovacao'] ?? null,
        'blocked_task' => [
            'id' => (int) ($taskContext['idfuncao_imagem'] ?? 0) ?: null,
            'nome' => (string) ($taskContext['nome_funcao'] ?? ''),
            'imagem_nome' => (string) ($taskContext['imagem_nome'] ?? ''),
            'responsavel_id' => (int) ($taskContext['tarefa_responsavel_id'] ?? 0) ?: null,
            'responsavel_nome' => (string) ($taskContext['tarefa_responsavel_nome'] ?? ''),
        ],
        'requirement_context' => sprintf(
            'Tarefa %s (%s) em %s com requisito pendente: %s.',
            (string) ($taskContext['imagem_nome'] ?? 'sem nome'),
            (string) ($taskContext['nome_funcao'] ?? ('função ' . (string) ($taskContext['funcao_id'] ?? ''))),
            (string) ($taskContext['status'] ?? 'status não informado'),
            (string) ($requirement['label'] ?? 'Requisito')
        ),
    ];
}

function motor_requisitos_enriquecer_requisito_flow_block(mysqli $conn, array $taskContext, array $requirement): array
{
    $aliases = array_values(array_filter(array_map('strval', (array) ($requirement['flow_block_aliases'] ?? []))));
    unset($requirement['flow_block_aliases']);
    $state = (string) ($requirement['estado'] ?? '');
    if ((string) ($requirement['codigo'] ?? '') === 'CONFIGURACAO_AUSENTE') {
        $requirement['flow_block'] = ['action' => 'disabled'];
        return $requirement;
    }
    if ($state !== 'NAO_ATENDIDO' || empty($taskContext['idfuncao_imagem']) || !flow_block_has_tables($conn)) {
        return $requirement;
    }

    $codes = array_values(array_unique(array_merge([(string) ($requirement['codigo'] ?? '')], $aliases)));
    $issue = null;
    foreach ($codes as $code) {
        if ($code === '') {
            continue;
        }
        $issue = flow_block_find_active_issue_by_requirement(
            $conn,
            (int) $taskContext['idfuncao_imagem'],
            $code
        );
        if ($issue) {
            break;
        }
    }

    $requirement['flow_block'] = [
        'action' => $issue ? 'open' : 'create',
        'action_label' => (string) ($requirement['codigo'] ?? '') === 'APROVACAO_ETAPA_ANTERIOR'
            ? 'Solicitar liberação'
            : 'Registrar impedimento',
        'contextual_approval' => (string) ($requirement['codigo'] ?? '') === 'APROVACAO_ETAPA_ANTERIOR',
        'issue_id' => $issue ? (int) $issue['id'] : null,
        'issue_code' => $issue ? (string) ($issue['codigo'] ?? '') : null,
        'issue_url' => $issue ? '/ImproovWeb/FlowBlock/issue.php?id=' . (int) $issue['id'] : null,
        'context' => motor_requisitos_flow_block_contexto($taskContext, $requirement),
    ];

    return $requirement;
}

function motor_requisitos_predecessora(mysqli $conn, int $imagemId, int $funcaoId): ?array
{
    $stmt = $conn->prepare(
        "SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.status, fi.colaborador_id, fi.requires_file_upload,
                fi.file_uploaded_at, c.nome_colaborador, f.nome_funcao, ico.imagem_nome, ico.status_id AS imagem_status_id
           FROM funcao_imagem fi
           LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
           JOIN funcao f ON f.idfuncao = fi.funcao_id
           JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
          WHERE fi.imagem_id = ? AND fi.funcao_id = ?
          LIMIT 1"
    );
    $stmt->bind_param('ii', $imagemId, $funcaoId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function motor_requisitos_ordem_producao(): array
{
    return [1, 8, 2, 3, 9, 4, 5, 6, 7];
}

/** Busca a etapa existente imediatamente anterior, ignorando funções ausentes. */
function motor_requisitos_predecessora_anterior_existente(mysqli $conn, int $imagemId, int $funcaoId): ?array
{
    $ordem = motor_requisitos_ordem_producao();
    $posicao = array_search($funcaoId, $ordem, true);
    if ($posicao === false) {
        return null;
    }

    for ($indice = $posicao - 1; $indice >= 0; $indice--) {
        $predecessora = motor_requisitos_predecessora($conn, $imagemId, (int) $ordem[$indice]);
        if ($predecessora) {
            $predecessora['funcao_id'] = (int) $ordem[$indice];
            return $predecessora;
        }
    }
    return null;
}

function motor_requisitos_modelagem_base_fachada(mysqli $conn, int $obraId): ?array
{
    $stmt = $conn->prepare(
        "SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.status, fi.colaborador_id,
                fi.requires_file_upload, fi.file_uploaded_at, c.nome_colaborador,
                f.nome_funcao, ico.imagem_nome
           FROM imagens_cliente_obra ico
           JOIN funcao_imagem fi
             ON fi.imagem_id = ico.idimagens_cliente_obra
            AND fi.funcao_id = 2
           LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
           JOIN funcao f ON f.idfuncao = fi.funcao_id
          WHERE ico.obra_id = ?
            AND LOWER(TRIM(ico.tipo_imagem)) = 'fachada'
          ORDER BY ico.idimagens_cliente_obra ASC, fi.idfuncao_imagem ASC
          LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function motor_requisitos_aliases_predecessora(?array $predecessora, bool $arquivo): array
{
    $funcaoId = (int) ($predecessora['funcao_id'] ?? 0);
    if ($funcaoId === 2) {
        return $arquivo ? ['ARQUIVO_FINAL_MODELAGEM_AUSENTE'] : ['MODELAGEM_NAO_CONCLUIDA'];
    }
    if ($funcaoId === 3) {
        return $arquivo ? ['arquivo_composicao'] : ['composicao_concluida'];
    }
    if ($funcaoId === 1 && !$arquivo) {
        return ['caderno_concluido'];
    }
    return [];
}

function motor_requisitos_adicionar_predecessora(mysqli $conn, array &$requisitos, ?array $predecessora, string $origem, string $urlAcao, bool $ignorarPendenciaArquivo = false): void
{
    if (!$predecessora) {
        $requisitos[] = motor_requisitos_item(
            'FUNCAO_ANTERIOR_CONCLUIDA',
            'Tarefa produtiva anterior concluida',
            'PRODUCAO',
            'NAO_APLICAVEL',
            false,
            $origem,
            null,
            $urlAcao
        );
        return;
    }

    $origemId = (int) $predecessora['idfuncao_imagem'];
    $status = trim((string) ($predecessora['status'] ?? ''));
    $metadados = motor_requisitos_metadados_origem($predecessora);

    if (in_array($status, ['Em aprovação', 'Aguardando Direção'], true)) {
        $aprovadores = flow_review_aprovacao_destinatarios($conn, $predecessora);
        $metadados['aprovacao'] = [
            'status' => $status,
            'predecessora_funcao_imagem_id' => $origemId,
            'approval_cycle_key' => $origemId . ':' . ($predecessora['file_uploaded_at'] ?? $status),
            'aprovadores' => $aprovadores,
        ];
        $requisito = motor_requisitos_item(
            'APROVACAO_ETAPA_ANTERIOR',
            'Aprovação da etapa anterior',
            'APROVACAO',
            'NAO_ATENDIDO',
            true,
            $origem,
            $origemId,
            $urlAcao,
            $metadados
        );
        $requisito['nao_confirmavel'] = true;
        $requisitos[] = $requisito;
        return;
    }

    if ($status === 'Finalizado') {
        $arquivoAtendido = (int) ($predecessora['requires_file_upload'] ?? 1) === 0
            || !empty($predecessora['file_uploaded_at']);

        // Finalizado sem arquivo obrigatório já é uma conclusão válida. Para a
        // função 5, a conclusão também libera a etapa mesmo sem o arquivo da
        // predecessora; nas demais funções, o envio continua bloqueante.
        if ($arquivoAtendido) {
            $requisitos[] = motor_requisitos_item(
                'FUNCAO_ANTERIOR_CONCLUIDA',
                'Tarefa produtiva anterior concluída',
                'PRODUCAO',
                'ATENDIDO',
                true,
                $origem,
                $origemId,
                $urlAcao,
                $metadados
            );
            return;
        }

        if ($ignorarPendenciaArquivo) {
            $requisitos[] = motor_requisitos_item(
                'FUNCAO_ANTERIOR_CONCLUIDA',
                'Tarefa produtiva anterior concluída',
                'PRODUCAO',
                'ATENDIDO',
                true,
                $origem,
                $origemId,
                $urlAcao,
                $metadados
            );
        } else {
            $requisito = motor_requisitos_item(
                'ENVIO_APROVACAO_ETAPA_ANTERIOR',
                'Arquivo da etapa anterior não enviado para aprovação',
                'APROVACAO',
                'NAO_ATENDIDO',
                true,
                $origem,
                $origemId,
                $urlAcao,
                $metadados
            );
            $requisito['nao_confirmavel'] = true;
            $requisitos[] = $requisito;
        }
        return;
    }

    $conclusao = motor_requisitos_item(
        'FUNCAO_ANTERIOR_CONCLUIDA',
        'Tarefa produtiva anterior concluida',
        'PRODUCAO',
        motor_requisitos_estado_predecessora($predecessora),
        true,
        $origem,
        $origemId,
        $urlAcao,
        $metadados
    );
    $conclusao['flow_block_aliases'] = motor_requisitos_aliases_predecessora($predecessora, false);
    $requisitos[] = $conclusao;
}

function motor_requisitos_fotografico(mysqli $conn, int $obraId): array
{
    $fotografico = pendencias_operacionais_fotografico_plano_estado($conn, $obraId);
    return motor_requisitos_item(
        'fotografico',
        'Fotografico',
        'PROJETO',
        (string) $fotografico['estado'],
        true,
        $fotografico['estado'] === 'NAO_APLICAVEL' ? 'Plano fotográfico inexistente' : 'Plano fotográfico',
        $fotografico['plano_id'] ? (int) $fotografico['plano_id'] : null,
        '/ImproovWeb/Fotografico/index.php?obra_id=' . $obraId . ($fotografico['plano_id'] ? '&plano_id=' . (int) $fotografico['plano_id'] : ''),
        motor_requisitos_metadados_origem(null, [
            'responsavel_id' => $fotografico['responsavel_id'] ?? null,
            'responsavel_nome' => $fotografico['responsavel_nome'] ?? '',
        ])
    );
}

function motor_requisitos_finalizacao_da_imagem(mysqli $conn, int $imagemId): ?array
{
    $stmt = $conn->prepare(
        "SELECT fi.idfuncao_imagem, fi.status, fi.colaborador_id, fi.requires_file_upload,
                fi.file_uploaded_at, c.nome_colaborador, f.nome_funcao, ico.imagem_nome
           FROM funcao_imagem fi
           JOIN funcao f ON f.idfuncao = fi.funcao_id
           JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
           LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
          WHERE fi.imagem_id = ? AND fi.funcao_id IN (4, 7)
          ORDER BY CASE WHEN fi.funcao_id = 4 THEN 0 ELSE 1 END, fi.idfuncao_imagem ASC
          LIMIT 1"
    );
    $stmt->bind_param('i', $imagemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function motor_requisitos_entrega_registrada_na_etapa(mysqli $conn, int $obraId, int $imagemId, int $statusId): ?array
{
    if ($obraId <= 0 || $imagemId <= 0 || $statusId <= 0) {
        return null;
    }

    // Entregas comuns vinculam a imagem em entregas_itens. P00 possui versões
    // próprias, mas segue a mesma regra: a entrega precisa estar registrada na
    // etapa atual da imagem.
    $stmt = $conn->prepare(
        "SELECT e.id AS entrega_id, ei.id AS entrega_item_id, e.data_prevista
           FROM entregas e
           JOIN entregas_itens ei ON ei.entrega_id = e.id
          WHERE e.obra_id = ?
            AND e.status_id = ?
            AND ei.imagem_id = ?
            AND (e.arquivada IS NULL OR e.arquivada = 0)
          ORDER BY e.id DESC, ei.id DESC
          LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('iii', $obraId, $statusId, $imagemId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return $row;
        }
    }

    $stmt = $conn->prepare(
        "SELECT e.id AS entrega_id, v.id AS entrega_item_id, e.data_prevista
           FROM entregas e
           JOIN entregas_p00_versoes v ON v.entrega_id = e.id
          WHERE e.obra_id = ?
            AND e.status_id = ?
            AND v.imagem_id = ?
            AND COALESCE(e.tipo_entrega, 'PADRAO') = 'P00'
            AND (e.arquivada IS NULL OR e.arquivada = 0)
          ORDER BY e.id DESC, v.id DESC
          LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('iii', $obraId, $statusId, $imagemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function motor_requisitos_primeira_composicao_pendente_subtipo(mysqli $conn, int $obraId, int $subtipoId): ?array
{
    if ($subtipoId <= 0) {
        return null;
    }
    $stmt = $conn->prepare(
        "SELECT fi.idfuncao_imagem, fi.status, fi.colaborador_id, fi.requires_file_upload,
                fi.file_uploaded_at, c.nome_colaborador, f.nome_funcao, ico.imagem_nome
           FROM imagens_cliente_obra ico
           JOIN funcao_imagem fi ON fi.imagem_id = ico.idimagens_cliente_obra AND fi.funcao_id = 3
           JOIN funcao f ON f.idfuncao = fi.funcao_id
           LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
          WHERE ico.obra_id = ? AND ico.subtipo_id = ?
            AND fi.colaborador_id <> 15
            AND NOT (fi.status IN ('Finalizado','Aprovado','Aprovado com ajustes')
                     AND fi.requires_file_upload = 0 AND fi.file_uploaded_at IS NOT NULL)
          ORDER BY ico.idimagens_cliente_obra ASC, fi.idfuncao_imagem ASC
          LIMIT 1"
    );
    $stmt->bind_param('ii', $obraId, $subtipoId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function motor_requisitos_estado_predecessora(?array $row): string
{
    if (!$row) {
        return 'NAO_APLICAVEL';
    }
    $nome = mb_strtolower(trim((string) ($row['nome_colaborador'] ?? '')), 'UTF-8');
    if ((int) ($row['colaborador_id'] ?? 0) === 15 || $nome === 'não se aplica' || $nome === 'nao se aplica') {
        return 'DISPENSADO';
    }
    return in_array((string) ($row['status'] ?? ''), ['Finalizado', 'Aprovado', 'Aprovado com ajustes'], true)
        ? 'ATENDIDO'
        : 'NAO_ATENDIDO';
}

function motor_requisitos_estado_arquivo(?array $row): string
{
    $estadoPredecessora = motor_requisitos_estado_predecessora($row);
    if (in_array($estadoPredecessora, ['NAO_APLICAVEL', 'DISPENSADO'], true)) {
        return $estadoPredecessora;
    }
    return (int) ($row['requires_file_upload'] ?? 1) === 0 || !empty($row['file_uploaded_at'])
        ? 'ATENDIDO'
        : 'NAO_ATENDIDO';
}

function motor_requisitos_avaliar_funcao_imagem(mysqli $conn, int $funcaoImagemId, ?bool $legacyLiberada = null): array
{
    $stmt = $conn->prepare(
        "SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.status, fi.colaborador_id AS tarefa_responsavel_id,
                f.nome_funcao,
                ico.imagem_nome, ico.obra_id, ico.tipo_imagem, ico.subtipo_id,
                o.liberar_modelagem,
                c.nome_colaborador AS tarefa_responsavel_nome,
                ico.status_id AS imagem_status_id, si.nome_status AS imagem_status_nome
           FROM funcao_imagem fi
           JOIN funcao f ON f.idfuncao = fi.funcao_id
           JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
           JOIN obra o ON o.idobra = ico.obra_id
           LEFT JOIN status_imagem si ON si.idstatus = ico.status_id
           LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
          WHERE fi.idfuncao_imagem = ?
          LIMIT 1"
    );
    $stmt->bind_param('i', $funcaoImagemId);
    $stmt->execute();
    $context = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$context) {
        return motor_requisitos_resultado(true, [], false, 'Tarefa de imagem não localizada para avaliação.');
    }

    $obraId = (int) $context['obra_id'];
    $politica = motor_requisitos_politica_funcao_imagem($context);
    if ($politica === null) {
        return motor_requisitos_resultado(true, [], false, 'Não existe política cadastrada para a função ' . (int) $context['funcao_id'] . '.');
    }
    if ($politica === 'LEGADO_PRE_FINALIZACAO') {
        return motor_requisitos_resultado(false, [], $legacyLiberada ?? false);
    }

    $checklist = motor_requisitos_checklist_projeto($conn, $obraId);
    $checklistVersionado = $checklist
        && ($checklist['requirements_version'] ?? null) === MOTOR_REQUISITOS_VERSAO;
    $projectItems = $checklistVersionado
        ? motor_requisitos_itens_projeto($conn, (int) $checklist['id'])
        : [];
    $checklistResponsavel = $checklist ? [
        'responsavel_id' => $checklist['responsavel_id'] ?? null,
        'responsavel_nome' => $checklist['responsavel_nome'] ?? '',
    ] : null;
    $funcaoId = (int) $context['funcao_id'];
    $imagemId = (int) $context['imagem_id'];
    $requisitos = [];
    $taskUrl = '/ImproovWeb/inicio.php?imagem_id=' . $imagemId;

    if ($funcaoId === 1) {
        $requisitos[] = motor_requisitos_projeto($projectItems, 'briefing', 'Briefing', true, $checklistVersionado, $checklistResponsavel);
        $requisitos[] = motor_requisitos_projeto($projectItems, 'kickoff', 'Kickoff', false, $checklistVersionado, $checklistResponsavel);
    } elseif ($funcaoId === 8) {
        $requisitos[] = motor_requisitos_projeto($projectItems, 'arquivos_tecnicos', 'Arquivos Tecnicos', true, $checklistVersionado, $checklistResponsavel);
    } elseif ($funcaoId === 2) {
        $requisitos[] = motor_requisitos_projeto($projectItems, 'arquivos_tecnicos', 'Arquivos Tecnicos', true, $checklistVersionado, $checklistResponsavel);
    } elseif ($funcaoId === 3) {
        // Composição não depende do requisito de Referências do projeto.
    } elseif ($funcaoId === 4 || $funcaoId === 7) {
        $isPlanta = $funcaoId === 7 || trim((string) $context['tipo_imagem']) === 'Planta Humanizada';
        if ($isPlanta) {
            $requisitos[] = motor_requisitos_projeto($projectItems, 'arquivos_tecnicos', 'Arquivos Tecnicos', true, $checklistVersionado, $checklistResponsavel);
            $subtipoId = (int) ($context['subtipo_id'] ?? 0);
            $requisitos[] = motor_requisitos_item('subtipo_definido', 'Subtipo definido', 'PROJETO', $subtipoId > 0 ? 'ATENDIDO' : 'NAO_ATENDIDO', true, 'Cadastro da imagem', $imagemId, '/ImproovWeb/Dashboard/obra.php?obra_id=' . $obraId, motor_requisitos_metadados_origem(null, $checklistResponsavel));
            if ($subtipoId <= 0) {
                $estadoArquivos = 'NAO_ATENDIDO';
            } else {
                $stmtComp = $conn->prepare(
                    "SELECT COUNT(*) total,
                            SUM(CASE WHEN fi.colaborador_id = 15
                                      OR (fi.status IN ('Finalizado','Aprovado','Aprovado com ajustes')
                                      AND fi.requires_file_upload = 0
                                      AND fi.file_uploaded_at IS NOT NULL) THEN 1 ELSE 0 END) atendidas
                       FROM imagens_cliente_obra ico
                       JOIN funcao_imagem fi ON fi.imagem_id = ico.idimagens_cliente_obra AND fi.funcao_id = 3
                      WHERE ico.obra_id = ? AND ico.subtipo_id = ?"
                );
                $stmtComp->bind_param('ii', $obraId, $subtipoId);
                $stmtComp->execute();
                $agg = $stmtComp->get_result()->fetch_assoc();
                $stmtComp->close();
                $total = (int) ($agg['total'] ?? 0);
                $estadoArquivos = $total === 0
                    ? 'NAO_APLICAVEL'
                    : (((int) ($agg['atendidas'] ?? 0) === $total) ? 'ATENDIDO' : 'NAO_ATENDIDO');
            }
            $composicaoPendente = $estadoArquivos === 'NAO_ATENDIDO'
                ? motor_requisitos_primeira_composicao_pendente_subtipo($conn, $obraId, $subtipoId)
                : null;
            $requisitos[] = motor_requisitos_item('arquivos_finais_subtipo', 'Arquivos finais do subtipo', 'PRODUCAO', $estadoArquivos, true, 'Composicoes do subtipo', $subtipoId ?: null, $taskUrl, motor_requisitos_metadados_origem($composicaoPendente, $checklistResponsavel));
        } else {
            $requisitos[] = motor_requisitos_projeto($projectItems, 'referencias_mood', 'Referencias', true, $checklistVersionado, $checklistResponsavel);
            $requisitos[] = motor_requisitos_fotografico($conn, $obraId);
        }
    } elseif ($funcaoId === 5) {
        $imagemStatusId = (int) ($context['imagem_status_id'] ?? 0);
        $stmtRender = $conn->prepare(
            "SELECT r.idrender_alta, r.status, r.responsavel_id, c.nome_colaborador
               FROM render_alta r
               LEFT JOIN colaborador c ON c.idcolaborador = r.responsavel_id
              WHERE r.imagem_id = ? AND r.status_id = ? AND r.excluido_em IS NULL
              ORDER BY r.idrender_alta DESC LIMIT 1"
        );
        $stmtRender->bind_param('ii', $imagemId, $imagemStatusId);
        $stmtRender->execute();
        $render = $stmtRender->get_result()->fetch_assoc();
        $stmtRender->close();
        $renderAprovado = $render && mb_strtolower((string) ($render['status'] ?? ''), 'UTF-8') === 'aprovado';
        $requisitos[] = motor_requisitos_item('render_aprovado', 'Render aprovado', 'PRODUCAO', $renderAprovado ? 'ATENDIDO' : 'NAO_ATENDIDO', true, 'Render', $render ? (int) $render['idrender_alta'] : null, '/ImproovWeb/Render/index.php', motor_requisitos_metadados_origem(null, [
            'responsavel_id' => $render['responsavel_id'] ?? null,
            'responsavel_nome' => $render['nome_colaborador'] ?? '',
        ]));
    } elseif ($funcaoId === 6) {
        $entrega = motor_requisitos_entrega_registrada_na_etapa(
            $conn,
            $obraId,
            $imagemId,
            (int) ($context['imagem_status_id'] ?? 0)
        );
        $etapa = trim((string) ($context['imagem_status_nome'] ?? ''));
        $requisitos[] = motor_requisitos_item(
            'entrega_registrada_etapa_atual',
            'Entrega registrada para a imagem na etapa atual',
            'ENTREGA',
            $entrega ? 'ATENDIDO' : 'NAO_ATENDIDO',
            true,
            $etapa !== '' ? 'Entregas da etapa ' . $etapa : 'Entregas da etapa atual',
            $entrega ? (int) $entrega['entrega_id'] : null,
            '/ImproovWeb/Entregas/index.php?obra_id=' . $obraId,
            motor_requisitos_metadados_origem(null, $checklistResponsavel)
        );

        $finalizacao = motor_requisitos_finalizacao_da_imagem($conn, $imagemId);
        $requisitos[] = motor_requisitos_item(
            'ARQUIVO_FINALIZACAO_ENVIADO',
            'Arquivo da Finalizacao enviado',
            'PRODUCAO',
            $finalizacao ? motor_requisitos_estado_arquivo($finalizacao) : 'NAO_ATENDIDO',
            true,
            $finalizacao ? 'Finalizacao da imagem' : 'Finalizacao da imagem nao cadastrada',
            $finalizacao ? (int) $finalizacao['idfuncao_imagem'] : null,
            $finalizacao ? '/ImproovWeb/inicio.php?funcao_imagem_id=' . (int) $finalizacao['idfuncao_imagem'] : '/ImproovWeb/Dashboard/obra.php?obra_id=' . $obraId,
            motor_requisitos_metadados_origem($finalizacao, $checklistResponsavel)
        );
    }

    // A cadeia produtiva usa a predecessora existente mais próxima. Alteração
    // e Pré-Finalização preservam suas regras próprias, sem pré-requisito linear.
    if (!in_array($funcaoId, [1, 6, 9], true)) {
        $tipoImagem = mb_strtolower(trim((string) ($context['tipo_imagem'] ?? '')), 'UTF-8');
        $predecessora = null;
        $origem = 'Tarefa produtiva anterior';
        $urlPredecessora = $taskUrl;

        $liberarModelagem = $funcaoId === 2 && (int) ($context['liberar_modelagem'] ?? 0) === 1;
        $composicaoAposModelagemAntecipada = $funcaoId === 3
            && (int) ($context['liberar_modelagem'] ?? 0) === 1;
        $usaModelagemBaseFachada = ($funcaoId === 4 && $tipoImagem === 'fachada')
            || ($funcaoId === 3 && $tipoImagem === 'imagem externa');
        if ($liberarModelagem) {
            // A liberação da obra permite iniciar Modelagem antes de qualquer
            // tarefa produtiva anterior, como no fluxo legado da obra.
        } elseif ($composicaoAposModelagemAntecipada) {
            // A exceção vale apenas para o início da Modelagem. A Composição
            // não pode herdar essa antecipação: quando Caderno e/ou Filtro
            // existirem na imagem, ambos precisam estar concluídos junto da
            // Modelagem antes de a Composição ser liberada.
            foreach ([1, 8, 2] as $funcaoAnteriorId) {
                $predecessora = motor_requisitos_predecessora($conn, $imagemId, $funcaoAnteriorId);
                if ($predecessora) {
                    motor_requisitos_adicionar_predecessora(
                        $conn,
                        $requisitos,
                        $predecessora,
                        'Etapa anterior à Composição',
                        $urlPredecessora
                    );
                }
            }
        } elseif ($usaModelagemBaseFachada) {
            $predecessora = motor_requisitos_modelagem_base_fachada($conn, $obraId);
            $origem = 'Modelagem-base da Fachada';
            if (!$predecessora) {
                $requisitos[] = motor_requisitos_item(
                    'MODELAGEM_FACHADA_BASE_AUSENTE',
                    'Modelagem-base da Fachada cadastrada',
                    'PRODUCAO',
                    'NAO_ATENDIDO',
                    true,
                    $origem,
                    null,
                    '/ImproovWeb/Dashboard/obra.php?obra_id=' . $obraId
                );
            } else {
                $urlPredecessora = '/ImproovWeb/inicio.php?imagem_id=' . (int) $predecessora['imagem_id'];
                motor_requisitos_adicionar_predecessora($conn, $requisitos, $predecessora, $origem, $urlPredecessora);
            }
        } else {
            $predecessora = motor_requisitos_predecessora_anterior_existente($conn, $imagemId, $funcaoId);
            motor_requisitos_adicionar_predecessora(
                $conn,
                $requisitos,
                $predecessora,
                $origem,
                $urlPredecessora,
                $funcaoId === 5
            );
        }
    }

    $fallbackResponsavelId = (int) ($checklist['responsavel_id'] ?? 0);
    foreach ($requisitos as &$requisito) {
        if (($requisito['origem'] ?? '') === 'Checklist do projeto') {
            $requisito['url_acao'] = '/ImproovWeb/Dashboard/obra.php?obra_id=' . $obraId;
        }
        $requisito = array_merge($requisito, motor_requisitos_sugestao_flow_block($requisito, $fallbackResponsavelId));
        $requisito = motor_requisitos_enriquecer_requisito_flow_block($conn, $context, $requisito);
    }
    unset($requisito);
    return motor_requisitos_resultado(true, $requisitos, $legacyLiberada ?? true);
}

function motor_requisitos_avaliar_funcao_animacao(mysqli $conn, int $funcaoAnimacaoId, ?bool $legacyLiberada = null): array
{
    $stmt = $conn->prepare(
        "SELECT fa.id, a.imagem_id, ico.obra_id, ico.status_id, ico.substatus_id
           FROM funcao_animacao fa
           JOIN animacao a ON a.idanimacao = fa.animacao_id
           JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = a.imagem_id
          WHERE fa.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $funcaoAnimacaoId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return motor_requisitos_resultado(true, [], false, 'Tarefa de animação não localizada para avaliação.');
    }
    $ready = (int) $row['status_id'] === 6 && (int) $row['substatus_id'] === 9;
    $req = motor_requisitos_item('imagem_base_pronta', 'Imagem-base pronta', 'PRODUCAO', $ready ? 'ATENDIDO' : 'NAO_ATENDIDO', true, 'Animacao', (int) $row['imagem_id'], '/ImproovWeb/Animacao/index.php');
    return motor_requisitos_resultado(true, [$req], $legacyLiberada ?? true);
}

function motor_requisitos_assert_inicio_funcao_imagem(mysqli $conn, int $funcaoImagemId): array
{
    $resultado = motor_requisitos_avaliar_funcao_imagem($conn, $funcaoImagemId);
    if (!$resultado['elegivel']) {
        throw new DomainException('A tarefa possui requisitos pendentes para iniciar.');
    }
    return $resultado;
}
