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
    // allow client-provided id so the browser can subscribe to progress before upload completes
    if (isset($_POST['client_id']) && preg_match('/^[A-Za-z0-9_\-\.]+$/', $_POST['client_id'])) {
        $id = $_POST['client_id'];
    } else {
        $id = uniqid('upl_', true);
    }
    $destFile = "$stagingDir/{$id}.{$ext}";

    if (!move_uploaded_file($tmpName, $destFile)) {
        $results[] = ['arquivo' => $originalName, 'status' => 'erro_move'];
        continue;
    }

    // coleta metadados do POST
    $meta = [];
    $meta['original_name'] = $originalName;
    $meta['staged_path'] = $destFile;
    $meta['id'] = $id;
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

    // Publish initial status to Redis (if Predis available)
    try {
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        }
        if (class_exists('\Predis\Client')) {
            $redis = new \Predis\Client();
            $ch = "upload_progress:{$id}";
            $payload = json_encode(['id' => $id, 'status' => 'queued', 'progress' => 0, 'message' => 'Enfileirado no servidor']);
            $redis->publish($ch, $payload);
            // also set a key so WS can read latest state
            $redis->setex("upload_status:{$id}", 3600, $payload);
        }
    } catch (Exception $e) {
        // ignore Redis failures here - enqueue still works
    }

    $results[] = ['arquivo' => $originalName, 'status' => 'enfileirado', 'id' => $id, 'meta' => $metaFile];
}

echo json_encode($results);
