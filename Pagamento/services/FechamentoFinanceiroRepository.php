<?php

require_once __DIR__ . '/FechamentoFinanceiroRules.php';

/** FASE 1A: SELECTs em uma transação própria, consistente e somente leitura. */
final class FechamentoFinanceiroRepository
{
    private const TABLES = ['colaborador', 'funcao_imagem', 'imagens_cliente_obra', 'funcao', 'log_alteracoes',
        'funcao_animacao', 'animacao', 'acompanhamento', 'pagamento_itens', 'pagamentos'];
    private mysqli $conn;

    public function __construct(mysqli $conn, private bool $mensal = false)
    {
        $this->conn = $conn;
    }

    private function select(string $sql, string $types = '', array $values = []): array
    {
        if (!preg_match('/^SELECT\b/i', ltrim($sql)) || preg_match('/;|\b(INTO|FOR\s+UPDATE|LOCK|SLEEP|GET_LOCK)\b/i', $sql)) {
            throw new LogicException('Consulta não permitida no repositório financeiro.');
        }
        if ($types === '') {
            $result = $this->conn->query($sql);
            if (!$result instanceof mysqli_result) {
                throw new RuntimeException('Consulta financeira falhou.');
            }
            try {
                return $result->fetch_all(MYSQLI_ASSOC);
            } finally {
                $result->free();
            }
        }
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Consulta financeira não preparada.');
        }
        try {
            $stmt->bind_param($types, ...$values);
            if (!$stmt->execute()) {
                throw new RuntimeException('Consulta financeira falhou.');
            }
            $result = $stmt->get_result();
            try {
                return $result->fetch_all(MYSQLI_ASSOC);
            } finally {
                $result->free();
            }
        } finally {
            $stmt->close();
        }
    }

    private function conferirEngines(): void
    {
        $names = "'" . implode("','", self::TABLES) . "'";
        $rows = $this->select("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($names)");
        $seen = [];
        foreach ($rows as $row) {
            if (strtoupper((string)$row['ENGINE']) !== 'INNODB') {
                throw new RuntimeException('Snapshot exige InnoDB: ' . $row['TABLE_NAME']);
            }
            $seen[] = $row['TABLE_NAME'];
        }
        if (array_diff(self::TABLES, $seen)) {
            throw new RuntimeException('Tabela financeira não encontrada para snapshot.');
        }
    }

    /** Leitura adicional interna auditada (shadow/1B), na MESMA visão do motor. */
    public function carregar(int $beneficiario, string $competencia, ?callable $leituraAdicional = null): array
    {
        [$inicio, $fim] = FechamentoFinanceiroRules::periodo($competencia);
        if ($beneficiario <= 0) {
            throw new InvalidArgumentException('Colaborador inválido.');
        }
        if ((int)$this->select('SELECT @@session.autocommit AS autocommit')[0]['autocommit'] !== 1) {
            throw new RuntimeException('Use conexão dedicada com autocommit; não assumir transação do chamador.');
        }
        // SET TRANSACTION (sem SESSION/GLOBAL) afeta somente a próxima transação.
        // Se já existe transação, MySQL rejeita o SET, sem commit/rollback do chamador.
        if (!$this->conn->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY')) {
            throw new RuntimeException('Não foi possível configurar transação somente leitura.');
        }
        $started = false;
        try {
            if (!$this->conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT)) {
                throw new RuntimeException('Não foi possível iniciar snapshot consistente.');
            }
            $started = true;
            return $this->lerNaTransacao($beneficiario, $competencia, $leituraAdicional);
        } finally {
            if ($started && !$this->conn->rollback()) {
                throw new RuntimeException('Não foi possível encerrar snapshot financeiro.');
            }
        }
    }
    /** 1C-A: transação própria RW; trava o fechamento ANTES de estabelecer a read view.
     * Callbacks internos não podem executar DDL, commit ou rollback.
     * Preserva o contrato READ ONLY de carregar().
     */
    public function persistir(int $beneficiario, string $competencia, callable $bloquear, callable $persistir): array
    {
        FechamentoFinanceiroRules::periodo($competencia);
        if ($beneficiario <= 0) {
            throw new InvalidArgumentException('Colaborador inválido.');
        }
        if ((int)$this->select('SELECT @@session.autocommit AS autocommit')[0]['autocommit'] !== 1) {
            throw new RuntimeException('Persistência exige conexão dedicada com autocommit.');
        }
        if (!$this->conn->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ WRITE')) {
            throw new RuntimeException('Não foi possível configurar transação persistente.');
        }
        $started = false;
        try {
            if (!$this->conn->begin_transaction(MYSQLI_TRANS_START_READ_WRITE)) {
                throw new RuntimeException('Não foi possível iniciar transação persistente.');
            }
            $started = true;
            $estado = $bloquear($this->conn);
            // Primeira leitura de tabela InnoDB APÓS adquirir locks: fixa a visão.
            $this->select('SELECT idcolaborador FROM colaborador WHERE idcolaborador=?', 'i', [$beneficiario]);
            $leitura = $this->lerNaTransacao($beneficiario, $competencia, null);
            $leitura['consistencia']['estrategia'] = 'INNODB_REPEATABLE_READ_VIEW_AFTER_CLOSURE_LOCK';
            $leitura['consistencia']['instante'] = 'UTC_TIMESTAMP(6) após estabelecer visão consistente e adquirir locks';
            $result = $persistir($this->conn, $leitura, $estado);
            if (!$this->conn->commit()) {
                throw new RuntimeException('Commit financeiro falhou.');
            }
            $started = false;
            return $result;
        } finally {
            if ($started) {
                $this->conn->rollback();
            }
        }
    }

    private function lerNaTransacao(int $beneficiario, string $competencia, ?callable $leituraAdicional): array
    {
        [$inicio, $fim] = FechamentoFinanceiroRules::periodo($competencia);
        $utc = $this->select('SELECT UTC_TIMESTAMP(6) AS snapshot_utc')[0]['snapshot_utc'];
        $snapshot = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        $this->conferirEngines();
        $colab = $this->select('SELECT idcolaborador, nome_colaborador FROM colaborador WHERE idcolaborador=?', 'i', [$beneficiario]);
        if (!$colab) {
            throw new InvalidArgumentException('Colaborador não encontrado.');
        }
        // SQL carrega candidatos; status/log e classe são decididos no domínio.
        $scope = '(fi.colaborador_id=? OR (?=8 AND fi.colaborador_id IN (23,40) AND fi.funcao_id=4))';
        $apresentacao = $this->mensal ? ", CASE WHEN fi.funcao_id=4 AND (
            EXISTS(SELECT 1 FROM funcao_imagem fp JOIN funcao fpar ON fpar.idfuncao=fp.funcao_id WHERE fp.imagem_id=fi.imagem_id AND fpar.nome_funcao='Pré-Finalização')
            OR (SELECT h.status_id FROM historico_imagens h WHERE h.imagem_id=fi.imagem_id AND h.data_movimento<'$fim' ORDER BY h.data_movimento DESC,h.idhistorico DESC LIMIT 1)=1
            ) THEN 1 ELSE 0 END AS finalizacao_parcial,
            (SELECT COUNT(*) FROM pagamento_itens pip JOIN funcao_imagem fip ON pip.origem='funcao_imagem' AND pip.origem_id=fip.idfuncao_imagem
             WHERE fip.imagem_id=fi.imagem_id AND fip.funcao_id=4 AND LOWER(TRIM(pip.observacao))='finalização parcial') AS pago_parcial_count" : '';
        $fi = $this->select("SELECT fi.* $apresentacao, 'funcao_imagem' AS origem, fi.idfuncao_imagem AS origem_id,
            ico.imagem_nome, ico.tipo_imagem, ico.obra_id,
            COALESCE(NULLIF(o.nomenclatura,''),NULLIF(o.nome_obra,'')) AS obra_nome, f.nome_funcao
            FROM funcao_imagem fi JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra=fi.imagem_id
            LEFT JOIN obra o ON o.idobra=ico.obra_id
            LEFT JOIN funcao f ON f.idfuncao=fi.funcao_id WHERE $scope
            AND ((fi.prazo>=? AND fi.prazo<?) OR EXISTS(SELECT 1 FROM log_alteracoes la
                WHERE la.funcao_imagem_id=fi.idfuncao_imagem AND la.data>=? AND la.data<?))
            ORDER BY fi.idfuncao_imagem", 'iissss', [$beneficiario,$beneficiario,$inicio,$fim,$inicio,$fim]);
        $logs = $this->select("SELECT la.idlog,la.funcao_imagem_id,la.data,la.status_novo,la.status_anterior
            FROM log_alteracoes la JOIN funcao_imagem fi ON fi.idfuncao_imagem=la.funcao_imagem_id
            WHERE $scope AND la.data>=? AND la.data<? ORDER BY la.data,la.idlog", 'iiss', [$beneficiario,$beneficiario,$inicio,$fim]);
        $fa = $this->select(
            "SELECT fa.*, 'funcao_animacao' AS origem, fa.id AS origem_id,
            a.data_anima, a.tipo_animacao, ico.imagem_nome, ico.obra_id,
            COALESCE(NULLIF(o.nomenclatura,''),NULLIF(o.nome_obra,'')) AS obra_nome, f.nome_funcao FROM funcao_animacao fa
            LEFT JOIN animacao a ON a.idanimacao=fa.animacao_id
            LEFT JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra=a.imagem_id
            LEFT JOIN obra o ON o.idobra=ico.obra_id
            LEFT JOIN funcao f ON f.idfuncao=fa.funcao_id WHERE fa.colaborador_id=?
            AND fa.prazo>=? AND fa.prazo<? ORDER BY fa.id",
            'iss',
            [$beneficiario,$inicio,$fim]
        );
        $ac = $this->select("SELECT ac.*, 'acompanhamento' AS origem, ac.idacompanhamento AS origem_id,
            ico.imagem_nome, ico.obra_id, COALESCE(NULLIF(o.nomenclatura,''),NULLIF(o.nome_obra,'')) AS obra_nome FROM acompanhamento ac LEFT JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra=ac.imagem_id
            LEFT JOIN obra o ON o.idobra=ico.obra_id
            WHERE ac.colaborador_id=? AND ac.data>=? AND ac.data<? ORDER BY ac.idacompanhamento", 'iss', [$beneficiario,$inicio,$fim]);
        $ledger = $this->select('SELECT pi.*, p.colaborador_id AS beneficiario_id, p.mes_ref, p.status AS pagamento_status
            FROM pagamento_itens pi JOIN pagamentos p ON p.idpagamento=pi.pagamento_id
            WHERE p.colaborador_id=? ORDER BY pi.idpagamento_item', 'i', [$beneficiario]);
        $legados = $this->select(
            "SELECT a.idanimacao,a.colaborador_id,a.data_anima,a.valor,a.pagamento
            FROM animacao a WHERE ((a.data_anima>=? AND a.data_anima<?) OR EXISTS(
                SELECT 1 FROM funcao_animacao fa WHERE fa.animacao_id=a.idanimacao AND fa.colaborador_id=? AND fa.prazo>=? AND fa.prazo<?))
            AND EXISTS(SELECT 1 FROM pagamento_itens pi JOIN pagamentos p ON p.idpagamento=pi.pagamento_id
                WHERE pi.origem='animacao' AND pi.origem_id=a.idanimacao AND p.colaborador_id=?) ORDER BY a.idanimacao",
            'ssissi',
            [$inicio,$fim,$beneficiario,$inicio,$fim,$beneficiario]
        );
        $dados = ['colaborador' => $colab[0], 'origens' => array_merge($fi, $fa, $ac), 'logs' => $logs,
            'ledger' => $ledger, 'animacoes_legadas' => $legados];
        $adicional = $leituraAdicional ? $leituraAdicional($this->conn, $dados, $snapshot) : null;
        // Conferência após as leituras, cujos metadata locks impedem DDL concorrente nas tabelas usadas.
        $this->conferirEngines();
        return ['dados' => $dados, 'snapshot' => $snapshot, 'adicional' => $adicional,
            'consistencia' => ['estrategia' => 'INNODB_REPEATABLE_READ_CONSISTENT_SNAPSHOT_READ_ONLY',
                'instante' => 'UTC_TIMESTAMP(6) na transação iniciada com visão consistente',
                'isolamento_global_alterado' => false]];
    }
}
