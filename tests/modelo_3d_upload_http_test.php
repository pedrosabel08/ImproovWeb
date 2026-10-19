<?php
// QA HTTP real; cria apenas um modelo temporário em TES_TES e remove sua própria fixture.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/referencia_arquitetonica_helper.php';
require_once __DIR__ . '/../helpers/mapa_modelo_3d_helper.php';
$directory = __DIR__ . '/../tmp/mapa-pavimento-qa';
$manifest = $directory . '/upload3d-http-fixture.json';
function http_check(bool $test, string $label): void { if (!$test) throw new RuntimeException($label); }
function http_cleanup(mysqli $conn, array $fixture): void {
    $model = mp_row($conn, 'SELECT id,nome,arquivo FROM mapa_modelos_3d WHERE id=? AND obra_id=74 AND nome=?', 'is', [$fixture['id'], $fixture['nome']]);
    if (!$model) return;
    $files = array_column(mp_rows($conn, 'SELECT arquivo FROM mapa_modelo_3d_versoes WHERE modelo_id=?', 'i', [$model['id']]), 'arquivo');
    foreach ($files as $relative) {
        http_check(str_contains($relative, 'obra-74_modelo-' . $model['id'] . '_'), 'Arquivo de fixture fora do modelo de teste.');
        mp3d_remove_published($relative);
        $path = mp3d_file($relative);
        unlink($path);
    }
    mp_query($conn, 'DELETE FROM mapa_modelos_3d WHERE id=? AND obra_id=74 AND nome=?', 'is', [$model['id'], $fixture['nome']])->close();
}
$conn = conectarBanco();
if (in_array('--cleanup', $argv, true)) {
    if (is_file($manifest)) { http_cleanup($conn, json_decode(file_get_contents($manifest), true, 16, JSON_THROW_ON_ERROR)); unlink($manifest); }
    echo "OK: somente a fixture de upload foi removida.\n"; exit;
}
$login = getenv('IMPROOV_QA_LOGIN'); $password = getenv('IMPROOV_QA_PASSWORD');
if (!$login || !$password) throw new RuntimeException('Configure IMPROOV_QA_LOGIN e IMPROOV_QA_PASSWORD apenas para a execução do teste.');
if (is_file($manifest)) throw new RuntimeException('Remova a fixture anterior com --cleanup antes de executar novamente.');
$curl = curl_init();
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_COOKIEFILE=>'', CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>300]);
$base = 'http://localhost:8066/ImproovWeb/';
$request = function (string $path, ?array $data = null, ?string $csrf = null, bool $multipart = false, bool $binary = false) use ($curl, $base): array {
    $headers = ['Accept: application/json'];
    if ($csrf !== null) $headers[] = 'X-CSRF-Token: ' . $csrf;
    if ($data !== null && !$multipart) $headers[] = 'Content-Type: application/json';
    curl_setopt_array($curl, [CURLOPT_URL=>$base.$path, CURLOPT_HTTPHEADER=>$headers, CURLOPT_POST=>$data !== null, CURLOPT_POSTFIELDS=>$data === null ? null : ($multipart ? $data : json_encode($data))]);
    // CURLOPT_POSTFIELDS muda o método; restaura GET ao consultar.
    if ($data === null) curl_setopt($curl, CURLOPT_HTTPGET, true);
    $raw = curl_exec($curl);
    if ($raw === false) throw new RuntimeException('Falha HTTP: ' . curl_error($curl));
    $code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    return [$code, $binary ? $raw : json_decode($raw, true, 128, JSON_THROW_ON_ERROR)];
};
$fixture = null; $keep = false;
try {
    [$code] = $request('MapaPavimento/api.php?action=modelo_3d_arquivo&obra_id=74&modelo_id=1');
    http_check($code === 401, 'Download sem login não foi recusado.');
    [$code,$result] = $request('login.php', ['login'=>$login,'senha'=>$password], null, true);
    http_check($code === 200 && ($result['status']??'') === 'success', 'Login de QA falhou.');
    [$code,$context] = $request('MapaPavimento/api.php?action=modelos_3d&obra_id=74');
    http_check($code === 200 && $context['can_edit'], 'Contexto de edição indisponível.');
    $csrf = $context['csrf'];
    $name = 'QA GLB upload ' . bin2hex(random_bytes(8));
    $upload = fn(string $file, array $extra=[]) => ['obra_id'=>74,'nome'=>$name,'glb'=>new CURLFile($directory.'/upload3d-'.$file.'.glb','model/gltf-binary','fixture.glb')] + $extra;
    [$code] = $request('MapaPavimento/api.php?action=modelo_3d_upload', $upload('v1'), 'invalid', true);
    http_check($code === 403, 'CSRF inválido aceito.');
    [$code] = $request('MapaPavimento/api.php?action=modelo_3d_upload', $upload('invalid'), $csrf, true);
    http_check($code === 422, 'GLB inválido aceito.');
    [$code,$result] = $request('MapaPavimento/api.php?action=modelo_3d_upload', $upload('v1'), $csrf, true);
    http_check($code === 200 && $result['numero'] === 1 && !$result['duplicado'], 'Upload inicial falhou.');
    $fixture = ['id'=>$result['id'],'nome'=>$name];
    file_put_contents($manifest, json_encode($fixture));
    $id = $fixture['id'];
    $model = mp3d_model($conn,74,$id);
    [$code] = $request($model['arquivo'], null, null, false, true);
    http_check($code === 403, 'Acesso direto ao arquivo de upload não foi bloqueado.');
    [$code,$binary] = $request("MapaPavimento/api.php?action=modelo_3d_arquivo&obra_id=74&modelo_id=$id", null, null, false, true);
    http_check($code === 200 && hash('sha256',$binary) === hash_file('sha256',$directory.'/upload3d-v1.glb'), 'Download autenticado diverge do GLB enviado.');
    [$code] = $request("MapaPavimento/api.php?action=modelo_3d_arquivo&obra_id=102&modelo_id=$id");
    http_check($code === 404, 'Isolamento entre obras falhou.');
    $author = (int)mp_row($conn,'SELECT idcolaborador FROM usuario WHERE login=?','s',[$login])['idcolaborador'];
    mp3d_save_link($conn,74,$id,'PAVTO_LAZER_01',3,0,$author);
    $links = mp3d_links($conn,$id);
    [$code] = $request('MapaPavimento/api.php?action=modelo_3d_upload', $upload('missing-floor',['modelo_id'=>$id,'arquivo_atual'=>$model['arquivo']]), $csrf, true);
    http_check($code === 422, 'Versão com pavimento vinculado ausente foi aceita.');
    [$code] = $request('MapaPavimento/api.php?action=modelo_3d_upload', $upload('v2',['modelo_id'=>$id,'arquivo_atual'=>'stale.glb']), $csrf, true);
    http_check($code === 409, 'Atualização concorrente foi aceita.');
    [$code,$result] = $request('MapaPavimento/api.php?action=modelo_3d_upload', $upload('v2',['modelo_id'=>$id,'arquivo_atual'=>$model['arquivo']]), $csrf, true);
    http_check($code === 200 && $result['numero'] === 2, 'Upload da versão 2 falhou.');
    $current = mp3d_model($conn,74,$id);
    [$code,$result] = $request('MapaPavimento/api.php?action=modelo_3d_upload', $upload('v2',['modelo_id'=>$id,'arquivo_atual'=>$current['arquivo']]), $csrf, true);
    http_check($code === 200 && $result['duplicado'], 'Conteúdo duplicado não foi reconhecido.');
    [$code,$history] = $request("MapaPavimento/api.php?action=modelo_3d_versoes&obra_id=74&modelo_id=$id");
    http_check($code === 200 && count($history['versoes'])===2 && $history['versoes'][0]['ativa'], 'Histórico incorreto.');
    [$code] = $request('MapaPavimento/api.php?action=modelo_3d_ativar_versao', ['obra_id'=>74,'modelo_id'=>$id,'versao_id'=>$history['versoes'][1]['id'],'arquivo_atual'=>$current['arquivo']], $csrf);
    http_check($code === 200 && mp3d_model($conn,74,$id)['arquivo']===$model['arquivo'] && mp3d_links($conn,$id)===$links, 'Recuperação não preservou os vínculos.');
    [$sftp,$root] = mp3d_sftp();
    $remote = $root.'/'.$current['arquivo'];
    http_check($sftp->filesize($remote) === filesize($directory.'/upload3d-v2.glb'), 'GLB não chegou ao VPS.');
    // Remove somente o cache local da fixture para verificar a recuperação do VPS.
    unlink(mp3d_file($current['arquivo']));
    http_check(hash_file('sha256',mp3d_file($current['arquivo'])) === hash_file('sha256',$directory.'/upload3d-v2.glb'), 'Recuperação do VPS para cache local falhou.');
    echo "OK: upload HTTP + VPS, validação, CSRF, login, isolamento, versões, duplicidade, conflitos e vínculos preservados.\n";
    $keep = in_array('--keep', $argv,true);
    if ($keep) echo 'Fixture para QA visual: modelo '.$id.' em TES_TES.'.PHP_EOL;
} finally {
    if ($fixture && !$keep) { http_cleanup($conn,$fixture); if (is_file($manifest)) unlink($manifest); }
    curl_close($curl); $conn->close();
}
