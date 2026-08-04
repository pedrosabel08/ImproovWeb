<?php
declare(strict_types=1);
namespace FlowConnect\Application\PendingSummary\Providers;
use FlowConnect\Application\PendingSummary\AbstractPendingSummaryProvider;
use mysqli;
final class FlowBlockPendingProvider extends AbstractPendingSummaryProvider {
    public function __construct(array $config) { parent::__construct($config, 'flow_block', 'Flow Block', 'FlowBlock/'); }
    public function collect(mysqli $conn): array { if (!$this->tableExists($conn,'flow_issue')) return []; $rows=$this->query($conn,"SELECT x.id entity_id,CASE WHEN x.status='RESOLVIDA' THEN fi.colaborador_id ELSE x.responsavel_colaborador_id END collaborator_id,x.criado_em created_at,x.proxima_cobranca_em due_at,x.codigo,i.imagem_nome imagem,COALESCE(NULLIF(o.nomenclatura,''),o.nome_obra) obra FROM flow_issue x JOIN funcao_imagem fi ON fi.idfuncao_imagem=x.funcao_imagem_id JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id JOIN obra o ON o.idobra=i.obra_id WHERE x.bloqueante=1 AND (x.status IN('ABERTA','AGUARDANDO_ACAO','PAUSADA') OR (x.status='RESOLVIDA' AND x.confirmada_em IS NULL)) AND (x.proxima_cobranca_em IS NOT NULL OR (x.status='RESOLVIDA' AND x.confirmada_em IS NULL)) AND (o.status_obra=0 OR o.status_obra IS NULL)"); foreach($rows as &$r){$r['entity_type']='flow_issue';$r['title']=trim(($r['codigo']?:'Issue').' · '.($r['imagem']?:'Tarefa'));$r['priority']=$this->priority($r['due_at']??null);$r['link']='FlowBlock/issue.php?id='.(int)$r['entity_id'];}unset($r);return $this->summarize($rows); }
}
