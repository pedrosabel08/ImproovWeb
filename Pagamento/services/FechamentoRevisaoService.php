<?php

require_once __DIR__ . '/FechamentoRevisaoRepository.php';
require_once __DIR__ . '/FechamentoFinanceiroRepository.php';
require_once __DIR__ . '/FechamentoComposicaoRepository.php';
require_once __DIR__ . '/FechamentoComposicaoRules.php';
require_once __DIR__ . '/FechamentoCompetenciaService.php';
require_once __DIR__ . '/FechamentoRetiradaRules.php';

/** 1C-A: atos auditados + revisão atômica. Usuário vem de sessão validada/CLI confiável. */
final class FechamentoRevisaoService
{
    private FechamentoRevisaoRepository $storage;
    private FechamentoFinanceiroRepository $fontes;

    public function __construct(private mysqli $conn, private bool $mensal = false)
    {
        FechamentoCompetenciaService::disponivel($conn); // Antes da transação: não antecipar a read view de um retry.
        $this->storage = new FechamentoRevisaoRepository($conn);
        $this->fontes = new FechamentoFinanceiroRepository($conn,$mensal);
    }

    private static function texto($value, int $max, string $campo): string
    {
        if (!is_string($value) || trim($value)==='' || strlen($value)>$max) throw new InvalidArgumentException($campo . ' obrigatório/inválido.');
        return trim($value);
    }

    private static function registro(int $b, string $ref, int $u, string $iso, string $classe, string $referencia, string $motivo): array
    {
        return ['colaborador_id'=>$b,'competencia'=>$ref,'classe'=>$classe,'autor_id'=>$u,'registrado_em'=>$iso,'referencia'=>$referencia,'motivo'=>$motivo];
    }

    private function contexto(array $base, array $decisoes): array
    {
        $base['fixo']['override'] = $decisoes['FIXO']['dados']['override'] ?? null;
        $base['fixo']['decisao'] = $decisoes['FIXO']['dados']['decisao'] ?? null;
        $base['fixo']['evidencias_liquidacao'] = $decisoes['LIQUIDACAO']['dados']['evidencias'] ?? [];
        $base['extras'] = $decisoes['BONUS']['dados'] ?? ['estado'=>'PENDENTE','itens'=>[]];
        $base['desconto'] = $decisoes['DESCONTO']['dados'] ?? null;
        $base['retiradas'] = $decisoes['SERVICOS']['dados'] ?? [];
        $base['persistencia'] = ['adapter'=>'pagamento_fechamento_1ca_v1','decisoes_ids'=>array_map(fn($d)=>$d['id'],$decisoes),
            'cadastro'=>'colaborador.valor_fixo observado no snapshot','ledger'=>'somente leitura','legado'=>'evidência informativa, sem inferência'];
        return $base;
    }

