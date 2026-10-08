<?php

require_once __DIR__ . '/FechamentoRevisaoService.php';
require_once __DIR__ . '/FechamentoDocumentoService.php';

/** Transporte/presentação da 1D. Nenhuma regra, soma ou mutação de ledger. */
final class FechamentoInterfaceService
{
    private FechamentoRevisaoService $financeiro;
    private FechamentoRevisaoRepository $repo;
    private FechamentoDocumentoService $documental;

    public function __construct(private mysqli $conn, string $root, private int $usuario, private bool $mensal = false)
    {
        $this->repo = new FechamentoRevisaoRepository($conn);
        $this->repo->autorizar($usuario);
        $this->repo->conferirEstrutura();
        if ($mensal && (int)$this->repo->sql("SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='colaborador' AND COLUMN_NAME IN ('tipo_remuneracao','participa_fechamento_mensal')")[0]['n'] !== 2) {
            throw new RuntimeException('Migration mensal ausente.');
        }
        (new FechamentoDocumentoRepository($conn))->conferir();
        $this->financeiro = new FechamentoRevisaoService($conn, $mensal);
        $this->documental = new FechamentoDocumentoService($conn, $root);
    }

    public function contexto(int $b, string $ref): array
    {
        FechamentoFinanceiroRules::periodo($ref);
        $c = $this->repo->sql('SELECT idcolaborador,nome_colaborador FROM colaborador WHERE idcolaborador=?', [$b])[0] ?? null;
        if (!$c) {
            throw new InvalidArgumentException('Colaborador não encontrado.');
        }
        return ['colaborador_id' => $b, 'competencia' => $ref, 'nome' => $c['nome_colaborador']];
    }

