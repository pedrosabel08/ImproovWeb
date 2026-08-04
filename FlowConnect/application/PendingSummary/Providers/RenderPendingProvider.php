<?php
declare(strict_types=1);
namespace FlowConnect\Application\PendingSummary\Providers;
use FlowConnect\Application\PendingSummary\AbstractPendingSummaryProvider;
use mysqli;
final class RenderPendingProvider extends AbstractPendingSummaryProvider {
    public function __construct(array $config) { parent::__construct($config, 'render', 'Render', 'Render/'); }
    public function collect(mysqli $conn): array { if (!$this->tableExists($conn,'render_alta')) return []; $rows=$this->query($conn,"SELECT r.idrender_alta entity_id,r.responsavel_id collaborator_id,r.data created_at,DATE_ADD(r.data,INTERVAL 1 HOUR) due_at,i.imagem_nome imagem,COALESCE(NULLIF(o.nomenclatura,''),o.nome_obra) obra FROM render_alta r LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=r.imagem_id LEFT JOIN obra o ON o.idobra=i.obra_id WHERE r.status IN ('Em aprovação','Em aprovaÃ§Ã£o') AND r.responsavel_id IS NOT NULL AND (o.status_obra=0 OR o.status_obra IS NULL)"); foreach($rows as &$r){$r['entity_type']='render_alta';$r['title']=$r['imagem']?:'Render em aprovação';$r['funcao']='Aprovação interna';$r['priority']=$this->priority($r['due_at']??null);$r['link']='Render/index.php';}unset($r);return $this->summarize($rows); }
}
