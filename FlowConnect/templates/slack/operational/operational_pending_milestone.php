<?php
return static function (array $payload): array {
    $title = flow_connect_tpl_escape((string) ($payload['titulo'] ?? 'Pendência'));
    $labels = ['WARNING_90' => 'próxima do vencimento', 'EXPIRED' => 'vencida', 'OVERDUE_100' => '100% atrasada', 'OVERDUE_200' => '200% atrasada'];
    $label = $labels[(string) ($payload['milestone'] ?? '')] ?? 'em atraso';
    return ['text' => "⏱️ Pendência {$label}: *{$title}*"];
};
