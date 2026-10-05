<?php

/** Raiz privada/configurada; nomes persistidos estritos; lock cooperativo e writes recuperáveis. */
final class FechamentoDocumentoFiles
{
    private string $root;
    public function __construct(string $root)
    {
        if (!is_dir($root) && !mkdir($root,0700,true) && !is_dir($root)) throw new RuntimeException('Storage documental indisponível.');
        $real=realpath($root);
        if (!$real || is_link($root)) throw new RuntimeException('Raiz documental inválida.');
        $this->root=$real;
        foreach (['staging','definitivo','locks'] as $dir) {
            $path=$real.DIRECTORY_SEPARATOR.$dir;
            if (!is_dir($path) && !mkdir($path,0700)) throw new RuntimeException('Storage documental indisponível.');
            if (is_link($path) || strcasecmp((string)realpath($path),$path)!==0) throw new RuntimeException('Diretório documental redirecionado.');
        }
    }
    public function path(string $relative): string
    {
        if (!preg_match('~^(staging|definitivo)/adendo_f[1-9][0-9]*_r[1-9][0-9]*_d[1-9][0-9]*_[a-f0-9]{32}\.pdf$~D',$relative)) throw new DomainException('Path documental inválido/traversal.');
        $path=$this->root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
        if (is_link($path) || strcasecmp((string)realpath(dirname($path)),dirname($path))!==0) throw new DomainException('Path documental fora da raiz permitida.');
        return $path;
    }
    public function comLock(string $uuid,callable $f)
    {
        if (!preg_match('/^[a-f0-9]{32}$/D',$uuid)) throw new DomainException('Identidade de arquivo inválida.');
        $path=$this->root.DIRECTORY_SEPARATOR.'locks'.DIRECTORY_SEPARATOR.$uuid.'.lock';
        if (is_link($path)) throw new DomainException('Lock documental inválido.');
        $file=fopen($path,'c+b');
        if (!$file) throw new RuntimeException('Lock documental indisponível.');
        try { if (!flock($file,LOCK_EX)) throw new RuntimeException('Lock documental indisponível.'); return $f(); }
        finally { flock($file,LOCK_UN); fclose($file); }
    }
    private function gravar(string $path,string $bytes): void
    {
        if (file_exists($path) || is_link($path)) throw new RuntimeException('Colisão de arquivo documental; não sobrescrever.');
        $f=fopen($path,'x+b'); if (!$f) throw new RuntimeException('Gravação documental falhou.');
        try {
            $pos=0; $len=strlen($bytes);
            while ($pos<$len) { $n=fwrite($f,substr($bytes,$pos)); if (!$n) throw new RuntimeException('Gravação parcial de documento.'); $pos+=$n; }
            if (!fflush($f) || (function_exists('fsync') && !fsync($f))) throw new RuntimeException('Sincronização do documento falhou.');
        } finally { fclose($f); }
    }
    private function promover(string $part,string $target): void
    {
        if (file_exists($target) || is_link($target)) throw new RuntimeException('Colisão de arquivo documental.');
        if (!rename($part,$target)) throw new RuntimeException('Publicação de arquivo documental falhou.');
    }
    public static function validarPdf(string $bytes,?string $hash=null,?int $size=null): array
    {
        if (strlen($bytes)<10 || !str_starts_with($bytes,'%PDF-') || !preg_match('/%%EOF\s*$/D',$bytes)) throw new DomainException('Arquivo PDF incompleto/inválido.');
        $actual=hash('sha256',$bytes); $len=strlen($bytes);
        if (($hash!==null && !hash_equals($hash,$actual)) || ($size!==null && $len!==$size)) throw new DomainException('Hash/tamanho do PDF inválido; confirmação bloqueada.');
        return ['pdf_hash'=>$actual,'tamanho_bytes'=>$len];
    }
    public function ler(array $doc,bool $definitivo=false): string
    {
        $path=$this->path($doc[$definitivo?'arquivo_definitivo':'arquivo_preview']);
        if (!is_file($path)) throw new DomainException('Arquivo documental ausente.');
        $bytes=file_get_contents($path); if ($bytes===false) throw new RuntimeException('Leitura documental falhou.');
        self::validarPdf($bytes,$doc['pdf_hash'],(int)$doc['tamanho_bytes']);
        return $bytes;
    }
    public function materializar(array $doc,callable $renderer): array
    {
        $path=$this->path($doc['arquivo_preview']); $part=$path.'.part'; $receipt=$path.'.receipt.json';
        $context=['documento_id'=>(int)$doc['id'],'revisao_id'=>(int)$doc['revisao_id'],'financial_snapshot_hash'=>$doc['financial_snapshot_hash'],'html_hash'=>hash('sha256',$doc['html_snapshot'])];
        if (is_link($receipt) || is_link($part) || is_link($receipt.'.part')) throw new DomainException('Artefato documental redirecionado.');
        if (is_file($receipt)) {
            $r=json_decode(file_get_contents($receipt),true,512,JSON_THROW_ON_ERROR);
            foreach ($context as $k=>$v) if (($r[$k]??null)!==$v) throw new DomainException('Recibo de materialização incompatível.');
            $source=is_file($path)?$path:$part;
            if (!is_file($source)) throw new DomainException('Artefato materializado ausente; não regenerar silenciosamente.');
            $meta=self::validarPdf(file_get_contents($source),$r['pdf_hash'],(int)$r['tamanho_bytes']);
            if ($source===$part) $this->promover($part,$path);
            return $meta;
        }
        if (file_exists($path)) throw new DomainException('Arquivo sem recibo de operação; requer reconciliação.');
        // Sob lock exclusivo: sobra parcial não selada não é PDF publicado.
        foreach ([$part,$receipt.'.part'] as $partial) if (is_file($partial) && !unlink($partial)) throw new RuntimeException('Limpeza de parcial falhou.');
        $bytes=$renderer(); $meta=self::validarPdf($bytes);
        $this->gravar($part,$bytes);
        $this->gravar($receipt.'.part',json_encode($context+$meta,JSON_THROW_ON_ERROR));
        $this->promover($receipt.'.part',$receipt);
        $this->promover($part,$path);
        return $meta;
    }
    public function publicar(array $doc,string $bytes): void
    {
        self::validarPdf($bytes,$doc['pdf_hash'],(int)$doc['tamanho_bytes']);
        $path=$this->path($doc['arquivo_definitivo']); $part=$path.'.part';
        if (is_link($part)) throw new DomainException('Artefato de publicação redirecionado.');
        if (is_file($path)) { self::validarPdf(file_get_contents($path),$doc['pdf_hash'],(int)$doc['tamanho_bytes']); return; }
        if (is_file($part)) {
            $candidate=file_get_contents($part);
            try { self::validarPdf($candidate,$doc['pdf_hash'],(int)$doc['tamanho_bytes']); }
            catch (DomainException $e) { if (!unlink($part)) throw new RuntimeException('Limpeza de publicação parcial falhou.'); $candidate=null; }
            if ($candidate!==null) { $this->promover($part,$path); return; }
        }
        $this->gravar($part,$bytes); $this->promover($part,$path);
    }
}
