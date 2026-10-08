<?php

require_once __DIR__.'/pagamento_adendos_persistencia.php';
require_once __DIR__.'/../../Pagamento/services/FechamentoRevisaoService.php';

function documental_test_connection(string $db = ''): mysqli
{
    if ($db !== '' && !preg_match('/^pagamento_1cb_test_[a-f0-9]{10}$/D', $db)) {
        throw new RuntimeException('Banco documental de teste não autorizado.');
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $c = new mysqli('127.0.0.1', 'root', '', $db, 3320);
    $c->set_charset('utf8mb4');
    if (!str_starts_with($c->query('SELECT VERSION() v')->fetch_assoc()['v'], '8.0.')) {
        throw new RuntimeException('Homologação exige MySQL 8.0 real isolado.');
    }
    return $c;
}
function documental_test_migration(mysqli $c, string $file): void
{
    $delimiter = ';';
    $buffer = '';
    foreach (explode("\n", file_get_contents($file)) as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        if (preg_match('/^DELIMITER (\S+)\s*$/', trim($line), $m)) {
            if (trim($buffer) !== '') {
                throw new RuntimeException('Parser migration incompleto');
            } $delimiter = $m[1];
            continue;
        }
        $buffer .= $line."\n";
        if (str_ends_with(rtrim($buffer), $delimiter)) {
            $sql = substr(rtrim($buffer), 0, -strlen($delimiter));
            if (trim($sql) !== '') {
                $c->query($sql);
            } $buffer = '';
        }
    }
    if (trim($buffer) !== '') {
        throw new RuntimeException('Migration não terminada.');
    }
}
function documental_test_schema(mysqli $c): void
{
    fechamento_test_schema($c); // Aplica SQL 1C-A original intacto, à conexão MySQL 8 recebida.
    $c->query("ALTER TABLE usuario ADD idcolaborador INT NULL, ADD nome_usuario VARCHAR(100) NULL");
    $c->query("UPDATE usuario SET idcolaborador=idusuario,nome_usuario=CONCAT('Beneficiario Fixture ',idusuario)");
    $c->query("INSERT INTO colaborador VALUES (8,'Gestor Fixture',0),(13,'Stellar Fixture',0),(23,'Autor Fixture',0),(40,'Autor 2 Fixture',0)");
    $c->query("INSERT INTO usuario VALUES (8,5,1,8,'Gestor Fixture'),(13,2,1,13,'Stellar Fixture')");
    $c->query('CREATE TABLE informacoes_usuario (usuario_id INT,cnpj VARCHAR(30),nome_empresarial VARCHAR(100),cpf VARCHAR(20),estado_civil VARCHAR(30)) ENGINE=InnoDB');
    $c->query('CREATE TABLE endereco (usuario_id INT,rua VARCHAR(100),numero VARCHAR(20),complemento VARCHAR(40),bairro VARCHAR(80),localidade VARCHAR(80),uf CHAR(2),cep VARCHAR(12)) ENGINE=InnoDB');
    $c->query('CREATE TABLE endereco_cnpj (usuario_id INT,rua_cnpj VARCHAR(100),numero_cnpj VARCHAR(20),complemento_cnpj VARCHAR(40),bairro_cnpj VARCHAR(80),localidade_cnpj VARCHAR(80),uf_cnpj CHAR(2),cep_cnpj VARCHAR(12)) ENGINE=InnoDB');
    $c->query("INSERT INTO informacoes_usuario SELECT idusuario,'11.111.111/0001-11',CONCAT('Empresa Fixture ',idusuario),'111.111.111-11','solteiro' FROM usuario");
    $c->query("INSERT INTO endereco SELECT idusuario,'Rua Teste','1','Fixture','Centro','Blumenau','SC','89000-000' FROM usuario");
    $c->query("INSERT INTO endereco_cnpj SELECT idusuario,'Rua Teste Empresa','2','Fixture','Centro','Blumenau','SC','89000-000' FROM usuario");
    $c->query("INSERT INTO obra VALUES (2,'Projeto Fachada','Projeto Fachada'),(3,'Projeto Interna','Projeto Interna')");
    $c->query("INSERT INTO imagens_cliente_obra VALUES (2,'Fachada comissao Fixture','Fachada',2),(3,'Comissao quitada Fixture','Interna',3)");
    $c->query("INSERT INTO funcao VALUES (4,'Finalizacao')");
    $c->query("INSERT INTO funcao_imagem VALUES (3,1,1,1,75,'Finalizado','2026-09-10',0,0),(4,2,23,4,300,'Finalizado','2026-09-10',0,0),(5,3,40,4,300,'Finalizado','2026-09-10',1,0),(6,3,2,1,50,'Finalizado','2026-09-10',1,0)");
    $c->query("INSERT INTO pagamentos VALUES (2,8,'2026-09','pago',80,'2026-09-20'),(3,2,'2026-09','pago',50,'2026-09-20')");
    $c->query("INSERT INTO pagamento_itens VALUES (1,2,'funcao_imagem',5,80,'Comissão Gestor'),(2,3,'funcao_imagem',6,50,'')");
    documental_test_migration($c, __DIR__.'/../../sql/2026-10-02_pagamento_fechamento_documento.sql');
}
function documental_test_limpa(FechamentoRevisaoService $s, int $b, string $prefix, array $extras = []): array
{
    $r = $s->prepararRevisao($b, '2026-09', 1, 0, $prefix.'-preparar');
    if ($r['snapshot']['composicao']['fixo']['utilizado_centavos'] > 0) {
        $r = $s->decidir($b, '2026-09', 1, $r['version'], $prefix.'-liquidacao', 'LIQUIDACAO', [
            'motivo' => 'Fixture documental sem pagamento de fixo comprovada','evidencias' => [['tipo' => 'APURACAO_FIXO_SEM_PAGAMENTO','referencia' => $prefix.'-apuracao','valor_centavos' => 0,'origem_verificavel' => 'Apuração sintética fixture']]]);
    }
    return $s->decidir($b, '2026-09', 1, $r['version'], $prefix.'-bonus', 'BONUS', ['estado' => $extras ? 'DEFINIDO' : 'SEM_BONUS','motivo' => 'Fixture documental explícita','itens' => $extras]);
}
function documental_test_root(string $db): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.'pagamento_1cb_files_'.$db;
}

