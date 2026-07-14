<?php

require_once __DIR__ . '/../_common.php';
require_once realpath(__DIR__ . '/../../config/version_manager.php') ?: __DIR__ . '/../../config/version_manager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notificacaoJsonResponse(false, 'Método não permitido.', 405);
}

$version_type = trim((string)($_POST['version_type'] ?? 'patch'));
$version_manual = trim((string)($_POST['version_manual'] ?? ''));
$version_desc = trim((string)($_POST['version_desc'] ?? ''));

$root = realpath(__DIR__ . '/../../');
$explicit = ($version_type === 'manual') ? $version_manual : null;
$result = improov_bump_versions($root ?: (__DIR__ . '/../../'), $version_type, $explicit);

if (!$result['ok']) {
    notificacaoJsonResponse(false, $result['message'] ?? 'Falha ao atualizar a versão.', 422);
}

$version_final = (string)($result['app_version'] ?? '');
$desc_final = $version_desc === '' ? null : $version_desc;
$tipo_final = $version_type === 'manual' ? 'manual' : $version_type;
$criado_por = (int)($_SESSION['idusuario'] ?? 0);

$stmtV = $conn->prepare('INSERT INTO versionamentos (versao, descricao, tipo, criado_por) VALUES (?, ?, ?, ?)');
if ($stmtV) {
    $stmtV->bind_param('sssi', $version_final, $desc_final, $tipo_final, $criado_por);
    if (!$stmtV->execute()) {
        $stmtV->close();
        notificacaoJsonResponse(false, 'Versão atualizada, mas falhou ao registrar no banco.', 500);
    }
    $stmtV->close();
} else {
    notificacaoJsonResponse(false, 'Versão atualizada, mas falhou ao preparar registro no banco.', 500);
}

notificacaoJsonResponse(true, 'Versão registrada: ' . $version_final, 200, ['redirect' => 'index.php']);
