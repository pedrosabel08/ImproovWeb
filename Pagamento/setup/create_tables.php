<?php
// Simple setup script to create necessary tables if they don't exist
// Usage: open this file in browser when logged in and with DB access

// session_start();
// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
//     http_response_code(403);
//     echo 'Acesso negado';
//     exit();
// }

require_once __DIR__ . '/../../conexao.php';

$sql = file_get_contents(__DIR__ . '/../schema.sql');
if ($sql === false) {
    http_response_code(500);
    echo 'Erro ao ler schema.sql';
    exit();
}

// Split on semicolons that end statements
$statements = array_filter(array_map('trim', explode(';', $sql)));
$ok = 0; $err = [];
foreach ($statements as $stmt) {
    if ($stmt === '' || stripos($stmt, 'CREATE TABLE') === false) continue;
    if ($conn->query($stmt) === TRUE) {
        $ok++;
    } else {
        $err[] = $conn->error;
    }
}

header('Content-Type: application/json');
echo json_encode(['created' => $ok, 'errors' => $err]);
