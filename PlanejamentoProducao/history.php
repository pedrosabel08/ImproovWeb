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
    $entregaId = (int) ($_GET['entrega_id'] ?? 0);
    $versaoId = (int) ($_GET['versao_id'] ?? 0);
    if ($entregaId <= 0 || $versaoId <= 0) throw new InvalidArgumentException('Versão do plano inválida.');
    $conn = conectarBanco();
    $entrega = flow_planejamento_contexto_entrega($conn, $entregaId);
    if (!improov_usuario_pode_acessar_obra($conn, (int) $entrega['obra_id'])) throw new RuntimeException('Sem permissão para acessar esta R00.');
    $stmt = $conn->prepare(
        'SELECT v.*, c.nome_colaborador AS confirmado_por
           FROM entrega_planejamento_producao p
           JOIN entrega_planejamento_versao v ON v.planejamento_id = p.id
      LEFT JOIN colaborador c ON c.idcolaborador = v.confirmado_por_colaborador_id
          WHERE p.entrega_id = ? AND v.id = ? LIMIT 1'
    );
    if (!$stmt) throw new RuntimeException($conn->error);
    $stmt->bind_param('ii', $entregaId, $versaoId);
    $stmt->execute();
    $versao = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    if (!$versao) throw new InvalidArgumentException('Versão não encontrada para esta R00.');
    $versao['snapshot'] = json_decode((string) $versao['snapshot_json'], true);
    unset($versao['snapshot_json'], $versao['contexto_fingerprint_json']);
    echo json_encode(['success' => true, 'versao' => $versao], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $erro) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $erro->getMessage()], JSON_UNESCAPED_UNICODE);
}
