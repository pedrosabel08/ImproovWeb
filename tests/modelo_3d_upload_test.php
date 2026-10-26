<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../helpers/mapa_modelo_3d_helper.php';
require_once __DIR__ . '/../helpers/modelo_3d_upload_helper.php';
function upload_check(bool $test, string $message): void { if (!$test) throw new RuntimeException($message); }
function upload_reject(callable $fn, string $message): void {
    try { $fn(); } catch (InvalidArgumentException $error) { return; }
    throw new RuntimeException($message);
}
function upload_fixture(string|array $name, float $height = 2, ?string $uri = null): string {
    $positions = [0,0,0, 2,0,0, 0,$height,0, 0,0,2];
    $binary = pack('g*', ...$positions) . pack('v*', 0,2,1, 0,1,3, 0,3,2, 1,2,3);
    $data = ['asset'=>['version'=>'2.0'], 'scene'=>0, 'scenes'=>[['nodes'=>[0]]], 'nodes'=>[['name'=>$name,'mesh'=>0]],
        'meshes'=>[['primitives'=>[['attributes'=>['POSITION'=>0],'indices'=>1]]]],
        'buffers'=>[['byteLength'=>strlen($binary)]],
        'bufferViews'=>[['buffer'=>0,'byteOffset'=>0,'byteLength'=>48],['buffer'=>0,'byteOffset'=>48,'byteLength'=>24]],
        'accessors'=>[['bufferView'=>0,'componentType'=>5126,'count'=>4,'type'=>'VEC3','min'=>[0,0,0],'max'=>[2,$height,2]], ['bufferView'=>1,'componentType'=>5123,'count'=>12,'type'=>'SCALAR']]];
    if (is_array($name)) {
        $data['nodes'] = [];
        $data['scenes'][0]['nodes'] = [];
        foreach ($name as $index => $floor) {
            $id = count($data['nodes']);
            $data['scenes'][0]['nodes'][] = $id;
            $data['nodes'][] = ['name'=>$floor, 'children'=>[$id + 1], 'translation'=>[0, $index * ($height + 1), 0]];
            $data['nodes'][] = ['name'=>'Geometria_' . $index, 'mesh'=>0];
        }
    }
    if ($uri) $data['images'] = [['uri'=>$uri]];
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    $json .= str_repeat(' ', (4 - strlen($json) % 4) % 4);
    return 'glTF' . pack('VV',2,12+8+strlen($json)+8+strlen($binary)) . pack('VV',strlen($json),0x4e4f534a) . $json . pack('VV',strlen($binary),0x004e4942) . $binary;
}
$file = tempnam(sys_get_temp_dir(), 'glb_test_');
try {
    ini_set('upload_max_filesize', '500M');
    $valid = upload_fixture('PAVTO_LAZER_01');
    file_put_contents($file, $valid);
    $meta = mp3d_validate_upload($file, 'prédio.GLB');
    upload_check($meta['pavimentos'] === ['PAVTO_LAZER_01'] && $meta['sha256'] === hash('sha256',$valid), 'GLB válido não identificado.');
    upload_reject(fn()=>mp3d_validate_upload($file,'modelo.php'), 'Extensão inválida aceita.');
    $floorNames = ['PAVTO_LAZER1_001', 'PAVTO_TIPO1_003', 'PAV_TERREO_001', 'PAVTO_LAZER2_001'];
    foreach ($floorNames as $floor) {
        upload_check(mp3d_floor_name($floor) === $floor, 'Nome completo do pavimento alterado: ' . $floor);
        upload_check(mp3d_floor_name($floor . '_002') === $floor, 'Sufixo da peça confundido com pavimento: ' . $floor);
        file_put_contents($file, upload_fixture($floor)); clearstatcache(true, $file);
        upload_check(mp3d_validate_upload($file, 'modelo.glb')['pavimentos'] === [$floor], 'Mesh de pavimento não reconhecido: ' . $floor);
    }
    $namedGroups = upload_fixture($floorNames);
    file_put_contents($file, $namedGroups); clearstatcache(true, $file);
    $expectedFloors = $floorNames; sort($expectedFloors, SORT_NATURAL);
    upload_check(mp3d_validate_upload($file, 'modelo.glb')['pavimentos'] === $expectedFloors, 'Grupos de pavimentos distintos foram perdidos ou unidos.');
    foreach (['invalid'=> 'not a GLB', 'truncated'=>substr($valid,0,-4), 'length'=>$valid.'xxxx', 'external'=>upload_fixture('PAVTO_LAZER_01',2,'https://example.invalid/texture.jpg'), 'relative'=>upload_fixture('PAVTO_LAZER_01',2,'texture.jpg'), 'floor'=>upload_fixture('TERRENO_01')] as $case=>$content) {
        file_put_contents($file,$content); clearstatcache(true,$file);
        upload_reject(fn()=>mp3d_validate_upload($file,'modelo.glb'), "GLB inválido aceito: $case");
    }
    foreach (['../Projeto-TES_TES.glb','uploads/model-3d/../../conexaoMain.php','uploads/model-3d/obra-74/obra-75_modelo-1_v0001_20261014T184500Z_a1b2c3d4e5f6.glb'] as $path) upload_reject(fn()=>mp3d_file($path),'Caminho inválido aceito.');
    upload_check(mp3d_ini_bytes('512M') === 536870912 && mp3d_ini_bytes('1G') === 1073741824, 'Limites PHP incorretos.');
    upload_check((bool)preg_match('~^uploads/model-3d/obra-74/obra-74_modelo-12_v0001_[0-9]{8}T[0-9]{6}Z_[a-f0-9]{12}\.glb$~',mp3d_upload_filename(74,12,1)), 'Nome não padronizado.');
    // Fixtures pequenas para QA via navegador, sem conteúdo de nenhuma obra real.
    if (in_array('--fixtures', $argv,true)) {
        $directory = __DIR__.'/../tmp/mapa-pavimento-qa';
        if (!is_dir($directory)) mkdir($directory,0755,true);
        foreach (['v1'=>upload_fixture('PAVTO_LAZER_01'), 'v2'=>upload_fixture('PAVTO_LAZER_01',3), 'missing-floor'=>upload_fixture('PAVTO_TIPO_01'), 'named-groups'=>$namedGroups, 'invalid'=>'invalid glb'] as $name=>$content) file_put_contents($directory.'/upload3d-'.$name.'.glb',$content);
    }
    echo "OK: GLB autocontido, pavimentos, integridade, extensão, recursos externos, caminhos e nomes padronizados.\n";
} finally { unlink($file); }
