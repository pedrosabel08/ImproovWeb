<?php

header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/conexaoMain.php';
require_once __DIR__ . '/Dashboard/onboarding_helpers.php';
require_once __DIR__ . '/Entregas/p00_delivery_helpers.php';
require_once __DIR__ . '/Entregas/pendencias_entrega_helper.php';
require_once __DIR__ . '/helpers/flow_review_eligibility_helper.php';

// Basic auth check: require logged collaborator
$userId = isset($_SESSION['idcolaborador']) ? intval($_SESSION['idcolaborador']) : null;
if (!$userId) {
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

// Aggregate ready_count per obra using same rule as Entregas/listar_entregas.php
$sql = "SELECT o.idobra AS obra_id,
    SUM(CASE WHEN (ei.status = 'Entrega pendente')
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

$p00HandoffCounts = improov_p00_fetch_pending_handoff_counts($conn);
foreach ($p00HandoffCounts as $obraId => $handoffCount) {
    // $counts_by_obra[$obraId] = intval($counts_by_obra[$obraId] ?? 0) + intval($handoffCount);
    $total_ready += intval($handoffCount);
}

foreach (array_keys($counts_by_obra) as $obraId) {
    if (!improov_usuario_pode_acessar_obra($conn, (int) $obraId)) {
        unset($counts_by_obra[$obraId]);
    }
}
$total_ready = array_sum(array_map('intval', $counts_by_obra));

$pendencias_entrega_by_obra = contar_pendencias_entrega($conn);
foreach (array_keys($pendencias_entrega_by_obra) as $obraId) {
    if (!improov_usuario_pode_acessar_obra($conn, (int) $obraId)) {
        unset($pendencias_entrega_by_obra[$obraId]);
    }
}
$total_pendencias_entrega = array_sum(array_map('intval', $pendencias_entrega_by_obra));

$onboarding_progress = dashboard_get_onboarding_progress($conn);
$onboarding_pending_total = 0;
foreach ($onboarding_progress as $obra_progress) {
    $onboarding_pending_total += max(0, (int) ($obra_progress['pending_items'] ?? 0));
}

// ── Pós-Produção: status_pos = 1 = "Não começou" ──────────────────────────────
$pos_count = 0;
if ($userId === 9 || $userId === 21) { // Apenas para colaboradores administradores (21)
    $res_pos = $conn->query("SELECT COUNT(*) AS cnt FROM pos_producao p JOIN render_alta ra on ra.idrender_alta = p.render_id WHERE status_pos = 1 AND ra.status IN ('Aprovado', 'Em aprovação')");
    $pos_count = ($res_pos) ? intval($res_pos->fetch_assoc()['cnt']) : 0;
}
// ── Render: items with status 'Em aprovação' ────────────────────────────────────
// userId 1 e 9 veem todos; demais veem apenas seus próprios
if ($userId === 21 || $userId === 9) {
    $res_render = $conn->query("SELECT COUNT(*) AS cnt FROM render_alta WHERE status = 'Em aprovação'");
} else {
    $stmt_render = $conn->prepare("SELECT COUNT(*) AS cnt FROM render_alta WHERE status = 'Em aprovação' AND responsavel_id = ?");
    $stmt_render->bind_param('i', $userId);
    $stmt_render->execute();
    $res_render = $stmt_render->get_result();
}
$render_count = ($res_render) ? intval($res_render->fetch_assoc()['cnt']) : 0;

// ── FlowReview: count of 'Em aprovação' tasks scoped by collaborator's role ─────
//  colaborador_id = 9  → funcoes Finalização (4) + Pós-Produção (5)
//  colaborador_id = 1  → funcoes Caderno (1), Modelagem (2), Composição (3), Filtro/Assets (8)
//  colaborador_id = 21 → all funcoes
$flow_review_count = 0;
$flowReviewHoldApprovalBlock = flow_review_hold_approval_block_sql('f');
if ($userId === 9) {
    $stmt_fr = $conn->prepare(
        "SELECT COUNT(*) AS cnt
         FROM funcao_imagem f
         JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
         JOIN obra o ON o.idobra = i.obra_id
         WHERE f.funcao_id IN (4, 5)
            AND (f.status = 'Em aprovação' OR (f.status = 'HOLD' AND $flowReviewHoldApprovalBlock))
           AND o.status_obra = 0"
    );
} elseif ($userId === 1) {
    $stmt_fr = $conn->prepare(
        "SELECT COUNT(*) AS cnt
         FROM funcao_imagem f
         JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
         JOIN obra o ON o.idobra = i.obra_id
         WHERE f.funcao_id IN (1, 2, 3, 8)
            AND (f.status = 'Em aprovação' OR (f.status = 'HOLD' AND $flowReviewHoldApprovalBlock))
           AND o.status_obra = 0"
    );
} elseif ($userId === 21) {
    $stmt_fr = $conn->prepare(
        "SELECT COUNT(*) AS cnt
         FROM funcao_imagem f
         JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
         JOIN obra o ON o.idobra = i.obra_id
          WHERE (f.status = 'Em aprovação' OR (f.status = 'HOLD' AND $flowReviewHoldApprovalBlock))
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

$pre_alt_analise_count = 0;
$preAltTablesReady = false;
$resPreAltTables = $conn->query(
    "SELECT COUNT(*) AS cnt
     FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name IN ('pre_alt_lote', 'pre_alt_itens')"
);
if ($resPreAltTables) {
    $preAltTablesReady = intval($resPreAltTables->fetch_assoc()['cnt'] ?? 0) === 2;
}

if ($preAltTablesReady) {
    if ($userId == 1) {
        $res_pa = $conn->query(
            "SELECT COUNT(*) AS cnt
             FROM pre_alt_lote
             WHERE status IN ('EM_TRIAGEM', 'AGUARDANDO_CLIENTE')"
        );
        $pre_alt_analise_count = ($res_pa) ? intval($res_pa->fetch_assoc()['cnt']) : 0;
    }

    if ($userId == 21) {
        $res_pa = $conn->query(
            "SELECT COUNT(*) AS cnt
             FROM pre_alt_lote
             WHERE status IN ('PRONTO_PLANEJAMENTO', 'EM_TRIAGEM')"
        );
        $pre_alt_analise_count = ($res_pa) ? intval($res_pa->fetch_assoc()['cnt']) : 0;
    }
} else {
    if ($userId == 1) {
        $res_pa = $conn->query(
            "SELECT COUNT(DISTINCT obra_id) AS cnt FROM imagens_cliente_obra WHERE substatus_id = 10"
        );
        $pre_alt_analise_count = ($res_pa) ? intval($res_pa->fetch_assoc()['cnt']) : 0;
    }

    if ($userId == 21) {
        $res_pa = $conn->query(
            "SELECT COUNT(DISTINCT obra_id) AS cnt FROM imagens_cliente_obra WHERE substatus_id = 12"
        );
        $pre_alt_analise_count = ($res_pa) ? intval($res_pa->fetch_assoc()['cnt']) : 0;
    }
}

// Notificações internas pendentes, agrupadas pelo módulo de origem.
// O bloco é opcional para manter a sidebar compatível com bases sem a migração de notificações.
$notification_modules = [];
$notificationUserId = (int) ($_SESSION['idusuario'] ?? 0);
$notificationTables = [];
$notificationTablesResult = $conn->query(
    "SELECT table_name AS table_name
     FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name IN ('notificacoes', 'notificacoes_destinatarios', 'notificacoes_modulos')"
);
if ($notificationTablesResult) {
    while ($tableRow = $notificationTablesResult->fetch_assoc()) {
        // Alguns drivers devolvem as colunas do information_schema em maiúsculas.
        $tableName = $tableRow['table_name'] ?? $tableRow['TABLE_NAME'] ?? null;
        if ($tableName !== null) {
            $notificationTables[] = $tableName;
        }
    }
}

$notificationModuleColumn = false;
$notificationColumnResult = $conn->query(
    "SELECT 1
     FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'notificacoes'
       AND column_name = 'modulo_id'
     LIMIT 1"
);
if ($notificationColumnResult) {
    $notificationModuleColumn = $notificationColumnResult->num_rows > 0;
}
$notificationPublicationClause = "";
$notificationStatusColumnResult = $conn->query(
    "SELECT 1
     FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'notificacoes'
       AND column_name = 'status_publicacao'
     LIMIT 1"
);
if ($notificationStatusColumnResult && $notificationStatusColumnResult->num_rows > 0) {
    $notificationPublicationClause = "
                          AND COALESCE(NULLIF(n.status_publicacao, ''), 'PUBLICADA') = 'PUBLICADA'";
}

if (
    $notificationUserId > 0
    && $notificationModuleColumn
    && count(array_intersect($notificationTables, ['notificacoes', 'notificacoes_destinatarios', 'notificacoes_modulos'])) === 3
) {
    $notificationSql = "SELECT
                            m.codigo AS modulo_codigo,
                            m.nome AS modulo_nome,
                            m.url AS modulo_url,
                            m.icone AS modulo_icone,
                            COUNT(*) AS total
                        FROM notificacoes n
                        JOIN notificacoes_destinatarios d ON d.notificacao_id = n.id
                        LEFT JOIN notificacoes_modulos m ON m.id = n.modulo_id
                        WHERE d.usuario_id = ?
                          AND n.ativa = 1
                          AND d.dispensado_em IS NULL
                          AND (n.inicio_em IS NULL OR n.inicio_em <= NOW())
                          AND (n.fim_em IS NULL OR n.fim_em >= NOW())" . $notificationPublicationClause . "
                          AND (
                              (n.exige_confirmacao = 1 AND d.confirmado_em IS NULL)
                              OR (n.exige_confirmacao = 0 AND d.visto_em IS NULL)
                          )
                        GROUP BY m.id, m.codigo, m.nome, m.url, m.icone
                        ORDER BY total DESC, m.nome ASC";
    $notificationStmt = $conn->prepare($notificationSql);
    if ($notificationStmt) {
        $notificationStmt->bind_param('i', $notificationUserId);
        if ($notificationStmt->execute()) {
            $notificationResult = $notificationStmt->get_result();
            while ($notificationResult && ($notificationRow = $notificationResult->fetch_assoc())) {
                $notificationCode = trim((string) ($notificationRow['modulo_codigo'] ?? ''));
                $notification_modules[] = [
                    'codigo' => $notificationCode !== '' ? $notificationCode : 'GERAL',
                    'nome' => trim((string) ($notificationRow['modulo_nome'] ?? '')) ?: 'Notificações gerais',
                    'url' => trim((string) ($notificationRow['modulo_url'] ?? '')) ?: 'notificacoes',
                    'icone' => trim((string) ($notificationRow['modulo_icone'] ?? '')) ?: 'fa-bell',
                    'total' => (int) ($notificationRow['total'] ?? 0),
                ];
            }
        }
        $notificationStmt->close();
    }
}

$modules = [
    'entregas' => $total_ready,
    'entregas_pendencias' => $total_pendencias_entrega,
    'onboarding' => $onboarding_pending_total,
    'pos_producao' => $pos_count,
    'render' => $render_count,
    'flow_review' => $flow_review_count,
    'pre_alt_analise' => $pre_alt_analise_count,
    'obras_updates' => array_reduce($counts_by_obra, function ($acc, $v) {
        return $acc + ($v > 0 ? 1 : 0);
    }, 0)
];

echo json_encode([
    'ok' => true,
    'counts_by_obra' => $counts_by_obra,
    'modules' => $modules,
    'notification_modules' => $notification_modules,
]);
