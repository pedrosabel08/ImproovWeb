<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/eventos_obra_helper.php';

eventos_obra_require_auth();

$obraId = isset($_GET['obra_id']) ? (int) $_GET['obra_id'] : 0;
eventos_obra_assert_obra_access($conn, $obraId);

try {
    eventos_obra_ensure_schema($conn);

    $stmt = $conn->prepare(
        "SELECT
            e.id,
            e.obra_id,
            e.tipo_evento,
            e.data_evento,
            e.hora_evento,
            e.participantes,
            e.ata,
            e.responsavel_id,
            c.nome_colaborador AS responsavel_nome,
            e.created_at,
            e.updated_at,
            COUNT(r.id) AS referencias_qtd
         FROM eventos_obra e
         LEFT JOIN colaborador c ON c.idcolaborador = e.responsavel_id
         LEFT JOIN evento_obra_referencias r
           ON r.evento_id = e.id
          AND r.arquivado_em IS NULL
         WHERE e.obra_id = ?
           AND e.origem_modulo = 'EVENTOS_OBRA'
           AND e.arquivado_em IS NULL
         GROUP BY e.id
         ORDER BY e.data_evento DESC, e.hora_evento DESC, e.id DESC"
    );
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar listagem: ' . $conn->error);
    }

    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $result = $stmt->get_result();
    $eventos = [];

    while ($row = $result->fetch_assoc()) {
        $ata = (string) ($row['ata'] ?? '');
        $resumo = trim(preg_replace('/\s+/', ' ', $ata));
        if (function_exists('mb_substr')) {
            $resumo = mb_substr($resumo, 0, 220, 'UTF-8');
        } else {
            $resumo = substr($resumo, 0, 220);
        }

        $eventos[] = [
            'id' => (int) $row['id'],
            'obra_id' => (int) $row['obra_id'],
            'tipo_evento' => (string) ($row['tipo_evento'] ?? ''),
            'data_evento' => $row['data_evento'] ?? null,
            'hora_evento' => $row['hora_evento'] ?? null,
            'participantes' => (string) ($row['participantes'] ?? ''),
            'responsavel_id' => $row['responsavel_id'] !== null ? (int) $row['responsavel_id'] : null,
            'responsavel_nome' => $row['responsavel_nome'] ?? null,
            'referencias_qtd' => (int) ($row['referencias_qtd'] ?? 0),
            'resumo' => $resumo,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
    $stmt->close();

    eventos_obra_json([
        'success' => true,
        'can_edit' => eventos_obra_can_edit(),
        'data' => $eventos,
    ]);
} catch (Throwable $e) {
    eventos_obra_json(['success' => false, 'error' => 'Erro interno', 'details' => $e->getMessage()], 500);
}

