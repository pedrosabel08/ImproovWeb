<?php
include_once __DIR__ . '/../conexao.php';

$metrics = [];

// Total pendentes (status_pos = 1)
$sql = "SELECT COUNT(*) AS total FROM pos_producao WHERE status_pos = 1";
$r = $conn->query($sql);
$metrics['total_pendentes'] = ($r && $row = $r->fetch_assoc()) ? (int) $row['total'] : 0;

// Em atraso: status_pos = 1 AND prazo vencido
$sql = "SELECT COUNT(*) AS total
        FROM pos_producao p
        INNER JOIN imagens_cliente_obra i ON p.imagem_id = i.idimagens_cliente_obra
        WHERE p.status_pos = 1 AND i.prazo IS NOT NULL AND DATE(i.prazo) < CURDATE()";
$r = $conn->query($sql);
$metrics['em_atraso'] = ($r && $row = $r->fetch_assoc()) ? (int) $row['total'] : 0;

// Finalizados hoje: status_pos = 0 AND data_pos é hoje
$sql = "SELECT COUNT(*) AS total FROM pos_producao WHERE status_pos = 0 AND DATE(data_pos) = CURDATE()";
$r = $conn->query($sql);
$metrics['finalizados_hoje'] = ($r && $row = $r->fetch_assoc()) ? (int) $row['total'] : 0;

// Finalizados esta semana (ISO week)
$sql = "SELECT COUNT(*) AS total FROM pos_producao WHERE status_pos = 0 AND YEARWEEK(data_pos, 1) = YEARWEEK(NOW(), 1)";
$r = $conn->query($sql);
$metrics['finalizados_semana'] = ($r && $row = $r->fetch_assoc()) ? (int) $row['total'] : 0;

header('Content-Type: application/json');
echo json_encode($metrics);
