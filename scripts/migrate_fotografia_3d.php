<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../conexaoMain.php';
require_once __DIR__.'/../helpers/referencia_arquitetonica_helper.php';
require_once __DIR__.'/../helpers/mapa_modelo_3d_helper.php';
$conn = conectarBanco();
$conn->query(file_get_contents(__DIR__.'/../sql/2026-11-24_imagem_enquadramentos_3d.sql'));
$legacy = mp_rows($conn, 'SELECT m.id,m.obra_id FROM mapa_modelos_3d m WHERE NOT EXISTS (SELECT 1 FROM mapa_modelo_3d_versoes v WHERE v.modelo_id=m.id AND v.arquivo=m.arquivo)');
foreach ($legacy as $row) {
    $conn->begin_transaction();
    try {
        $m = mp3d_model($conn, $row['obra_id'], $row['id'], true);
        $existing = mp_row($conn, 'SELECT id FROM mapa_modelo_3d_versoes WHERE modelo_id=? AND arquivo=?','is',[$m['id'],$m['arquivo']]);
        if (!$existing) {
            $number = mp_row($conn,'SELECT COALESCE(MAX(numero),0)+1 n FROM mapa_modelo_3d_versoes WHERE modelo_id=?','i',[$m['id']])['n'];
            $path = mp3d_file($m['arquivo']);
            mp_query($conn,'INSERT INTO mapa_modelo_3d_versoes(modelo_id,numero,arquivo,nome_original,tamanho_bytes,sha256,criado_em) SELECT id,?,arquivo,?,?,?,criado_em FROM mapa_modelos_3d WHERE id=?','isisi',[$number,basename($path),filesize($path),hash_file('sha256',$path),$m['id']])->close();
        }
        $conn->commit();
    } catch (Throwable $e) { $conn->rollback(); throw $e; }
}
echo "Estrutura e versões legadas verificadas.\n";
