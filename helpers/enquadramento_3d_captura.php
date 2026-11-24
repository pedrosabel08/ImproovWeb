<?php
require_once __DIR__.'/enquadramento_3d_helper.php';

function enq_media_dir(int $obra,int $id): string {
    $public=realpath(($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__,2));
    $root=improov_env('FLOW_PHOTO_STORAGE_ROOT',dirname($public).'/flow-private/enquadramentos-3d');
    if (!is_dir($root) && !mkdir($root,0750,true) && !is_dir($root)) throw new RuntimeException('Armazenamento privado indisponível.',503);
    $real=realpath($root);
    foreach ([$public,realpath(__DIR__.'/..')] as $dir) {
        $base=strtolower(str_replace('\\','/',$dir)).'/';
        if (str_starts_with(strtolower(str_replace('\\','/',$real)).'/',$base)) throw new RuntimeException('Configure o armazenamento fora da raiz pública.',503);
    }
    return $real."/$obra/$id";
}
function enq_capture(mysqli $c,int $obra,int $actor,bool $admin,array $input,?array $file): void {
    if (!$file || $file['error']!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size']>8388608)
        throw new InvalidArgumentException('Envie uma captura JPEG de até 8 MB.');
    $info=@getimagesize($file['tmp_name']);
    if (!$info || $info[2]!==IMAGETYPE_JPEG || max($info[0],$info[1])!==2048 || min($info[0],$info[1])<1)
        throw new InvalidArgumentException('Dimensões ou formato da captura inválidos.');
    $id=(int)($input['id']??0);
    $c->begin_transaction();$temporaries=[];
    try {
        $row=mp_row($c,'SELECT * FROM imagem_enquadramentos_3d WHERE id=? AND obra_id=? FOR UPDATE','ii',[$id,$obra]);
        if (!$row) throw new RuntimeException('Enquadramento indisponível.',404);
        if (!$admin && $actor!=(int)$row['autor_id']) throw new RuntimeException('Permissão insuficiente para a captura.',403);
        if ((int)$row['revisao']!==(int)$input['revisao']) throw new RuntimeException('Reabra a composição atual antes de capturar.',409);
        if ($row['aprovado_em'] && $row['captura']) throw new RuntimeException('A captura aprovada está preservada.',409);
        $s=json_decode($row['composicao'],true,32,JSON_THROW_ON_ERROR);
        if (abs($info[0]-$info[1]*$s['aspect'])>max(1,$s['aspect'])) throw new InvalidArgumentException('A captura não corresponde ao recorte registrado.');
        $dir=enq_media_dir($obra,$id);
        if (!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) throw new RuntimeException('Pasta de captura indisponível.',503);
        $image=imagecreatefromjpeg($file['tmp_name']);if (!$image) throw new InvalidArgumentException('Falha ao ler a captura.');
        $hash=hash_file('sha256',$file['tmp_name']);
        $scale=320/max($info[0],$info[1]);
        $thumb=imagescale($image,max(1,(int)round($info[0]*$scale)),max(1,(int)round($info[1]*$scale)),IMG_BICUBIC);
        foreach (['captura'=>$image,'miniatura'=>$thumb] as $kind=>$source) {
            $dest="$dir/$hash-$kind.jpg";
            if (is_file($dest)) continue;
            $temp=tempnam($dir,'part-');$temporaries[]=$temp;
            if (!$source || !imagejpeg($source,$temp,90) || !filesize($temp) || !rename($temp,$dest)) throw new RuntimeException('Falha ao gravar a captura. Tente novamente.',503);
        }
        mp_query($c,'UPDATE imagem_enquadramentos_3d SET captura=?,revisao=revisao+1 WHERE id=?','si',[$hash,$id])->close();
        $c->commit();
    } catch(Throwable $e){$c->rollback();throw $e;}
    finally {foreach($temporaries as $temp) if(is_file($temp)) unlink($temp);}
}
function enq_stream(mysqli $c,int $obra,array $in): never {
    $id=(int)($in['id']??0);
    $row=mp_row($c,'SELECT captura FROM imagem_enquadramentos_3d WHERE obra_id=? AND id=?','ii',[$obra,$id]);
    if (empty($row['captura'])) throw new RuntimeException('Captura indisponível.',404);
    $kind=($in['tipo']??'')==='miniatura'?'miniatura':'captura';
    $path=enq_media_dir($obra,$id).'/'.$row['captura']."-$kind.jpg";
    if(!is_file($path))throw new RuntimeException('Arquivo indisponível.',404);
    header('Content-Type: image/jpeg');header('Cache-Control: private, no-cache');
    header('Content-Length: '.filesize($path));readfile($path);exit;
}
