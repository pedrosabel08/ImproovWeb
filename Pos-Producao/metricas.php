<?php

include_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/metricas_tempo.php';

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

// Para cada envio, o evento relevante anterior deve ser um início. Isso impede
// reutilizar o mesmo início em dois envios e escolhe o último início do ciclo.
// A revisão é o status histórico da imagem no momento do envio, nunca o atual.
$periodos = $conn->query("SELECT NOW() AS fim, DATE_SUB(NOW(), INTERVAL 30 DAY) AS inicio_atual, DATE_SUB(NOW(), INTERVAL 60 DAY) AS inicio_anterior")->fetch_assoc();
$sql = "SELECT s.nome_status AS revisao, envio.data AS envio,
               TIMESTAMPDIFF(SECOND, inicio.data, envio.data) AS segundos
        FROM log_alteracoes envio
        INNER JOIN funcao_imagem fi ON fi.idfuncao_imagem = envio.funcao_imagem_id AND fi.funcao_id = 5
        INNER JOIN log_alteracoes inicio ON inicio.idlog = (
            SELECT anterior.idlog FROM log_alteracoes anterior
            WHERE anterior.funcao_imagem_id = envio.funcao_imagem_id
              AND anterior.status_novo IN ('Em andamento', 'Em aprovação')
              AND NOT (anterior.status_anterior <=> anterior.status_novo)
              AND (anterior.data < envio.data OR (anterior.data = envio.data AND anterior.idlog < envio.idlog))
            ORDER BY anterior.data DESC, anterior.idlog DESC LIMIT 1
        ) AND inicio.status_novo = 'Em andamento'
        LEFT JOIN historico_imagens hi ON hi.idhistorico = (
            SELECT h.idhistorico FROM historico_imagens h
            WHERE h.imagem_id = fi.imagem_id AND h.data_movimento <= envio.data
            ORDER BY h.data_movimento DESC, h.idhistorico DESC LIMIT 1
        )
        LEFT JOIN status_imagem s ON s.idstatus = hi.status_id
        WHERE envio.status_novo = 'Em aprovação'
          AND NOT (envio.status_anterior <=> envio.status_novo)
          AND envio.data >= ? AND envio.data <= ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $periodos['inicio_anterior'], $periodos['fim']);
$stmt->execute();
$ciclos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$metrics['tempo_pos'] = pos_resumir_ciclos($ciclos, $periodos['inicio_anterior'], $periodos['inicio_atual'], $periodos['fim']);

header('Content-Type: application/json');
echo json_encode($metrics);
