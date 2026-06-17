<?php
// get_entrega_item.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/review_cobranca_lib.php';
require_once __DIR__ . '/p00_delivery_helpers.php';
require_once __DIR__ . '/prazo_entrega_helper.php';

improov_p00_ensure_schema($conn);
entregas_ensure_data_recebimento_schema($conn);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID da entrega inválido.']);
    exit;
}

$entrega_id = intval($_GET['id']);

try {
    // buscar informações da entrega
    $sql = "SELECT e.id, e.obra_id, e.status_id, e.data_recebimento, e.data_prevista, e.status, e.data_conclusao, e.observacoes, COALESCE(e.tipo_entrega, 'PADRAO') AS tipo_entrega, s.nome_status as nome_etapa, o.nomenclatura
            FROM entregas e 
            JOIN status_imagem s ON e.status_id = s.idstatus
            JOIN obra o ON e.obra_id = o.idobra
            WHERE e.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $entrega_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        echo json_encode(['error' => 'Entrega não encontrada']);
        exit;
    }
    $entrega = $res->fetch_assoc();

    $itens = [];
    if (($entrega['tipo_entrega'] ?? 'PADRAO') === 'P00') {
        $sql2 = "SELECT
                    v.id,
                    v.imagem_id,
                    v.versao_label AS nome,
                    v.status,
                    NULL AS nome_substatus,
                    NULL AS substatus_id,
                    v.versao_label
                 FROM entregas_p00_versoes v
                 WHERE v.entrega_id = ?
                 ORDER BY
                    CASE
                        WHEN LOWER(TRIM(v.status)) IN ('entrega pendente', 'pendente') THEN 1
                        WHEN LOWER(TRIM(v.status)) LIKE 'entregue%' OR LOWER(TRIM(v.status)) = 'entrega antecipada' THEN 3
                        ELSE 2
                    END ASC,
                    v.imagem_id ASC,
                    v.versao_num ASC,
                    v.id ASC";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param('i', $entrega_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($row = $res2->fetch_assoc()) {
            $itens[] = $row;
        }
        $stmt2->close();
        $latestVersion = improov_p00_fetch_latest_version($conn, $entrega_id);
        $nasPath = trim((string) ($latestVersion['nas_path'] ?? ''));
        if ($nasPath !== '') {
            $entrega['caminho_entrega'] = 'Z:\\2025\\' . ($entrega['nomenclatura'] ?? '') . '\\' . str_replace('/', '\\', ltrim($nasPath, '/'));
        } else {
            $entrega['caminho_entrega'] = 'Z:\\2025\\' . ($entrega['nomenclatura'] ?? '') . '\\03.Models\\Modelagem_Fachada\\Toons\\' . ($latestVersion['versao_label'] ?? 'V1');
        }
    } else {
        // buscar itens da entrega
        $sql2 = "SELECT ei.id, ei.imagem_id, i.imagem_nome AS nome, ei.status, ss.nome_substatus, i.substatus_id
                 FROM entregas_itens ei
                 INNER JOIN imagens_cliente_obra i ON ei.imagem_id = i.idimagens_cliente_obra
                 INNER JOIN substatus_imagem ss ON ss.id = i.substatus_id
                 WHERE ei.entrega_id = ?
                 ORDER BY
                 CASE
                     WHEN LOWER(TRIM(ei.status)) IN ('entrega pendente', 'pendente')
                          OR UPPER(TRIM(ss.nome_substatus)) IN ('RVW', 'DRV') THEN 1
                     WHEN LOWER(TRIM(ei.status)) LIKE 'entregue%'
                          OR LOWER(TRIM(ei.status)) = 'entrega antecipada' THEN 3
                     ELSE 2
                 END ASC,
                 ei.imagem_id ASC,
                 ei.id ASC";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i", $entrega_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($row = $res2->fetch_assoc()) {
            $itens[] = $row;
        }
        $stmt2->close();
    }

    $entrega['itens'] = $itens;
    $reviewBatches = entregas_review_fetch_batches_for_entrega($conn, $entrega_id);
    $entrega['review_batches_enabled'] = entregas_review_schema_ready($conn);
    $entrega['review_batches'] = $reviewBatches;
    $entrega['review_batches_summary'] = [
        'total' => count($reviewBatches),
        'overdue' => count(array_filter($reviewBatches, static function ($batch) {
            return in_array(strtoupper((string) ($batch['billing_status'] ?? '')), ['OVERDUE', 'NOTIFIED'], true);
        })),
        'pending' => count(array_filter($reviewBatches, static function ($batch) {
            return strtoupper((string) ($batch['billing_status'] ?? '')) === 'PENDING';
        })),
        'snoozed' => count(array_filter($reviewBatches, static function ($batch) {
            return strtoupper((string) ($batch['billing_status'] ?? '')) === 'SNOOZED';
        })),
    ];

    echo json_encode($entrega);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
