<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

if (($argv[1] ?? '') === '--request') {
    session_save_path(sys_get_temp_dir());
    require __DIR__ . '/../config/session_bootstrap.php';
    $_SESSION = ['logado' => true, 'idcolaborador' => 34, 'nivel_acesso' => 1,
        'mapa_pavimento_csrf' => 'test-token'];
    $_GET = ['action' => $argv[2]];
    $_SERVER['REQUEST_METHOD'] = $argv[2] === 'modelo_3d_versoes' ? 'GET' : 'POST';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'test-token';
    ob_start();
    require __DIR__ . '/../MapaPavimento/api.php';
    $body = json_decode(ob_get_clean(), true);
    session_destroy();
    if (http_response_code() !== 403 || ($body['sucesso'] ?? true) !== false) {
        fwrite(STDERR, 'Esperado bloqueio HTTP 403 antes de acessar o banco.');
        exit(1);
    }
    exit(0);
}

$actions = ['subtipo_salvar', 'documento_upload', 'pagina_vincular', 'legado_associar',
    'referencia_configurar', 'ambiente_salvar', 'ambiente_excluir', 'modelo_3d_vincular',
    'espacial_salvar', 'modelo_3d_upload', 'modelo_3d_ativar_versao', 'modelo_3d_versoes'];
foreach ($actions as $action) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --request ' . escapeshellarg($action), $code);
    if ($code !== 0) throw new RuntimeException("Falha em $action");
}
echo "API: 12 operações administrativas bloqueadas com HTTP 403, sem gravações.\n";
