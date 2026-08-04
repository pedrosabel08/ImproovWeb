<?php
declare(strict_types=1);
namespace FlowConnect\Application\PendingSummary;
use FlowConnect\Contracts\EventEnvelope;
final class PendingSummaryFactory {
    public const MODULE_ORDER=['flow_review','render','projeto','imagem','pre_alteracao','fotografico','flow_block','cobranca_cliente','links','arquivo'];
    public static function priority(int $total,array $config): string { $pending=$config['operational']['pending_summary']??[]; $normal=(int)($pending['normal_max']??5); $attention=max($normal,(int)($pending['attention_max']??10)); return $total<1?'NONE':($total<=$normal?'NORMAL':($total<=$attention?'ATTENTION':'CRITICAL')); }
    public static function event(int $collaboratorId,string $windowKey,array $modules,array $config,array $success,array $failed): array { usort($modules,static fn($a,$b)=>array_search($a['module_key'],self::MODULE_ORDER,true)<=>array_search($b['module_key'],self::MODULE_ORDER,true)); $total=array_sum(array_map(static fn($m)=>(int)$m['total'],$modules)); return EventEnvelope::normalize(['event_type'=>'pending.summary.ready','source_module'=>'pending_summary','entity_type'=>'collaborator','entity_id'=>(string)$collaboratorId,'idempotency_key'=>'pending-summary:'.str_replace('T',':',$windowKey).':'.$collaboratorId.':v1','payload'=>['collaborator_id'=>$collaboratorId,'generated_at'=>gmdate('c'),'window_key'=>$windowKey,'total_pending'=>$total,'total_modules'=>count($modules),'priority_level'=>self::priority($total,$config),'modules'=>$modules,'origin_url'=>$config['operational']['pending_summary']['origin_url']??''],'metadata'=>['policy_key'=>'pending.summary.v1','flow_connect_mode'=>$config['operational']['pending_summary']['mode']??'off','producer'=>'pending_summary_worker','providers_success'=>$success,'providers_failed'=>$failed]]); }
}
