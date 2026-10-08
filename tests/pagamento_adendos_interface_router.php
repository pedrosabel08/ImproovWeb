<?php
// Somente php -S, bind loopback, banco descartável e configuração explícita.
if(PHP_SAPI!=='cli-server' || ($_SERVER['SERVER_PORT']??'')!='8066'
    || getenv('PAGAMENTO_FECHAMENTO_DB_HOST')!=='127.0.0.1'
    || getenv('PAGAMENTO_FECHAMENTO_DB_PORT')!=='3320'
    || !preg_match('/^pagamento_1cb_test_[a-f0-9]{10}$/D',getenv('PAGAMENTO_FECHAMENTO_DB_NAME')?:'')) { http_response_code(404); exit; }
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$base='/ImproovWeb/';
if(in_array($path,[$base,$base.'index.html',$base.'fixture-login'],true)) {
    require_once __DIR__.'/../Pagamento/pagamento_auth.php';
    if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
        $accounts=['pedro_imp'=>[1,1],'gestor_fixture'=>[8,5],'restrito_fixture'=>[3,2],'inativo_fixture'=>[4,1]];
        $account=$accounts[$_POST['usuario']??'']??null;
        if(!$account || !hash_equals(getenv('PAGAMENTO_FIXTURE_PASSWORD')?:'',$_POST['senha']??'')) { http_response_code(401); echo 'Login sintético inválido'; exit; }
        session_regenerate_id(true);
        $_SESSION=['logado'=>true,'idusuario'=>$account[0],'nivel_acesso'=>$account[1],'nome_usuario'=>'Usuário sintético','login_ts'=>time(),'last_activity'=>time()];
        header('Location: '.$base.'fixture-menu'); exit;
    }
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Homologação isolada — login</title><h1>Homologação sintética FASE 1D</h1><p>Banco e PDFs descartáveis; nenhuma assinatura ou pagamento real.</p><form method="post"><label>Usuário<input name="usuario" required></label><label>Senha<input name="senha" type="password" required></label><button>Entrar</button></form></html>'; exit;
}
if($path===$base.'fixture-menu' || $path===$base.'Pagamento/' || $path===$base.'Pagamento/index.php') {
    require_once __DIR__.'/../Pagamento/pagamento_auth.php'; pagamento_require_gestor(false);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="pagamento-csrf" content="'.htmlspecialchars(pagamento_csrf_token(),ENT_QUOTES).'">';
    echo '<h1>Homologação sintética FASE 1D</h1><p>Shell de navegação da fixture; página e endpoints novos são os arquivos reais.</p>';
    if($path===$base.'fixture-menu') echo '<a href="'.$base.'Pagamento/">Pagamento</a>';
    else echo '<a href="fechamento.php">Fechamento mensal</a>';
    exit;
}
if(!str_starts_with($path,$base) || str_contains($path,'..') || str_contains($path,'%')) { http_response_code(404);exit; }
$relative=substr($path,strlen($base)); $file=realpath(__DIR__.'/../'.$relative); $repo=realpath(__DIR__.'/..');
if(!$file || !str_starts_with($file,$repo.DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404);exit; }
if(str_ends_with($file,'.php')) {
    if($relative!=='Pagamento/fechamento.php' && !preg_match('~^Pagamento/api/(fechamento|documento)/[a-z]+\.php$~D',$relative)) { http_response_code(404);exit; }
    require $file; exit;
}
if(!in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)),['js','css','png','jpg','jpeg','gif','svg','ico','woff','woff2','ttf'],true)) { http_response_code(404);exit; }
return false;
