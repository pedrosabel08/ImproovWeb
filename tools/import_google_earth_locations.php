<?php
/** Explicit CLI backfill. Default is a dry run; no API/GET ever writes spatial settings. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/referencia_arquitetonica_helper.php';
require_once __DIR__ . '/../helpers/mapa_permissoes_helper.php';
require_once __DIR__ . '/../helpers/google_earth_location_helper.php';

$conn = null;
try {
    $options = getopt('', ['user:', 'apply', 'confirmed-point:', 'obra:']);
    $login = $options['user'] ?? '';
    if (!is_string($login) || trim($login) === '') throw new InvalidArgumentException('Informe --user=login para atribuição e escopo das obras.');
    $apply = array_key_exists('apply', $options);
    $confirmed = [];
    $points = $options['confirmed-point'] ?? [];
    foreach (is_array($points) ? $points : [$points] as $point) {
        if (!preg_match('/^([1-9][0-9]*):([+-]?[0-9]+(?:\.[0-9]+)?):([+-]?[0-9]+(?:\.[0-9]+)?)$/D', $point, $match)) throw new InvalidArgumentException('Use --confirmed-point=obra:latitude:longitude somente após conferir o terreno no Google Earth.');
        $id = (int) $match[1];
        if (isset($confirmed[$id])) throw new InvalidArgumentException('Ponto confirmado duplicado.');
        $confirmed[$id] = ['latitude'=>mpe_coordinate($match[2],-90,90,'Latitude'), 'longitude'=>mpe_coordinate($match[3],-180,180,'Longitude')];
    }
    $conn = conectarBanco();
    $user = mp_row($conn, 'SELECT idusuario,idcolaborador,nivel_acesso FROM usuario WHERE login=? AND ativo=1', 's', [$login]);
    if (!$user || !mp_can_edit(['logado'=>true,'idcolaborador'=>$user['idcolaborador']])) throw new RuntimeException('O usuário deve ser um administrador espacial ativo do Flow.');
    // CLI-only attribution context. Does not create, authenticate or modify any web session.
    $_SESSION = ['logado'=>true,'idusuario'=>(int)$user['idusuario'],'idcolaborador'=>(int)$user['idcolaborador'],'nivel_acesso'=>(int)$user['nivel_acesso']];
    $allowed = array_map('intval', array_column(obterObras($conn, 'all'), 'idobra'));
    if (isset($options['obra'])) {
        if (!is_string($options['obra']) || !preg_match('/^[1-9][0-9]*$/D', $options['obra'])) throw new InvalidArgumentException('Obra inválida.');
        $id = (int) $options['obra'];
        if (!in_array($id, $allowed, true)) throw new RuntimeException('Obra não autorizada.');
        $allowed = [$id];
    }
    if (!$allowed) throw new RuntimeException('Nenhuma obra autorizada.');
    $marks = implode(',',array_fill(0,count($allowed),'?'));
    $rows = mp_rows($conn, "SELECT o.idobra,o.nomenclatura,o.google_earth,e.latitude,e.longitude FROM obra o
        LEFT JOIN mapa_obra_configuracoes_espaciais e ON e.obra_id=o.idobra
        WHERE o.idobra IN ($marks) AND o.google_earth IS NOT NULL AND TRIM(o.google_earth)<>'' ORDER BY o.idobra", str_repeat('i',count($allowed)),$allowed);
    $found = array_map('intval',array_column($rows,'idobra'));
    if (array_diff(array_keys($confirmed),$found)) throw new InvalidArgumentException('Ponto confirmado fora do escopo ou sem link Google Earth cadastrado.');
    $report = ['modo'=>$apply?'aplicacao':'simulacao','links_encontrados'=>count($rows),'adicionadas'=>0,'prontas'=>0,'preservadas'=>0,'pendentes'=>0,'obras'=>[]];
    foreach ($rows as $row) {
        $id=(int)$row['idobra'];
        $current=mpe_get($conn,$id);
        $decision=flow_earth_import_decision($current+['google_earth'=>$row['google_earth']],$confirmed[$id]??null);
        if ($decision['status']==='pronto') {
            $report['prontas']++;
            if ($apply) {
                // Optimistic spatial version prevents overwriting a concurrent spatial edit.
                // Read the link again before saving; never use an obsolete audited source.
                $fresh=mp_row($conn,'SELECT google_earth FROM obra WHERE idobra=?','i',[$id]);
                if (!$fresh || $fresh['google_earth']!==$row['google_earth']) throw new RuntimeException('Link alterado durante a importação; execute novamente. Obra '.$id);
                mpe_save($conn,$id,$decision['coordinates']+['norte_graus'=>$current['norte_graus'],'timezone'=>$current['timezone'],'versao'=>$current['versao']],(int)$user['idcolaborador']);
                $report['adicionadas']++;
                $decision['status']='adicionada';
            }
        } elseif ($decision['status']==='coordenadas_preservadas') $report['preservadas']++;
        else $report['pendentes']++;
        // No links, project payloads or coordinates in the console/report.
        $report['obras'][]=['obra_id'=>$id,'codigo'=>(string)$row['nomenclatura'],'status'=>$decision['status'],'origem'=>$decision['origem']??null];
    }
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'Importação interrompida: '.$error->getMessage().PHP_EOL);
    exit(1);
} finally { if ($conn instanceof mysqli) $conn->close(); }
