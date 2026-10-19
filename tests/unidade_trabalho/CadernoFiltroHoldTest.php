<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Executa sobre registros existentes, sem persistir alterações ou notificações.
require_once dirname(__DIR__, 2) . '/conexao.php';
require_once dirname(__DIR__, 2) . '/helpers/flow_block_helper.php';

function cf_hold_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pair = $conn->query("SELECT c.idfuncao_imagem caderno, f.idfuncao_imagem filtro, c.imagem_id
    FROM funcao_imagem c JOIN funcao_imagem f
      ON f.imagem_id=c.imagem_id AND f.colaborador_id=c.colaborador_id AND f.funcao_id=8
    WHERE c.funcao_id=1 AND c.colaborador_id > 0
      AND NOT EXISTS (SELECT 1 FROM funcao_par_separado s
        WHERE s.imagem_id=c.imagem_id AND s.par_tipo='caderno_filtro')
      AND NOT EXISTS (SELECT 1 FROM flow_issue i
        WHERE i.funcao_imagem_id IN (c.idfuncao_imagem,f.idfuncao_imagem))
    LIMIT 1")->fetch_assoc();
cf_hold_assert((bool) $pair, 'Sem par disponível para o teste transacional.');
$caderno = (int) $pair['caderno'];
$filtro = (int) $pair['filtro'];
$imagem = (int) $pair['imagem_id'];

$conn->begin_transaction();
try {
    $conn->query("SELECT idfuncao_imagem FROM funcao_imagem WHERE idfuncao_imagem IN ($caderno,$filtro) FOR UPDATE");
    foreach ([$caderno, $filtro] as $entryId) {
        cf_hold_assert(count(flow_block_task_members($conn, $entryId)) === 2, 'Ambos os IDs devem resolver o mesmo par.');
        $conn->query("UPDATE funcao_imagem SET status='Em andamento' WHERE idfuncao_imagem IN ($caderno,$filtro)");
        cf_hold_assert(count(flow_block_apply_task_hold($conn, $entryId, 'Teste transacional HOLD')) === 2,
            'O HOLD deve atingir os dois membros, independentemente do ID recebido.');
        foreach (flow_block_task_members($conn, $entryId) as $member) {
            cf_hold_assert($member['status'] === 'HOLD', 'Um membro continuou em andamento.');
            cf_hold_assert($member['observacao'] === 'Teste transacional HOLD', 'O motivo deve ser compartilhado.');
        }
    }

    $conn->query("UPDATE funcao_imagem SET status='Finalizado' WHERE idfuncao_imagem=$caderno");
    flow_block_apply_task_hold($conn, $filtro);
    cf_hold_assert(flow_block_task_members($conn, $filtro)[0]['status'] === 'Finalizado', 'Não reabrir etapa concluída do par.');

    $conn->query("INSERT INTO flow_issue (funcao_imagem_id,tipo_id,descricao,criado_por_colaborador_id,sla_atendimento_em)
        SELECT $caderno,id,'Teste transacional HOLD',1,NOW() FROM flow_issue_tipo LIMIT 1");
    $issueId = (int) $conn->insert_id;
    cf_hold_assert($issueId > 0, 'Não foi possível criar o impedimento de teste.');
    cf_hold_assert(flow_block_has_blocking_issues($conn, $filtro), 'Issue do Caderno deve bloquear o Filtro.');
    $conn->query("UPDATE flow_issue SET funcao_imagem_id=$filtro WHERE id=$issueId");
    cf_hold_assert(flow_block_has_blocking_issues($conn, $caderno), 'Issue do Filtro deve bloquear o Caderno.');
    $conn->query("UPDATE funcao_imagem SET status='Em andamento' WHERE idfuncao_imagem IN ($caderno,$filtro)");
    flow_block_refresh_task_status($conn, $caderno);
    cf_hold_assert(count(array_filter(flow_block_task_members($conn, $caderno), static fn ($m) => $m['status'] === 'HOLD')) === 2,
        'Reabertura do impedimento deve manter o HOLD conjunto.');
    $conn->query("UPDATE flow_issue SET status='RESOLVIDA' WHERE id=$issueId");
    cf_hold_assert(!flow_block_task_ready_to_continue($conn, $caderno), 'Resolução sem confirmação ainda bloqueia o par.');
    $conn->query("UPDATE flow_issue SET confirmada_em=NOW() WHERE id=$issueId");
    cf_hold_assert(flow_block_task_ready_to_continue($conn, $caderno), 'Confirmação deve liberar ambos para reprogramar.');
    cf_hold_assert(flow_block_task_ready_to_continue($conn, $filtro), 'Filtro deve reconhecer o mesmo encerramento.');
    $conn->query("UPDATE flow_issue SET status='ABERTA',confirmada_em=NULL WHERE id=$issueId");

    $conn->query("INSERT INTO funcao_par_separado (imagem_id,par_tipo) VALUES ($imagem,'caderno_filtro')");
    cf_hold_assert(count(flow_block_task_members($conn, $caderno)) === 1, 'Par separado não deve receber HOLD conjunto.');
    cf_hold_assert(!flow_block_has_blocking_issues($conn, $caderno), 'Issue do Filtro separado não deve bloquear Caderno.');
    $conn->query("DELETE FROM funcao_par_separado WHERE imagem_id=$imagem AND par_tipo='caderno_filtro'");
    $conn->query("UPDATE funcao_imagem SET colaborador_id=NULL WHERE idfuncao_imagem=$filtro");
    cf_hold_assert(count(flow_block_task_members($conn, $caderno)) === 1, 'Responsáveis diferentes não formam o par.');
    echo "CadernoFiltroHoldTest: OK (HOLD pelos dois IDs, motivo, finalizados, resolução, reabertura, separação e responsáveis; rollback)\n";
} finally {
    $conn->rollback();
}
