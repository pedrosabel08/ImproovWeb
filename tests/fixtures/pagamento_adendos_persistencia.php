<?php

/** Somente fixtures sintéticas em instância loopback dedicada. Nunca require conexao.php. */
function fechamento_test_connection(string $database = ''): mysqli
{
    if ($database!=='' && !preg_match('/^pagamento_1ca_test_[a-f0-9]{10}$/D',$database)) throw new RuntimeException('Banco de teste não autorizado.');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $c=new mysqli('127.0.0.1','root','',$database,3319); $c->set_charset('utf8mb4');
    return $c;
}

function fechamento_test_schema(mysqli $c): void
{
    $ddls=[
        'CREATE TABLE usuario (idusuario INT PRIMARY KEY,nivel_acesso INT,ativo TINYINT NOT NULL) ENGINE=InnoDB',
        'CREATE TABLE colaborador (idcolaborador INT PRIMARY KEY,nome_colaborador VARCHAR(45),valor_fixo DECIMAL(10,2) NULL) ENGINE=InnoDB',
        'CREATE TABLE funcao (idfuncao INT PRIMARY KEY,nome_funcao VARCHAR(45)) ENGINE=InnoDB',
        'CREATE TABLE imagens_cliente_obra (idimagens_cliente_obra INT PRIMARY KEY,imagem_nome VARCHAR(80),tipo_imagem VARCHAR(80)) ENGINE=InnoDB',
        'CREATE TABLE funcao_imagem (idfuncao_imagem INT PRIMARY KEY,imagem_id INT,colaborador_id INT,funcao_id INT,valor DECIMAL(12,2),status VARCHAR(40),prazo DATE,pagamento INT DEFAULT 0,parcial INT DEFAULT 0) ENGINE=InnoDB',
        'CREATE TABLE log_alteracoes (idlog INT PRIMARY KEY,funcao_imagem_id INT,data DATETIME,status_novo VARCHAR(40),status_anterior VARCHAR(40)) ENGINE=InnoDB',
        'CREATE TABLE animacao (idanimacao INT PRIMARY KEY,colaborador_id INT,imagem_id INT,data_anima DATE,valor DECIMAL(12,2),pagamento INT) ENGINE=InnoDB',
        'CREATE TABLE funcao_animacao (id INT PRIMARY KEY,animacao_id INT,colaborador_id INT,funcao_id INT,valor DECIMAL(12,2),status VARCHAR(40),prazo DATE,pagamento INT,parcial INT) ENGINE=InnoDB',
        'CREATE TABLE acompanhamento (idacompanhamento INT PRIMARY KEY,imagem_id INT,colaborador_id INT,data DATE,valor DECIMAL(12,2),pagamento INT) ENGINE=InnoDB',
        'CREATE TABLE pagamentos (idpagamento INT PRIMARY KEY,colaborador_id INT,mes_ref CHAR(7),status VARCHAR(100),valor_total DECIMAL(12,2),pago_em DATETIME) ENGINE=InnoDB',
        'CREATE TABLE pagamento_itens (idpagamento_item INT PRIMARY KEY,pagamento_id INT,origem VARCHAR(100),origem_id INT,valor DECIMAL(12,2),observacao VARCHAR(255)) ENGINE=InnoDB',
        'CREATE TABLE adendos (id INT PRIMARY KEY,colaborador_id INT,competencia VARCHAR(7),status VARCHAR(40),payload_enviado LONGTEXT,created_at DATETIME) ENGINE=InnoDB',
    ];
    foreach ($ddls as $sql) $c->query($sql);
    fechamento_test_migration($c,__DIR__.'/../../sql/2026-10-02_pagamento_fechamento_revisao.sql');
    $c->query('INSERT INTO usuario VALUES (1,1,1),(2,5,1),(3,2,1),(4,1,0)');
    $c->query("INSERT INTO colaborador VALUES (1,'Fixture 1',1500),(2,'Fixture 2',1500),(3,'Fixture 3',0),(4,'Fixture 4',NULL),(5,'Fixture 5',0),(6,'Fixture 6',0)");
    $c->query("INSERT INTO funcao VALUES (1,'Fixture função')");
    $c->query("INSERT INTO imagens_cliente_obra VALUES (1,'Fixture imagem','Interna')");
    $c->query("INSERT INTO funcao_imagem VALUES (1,1,2,1,100,'Finalizado','2026-09-10',0,0),(2,1,5,1,250,'Finalizado','2026-09-10',1,0)");
    // Cabeçalho pago/adendo assinado não provam fixo nem bônus.
    $c->query("INSERT INTO pagamentos VALUES (1,2,'2026-09','pago',1500,'2026-09-10')");
    $c->query("INSERT INTO adendos VALUES (1,2,'2026-09','assinado','{\"VALOR_FIXO\":1500.5,\"VALOR_TOTAL\":1500.5}','2026-09-10')");
}

function fechamento_test_migration(mysqli $c,string $path): void
{
    $sql=preg_replace('/^--.*$/m','',file_get_contents($path));
    foreach (explode(';',$sql) as $statement) if (trim($statement)!=='') $c->query($statement);
}
