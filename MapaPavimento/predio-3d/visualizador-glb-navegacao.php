<?php
require_once __DIR__ . '/../../config/session_bootstrap.php';

if (($_SESSION['logado'] ?? false) !== true) {
    flow_auth_redirect_to_login();
    exit;
}


require_once __DIR__ . '/../../conexaoMain.php';

$conn = conectarBanco();
// Validate an explicit project before rendering the viewer; its APIs retain their own guards.
require_once __DIR__ . '/../../helpers/referencia_arquitetonica_helper.php';
try {
    if (isset($_GET['obra_id'])) {
        if (!is_string($_GET['obra_id']) || !preg_match('/^[1-9][0-9]{0,9}$/D', $_GET['obra_id'])) {
            throw new InvalidArgumentException('Obra inválida.');
        }
        mp_require_obra($conn, (int) $_GET['obra_id']);
        if (isset($_GET['modelo_id'])) {
            if (!is_string($_GET['modelo_id']) || !preg_match('/^[1-9][0-9]{0,9}$/D', $_GET['modelo_id'])) throw new InvalidArgumentException('Modelo inválido.');
            if (!mp_row($conn, 'SELECT id FROM mapa_modelos_3d WHERE id=? AND obra_id=?', 'ii', [(int) $_GET['modelo_id'], (int) $_GET['obra_id']])) {
                throw new RuntimeException('Modelo indisponível nesta obra.', 403);
            }
        }
    }
} catch (Throwable $error) {
    $conn->close();
    http_response_code($error instanceof InvalidArgumentException ? 400 : 403);
    exit('Obra ou modelo indisponível.');
}
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$conn->close();
ob_start();
include __DIR__ . '/../../sidebar.php';
$flowSidebar = ob_get_clean();
$html = file_get_contents(__DIR__ . '/visualizador-glb-navegacao.html');
$html = str_replace('"flow/environment-config": "./environment-config.mjs"',
    '"flow/environment-config": "./environment-config.mjs?v=' . substr(hash_file('sha256', __DIR__ . '/environment-config.mjs'), 0, 12) . '"', $html);
$html = preg_replace_callback('~(href|src)="(\.{1,2}/[^"?]+)(?:\?[^\"]*)?"~', static function ($match) {
    $file = __DIR__ . '/' . $match[2];
    return $match[1] . '="' . $match[2] . (is_file($file) ? '?v=' . filemtime($file) : '') . '"';
}, $html);
$html = flow_metadata_apply_to_html($html);
$html = str_replace('<head>', '<head>' . flow_auth_navigation_script(), $html);
$html = str_replace('</head>', '<link rel="stylesheet" href="../../css/styleSidebar.css"><link rel="stylesheet" href="./flow-spatial-shell.css?v=' . filemtime(__DIR__ . '/flow-spatial-shell.css') . '"></head>', $html);
$html = str_replace('</body>', '<script src="../../script/sidebar.js"></script><script src="../../script/controleSessao.js?authReturn=1"></script></body>', $html);
if (($_GET['origem'] ?? '') === 'mapa-improov') {
    $html = str_replace('</body>', '<a class="mi-viewer-return" href="../../MapaImproov/">← Mapa Improov</a><link rel="stylesheet" href="../../MapaImproov/viewer-return.css?v=' . filemtime(__DIR__ . '/../../MapaImproov/viewer-return.css') . '"></body>', $html);
}
$html = str_replace('<body class="spatial-workspace">', '<body class="spatial-workspace flow-spatial-page">' . $flowSidebar . '<div id="flowSpatialWorkspace"></div>', $html);
echo $html;
