<?php
declare(strict_types=1);
namespace FlowConnect\Application\PendingSummary\Providers;
use FlowConnect\Application\PendingSummary\AbstractPendingSummaryProvider;
use mysqli;
final class LinksPendingProvider extends AbstractPendingSummaryProvider {
    public function __construct(array $config) { parent::__construct($config, 'links', 'Links'); }
    public function collect(mysqli $conn): array { if (!$this->tableExists($conn,'pendencias_links_obra')) return []; $rows=$this->query($conn,"SELECT pl.id entity_id,pl.responsavel_id collaborator_id,pl.criada_em created_at,NULL due_at,pl.tipo_link,COALESCE(NULLIF(o.nomenclatura,''),o.nome_obra) obra FROM pendencias_links_obra pl JOIN obra o ON o.idobra=pl.obra_id WHERE LOWER(pl.status) NOT IN('resolvida','concluida','cancelada') AND pl.responsavel_id IS NOT NULL AND o.status_obra=0"); foreach($rows as &$r){$r['entity_type']='pendencias_links_obra';$r['title']='Cadastrar '.($r['tipo_link']?:'link').' · '.($r['obra']?:'Obra');$r['priority']='NORMAL';$r['link']='PaginaPrincipal/';}unset($r);return $this->summarize($rows); }
}
