<?php
require_once __DIR__.'/../../FlowConnect/bootstrap.php';

/** Usa a fila e o transporte Slack existentes; processa exclusivamente os dois alertas financeiros. */
final class FechamentoCompetenciaNotificacoes
{
    public static function entregar(mysqli $conn): array
    {
        $config=flow_connect_config();
        $queue=new \FlowConnect\Infrastructure\DeliveryRepository($conn,(int)$config['claim_ttl_seconds']);
        $adapter=new \FlowConnect\Channels\SlackApiAdapter($config['slack']);
        $retry=new \FlowConnect\Application\RetryPolicy();
        $dead=new \FlowConnect\Infrastructure\DeadLetterRepository($conn);
        $resultados=[];
        foreach($queue->claimEligible(20,'pagamento-cron:'.getmypid(),true) as $delivery) {
            $result=$adapter->send($delivery);
            $decision=$retry->decide($result,(int)$delivery['attempt_count']);
            // 429 confirma recusa por rate limit. Timeout/falha incerta não pode duplicar o alerta.
            if (empty($result['ok']) && ($result['http_status']??0)!==429) $decision=['status'=>'DEAD','next_attempt_at'=>null];
            $queue->completeAttempt($delivery,$result,$decision);
            if($decision['status']==='DEAD') $dead->record(null,(int)$delivery['notification_id'],(int)$delivery['id'],'financial_delivery_failed',['error_code'=>$result['error_code']??'unknown']);
            $resultados[]=['delivery_id'=>$delivery['id'],'status'=>$decision['status']];
        }
        return $resultados;
    }
}
