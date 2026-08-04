<?php

return static function (array $payload): array {
    $items = array_slice(is_array($payload['itens'] ?? null) ? $payload['itens'] : [], 0, 5);
    $lines = array_map(static fn(array $item): string => '• ' . flow_connect_tpl_escape((string) ($item['titulo'] ?? 'Arquivo pendente')), $items);
    $total = (int) ($payload['total'] ?? count($items));
    $obras = (int) ($payload['obras_total'] ?? 0);
    $summary = "Você possui *{$total}* tarefa(s) com arquivo pendente" . ($obras > 0 ? " em *{$obras}* obra(s)." : '.');
    $compact = (bool) ($payload['resumo_compacto'] ?? $total > 5);
    $detail = $compact
        ? 'Verifique agora e faça os devidos uploads para liberar a continuidade das tarefas.'
        : "Envie os arquivos para liberar a continuidade das tarefas.\n" . ($lines === [] ? 'Nenhum item pendente.' : implode("\n", $lines));
    $url = trim((string) ($payload['origin_url'] ?? ''));
    $link = preg_match('~^https?://~i', $url) ? '<' . str_replace(['<', '>'], '', $url) . '|Abrir pendências>' : '';
    return ['text' => "📎 *Arquivos pendentes de upload*\n{$summary}\n{$detail}" . ($link !== '' ? "\n{$link}" : '')];
};