/** Limpeza somente de arquivos sintéticos próprios, com raiz absoluta verificada. */
function documental_test_cleanup_root(string $db): void
{
    if (!preg_match('/^pagamento_1cb_test_[a-f0-9]{10}$/D', $db)) {
        throw new RuntimeException('Limpeza documental não autorizada.');
    }
    $root = documental_test_root($db);
    if (!file_exists($root)) {
        return;
    }
    $temp = realpath(sys_get_temp_dir());
    $real = realpath($root);
    if (!$temp || !$real || is_link($root) || strcasecmp($real, $temp.DIRECTORY_SEPARATOR.'pagamento_1cb_files_'.$db) !== 0) {
        throw new RuntimeException('Raiz de limpeza insegura.');
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        $path = $entry->getPathname();
        if (!$entry->isLink()) {
            $resolved = realpath($path);
            if (!$resolved || strncasecmp($resolved, $real.DIRECTORY_SEPARATOR, strlen($real) + 1) !== 0) {
                throw new RuntimeException('Artefato fora da raiz de limpeza.');
            }
        }
        if ($entry->isDir() && !$entry->isLink()) {
            if (!rmdir($path)) {
                throw new RuntimeException('Limpeza de diretório falhou.');
            }
        } elseif (!unlink($path)) {
            throw new RuntimeException('Limpeza de arquivo falhou.');
        }
    }
    if (!rmdir($real)) {
        throw new RuntimeException('Limpeza da raiz falhou.');
    }
}
