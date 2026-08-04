<?php
declare(strict_types=1);
namespace FlowConnect\Application\PendingSummary\Providers;
use FlowConnect\Application\PendingSummary\AbstractPendingSummaryProvider;
use mysqli;
final class FotograficoPendingProvider extends AbstractPendingSummaryProvider {
    public function __construct(array $config) { parent::__construct($config, 'fotografico', 'Fotográfico', 'Fotografico/'); }
    public function collect(mysqli $conn): array { if (!$this->tableExists($conn,'fotografico_pendencia')) return []; $rows=$this->query($conn,"SELECT fp.id entity_id,fp.responsavel_id,fp.responsavel_cobranca_id,fp.criado_em created_at,fp.proxima_cobranca_em due_at,fp.titulo,COALESCE(NULLIF(o.nomenclatura,''),o.nome_obra) obra,fp.plano_id FROM fotografico_pendencia fp JOIN fotografico_plano p ON p.id=fp.plano_id JOIN obra o ON o.idobra=p.obra_id WHERE fp.status='ABERTA' AND (o.status_obra=0 OR o.status_obra IS NULL)"); foreach($rows as &$r){$r['recipient_ids']=[$r['responsavel_id'],$r['responsavel_cobranca_id']];$r['entity_type']='fotografico_pendencia';$r['title']=$r['titulo']?:'Pendência fotográfica';$r['priority']=$this->priority($r['due_at']??null);$r['link']='Fotografico/index.php?plano_id='.(int)$r['plano_id'];}unset($r);return $this->summarize($rows); }
}
