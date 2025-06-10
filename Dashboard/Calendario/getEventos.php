<?php
include '../../conexao.php';

$obraId = $_GET['obraId'] ?? 0;
$eventos = [];

// Eventos do banco de eventos_obra
$sql = "SELECT 
  MIN(id) AS id, 
  descricao, 
  data_evento AS start, 
  tipo_evento 
FROM eventos_obra 
WHERE obra_id = ? 
GROUP BY descricao, data_evento, tipo_evento";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $obraId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $eventos[] = $row;
}
$stmt->close();

// Prazos das imagens com tipo_evento baseado no status
$sql2 = "SELECT 
        NULL AS id,
        s.nome_status AS descricao,
        i.prazo AS start,
        s.nome_status AS tipo_evento
    FROM imagens_cliente_obra i 
    JOIN status_imagem s ON i.status_id = s.idstatus 
    WHERE i.obra_id = ?
    GROUP BY s.nome_status, i.prazo
";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $obraId);
$stmt2->execute();
$result2 = $stmt2->get_result();

while ($row = $result2->fetch_assoc()) {
    $row['id'] = uniqid('img_'); // cria um id único
    $eventos[] = $row;
}
$stmt2->close();

// Eventos longos (ex: reuniões ou etapas com data início e fim)
// $sql3 = "SELECT 
//             id,
//             CONCAT(tipo_imagem, ' - ', etapa) AS descricao,
//             data_inicio AS start,
//             data_fim AS end,
//             etapa AS tipo_evento
//         FROM gantt_prazos 
//         WHERE obra_id = ?";

// $stmt3 = $conn->prepare($sql3);
// $stmt3->bind_param("i", $obraId);
// $stmt3->execute();
// $result3 = $stmt3->get_result();

// while ($row = $result3->fetch_assoc()) {
//     $eventos[] = $row;
// }
// $stmt3->close();


header('Content-Type: application/json');
echo json_encode($eventos);
