<?php
require_once __DIR__ . '/../config/session_bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    exit('Não autenticado.');
}

if (!isset($_SESSION['nivel_acesso']) || !in_array((int) $_SESSION['nivel_acesso'], [1, 5], true)) {
    http_response_code(403);
    exit('Sem permissão.');
}

if (!class_exists('ZipArchive')) {
    http_response_code(501);
    exit('Compactação de arquivos não está disponível neste servidor.');
}

include __DIR__ . '/../conexao.php';
include __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/services/ContratoDateService.php';
require_once __DIR__ . '/services/ContratoManagementService.php';

$competencia = trim((string) ($_GET['competencia'] ?? ''));
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($_GET['colaborador_ids'] ?? ''))))));

$conn = conectarBanco();
try {
    $service = new ContratoManagementService($conn);
    $arquivos = $service->getArquivosParaDownload($ids, $competencia);
    if (!$arquivos) {
        http_response_code(404);
        exit('Nenhum arquivo disponível para baixar.');
    }

    $zipPath = tempnam(sys_get_temp_dir(), 'contratos_');
    if ($zipPath === false) {
        throw new RuntimeException('Não foi possível preparar o arquivo compactado.');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Não foi possível criar o arquivo compactado.');
    }
    foreach ($arquivos as $arquivo) {
        $zip->addFile($arquivo['arquivo_path'], basename((string) $arquivo['arquivo_nome']));
    }
    $zip->close();

    $nome = 'contratos_' . str_replace('-', '_', $competencia) . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Não foi possível preparar o arquivo compactado.';
} finally {
    $conn->close();
}
