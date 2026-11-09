<?php
if (PHP_SAPI !== 'cli' || !preg_match('/^flow_ef_test_[a-f0-9]{16}$/D',getenv('DB_DATABASE') ?: '')) {http_response_code(404);exit;}
require __DIR__.'/../../config/session_bootstrap.php';
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$_SESSION=['logado'=>true,'idusuario'=>1,'idcolaborador'=>(int)($input['actor']??7),'nivel_acesso'=>2,'render_ef_csrf'=>'test-only-csrf'];
$_SERVER['REQUEST_METHOD']='POST';
$_POST=$input+['csrf'=>'test-only-csrf'];
require __DIR__.'/../render_ef.php';
