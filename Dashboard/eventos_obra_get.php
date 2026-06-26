<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/eventos_obra_helper.php';

eventos_obra_require_auth();

$eventoId = isset($_GET['evento_id']) ? (int) $_GET['evento_id'] : 0;
if ($eventoId <= 0) {
    eventos_obra_json(['success' => false, 'error' => 'evento_id invalido'], 400);
}

try {
    eventos_obra_ensure_schema($conn);

    $stmt = $conn->prepare(
        "SELECT e.*, c.nome_colaborador AS responsavel_nome
           FROM eventos_obra e
           LEFT JOIN colaborador c ON c.idcolaborador = e.responsavel_id
          WHERE e.id = ?
            AND e.origem_modulo = 'EVENTOS_OBRA'
            AND e.arquivado_em IS NULL
          LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar evento: ' . $conn->error);
    }

    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$event) {
        eventos_obra_json(['success' => false, 'error' => 'Evento nao encontrado'], 404);
    }

    $obraId = (int) $event['obra_id'];
    eventos_obra_assert_obra_access($conn, $obraId);

    $stmtRefs = $conn->prepare(
        "SELECT id, tipo, url, nome_original, nome_arquivo, caminho, mime, tamanho_bytes,
                hash_sha1, origem, status, observacao, status_sire, criado_em
           FROM evento_obra_referencias
          WHERE evento_id = ?
            AND arquivado_em IS NULL
          ORDER BY criado_em ASC, id ASC"
    );
    if (!$stmtRefs) {
        throw new RuntimeException('Erro ao preparar referencias: ' . $conn->error);
    }

    $stmtRefs->bind_param('i', $eventoId);
    $stmtRefs->execute();
    $refsResult = $stmtRefs->get_result();
    $refs = [];
    while ($row = $refsResult->fetch_assoc()) {
        $refs[] = [
            'id' => (int) $row['id'],
            'tipo' => (string) $row['tipo'],
            'url' => $row['url'],
            'nome_original' => $row['nome_original'],
            'nome_arquivo' => $row['nome_arquivo'],
            'caminho' => $row['caminho'],
            'mime' => $row['mime'],
            'tamanho_bytes' => $row['tamanho_bytes'] !== null ? (int) $row['tamanho_bytes'] : null,
            'hash_sha1' => $row['hash_sha1'],
            'origem' => $row['origem'],
            'status' => $row['status'],
            'observacao' => $row['observacao'],
            'status_sire' => $row['status_sire'],
            'criado_em' => $row['criado_em'],
        ];
    }
    $stmtRefs->close();

    eventos_obra_json([
        'success' => true,
        'can_edit' => eventos_obra_can_edit(),
        'data' => [
            'id' => (int) $event['id'],
            'obra_id' => $obraId,
            'tipo_evento' => (string) ($event['tipo_evento'] ?? ''),
            'data_evento' => $event['data_evento'] ?? null,
            'hora_evento' => $event['hora_evento'] ?? null,
            'participantes' => (string) ($event['participantes'] ?? ''),
            'ata' => (string) ($event['ata'] ?? ''),
            'responsavel_id' => $event['responsavel_id'] !== null ? (int) $event['responsavel_id'] : null,
            'responsavel_nome' => $event['responsavel_nome'] ?? null,
            'created_at' => $event['created_at'] ?? null,
            'updated_at' => $event['updated_at'] ?? null,
            'referencias' => $refs,
        ],
    ]);
} catch (Throwable $e) {
    eventos_obra_json(['success' => false, 'error' => 'Erro interno', 'details' => $e->getMessage()], 500);
}

