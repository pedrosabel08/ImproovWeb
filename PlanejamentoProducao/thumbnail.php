<?php

require_once __DIR__.'/../config/session_bootstrap.php';
require_once __DIR__.'/../conexaoMain.php';
require_once __DIR__.'/../helpers/planejamento_alteracao_helper.php';
if (empty($_SESSION['logado'])) {
    http_response_code(401);
    exit;
}
$id = filter_var($_GET['imagem_id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(404);
    exit;
}
$conn = conectarBanco();
$im = flow_pa_rows($conn, 'SELECT obra_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra=?', 'i', [$id])[0] ?? null;
if (!$im || !improov_usuario_pode_acessar_obra($conn, (int)$im['obra_id'])) {
    http_response_code(403);
    exit;
}
$r = flow_pa_rows($conn, "SELECT hi.imagem FROM historico_aprovacoes_imagens hi JOIN funcao_imagem fi ON fi.idfuncao_imagem=hi.funcao_imagem_id WHERE fi.imagem_id=? AND hi.imagem IS NOT NULL AND hi.imagem<>'' AND hi.imagem NOT LIKE '%imagem_%' ORDER BY hi.id DESC LIMIT 1", 'i', [$id])[0] ?? null;
if (!$r) {
    http_response_code(404);
    exit;
}
// O caminho vem exclusivamente do registro autorizado. O navegador recebe só o ID da imagem.
$_GET = ['path' => $r['imagem'],'w' => 96,'q' => 70];
header_register_callback(static function (): void {
    header('Cache-Control: private, max-age=300');
});
require __DIR__.'/../thumb.php';
