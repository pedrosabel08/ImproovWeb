<?php

require_once __DIR__.'/pagamento_auth.php';
pagamento_require_gestor(false);
require __DIR__.'/../conexao.php';
$b = (int)($_GET['colaborador_id'] ?? 0);
$hasTipo = (int)$conn->query("SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='colaborador' AND COLUMN_NAME='tipo_remuneracao'")->fetch_assoc()['n'];
$s = $conn->prepare('SELECT '.($hasTipo ? 'tipo_remuneracao' : 'NULL tipo_remuneracao').' FROM colaborador WHERE idcolaborador=?');
$s->bind_param('i', $b);
$s->execute();
pagamento_json($s->get_result()->fetch_assoc() ?: ['tipo_remuneracao' => null]);
