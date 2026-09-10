<?php

$root = dirname(__DIR__, 2);
$kanban = file_get_contents($root . '/PaginaPrincipal/scriptIndex.js');
$obra = file_get_contents($root . '/Dashboard/scriptObra.js');
$review = file_get_contents($root . '/FlowReview/script.js');
$reviewPayload = file_get_contents($root . '/FlowReview/atualizar.php');

if ($kanban === false || !str_contains($kanban, 'subtitulo = "MODELAGEM + COMPOSIÇÃO";')) {
    throw new RuntimeException('O Kanban não usa o nome funcional exato da unidade.');
}
if (str_contains($kanban, 'workUnitMembersHTML')) {
    throw new RuntimeException('O Kanban não deve acrescentar o detalhamento dos membros ao card agrupado.');
}
if ($obra === false || !str_contains($obra, 'cellUnit.colSpan = memberColumns.length;')) {
    throw new RuntimeException('A obra não deriva a mesclagem dos membros da unidade central.');
}
if (!str_contains($obra, 'cellUnit.textContent = owner;') || str_contains($obra, 'cellUnit.innerHTML')) {
    throw new RuntimeException('A célula agrupada da obra deve mostrar somente o responsável.');
}
if (!str_contains($obra, '"func-pair-unified"')) {
    throw new RuntimeException('A célula agrupada da obra não reutiliza o padrão visual de Caderno + Filtro.');
}
if ($review === false || !str_contains($review, 'tarefa.work_unit?.label || tarefa.nome_funcao')) {
    throw new RuntimeException('O Flow Review não apresenta o nome único da unidade.');
}
if (str_contains($review, 'task-card-work-unit-members')) {
    throw new RuntimeException('O Flow Review não deve detalhar membros dentro do card agrupado.');
}
if ($reviewPayload === false || !str_contains($reviewPayload, "? 'Composição + Modelagem'")) {
    throw new RuntimeException('O Flow Review não usa a Composição como representante da unidade.');
}

echo "PresentationContractTest: OK\n";
