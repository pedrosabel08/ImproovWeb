<?php
/** Fatos de execução somente por ciclo vinculado à identidade da rodada. */
function flow_pac_resumir_realizado(array $itens,array $baseline,array $vigente,array $ciclos): array
{
    $bs=array_column($baseline,null,'entrega_item_id');$vs=array_column($vigente,null,'entrega_item_id');$porItem=[];$dias=[];
    foreach ($ciclos as $c) $porItem[$c['entrega_item_id']][]=$c;
    $resultado=[];$semPontos=[];
    foreach ($itens as $i) {
        $id=$i['entrega_item_id'];$lista=$porItem[$id]??[];$ultimo=$lista?end($lista):null;
        $inicio=$lista?min(array_column($lista,'inicio_em')):null;
        $concluido=$ultimo && $ultimo['situacao']==='ENCERRADO' && in_array($ultimo['motivo_encerramento'],['CONCLUSAO','ENVIO_APROVACAO','SINCRONIZACAO_ENVIO_APROVACAO'],true);
        $fim=$concluido?$ultimo['encerrado_em']:null;$dia=$fim?substr($fim,0,10):null;
        $plano=$vs[$id]??[];$previsto=$plano['fim_planejado']??null;
        $atraso=$dia&&$previsto&&$dia>$previsto?flow_planejamento_dias_uteis_entre($previsto,$dia):0;
        $r=['entrega_item_id'=>$id,'baseline'=>$bs[$id]??null,'vigente'=>$plano?:null,'executor_id'=>$ultimo?(int)$ultimo['responsavel_atual_id']:null,'executor_nome'=>$ultimo['executor_nome']??null,'inicio_real'=>$inicio,'conclusao_alteracao'=>$fim,'entrega_cliente'=>$i['data_entregue']??null,'dias_uteis_atraso'=>$atraso,'resultado'=>!$dia?'Sem conclusão comprovada':(!$previsto?'Sem dia confirmado':($dia<=$previsto?'No prazo':($atraso.' dias úteis após o planejado')))];
        $resultado[]=$r;
        $pontos=(float)($plano['pontos_snapshot']??0);$resp=$plano['responsavel_planejado_id']??null;
        if ($resp&&$previsto) {
            $dias[$resp][$previsto]??=['planejados'=>0,'concluidos'=>0,'pendentes_do_planejado'=>0];
            $dias[$resp][$previsto]['planejados']+=$pontos;
            if (!$dia||$dia>$previsto) $dias[$resp][$previsto]['pendentes_do_planejado']+=$pontos;
            if (!isset($plano['pontos_snapshot'])) {
                $semPontos[$resp][$previsto]['planejados']=true;
                if (!$dia||$dia>$previsto) $semPontos[$resp][$previsto]['pendentes_do_planejado']=true;
            }
        }
        if ($dia&&$r['executor_id']) {
            $dias[$r['executor_id']][$dia]??=['planejados'=>0,'concluidos'=>0,'pendentes_do_planejado'=>0];
            $dias[$r['executor_id']][$dia]['concluidos']+=$pontos;
            if (!isset($plano['pontos_snapshot'])) $semPontos[$r['executor_id']][$dia]['concluidos']=true;
        }
    }
    foreach ($semPontos as $resp=>$datas) foreach ($datas as $d=>$campos) foreach ($campos as $campo=>$_) $dias[$resp][$d][$campo]=null;
    return ['itens'=>$resultado,'por_dia'=>$dias];
}

function flow_pac_realizado(mysqli $conn,int $entregaId,array $itens,array $baseline,array $vigente): array
{
    $ciclos=flow_pa_rows($conn,'SELECT c.*,co.nome_colaborador AS executor_nome FROM janela_operacional_ciclo c JOIN entregas_itens ei ON ei.id=c.entrega_item_id LEFT JOIN colaborador co ON co.idcolaborador=c.responsavel_atual_id WHERE ei.entrega_id=? ORDER BY c.id','i',[$entregaId]);
    return flow_pac_resumir_realizado($itens,$baseline,$vigente,$ciclos);
}
