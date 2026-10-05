<?php

/** Guarda adicional para CLI shadow/testes: não é usuário DB de privilégios limitados. */
final class FechamentoFinanceiroReadOnlyConnection extends mysqli
{
    public array $audit = [];

    public static function validarSql(string $sql): void
    {
        if ($sql === 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY') return;
        if (!preg_match('/^\s*SELECT\b/i', $sql)
            || preg_match('/;|\b(INTO|OUTFILE|DUMPFILE|FOR\s+UPDATE|LOCK\s+IN\s+SHARE|GET_LOCK|RELEASE_LOCK|SLEEP|BENCHMARK)\b/i', $sql)) {
            throw new LogicException('SQL não permitido no shadow financeiro.');
        }
    }

    public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT): mysqli_result|bool
    { self::validarSql($query); $this->audit[]=$query; return parent::query($query,$result_mode); }
    public function prepare(string $query): mysqli_stmt|false
    { self::validarSql($query); $this->audit[]=$query; return parent::prepare($query); }
    public function real_query(string $query): bool
    { self::validarSql($query); $this->audit[]=$query; return parent::real_query($query); }
    public function multi_query(string $query): bool
    { throw new LogicException('multi_query proibido no shadow financeiro.'); }

    public function begin_transaction(int $flags = 0, ?string $name = null): bool
    {
        $required=MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT;
        if ($flags!==$required || $name!==null) throw new LogicException('CLI exige snapshot consistente somente leitura.');
        $this->audit[]='START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY';
        return parent::begin_transaction($flags,$name);
    }

    public function rollback(int $flags = 0, ?string $name = null): bool
    { $this->audit[]='ROLLBACK'; return parent::rollback($flags,$name); }
}
