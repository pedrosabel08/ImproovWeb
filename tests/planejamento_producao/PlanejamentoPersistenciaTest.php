<?php

require_once dirname(__DIR__, 2) . '/helpers/planejamento_producao_helper.php';

function persistencia_assert(bool $condicao, string $mensagem): void
{
    if (!$condicao) throw new RuntimeException($mensagem);
}

$snapshot = flow_planejamento_snapshot([
    'fim_previsto' => '2026-10-02',
    'etapas' => [[
        'codigo' => 'MODELAGEM_INTERNA',
        'volume' => 12,
        'itens' => [['imagem_id' => 10, 'funcao_id' => 2]],
    ]],
]);
persistencia_assert(!isset($snapshot['etapas'][0]['itens']), 'Snapshot confirmado não deve armazenar lista operacional de itens.');
persistencia_assert($snapshot['etapas'][0]['volume'] === 12, 'Snapshot deve preservar os dados gerenciais da etapa.');

$anterior = [
    'data_inicio' => '2026-08-12',
    'prazo_r00' => '2026-10-15',
    'imagens' => [['imagem_id' => 1, 'tipo_imagem' => 'INTERNA']],
    'funcoes' => [['imagem_id' => 1, 'funcao_id' => 2, 'tipo_imagem' => 'INTERNA', 'status' => 'nao iniciado', 'origem' => 'TAREFA']],
    'volumes_por_etapa' => ['MODELAGEM_INTERNA' => 1],
];
$atual = $anterior;
$atual['prazo_r00'] = '2026-10-13';
$atual['volumes_por_etapa']['MODELAGEM_INTERNA'] = 4;
$atual['imagens'][] = ['imagem_id' => 2, 'tipo_imagem' => 'EXTERNA'];
$mudancas = flow_planejamento_diferencas_estrutura($anterior, $atual);
persistencia_assert(count($mudancas) >= 3, 'Prazo, volume e composição devem invalidar o plano confirmado.');
persistencia_assert(in_array('prazo_r00', array_column($mudancas, 'tipo'), true), 'Alteração de prazo R00 deve ser explicada.');
persistencia_assert(in_array('volume', array_column($mudancas, 'tipo'), true), 'Alteração de volume deve ser explicada.');

$migration = file_get_contents(dirname(__DIR__, 2) . '/sql/2026-08-20_planejamento_producao_versionado.sql');
foreach (
    [
        'entrega_planejamento_producao',
        'entrega_planejamento_versao',
        'entrega_planejamento_funcao',
        'entrega_planejamento_evento',
        'uq_entrega_planejamento_uma_vigente',
        'FOREIGN KEY',
    ] as $trecho
) {
    persistencia_assert(strpos((string) $migration, $trecho) !== false, 'Migration sem contrato obrigatório: ' . $trecho);
}

echo "OK: snapshot, fingerprint estrutural e contrato de versionamento validados.\n";
