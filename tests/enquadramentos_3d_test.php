<?php
// Uses a disposable schema in a dedicated loopback QA server. Never uses Flow credentials.
if (PHP_SAPI !== 'cli') {http_response_code(404);exit;}
require_once __DIR__.'/../helpers/enquadramento_3d_helper.php';
$c=new mysqli('127.0.0.1','root','',null,(int)($argv[1]??33791));
$db='qa_foto_'.bin2hex(random_bytes(8));
$c->query("CREATE DATABASE `$db`");$c->select_db($db);
function check($condition,$message) {if(!$condition)throw new Exception($message);}
try {
    foreach (['obra'=>'idobra INT PRIMARY KEY','colaborador'=>'idcolaborador INT PRIMARY KEY,nome_colaborador VARCHAR(80)',
        'imagens_cliente_obra'=>'idimagens_cliente_obra INT PRIMARY KEY,obra_id INT,imagem_nome VARCHAR(150),tipo_imagem VARCHAR(80)',
        'mapa_modelos_3d'=>'id INT PRIMARY KEY,obra_id INT','mapa_modelo_3d_versoes'=>'id INT PRIMARY KEY,modelo_id INT,numero INT,arquivo VARCHAR(60)'] as $table=>$ddl)
        $c->query("CREATE TABLE $table ($ddl) ENGINE=InnoDB");
    $c->query("INSERT INTO obra VALUES(10),(20)");$c->query("INSERT INTO colaborador VALUES(1,'A'),(2,'B')");
    $c->query("INSERT INTO imagens_cliente_obra VALUES(31,10,'Fachada','Fachada'),(32,20,'Outra','Fachada')");
    $c->query("INSERT INTO mapa_modelos_3d VALUES(11,10),(12,20)");
    $c->query("INSERT INTO mapa_modelo_3d_versoes VALUES(51,11,1,'one'),(52,11,2,'two'),(53,12,1,'other')");
    $ddl=file_get_contents(__DIR__.'/../sql/2026-11-24_imagem_enquadramentos_3d.sql');$c->query($ddl);$c->query($ddl);
    $identity=[1,0,0,0,0,1,0,0,0,0,1,0,0,0,0,1];
    $state=['source'=>'seagull','schema'=>1,'vertical'=>false,'unit'=>'m','projection'=>'perspective',
        'local'=>$identity,'modelToScene'=>$identity,'focal'=>24,'aspect'=>.8,'height'=>8,'roll'=>0,'shiftY'=>0,'fov'=>55,
        'solar'=>['enabled'=>true,'date'=>'2026-11-24','time'=>'08:00','config'=>['timezone'=>'America/Sao_Paulo','latitude'=>-22,'longitude'=>-48,'norte_graus'=>23]]];
    $base=['versao_id'=>51,'imagem_id'=>31,'nome'=>'Leitura','composicao'=>$state,'chave'=>str_repeat('a',36)];
    $write=fn($op,$extra=[],$actor=1,$admin=false)=>enq_write($c,10,$actor,$admin,['operacao'=>$op]+$extra+$base);
    $reject=function($fn,$code){try{$fn();}catch(RuntimeException $e){check($e->getCode()===$code,$e->getMessage());return;}throw new Exception('Operação deveria falhar');};
    $id=$write('criar');check($id>0 && $id===$write('criar'),'Criação idempotente');
    $reject(fn()=>$write('criar',['imagem_id'=>32]),403);$reject(fn()=>$write('criar',['versao_id'=>53]),404);
    $reject(fn()=>$write('editar',['id'=>$id,'revisao'=>1],2),403);
    $reject(fn()=>$write('aprovar',['id'=>$id,'revisao'=>1],1),403);
    $write('preferir',['id'=>$id,'revisao'=>1],2);$write('aprovar',['id'=>$id,'revisao'=>2],1,true);
    $reject(fn()=>$write('editar',['id'=>$id,'revisao'=>3],1,true),409);
    $reject(fn()=>$write('excluir',['id'=>$id,'revisao'=>3],1,true),409);
    $copy=$write('duplicar',['id'=>$id,'revisao'=>3,'chave'=>str_repeat('b',36)]);
    $write('preferir',['id'=>$copy,'revisao'=>1],2);$write('aprovar',['id'=>$copy,'revisao'=>2],1,true);
    $data=enq_list($c,10,11);check(count($data['enquadramentos'])===2,'Listagem');
    $old=$data['enquadramentos'][1];check(!$old['aprovado'] && $old['aprovado_em'] && $old['preferido_por']==2 && $old['preferido_em'],'Histórico de seleção');
    check($old['composicao']==$state,'Reprodução da composição');
    $reject(fn()=>$write('editar',['id'=>$id,'revisao'=>$old['revisao']],1,true),409);
    $reject(fn()=>$write('duplicar',['id'=>$id,'revisao'=>$old['revisao'],'chave'=>str_repeat('c',36),'versao_id'=>52]),409);
    $write('duplicar',['id'=>$id,'revisao'=>$old['revisao'],'chave'=>str_repeat('c',36),'versao_id'=>52,'validado'=>true]);
    $free=$write('criar',['imagem_id'=>null,'chave'=>str_repeat('d',36)]);
    $write('editar',['id'=>$free,'revisao'=>1]);$reject(fn()=>$write('editar',['id'=>$free,'revisao'=>1]),409);
    $write('excluir',['id'=>$free,'revisao'=>2]);
    echo "OK: migration repetida, gravação idempotente, integridade da obra, autorização, preferência com autoria, aprovação imutável, substituição, cópia entre versões, vínculo e revisão concorrente.\n";
} finally { $c->query("DROP DATABASE `$db`"); }
