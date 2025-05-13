<?php
header('Content-Type: application/json');

include '../conexao.php';

// Recebe os dados JSON
$data = json_decode(file_get_contents('php://input'), true);

$etapa = $conn->real_escape_string($data['etapa']);
$data_inicio = $data['data_inicio']; // formato dd/mm/yyyy
$data_fim = $data['data_fim'];
$obra_id = intval($data['obra_id']);

// Converte para formato do MySQL (yyyy-mm-dd)
function formatarData($dataPt)
{
    $partes = explode('/', $dataPt); // dd/mm/yyyy
    return "{$partes[2]}-{$partes[1]}-{$partes[0]}";
}

$data_inicio_sql = formatarData($data_inicio);
$data_fim_sql = formatarData($data_fim);

// 1. Inserir etapa coringa
$insert = $conn->prepare("INSERT INTO gantt_prazos (tipo_imagem, etapa, data_inicio, data_fim, obra_id) VALUES (?, ?, ?, ?)");
$insert->bind_param("ssssi", $etapa, $etapa, $data_inicio_sql, $data_fim_sql, $obra_id);

if (!$insert->execute()) {
    echo json_encode(['success' => false, 'message' => 'Erro ao inserir etapa: ' . $conn->error]);
    exit;
}

// 2. Calcular diferença de dias
$data_inicio_dt = new DateTime($data_inicio_sql);
$data_fim_dt = new DateTime($data_fim_sql);
$diasCoringa = $data_inicio_dt->diff($data_fim_dt)->days + 1; // Inclui o último dia

// 3. Atualizar etapas seguintes
$update = $conn->prepare("UPDATE gantt_prazos 
    SET 
        data_inicio = DATE_ADD(data_inicio, INTERVAL ? DAY), 
        data_fim = DATE_ADD(data_fim, INTERVAL ? DAY)
    WHERE obra_id = ? AND data_inicio > ?
");

$update->bind_param("iiis", $diasCoringa, $diasCoringa, $obra_id, $data_fim_sql);

if (!$update->execute()) {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar etapas seguintes: ' . $conn->error]);
    exit;
}

echo json_encode(['success' => true]);
$conn->close();
