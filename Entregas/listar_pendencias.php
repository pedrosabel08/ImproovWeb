<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/pendencias_entrega_helper.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

try {
    entregas_pendencias_ensure_schema($conn);

    $sql = "SELECT
            p.id,
            p.obra_id,
            p.status_id,
            p.imagem_id,
            p.funcao_imagem_id,
            p.historico_id,
            p.motivo,
            p.criada_em,
            o.nomenclatura,
            s.nome_status AS nome_etapa,
            i.imagem_nome,
            f.nome_funcao
        FROM entregas_pendencias p
        JOIN obra o ON o.idobra = p.obra_id
        JOIN status_imagem s ON s.idstatus = p.status_id
        JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = p.imagem_id
        LEFT JOIN funcao_imagem fi ON fi.idfuncao_imagem = p.funcao_imagem_id
        LEFT JOIN funcao f ON f.idfuncao = fi.funcao_id
        WHERE p.status = 'aberta'
          AND o.status_obra = 0
        ORDER BY p.criada_em DESC, p.id DESC";

    $res = $conn->query($sql);
    if (!$res) {
        throw new RuntimeException($conn->error);
    }

    $pendencias = [];
    $pairs = [];
    while ($row = $res->fetch_assoc()) {
        $obraId = (int) $row['obra_id'];
        $statusId = (int) $row['status_id'];
        $key = $obraId . ':' . $statusId;
        $pairs[$key] = ['obra_id' => $obraId, 'status_id' => $statusId];

        $pendencias[] = [
            'id' => (int) $row['id'],
            'obra_id' => $obraId,
            'status_id' => $statusId,
            'imagem_id' => (int) $row['imagem_id'],
            'funcao_imagem_id' => (int) $row['funcao_imagem_id'],
            'historico_id' => isset($row['historico_id']) ? (int) $row['historico_id'] : null,
            'motivo' => $row['motivo'],
            'criada_em' => $row['criada_em'],
            'nomenclatura' => $row['nomenclatura'],
            'nome_etapa' => $row['nome_etapa'],
            'imagem_nome' => $row['imagem_nome'],
            'nome_funcao' => $row['nome_funcao'],
            'entregas_existentes' => [],
        ];
    }

    $entregasByPair = [];
    if (!empty($pairs)) {
        $stmtEnt = $conn->prepare("SELECT e.id, e.obra_id, e.status_id, e.data_recebimento, e.data_prevista, COUNT(ei.id) AS total_itens
            FROM entregas e
            LEFT JOIN entregas_itens ei ON ei.entrega_id = e.id
            WHERE e.obra_id = ?
              AND e.status_id = ?
              AND (e.arquivada IS NULL OR e.arquivada = 0)
              AND (e.tipo_entrega IS NULL OR e.tipo_entrega <> 'P00')
            GROUP BY e.id
            ORDER BY e.id DESC");

        if ($stmtEnt) {
            foreach ($pairs as $key => $pair) {
                $stmtEnt->bind_param('ii', $pair['obra_id'], $pair['status_id']);
                $stmtEnt->execute();
                $resEnt = $stmtEnt->get_result();
                $entregasByPair[$key] = [];
                while ($resEnt && ($ent = $resEnt->fetch_assoc())) {
                    $entregasByPair[$key][] = [
                        'id' => (int) $ent['id'],
                        'data_recebimento' => $ent['data_recebimento'],
                        'data_prevista' => $ent['data_prevista'],
                        'total_itens' => (int) $ent['total_itens'],
                    ];
                }
            }
            $stmtEnt->close();
        }
    }

    foreach ($pendencias as &$pendencia) {
        $key = $pendencia['obra_id'] . ':' . $pendencia['status_id'];
        $pendencia['entregas_existentes'] = $entregasByPair[$key] ?? [];
    }
    unset($pendencia);

    echo json_encode(['success' => true, 'pendencias' => $pendencias]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
