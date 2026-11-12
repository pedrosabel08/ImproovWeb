<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/planejamento_alteracao_helper.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (empty($_SESSION['logado'])) {
        throw new RuntimeException('Sessão inválida.', 401);
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET','POST'], true)) {
        throw new RuntimeException('Método não permitido.', 405);
    }
    if (empty($_SESSION['planejamento_alteracao_csrf'])) {
        $_SESSION['planejamento_alteracao_csrf'] = bin2hex(random_bytes(32));
    }
    $dados = $method === 'POST' ? json_decode((string)file_get_contents('php://input'), true) : $_GET;
    if (!is_array($dados)) {
        throw new InvalidArgumentException('Dados inválidos.');
    }
    $id = filter_var($dados['entrega_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        throw new InvalidArgumentException('entrega_id é obrigatório.');
    }
    if ($method === 'POST' && !hash_equals($_SESSION['planejamento_alteracao_csrf'], (string)($dados['csrf_token'] ?? ''))) {
        throw new RuntimeException('Recarregue a página para renovar a autorização da operação.', 403);
    }
    $conn = conectarBanco();
    if ($method === 'GET') {
        try {
            $plano = flow_planejamento_alteracao_carregar($conn, $id);
        } catch (FlowPaClassificacaoPendente $erro) {
            $entrega = flow_pa_entrega($conn, $id);
            if (!empty($entrega['arquivada']) || !improov_usuario_eh_gestor_sidebar($conn)) {
                throw $erro;
            }
            $liberacao = flow_pa_liberacao_classificacao($conn, $entrega);
            $itens = $erro->itens;
            if (!$liberacao) {
                $existentes = flow_pa_niveis_manuais($conn, $entrega, $itens);
                $itens = array_values(array_filter($itens, static fn ($item) => !isset($existentes[$item['entrega_item_id']])));
            }
            $plano = ['classificacao_pendente' => ['liberacao' => $liberacao, 'itens' => $itens]];
        }
    } else {
        $acao = $dados['acao'] ?? '';
        if (!in_array($acao, ['rascunho','confirmar','sugerir','classificar'], true)) {
            throw new InvalidArgumentException('Ação inválida.');
        }
        if ($acao === 'classificar') {
            flow_pa_classificar_imagens($conn, $id, $dados);
            $plano = flow_planejamento_alteracao_carregar($conn, $id);
        } elseif ($acao === 'sugerir') {
            $plano = flow_planejamento_alteracao_carregar($conn, $id);
            if (!$plano['pode_editar']) {
                throw new RuntimeException('Somente a gestão pode planejar.', 403);
            }
            if ((int)($dados['lock_version'] ?? -1) !== (int)$plano['planejamento']['lock_version'] || !hash_equals($plano['fingerprint'], (string)($dados['fingerprint'] ?? '')) || !hash_equals($plano['capacidade']['fingerprint'], (string)($dados['capacidade_fingerprint'] ?? ''))) {
                throw new RuntimeException('O contexto mudou. Recarregue o planejamento.', 409);
            }
            $escopo = flow_pa_itens_editaveis($plano['itens']);
            foreach ($escopo as &$i) {
                $i['nivel_complexidade_snapshot'] = $i['nivel_atual'];
                $i['pontos_snapshot'] = $i['pontos_replanejamento'];
            }
            unset($i);
            $porItem = array_column($escopo, null, 'entrega_item_id');
            $decisoes = $dados['itens'] ?? [];
            foreach ($decisoes as &$d) {
                if (is_array($d) && isset($porItem[(int)($d['entrega_item_id'] ?? 0)]['responsavel_preservado_id'])) {
                    $d['fixado'] = true;
                }
            }
            unset($d);
            $decisoes = flow_pac_decisoes_sugestao($decisoes, $plano['colaboradores']);
            $itens = flow_pa_validar_itens($escopo, $decisoes, $plano['colaboradores'], false);
            $agenda = $plano['capacidade']['agenda'];
            $itens = array_values(array_filter($itens, static function ($i) use (&$agenda) {
                if ($i['pode_editar_responsavel']) {
                    return true;
                }
                $responsavel = $i['responsavel_planejado_id'];
                $dia = $i['fim_planejado'];
                if (isset($agenda[$responsavel][$dia])) {
                    $agenda[$responsavel][$dia]['comprometidos'] += (float)$i['pontos_snapshot'];
                }
                return false;
            }));
            if (!$itens) {
                throw new DomainException('Não há imagens disponíveis para redistribuição automática.');
            }
            $ids = array_map('intval', $dados['colaboradores'] ?? []);
            $pessoas = array_values(array_filter($plano['colaboradores'], fn ($p) => in_array((int)$p['id'], $ids, true)));
            $ordem = $dados['ordem_complexidade'] ?? 'MAIOR';
            if (!is_string($ordem) || !in_array($ordem, ['MAIOR','MENOR'], true)) {
                throw new InvalidArgumentException('Ordem de complexidade inválida.');
            }
            $regras = $dados['responsaveis_por_tipo'] ?? [];
            if (!is_array($regras)) {
                throw new InvalidArgumentException('Regras por tipo inválidas.');
            }
            $prioridade = $dados['prioridade_tipos'] ?? [];
            if (!is_array($prioridade)) {
                throw new InvalidArgumentException('Prioridade de tipos inválida.');
            }
            $sugestao = flow_pac_sugerir($itens, $pessoas, $agenda, $plano['capacidade']['dias'], substr($plano['entrega']['data_prevista'], 0, 10), $ordem, $regras, $prioridade);
            echo json_encode(['success' => true,'sugestao' => $sugestao], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } else {
            $plano = flow_planejamento_alteracao_salvar($conn, $id, $dados, $acao === 'confirmar', isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : null);
        }
    }
    $plano['csrf_token'] = $_SESSION['planejamento_alteracao_csrf'];
    echo json_encode($method === 'GET' ? $plano : ['success' => true,'plano' => $plano], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if ($e instanceof mysqli_sql_exception && in_array($e->getCode(), [1205, 1213], true)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'O planejamento está sendo atualizado. Recarregue e revise antes de tentar novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(in_array($e->getCode(), [401,403,405,409], true) ? $e->getCode() : 422);
    echo json_encode(['success' => false,'erro' => $e->getMessage(),'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
