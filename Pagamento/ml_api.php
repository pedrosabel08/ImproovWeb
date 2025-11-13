<?php
// Simple ML API: train and predict tipo_imagem from imagem_nome
// Requirements: Python 3 with packages listed in ML/requirements-ml.txt

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$root = realpath(__DIR__ . '/..'); // ImproovWeb
$mlDir = $root . DIRECTORY_SEPARATOR . 'ML';
$modelPath = $mlDir . DIRECTORY_SEPARATOR . 'model.joblib';
// Use the Windows Python launcher by default
$python = 'py -3'; // altere para 'python' se preferir

function respond($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

if ($action === 'train') {
    require_once __DIR__ . '/../conexao.php';
    // export labeled data
    $csvPath = $mlDir . DIRECTORY_SEPARATOR . 'training.csv';
    $fh = fopen($csvPath, 'w');
    if (!$fh) respond(['status'=>'error','message'=>'Não foi possível criar CSV']);
    fputcsv($fh, ['imagem_nome','tipo_imagem']);

    $sql = "SELECT imagem_nome, tipo_imagem FROM imagens_cliente_obra WHERE tipo_imagem IS NOT NULL AND tipo_imagem <> ''";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            fputcsv($fh, [$row['imagem_nome'], $row['tipo_imagem']]);
        }
    }
    fclose($fh);

    $script = $mlDir . DIRECTORY_SEPARATOR . 'train_and_predict.py';
    $cmd = $python . ' ' . escapeshellarg($script) . ' train --csv ' . escapeshellarg($csvPath);
    $out = shell_exec($cmd);

    // Return raw python output for transparency
    if ($out) {
        $decoded = json_decode($out, true);
        if ($decoded) { respond($decoded); }
        respond(['status'=>'ok','raw'=>$out]);
    } else {
        respond(['status'=>'error','message'=>'Falha ao executar Python. Verifique instalação do Python e dependências.']);
    }
}

if ($action === 'predict') {
    $itemsJson = $_POST['items'] ?? file_get_contents('php://input');
    if (!$itemsJson) respond(['status'=>'error','message'=>'Forneça items (array JSON de nomes).']);

    $script = $mlDir . DIRECTORY_SEPARATOR . 'train_and_predict.py';
    $cmd = $python . ' ' . escapeshellarg($script) . ' predict --items ' . escapeshellarg($itemsJson);
    $out = shell_exec($cmd);
    if ($out) {
        $decoded = json_decode($out, true);
        if ($decoded) { respond($decoded); }
        respond(['status'=>'ok','raw'=>$out]);
    } else {
        respond(['status'=>'error','message'=>'Falha ao executar Python.']);
    }
}

respond(['status'=>'error','message'=>'Ação inválida. Use action=train ou action=predict']);
