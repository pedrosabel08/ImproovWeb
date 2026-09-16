<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../conexao.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');
if (!(int)$conn->query("SELECT GET_LOCK('portal_cliente_migration',10)")->fetch_row()[0]) { throw new RuntimeException('Migration em execução.'); }
try {
    foreach (['obra','usuario','contato_cliente','obra_contato','categorias','briefing_requisitos_arquivo','external_auth_session','external_otp_challenge'] as $table) {
        if (!$conn->query("SHOW TABLES LIKE '$table'")->num_rows) { throw new RuntimeException("Pré-requisito ausente: $table"); }
    }
    $sql = file_get_contents(__DIR__ . '/../sql/2026-09-16_portal_cliente.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') { continue; }
        if (str_starts_with($statement, 'ALTER TABLE external_otp_challenge') && $conn->query("SHOW COLUMNS FROM external_otp_challenge LIKE 'portal_obra_id'")->num_rows) { continue; }
        $conn->query($statement);
    }
    echo "Portal Parte 1: migration aplicada; nenhuma obra habilitada automaticamente.\n";
} finally { $conn->query("SELECT RELEASE_LOCK('portal_cliente_migration')"); }
