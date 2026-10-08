<?php
require_once __DIR__.'/FechamentoCompetenciaService.php';
require_once __DIR__.'/FechamentoRevisaoService.php';
require_once __DIR__.'/../../FlowConnect/bootstrap.php';

/** Runner diário recupera o ciclo do mês corrente, sem gerar meses retroativos. */
final class FechamentoCompetenciaAutomacao
{
    public function __construct(private mysqli $conn,private int $usuario) {}
    public static function mensagem(array $c,string $tipo,string $base): string
    {
        $nome=pagamento_competencia_nome($c['competencia']);
        $close=$base.'Pagamento/fechamento.php?competencia='.$c['competencia'];
        $pay=$base.'Pagamento/?view=geral&mes='.(int)substr($c['competencia'],5,2).'&ano='.substr($c['competencia'],0,4);
        $rev=$c['contagens']['CONFIRMADO']; $q=$c['quantidade'];
        if ($tipo==='fechamento') return "📋 *Fechamento mensal disponível*\nO fechamento da competência $nome está disponível para revisão.\n$rev de $q colaboradores revisados.\nApós todos os fechamentos individuais serem revisados, o fechamento poderá ser concluído.\n<$close|Abrir fechamento>";
        if ($c['estado']!=='CONCLUIDO') {
            $n=$q-$rev;
            return "⚠️ *Pagamento bloqueado por fechamento pendente*\nHoje é o 5º dia útil, porém o fechamento de $nome ainda não foi concluído.\n$rev de $q colaboradores revisados.\nAinda existem $n revisões pendentes.\n<$close|Resolver fechamento>";
        }
        $money=fn($v)=>'R$ '.number_format($v/100,2,',','.');
        return "💰 *Pagamento da produção*\nHoje é a data prevista para o pagamento da competência $nome.\nTotal fechado: ".$money($c['total_fechado_centavos'])."\nPago: ".$money($c['pago_centavos'])."\nPendente: ".$money($c['pendente_centavos'])."\n".$c['quantidade_pagos']." de $q colaboradores pagos.\n<$pay|Abrir pagamentos>";
    }
    public function executar(DateTimeImmutable $agora,bool $notificar=true,bool $planejar=false): array
    {
        $hoje=$agora->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
        $ref=(new DateTimeImmutable(substr($hoje,0,7).'-01'))->modify('-1 month')->format('Y-m');
        if (!pagamento_competencia_nova($ref)) return ['ignorado'=>true,'competencia'=>$ref];
        $s=new FechamentoCompetenciaService($this->conn,$this->usuario);
        $c=$s->criar($ref); $events=[];
        // Consolidar registros recém-criados, sem substituir revisões/PDFs já existentes.
        $db=new FechamentoRevisaoRepository($this->conn);
        $novos=$db->sql('SELECT f.colaborador_id FROM pagamento_competencia_colaborador m JOIN pagamento_fechamento f ON f.id=m.fechamento_id WHERE m.competencia_id=? AND f.numero_revisao=0',[$c['ciclo_id']]);
        foreach($novos as $p) {
            try { (new FechamentoRevisaoService($this->conn,true))->prepararRevisao((int)$p['colaborador_id'],$ref,$this->usuario,0,'competencia-inicial:'.$ref.':'.$p['colaborador_id']); }
            catch(DomainException $e) {
                // Outro gestor pode preparar o mesmo indivíduo entre a leitura e o lock.
                if (!str_contains($e->getMessage(),'STALE_VERSION')) throw $e;
            }
        }
        $c=$s->resumo($ref);
        if ($notificar) {
            $base=rtrim(getenv('PAGAMENTO_APP_URL')?:'https://improov/ImproovWeb/','/').'/';
            foreach(['fechamento','pagamento'] as $tipo) {
                // Alertas só nos dias especificados. Execução tardia cria o ciclo/pendências sem mensagem retroativa.
                if (($tipo==='fechamento' && substr($hoje,8,2)!=='01') || ($tipo==='pagamento' && $hoje!==$c['previsto_em'])) continue;
                $event=\FlowConnect\Application\LegacyImmediateEventFactory::make('pagamento.competencia.'.$tipo,'competencia',$c['ciclo_id'],
                    ['message'=>self::mensagem($c,$tipo,$base)],null,'SLACK_WEBHOOK_CONTRATOS_URL','pagamento:'.$tipo.':'.$ref.':v1','pagamento');
                $event['metadata']['flow_connect_mode']=getenv('FLOW_CONNECT_PAGAMENTO_MODE')?:'active';
                if (!in_array($event['metadata']['flow_connect_mode'],['active','shadow'],true)) throw new RuntimeException('Modo de notificação inválido.');
                $this->conn->begin_transaction();
                try {
                    $id=flow_connect_publish_in_transaction($this->conn,$event); $events[]=$id;
                    if ($planejar) {
                        // Reserva/planejamento no mesmo commit: o cron não disputa com outros workers.
                        $db=new FechamentoRevisaoRepository($this->conn);
                        $stored=$db->sql('SELECT * FROM flow_connect_events WHERE id=? FOR UPDATE',[$id])[0];
                        if ($stored['status']!=='PROCESSED') {
                            $stored['payload']=json_decode($stored['payload_json'],true,512,JSON_THROW_ON_ERROR);
                            $stored['metadata']=json_decode($stored['metadata_json'],true,512,JSON_THROW_ON_ERROR);
                            (new \FlowConnect\Application\EventPlanner($this->conn,flow_connect_config()))->plan($stored,false);
                            (new \FlowConnect\Infrastructure\EventRepository($this->conn))->markProcessed($id);
                        }
                    }
                    $this->conn->commit();
                }
                catch(Throwable $e) { $this->conn->rollback(); throw $e; }
            }
        }
        return ['competencia'=>$ref,'ciclo_id'=>$c['ciclo_id'],'eventos'=>$events,'quantidade'=>$c['quantidade']];
    }
}
