<?php

require_once __DIR__.'/FechamentoRevisaoRepository.php';
require_once __DIR__.'/FechamentoSnapshot.php';
require_once __DIR__.'/FechamentoFinanceiroRules.php';
require_once __DIR__.'/../PagamentoService.php';
require_once __DIR__.'/../../helpers/pagamento_competencia_helper.php';

/** Uma competência oficial; revisões financeiras/documentos e ledger continuam nas tabelas existentes. */
final class FechamentoCompetenciaService
{
    private FechamentoRevisaoRepository $db;
    public function __construct(private mysqli $conn, private int $usuario)
    {
        $this->db = new FechamentoRevisaoRepository($conn);
        self::disponivel($conn);
    }
    public static function disponivel(mysqli $conn): bool
    {
        static $cache = null;
        $cache ??= new WeakMap();
        if (!isset($cache[$conn])) {
            $cache[$conn] = (int)$conn->query("SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagamento_competencia'")->fetch_assoc()['n'] === 1;
        }
        return $cache[$conn];
    }
    public function cabecalho(string $ref, bool $lock = false): ?array
    {
        pagamento_competencia_nova($ref);
        if (!self::disponivel($this->conn)) {
            return null;
        }
        return $this->db->sql('SELECT * FROM pagamento_competencia WHERE competencia=?'.($lock ? ' FOR UPDATE' : ''), [$ref])[0] ?? null;
    }
    /** Chamado dentro das transações existentes, antes do lock individual. */
    public function permitirRevisao(int $b, string $ref): bool
    {
        $c = $this->cabecalho($ref, true);
        if (!$c) {
            return false;
        }
        if ($c['estado'] === 'CONCLUIDO') {
            throw new DomainException('Fechamento concluído. Os valores desta competência estão congelados.');
        }
        if (!$this->db->sql('SELECT f.id FROM pagamento_competencia_colaborador m JOIN pagamento_fechamento f ON f.id=m.fechamento_id WHERE m.competencia_id=? AND f.colaborador_id=?', [$c['id'],$b])) {
            throw new DomainException('Colaborador fora do snapshot desta competência.');
        }
        return true;
    }
    private function transacao(callable $fn): array
    {
        $this->db->autorizar($this->usuario);
        $this->conn->begin_transaction();
        try {
            $r = $fn();
            $this->conn->commit();
            return $r;
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    private function auditar(int $c, ?int $f, string $tipo, string $key, array $antes, array $depois, array $request = []): void
    {
        $this->db->sql(
            'INSERT INTO pagamento_competencia_evento (competencia_id,fechamento_id,tipo,chave,request_hash,usuario_id,criado_em,antes_json,depois_json) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP(6),?,?)',
            [$c,$f,$tipo,$key,FechamentoSnapshot::hash($request),$this->usuario,FechamentoSnapshot::json($antes),FechamentoSnapshot::json($depois)]
        );
    }
    private function retry(int $c, string $key, array $request): bool
    {
        if (!preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $key)) {
            throw new InvalidArgumentException('Chave inválida.');
        }
        $r = $this->db->sql('SELECT usuario_id,request_hash FROM pagamento_competencia_evento WHERE competencia_id=? AND chave=?', [$c,$key])[0] ?? null;
        if (!$r) {
            return false;
        }
        if ((int)$r['usuario_id'] !== $this->usuario || !hash_equals($r['request_hash'], FechamentoSnapshot::hash($request))) {
            throw new DomainException('Chave de idempotência reutilizada com outro conteúdo.');
        }
        return true;
    }
    public function atualizarPrevisao(string $ref): array
    {
        return $this->transacao(function () use ($ref) {
            $c = $this->cabecalho($ref, true);
            if (!$c) {
                throw new DomainException('Competência não encontrada.');
            }
            $nova = pagamento_previsao($ref);
            if ($c['estado'] === 'EM_ANDAMENTO' && $c['previsto_em'] !== $nova) {
                $this->auditar((int)$c['id'], null, 'PREVISAO_ALTERADA', 'previsao:sabado:'.$nova, ['previsto_em' => $c['previsto_em']], ['previsto_em' => $nova,'regra' => 'SABADO_INCLUIDO']);
                $this->db->sql('UPDATE pagamento_competencia SET previsto_em=? WHERE id=?', [$nova,$c['id']]);
                $this->pendencias($this->cabecalho($ref));
            }
            if ($c['estado'] === 'EM_ANDAMENTO') {
                $this->pendencias($this->cabecalho($ref));
            }
            return $this->resumo($ref);
        });
    }
    public function criar(string $ref): array
    {
        if (!pagamento_competencia_nova($ref)) {
            throw new DomainException('Competência anterior ao início do novo fluxo.');
        }
        if (!self::disponivel($this->conn)) {
            throw new RuntimeException('Migration da competência ausente.');
        }
        return $this->transacao(function () use ($ref) {
            $this->db->sql("INSERT INTO pagamento_competencia (competencia,criado_em,criado_por,previsto_em) VALUES (?,UTC_TIMESTAMP(6),?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)", [$ref,$this->usuario,pagamento_previsao($ref)]);
            $c = $this->cabecalho($ref, true);
            if (!$this->db->sql("SELECT id FROM pagamento_competencia_evento WHERE competencia_id=? AND tipo='CRIADO'", [$c['id']])) {
                // Não inferir participação por atividade, função ou valor fixo.
                $aptos = $this->db->sql('SELECT idcolaborador,nome_colaborador,tipo_remuneracao,valor_fixo FROM colaborador WHERE ativo=1 AND participa_fechamento_mensal=1 ORDER BY idcolaborador FOR UPDATE');
                foreach ($aptos as $p) {
                    $f = $this->db->bloquear((int)$p['idcolaborador'], $ref, $this->usuario);
                    $this->db->sql('INSERT INTO pagamento_competencia_colaborador (competencia_id,fechamento_id,nome,tipo_remuneracao,fixo_cadastro) VALUES (?,?,?,?,?)', [$c['id'],$f['id'],$p['nome_colaborador'],$p['tipo_remuneracao'],$p['valor_fixo']]);
                }
                $ids = array_map('intval', explode(',', getenv('PAGAMENTO_RESPONSAVEL_IDS') ?: '21,9,43'));
                foreach (array_unique($ids) as $id) {
                    if (!$this->db->sql('SELECT idcolaborador FROM colaborador WHERE idcolaborador=?', [$id])) {
                        throw new DomainException('Responsável financeiro não encontrado.');
                    }
                    $this->db->sql('INSERT INTO pagamento_competencia_responsavel VALUES (?,?)', [$c['id'],$id]);
                }
                $this->auditar((int)$c['id'], null, 'CRIADO', 'criar:'.$ref, [], ['aptos' => count($aptos),'responsaveis' => $ids]);
                $this->sincronizar((int)$c['id']);
                $this->pendencias($c);
            }
            return $this->resumo($ref);
        });
    }

    /** Acrescenta um participante elegível a um ciclo iniciado, com snapshot e trilha de auditoria. */
    public function incluirParticipante(string $ref, int $colaborador, string $key): array
    {
        if (!preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $key)) {
            throw new InvalidArgumentException('Chave inválida.');
        }
        return $this->transacao(function () use ($ref, $colaborador, $key) {
            $request = ['acao' => 'incluir_participante','competencia' => $ref,'colaborador_id' => $colaborador];
            $c = $this->cabecalho($ref, true);
            if (!$c) {
                throw new DomainException('Inicie o fechamento antes de incluir participantes.');
            }
            if ($this->retry((int)$c['id'], $key, $request)) {
                return $this->resumo($ref);
            }
            if ($c['estado'] === 'CONCLUIDO') {
                throw new DomainException('Fechamento concluído. Não é possível incluir participantes.');
            }
            if (!$this->db->sql("SELECT id FROM pagamento_competencia_evento WHERE competencia_id=? AND tipo='CRIADO'", [$c['id']])) {
                throw new DomainException('Inicie o fechamento antes de incluir participantes.');
            }
            if ($this->db->sql('SELECT m.fechamento_id FROM pagamento_competencia_colaborador m JOIN pagamento_fechamento f ON f.id=m.fechamento_id WHERE m.competencia_id=? AND f.colaborador_id=?', [$c['id'],$colaborador])) {
                throw new DomainException('Este colaborador já participa do fechamento. Atualize a lista.');
            }

            $p = $this->db->sql('SELECT idcolaborador,nome_colaborador,tipo_remuneracao,valor_fixo FROM colaborador WHERE idcolaborador=? AND ativo=1 AND participa_fechamento_mensal=1 FOR UPDATE', [$colaborador])[0] ?? null;
            if (!$p) {
                throw new DomainException('O cadastro precisa estar ativo e marcado para participar explicitamente do fechamento.');
            }
            $guard = $this->db->sql("SELECT ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='pcc_roster_guard'")[0] ?? null;
            if (!$guard || stripos($guard['ACTION_STATEMENT'], 'PARTICIPANTE_INCLUIDO') === false) {
                throw new RuntimeException('Migration para inclusão auditada de participante ausente.');
            }

            $antigo = $this->db->sql('SELECT id,numero_revisao FROM pagamento_fechamento WHERE colaborador_id=? AND competencia=? FOR UPDATE', [$colaborador,$ref])[0] ?? null;
            if ($antigo && (int)$antigo['numero_revisao'] > 0) {
                throw new DomainException('Este colaborador já possui uma revisão anterior nesta competência. Confira o histórico antes de incluí-lo.');
            }
            $f = $this->db->bloquear($colaborador, $ref, $this->usuario);
            $snapshot = ['colaborador_id' => $colaborador,'nome' => $p['nome_colaborador'],'tipo_remuneracao' => $p['tipo_remuneracao'],'fixo_cadastro' => $p['valor_fixo']];
            $this->auditar((int)$c['id'], (int)$f['id'], 'PARTICIPANTE_INCLUIDO', $key, ['incluido' => false], $snapshot, $request);
            $this->db->sql('INSERT INTO pagamento_competencia_colaborador (competencia_id,fechamento_id,nome,tipo_remuneracao,fixo_cadastro) VALUES (?,?,?,?,?)', [$c['id'],$f['id'],$p['nome_colaborador'],$p['tipo_remuneracao'],$p['valor_fixo']]);
            $this->pendencias($c);
            return $this->resumo($ref);
        });
    }

