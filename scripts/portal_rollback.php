<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../conexao.php';
// Deliberately refuses destructive rollback after adoption. Roll back code instead.
if ((int)$conn->query('SELECT COUNT(*) FROM portal_projeto')->fetch_row()[0] !== 0) {
    throw new RuntimeException('Existem projetos do Portal. Preserve os dados e reverta apenas a aplicação.');
}
$conn->query('ALTER TABLE external_otp_challenge DROP CHECK ck_external_otp_scope, DROP FOREIGN KEY fk_external_otp_portal, DROP INDEX idx_external_otp_portal, DROP INDEX idx_external_otp_global_ip, DROP COLUMN portal_obra_id, DROP COLUMN portal_convite_hash, MODIFY briefing_access_link_id BIGINT UNSIGNED NOT NULL');
foreach (['portal_evento','portal_material_origem','portal_material_formato','portal_material','portal_solicitacao','portal_participante_disciplina','portal_projeto_disciplina','portal_participante','portal_projeto','flow_disciplina'] as $table) { $conn->query("DROP TABLE `$table`"); }
echo "Estrutura sem uso revertida.\n";
