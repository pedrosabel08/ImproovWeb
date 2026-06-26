<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../Dashboard/eventos_obra_helper.php';

eventos_obra_require_auth();

try {
    eventos_obra_ensure_schema($conn);

    $stmt = $conn->prepare(
        "SELECT
            r.id,
            r.evento_id,
            r.obra_id,
            r.tipo,
            r.url,
            r.nome_original,
            r.nome_arquivo,
            r.caminho,
            r.mime,
            r.tamanho_bytes,
            r.origem,
            r.status,
            r.observacao,
            r.status_sire,
            r.criado_em,
            e.tipo_evento,
            e.data_evento,
            e.hora_evento,
            e.participantes,
            o.nomenclatura AS obra_nomenclatura
         FROM evento_obra_referencias r
         INNER JOIN eventos_obra e ON e.id = r.evento_id
         INNER JOIN obra o ON o.idobra = r.obra_id
         WHERE r.status_sire = 'pendente'
           AND r.arquivado_em IS NULL
           AND e.arquivado_em IS NULL
         ORDER BY r.criado_em DESC, r.id DESC
         LIMIT 300"
    );
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar fila: ' . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $refs = [];
    while ($row = $result->fetch_assoc()) {
        $obraId = (int) $row['obra_id'];
        if (function_exists('improov_usuario_pode_acessar_obra') && !improov_usuario_pode_acessar_obra($conn, $obraId)) {
            continue;
        }
        $refs[] = [
            'id' => (int) $row['id'],
            'evento_id' => (int) $row['evento_id'],
            'obra_id' => $obraId,
            'obra_nomenclatura' => $row['obra_nomenclatura'],
            'tipo' => $row['tipo'],
            'url' => $row['url'],
            'nome_original' => $row['nome_original'],
            'nome_arquivo' => $row['nome_arquivo'],
            'caminho' => $row['caminho'],
            'mime' => $row['mime'],
            'tamanho_bytes' => $row['tamanho_bytes'] !== null ? (int) $row['tamanho_bytes'] : null,
            'origem' => $row['origem'],
            'status' => $row['status'],
            'observacao' => $row['observacao'],
            'status_sire' => $row['status_sire'],
            'criado_em' => $row['criado_em'],
            'tipo_evento' => $row['tipo_evento'],
            'data_evento' => $row['data_evento'],
            'hora_evento' => $row['hora_evento'],
            'participantes' => $row['participantes'],
        ];
    }
    $stmt->close();

    eventos_obra_json([
        'success' => true,
        'data' => $refs,
        'total' => count($refs),
    ]);
} catch (Throwable $e) {
    eventos_obra_json(['success' => false, 'error' => 'Erro interno', 'details' => $e->getMessage()], 500);
}