    private function ato(string $tipo, array $input, array $base, int $b, string $ref, int $u, string $iso, string $key): array
    {
        $motivo = self::texto($input['motivo'] ?? null,1000,'Motivo');
        if ($tipo==='DESCONTO') {
            if (!$this->mensal) throw new DomainException('Desconto disponível somente no fechamento mensal.');
            $valor=FechamentoComposicaoSupport::valor($input);
            if ($valor<0) throw new InvalidArgumentException('Desconto deve ser não negativo.');
            return ['valor_centavos'=>$valor,'motivo'=>$motivo,'autor_id'=>$u,'registrado_em'=>$iso,'referencia'=>'desconto:'.$key];
        }
        $r = self::registro($b,$ref,$u,$iso,$tipo==='BONUS'?'BONUS_EXTRAS':'VALOR_FIXO','ato:'.$key,$motivo);
        if ($tipo==='FIXO') {
            $config = $base['fixo']['configurado'];
            $original = $config===null ? null : FechamentoComposicaoSupport::moeda($config);
            $estado = $input['estado'] ?? null;
            $out = ['estado'=>$estado==='SEM_VALOR_FIXO' ? 'SEM_VALOR_FIXO' : ($estado==='CADASTRO' && $original===null ? 'NAO_DEFINIDO':'DEFINIDO'),
                'modo'=>$estado,'configurado_observado'=>$config,'original_centavos'=>$original,'utilizado_centavos'=>$original,'registro'=>$r];
            if ($estado==='OVERRIDE') {
                $valor = FechamentoComposicaoSupport::valor($input);
                if ($valor<0) throw new InvalidArgumentException('Override deve ser não negativo.');
                $out['override']=$r+['original_centavos'=>$original,'substituto_centavos'=>$valor];
                $out['utilizado_centavos']=$valor;
            } elseif ($estado==='SEM_VALOR_FIXO') { $out['decisao']=$r+['estado'=>'SEM_VALOR_FIXO','original_centavos'=>$original]; $out['utilizado_centavos']=0; }
            elseif ($estado!=='CADASTRO') throw new InvalidArgumentException('Estado de fixo inválido.');
            return $out;
        }
        if ($tipo==='BONUS') {
            $estado = $input['estado'] ?? null;
            if (!in_array($estado,['PENDENTE','SEM_BONUS','DEFINIDO'],true)) throw new InvalidArgumentException('Estado de bônus inválido.');
            $itens = $input['itens'] ?? [];
            if (!is_array($itens) || !array_is_list($itens)) throw new InvalidArgumentException('Lista de extras inválida.');
            $out = ['estado'=>$estado,'decisao'=>$r+['estado'=>$estado],'itens'=>[]];
            foreach ($itens as $item) {
                if (!is_array($item)) throw new InvalidArgumentException('Extra inválido.');
                $out['itens'][] = self::registro($b,$ref,$u,$iso,'BONUS_EXTRAS',self::texto($item['referencia']??null,191,'Referência'),$motivo)
                    + ['categoria'=>self::texto($item['categoria']??null,160,'Categoria'),'valor_centavos'=>FechamentoComposicaoSupport::valor($item)];
            }
            if ($estado==='PENDENTE' && $itens) throw new InvalidArgumentException('PENDENTE não aceita extras.');
            $result = (new FechamentoExtrasRules())->calcular($out,$b,$ref,FechamentoComposicaoSupport::instante($iso));
            foreach ($result['pendencias'] as $p) if ($p['codigo']!=='BONUS_PENDENTE') throw new InvalidArgumentException('Bônus/rubricas inválidos: '.$p['codigo']);
            return $out;
        }
        if ($tipo==='LIQUIDACAO') {
            $evidencias = $input['evidencias'] ?? null;
            if (!is_array($evidencias) || !array_is_list($evidencias)) throw new InvalidArgumentException('Conjunto de evidências obrigatório.');
            $estado = $input['estado'] ?? 'EVIDENCIADA';
            if (!in_array($estado,['INDETERMINADA','EVIDENCIADA'],true) || ($estado==='INDETERMINADA') !== (!$evidencias)) throw new InvalidArgumentException('Estado de evidência incompatível.');
            $out = ['estado'=>$estado,'registro'=>$r,'evidencias'=>[]];
            foreach ($evidencias as $item) {
                if (!is_array($item)) throw new InvalidArgumentException('Evidência inválida.');
                $out['evidencias'][]=self::registro($b,$ref,$u,$iso,'VALOR_FIXO',self::texto($item['referencia']??null,191,'Referência'),$motivo)
                    + ['tipo'=>$item['tipo']??null,'valor_centavos'=>FechamentoComposicaoSupport::valor($item),
                        'origem_verificavel'=>self::texto($item['origem_verificavel']??null,1000,'Origem verificável')];
            }
            $result = (new FechamentoFixoRules())->calcular(['configurado'=>$base['fixo']['configurado'],'evidencias_liquidacao'=>$out['evidencias']],$b,$ref,FechamentoComposicaoSupport::instante($iso));
            if ($result['evidencias_invalidas']) throw new InvalidArgumentException('Evidências inválidas/conflitantes.');
            return $out;
        }
        throw new InvalidArgumentException('Tipo de ato inválido.');
    }

    public function prepararRevisao(int $b, string $ref, int $usuario, int $expectedVersion, string $key): array
    {
        return $this->executar($b,$ref,$usuario,$expectedVersion,$key,'PREPARAR',[]);
    }

    /** Substitui o conjunto vigente de um componente e publica nova revisão atomicamente. */
    public function decidir(int $b, string $ref, int $usuario, int $expectedVersion, string $key, string $tipo, array $input): array
    {
        if (!in_array($tipo,['FIXO','BONUS','LIQUIDACAO','DESCONTO','SERVICOS'],true)) throw new InvalidArgumentException('Ato financeiro inválido.');
        return $this->executar($b,$ref,$usuario,$expectedVersion,$key,$tipo,$input);
    }

