<?php

declare(strict_types=1);

namespace FlowConnect\Application\PendingSummary\Providers;

use FlowConnect\Application\PendingSummary\AbstractPendingSummaryProvider;
use mysqli;

/** Migrates the legacy upload-summary eligibility into the consolidated provider. */
final class UploadPendingProvider extends AbstractPendingSummaryProvider
{
    public function __construct(array $config)
    {
        parent::__construct($config, 'arquivo', 'Arquivos', 'PaginaPrincipal/');
    }
    public function collect(mysqli $conn): array
    {
        if (!$this->tableExists($conn, 'funcao_imagem') || !$this->columnExists($conn, 'funcao_imagem', 'requires_file_upload')) return [];
        $statusFilter = $this->columnExists($conn, 'funcao_imagem', 'status') ? "AND COALESCE(fi.status,'') NOT IN ('Concluído','Concluido','Concluída','Concluida','Cancelado','Cancelada')" : '';
        $rows = $this->query($conn, "SELECT fi.idfuncao_imagem entity_id,fi.colaborador_id collaborator_id,NULL created_at,fi.prazo due_at,i.imagem_nome imagem,f.nome_funcao funcao,COALESCE(NULLIF(o.nomenclatura,''),o.nome_obra) obra FROM funcao_imagem fi JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id JOIN obra o ON o.idobra=i.obra_id LEFT JOIN funcao f ON f.idfuncao=fi.funcao_id WHERE fi.requires_file_upload=1 AND fi.file_uploaded_at IS NULL AND fi.colaborador_id IS NOT NULL AND fi.colaborador_id>0 AND o.status_obra=0 {$statusFilter} ORDER BY COALESCE(fi.prazo,fi.idfuncao_imagem)");
        foreach ($rows as &$r) {
            $r['entity_type'] = 'funcao_imagem';
            $r['title'] = trim(($r['imagem'] ?: 'Imagem') . ' · ' . ($r['funcao'] ?: 'Função não identificada') . ' · arquivo pendente');
            $r['priority'] = $this->priority($r['due_at'] ?? null);
            $r['link'] = 'PaginaPrincipal/';
        }
        unset($r);
        return $this->summarize($rows);
    }
}
