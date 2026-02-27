<?php
/**
 * run_migration_unif.php  — rodar UMA vez e deletar
 * Adiciona pdf_unificado_path a planta_compatibilizacao
 */
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    die('Não autenticado.');
}

$conn = conectarBanco();
$results = [];

$checks = [
    'pdf_unificado_path' =>
        "ALTER TABLE planta_compatibilizacao
         ADD COLUMN pdf_unificado_path VARCHAR(500) NULL
         AFTER arquivo_ids_json",
];

foreach ($checks as $col => $sql) {
    // Verificar se coluna já existe
    $r = $conn->query(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'planta_compatibilizacao'
           AND COLUMN_NAME  = '$col'"
    );
    $exists = (int) $r->fetch_assoc()['cnt'] > 0;

    if ($exists) {
        $results[] = "✔ $col — já existe, nada feito.";
    } elseif ($conn->query($sql)) {
        $results[] = "✔ $col — adicionada com sucesso.";
    } else {
        $results[] = "✘ $col — erro: " . $conn->error;
    }
}

$conn->close();
echo implode("\n", $results);
