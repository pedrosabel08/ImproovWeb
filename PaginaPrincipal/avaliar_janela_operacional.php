<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/janela_operacional_helper.php';

if (empty($_SESSION['logado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'code' => 'UNAUTHENTICATED', 'message' => 'Sessao invalida.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!flow_janela_schema_disponivel($conn)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'code' => 'JANELA_SCHEMA_INDISPONIVEL', 'message' => 'A migration da Janela Operacional V1 ainda nao foi aplicada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tarefaId = (int) ($_GET['funcao_imagem_id'] ?? 0);
$previsao = flow_janela_data_valida($_GET['previsao'] ?? null);
$agrupar = !empty($_GET['iniciar_modelagem_composicao']);
if ($tarefaId <= 0 || !$previsao) {
    http_response_code(422);
    echo json_encode(['success' => false, 'code' => 'INVALID_INPUT', 'message' => 'Informe a tarefa e uma previsao valida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $unidade = flow_janela_resolver_unidade($conn, $tarefaId, false, $agrupar);
    $ciclo = flow_janela_ciclo_ativo_por_tarefa($conn, $tarefaId, false);
    if ($ciclo) {
        $prazo = flow_janela_prazo_necessario($conn, $unidade);
        $aplica = !empty($ciclo['aplica_regra_snapshot']);
        $estado = flow_janela_classificar($previsao, $aplica ? $ciclo['limite_data_atual'] : null, $prazo['data']);
        $avaliacao = [
            'aplica_regra' => $aplica,
            'perfil_codigo' => $ciclo['perfil_codigo_snapshot'],
            'perfil_nome' => $ciclo['perfil_nome_snapshot'],
            'limite_dias_uteis' => $ciclo['limite_dias_uteis_snapshot'] === null ? null : (int) $ciclo['limite_dias_uteis_snapshot'],
            'inicio_data' => substr($ciclo['inicio_em'], 0, 10),
            'limite_data' => $ciclo['limite_data_atual'],
            'limite_data_original' => $ciclo['limite_data_original'],
            'prazo_necessario' => $prazo['data'],
            'previsao' => $previsao,
            'estado' => $estado,
            'exige_justificativa' => $estado !== FLOW_JANELA_ESTADO_NORMAL,
            'ciclo_id' => (int) $ciclo['id'],
            'situacao' => $ciclo['situacao'],
            'unidade' => [
                'chave' => $unidade['chave_referencia'],
                'tipo' => $unidade['tipo_unidade'],
                'membros' => array_map(static fn(array $m): int => (int) $m['idfuncao_imagem'], $unidade['membros']),
            ],
        ];
    } else {
        $avaliacao = flow_janela_avaliar($conn, $unidade, $previsao);
        unset($avaliacao['perfil']);
    }
    echo json_encode(['success' => true, 'evaluation' => $avaliacao, 'reasons' => flow_janela_motivos_ativos($conn)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(422);
    echo json_encode(['success' => false, 'code' => 'EVALUATION_FAILED', 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
}
$conn->close();
