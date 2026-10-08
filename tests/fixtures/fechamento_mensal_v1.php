<?php
require_once __DIR__.'/pagamento_adendos_documental.php';
function mensal_test_seed(): array {
 $c=documental_test_connection(); $db='pagamento_1cb_test_'.bin2hex(random_bytes(5));
 $c->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); $c->select_db($db); documental_test_schema($c);
 $c->query(file_get_contents(__DIR__.'/../../sql/2026-10-06_fechamento_mensal_v1.sql'));
 $c->query(file_get_contents(__DIR__.'/../../sql/2026-10-06_fechamento_mensal_participacao.sql'));
 if ((int)$c->query('SELECT COUNT(*) n FROM colaborador WHERE participa_fechamento_mensal IS NOT NULL')->fetch_assoc()['n']!==0) throw new RuntimeException('Migration classificou colaboradores.');
 $c->query('ALTER TABLE colaborador ADD ativo TINYINT NOT NULL DEFAULT 1');
 $c->query("CREATE TABLE historico_imagens (idhistorico INT PRIMARY KEY,imagem_id INT,status_id INT,data_movimento DATETIME) ENGINE=InnoDB");
 $c->query('UPDATE colaborador SET participa_fechamento_mensal=1');
 $c->query('UPDATE colaborador SET ativo=0 WHERE idcolaborador=40');
 $c->query("UPDATE colaborador SET participa_fechamento_mensal=0,tipo_remuneracao=NULL,valor_fixo=NULL WHERE idcolaborador=13");
 $c->query("UPDATE colaborador SET participa_fechamento_mensal=NULL,tipo_remuneracao='FIXO',valor_fixo=2500 WHERE idcolaborador=23");
 $names=[1=>['Adriana — sintético','FIXO'],2=>['Anderson — sintético','VARIAVEL'],3=>['Nicolle — sintético','FIXO_VARIAVEL'],4=>['Cadastro pendente — sintético',null],5=>['Divergência — sintético','VARIAVEL'],6=>['Marcio — sintético','VARIAVEL'],8=>['Bruna — sintético','VARIAVEL']];
 $stmt=$c->prepare('UPDATE colaborador SET nome_colaborador=?,tipo_remuneracao=? WHERE idcolaborador=?');
 foreach($names as $id=>[$name,$type]) { $stmt->bind_param('ssi',$name,$type,$id); $stmt->execute(); }
 $c->query('UPDATE colaborador SET valor_fixo=4600 WHERE idcolaborador=3');
 $c->query("INSERT INTO funcao VALUES (3,'Composição')");
 $c->query("INSERT INTO funcao_imagem VALUES (900,1,3,1,75,'Finalizado','2026-09-10',0,0)");
 $c->query("INSERT INTO funcao_imagem VALUES (901,1,1,4,0,'Finalizado','2026-09-10',0,0),(902,1,3,3,0,'Finalizado','2026-09-10',0,0)");
 // Reprodução do caso Bruna: parcela inicial para outro executor e complemento final quitado.
 $c->query("INSERT INTO imagens_cliente_obra VALUES (2082,'9.WER_RIO Brinquedoteca','Interna')");
 $c->query("INSERT INTO funcao_imagem VALUES (903,2082,8,4,380,'Finalizado','2026-07-16',1,0)");
 $c->query("INSERT INTO historico_imagens VALUES (2001,2082,3,'2026-09-01 08:00:00')");
 $c->query("INSERT INTO historico_imagens VALUES (2002,1,1,'2026-09-01 08:00:00')");
 $c->query("INSERT INTO log_alteracoes VALUES (2001,903,'2026-09-09 10:00:00','Finalizado','Ajuste')");
 $c->query("INSERT INTO pagamentos VALUES (900,8,'2026-07','pago',190,'2026-08-01 10:00:00'),(901,2,'2026-02','pago',125,'2026-03-01 10:00:00')");
 $c->query("INSERT INTO pagamento_itens VALUES (900,900,'funcao_imagem',903,190,'Pago Completa'),(901,901,'funcao_imagem',903,125,'Finalização Parcial')");
 $id=100;
 foreach([2=>[21,380],6=>[32,420],8=>[10,380]] as $b=>[$n,$tarifa]) {
  for($i=0;$i<$n;$i++) {
   $id++; $c->query("INSERT INTO imagens_cliente_obra VALUES ($id,'Imagem R0 $id','Interna')");
   $c->query("INSERT INTO funcao_imagem VALUES ($id,$id,$b,4,$tarifa,'Finalizado','2026-09-10',0,0)");
   $c->query("INSERT INTO historico_imagens VALUES ($id,$id,2,'2026-09-01 08:00:00')");
   $c->query("INSERT INTO log_alteracoes VALUES ($id,$id,'2026-09-10 10:00:00','Finalizado','Em andamento')");
  }
 }
 // R1 e uma conclusão antiga repetida no mês não podem acrescentar bônus.
 $c->query("INSERT INTO historico_imagens VALUES (1000,101,3,'2026-09-12 10:00:00')");
 $c->query("INSERT INTO log_alteracoes VALUES (1000,101,'2026-09-15 10:00:00','Finalizado','Ajuste')");
 $c->query("INSERT INTO log_alteracoes VALUES (1001,102,'2026-10-02 10:00:00','Finalizado','Ajuste')");
 for($i=0;$i<20;$i++) {
  $id++; $c->query("INSERT INTO imagens_cliente_obra VALUES ($id,'Composição $id','Interna')");
  $c->query("INSERT INTO funcao_imagem VALUES ($id,$id,8,3,100,'Finalizado','2026-09-10',0,0)");
 }
 $root=documental_test_root($db); mkdir($root,0700,true); $c->close();
 return ['db'=>$db,'root'=>$root];
}
