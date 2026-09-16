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
    // Upgrade installations made during the initial Part 1 rollout.
    $fk = $conn->query("SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='portal_material_origem' AND REFERENCED_TABLE_NAME='briefing_requisitos_arquivo'")->fetch_assoc();
    if ($fk && $fk['DELETE_RULE'] !== 'CASCADE') {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $fk['CONSTRAINT_NAME'])) { throw new RuntimeException('Unexpected FK name'); }
        $conn->query('ALTER TABLE portal_material_origem DROP FOREIGN KEY `' . $fk['CONSTRAINT_NAME'] . '`, ADD CONSTRAINT fk_portal_origem_requisito FOREIGN KEY(requisito_id) REFERENCES briefing_requisitos_arquivo(id) ON DELETE CASCADE');
    }
    $hasAdminFk = $conn->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='portal_projeto' AND CONSTRAINT_NAME='fk_portal_admin_vinculo'")->num_rows;
    if (!$hasAdminFk) {
        $conn->query('ALTER TABLE portal_projeto ADD CONSTRAINT fk_portal_admin_vinculo FOREIGN KEY(obra_id,administrador_contato_id) REFERENCES obra_contato(obra_id,contato_cliente_id)');
    }
    echo "Portal Parte 1: migration aplicada; nenhuma obra habilitada automaticamente.\n";
} finally { $conn->query("SELECT RELEASE_LOCK('portal_cliente_migration')"); }
