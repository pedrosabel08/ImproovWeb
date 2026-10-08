<?php
// Rota de homologação disponível apenas em loopback, com sessão, DB e arquivos sintéticos.
if (!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)
 || ($_SERVER['HTTP_HOST']??'')!=='localhost:8066') { http_response_code(404); exit; }
$manifest=__DIR__.'/../output/competencia-fixture.json';
if (!is_file($manifest)) { http_response_code(404); exit; }
$fixture=json_decode(file_get_contents($manifest),true);
if (!preg_match('/^pagamento_1cb_test_[a-f0-9]{10}$/D',$fixture['db']??'')) { http_response_code(404); exit; }
session_name('COMPETENCIA_FIXTURE');
require_once __DIR__.'/../Pagamento/pagamento_auth.php';
require_once __DIR__.'/../config/pagamento_fechamento.php';
if (($_SESSION['logado']??false)!==true) {
 if (($_SERVER['REQUEST_METHOD']??'')==='POST' && ($_POST['login']??'')==='pedro_imp' && hash_equals('Pedro0808',$_POST['senha']??'')) {
  session_regenerate_id(true); $_SESSION=['logado'=>true,'idusuario'=>1,'nivel_acesso'=>1,'nome_usuario'=>'Homologação sintética','login_ts'=>time(),'last_activity'=>time()];
  header('Location: competencia-browser.php'); exit;
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
 if($action==='overview') {
  require_once __DIR__.'/../Pagamento/resumo_geral.php';
  $c=pagamento_fechamento_connection();
  pagamento_json(['success'=>true]+pagamento_resumo_geral($c,(int)($_GET['mes']??9),(int)($_GET['ano']??2026)));
 }
 if(in_array($action,['competencia','concluir','quitar','pagar'],true)) { require __DIR__.'/../Pagamento/api/fechamento/competencia.php'; exit; }
 if(!in_array($action,['mensal','iniciar','obter','preparar','decidir','gerar','listar','visualizar','confirmar','recuperar'],true)) { http_response_code(404); exit; }
 require_once __DIR__.'/../Pagamento/api/FechamentoHttp.php';
 FechamentoHttp::executar($action,in_array($action,['mensal','obter','listar'],true)?'GET':'POST'); exit;
}
function asset_url(string $path): string { return '/ImproovWeb/Pagamento/'.$path.'?v=fixture'; }
ob_start(); require __DIR__.'/../Pagamento/'.(isset($_GET['view'])?'index.php':'fechamento.php'); $html=ob_get_clean();
if(isset($_GET['view'])) {
 require_once __DIR__.'/../Pagamento/services/FechamentoCompetenciaService.php';
 $html=str_replace('<head>','<head><meta name="pagamento-test-competencia" content="competencia-browser.php?fixture_action=competencia"><meta name="pagamento-test-overview" content="competencia-browser.php?fixture_action=overview&amp;">',$html);
}
$html=str_replace('<h1>Pagamento</h1>','<h1>Pagamento · dados sintéticos</h1>',$html);
$html=str_replace('<head>','<head><base href="http://localhost:8066/ImproovWeb/tests/">',$html);

// Reescreve somente a base do transporte. A UI, PDF.js e endpoints são código real.
$html=str_replace('<head>','<head><meta name="mensal-test-api" content="competencia-browser.php?fixture_action=">',$html);
$html=str_replace('<h1>Fechamento mensal</h1>','<h1>Fechamento mensal</h1><p>Homologação · dados sintéticos</p>',$html);
// Exercita os tokens existentes nos dois temas sem mudar preferências do usuário.
$theme=($_GET['theme']??'dark')==='light'?'light':'dark';
$html=str_replace('<html lang="pt-BR">','<html lang="pt-BR" data-theme="'.$theme.'">',$html);
$html=str_replace('Homologação · dados sintéticos','Homologação · dados sintéticos · <a href="?theme=dark">Escuro</a> / <a href="?theme=light">Claro</a>',$html);
$banner='<div id="competencia-fixture-nav" style="position:absolute;left:60px;right:8px;top:0;z-index:2000;width:auto;height:auto;display:flex;flex-wrap:wrap;gap:8px;padding:6px 8px;background:#fff;color:#000;line-height:1.4;font-size:12px">Homologação isolada: <a href="competencia-browser.php">Fechamento</a> · <a href="competencia-browser.php?view=geral&mes=9&ano=2026">Visão geral</a> · <a href="competencia-browser.php?view=colaborador&mes=9&ano=2026">Por colaborador</a></div>';
$html=preg_replace('~(<body[^>]*>)~','$1'.$banner,$html,1);
echo $html;
