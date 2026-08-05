<?php
// Setup legado mantido apenas para operação administrativa explícita.
// O schema é documental; este endpoint não deve ser chamado automaticamente.
require_once __DIR__ . '/../pagamento_auth.php';
pagamento_require_gestor(true);

require_once __DIR__ . '/../../conexao.php';

$sql = file_get_contents(__DIR__ . '/../schema.sql');
if ($sql === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Não foi possível ler o schema.']);
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
        error_log('Pagamento schema setup failed: ' . $conn->error);
        $err[] = 'Falha ao executar uma instrução do schema.';
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['created' => $ok, 'errors' => $err]);
