<?php
// Rota de homologação disponível apenas em loopback, com sessão, DB e arquivos sintéticos.
if (!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)
 || ($_SERVER['HTTP_HOST']??'')!=='localhost:8066') { http_response_code(404); exit; }
$manifest=__DIR__.'/../output/mensal-fixture.json';
if (!is_file($manifest)) { http_response_code(404); exit; }
$fixture=json_decode(file_get_contents($manifest),true);
if (!preg_match('/^pagamento_1cb_test_[a-f0-9]{10}$/D',$fixture['db']??'')) { http_response_code(404); exit; }
session_name('MENSAL_FIXTURE');
require_once __DIR__.'/../Pagamento/pagamento_auth.php';
if (($_SESSION['logado']??false)!==true) {
 if (($_SERVER['REQUEST_METHOD']??'')==='POST' && ($_POST['login']??'')==='pedro_imp' && hash_equals('Pedro0808',$_POST['senha']??'')) {
  session_regenerate_id(true); $_SESSION=['logado'=>true,'idusuario'=>1,'nivel_acesso'=>1,'nome_usuario'=>'Homologação sintética','login_ts'=>time(),'last_activity'=>time()];
  header('Location: mensal-browser.php'); exit;
 }
 echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Homologação mensal</title><h1>Fechamento mensal — ambiente sintético</h1><p>Dados de teste. Nenhum pagamento ou documento real é alterado.</p><form method="post"><label>Login<input name="login" required></label><label>Senha<input name="senha" type="password" required></label><button>Entrar</button></form></html>'; exit;
}
foreach(['PAGAMENTO_FECHAMENTO_V2_ENABLED'=>'1','PAGAMENTO_FECHAMENTO_DB_HOST'=>'127.0.0.1','PAGAMENTO_FECHAMENTO_DB_PORT'=>'3320','PAGAMENTO_FECHAMENTO_DB_NAME'=>$fixture['db'],'PAGAMENTO_FECHAMENTO_DB_USER'=>'root','PAGAMENTO_FECHAMENTO_DB_PASSWORD'=>'','PAGAMENTO_FECHAMENTO_STORAGE_ROOT'=>$fixture['root']] as $name=>$value) {
 putenv($name.'='.$value);
 // O SetEnv do Apache tem prioridade no getenv: sobrescrever somente neste request de teste.
 if(function_exists('apache_setenv')) apache_setenv($name,$value);
}
if(isset($_GET['fixture_action'])) {
 $action=$_GET['fixture_action']; unset($_GET['fixture_action']);
 if(!in_array($action,['mensal','iniciar','obter','preparar','decidir','gerar','listar','visualizar','confirmar','recuperar'],true)) { http_response_code(404); exit; }
 require_once __DIR__.'/../Pagamento/api/FechamentoHttp.php';
 FechamentoHttp::executar($action,in_array($action,['mensal','obter','listar'],true)?'GET':'POST'); exit;
}
function asset_url(string $path): string { return '/ImproovWeb/Pagamento/'.$path.'?v=fixture'; }
ob_start(); require __DIR__.'/../Pagamento/fechamento.php'; $html=ob_get_clean();
// Reescreve somente a base do transporte. A UI, PDF.js e endpoints são código real.
$html=str_replace('<head>','<head><meta name="mensal-test-api" content="mensal-browser.php?fixture_action=">',$html);
$html=str_replace('<h1>Fechamento mensal</h1>','<h1>Fechamento mensal</h1><p>Homologação · dados sintéticos</p>',$html);
// Exercita os tokens existentes nos dois temas sem mudar preferências do usuário.
$theme=($_GET['theme']??'dark')==='light'?'light':'dark';
$html=str_replace('<html lang="pt-BR">','<html lang="pt-BR" data-theme="'.$theme.'">',$html);
$html=str_replace('Homologação · dados sintéticos','Homologação · dados sintéticos · <a href="?theme=dark">Escuro</a> / <a href="?theme=light">Claro</a>',$html);
echo $html;
