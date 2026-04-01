<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE)
    session_start();

require_once __DIR__ . '/conexao.php';

// Basic auth check: require logged collaborator
$userId = isset($_SESSION['idcolaborador']) ? intval($_SESSION['idcolaborador']) : null;
if (!$userId) {
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

// Aggregate ready_count per obra using same rule as Entregas/listar_entregas.php
$sql = "SELECT o.idobra AS obra_id,
    SUM(CASE WHEN (ei.status = 'Entrega pendente' OR ss.nome_substatus IN ('RVW','DRV'))
        AND ei.status NOT IN ('Entregue no prazo', 'Entregue com atraso', 'Entrega antecipada')
        THEN 1 ELSE 0 END) AS ready_count
FROM entregas e
LEFT JOIN entregas_itens ei ON ei.entrega_id = e.id
LEFT JOIN imagens_cliente_obra i ON ei.imagem_id = i.idimagens_cliente_obra
LEFT JOIN substatus_imagem ss ON ss.id = i.substatus_id
JOIN obra o ON e.obra_id = o.idobra
GROUP BY o.idobra";

$res = $conn->query($sql);
$counts_by_obra = [];
$total_ready = 0;
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $obra = (string) $row['obra_id'];
        $count = intval($row['ready_count']);
        $counts_by_obra[$obra] = $count;
        $total_ready += $count;
    }
}

// ── Pós-Produção: status_pos = 1 = "Não começou" ──────────────────────────────
$res_pos = $conn->query("SELECT COUNT(*) AS cnt FROM pos_producao WHERE status_pos = 1");
$pos_count = ($res_pos) ? intval($res_pos->fetch_assoc()['cnt']) : 0;

// ── Render: items with status 'Em aprovação' ────────────────────────────────────
$res_render = $conn->query("SELECT COUNT(*) AS cnt FROM render_alta WHERE status = 'Em aprovação'");
$render_count = ($res_render) ? intval($res_render->fetch_assoc()['cnt']) : 0;

// ── FlowReview: count of 'Em aprovação' tasks scoped by collaborator's role ─────
//  colaborador_id = 9  → funcoes Finalização (4) + Pós-Produção (5)
//  colaborador_id = 1  → funcoes Caderno (1), Modelagem (2), Composição (3), Filtro/Assets (8)
//  colaborador_id = 21 → all funcoes
$flow_review_count = 0;
if ($userId === 9) {
    $stmt_fr = $conn->prepare(
        "SELECT COUNT(*) AS cnt
         FROM funcao_imagem f
         JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
         JOIN obra o ON o.idobra = i.obra_id
         WHERE f.funcao_id IN (4, 5)
           AND f.status = 'Em aprovação'
           AND o.status_obra = 0"
    );
} elseif ($userId === 1) {
    $stmt_fr = $conn->prepare(
        "SELECT COUNT(*) AS cnt
         FROM funcao_imagem f
         JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
         JOIN obra o ON o.idobra = i.obra_id
         WHERE f.funcao_id IN (1, 2, 3, 8)
           AND f.status = 'Em aprovação'
           AND o.status_obra = 0"
    );
} elseif ($userId === 21) {
    $stmt_fr = $conn->prepare(
        "SELECT COUNT(*) AS cnt
         FROM funcao_imagem f
         JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
         JOIN obra o ON o.idobra = i.obra_id
         WHERE f.status = 'Em aprovação'
           AND o.status_obra = 0"
    );
} else {
    $stmt_fr = null;
}
if ($stmt_fr) {
    $stmt_fr->execute();
    $row_fr = $stmt_fr->get_result()->fetch_assoc();
    $flow_review_count = intval($row_fr['cnt'] ?? 0);
    $stmt_fr->close();
}

$modules = [
    'entregas' => $total_ready,
    'pos_producao' => $pos_count,
    'render' => $render_count,
    'flow_review' => $flow_review_count,
    'obras_updates' => array_reduce($counts_by_obra, function ($acc, $v) {
        return $acc + ($v > 0 ? 1 : 0);
    }, 0)
];

echo json_encode(['ok' => true, 'counts_by_obra' => $counts_by_obra, 'modules' => $modules]);
