<?php
require_once __DIR__.'/FechamentoDocumentoRepository.php';
require_once __DIR__.'/FechamentoDocumentoFiles.php';
require_once __DIR__.'/../../Contratos/services/ContratoPdfService.php';

/** Ciclo documental paralelo. API interna: usuário obtido da sessão validada ou CLI confiável. */
final class FechamentoDocumentoService
{
    private FechamentoDocumentoRepository $db;
    private FechamentoDocumentoFiles $files;
    public function __construct(mysqli $conn,string $storageRoot)
    {
        $this->db=new FechamentoDocumentoRepository($conn); $this->files=new FechamentoDocumentoFiles($storageRoot);
    }
    private static function request(int $u,string $key,array $r): string
    {
        if ($u<=0 || !preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D',$key)) throw new InvalidArgumentException('Usuário/chave documental inválidos.');
        return FechamentoSnapshot::hash(['usuario'=>$u,'request'=>$r]);
    }
    private static function conferirOperacao(?array $op,string $hash,string $tipo): void
    {
        if ($op && (!hash_equals($op['request_hash'],$hash) || $op['tipo']!==$tipo)) throw new DomainException('Conflito de idempotência documental.');
    }
    public function gerarPreview(int $f,int $rev,int $u,string $key,?string $dataDocumental=null): array
    {
        // Data ausente é resolvida somente na primeira reserva, nunca muda em retry.
        $request=['tipo'=>'GERAR','fechamento_id'=>$f,'revision_id'=>$rev,'data_documental'=>$dataDocumental];
        $hash=self::request($u,$key,$request); $this->db->autorizar($u); $this->db->conferir();
        $reserved=$this->db->transacao(function() use($f,$rev,$u,$key,$request,$hash,$dataDocumental) {
            if (!$this->db->sql('SELECT id FROM pagamento_fechamento WHERE id=? FOR UPDATE',[$f])) throw new DomainException('Fechamento não encontrado.');
            $this->db->autorizar($u,true); $op=$this->db->operacao($u,$key); self::conferirOperacao($op,$hash,'GERAR');
            if ($op) return ['doc'=>$this->db->documento((int)$op['documento_id']),'op'=>$op];
            $r=$this->db->revisao($rev,$f); $identity=$this->db->identidade($r['colaborador_id']);
            $date=$dataDocumental??(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
            $model=(new FechamentoDocumentoProjection())->projetar($r,$identity,$date);
            $tpl=file_get_contents(__DIR__.'/../../Contratos/templates/adendo_modelo.html'); if (!$tpl) throw new RuntimeException('Template documental ausente.');
            $map=[]; foreach ($model['placeholders'] as $k=>$v) $map['{{'.$k.'}}']=$v;
            $html=strtr($tpl,$map); if (preg_match('/\{\{[^}]+\}\}/',$html)) throw new RuntimeException('Placeholder documental não resolvido.');
            $doc=$this->db->reservarDocumento($f,$rev,$u,$r,$model,$html,hash('sha256',$tpl));
            $op=$this->db->reservarOperacao((int)$doc['id'],$u,$key,'GERAR',$hash,$request,['estado'=>null]);
            return ['doc'=>$doc,'op'=>$op];
        });
        return $this->files->comLock($reserved['doc']['uuid'],function() use($reserved,$u,$key) {
            $this->db->autorizar($u);
            $doc=$this->db->documento((int)$reserved['doc']['id']); $this->db->vinculo($doc);
            if ($doc['estado']===null) {
                $meta=$this->files->materializar($doc,function() use($doc) {
                    $renderer=new ContratoPdfService(sys_get_temp_dir(),__DIR__.'/../../Contratos/templates/adendo_modelo.html');
                    return $renderer->renderizarHtml($doc['html_snapshot'],basename($doc['arquivo_preview']));
                });
                $this->db->transacao(function() use($doc,$meta,$u,$key) {
                    $current=$this->db->documento((int)$doc['id'],true); $this->db->autorizar($u,true); $this->db->vinculo($current);
                    $op=$this->db->operacao($u,$key);
                    if ($current['estado']===null) $this->db->sql("UPDATE pagamento_fechamento_documento SET estado='PREVIEW',pdf_hash=?,tamanho_bytes=?,gerado_em=UTC_TIMESTAMP(6) WHERE id=?",[$meta['pdf_hash'],$meta['tamanho_bytes'],$doc['id']]);
                    $current=$this->db->documento((int)$doc['id']); $this->db->concluir($op,$this->db->resumo($current));
                    return $current;
                });
            }
            $doc=$this->db->documento((int)$doc['id']); $this->files->ler($doc,$doc['estado']==='CONFIRMADO');
            return $this->db->resumo($doc);
        });
    }
    public function visualizar(int $id,int $u,string $key): array
    {
        $request=['tipo'=>'VISUALIZAR','document_id'=>$id]; $hash=self::request($u,$key,$request); $this->db->autorizar($u); $this->db->conferir();
        $doc=$this->db->documento($id);
        return $this->files->comLock($doc['uuid'],function() use($id,$u,$key,$request,$hash) {
            $doc=$this->db->documento($id); $this->db->vinculo($doc);
            if (!in_array($doc['estado'],['PREVIEW','CONFIRMADO'],true)) throw new DomainException('Preview ainda não publicado.');
            $bytes=$this->files->ler($doc,$doc['estado']==='CONFIRMADO');
            $this->db->transacao(function() use($id,$u,$key,$hash,$request) {
                $doc=$this->db->documento($id,true); $this->db->autorizar($u,true);
                $op=$this->db->operacao($u,$key); self::conferirOperacao($op,$hash,'VISUALIZAR');
                if (!$op) $op=$this->db->reservarOperacao($id,$u,$key,'VISUALIZAR',$hash,$request,['pdf_hash'=>$doc['pdf_hash'],'revision_id'=>(int)$doc['revisao_id']]);
                $this->db->concluir($op,$this->db->resumo($doc)); return $doc;
            });
            return ['metadata'=>$this->db->resumo($doc),'mime'=>'application/pdf','bytes'=>$bytes];
        });
    }
    public function confirmar(int $id,int $expectedRevision,string $expectedPdfHash,int $u,string $key): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$expectedPdfHash)) throw new InvalidArgumentException('Hash PDF esperado inválido.');
        $request=['tipo'=>'CONFIRMAR','document_id'=>$id,'revision_id'=>$expectedRevision,'pdf_hash'=>$expectedPdfHash];
        $hash=self::request($u,$key,$request); $this->db->autorizar($u); $this->db->conferir();
        $reserved=$this->db->transacao(function() use($id,$expectedRevision,$expectedPdfHash,$u,$key,$hash,$request) {
            $doc=$this->db->documento($id,true); $this->db->autorizar($u,true); $this->db->vinculo($doc);
            if ((int)$doc['revisao_id']!==$expectedRevision || $doc['pdf_hash']!==$expectedPdfHash) throw new DomainException('Documento/revisão/hash esperados não correspondem ao visualizado.');
            if (!in_array($doc['estado'],['PREVIEW','CONFIRMADO'],true)) throw new DomainException('Preview ainda não publicado.');
            $op=$this->db->operacao($u,$key); self::conferirOperacao($op,$hash,'CONFIRMAR');
            if (!$op) {
                $view=$this->db->sql("SELECT id FROM pagamento_fechamento_documento_operacao WHERE documento_id=? AND autor_id=? AND tipo='VISUALIZAR' AND estado='CONCLUIDA' LIMIT 1",[$id,$u]);
                if (!$view) throw new DomainException('Visualize este documento antes de confirmar.');
                $op=$this->db->reservarOperacao($id,$u,$key,'CONFIRMAR',$hash,$request,$this->db->resumo($doc));
            }
            return ['doc'=>$doc,'op'=>$op];
        });
        return $this->files->comLock($reserved['doc']['uuid'],function() use($id,$u,$key) {
            $doc=$this->db->documento($id); $this->db->vinculo($doc); $this->db->autorizar($u);
            $bytes=$this->files->ler($doc,$doc['estado']==='CONFIRMADO');
            if ($doc['estado']==='PREVIEW') $this->files->publicar($doc,$bytes);
            return $this->db->transacao(function() use($id,$u,$key) {
                $doc=$this->db->documento($id,true); $this->db->autorizar($u,true); $this->db->vinculo($doc);
                $this->files->ler($doc,$doc['estado']==='CONFIRMADO');
                if ($doc['estado']==='PREVIEW') $this->db->sql("UPDATE pagamento_fechamento_documento SET estado='CONFIRMADO',confirmado_por=?,confirmado_em=UTC_TIMESTAMP(6) WHERE id=?",[$u,$id]);
                $doc=$this->db->documento($id); $op=$this->db->operacao($u,$key); $result=$this->db->resumo($doc); $this->db->concluir($op,$result);
                return $result;
            });
        });
    }
    public function obter(int $id,int $u): array
    {
        $this->db->autorizar($u); $d=$this->db->documento($id); $this->db->vinculo($d);
        if ($d['estado']!==null) $this->files->ler($d,$d['estado']==='CONFIRMADO');
        return $this->db->resumo($d);
    }
    public function listar(int $f,int $u): array
    {
        $this->db->autorizar($u);
        return array_map(function($d) { $this->db->vinculo($d); return $this->db->resumo($d); },$this->db->sql('SELECT * FROM pagamento_fechamento_documento WHERE fechamento_id=? ORDER BY numero',[$f]));
    }
    public function diagnosticar(int $id,int $u): array
    {
        $d=$this->obter($id,$u); $raw=$this->db->documento($id); $r=$this->db->vinculo($raw);
        $modelo=json_decode($raw['modelo_json'],true,512,JSON_THROW_ON_ERROR);
        return ['metadata'=>$d,'snapshot_total_centavos'=>$r['snapshot']['composicao']['total_final_centavos'],'documento_total_centavos'=>$modelo['total_centavos'],
            'total_igual'=>$modelo['total_centavos']===$r['snapshot']['composicao']['total_final_centavos'],'servicos'=>count($modelo['servicos']),'rubricas'=>count($modelo['rubricas']),
            'modelo_version'=>$modelo['version'],'template_hash'=>$raw['template_hash']];
    }
    public function pendentes(int $u): array
    {
        $this->db->autorizar($u);
        return $this->db->sql("SELECT id,documento_id,tipo,chave,criado_em FROM pagamento_fechamento_documento_operacao WHERE autor_id=? AND estado='RESERVADA' ORDER BY id",[$u]);
    }
    public function recuperar(int $opId,int $u): array
    {
        $this->db->autorizar($u);
        $op=$this->db->sql('SELECT * FROM pagamento_fechamento_documento_operacao WHERE id=? AND autor_id=?',[$opId,$u])[0]??null;
        if (!$op) throw new DomainException('Operação de recuperação não encontrada para este ator.');
        $r=json_decode($op['request_json'],true,512,JSON_THROW_ON_ERROR);
        return match($op['tipo']) {
            'GERAR'=>$this->gerarPreview($r['fechamento_id'],$r['revision_id'],$u,$op['chave'],$r['data_documental']),
            'CONFIRMAR'=>$this->confirmar($r['document_id'],$r['revision_id'],$r['pdf_hash'],$u,$op['chave']),
            default=>throw new DomainException('Visualização requer nova leitura explícita.')
        };
    }
}
