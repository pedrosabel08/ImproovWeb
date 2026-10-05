<?php
require_once __DIR__.'/FechamentoDocumentoProjection.php';

final class FechamentoDocumentoRepository
{
    private FechamentoRevisaoRepository $financeiro;
    public int $insertId=0;
    public function __construct(private mysqli $conn) { $this->financeiro=new FechamentoRevisaoRepository($conn); }
    public function sql(string $sql,array $p=[]): array
    {
        $s=$this->conn->prepare($sql); if (!$s) throw new RuntimeException('Preparação documental falhou.');
        try {
            if ($p) $s->bind_param(str_repeat('s',count($p)),...$p);
            if (!$s->execute()) throw new RuntimeException('Operação documental falhou.');
            $this->insertId=(int)$s->insert_id;
            $r=$s->get_result(); return $r?$r->fetch_all(MYSQLI_ASSOC):[];
        } finally { $s->close(); }
    }
    public function autorizar(int $u,bool $lock=false): void
    {
        if ($lock) $this->sql('SELECT idusuario FROM usuario WHERE idusuario=? FOR UPDATE',[$u]);
        $this->financeiro->autorizar($u,$lock);
    }
    public function transacao(callable $f): array
    {
        if ((int)$this->sql('SELECT @@session.autocommit a')[0]['a']!==1) throw new RuntimeException('Conexão documental dedicada exige autocommit.');
        if (!$this->conn->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ WRITE')) throw new RuntimeException('Configuração transacional documental falhou.');
        $active=false;
        try {
            if (!$this->conn->begin_transaction(MYSQLI_TRANS_START_READ_WRITE)) throw new RuntimeException('Início transacional documental falhou.');
            $active=true; $r=$f();
            if (!$this->conn->commit()) throw new RuntimeException('Commit documental falhou.');
            $active=false; return $r;
        }
        finally { if ($active) $this->conn->rollback(); }
    }
    public function conferir(): void
    {
        $tables=['pagamento_fechamento_documento','pagamento_fechamento_documento_operacao'];
        $r=$this->sql('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?)',$tables);
        if (count($r)!==2 || array_filter($r,fn($t)=>strtoupper($t['ENGINE'])!=='INNODB')) throw new RuntimeException('Migration documental ausente/incompleta.');
        $r=$this->sql("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN ('pdoc_no_update','pdoc_no_delete','pdop_no_update','pdop_no_delete')");
        if (count($r)!==4) throw new RuntimeException('Proteção documental incompleta.');
    }
    public function documento(int $id,bool $lock=false): array
    {
        $r=$this->sql('SELECT * FROM pagamento_fechamento_documento WHERE id=?'.($lock?' FOR UPDATE':''),[$id])[0]??null;
        if (!$r) throw new DomainException('Documento não encontrado.'); return $r;
    }
    public function revisao(int $id,int $f): array
    {
        $r=$this->financeiro->revisao($id); FechamentoDocumentoProjection::validar($r,$f); return $r;
    }
    public function vinculo(array $d): array
    {
        $r=$this->revisao((int)$d['revisao_id'],(int)$d['fechamento_id']);
        if (!hash_equals($d['financial_snapshot_hash'],$r['snapshot_hash'])) throw new DomainException('Hash financeiro do documento divergente da revisão.');
        return $r;
    }
    public function operacao(int $u,string $key): ?array
    {
        return $this->sql('SELECT * FROM pagamento_fechamento_documento_operacao WHERE autor_id=? AND chave=? FOR UPDATE',[$u,$key])[0]??null;
    }
    public function reservarOperacao(int $doc,int $u,string $key,string $tipo,string $hash,array $request,array $before): array
    {
        $this->sql('INSERT INTO pagamento_fechamento_documento_operacao (documento_id,autor_id,chave,request_hash,tipo,request_json,antes_json,criado_em) VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP(6))',
            [$doc,$u,$key,$hash,$tipo,FechamentoSnapshot::json($request),FechamentoSnapshot::json($before)]);
        return $this->sql('SELECT * FROM pagamento_fechamento_documento_operacao WHERE id=?',[$this->insertId])[0];
    }
    public function concluir(array $op,array $result): void
    {
        if ($op['estado']==='CONCLUIDA') return;
        $this->sql("UPDATE pagamento_fechamento_documento_operacao SET estado='CONCLUIDA',depois_json=?,concluido_em=UTC_TIMESTAMP(6) WHERE id=?",[FechamentoSnapshot::json($result),$op['id']]);
    }
    public function identidade(int $b): array
    {
        // Somente identidade jurídica atual, capturada uma vez na reserva; nenhum valor financeiro.
        $rows=$this->sql('SELECT c.idcolaborador,u.nome_usuario AS nome_colaborador,iu.nome_empresarial,iu.cnpj,iu.cpf,iu.estado_civil,
            e.rua,e.numero,e.complemento,e.bairro,e.localidade,e.uf,e.cep,
            ec.rua_cnpj,ec.numero_cnpj,ec.complemento_cnpj,ec.bairro_cnpj,ec.localidade_cnpj,ec.uf_cnpj,ec.cep_cnpj
            FROM colaborador c JOIN usuario u ON u.idcolaborador=c.idcolaborador
            LEFT JOIN informacoes_usuario iu ON iu.usuario_id=u.idusuario
            LEFT JOIN endereco e ON e.usuario_id=u.idusuario LEFT JOIN endereco_cnpj ec ON ec.usuario_id=u.idusuario
            WHERE c.idcolaborador=?',[$b]);
        if (count($rows)!==1) throw new DomainException('Qualificação ausente/ambígua; não escolher fallback.');
        return $rows[0];
    }
    public function reservarDocumento(int $f,int $rev,int $u,array $r,array $modelo,string $html,string $tplHash): array
    {
        $last=$this->sql('SELECT numero FROM pagamento_fechamento_documento WHERE fechamento_id=? ORDER BY numero DESC LIMIT 1 FOR UPDATE',[$f]);
        $numero=(int)($last[0]['numero']??0)+1; $uuid=bin2hex(random_bytes(16));
        $name="adendo_f{$f}_r{$rev}_d{$numero}_{$uuid}.pdf";
        $this->sql('INSERT INTO pagamento_fechamento_documento (fechamento_id,revisao_id,numero,uuid,financial_snapshot_hash,modelo_json,html_snapshot,template_hash,arquivo_preview,arquivo_definitivo,criado_por,criado_em) VALUES (?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6))',
            [$f,$rev,$numero,$uuid,$r['snapshot_hash'],FechamentoSnapshot::json($modelo),$html,$tplHash,'staging/'.$name,'definitivo/'.$name,$u]);
        return $this->documento($this->insertId);
    }
    public function resumo(array $d): array
    {
        return ['document_id'=>(int)$d['id'],'fechamento_id'=>(int)$d['fechamento_id'],'revision_id'=>(int)$d['revisao_id'],
            'numero_documento'=>(int)$d['numero'],'estado'=>$d['estado'],'financial_snapshot_hash'=>$d['financial_snapshot_hash'],
            'pdf_hash'=>$d['pdf_hash'],'tamanho_bytes'=>$d['tamanho_bytes']===null?null:(int)$d['tamanho_bytes'],
            'arquivo_preview'=>$d['arquivo_preview'],'arquivo_definitivo'=>$d['estado']==='CONFIRMADO'?$d['arquivo_definitivo']:null,
            'criado_por'=>(int)$d['criado_por'],'criado_em_utc'=>$d['criado_em'],
            'confirmado_por'=>$d['confirmado_por']===null?null:(int)$d['confirmado_por'],'confirmado_em_utc'=>$d['confirmado_em']];
    }
}
