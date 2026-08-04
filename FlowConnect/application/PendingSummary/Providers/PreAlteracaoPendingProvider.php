<?php
declare(strict_types=1);
namespace FlowConnect\Application\PendingSummary\Providers;
use FlowConnect\Application\PendingSummary\AbstractPendingSummaryProvider;
use mysqli;
final class PreAlteracaoPendingProvider extends AbstractPendingSummaryProvider {
    public function __construct(array $config) { parent::__construct($config, 'pre_alteracao', 'Pré-Alteração', 'PreAlteracao/'); }
    public function collect(mysqli $conn): array { if (!$this->tableExists($conn,'pre_alt_lote')) return []; $rows=$this->query($conn,"SELECT l.id entity_id,COALESCE(l.responsavel_id,l.created_by) collaborator_id,l.created_at,COALESCE(CONCAT(l.prazo,' 18:00:00'),l.data_finalizacao_cliente) due_at,COALESCE(NULLIF(o.nomenclatura,''),o.nome_obra) obra,l.status FROM pre_alt_lote l JOIN obra o ON o.idobra=l.obra_id WHERE l.status NOT IN('PLANEJADO','CANCELADO') AND COALESCE(l.responsavel_id,l.created_by) IS NOT NULL AND o.status_obra=0"); foreach($rows as &$r){$r['entity_type']='pre_alt_lote';$r['title']=$r['obra']?:'Lote de triagem';$r['funcao']=$r['status']?:'Triagem';$r['priority']=$this->priority($r['due_at']??null);$r['link']='PreAlteracao/';}unset($r);return $this->summarize($rows); }
}
