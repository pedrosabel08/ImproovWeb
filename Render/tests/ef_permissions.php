<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../../config/database.php';
require __DIR__.'/../render_ef_helpers.php';
if (!preg_match('/^flow_ef_test_[a-f0-9]{16}$/D',getenv('DB_DATABASE') ?: '')) throw new RuntimeException('Isolated test schema required.');
$conn=improov_db_connect();
$source=['imagem_id'=>1,'obra_id'=>1,'responsavel_id'=>7];
$base=['logado'=>true,'idusuario'=>1,'nivel_acesso'=>2];
$cases=[[7,true],[8,true],[9,false],[0,false]];
foreach($cases as [$actor,$expected]) {
    if (render_ef_authorized($conn,$base+['idcolaborador'=>$actor],$source)!==$expected) throw new RuntimeException('Authorization failed for test actor.');
}
if (!render_ef_authorized($conn,['logado'=>true,'idusuario'=>1,'nivel_acesso'=>1,'idcolaborador'=>9],$source)) throw new RuntimeException('Manager policy failed.');
if (render_ef_authorized($conn,$base+['idcolaborador'=>8],$source+['unused'=>null])!==true) throw new RuntimeException('Owner policy failed.');
echo 'permissions: OK';
