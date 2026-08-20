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

    // Cenário de validação explicitamente acordado para RAY_BRH; nenhuma data é gravada.
    $padrao116 = $obraId === 116;
    $opcoes = [
        'data_inicio' => $_GET['inicio'] ?? ($padrao116 ? '2026-08-12' : date('Y-m-d')),
        'data_hoje' => $_GET['hoje'] ?? ($padrao116 ? '2026-08-20' : date('Y-m-d')),
        'data_entrega' => $_GET['entrega'] ?? ($padrao116 ? '2026-10-15' : null),
        'pessoas_alocadas' => $pessoas,
    ];
    $plano = flow_planejamento_planejar_obra($conn, $obraId, $opcoes);
    $conn->close();

    echo json_encode($plano, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $erro) {
    echo json_encode(['erro' => $erro->getMessage()], JSON_UNESCAPED_UNICODE);
}