    private function executar(int $b, string $ref, int $usuario, int $expected, string $key, string $tipo, array $input): array
    {
        FechamentoFinanceiroRules::periodo($ref);
        if ($b<=0 || $usuario<=0 || $expected<0 || !preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D',$key)) throw new InvalidArgumentException('Identidade/versão/chave inválida.');
        $this->storage->conferirEstrutura();
        $this->storage->autorizar($usuario); // Revalidado sob lock antes de qualquer publicação.
        $request=['beneficiario'=>$b,'competencia'=>$ref,'usuario'=>$usuario,'expected_version'=>$expected,'tipo'=>$tipo,'input'=>$input];
        if ($this->mensal) $request['fluxo']=FechamentoMensalRules::VERSION;
        $hash = FechamentoSnapshot::hash($request);
        return $this->fontes->persistir($b,$ref,
            function() use ($b,$ref,$usuario,$expected,$key,$hash) {
                $capturado=(new FechamentoCompetenciaService($this->conn,$usuario))->permitirRevisao($b,$ref);
                $f=$this->storage->bloquear($b,$ref,$usuario);
                if ($this->mensal && !$capturado) {
                    $cadastro=$this->storage->sql('SELECT ativo,participa_fechamento_mensal FROM colaborador WHERE idcolaborador=? FOR UPDATE',[$b])[0]??null;
                    if (!$cadastro || (int)$cadastro['ativo']!==1 || $cadastro['participa_fechamento_mensal']===null || (int)$cadastro['participa_fechamento_mensal']!==1) throw new DomainException('O colaborador precisa estar ativo e participar explicitamente do fechamento mensal.');
                }
                $op=$this->storage->operacao((int)$f['id'],$key);
                if ($op) {
                    if ((int)$op['autor_id']!==$usuario || !hash_equals($op['request_hash'],$hash)) throw new DomainException('Chave de idempotência reutilizada com outro conteúdo/autor.');
                    $f['retry_revisao_id']=(int)$op['revisao_id'];
                } elseif ((int)$f['lock_version']!==$expected) throw new DomainException('STALE_VERSION: atualize a revisão antes de decidir.');
                return $f;
            },
            function(mysqli $conn, array $leitura, array $f) use ($b,$ref,$usuario,$key,$hash,$tipo,$input) {
                if (isset($f['retry_revisao_id'])) return $this->storage->revisao($f['retry_revisao_id']);
                $snapshot=$leitura['snapshot']; $utc=$snapshot->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
                $iso=$snapshot->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d\TH:i:s.uP');
                $base=(new FechamentoComposicaoRepository())->carregarComplementos($conn,$b,$ref,$snapshot,$this->mensal);
                $decisoes=$this->storage->decisoes((int)$f['id']);
                $servicos=(new FechamentoFinanceiroRules())->calcular($leitura['dados'],$b,$ref,$snapshot,$this->mensal);
                $servicos['consistencia']=$leitura['consistencia'];
                $motivo=$tipo==='PREPARAR' ? 'Preparação explícita de revisão financeira' : self::texto($input['motivo']??null,1000,'Motivo');
                if ($tipo!=='PREPARAR') {
                    if ($tipo==='SERVICOS' && !$this->mensal) throw new DomainException('Retiradas disponíveis somente no fechamento mensal.');
                    $depois=$tipo==='SERVICOS' ? FechamentoRetiradaRules::decidir($decisoes['SERVICOS']['dados']??[],$input,$servicos,$usuario,$iso) : $this->ato($tipo,$input,$base,$b,$ref,$usuario,$iso,$key);
                    $antes=$decisoes[$tipo]['dados'] ?? ($tipo==='BONUS' ? ['estado'=>'PENDENTE','itens'=>[]] : ($tipo==='FIXO' ? ['configurado_observado'=>$base['fixo']['configurado'],'origem'=>'CADASTRO'] : ['estado'=>'INDETERMINADA','evidencias'=>[]]));
                    $id=$this->storage->salvarDecisao((int)$f['id'],$tipo,$usuario,$utc,$motivo,$antes,$depois);
                    $decisoes[$tipo]=['id'=>$id,'dados'=>$depois];
                }
                $contexto=$this->contexto($base,$decisoes);
                if ($this->mensal) $servicos=FechamentoRetiradaRules::aplicar($servicos,$contexto['retiradas']);
                $composicao=(new FechamentoComposicaoRules())->compor($servicos,$contexto);
                if ($tipo==='FIXO' && array_filter($composicao['fixo']['pendencias'],fn($p)=>in_array($p['codigo'],['FIXO_OVERRIDE_INVALIDO','FIXO_INVALIDO'],true))) throw new InvalidArgumentException('Decisão de fixo inválida.');
                $completo=['schema_version'=>'pagamento_fechamento_snapshot_v1','canonical_version'=>FechamentoSnapshot::VERSION,
                    'autor_id'=>$usuario,'snapshot_em'=>$iso,'timezone'=>'America/Sao_Paulo','dados_servicos'=>$leitura['dados'],
                    'contexto_composicao'=>$contexto,'decisoes'=>$decisoes,'composicao'=>$composicao];
                $rev=$this->storage->salvarRevisao($f,$completo,array_map(fn($d)=>$d['id'],$decisoes),$usuario,$utc);
                $this->storage->registrarOperacao($f,$key,$hash,$tipo,$usuario,$utc,$motivo,$rev);
                $cycle=new FechamentoCompetenciaService($conn,$usuario);
                $cab=$cycle->cabecalho($ref);
                if ($cab) $cycle->sincronizar((int)$cab['id']);
                return $this->storage->revisao($rev);
            });
    }

    public function obterRevisao(int $id, int $usuario): array
    {
        $this->storage->autorizar($usuario);
        return $this->storage->revisao($id);
    }

    public function listarRevisoes(int $b, string $ref, int $usuario): array
    {
        FechamentoFinanceiroRules::periodo($ref); $this->storage->autorizar($usuario);
        return $this->storage->sql('SELECT r.id,r.numero,r.estado,r.snapshot_hash,r.criado_em,r.criado_por FROM pagamento_fechamento_revisao r JOIN pagamento_fechamento f ON f.id=r.fechamento_id WHERE f.colaborador_id=? AND f.competencia=? ORDER BY r.numero',[$b,$ref]);
    }

    /** Diagnóstico READ ONLY: nenhuma revisão ou decisão é criada. */
    public function compararRevisao(int $id, int $usuario): array
    {
        $anterior=$this->obterRevisao($id,$usuario); $b=$anterior['colaborador_id']; $ref=$anterior['competencia'];
        $leitura=$this->fontes->carregar($b,$ref,function(mysqli $conn,array $dados,DateTimeImmutable $snapshot) use ($b,$ref,$usuario,$anterior) {
            $this->storage->autorizar($usuario);
            $base=(new FechamentoComposicaoRepository())->carregarComplementos($conn,$b,$ref,$snapshot,$this->mensal);
            return $this->contexto($base,$this->storage->decisoes($anterior['fechamento_id']));
        });
        $servicos=(new FechamentoFinanceiroRules())->calcular($leitura['dados'],$b,$ref,$leitura['snapshot'],$this->mensal);
        $servicos['consistencia']=$leitura['consistencia'];
        $novo=(new FechamentoComposicaoRules())->compor($servicos,$leitura['adicional']);
        $velho=$anterior['snapshot']['composicao'];
        $campos=['componentes','componentes_conhecidos_centavos','total_final_centavos','total_final_determinado','bloqueado','situacao'];
        $diff=[]; foreach ($campos as $campo) if ($velho[$campo]!==$novo[$campo]) $diff[$campo]=['persistido'=>$velho[$campo],'atual'=>$novo[$campo]];
        $estruturais=[];
        foreach (['financeiro_servicos','fixo','extras','acompanhamento_especial','pendencias'] as $campo) {
            $a=$velho[$campo]; $n=$novo[$campo];
            if ($campo==='financeiro_servicos') { unset($a['snapshot_em'],$a['consistencia'],$n['snapshot_em'],$n['consistencia']); }
            $ha=FechamentoSnapshot::hash($a); $hn=FechamentoSnapshot::hash($n);
            if ($ha!==$hn) $estruturais[$campo]=['persistido_hash'=>$ha,'atual_hash'=>$hn];
        }
        return ['revisao_id'=>$id,'snapshot_hash_preservado'=>$anterior['snapshot_hash'],'snapshot_em_atual'=>$novo['snapshot_em'],
            'diferencas'=>$diff,'alteracoes_estruturais'=>$estruturais,'pendencias_persistidas'=>$velho['pendencias'],'pendencias_atuais'=>$novo['pendencias'],
            'composicao_atual'=>$novo];
    }
}
