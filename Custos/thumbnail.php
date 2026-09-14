<?php
require_once __DIR__ . '/custos_auth.php';
custos_auth();
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/custos_helper.php';
custos_obra($conn, (int)($_GET['obra_id'] ?? 0));
$r = custos_query($conn, "SELECT h.imagem FROM historico_aprovacoes_imagens h JOIN funcao_imagem fi ON fi.idfuncao_imagem=h.funcao_imagem_id JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id WHERE h.id=? AND i.obra_id=? AND i.idimagens_cliente_obra=? AND h.media_tipo='imagem'", 'iii', [(int)($_GET['id'] ?? 0), (int)($_GET['obra_id'] ?? 0), (int)($_GET['imagem_id'] ?? 0)])[0] ?? null;
if (!$r || !$r['imagem']) {
    http_response_code(404);
    exit;
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($r['imagem']);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
    http_response_code(415);
    exit;
}
header('Content-Type: ' . $mime);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
echo $r['imagem'];
