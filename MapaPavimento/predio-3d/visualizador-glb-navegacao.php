<?php

require_once __DIR__ . '/../../config/session_bootstrap.php';
require_once __DIR__ . '/../../conexaoMain.php';
if (($_SESSION['logado'] ?? false) !== true) {
    header('Location: ../../index.html');
    exit;
}
$conn = conectarBanco();
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$conn->close();
ob_start();
include __DIR__ . '/../../sidebar.php';
$flowSidebar = ob_get_clean();
$html = file_get_contents(__DIR__ . '/visualizador-glb-navegacao.html');
$html = preg_replace_callback('~(href|src)="(\.{1,2}/[^"?]+)(?:\?[^\"]*)?"~', static function ($match) {
    $file = __DIR__ . '/' . $match[2];
    return $match[1] . '="' . $match[2] . (is_file($file) ? '?v=' . filemtime($file) : '') . '"';
}, $html);
$html = str_replace('</head>', '<link rel="stylesheet" href="../../css/styleSidebar.css"><link rel="stylesheet" href="./flow-spatial-shell.css?v=' . filemtime(__DIR__ . '/flow-spatial-shell.css') . '"></head>', $html);
$html = str_replace('</body>', '<script src="../../script/sidebar.js"></script><script src="../../script/controleSessao.js"></script></body>', $html);
$html = str_replace('<body class="spatial-workspace">', '<body class="spatial-workspace flow-spatial-page">' . $flowSidebar . '<div id="flowSpatialWorkspace"></div>', $html);
echo $html;