    /** Centavos como strings decimais no HTTP: preservar inteiros acima de 2^53 no JS. */
    private static function quantias(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = self::quantias($value);
            } elseif (is_int($value) && is_string($key) && str_ends_with($key, '_centavos')) {
                $value = (string)$value;
            }
        }
        return $data;
    }

    private function revisaoEscopo(int $id, int $b, string $ref): array
    {
        $r = $this->financeiro->obterRevisao($id, $this->usuario);
        if ($r['colaborador_id'] !== $b || $r['competencia'] !== $ref) {
            throw new DomainException('Revisão de outro fechamento.');
        }
        return $r;
    }

    private function projetar(array $r, int $latest): array
    {
        $c = $r['snapshot']['composicao'];
        $resumo = $c['componentes'];
        if (!$c['financeiro_servicos']['subtotal_servicos_completo']) {
            $resumo['SERVICOS'] = null;
        }
        foreach ($resumo as &$value) {
            if ($value !== null) {
                $value = (string)$value;
            }
        }
        unset($value);
        $fixo = array_intersect_key($c['fixo'], array_flip(['estado','configurado_centavos','utilizado_centavos','pago_centavos',
            'saldo_centavos','estado_liquidacao','origem','override','decisao','evidencias_liquidacao']));
        $fixo['ato_valor'] = $r['snapshot']['decisoes']['FIXO']['dados']['registro'] ?? null;
        $fixo['ato_liquidacao'] = $r['snapshot']['decisoes']['LIQUIDACAO']['dados']['registro'] ?? null;
        $pendencias = [];
        foreach ($c['pendencias'] as $p) {
            // A evidência financeira útil fica no detalhe dos serviços/fixo; não expor payload jurídico legado.
            $pendencias[] = array_intersect_key($p, array_flip(['codigo','componente','identidade','valores','mensagem','bloqueante','severidade']));
        }
        $meta = array_intersect_key($r, array_flip(['id','fechamento_id','colaborador_id','competencia','numero','version','estado','autor_id','criado_em_utc','snapshot_hash']));
        return $meta + [
            'monthly_rule_version' => $c['monthly_rule_version'] ?? null,
            'tipo_remuneracao' => $c['tipo_remuneracao'] ?? null,
            'bonus_produtividade' => self::quantias($c['bonus_produtividade'] ?? []),
            'situacao' => $c['situacao'], 'snapshot_em' => $c['snapshot_em'],
            'resumo' => $resumo, 'total_final_centavos' => $c['total_final_centavos'] === null ? null : (string)$c['total_final_centavos'],
            'total_final_determinado' => $c['total_final_determinado'],
            'financeiro_servicos' => self::quantias($c['financeiro_servicos']), 'fixo' => self::quantias($fixo),
            'extras' => self::quantias(array_intersect_key($c['extras'], array_flip(['estado','decisao','itens','subtotal_centavos']))),
            'acompanhamento_especial' => self::quantias($c['acompanhamento_especial']),
            'pendencias' => self::quantias($pendencias),
            'desconto' => self::quantias($c['desconto'] ?? []),
            'retiradas' => $c['retiradas'] ?? [],
            'acoes' => ['decidir' => $r['version'] === $latest, 'gerar_preview' => $r['estado'] === 'PRONTO',
                'fixo' => ['CADASTRO','SEM_VALOR_FIXO','OVERRIDE'], 'bonus' => ['SEM_BONUS','DEFINIDO','PENDENTE'],
                'reconciliar_fixo' => $r['version'] === $latest],
        ];
    }

    public function obter(int $b, string $ref, ?int $revision = null): array
    {
        $contexto = $this->contexto($b, $ref);
        $list = $this->financeiro->listarRevisoes($b, $ref, $this->usuario);
        $latest = $list ? (int)$list[count($list) - 1]['numero'] : 0;
        $id = $revision ?? ($list ? (int)$list[count($list) - 1]['id'] : null);
        return $contexto + ['latest_version' => $latest, 'revisao' => $id === null ? null : $this->projetar($this->revisaoEscopo($id, $b, $ref), $latest)];
    }

    public function mensal(string $ref, bool $iniciar = false, string $key = ''): array
    {
        FechamentoFinanceiroRules::periodo($ref);
        $cycle=new FechamentoCompetenciaService($this->conn,$this->usuario);
        if (FechamentoCompetenciaService::disponivel($this->conn) && pagamento_competencia_nova($ref)) {
            if ($iniciar) $cycle->criar($ref);
            $c=$cycle->resumo($ref);
            if ($iniciar && $c['estado']!=='CONCLUIDO') {
                foreach($c['colaboradores'] as $p) {
                    if ($p['preparado']) continue;
                    $f=$this->obter($p['colaborador_id'],$ref);
                    $this->preparar($p['colaborador_id'],$ref,$f['latest_version'],'ciclo:'.$c['ciclo_id'].':'.$p['colaborador_id']);
                }
            }
            return $cycle->resumo($ref);
        }
        $rows = $this->repo->sql('SELECT idcolaborador,nome_colaborador,tipo_remuneracao FROM colaborador WHERE ativo=1 AND participa_fechamento_mensal=1 ORDER BY nome_colaborador,idcolaborador');
        $configuracao = $this->repo->sql('SELECT idcolaborador AS colaborador_id,nome_colaborador AS nome FROM colaborador WHERE ativo=1 AND participa_fechamento_mensal IS NULL ORDER BY nome_colaborador,idcolaborador');
        foreach ($configuracao as &$cadastro) {
            $cadastro['colaborador_id'] = (int)$cadastro['colaborador_id'];
        }
        unset($cadastro);
        $items = [];
        $counts = ['NAO_REVISADO' => 0,'ATENCAO' => 0,'CONFIRMADO' => 0];
        foreach ($rows as $row) {
            $b = (int)$row['idcolaborador'];
            $error = null;
            $f = $this->obter($b, $ref);
            $r = $f['revisao'];
            $docs = $this->listarDocumentos($b, $ref)['documentos'];
            $confirmed = $r && $r['monthly_rule_version'] === 'fechamento_mensal_v1' && array_filter($docs, fn ($d) => $d['revision_id'] === $r['id'] && $d['estado'] === 'CONFIRMADO');
            if ($iniciar && !$confirmed) {
                try {
                    $operationKey = 'mensal:'.$key.':'.$b;
                    $previous = $this->repo->sql('SELECT o.revisao_id FROM pagamento_fechamento_operacao o JOIN pagamento_fechamento f ON f.id=o.fechamento_id WHERE f.colaborador_id=? AND f.competencia=? AND o.chave=? AND o.autor_id=?', [$b,$ref,$operationKey,$this->usuario]);
                    if (!$previous) {
                        $this->preparar($b, $ref, $f['latest_version'], $operationKey);
                    }
                    $f = $this->obter($b, $ref);
                    $r = $f['revisao'];
                } catch (Throwable $e) {
                    $error = 'Não foi possível preparar este colaborador. Recarregue e tente novamente.';
                }
            }
            $monthly = $r && $r['monthly_rule_version'] === 'fechamento_mensal_v1';
            $status = $confirmed ? 'CONFIRMADO' : ($error || ($monthly && !$r['total_final_determinado']) || !$row['tipo_remuneracao'] ? 'ATENCAO' : 'NAO_REVISADO');
            $counts[$status]++;
            $items[] = ['colaborador_id' => $b,'nome' => $row['nome_colaborador'],'tipo_remuneracao' => $row['tipo_remuneracao'],
                'status' => $status,'preparado' => (bool)$monthly,'total_centavos' => $monthly ? $r['total_final_centavos'] : null,
                'pendencias' => $error ? [$error] : ($monthly ? array_column($r['pendencias'], 'mensagem') : (!$row['tipo_remuneracao'] ? ['Forma de remuneração não configurada.'] : []))];
        }
        return ['competencia' => $ref,'colaboradores' => $items,'contagens' => $counts,'quantidade' => count($items),'pendencias_configuracao' => $configuracao];
    }

    public function revisoes(int $b, string $ref): array
    {
        $this->contexto($b, $ref);
        return $this->financeiro->listarRevisoes($b, $ref, $this->usuario);
    }

    public function preparar(int $b, string $ref, int $expected, string $key): array
    {
        $this->contexto($b, $ref);
        $r = $this->financeiro->prepararRevisao($b, $ref, $this->usuario, $expected, $key);
        return $this->obter($b, $ref, $r['id']); // Retry mantém a revisão original, nunca seleciona a mais nova.
    }

    public function decidir(int $b, string $ref, int $expected, string $key, string $tipo, array $input): array
    {
        $this->contexto($b, $ref);
        $r = $this->financeiro->decidir($b, $ref, $this->usuario, $expected, $key, $tipo, $input);
        return $this->obter($b, $ref, $r['id']);
    }

    private function documentoEscopo(int $id, int $b, string $ref): array
    {
        $doc = $this->documental->obter($id, $this->usuario);
        $this->revisaoEscopo($doc['revision_id'], $b, $ref);
        return $doc;
    }

    private function documentoPublico(array $doc): array
    {
        unset($doc['arquivo_preview'],$doc['arquivo_definitivo']);
        $r = $this->financeiro->obterRevisao($doc['revision_id'], $this->usuario);
        $view = $this->repo->sql("SELECT id FROM pagamento_fechamento_documento_operacao WHERE documento_id=? AND autor_id=? AND tipo='VISUALIZAR' AND estado='CONCLUIDA' LIMIT 1", [$doc['document_id'],$this->usuario]);
        return $doc + ['numero_revisao' => $r['numero'],
            'total_centavos' => (string)$r['snapshot']['composicao']['total_final_centavos'],
            'visualizado_pelo_usuario' => (bool)$view,
            'acoes' => ['visualizar' => $doc['estado'] !== null, 'confirmar' => $doc['estado'] === 'PREVIEW' && (bool)$view]];
    }

    public function gerar(int $b, string $ref, int $f, int $revision, string $key): array
    {
        $r = $this->revisaoEscopo($revision, $b, $ref);
        if ($r['fechamento_id'] !== $f) {
            throw new DomainException('Revisão de outro fechamento.');
        }
        return $this->documentoPublico($this->documental->gerarPreview($f, $revision, $this->usuario, $key));
    }

    public function visualizar(int $b, string $ref, int $doc, string $key): array
    {
        $this->documentoEscopo($doc, $b, $ref);
        $result = $this->documental->visualizar($doc, $this->usuario, $key);
        $result['metadata'] = $this->documentoPublico($result['metadata']);
        return $result;
    }

    public function confirmar(int $b, string $ref, int $doc, int $revision, string $hash, string $key): array
    {
        $this->documentoEscopo($doc, $b, $ref);
        return $this->documentoPublico($this->documental->confirmar($doc, $revision, $hash, $this->usuario, $key));
    }

    public function listarDocumentos(int $b, string $ref): array
    {
        $this->contexto($b, $ref);
        $f = $this->repo->sql('SELECT id FROM pagamento_fechamento WHERE colaborador_id=? AND competencia=?', [$b,$ref])[0] ?? null;
        if (!$f) {
            return ['documentos' => [], 'operacoes_pendentes' => []];
        }
        $docs = array_map(fn ($d) => $this->documentoPublico($d), $this->documental->listar((int)$f['id'], $this->usuario));
        $ops = $this->repo->sql("SELECT o.id,o.documento_id,o.tipo,o.chave,o.criado_em FROM pagamento_fechamento_documento_operacao o JOIN pagamento_fechamento_documento d ON d.id=o.documento_id WHERE d.fechamento_id=? AND o.autor_id=? AND o.estado='RESERVADA' ORDER BY o.id", [$f['id'],$this->usuario]);
        foreach ($ops as &$op) {
            $op['recuperavel'] = in_array($op['tipo'], ['GERAR','CONFIRMAR'], true);
        }
        return ['documentos' => $docs, 'operacoes_pendentes' => $ops];
    }

    public function recuperar(int $b, string $ref, int $operation, string $key): array
    {
        $op = $this->repo->sql('SELECT documento_id,chave FROM pagamento_fechamento_documento_operacao WHERE id=? AND autor_id=?', [$operation,$this->usuario])[0] ?? null;
        if (!$op || !hash_equals($op['chave'], $key)) {
            throw new DomainException('Operação de recuperação não encontrada para este ator.');
        }
        $this->documentoEscopo((int)$op['documento_id'],$b,$ref);
        return $this->documentoPublico($this->documental->recuperar($operation,$this->usuario));
    }
}
