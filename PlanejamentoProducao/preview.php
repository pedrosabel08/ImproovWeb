<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/planejamento_producao_helper.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_SESSION['logado'])) {
        http_response_code(401);
        throw new RuntimeException('Sessão inválida.');
    }

    $obraId = (int) ($_GET['obra_id'] ?? 0);
    if ($obraId <= 0) {
        http_response_code(422);
        throw new InvalidArgumentException('obra_id é obrigatório.');
    }

    $conn = conectarBanco();
    if (!improov_usuario_pode_acessar_obra($conn, $obraId)) {
        $conn->close();
        http_response_code(403);
        throw new RuntimeException('Sem permissão para acessar esta obra.');
    }

    $pessoas = json_decode((string) ($_GET['pessoas'] ?? '{}'), true);
    if (!is_array($pessoas)) {
        http_response_code(422);
        throw new InvalidArgumentException('pessoas deve ser um objeto JSON.');
    }

    $entregaId = (int) ($_GET['entrega_id'] ?? 0);
    if ($entregaId <= 0) {
        $stmtEntrega = $conn->prepare(
            'SELECT id FROM entregas WHERE obra_id = ? AND status_id = 2 ORDER BY data_recebimento DESC, id DESC LIMIT 1'
        );
        if (!$stmtEntrega) throw new RuntimeException($conn->error);
        $stmtEntrega->bind_param('i', $obraId);
        $stmtEntrega->execute();
        $entregaId = (int) (($stmtEntrega->get_result()->fetch_assoc()['id'] ?? 0));
        $stmtEntrega->close();
    }
    if ($entregaId <= 0) {
        throw new InvalidArgumentException('Nenhuma R00 foi encontrada para esta obra.');
    }

    $entrega = flow_planejamento_contexto_entrega($conn, $entregaId);
    if ((int) $entrega['obra_id'] !== $obraId) {
        throw new InvalidArgumentException('A R00 informada não pertence a esta obra.');
    }
    $opcoes = [
        'data_inicio' => $_GET['inicio'] ?? $entrega['data_recebimento'],
        'data_hoje' => $_GET['hoje'] ?? date('Y-m-d'),
        'data_entrega' => $_GET['entrega'] ?? $entrega['data_prevista'],
        'pessoas_alocadas' => $pessoas,
        'replanejar' => !empty($_GET['replanejar']),
    ];
    $plano = flow_planejamento_carregar_para_interface($conn, $entregaId, $opcoes);
    $plano['entrega_id'] = $entregaId;
    $conn->close();

    echo json_encode($plano, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $erro) {
    echo json_encode(['erro' => $erro->getMessage()], JSON_UNESCAPED_UNICODE);
}
