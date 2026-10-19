<?php
// Completa a publicação de um upload local anterior à ativação do armazenamento SFTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/referencia_arquitetonica_helper.php';
require_once __DIR__ . '/../helpers/mapa_modelo_3d_helper.php';
$options = getopt('', ['modelo:', 'verify']);
$id = (int)($options['modelo'] ?? 0);
if (!$id) throw new InvalidArgumentException('Informe --modelo=ID.');
$conn = conectarBanco();
$model = mp_row($conn, 'SELECT m.arquivo,v.sha256,v.tamanho_bytes FROM mapa_modelos_3d m JOIN mapa_modelo_3d_versoes v ON v.modelo_id=m.id AND v.arquivo=m.arquivo WHERE m.id=?', 'i', [$id]);
if (!$model || !str_starts_with($model['arquivo'], 'uploads/model-3d/')) throw new RuntimeException('Upload não encontrado.');
$local = mp3d_file($model['arquivo']);
if (!hash_equals($model['sha256'], hash_file('sha256', $local)) || filesize($local) !== (int)$model['tamanho_bytes']) throw new RuntimeException('O GLB local diverge do cadastro.');
if (isset($options['verify'])) {
    [$sftp, $root] = mp3d_sftp();
    $hash = hash_init('sha256'); $size = 0;
    $ok = $sftp->get($root . '/' . $model['arquivo'], function ($chunk) use ($hash, &$size) { hash_update($hash, $chunk); $size += strlen($chunk); });
    if ($ok === false || $size !== (int)$model['tamanho_bytes'] || !hash_equals($model['sha256'], hash_final($hash))) throw new RuntimeException('Verificação do arquivo no VPS falhou.');
    $conn->close();
    echo "Modelo $id no VPS: tamanho e SHA-256 conferidos.\n";
    exit;
}
if (!mp3d_publish($model['arquivo'], $local)) throw new RuntimeException('Este script exige armazenamento SFTP configurado.');
$conn->close();
echo "Modelo $id publicado no VPS, com nome e cadastro preservados.\n";
