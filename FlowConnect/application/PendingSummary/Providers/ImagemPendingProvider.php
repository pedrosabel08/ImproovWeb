<?php
declare(strict_types=1);
namespace FlowConnect\Application\PendingSummary\Providers;
use FlowConnect\Application\PendingSummary\AbstractPendingSummaryProvider;
use mysqli;
final class ImagemPendingProvider extends AbstractPendingSummaryProvider {
    public function __construct(array $config) { parent::__construct($config, 'imagem', 'Imagem'); }
    public function collect(mysqli $conn): array { if (!$this->tableExists($conn,'checklist_operacional') || !$this->tableExists($conn,'checklist_operacional_item')) return []; $rows=$this->query($conn,"SELECT co.id entity_id,co.responsavel_id collaborator_id,co.created_at,co.due_at,i.imagem_nome imagem,COALESCE(NULLIF(o.nomenclatura,''),o.nome_obra) obra FROM checklist_operacional co JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=co.entity_id JOIN obra o ON o.idobra=i.obra_id WHERE co.module_key='imagem' AND co.entity_type='imagem' AND co.status='aberto' AND co.responsavel_id IS NOT NULL AND o.status_obra=0 AND i.substatus_id=2 AND i.status_id IN(1,2) AND EXISTS(SELECT 1 FROM checklist_operacional_item ci WHERE ci.checklist_id=co.id AND ci.required=1 AND ci.done=0)"); foreach($rows as &$r){$r['entity_type']='checklist_operacional';$r['title']=$r['imagem']?:'Imagem em TO-DO';$r['priority']=$this->priority($r['due_at']??null);$r['link']='PaginaPrincipal/';}unset($r);return $this->summarize($rows); }
}
