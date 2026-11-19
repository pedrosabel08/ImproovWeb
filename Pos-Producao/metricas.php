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

// Uma função conta uma vez: primeiro início e primeiro envio posterior a ele.
// A janela é aplicada ao primeiro envio, não ao início da tarefa.
$sql = "SELECT COUNT(*) AS total, AVG(TIMESTAMPDIFF(SECOND, ciclos.inicio, ciclos.envio)) AS media_segundos
        FROM (
            SELECT inicios.funcao_imagem_id, inicios.inicio, MIN(envio.data) AS envio
            FROM (
                SELECT l.funcao_imagem_id, MIN(l.data) AS inicio
                FROM log_alteracoes l
                INNER JOIN funcao_imagem fi ON fi.idfuncao_imagem = l.funcao_imagem_id
                WHERE fi.funcao_id = 5 AND l.status_novo = 'Em andamento'
                GROUP BY l.funcao_imagem_id
            ) inicios
            INNER JOIN log_alteracoes envio ON envio.funcao_imagem_id = inicios.funcao_imagem_id
                AND envio.status_novo = 'Em aprovação' AND envio.data >= inicios.inicio
            GROUP BY inicios.funcao_imagem_id, inicios.inicio
        ) ciclos
        WHERE ciclos.envio >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND ciclos.envio <= NOW()";
$r = $conn->query($sql);
if ($r && $row = $r->fetch_assoc()) {
    $metrics['tempo_medio_pos_segundos'] = $row['media_segundos'] === null ? null : (float) $row['media_segundos'];
    $metrics['tempo_medio_pos_amostra'] = (int) $row['total'];
} else {
    $metrics['tempo_medio_pos_segundos'] = null;
    $metrics['tempo_medio_pos_amostra'] = null;
}

header('Content-Type: application/json');
echo json_encode($metrics);
