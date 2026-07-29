<?php
require_once __DIR__ . '/pendencias_operacionais_helper.php';
require_once __DIR__ . '/flow_block_helper.php';

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
    string $urlAcao = ''
): array {
    return [
        'codigo' => $codigo,
        'label' => $label,
        'tipo' => $tipo,
        'estado' => $estado,
        'obrigatorio' => $obrigatorio,
        'bloqueia_inicio' => $obrigatorio && $estado === 'NAO_ATENDIDO',
        'origem' => $origem,
        'origem_id' => $origemId,
        'url_acao' => $urlAcao,
    ];
}

function motor_requisitos_resultado(
    bool $aplicavel,
    array $requisitos,
    bool $legacyLiberada = true,
    ?string $erroConfiguracao = null
): array
{
    if ($erroConfiguracao !== null) {
        $requisitos[] = motor_requisitos_item(
            'CONFIGURACAO_AUSENTE',
            'Configuração obrigatória do Motor de Requisitos ausente',
            'CONFIGURACAO',
            'NAO_ATENDIDO',
            true,
            'Motor de Requisitos'
        );
    }
    $bloqueios = array_values(array_filter($requisitos, static function (array $item): bool {
        return !empty($item['bloqueia_inicio']);
    }));
    // Não aplicável não significa liberada. A decisão precisa continuar
    // respeitando a compatibilidade legada ou o bloqueio seguro de configuração.
    $elegivel = $erroConfiguracao === null && empty($bloqueios) && $legacyLiberada;
    $atendidos = count(array_filter($requisitos, static fn(array $i): bool => $i['estado'] === 'ATENDIDO'));
    $pendentes = count(array_filter($requisitos, static fn(array $i): bool => $i['estado'] === 'NAO_ATENDIDO'));

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
        "SELECT id, requirements_version
           FROM checklist_operacional
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
    bool $checklistVersionado = true
): array {
    if (!$checklistVersionado) {
        return motor_requisitos_item(
            $key,
            $label,
            'PROJETO',
            'NAO_APLICAVEL',
            false,
            'Regra legada do projeto'
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
        '/ImproovWeb/Dashboard/obra.php'
    );
}

function motor_requisitos_politica_funcao_imagem(array $context): ?string
{
    $funcaoId = (int) ($context['funcao_id'] ?? 0);
    if ($funcaoId === 1) return 'CADERNO';
    if ($funcaoId === 8) return 'FILTRO';
    if ($funcaoId === 2) return 'MODELAGEM';
    if ($funcaoId === 3) return 'COMPOSICAO';
    if ($funcaoId === 4) {
        return trim((string) ($context['tipo_imagem'] ?? '')) === 'Planta Humanizada'
            ? 'FINALIZACAO_PLANTA_HUMANIZADA'
            : 'FINALIZACAO';
    }
    if ($funcaoId === 7) return 'FINALIZACAO_PLANTA_HUMANIZADA';
    if ($funcaoId === 5) return 'POS_PRODUCAO';
    if ($funcaoId === 6) return 'ALTERACAO';
    if ($funcaoId === 9) return 'LEGADO_PRE_FINALIZACAO';
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
    $state = (string) ($requirement['estado'] ?? '');
    if ($state !== 'NAO_ATENDIDO' || empty($taskContext['idfuncao_imagem']) || !flow_block_has_tables($conn)) {
        return $requirement;
    }

    $issue = flow_block_find_active_issue_by_requirement(
        $conn,
        (int) $taskContext['idfuncao_imagem'],
        (string) ($requirement['codigo'] ?? '')
    );

    $requirement['flow_block'] = [
        'action' => $issue ? 'open' : 'create',
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
        "SELECT fi.idfuncao_imagem, fi.status, fi.colaborador_id, fi.requires_file_upload,
                fi.file_uploaded_at, c.nome_colaborador
           FROM funcao_imagem fi
           LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
          WHERE fi.imagem_id = ? AND fi.funcao_id = ?
          LIMIT 1"
    );
    $stmt->bind_param('ii', $imagemId, $funcaoId);
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
    return (int) ($row['requires_file_upload'] ?? 1) === 0 && !empty($row['file_uploaded_at'])
        ? 'ATENDIDO'
        : 'NAO_ATENDIDO';
}

function motor_requisitos_avaliar_funcao_imagem(mysqli $conn, int $funcaoImagemId, ?bool $legacyLiberada = null): array
{
    $stmt = $conn->prepare(
        "SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.status,
                f.nome_funcao,
                ico.imagem_nome, ico.obra_id, ico.tipo_imagem, ico.subtipo_id, ico.status_id AS imagem_status_id
           FROM funcao_imagem fi
           JOIN funcao f ON f.idfuncao = fi.funcao_id
           JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
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
    $funcaoId = (int) $context['funcao_id'];
    $imagemId = (int) $context['imagem_id'];
    $requisitos = [];
    $taskUrl = '/ImproovWeb/inicio.php?imagem_id=' . $imagemId;

    if ($funcaoId === 1) {
        $requisitos[] = motor_requisitos_projeto($projectItems, 'briefing', 'Briefing', true, $checklistVersionado);
        $requisitos[] = motor_requisitos_projeto($projectItems, 'kickoff', 'Kickoff', false, $checklistVersionado);
    } elseif ($funcaoId === 8) {
        $requisitos[] = motor_requisitos_projeto($projectItems, 'arquivos_tecnicos', 'Arquivos Tecnicos', true, $checklistVersionado);
    } elseif ($funcaoId === 2) {
        $caderno = motor_requisitos_predecessora($conn, $imagemId, 1);
        $requisitos[] = motor_requisitos_item('caderno_concluido', 'Caderno concluido', 'PRODUCAO', motor_requisitos_estado_predecessora($caderno), true, 'Tarefa Caderno', $caderno ? (int) $caderno['idfuncao_imagem'] : null, $taskUrl);
        $requisitos[] = motor_requisitos_projeto($projectItems, 'arquivos_tecnicos', 'Arquivos Tecnicos', true, $checklistVersionado);
    } elseif ($funcaoId === 3) {
        $modelagem = motor_requisitos_predecessora($conn, $imagemId, 2);
        $requisitos[] = motor_requisitos_item('MODELAGEM_NAO_CONCLUIDA', 'Modelagem concluida', 'PRODUCAO', motor_requisitos_estado_predecessora($modelagem), true, 'Tarefa Modelagem', $modelagem ? (int) $modelagem['idfuncao_imagem'] : null, $taskUrl);
        $requisitos[] = motor_requisitos_item('ARQUIVO_FINAL_MODELAGEM_AUSENTE', 'Arquivo final de Modelagem', 'PRODUCAO', motor_requisitos_estado_arquivo($modelagem), true, 'Upload da Modelagem', $modelagem ? (int) $modelagem['idfuncao_imagem'] : null, $taskUrl);
        $requisitos[] = motor_requisitos_projeto($projectItems, 'referencias_mood', 'Referencias', true, $checklistVersionado);
    } elseif ($funcaoId === 4 || $funcaoId === 7) {
        $isPlanta = $funcaoId === 7 || trim((string) $context['tipo_imagem']) === 'Planta Humanizada';
        if ($isPlanta) {
            $requisitos[] = motor_requisitos_projeto($projectItems, 'arquivos_tecnicos', 'Arquivos Tecnicos', true, $checklistVersionado);
            $subtipoId = (int) ($context['subtipo_id'] ?? 0);
            $requisitos[] = motor_requisitos_item('subtipo_definido', 'Subtipo definido', 'PROJETO', $subtipoId > 0 ? 'ATENDIDO' : 'NAO_ATENDIDO', true, 'Cadastro da imagem', $imagemId, '/ImproovWeb/Dashboard/obra.php?obra_id=' . $obraId);
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
            $requisitos[] = motor_requisitos_item('arquivos_finais_subtipo', 'Arquivos finais do subtipo', 'PRODUCAO', $estadoArquivos, true, 'Composicoes do subtipo', $subtipoId ?: null, $taskUrl);
        } else {
            $composicao = motor_requisitos_predecessora($conn, $imagemId, 3);
            $requisitos[] = motor_requisitos_item('composicao_concluida', 'Composicao concluida', 'PRODUCAO', motor_requisitos_estado_predecessora($composicao), true, 'Tarefa Composicao', $composicao ? (int) $composicao['idfuncao_imagem'] : null, $taskUrl);
            $requisitos[] = motor_requisitos_item('arquivo_composicao', 'Arquivo final de Composicao', 'PRODUCAO', motor_requisitos_estado_arquivo($composicao), true, 'Upload da Composicao', $composicao ? (int) $composicao['idfuncao_imagem'] : null, $taskUrl);
            $requisitos[] = motor_requisitos_projeto($projectItems, 'referencias_mood', 'Referencias', true, $checklistVersionado);
            $requisitos[] = motor_requisitos_projeto($projectItems, 'fotografico', 'Fotografico', true, $checklistVersionado);
            $requisitos[count($requisitos) - 1]['url_acao'] = '/ImproovWeb/Fotografico/index.php?obra_id=' . $obraId;
        }
    } elseif ($funcaoId === 5) {
        $imagemStatusId = (int) ($context['imagem_status_id'] ?? 0);
        $stmtRender = $conn->prepare("SELECT idrender_alta FROM render_alta WHERE imagem_id = ? AND status_id = ? AND LOWER(status) = 'aprovado' AND excluido_em IS NULL ORDER BY idrender_alta DESC LIMIT 1");
        $stmtRender->bind_param('ii', $imagemId, $imagemStatusId);
        $stmtRender->execute();
        $render = $stmtRender->get_result()->fetch_assoc();
        $stmtRender->close();
        $requisitos[] = motor_requisitos_item('render_aprovado', 'Render aprovado', 'PRODUCAO', $render ? 'ATENDIDO' : 'NAO_ATENDIDO', true, 'Render', $render ? (int) $render['idrender_alta'] : null, '/ImproovWeb/Render/index.php');
    } elseif ($funcaoId === 6) {
        $stmtAlt = $conn->prepare("SELECT pli.id FROM pre_alt_liberacao_itens pli JOIN pre_alt_itens pai ON pai.id = pli.pre_alt_item_id WHERE pai.imagem_id = ? ORDER BY pli.id DESC LIMIT 1");
        $stmtAlt->bind_param('i', $imagemId);
        $stmtAlt->execute();
        $liberacao = $stmtAlt->get_result()->fetch_assoc();
        $stmtAlt->close();
        $requisitos[] = motor_requisitos_item('pre_alteracao_liberada', 'Item liberado na Pre-Alteracao', 'PRODUCAO', $liberacao ? 'ATENDIDO' : 'NAO_ATENDIDO', true, 'Pre-Alteracao', $liberacao ? (int) $liberacao['id'] : null, '/ImproovWeb/PreAlteracao/index.php');
    }

    foreach ($requisitos as &$requisito) {
        if (($requisito['origem'] ?? '') === 'Checklist do projeto') {
            $requisito['url_acao'] = '/ImproovWeb/Dashboard/obra.php?obra_id=' . $obraId;
        }
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
