<?php
/** Local read-only HTTP integration checks. Credentials/cookies never enter output. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$base = 'http://localhost:8066/ImproovWeb/';
$checks = 0; $failures = []; $details = [];
$check = static function ($ok, $label) use (&$checks, &$failures) { $checks++; if (!$ok) $failures[] = $label; };
$curl = curl_init();
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_COOKIEFILE=>'', CURLOPT_TIMEOUT=>30, CURLOPT_FOLLOWLOCATION=>false]);
$request = static function ($route, $method='GET', $body=null) use ($curl,$base) {
    curl_setopt_array($curl, [CURLOPT_URL=>$base.$route, CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_POSTFIELDS=>$body, CURLOPT_HTTPHEADER=>['Accept: application/json']]);
    $content = curl_exec($curl);
    if ($content === false) throw new RuntimeException('Local HTTP unavailable: '.curl_error($curl));
    return ['status'=>curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'mime'=>curl_getinfo($curl,CURLINFO_CONTENT_TYPE),'body'=>$content,'json'=>json_decode(ltrim($content,"\xEF\xBB\xBF"),true)];
};
$r=$request(''); $check($r['status']===200, 'Official HTTP root');
$r=$request('MapaImproov/api.php'); $check($r['status']===401, 'Anonymous API');
foreach(['assets/icons/flow-favicon.png','assets/og/flow-default.png'] as $asset) {
    $r=$request($asset); $check($r['status']===200&&str_starts_with($r['mime'],'image/png'),'Public asset MIME '.$asset);
}
$r=$request('tools/import_google_earth_locations.php');
$check(in_array($r['status'],[403,404],true),'Spatial importer cannot run over HTTP');
// HTML document check must not send Accept JSON.
curl_setopt($curl,CURLOPT_HTTPHEADER,[]); curl_setopt($curl,CURLOPT_URL,$base.'MapaImproov/'); curl_exec($curl);
$check(curl_getinfo($curl,CURLINFO_RESPONSE_CODE)===302,'Anonymous document');
$instructions=file_get_contents(__DIR__.'/../AGENTS.md');
preg_match('/Usuario:\s*(\S+)/u',$instructions,$login);
preg_match('/Senha:\s*(\S+)/u',$instructions,$password);
if (empty($login[1]) || empty($password[1])) throw new RuntimeException('Local test credentials unavailable.');
$r=$request('login.php','POST',http_build_query(['login'=>$login[1],'senha'=>$password[1]]));
$check(($r['json']['status']??'')==='success','Authenticated login');
unset($instructions,$login,$password);
$page=$request('MapaImproov/');
$check($page['status']===200&&substr_count($page['body'],'<title>')===1,'Single initial page title');
$check(substr_count($page['body'],'property="og:title"')===1&&substr_count($page['body'],'name="twitter:card"')===1,'Initial OG and Twitter metadata');
$check((bool)preg_match('~property="og:url" content="http://localhost:8066/ImproovWeb/MapaImproov/"~',$page['body']),'Institutional canonical');
$r=$request('MapaImproov/api.php');
$check($r['status']===200 && ($r['json']['sucesso']??false),'Authenticated API');
$data=$r['json'];
$check(($data['type']??'')==='FeatureCollection','GeoJSON contract');
$check(count($data['obras'])>=count($data['features']),'Missing-location projects remain in counts');
$check(!preg_match('~uploads/|\.glb|"arquivo"|"token"~i',$r['body']),'No private storage paths or credentials');
foreach($data['features'] as $f) {
    [$lng,$lat]=$f['geometry']['coordinates'];
    $check(is_numeric($lng)&&is_numeric($lat)&&abs($lng)<=180&&abs($lat)<=90,'Feature valid '.$f['id']);
}
$details['counts']=['accessible'=>count($data['obras']),'located'=>count($data['features']),'glb'=>count(array_filter($data['obras'],fn($p)=>$p['has_glb']))];
foreach([102,133] as $id) {
    $r=$request('MapaImproov/api.php?obra_id='.$id);
    if ($r['status']===403) { $details['projects'][$id]='Unavailable to test user'; continue; }
    $check($r['status']===200,'Project '.$id.' available');
    $p=$r['json']['obras'][0];
    $check($p['obra_id']===$id && $p['coordinates']!==null,'Project '.$id.' has real location');
    $check($p['has_glb'] && $p['viewer_url']!==null,'Project '.$id.' has model');
    $viewer=preg_replace('~^\.\./~','',$p['viewer_url']);
    $v=$request($viewer);
    $check($v['status']===200 && str_contains($v['body'],'mi-viewer-return'),'Viewer and return link '.$id);
    parse_str(parse_url($viewer,PHP_URL_QUERY),$query);
    $model=$request('MapaPavimento/api.php?'.http_build_query(['action'=>'modelo_3d_contexto','obra_id'=>$id,'modelo_id'=>$query['modelo_id']]));
    $check($model['status']===200&&($model['json']['modelo']['obra_id']??null)===$id,'Existing model API '.$id);
    $details['projects'][$id]=['located'=>true,'glb'=>true,'model_id'=>(int)$query['modelo_id']];
}
foreach(['obra_id[]=102','obra_id=-1','obra_id=0','obra_id=1%20OR%201=1','obra_id=999999999999999999999','next=https://example.com'] as $query) {
    $check($request('MapaImproov/api.php?'.$query)['status']===400,'Invalid input '.$query);
}
$check($request('MapaImproov/api.php?obra_id=2147483647')['status']===403,'Unknown project denied');
$check($request('MapaImproov/api.php','POST','obra_id=102')['status']===405,'Read-only API');
$check($request('MapaPavimento/predio-3d/visualizador-glb-navegacao.php?obra_id=102&modelo_id=8')['status']===403,'Cross-project model denied before rendering');
$check($request('MapaPavimento/predio-3d/visualizador-glb-navegacao.php?obra_id[]=102')['status']===400,'Viewer rejects array project');
// Reduce only this isolated test session; no DB permission changes.
$cookies=curl_getinfo($curl,CURLINFO_COOKIELIST);
$sessionId=''; foreach($cookies as $cookie) { $parts=explode("\t",$cookie); if(($parts[5]??'')==='PHPSESSID') $sessionId=$parts[6]; }
if ($sessionId && is_file(rtrim(session_save_path(),'/\\').'/sess_'.$sessionId)) {
    session_id($sessionId); session_start();
    $_SESSION['nivel_acesso']=0; $_SESSION['cargo_colaborador']=''; $_SESSION['idusuario']=0; $_SESSION['idcolaborador']=0;
    session_write_close();
    $restricted=$request('MapaImproov/api.php');
    $check($restricted['status']===200,'Restricted role API');
    $returned=array_column($restricted['json']['obras'],'obra_id');
    require_once __DIR__.'/../config/database.php'; $conn=improov_db_connect();
    $expected=array_map('intval',array_column($conn->query("SELECT DISTINCT o.idobra FROM obra o JOIN obra_pacote p ON p.obra_id=o.idobra WHERE o.status_obra=0 AND p.status='ATIVO' AND p.tipo='STILL'")->fetch_all(MYSQLI_ASSOC),'idobra'));
    $check(!array_diff($returned,$expected),'Restricted role sees only active STILL projects');
    $denied=$conn->query("SELECT idobra FROM obra WHERE status_obra<>0 ORDER BY idobra LIMIT 1")->fetch_assoc();
    if($denied) $check($request('MapaImproov/api.php?obra_id='.$denied['idobra'])['status']===403,'Restricted inaccessible project denied');
    $conn->close();
    session_id($sessionId); session_start(); $_SESSION['login_ts']=time()-86400; $_SESSION['last_activity_ts']=time()-86400; session_write_close();
    $check($request('MapaImproov/api.php')['status']===401,'Expired session API');
    $details['isolated_role_and_expiration']=true;
} else $details['isolated_role_and_expiration']='Session path unavailable';
curl_close($curl);
$report=compact('checks','failures','details');
file_put_contents(__DIR__.'/../output/mapa-improov-http.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failures?1:0);
