<?php
function financeiro_registrar_parcela(mysqli $conn, string $mode): void
{
    $input = pagamento_request_json();
    $date = (string)($input['data_pagamento'] ?? date('Y-m-d'));
    if (preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $date, $m)) $date = "$m[3]-$m[2]-$m[1]";
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) pagamento_json(['success' => false, 'error' => 'Data inválida.'], 422);
    try {
        $conn->begin_transaction();
        $ids = $input['ids'] ?? [(int)($input['idfuncao_imagem'] ?? $input['funcao_imagem_id'] ?? 0)];
        if (!is_array($ids)) throw new InvalidArgumentException('IDs inválidos.');
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        sort($ids);
        if (!$ids && !empty($input['colaborador_id']) && $mode === 'parcial') {
            $rows = custos_query($conn, 'SELECT idfuncao_imagem FROM funcao_imagem WHERE colaborador_id=? AND data_pagamento=? AND funcao_id=4 ORDER BY idfuncao_imagem', 'is', [(int)$input['colaborador_id'], $date]);
            $ids = array_column($rows, 'idfuncao_imagem');
        }
        if (!$ids) throw new InvalidArgumentException('Nenhuma finalização selecionada.');
        $created = [];
        $skipped = [];
        foreach ($ids as $id) {
            $r = custos_query($conn, "SELECT fi.*, 'funcao_imagem' origem, fi.idfuncao_imagem origem_id FROM funcao_imagem fi WHERE idfuncao_imagem=? FOR UPDATE", 'i', [$id])[0] ?? null;
            if (!$r || (int)$r['funcao_id'] !== 4) throw new InvalidArgumentException('Tarefa não é Finalização.');
            if (!empty($input['colaborador_id']) && (int)$input['colaborador_id'] !== (int)$r['colaborador_id']) throw new InvalidArgumentException('Colaborador incompatível.');
            // Manual historical registration is explicit; never rewrites previous entries.
            $out = financeiro_lancar($conn, $r, (int)$r['colaborador_id'], (int)$dt->format('m'), (int)$dt->format('Y'), pagamento_current_user_id(), $mode, $date);
            if (!empty($out['skipped'])) $skipped[] = $out;
            else $created[] = $out;
        }
        $conn->commit();
        pagamento_json(['success' => true, 'created' => $mode === 'parcial' ? count($created) : $created, 'skipped' => $mode === 'parcial' ? count($skipped) : $skipped, 'errors' => []]);
    } catch (InvalidArgumentException | DomainException $e) {
        $conn->rollback();
        pagamento_json(['success' => false, 'error' => $e->getMessage()], 422);
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Parcela: ' . $e->getMessage());
        pagamento_json(['success' => false, 'error' => 'Falha ao registrar parcela. Nenhum item alterado.'], 500);
    }
}
