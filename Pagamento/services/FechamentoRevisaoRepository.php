<?php

require_once __DIR__ . '/FechamentoSnapshot.php';

/** Storage explícito; nunca escreve em cadastro, ledger ou adendos. */
final class FechamentoRevisaoRepository
{
    private int $afetados = 0;
    private int $inserido = 0;
    public function __construct(private mysqli $conn) {}

    public function sql(string $sql, array $params = []): array
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) throw new RuntimeException('Não foi possível preparar operação financeira.');
        try {
            if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
            if (!$stmt->execute()) throw new RuntimeException('Operação financeira falhou.');
            $this->afetados = $stmt->affected_rows;
            $this->inserido = (int)$stmt->insert_id;
            $result = $stmt->get_result();
            return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        } finally { $stmt->close(); }
    }

    public function conferirEstrutura(): void
    {
        $tables = ['usuario','pagamento_fechamento','pagamento_fechamento_decisao','pagamento_fechamento_extra',
            'pagamento_fechamento_evidencia','pagamento_fechamento_revisao','pagamento_fechamento_operacao'];
        $rows = $this->sql('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . implode(',', array_fill(0,count($tables),'?')) . ')', $tables);
        if (count($rows) !== count($tables)) throw new RuntimeException('Migration financeira ausente/incompleta.');
        foreach ($rows as $row) if (strtoupper($row['ENGINE']) !== 'INNODB') throw new RuntimeException('Persistência financeira exige InnoDB.');
        $triggers=['pfr_no_update','pfr_no_delete','pfd_no_update','pfd_no_delete','pfe_no_update','pfe_no_delete','pfv_no_update','pfv_no_delete','pfo_no_update','pfo_no_delete'];
        $presentes=$this->sql('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN ('.implode(',',array_fill(0,count($triggers),'?')).')',$triggers);
        if (count($presentes)!==count($triggers)) throw new RuntimeException('Proteção de imutabilidade ausente/incompleta.');
    }

    public function autorizar(int $usuario, bool $travar = false): void
    {
        // Mesma ACL já aplicada por pagamento_is_gestor() e custos_auth.php.
        $u = $this->sql('SELECT idusuario,nivel_acesso,ativo FROM usuario WHERE idusuario=?' . ($travar ? ' LOCK IN SHARE MODE' : ''), [$usuario]);
        if (!$u || (int)$u[0]['ativo'] !== 1 || !in_array((int)$u[0]['nivel_acesso'], [1,5], true)) {
            throw new DomainException('Sem autorização para gerir fechamento financeiro.');
        }
    }

    public function bloquear(int $b, string $ref, int $usuario): array
    {
        $this->sql('INSERT INTO pagamento_fechamento (colaborador_id,competencia,criado_por,criado_em) VALUES (?,?,?,UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)', [$b,$ref,$usuario]);
        $f = $this->sql('SELECT * FROM pagamento_fechamento WHERE colaborador_id=? AND competencia=? FOR UPDATE', [$b,$ref])[0];
        $this->autorizar($usuario, true);
        return $f;
    }

    public function operacao(int $f, string $key): ?array
    {
        // Locking read precede a read view; retry vê o commit de quem tinha o lock.
        return $this->sql('SELECT * FROM pagamento_fechamento_operacao WHERE fechamento_id=? AND chave=? FOR UPDATE', [$f,$key])[0] ?? null;
    }

    public function decisoes(int $f): array
    {
        $out = [];
        foreach ($this->sql('SELECT d.* FROM pagamento_fechamento_decisao d WHERE d.fechamento_id=? AND NOT EXISTS (SELECT 1 FROM pagamento_fechamento_decisao nova WHERE nova.fechamento_id=d.fechamento_id AND nova.tipo=d.tipo AND nova.id>d.id) ORDER BY d.tipo', [$f]) as $r) {
            $out[$r['tipo']] = ['id' => (int)$r['id'], 'dados' => json_decode($r['depois_json'],true,512,JSON_THROW_ON_ERROR)];
        }
        return $out;
    }

    public function salvarDecisao(int $f, string $tipo, int $autor, string $utc, string $motivo, array $antes, array $depois): int
    {
        $this->sql('INSERT INTO pagamento_fechamento_decisao (fechamento_id,tipo,autor_id,registrado_em,motivo,antes_json,depois_json) VALUES (?,?,?,?,?,?,?)',
            [$f,$tipo,$autor,$utc,$motivo,FechamentoSnapshot::json($antes),FechamentoSnapshot::json($depois)]);
        $id = $this->inserido;
        foreach ($tipo === 'BONUS' ? ($depois['itens'] ?? []) : [] as $r) {
            $this->sql('INSERT INTO pagamento_fechamento_extra (decisao_id,fechamento_id,referencia,categoria,valor_centavos,autor_id,registrado_em) VALUES (?,?,?,?,?,?,?)',
                [$id,$f,$r['referencia'],$r['categoria'],$r['valor_centavos'],$autor,$utc]);
        }
        foreach ($tipo === 'LIQUIDACAO' ? ($depois['evidencias'] ?? []) : [] as $r) {
            $this->sql('INSERT INTO pagamento_fechamento_evidencia (decisao_id,fechamento_id,referencia,tipo,valor_centavos,origem_verificavel,motivo,autor_id,registrado_em) VALUES (?,?,?,?,?,?,?,?,?)',
                [$id,$f,$r['referencia'],$r['tipo'],$r['valor_centavos'],$r['origem_verificavel'],$r['motivo'],$autor,$utc]);
        }
        return $id;
    }

    public function salvarRevisao(array $f, array $snapshot, array $ids, int $autor, string $utc): int
    {
        $c = $snapshot['composicao'];
        $values = [(int)$f['id'],(int)$f['numero_revisao']+1,$utc,$c['timezone'],$c['servicos_rule_version'],$c['rule_version'],
            $c['total_final_determinado'] ? 'PRONTO':'PENDENTE',$c['financeiro_servicos']['subtotal_servicos_centavos'],
            $c['componentes']['VALOR_FIXO'],$c['componentes']['ACOMPANHAMENTO_ESPECIAL'],$c['componentes']['BONUS_EXTRAS'],
            $c['componentes_conhecidos_centavos'],$c['total_final_centavos'],(int)$c['total_final_determinado'],(int)$c['bloqueado'],
            $ids['FIXO'] ?? null,$ids['BONUS'] ?? null,$ids['LIQUIDACAO'] ?? null,FechamentoSnapshot::json($snapshot),FechamentoSnapshot::hash($snapshot),$autor,$utc];
        $this->sql('INSERT INTO pagamento_fechamento_revisao (fechamento_id,numero,snapshot_em,timezone,financial_rule_version,composition_rule_version,estado,subtotal_servicos_centavos,fixo_centavos,especial_centavos,extras_centavos,componentes_conhecidos_centavos,total_final_centavos,total_final_determinado,bloqueado,fixo_decisao_id,bonus_decisao_id,liquidacao_decisao_id,snapshot_json,snapshot_hash,criado_por,criado_em) VALUES (' . implode(',',array_fill(0,count($values),'?')) . ')',$values);
        $id = $this->inserido;
        $this->sql('UPDATE pagamento_fechamento SET lock_version=lock_version+1,numero_revisao=numero_revisao+1,estado=? WHERE id=? AND lock_version=?', [$c['total_final_determinado'] ? 'PRONTO':'PENDENTE',$f['id'],$f['lock_version']]);
        if ($this->afetados !== 1) throw new RuntimeException('Conflito ao publicar revisão.');
        return $id;
    }

    public function registrarOperacao(array $f, string $key, string $hash, string $tipo, int $autor, string $utc, string $motivo, int $rev): void
    {
        $this->sql('INSERT INTO pagamento_fechamento_operacao (fechamento_id,chave,request_hash,tipo,autor_id,registrado_em,motivo,antes_json,depois_json,revisao_id) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$f['id'],$key,$hash,$tipo,$autor,$utc,$motivo,FechamentoSnapshot::json(['expected_version'=>(int)$f['lock_version'],'estado'=>$f['estado']]),
                FechamentoSnapshot::json(['version'=>(int)$f['lock_version']+1,'revisao_id'=>$rev]),$rev]);
    }

    public function revisao(int $id): array
    {
        $r = $this->sql('SELECT r.*,f.colaborador_id,f.competencia FROM pagamento_fechamento_revisao r JOIN pagamento_fechamento f ON f.id=r.fechamento_id WHERE r.id=?',[$id])[0] ?? null;
        if (!$r) throw new InvalidArgumentException('Revisão não encontrada.');
        $snapshot = json_decode($r['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
        if (!hash_equals($r['snapshot_hash'],FechamentoSnapshot::hash($snapshot))) throw new RuntimeException('Integridade do snapshot inválida.');
        return ['id'=>(int)$r['id'],'fechamento_id'=>(int)$r['fechamento_id'],'colaborador_id'=>(int)$r['colaborador_id'],
            'competencia'=>$r['competencia'],'numero'=>(int)$r['numero'],'version'=>(int)$r['numero'],'estado'=>$r['estado'],
            'autor_id'=>(int)$r['criado_por'],'criado_em_utc'=>$r['criado_em'],'snapshot_hash'=>$r['snapshot_hash'],'snapshot'=>$snapshot];
    }
}
