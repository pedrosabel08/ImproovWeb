<?php

/**
 * Centraliza a fila nativa do Flow Review para que consumidores operacionais
 * usem a mesma regra que a lista de pendências. A decisão continua baseada
 * nas regras vigentes por função e etapa; este helper não cria pendências.
 */

if (!function_exists('flow_review_aprovacao_ids')) {
    function flow_review_aprovacao_ids(array $tarefa): array
    {
        $funcaoId = (int) ($tarefa['funcao_id'] ?? 0);
        $status = mb_strtolower(trim((string) ($tarefa['status'] ?? '')), 'UTF-8');
        $imagemStatusId = (int) ($tarefa['imagem_status_id'] ?? 0);

        if ($status === 'aguardando direção' || $status === 'aguardando direcao') {
            return in_array($funcaoId, [4, 5, 6], true) ? [9, 31] : [];
        }
        if ($status !== 'em aprovação' && $status !== 'em aprovacao') {
            return [];
        }
        if (in_array($funcaoId, [1, 2, 3, 8], true)) {
            return [1, 21];
        }
        if ($funcaoId === 4) {
            return $imagemStatusId === 1 ? [9, 31] : [1, 21];
        }
        if ($funcaoId === 6) {
            return [1, 21];
        }
        if ($funcaoId === 5) {
            return [21, 9, 31];
        }
        return [];
    }
}

if (!function_exists('flow_review_aprovacao_destinatarios')) {
    function flow_review_aprovacao_destinatarios(mysqli $conn, array $tarefa): array
    {
        $ids = flow_review_aprovacao_ids($tarefa);
        if (!$ids) return [];

        $marks = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $conn->prepare("SELECT idcolaborador, nome_colaborador FROM colaborador WHERE ativo = 1 AND idcolaborador IN ($marks)");
        if (!$stmt) return [];
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $byId = [];
        foreach ($rows as $row) $byId[(int) $row['idcolaborador']] = $row;
        $ordered = [];
        foreach ($ids as $id) {
            if (!empty($byId[$id])) {
                $ordered[] = ['id' => (int) $id, 'nome' => (string) $byId[$id]['nome_colaborador']];
            }
        }
        return $ordered;
    }
}
