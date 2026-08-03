<?php

return static function (array $payload): array {
    $items = array_slice(is_array($payload['itens'] ?? null) ? $payload['itens'] : [], 0, 5);
    $lines = array_map(static fn(array $item): string => '• ' . flow_connect_tpl_escape((string) ($item['titulo'] ?? 'Arquivo pendente')), $items);
    $total = (int) ($payload['total'] ?? count($items));
    $obras = (int) ($payload['obras_total'] ?? 0);
    $summary = "Você possui *{$total}* imagem(ns) aguardando envio de arquivo" . ($obras > 0 ? " em *{$obras}* obra(s)." : '.');
    return ['text' => "📎 *Arquivos pendentes de upload*\n{$summary}\nEnvie os arquivos para liberar a continuidade das tarefas.\n" . ($lines === [] ? 'Nenhum item pendente.' : implode("\n", $lines))];
};
