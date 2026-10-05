<?php
require_once __DIR__ . '/../Dashboard/image_import_helpers.php';

$checks = 0;
function same($actual, $expected, string $label): void
{
    global $checks;
    $checks++;
    if ($actual !== $expected) throw new RuntimeException($label . ': ' . var_export($actual, true) . ' != ' . var_export($expected, true));
}

same(dashboard_next_image_sequence_from_names(['1.ABC Fachada', '20.ABC Sala']), 21, 'Continua após o maior número');
same(dashboard_next_image_sequence_from_names(['1.ABC', '7.ABC', '9.ABC', 'ABC Planta']), 10, 'Ignora lacunas e nomes sem prefixo');
same(dashboard_next_image_sequence_from_names(['ABC 30 - Fachada', 'Fachada']), 1, 'Não interpreta número fora do prefixo');

$initial = dashboard_prepare_image_entries(['Fachada', 'Living', 'Piscina'], 'ABC', 1)['entries'];
same(array_column($initial, 'imagem_nome'), ['1.ABC Fachada', '2.ABC Living', '3.ABC Piscina'], 'Numeração inicial');
$extras = dashboard_prepare_image_entries(['21.ABC Fachada extra', 'Sala de jogos'], 'ABC', 21)['entries'];
same(array_column($extras, 'imagem_nome'), ['21.ABC Fachada extra', '22.ABC Sala de jogos'], 'Numeração de extras preserva descrições');

echo $checks . " verificações de sequência OK\n";
