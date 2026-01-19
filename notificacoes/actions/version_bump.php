<?php

require_once __DIR__ . '/../_common.php';
require_once realpath(__DIR__ . '/../../config/version_manager.php') ?: __DIR__ . '/../../config/version_manager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

$version_type = trim((string)($_POST['version_type'] ?? 'patch'));
$version_manual = trim((string)($_POST['version_manual'] ?? ''));
$version_desc = trim((string)($_POST['version_desc'] ?? ''));

$root = realpath(__DIR__ . '/../../');
$explicit = ($version_type === 'manual') ? $version_manual : null;
$result = improov_bump_versions($root ?: (__DIR__ . '/../../'), $version_type, $explicit);

if (!$result['ok']) {
    header('Location: ../index.php?err=' . urlencode($result['message'] ?? 'Falha ao atualizar a versão.'));
    exit();
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
        header('Location: ../index.php?err=' . urlencode('Versão atualizada, mas falhou ao registrar no banco.'));
        exit();
    }
    $stmtV->close();
} else {
    header('Location: ../index.php?err=' . urlencode('Versão atualizada, mas falhou ao preparar registro no banco.'));
    exit();
}

header('Location: ../index.php?ok=' . urlencode('Versão registrada: ' . $version_final));
exit();
