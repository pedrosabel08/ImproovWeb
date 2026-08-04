<?php
declare(strict_types=1);
namespace FlowConnect\Application\PendingSummary\Providers;
use FlowConnect\Application\PendingSummary\AbstractPendingSummaryProvider;
use mysqli;
final class FlowReviewPendingProvider extends AbstractPendingSummaryProvider {
    public function __construct(array $config) { parent::__construct($config, 'flow_review', 'Flow Review', 'FlowReview/'); }
    public function collect(mysqli $conn): array {
        if (!$this->tableExists($conn, 'historico_aprovacoes_imagens')) return [];
        $slaJoin = $this->tableExists($conn, 'sla_funcao') ? 'LEFT JOIN sla_funcao sf ON sf.funcao_id=fi.funcao_id' : 'LEFT JOIN (SELECT NULL limite_horas) sf ON 1=1';
        $rows = $this->query($conn, "SELECT fi.idfuncao_imagem entity_id,fi.colaborador_id collaborator_id,COALESCE(h.data_envio,a.criado_em) created_at, fi.prazo due_at, ico.imagem_nome imagem,f.nome_funcao funcao,COALESCE(NULLIF(o.nomenclatura,''),o.nome_obra) obra, sf.limite_horas FROM funcao_imagem fi JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra=fi.imagem_id JOIN obra o ON o.idobra=ico.obra_id LEFT JOIN funcao f ON f.idfuncao=fi.funcao_id LEFT JOIN historico_aprovacoes_imagens h ON h.id=(SELECT h2.id FROM historico_aprovacoes_imagens h2 WHERE h2.funcao_imagem_id=fi.idfuncao_imagem ORDER BY h2.data_envio DESC,h2.id DESC LIMIT 1) LEFT JOIN arquivo_log a ON a.id=(SELECT a2.id FROM arquivo_log a2 WHERE a2.funcao_imagem_id=fi.idfuncao_imagem AND UPPER(a2.tipo)='PDF' ORDER BY a2.criado_em DESC,a2.id DESC LIMIT 1) {$slaJoin} WHERE fi.colaborador_id IS NOT NULL AND fi.status IN ('Em aprovação','Em aprovaÃ§Ã£o','Aguardando Direção','Aguardando DireÃ§Ã£o') AND o.status_obra=0 AND (h.id IS NOT NULL OR a.id IS NOT NULL) ORDER BY created_at ASC");
        foreach ($rows as &$row) { $row['entity_type']='funcao_imagem'; $row['title']=trim(($row['imagem'] ?: 'Imagem').' · '.($row['funcao'] ?: 'Aprovação')); if (!empty($row['limite_horas']) && !empty($row['created_at'])) $row['due_at']=date('Y-m-d H:i:s', strtotime($row['created_at'].' +'.(float)$row['limite_horas'].' hours')); $row['priority']=$this->priority($row['due_at'] ?? null); $row['link']='FlowReview/index.php'; } unset($row);
        return $this->summarize($rows);
    }
}
