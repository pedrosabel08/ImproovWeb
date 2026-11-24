<?php
require_once __DIR__.'/referencia_arquitetonica_helper.php';
require_once __DIR__.'/mapa_espacial_helper.php';

function enq_state(array $s): string {
    if (($s['schema'] ?? null) !== 1 || !in_array($s['source'] ?? '', ['josh','seagull'], true)
        || !is_bool($s['vertical'] ?? null) || ($s['unit'] ?? '') !== 'm' || ($s['projection'] ?? '') !== 'perspective')
        throw new InvalidArgumentException('Convenção de câmera inválida.');
    foreach (['local','modelToScene'] as $key) {
        if (!is_array($s[$key] ?? null) || !array_is_list($s[$key]) || count($s[$key]) !== 16) throw new InvalidArgumentException('Matriz de câmera inválida.');
        foreach ($s[$key] as $v) if (!is_numeric($v) || !is_finite((float)$v) || abs($v)>1e10) throw new InvalidArgumentException('Coordenada inválida.');
    }
    foreach (['focal','aspect','height','roll','shiftY','fov'] as $key)
        if (!is_numeric($s[$key] ?? null) || !is_finite((float)$s[$key])) throw new InvalidArgumentException('Parâmetros ópticos inválidos.');
    if ($s['focal']<=0 || $s['focal']>1000 || $s['aspect']<0.05 || $s['aspect']>20 || $s['fov']<=0 || $s['fov']>=180)
        throw new InvalidArgumentException('Lente ou proporção fora da faixa.');
    $solar = $s['solar'] ?? [];
    if (!is_bool($solar['enabled'] ?? null) || !array_key_exists('config',$solar)) throw new InvalidArgumentException('Configuração solar inválida.');
    if ($solar['config'] !== null) mpe_values($solar['config']);
    if ($solar['enabled']) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d',$solar['date'] ?? '');
        if (!$date || $date->format('Y-m-d') !== $solar['date'] || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$solar['time'] ?? '')
            || !isset($solar['config']['timezone'],$solar['config']['latitude'],$solar['config']['longitude'],$solar['config']['norte_graus']))
            throw new InvalidArgumentException('Data, horário ou referencial solar inválido.');
    }
    $json = json_encode($s,JSON_THROW_ON_ERROR);
    if (strlen($json)>32768) throw new InvalidArgumentException('Composição muito grande.');
    return $json;
}
function enq_version(mysqli $c,int $obra,int $id): array {
    $v=mp_row($c,'SELECT v.id,v.modelo_id,v.numero,v.arquivo FROM mapa_modelo_3d_versoes v JOIN mapa_modelos_3d m ON m.id=v.modelo_id WHERE v.id=? AND m.obra_id=?','ii',[$id,$obra]);
    if (!$v) throw new RuntimeException('Versão indisponível nesta obra.',404);
    return $v;
}
function enq_list(mysqli $c,int $obra,int $modelo): array {
    require_once __DIR__.'/../conexaoMain.php';
    $dimensions = improov_main_table_has_column($c,'imagens_cliente_obra','largura')
        && improov_main_table_has_column($c,'imagens_cliente_obra','altura') ? ',largura,altura' : '';
    $images=mp_rows($c,"SELECT idimagens_cliente_obra id,imagem_nome nome,tipo_imagem tipo $dimensions FROM imagens_cliente_obra WHERE obra_id=? ORDER BY imagem_nome",'i',[$obra]);
    $versions=mp_rows($c,'SELECT v.id,v.numero FROM mapa_modelo_3d_versoes v JOIN mapa_modelos_3d m ON m.id=v.modelo_id WHERE m.id=? AND m.obra_id=? ORDER BY numero DESC','ii',[$modelo,$obra]);
    $rows=mp_rows($c,'SELECT e.*,v.numero versao_numero,col.nome_colaborador autor FROM imagem_enquadramentos_3d e JOIN mapa_modelo_3d_versoes v ON v.id=e.versao_id JOIN colaborador col ON col.idcolaborador=e.autor_id WHERE e.obra_id=? AND v.modelo_id=? ORDER BY e.id DESC','ii',[$obra,$modelo]);
    foreach ($rows as &$row) {
        $row['composicao']=json_decode($row['composicao'],true,32,JSON_THROW_ON_ERROR);
        unset($row['chave_criacao']);
    }
    return ['imagens'=>$images,'versoes'=>$versions,'enquadramentos'=>$rows];
}
function enq_write(mysqli $c,int $obra,int $actor,bool $admin,array $in): int {
    if (!$actor) throw new RuntimeException('Colaborador necessário.',403);
    $op=$in['operacao'] ?? '';
    $id=(int)($in['id'] ?? 0);
    $version=(int)($in['versao_id'] ?? 0);
    $image=empty($in['imagem_id']) ? null : (int)$in['imagem_id'];
    $c->begin_transaction();
    try {
        // Serializa preferências e aprovações da obra, inclusive no primeiro registro.
        mp_row($c,'SELECT idobra FROM obra WHERE idobra=? FOR UPDATE','i',[$obra]);
        $v=enq_version($c,$obra,$version);
        if ($image && !mp_row($c,'SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE idimagens_cliente_obra=? AND obra_id=?','ii',[$image,$obra]))
            throw new RuntimeException('Imagem indisponível nesta obra.',403);
        $row=$id ? mp_row($c,'SELECT * FROM imagem_enquadramentos_3d WHERE id=? AND obra_id=? FOR UPDATE','ii',[$id,$obra]) : null;
        $new=in_array($op,['criar','duplicar'],true);
        if ($new) {
            $uuid=$in['chave'] ?? '';
            if (!is_string($uuid) || !preg_match('/^[0-9a-f-]{36}$/D',$uuid)) throw new InvalidArgumentException('Identificador de operação inválido.');
            $prior=mp_row($c,'SELECT id,obra_id,versao_id FROM imagem_enquadramentos_3d WHERE autor_id=? AND chave_criacao=?','is',[$actor,$uuid]);
            if ($prior) {
                if ((int)$prior['obra_id']!==$obra || (int)$prior['versao_id']!==$version) throw new RuntimeException('Operação incompatível.',409);
                $c->commit(); return (int)$prior['id'];
            }
        }
        if ($op!=='criar') {
            if (!$row) throw new RuntimeException('Enquadramento indisponível.',404);
            if ((int)$row['revisao']!==(int)($in['revisao'] ?? 0)) throw new RuntimeException('Este enquadramento mudou. Atualize a lista.',409);
            $source=enq_version($c,$obra,$row['versao_id']);
            if ($source['modelo_id']!=$v['modelo_id'] || ($op!=='duplicar' && (int)$row['versao_id']!==$version)) throw new RuntimeException('Versão incompatível.',409);
        }
        $name=trim($in['nome'] ?? '');
        if (($new || $op==='editar') && (!$name || mb_strlen($name)>150)) throw new InvalidArgumentException('Informe um nome de até 150 caracteres.');
        if ($new) {
            if ($op==='duplicar' && (int)$row['versao_id']!==$version && empty($in['validado'])) throw new RuntimeException('Revise a composição no modelo de destino.',409);
            $state=enq_state($in['composicao'] ?? []);
            $origin=$op==='duplicar' ? $id : null;
            mp_query($c,'INSERT INTO imagem_enquadramentos_3d(obra_id,imagem_id,versao_id,origem_id,autor_id,nome,composicao,chave_criacao) VALUES(?,?,?,?,?,?,?,?)','iiiiisss',[$obra,$image,$version,$origin,$actor,$name,$state,$uuid])->close();
            $id=(int)$c->insert_id;
        } elseif (in_array($op,['preferir','aprovar'],true)) {
            if ($op==='aprovar' && !$admin) throw new RuntimeException('Aprovação restrita à administração.',403);
            if (!$row['imagem_id']) throw new InvalidArgumentException('Vincule uma imagem antes de escolher este enquadramento.');
            $flag=$op==='aprovar'?'aprovado':'preferido';
            mp_query($c,"UPDATE imagem_enquadramentos_3d SET $flag=NULL,revisao=revisao+1 WHERE imagem_id=? AND versao_id=? AND $flag=1 AND id<>?",'iii',[$row['imagem_id'],$version,$id])->close();
            mp_query($c,"UPDATE imagem_enquadramentos_3d SET $flag=1,{$flag}_por=?,{$flag}_em=NOW(),revisao=revisao+1 WHERE id=?",'ii',[$actor,$id])->close();
        } else {
            if (!$admin && (int)$row['autor_id']!==$actor) throw new RuntimeException('Edição restrita ao autor ou à administração.',403);
            if ($row['aprovado_em']!==null) throw new RuntimeException('Composição aprovada. Duplique para criar uma alternativa.',409);
            if ($op==='excluir') mp_query($c,'DELETE FROM imagem_enquadramentos_3d WHERE id=?','i',[$id])->close();
            elseif ($op==='editar') {
                $state=enq_state($in['composicao'] ?? []);
                $sameImage=$image===($row['imagem_id']!==null?(int)$row['imagem_id']:null);
                $sameState=json_decode($state,true)==json_decode($row['composicao'],true);
                mp_query($c,'UPDATE imagem_enquadramentos_3d SET nome=?,imagem_id=?,composicao=?,captura=IF(?,captura,NULL),preferido=IF(?,preferido,NULL),revisao=revisao+1 WHERE id=?','sisiii',[$name,$image,$state,$sameState,$sameImage,$id])->close();
            } else throw new InvalidArgumentException('Operação desconhecida.');
        }
        $c->commit();return $id;
    } catch(Throwable $e) {$c->rollback();throw $e;}
}
