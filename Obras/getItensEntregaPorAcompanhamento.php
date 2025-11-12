<?php
// Retorna itens (nomes de imagens) entregues para um acompanhamento_email (quando originado de uma entrega)
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

$acompId = isset($_GET['acomp_id']) ? intval($_GET['acomp_id']) : 0;
if ($acompId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'acomp_id inválido']);
    exit;
}

try {
    // 1) Se a coluna entrega_id já existir e estiver preenchida, usa direto
    $sqlCheck = "SELECT entrega_id FROM acompanhamento_email WHERE idacompanhamento_email = ?";
    $stmt0 = $conn->prepare($sqlCheck);
    $stmt0->bind_param('i', $acompId);
    $stmt0->execute();
    $res0 = $stmt0->get_result();
    $entregaId = null;
    if ($res0 && $res0->num_rows > 0) {
        $row0 = $res0->fetch_assoc();
        if (!is_null($row0['entrega_id'])) {
            $entregaId = intval($row0['entrega_id']);
        }
    }

    // 2) Se não encontrou via FK, tenta resolver via feed_atualizacoes (fallback)
    if (!$entregaId) {
        // Mapeia acompanhamento -> entrega usando obra_id + data e feed_atualizacoes
        // Preferência: igual ao assunto; fallback: assunto contido na descrição; por fim, mais recente no dia
        $sqlEntrega = "
            SELECT fa.entrega_id
            FROM acompanhamento_email ae
            JOIN entregas e ON e.obra_id = ae.obra_id
            JOIN feed_atualizacoes fa ON fa.entrega_id = e.id
            WHERE ae.idacompanhamento_email = ?
              AND DATE(fa.data_evento) = DATE(ae.data)
              AND fa.tipo_evento LIKE 'entrega%'
            ORDER BY
              (fa.descricao = ae.assunto) DESC,
              (LOCATE(ae.assunto, fa.descricao) > 0) DESC,
              fa.data_evento DESC
            LIMIT 1
        ";

        $stmt = $conn->prepare($sqlEntrega);
        $stmt->bind_param('i', $acompId);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            echo json_encode(['success' => true, 'entrega_id' => null, 'itens' => [], 'message' => 'Nenhuma entrega relacionada encontrada.']);
            exit;
        }

        $row = $res->fetch_assoc();
        $entregaId = intval($row['entrega_id']);
    }

    // Busca nomes das imagens da entrega
    // Return only items that were actually delivered. We consider an item delivered when
    // ei.status starts with 'Entregue' or when data_entregue is not null.
        // Return only items that were actually delivered.
        // Treat '0000-00-00' as not delivered (some rows store zero-date instead of NULL).
        $sqlItens = "
                SELECT i.imagem_nome AS nome
                FROM entregas_itens ei
                INNER JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = ei.imagem_id
                WHERE ei.entrega_id = ?
                    AND (
                            ei.status LIKE 'Entregue%'
                            OR (ei.data_entregue IS NOT NULL AND ei.data_entregue <> '0000-00-00')
                    )
                ORDER BY i.imagem_nome
        ";
    $stmt2 = $conn->prepare($sqlItens);
    $stmt2->bind_param('i', $entregaId);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    $itens = [];
    while ($r = $res2->fetch_assoc()) {
        $itens[] = $r['nome'];
    }

    echo json_encode(['success' => true, 'entrega_id' => $entregaId, 'itens' => $itens]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
