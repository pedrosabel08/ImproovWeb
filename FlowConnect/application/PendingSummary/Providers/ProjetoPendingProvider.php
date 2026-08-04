<?php
declare(strict_types=1);
namespace FlowConnect\Application\PendingSummary\Providers;
use FlowConnect\Application\PendingSummary\AbstractPendingSummaryProvider;
use mysqli;
final class ProjetoPendingProvider extends AbstractPendingSummaryProvider {
    public function __construct(array $config) { parent::__construct($config, 'projeto', 'Projeto', 'Dashboard/obra.php'); }
    public function collect(mysqli $conn): array { if (!$this->tableExists($conn,'checklist_operacional')) return []; $rows=$this->query($conn,"SELECT co.id entity_id,co.responsavel_id collaborator_id,co.created_at,co.due_at,COALESCE(NULLIF(o.nomenclatura,''),o.nome_obra) obra FROM checklist_operacional co JOIN obra o ON o.idobra=co.obra_id WHERE co.module_key='projeto' AND co.status='aberto' AND co.responsavel_id IS NOT NULL AND o.status_obra=0"); foreach($rows as &$r){$r['entity_type']='checklist_operacional';$r['title']='Projeto OK · '.($r['obra']?:'Obra');$r['priority']=$this->priority($r['due_at']??null);$r['link']='Dashboard/obra.php';}unset($r);return $this->summarize($rows); }
}
