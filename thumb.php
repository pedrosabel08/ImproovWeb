<?php
// thumb.php
// Gera e serve uma versão reduzida (thumbnail) de uma imagem do projeto.
// Parâmetros: path (caminho relativo ao diretório do projeto), w (largura desejada), q (qualidade JPEG 0-100)

ini_set('display_errors', 0);
// Config
$cacheDir = __DIR__ . '/cache/thumbs';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);

$path = isset($_GET['path']) ? $_GET['path'] : '';
$w = isset($_GET['w']) ? intval($_GET['w']) : 240;
$q = isset($_GET['q']) ? intval($_GET['q']) : 75;

if ($w <= 0) $w = 240;
if ($q <= 0 || $q > 100) $q = 75;

// Basic sanitization: remove null bytes and disallow traversal
$path = str_replace("\0", '', $path);
$path = trim($path);
// disallow .. sequences
if (strpos($path, '..') !== false) {
    http_response_code(400);
    exit('Invalid path');
}

// Resolve path: support remote URLs, multiple local candidate locations, and a public uploads fallback
$isRemote = preg_match('#^https?://#i', $path);

$originalPath = $path;
$file = null;

if ($isRemote) {
    $key = md5($path);
    $local = $cacheDir . '/remote_' . $key;
    if (!file_exists($local)) {
        // Use cURL streaming to avoid loading entire file into memory on shared hosts
        $fp = @fopen($local . '.tmp', 'wb');
        if ($fp === false) {
            http_response_code(500);
            exit('Unable to write cache');
        }
        $ch = curl_init($path);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_exec($ch);
        $err = curl_errno($ch);
        curl_close($ch);
        fclose($fp);
        if ($err) {
            @unlink($local . '.tmp');
            http_response_code(404);
            exit('Remote image not reachable');
        }
        @rename($local . '.tmp', $local);
    }
    $file = $local;
} else {
    // Normalize relative path and prepare candidate locations
    $path = ltrim($path, '/\\');
    $candidates = [];

    // if looks like absolute path (windows drive or starting slash), try as-is
    if (preg_match('#^[A-Za-z]:\\\\#', $path) || strpos($originalPath, '/') === 0) {
        $candidates[] = $originalPath;
    }

    // project-relative
    $candidates[] = __DIR__ . '/' . $path;

    // document root relative
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/' . $path;
    }

    // also try in an uploads subfolder (common place where only filename is provided)
    $candidates[] = __DIR__ . '/uploads/' . $path;
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/uploads/' . $path;
    }

    // try candidates
    foreach ($candidates as $cand) {
        if (file_exists($cand) && is_file($cand)) {
            $file = $cand;
            break;
        }
    }

    // If not found locally, try public URL fallback. If originalPath is just a filename, prefer /sistema/uploads/filename
    if (!$file) {
        if (basename($originalPath) === $originalPath) {
            $publicUrl = 'https://improov.com.br/sistema/uploads/' . ltrim($originalPath, '/\\');
        } else {
            $publicUrl = 'https://improov.com.br/' . ltrim($originalPath, '/\\');
        }
        // fetch via cURL streaming to tmp file
        $tmpLocal = $cacheDir . '/remote_fallback_' . md5($publicUrl);
        $fp = @fopen($tmpLocal . '.tmp', 'wb');
        if ($fp !== false) {
            $ch = curl_init($publicUrl);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_exec($ch);
            $err = curl_errno($ch);
            curl_close($ch);
            fclose($fp);
            if (!$err) {
                @rename($tmpLocal . '.tmp', $tmpLocal);
                $file = $tmpLocal;
            } else {
                @unlink($tmpLocal . '.tmp');
            }
        }
    }

    if (!$file) {
        http_response_code(404);
        exit('File not found (tried candidates)');
    }
}

// If GD missing, fail-fast and redirect to original (or return error)
if (!function_exists('imagecreatetruecolor')) {
    // fallback: attempt to serve original file directly
    if (is_file($file)) {
        $mime = mime_content_type($file) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=2592000');
        readfile($file);
        exit;
    }
    http_response_code(415);
    exit('GD extension required');
}

$info = @getimagesize($file);
if (!$info) {
    http_response_code(415);
    exit('Not an image');
}

$mime = $info['mime'];
$orig_w = $info[0];
$orig_h = $info[1];

$ratio = $orig_h > 0 ? ($orig_w / $orig_h) : 1;
$new_w = $w;
$new_h = max(1, intval($new_w / $ratio));

// Prevent upscaling: do not create thumbnails larger than the original image
if ($new_w > $orig_w) {
    $new_w = $orig_w;
    $new_h = max(1, intval($new_w / $ratio));
}

// cache filename
$cacheKey = md5($file . '|w=' . $new_w . '|q=' . $q);
$cacheFile = $cacheDir . '/' . $cacheKey . '.jpg';

// Compare mtimes to avoid regenerating thumbnails unnecessarily
$src_mtime = @filemtime($file) ?: time();
if (file_exists($cacheFile)) {
    $cache_mtime = @filemtime($cacheFile) ?: 0;
    // if cache is newer or equal to source, serve cached
    if ($cache_mtime >= $src_mtime) {
        // ETag and Last-Modified handling for conditional requests
        $etag = '"' . md5_file($cacheFile) . '"';
        $lastMod = gmdate('D, d M Y H:i:s', $cache_mtime) . ' GMT';
        if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) ||
            (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $cache_mtime)) {
            header('HTTP/1.1 304 Not Modified');
            exit;
        }
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=2592000'); // 30 days
        header('ETag: ' . $etag);
        header('Last-Modified: ' . $lastMod);
        readfile($cacheFile);
        exit;
    }
}

// create image resource from source
switch ($mime) {
    case 'image/jpeg':
        $src = @imagecreatefromjpeg($file);
        break;
    case 'image/png':
        $src = @imagecreatefrompng($file);
        break;
    case 'image/gif':
        $src = @imagecreatefromgif($file);
        break;
    case 'image/webp':
        if (function_exists('imagecreatefromwebp')) {
            $src = @imagecreatefromwebp($file);
        } else {
            $src = @imagecreatefromjpeg($file); // fallback (may fail)
        }
        break;
    default:
        $src = @imagecreatefromjpeg($file);
}

if (!$src) {
    http_response_code(500);
    exit('Cannot decode image');
}

$dst = imagecreatetruecolor($new_w, $new_h);
// preserve PNG transparency
if ($mime === 'image/png' || $mime === 'image/gif') {
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
    imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $transparent);
} else {
    // fill with white for JPG
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $white);
}

imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);

// write to cache file
imagejpeg($dst, $cacheFile, $q);

// output
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=2592000');
readfile($cacheFile);

imagedestroy($src);
imagedestroy($dst);
exit;
