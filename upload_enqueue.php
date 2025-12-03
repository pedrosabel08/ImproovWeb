<?php

// Pequeno endpoint para enfileirar uploads para processamento em background.
// Ele salva o arquivo enviado em `uploads/staging` e grava um arquivo .json com metadados.

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: https://improov.com.br");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verifica arquivo
if (!isset($_FILES['arquivo_final']) || empty($_FILES['arquivo_final']['name'])) {
    echo json_encode(['error' => 'Nenhum arquivo enviado']);
    exit;
}

$stagingDir = __DIR__ . '/uploads/staging';
if (!is_dir($stagingDir)) {
    mkdir($stagingDir, 0777, true);
}

$arquivos = $_FILES['arquivo_final'];
$total = is_array($arquivos['name']) ? count($arquivos['name']) : 1;
$results = [];

for ($i = 0; $i < $total; $i++) {
    $originalName = is_array($arquivos['name']) ? $arquivos['name'][$i] : $arquivos['name'];
    $tmpName = is_array($arquivos['tmp_name']) ? $arquivos['tmp_name'][$i] : $arquivos['tmp_name'];
    $error = is_array($arquivos['error']) ? $arquivos['error'][$i] : $arquivos['error'];

    if ($error !== UPLOAD_ERR_OK) {
        $results[] = ['arquivo' => $originalName, 'status' => 'erro_upload', 'code' => $error];
        continue;
    }

    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $id = uniqid('upl_', true);
    $destFile = "$stagingDir/{$id}.{$ext}";

    if (!move_uploaded_file($tmpName, $destFile)) {
        $results[] = ['arquivo' => $originalName, 'status' => 'erro_move'];
        continue;
    }

    // coleta metadados do POST
    $meta = [];
    $meta['original_name'] = $originalName;
    $meta['staged_path'] = $destFile;
    $meta['uploaded_at'] = date('c');
    $meta['post'] = $_POST;
    // dataIdFuncoes pode estar serializado como JSON ou string
    if (isset($_POST['dataIdFuncoes'])) {
        $raw = $_POST['dataIdFuncoes'];
        $decoded = json_decode($raw, true);
        $meta['dataIdFuncoes'] = is_array($decoded) ? $decoded : [$raw];
    } else {
        $meta['dataIdFuncoes'] = [];
    }

    $metaFile = "$stagingDir/{$id}.json";
    file_put_contents($metaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $results[] = ['arquivo' => $originalName, 'status' => 'enfileirado', 'id' => $id, 'meta' => $metaFile];
}

echo json_encode($results);
