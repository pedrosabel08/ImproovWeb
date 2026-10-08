<?php
/** Projeção da fila existente; a conclusão é sempre derivada do financeiro. */
function pagamento_pendencias_append(mysqli $conn,array &$modules,int $colaboradorId,int $obraScopeId): void
{
    if ($obraScopeId>0) return;
    $exists=$conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagamento_competencia'");
    if (!$exists || !$exists->num_rows) return;
    require_once __DIR__.'/pagamento_competencia_helper.php';
    require_once __DIR__.'/../Pagamento/services/FechamentoCompetenciaService.php';
    $s=$conn->prepare("SELECT c.*,ch.id checklist_id,ch.entity_type
        FROM pagamento_competencia c JOIN pagamento_competencia_responsavel pr ON pr.competencia_id=c.id
        JOIN checklist_operacional ch ON ch.module_key='pagamentos' AND ch.entity_id=c.id AND ch.entity_type IN ('fechamento','pagamento')
        WHERE pr.colaborador_id=? AND ((ch.entity_type='fechamento' AND c.estado<>'CONCLUIDO') OR (ch.entity_type='pagamento' AND c.quitado_em IS NULL)) ORDER BY c.competencia,ch.entity_type");
    $s->bind_param('i',$colaboradorId); $s->execute(); $rows=$s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    $modules['pagamentos']=pendencias_operacionais_empty_module('pagamentos','Pagamentos','Fechamento e quitação das competências','ri-money-dollar-circle-line','#16a34a');
    $summaries=[];
    foreach($rows as $r) {
        $summary=$summaries[$r['competencia']]??= (new FechamentoCompetenciaService($conn,0))->resumo($r['competencia']);
        $r['aptos']=$summary['quantidade'];
        $r['revisados']=$summary['contagens']['CONFIRMADO'];
        $r['pagos']=$summary['quantidade_pagos'];
        $closing=$r['entity_type']==='fechamento';
        $state=$closing?'Pendente':($r['estado']==='CONCLUIDO'?'Pagamento pendente':'Aguardando fechamento');
        $url=$closing?'Pagamento/fechamento.php?competencia='.$r['competencia']:'Pagamento/?view=geral&mes='.(int)substr($r['competencia'],5,2).'&ano='.substr($r['competencia'],0,4);
        $zone=new DateTimeZone('America/Sao_Paulo');
        $created=(new DateTimeImmutable($r['criado_em'],new DateTimeZone('UTC')))->setTimezone($zone)->format(DateTimeInterface::ATOM);
        $due=(new DateTimeImmutable($r['previsto_em'].' 23:59:59',$zone))->format(DateTimeInterface::ATOM);
        $sla=pendencias_operacionais_sla_status($created,$due);
        pendencias_operacionais_add_item($modules['pagamentos'],[
            'id'=>'pagamentos-'.$r['checklist_id'],'source_type'=>'pagamentos','source_id'=>(int)$r['checklist_id'],
            'title'=>($closing?'Realizar fechamento de ':'Realizar pagamentos de ').pagamento_competencia_nome($r['competencia']),
            'subtitle'=>$state.' · '.($closing?$r['revisados']:$r['pagos']).'/'.$r['aptos'].($closing?' revisados':' pagos'),
            'obra_id'=>null,'obra_nome'=>'','responsavel_id'=>$colaboradorId,'responsavel_nome'=>'',
            'created_at'=>$created,'sla_start_at'=>$created,'due_at'=>$due,
            'sla_status'=>$sla['nivel'],'sla_label'=>$sla['label'],'tempo_decorrido_minutos'=>$sla['tempo_decorrido_minutos'],'sla_minutos'=>$sla['sla_minutos'],
            'action_url'=>$url,'status'=>$state,'checklist_id'=>(int)$r['checklist_id'],
            'checklist_items'=>[['item_key'=>'financeiro','label'=>$closing?'100% revisados':'100% pagos','required'=>1,'update_mode'=>'AUTOMATICO','done'=>0]]
        ]);
    }
}
