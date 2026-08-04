<?php
declare(strict_types=1);
namespace FlowConnect\Application\PendingSummary\Providers;
use FlowConnect\Application\PendingSummary\AbstractPendingSummaryProvider;
use mysqli;
final class CobrancaClientePendingProvider extends AbstractPendingSummaryProvider {
    public function __construct(array $config) { parent::__construct($config, 'cobranca_cliente', 'Cobrança Cliente', 'Entregas/'); }
    public function collect(mysqli $conn): array { if (!$this->tableExists($conn,'cobranca_review')) return []; $recipients=$this->config['operational']['pending_summary']['cobranca_collaborator_ids']??[]; if($recipients===[]) return []; $rows=$this->query($conn,"SELECT cr.id entity_id,cr.created_at,COALESCE(cr.snooze_until,cr.due_at) due_at,COALESCE(NULLIF(o.nomenclatura,''),o.nome_obra) obra,cr.overdue_days FROM cobranca_review cr JOIN review_batch rb ON rb.id=cr.review_batch_id JOIN entregas e ON e.id=rb.entrega_id JOIN obra o ON o.idobra=e.obra_id WHERE cr.status='OVERDUE' AND cr.resolved_at IS NULL AND rb.status NOT IN('RESOLVED','IGNORED') AND o.status_obra=0"); foreach($rows as &$r){$r['recipient_ids']=$recipients;$r['entity_type']='cobranca_review';$r['title']=$r['obra']?:'Lote sem retorno';$r['funcao']='Cobrança ao cliente';$r['priority']=$this->priority($r['due_at']??null);$r['link']='Entregas/';}unset($r);return $this->summarize($rows); }
}
