<?php

require_once __DIR__ . '/FechamentoFinanceiroReadOnlyConnection.php';

/** Complementos somente leitura, chamados dentro do snapshot aberto pela 1A. */
final class FechamentoComposicaoRepository
{
    private function select(mysqli $conn, string $sql, string $types = '', array $params = []): array
    {
        FechamentoFinanceiroReadOnlyConnection::validarSql($sql);
        if ($types === '') {
            $res = $conn->query($sql);
            try { return $res->fetch_all(MYSQLI_ASSOC); } finally { $res->free(); }
        }
        $stmt = $conn->prepare($sql);
        try {
            $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result();
            try { return $res->fetch_all(MYSQLI_ASSOC); } finally { $res->free(); }
        } finally { $stmt->close(); }
    }

    private function conferirAdendos(mysqli $conn): void
    {
        $rows = $this->select($conn, "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='adendos'");
        if (count($rows) !== 1 || strtoupper((string)$rows[0]['ENGINE']) !== 'INNODB') throw new RuntimeException('Diagnóstico de adendos exige tabela InnoDB.');
    }

    public function carregarComplementos(mysqli $conn, int $b, string $ref, DateTimeImmutable $snapshot): array
    {
        $this->conferirAdendos($conn);
        $cadastro = $this->select($conn, 'SELECT idcolaborador,valor_fixo FROM colaborador WHERE idcolaborador=?', 'i', [$b]);
        if (!$cadastro) throw new InvalidArgumentException('Colaborador não encontrado na leitura de complementos.');
        $pagamentos = $this->select($conn, 'SELECT idpagamento,colaborador_id,mes_ref,status,valor_total,pago_em FROM pagamentos WHERE colaborador_id=? AND mes_ref=? ORDER BY idpagamento', 'is', [$b,$ref]);
        $adendos = $this->select($conn, 'SELECT id,colaborador_id,competencia,status,payload_enviado,created_at FROM adendos WHERE colaborador_id=? AND competencia=? ORDER BY id', 'is', [$b,$ref]);
        foreach ($adendos as &$adendo) {
            $payload = json_decode($adendo['payload_enviado'] ?? '', true);
            $adendo['payload_utilizavel'] = is_array($payload);
            $adendo['payload_financeiro'] = is_array($payload) ? array_intersect_key($payload, array_flip(['COMPETENCIA','VALOR_FIXO','VALOR_TOTAL'])) : [];
            unset($adendo['payload_enviado']); // não expor arquivo, URL/token ou dados pessoais extras
        }
        unset($adendo);
        $this->conferirAdendos($conn);
        return ['colaborador_id' => $b, 'competencia' => $ref, 'snapshot_em' => $snapshot->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d\TH:i:s.uP'),
            'fixo' => ['configurado' => $cadastro[0]['valor_fixo'], 'evidencias_liquidacao' => [],
                'evidencias_legadas' => ['pagamentos_agregados' => $pagamentos, 'adendos' => $adendos]],
            'extras' => ['estado' => 'PENDENTE', 'itens' => []],
            'persistencia' => ['fixo_configurado' => 'colaborador.valor_fixo',
                'liquidacao_fixo' => 'SEM_STORAGE_DISCRIMINADO_AUDITADO', 'override' => 'SEM_STORAGE_AUDITADO',
                'decisao_bonus' => 'SEM_STORAGE_AUDITADO', 'rubricas_bonus' => 'NAO_PRESERVADAS_NO_PAYLOAD_LEGADO']];
    }
}
