<?php

require_once dirname(__DIR__, 2) . '/conexao.php';
require_once dirname(__DIR__, 2) . '/helpers/unidade_trabalho_helper.php';

foreach (['unidade_trabalho', 'unidade_trabalho_item', 'unidade_trabalho_evento'] as $table) {
    $safe = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    if (!$result || $result->num_rows !== 1) {
        throw new RuntimeException('Tabela ausente: ' . $table);
    }
}

$result = $conn->query('SELECT idcolaborador FROM colaborador ORDER BY idcolaborador LIMIT 1');
$collaboratorId = (int) ($result->fetch_assoc()['idcolaborador'] ?? 0);
if ($collaboratorId <= 0) {
    throw new RuntimeException('Não foi possível selecionar colaborador para smoke test.');
}
$summary = flow_wip_resumo($conn, $collaboratorId);
foreach (['limit', 'active_count', 'blocking_count', 'can_start_new', 'active_units'] as $key) {
    if (!array_key_exists($key, $summary)) {
        throw new RuntimeException('Contrato WIP incompleto: ' . $key);
    }
}

$groupedWipVerified = 0;
$group = $conn->query(
    "SELECT ut.id, ut.colaborador_id
       FROM unidade_trabalho ut
       JOIN unidade_trabalho_item uti ON uti.unidade_trabalho_id = ut.id
       JOIN funcao_imagem fi ON fi.idfuncao_imagem = uti.funcao_imagem_id
      WHERE ut.estado = 'ATIVA'
        AND fi.status IN ('Em andamento', 'Ajuste')
      GROUP BY ut.id, ut.colaborador_id
     HAVING COUNT(*) >= 2
      LIMIT 1"
)->fetch_assoc();
if ($group) {
    $groupSummary = flow_wip_resumo($conn, (int) $group['colaborador_id']);
    $matches = array_values(array_filter(
        $groupSummary['active_units'],
        static fn(array $unit): bool => ($unit['key'] ?? '') === 'UT:' . (int) $group['id']
    ));
    if (count($matches) !== 1) {
        throw new RuntimeException('A unidade explícita ativa não foi deduplicada no WIP.');
    }
    $batchCounts = flow_wip_contagens_colaboradores($conn, [(int) $group['colaborador_id']]);
    if (($batchCounts[(int) $group['colaborador_id']] ?? -1) !== (int) $groupSummary['active_count']) {
        throw new RuntimeException('A contagem gerencial divergiu do motor central de WIP.');
    }
    $groupedWipVerified = 1;
}

echo "DatabaseSmokeTest: OK (colaborador={$collaboratorId}, unidades={$summary['active_count']}, wip_agrupado={$groupedWipVerified})\n";