    private function membros(int $c, bool $lock = false): array
    {
        return $this->db->sql('SELECT m.*,f.colaborador_id,f.numero_revisao,f.competencia FROM pagamento_competencia_colaborador m JOIN pagamento_fechamento f ON f.id=m.fechamento_id WHERE m.competencia_id=? ORDER BY f.id'.($lock ? ' FOR UPDATE' : ''), [$c]);
    }
    private function revisao(array $m, bool $fechado): ?array
    {
        $id = $fechado ? $m['revisao_id'] : ($this->db->sql('SELECT id FROM pagamento_fechamento_revisao WHERE fechamento_id=? AND numero=?', [$m['fechamento_id'],$m['numero_revisao']])[0]['id'] ?? null);
        return $id ? $this->db->revisao((int)$id) : null;
    }
    private function confirmado(array $r): ?array
    {
        if (($r['snapshot']['composicao']['monthly_rule_version'] ?? null) !== 'fechamento_mensal_v1') {
            return null;
        }
        return $this->db->sql("SELECT confirmado_em,confirmado_por FROM pagamento_fechamento_documento WHERE revisao_id=? AND estado='CONFIRMADO' ORDER BY id DESC LIMIT 1", [$r['id']])[0] ?? null;
    }
    /** Reconciliar somente beneficiário/origem/classe presentes na revisão. Não inventar liquidações. */
    private function reconciliar(array $m, array $r): array
    {
        $comp = $r['snapshot']['composicao'];
        $credito = 0;
        $ids = [];
        $valor = 0;
        $variable = $comp['tipo_remuneracao'] !== 'FIXO';
        $ledger = $variable ? $this->db->sql('SELECT pi.*,l.fechamento_id vinculado FROM pagamento_itens pi JOIN pagamentos p ON p.idpagamento=pi.pagamento_id LEFT JOIN pagamento_competencia_lancamento l ON l.pagamento_item_id=pi.idpagamento_item WHERE p.colaborador_id=? AND p.mes_ref=?', [$m['colaborador_id'],$m['competencia']]) : [];
        foreach ($variable ? ($comp['financeiro_servicos']['itens_analisados'] ?? []) : [] as $s) {
            if (!$s['elegibilidade']['elegivel']) {
                continue;
            }
            $ident = $s['identidade'];
            foreach ($s['pagamentos'] as $p) {
                if (($p['competencia_pagamento'] ?? null) === $m['competencia']) {
                    $credito = FechamentoFinanceiroRules::somar($credito, $p['valor_centavos']);
                }
            }
            foreach ($ledger as $l) {
                if ($l['origem'] !== $ident['origem'] || (int)$l['origem_id'] !== (int)$ident['origem_id'] || FechamentoFinanceiroRules::classeLedger($l) !== $ident['classe']) {
                    continue;
                }
                if ($l['vinculado'] && (int)$l['vinculado'] !== (int)$m['fechamento_id']) {
                    throw new DomainException('Lançamento já vinculado a outra competência.');
                }
                $ids[] = (int)$l['idpagamento_item'];
                $valor = FechamentoFinanceiroRules::somar($valor, FechamentoFinanceiroRules::centavos($l['valor']));
            }
        }
        $credito -= (int)($comp['pagamentos_competencia_incluidos_centavos'] ?? 0);
        if ($credito < 0) {
            throw new DomainException('Reconciliação incompatível com a revisão.');
        }
        $total = FechamentoFinanceiroRules::somar((int)$comp['total_final_centavos'], $credito);
        if ($total < 0 || $valor > $total) {
            throw new DomainException('Pagamentos existentes divergem do fechamento de '.$m['nome'].'. Confira a reconciliação.');
        }
        return ['credito_historico_centavos' => $credito,'total_centavos' => $total,'pago_centavos' => $valor,'ledger_ids' => array_values(array_unique($ids))];
    }
    /** Dentro de transação; lock geral sempre antecede locks individuais. */
    public function sincronizar(int $id): void
    {
        $c = $this->db->sql('SELECT * FROM pagamento_competencia WHERE id=? FOR UPDATE', [$id])[0];
        if ($c['estado'] === 'CONCLUIDO') {
            return;
        }
        foreach ($this->membros($id, true) as $m) {
            $r = $this->revisao($m, false);
            $doc = $r ? $this->confirmado($r) : null;
            if ($doc && $r['snapshot']['composicao']['total_final_determinado']) {
                $rec = $this->reconciliar($m, $r);
                if ($rec['total_centavos'] !== (int)$r['snapshot']['composicao']['total_final_centavos']) {
                    throw new DomainException('Atualize a revisão de '.$m['nome'].' para incluir os pagamentos históricos no PDF antes de concluir o fechamento.');
                }
                $this->db->sql('UPDATE pagamento_competencia_colaborador SET revisao_id=?,revisado_em=?,revisado_por=?,total_centavos=?,reconciliacao_json=? WHERE fechamento_id=?', [$r['id'],$doc['confirmado_em'],$doc['confirmado_por'],$rec['total_centavos'],FechamentoSnapshot::json($rec),$m['fechamento_id']]);
                if ((int)($m['revisao_id'] ?? 0) !== $r['id']) {
                    $this->auditar($id, (int)$m['fechamento_id'], 'REVISADO', 'revisado:'.$r['id'], ['revisao_id' => $m['revisao_id']], ['revisao_id' => $r['id'],'total_centavos' => $rec['total_centavos']]);
                }
            } elseif ($m['revisao_id'] !== null) {
                $this->db->sql('UPDATE pagamento_competencia_colaborador SET revisao_id=NULL,revisado_em=NULL,revisado_por=NULL,total_centavos=NULL,reconciliacao_json=NULL WHERE fechamento_id=?', [$m['fechamento_id']]);
                $this->auditar($id, (int)$m['fechamento_id'], 'REVISAO_REMOVIDA', 'nova-revisao:'.$m['fechamento_id'].':'.$m['numero_revisao'], ['revisao_id' => $m['revisao_id']], ['revisao_id' => null]);
            }
        }
    }
    public function concluir(string $ref, string $key): array
    {
        return $this->transacao(function () use ($ref, $key) {
            $c = $this->cabecalho($ref, true);
            if (!$c) {
                throw new DomainException('Inicie o fechamento desta competência.');
            }
            $request = ['acao' => 'concluir','competencia' => $ref];
            if ($this->retry((int)$c['id'], $key, $request)) {
                return $this->resumo($ref);
            }
            if ($c['estado'] === 'CONCLUIDO') {
                return $this->resumo($ref);
            }
            $this->sincronizar((int)$c['id']);
            $membros = $this->membros((int)$c['id'], true);
            $pendentes = count(array_filter($membros, fn ($m) => !$m['revisao_id']));
            if ($pendentes) {
                throw new DomainException('Não é possível concluir o fechamento. Ainda existem '.$pendentes.' colaboradores pendentes de revisão.');
            }
            if (!$membros) {
                throw new DomainException('Não é possível concluir um fechamento sem colaboradores aptos.');
            }
            $total = 0;
            foreach ($membros as $m) {
                $rec = json_decode($m['reconciliacao_json'], true, 512, JSON_THROW_ON_ERROR);
                foreach ($rec['ledger_ids'] as $item) {
                    $vinculo = $this->db->sql('SELECT fechamento_id FROM pagamento_competencia_lancamento WHERE pagamento_item_id=? FOR UPDATE', [$item])[0] ?? null;
                    if ($vinculo) {
                        if ((int)$vinculo['fechamento_id'] !== (int)$m['fechamento_id']) {
                            throw new DomainException('Lançamento já vinculado a outra competência.');
                        }
                        continue;
                    }
                    try {
                        $this->db->sql('INSERT INTO pagamento_competencia_lancamento VALUES (?,?)', [$item,$m['fechamento_id']]);
                    } catch (mysqli_sql_exception $e) {
                        if ($e->getCode() !== 1062) {
                            throw $e;
                        }
                        $vinculo = $this->db->sql('SELECT fechamento_id FROM pagamento_competencia_lancamento WHERE pagamento_item_id=?', [$item])[0] ?? null;
                        if (!$vinculo || (int)$vinculo['fechamento_id'] !== (int)$m['fechamento_id']) {
                            throw $e;
                        }
                    }
                }
                $total = FechamentoFinanceiroRules::somar($total, (int)$m['total_centavos']);
            }
            $this->db->sql("UPDATE pagamento_competencia SET estado='CONCLUIDO',total_fechado_centavos=?,concluido_em=UTC_TIMESTAMP(6),concluido_por=? WHERE id=?", [$total,$this->usuario,$c['id']]);
            $this->auditar((int)$c['id'], null, 'CONCLUIDO', $key, ['estado' => $c['estado']], ['estado' => 'CONCLUIDO','total_centavos' => $total], $request);
            // Histórico já quitado é reconhecido somente depois da conclusão geral.
            foreach ($membros as $m) {
                $pago = $this->pago((int)$m['fechamento_id']);
                if ($pago === (int)$m['total_centavos'] && $pago > 0 && $this->funcoesPagas($this->revisao($m, true)['snapshot']['composicao'])) {
                    $d = $this->db->sql('SELECT MAX(COALESCE(p.data_pagamento,p.pago_em,DATE(pi.criado_em))) data FROM pagamento_competencia_lancamento l JOIN pagamento_itens pi ON pi.idpagamento_item=l.pagamento_item_id JOIN pagamentos p ON p.idpagamento=pi.pagamento_id WHERE l.fechamento_id=?', [$m['fechamento_id']])[0]['data'];
                    $this->marcarPago($m, substr($d, 0, 10), 'reconciliado:'.$m['fechamento_id']);
                }
            }
            $this->quitar($this->cabecalho($ref, true));
            return $this->resumo($ref);
        });
    }
    private function funcoesPagas(array $comp): bool
    {
        $tables = ['funcao_imagem' => 'idfuncao_imagem','funcao_animacao' => 'id','acompanhamento' => 'idacompanhamento'];
        foreach ($comp['producao_consulta']['itens_analisados'] ?? [] as $s) {
            if (!$s['elegibilidade']['elegivel'] || $s['identidade']['classe'] === FechamentoFinanceiroRules::COMISSAO) {
                continue;
            }
            $i = $s['identidade'];
            if (!isset($tables[$i['origem']])) {
                return false;
            }
            $source = $this->db->sql('SELECT pagamento FROM '.$i['origem'].' WHERE '.$tables[$i['origem']].'=?', [$i['origem_id']])[0] ?? null;
            if (!$source || (int)$source['pagamento'] !== 1) {
                return false;
            }
        }
        return true;
    }
    private function pago(int $f): int
    {
        return FechamentoFinanceiroRules::centavos($this->db->sql('SELECT COALESCE(SUM(pi.valor),0) valor FROM pagamento_competencia_lancamento l JOIN pagamento_itens pi ON pi.idpagamento_item=l.pagamento_item_id WHERE l.fechamento_id=?', [$f])[0]['valor']);
    }
    private function marcarPago(array $m, string $date, string $key): void
    {
        if (($m['pago_em'] ?? null) !== null) {
            return;
        }
        $this->db->sql('UPDATE pagamento_competencia_colaborador SET pago_em=?,pago_por=? WHERE fechamento_id=? AND pago_em IS NULL', [$date,$this->usuario,$m['fechamento_id']]);
        $this->auditar((int)$m['competencia_id'], (int)$m['fechamento_id'], 'PAGO', $key, ['pago_em' => null], ['pago_em' => $date,'pago_centavos' => $this->pago((int)$m['fechamento_id'])]);
    }
    private function quitar(array $c): void
    {
        $m = $this->membros((int)$c['id']);
        if ($c['estado'] === 'CONCLUIDO' && $m && !array_filter($m, fn ($p) => !$p['pago_em'] || $this->pago((int)$p['fechamento_id']) !== (int)$p['total_centavos']) && !$c['quitado_em']) {
            $this->db->sql('UPDATE pagamento_competencia SET quitado_em=UTC_TIMESTAMP(6) WHERE id=?', [$c['id']]);
            $this->auditar((int)$c['id'], null, 'QUITADO', 'quitado:'.$c['competencia'], ['quitado_em' => null], ['situacao' => 'QUITADO']);
        }
        $this->pendencias($this->cabecalho($c['competencia']));
    }
    public function concluirPagamento(string $ref, string $key): array
    {
        return $this->transacao(function () use ($ref, $key) {
            $c = $this->cabecalho($ref, true);
            if (!$c || $c['estado'] !== 'CONCLUIDO') {
                throw new DomainException('Pagamento bloqueado por fechamento pendente.');
            }
            $m = $this->membros((int)$c['id'], true);
            $n = count(array_filter($m, fn ($p) => !$p['pago_em'] || $this->pago((int)$p['fechamento_id']) !== (int)$p['total_centavos']));
            if ($n) {
                throw new DomainException('Não é possível concluir o pagamento. Ainda existem '.$n.' colaboradores pendentes de pagamento.');
            }
            $this->quitar($c);
            return $this->resumo($ref);
        });
    }
    public function pagar(string $ref, int $b, string $key, string $date, string $obs = ''): array
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date || $date > (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d') || strlen($obs) > 255) {
            throw new InvalidArgumentException('Data/observação inválida.');
        }
        return $this->transacao(function () use ($ref, $b, $key, $date, $obs) {
            $c = $this->cabecalho($ref, true);
            if (!$c || $c['estado'] !== 'CONCLUIDO') {
                throw new DomainException('Pagamento bloqueado por fechamento pendente.');
            }
            $request = ['acao' => 'pagar','competencia' => $ref,'colaborador_id' => $b,'data' => $date,'observacao' => $obs];
            if ($this->retry((int)$c['id'], $key, $request)) {
                return $this->resumo($ref);
            }
            $m = array_values(array_filter($this->membros((int)$c['id'], true), fn ($p) => (int)$p['colaborador_id'] === $b))[0] ?? null;
            if (!$m) {
                throw new DomainException('Colaborador fora do snapshot desta competência.');
            }
            if ($m['pago_em']) {
                return $this->resumo($ref);
            }
            $r = $this->revisao($m, true);
            $comp = $r['snapshot']['composicao'];
            $restante = (int)$m['total_centavos'] - $this->pago((int)$m['fechamento_id']);
            if ($restante < 0) {
                throw new DomainException('Pagamentos excedem o valor fechado.');
            }
            $valorNovo = $restante;
            $service = new PagamentoService($this->conn, $this->usuario);
            $pid = $service->garantirPagamento($b, (int)substr($ref, 5, 2), (int)substr($ref, 0, 4));
            $linhas = [];
            foreach ($comp['producao_consulta']['itens_analisados'] ?? [] as $s) {
                if (!$s['elegibilidade']['elegivel']) {
                    continue;
                }
                $ident = $s['identidade'];
                $tables = ['funcao_imagem' => 'idfuncao_imagem','funcao_animacao' => 'id','acompanhamento' => 'idacompanhamento'];
                if (!isset($tables[$ident['origem']])) {
                    throw new DomainException('Origem financeira não reconhecida.');
                }
                $source = $this->db->sql('SELECT * FROM '.$ident['origem'].' WHERE '.$tables[$ident['origem']].'=? FOR UPDATE', [$ident['origem_id']])[0] ?? null;
                if (!$source || ((int)$source['colaborador_id'] !== $b && $ident['classe'] !== FechamentoFinanceiroRules::COMISSAO) || ($ident['classe'] === FechamentoFinanceiroRules::COMISSAO && !in_array((int)$source['colaborador_id'], [23,40], true))) {
                    throw new DomainException('Beneficiário da função foi alterado. Confira antes de registrar o pagamento.');
                }
                $valor = $comp['tipo_remuneracao'] === 'FIXO' ? 0 : (int)($s['saldo_centavos'] ?? 0);
                $known = array_column($s['pagamentos'], 'idpagamento_item');
                $ledger = $this->db->sql('SELECT pi.*,p.colaborador_id FROM pagamento_itens pi JOIN pagamentos p ON p.idpagamento=pi.pagamento_id WHERE pi.origem=? AND pi.origem_id=? FOR UPDATE', [$ident['origem'],$ident['origem_id']]);
                foreach ($ledger as $l) {
                    if ((int)$l['colaborador_id'] === $b && FechamentoFinanceiroRules::classeLedger($l) === $ident['classe'] && !in_array((int)$l['idpagamento_item'], $known, true)) {
                        $valor -= FechamentoFinanceiroRules::centavos($l['valor']);
                    }
                }
                if ($valor < 0) {
                    throw new DomainException('Ledger alterado após o fechamento. Confira a função.');
                }
                $amount = min($restante, $valor);
                $restante -= $amount;
                $linhas[] = [$ident,$amount,$source];
            }
            foreach ($linhas as [$ident,$amount,$source]) {
                if ($amount > 0) {
                    $this->lancar($pid, (int)$m['fechamento_id'], $ident['origem'], (int)$ident['origem_id'], $amount, $ident['classe'] === FechamentoFinanceiroRules::COMISSAO ? 'Comissão Gestor' : ($ident['origem'] === 'funcao_imagem' && (int)($source['funcao_id'] ?? 0) === 4 ? 'Pago Completa' : $obs));
                }
                if ($ident['classe'] !== FechamentoFinanceiroRules::COMISSAO) {
                    $pk = ['funcao_imagem' => 'idfuncao_imagem','funcao_animacao' => 'id','acompanhamento' => 'idacompanhamento'][$ident['origem']];
                    $this->db->sql('UPDATE '.$ident['origem'].' SET pagamento=1 WHERE '.$pk.'=?', [$ident['origem_id']]);
                }
            }
            if ($restante > 0) {
                $this->lancar($pid, (int)$m['fechamento_id'], 'fechamento_mensal', (int)$m['fechamento_id'], $restante, $obs ?: 'Fixo, bônus e extras do fechamento mensal');
            }
            if ($this->pago((int)$m['fechamento_id']) !== (int)$m['total_centavos']) {
                throw new DomainException('Liquidação divergente do snapshot financeiro.');
            }
            $this->db->sql("UPDATE pagamentos SET valor_total=(SELECT COALESCE(SUM(valor),0) FROM pagamento_itens WHERE pagamento_id=?),status='pago',data_pagamento=IF(?=0,COALESCE(data_pagamento,?),?),pago_em=IF(?=0,COALESCE(pago_em,?),?) WHERE idpagamento=?", [$pid,$valorNovo,$date,$date,$valorNovo,$date,$date,$pid]);
            $service->registrarEvento($pid, 'pago', 'Fechamento '.$ref.' · revisão '.$r['id'].' · data '.$date.' · '.$obs);
            $this->marcarPago($m, $date, 'pago:'.$m['fechamento_id']);
            $this->auditar((int)$c['id'], (int)$m['fechamento_id'], 'LIQUIDACAO', $key, [], ['pagamento_id' => $pid,'pago_centavos' => $this->pago((int)$m['fechamento_id'])], $request);
            $this->quitar($c);
            return $this->resumo($ref);
        });
    }
    private function lancar(int $pid, int $f, string $origem, int $id, int $cents, string $obs): void
    {
        $amount = sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
        $this->db->sql('INSERT INTO pagamento_itens (pagamento_id,origem,origem_id,valor,observacao) VALUES (?,?,?,?,?)', [$pid,$origem,$id,$amount,$obs]);
        $item = (int)$this->conn->insert_id;
        $this->db->sql('INSERT INTO pagamento_competencia_lancamento VALUES (?,?)', [$item,$f]);
    }
    private function pendencias(array $c): void
    {
        // Reutilizar checklists automáticos. A fila financeira consulta estes mesmos registros.
        foreach (['fechamento','pagamento'] as $tipo) {
            $done = $tipo === 'fechamento' ? $c['estado'] === 'CONCLUIDO' : (bool)$c['quitado_em'];
            $this->db->sql("INSERT INTO checklist_operacional (module_key,entity_type,entity_id,requirements_version,sla_start_at,due_at) VALUES ('pagamentos',?,?,'PAGAMENTO_COMPETENCIA_V1',?,?) ON DUPLICATE KEY UPDATE due_at=IF(status<>'concluido',VALUES(due_at),due_at)", [$tipo,$c['id'],$c['criado_em'],$c['previsto_em'].' 23:59:59']);
            $ch = $this->db->sql("SELECT id FROM checklist_operacional WHERE module_key='pagamentos' AND entity_type=? AND entity_id=?", [$tipo,$c['id']])[0]['id'];
            $this->db->sql("INSERT INTO checklist_operacional_item (checklist_id,item_key,label,required,update_mode,done) VALUES (?,'financeiro',?,1,'AUTOMATICO',?) ON DUPLICATE KEY UPDATE done=VALUES(done),done_at=IF(VALUES(done)=1,COALESCE(done_at,NOW()),NULL)", [$ch,$tipo === 'fechamento' ? '100% dos colaboradores revisados' : '100% dos colaboradores pagos',(int)$done]);
            $this->db->sql('UPDATE checklist_operacional SET status=? WHERE id=?', [$done ? 'concluido' : 'aberto',$ch]);
        }
    }
    public function resumo(string $ref): array
    {
        $c = $this->cabecalho($ref);
        $fechado = $c && $c['estado'] === 'CONCLUIDO';
        $items = [];
        $counts = ['NAO_REVISADO' => 0,'ATENCAO' => 0,'CONFIRMADO' => 0];
        $parcial = 0;
        $pagos = 0;
        $pago = 0;
        foreach ($c ? $this->membros((int)$c['id']) : [] as $m) {
            $r = $this->revisao($m, $fechado);
            $comp = $r['snapshot']['composicao'] ?? [];
            $monthly = ($comp['monthly_rule_version'] ?? null) === 'fechamento_mensal_v1';
            $doc = $r ? $this->confirmado($r) : null;
            $status = $doc ? 'CONFIRMADO' : (($monthly && !$comp['total_final_determinado']) || !$m['tipo_remuneracao'] ? 'ATENCAO' : 'NAO_REVISADO');
            $counts[$status]++;
            $rec = $fechado ? json_decode($m['reconciliacao_json'], true) : ($monthly && $comp['total_final_determinado'] ? $this->reconciliar($m, $r) : null);
            $total = $fechado ? (int)$m['total_centavos'] : ($rec['total_centavos'] ?? null);
            $reconciliacaoPendente = $doc && $rec && $rec['total_centavos'] !== (int)$comp['total_final_centavos'];
            if ($reconciliacaoPendente) {
                $counts[$status]--;
                $status = 'ATENCAO';
                $counts[$status]++;
                $doc = null;
            }
            $paid = $fechado ? $this->pago((int)$m['fechamento_id']) : 0;
            $quitado = $fechado && $m['pago_em'] && $paid === $total;
            $pagos += (int)$quitado;
            $pago += $paid;
            if ($doc && $total !== null) {
                $parcial = FechamentoFinanceiroRules::somar($parcial, $total);
            }
            $items[] = ['colaborador_id' => (int)$m['colaborador_id'],'fechamento_id' => (int)$m['fechamento_id'],'nome' => $m['nome'],'tipo_remuneracao' => $comp['tipo_remuneracao'] ?? $m['tipo_remuneracao'],
                'status' => $status,'preparado' => $monthly,'total_centavos' => $total,'pago_centavos' => $paid,'pendente_centavos' => $fechado ? $total - $paid : null,
                'pago_em' => $m['pago_em'],'pagamento_status' => $quitado ? 'PAGO' : ($fechado ? 'PENDENTE' : 'AGUARDANDO_FECHAMENTO'),
                'funcoes' => array_values(array_map(fn ($s) => ['identidade' => $s['identidade'],'descricao' => $s['descricao'],'valor_centavos' => $s['saldo_centavos'] ?? 0,'valor_reconhecido_centavos' => $s['valor_reconhecido_centavos'] ?? $s['saldo_centavos'] ?? 0,'situacao_financeira' => $quitado ? 'PAGO' : ($fechado ? 'PENDENTE' : 'AGUARDANDO_FECHAMENTO')], array_filter($comp['producao_consulta']['itens_analisados'] ?? [], fn ($s) => $s['elegibilidade']['elegivel']))),
                'resumo' => $comp['componentes'] ?? [],'reconciliacao' => $fechado ? json_decode($m['reconciliacao_json'], true) : $rec,
                'pendencias' => array_merge(array_column($comp['pendencias'] ?? [],'mensagem'),$reconciliacaoPendente ? ['Atualize a revisão para incluir os pagamentos históricos no PDF.'] : [])];
        }
        $q = count($items);
        $total = $fechado ? (int)$c['total_fechado_centavos'] : null;
        $inclusaoDisponivel = false;
        $disponiveis = [];
        if ($c && !$fechado) {
            $guard = $this->db->sql("SELECT ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='pcc_roster_guard'")[0] ?? null;
            $inclusaoDisponivel = $guard && stripos($guard['ACTION_STATEMENT'], 'PARTICIPANTE_INCLUIDO') !== false;
            $disponiveis = $this->db->sql('SELECT p.idcolaborador AS colaborador_id,p.nome_colaborador AS nome FROM colaborador p WHERE p.ativo=1 AND p.participa_fechamento_mensal=1 AND NOT EXISTS (SELECT 1 FROM pagamento_competencia_colaborador m JOIN pagamento_fechamento f ON f.id=m.fechamento_id WHERE m.competencia_id=? AND f.colaborador_id=p.idcolaborador) ORDER BY p.nome_colaborador,p.idcolaborador', [$c['id']]);
            foreach ($disponiveis as &$p) $p['colaborador_id'] = (int)$p['colaborador_id'];
            unset($p);
        }
        return ['competencia' => $ref,'ciclo_id' => $c ? (int)$c['id'] : null,'estado' => $fechado ? 'CONCLUIDO' : 'EM_ANDAMENTO','quantidade' => $q,'colaboradores' => $items,'contagens' => $counts,
            'parcial_centavos' => $parcial,'total_fechado_centavos' => $total,'pago_centavos' => $pago,'pendente_centavos' => $fechado ? $total - $pago : null,'quantidade_pagos' => $pagos,
            'previsto_em' => $c['previsto_em'] ?? pagamento_previsao($ref),'concluido_em' => $c['concluido_em'] ?? null,
            'situacao' => $fechado ? ($q && $pagos === $q ? 'QUITADO' : ($pago > 0 ? 'PARCIALMENTE_PAGO' : 'AGUARDANDO_PAGAMENTO')) : 'AGUARDANDO_FECHAMENTO',
            'pendencias_configuracao' => [],'inclusao_disponivel' => (bool)$inclusaoDisponivel,'participantes_disponiveis' => $disponiveis];
    }
}
