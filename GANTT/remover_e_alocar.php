<?php

include '../conexao.php';

// Recebendo os dados enviados via POST (JSON)
$data = json_decode(file_get_contents("php://input"));

$antigoId = $data->antigoId;
$novoId = $data->novoId;
$etapaId = $data->etapaId;

// Atualizar o colaborador na etapa (substituindo o colaborador antigo pelo novo)
$sql = "UPDATE etapa_colaborador SET colaborador_id = ? WHERE gantt_id = ? AND colaborador_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $novoId, $etapaId, $antigoId);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["sucesso" => true]);
    } else {
        echo json_encode(["sucesso" => false, "mensagem" => "Nenhuma linha foi atualizada."]);
    }
} else {
    echo json_encode(["sucesso" => false, "erro" => $stmt->error]);
}


// Fechando a conexão
$stmt->close();
$conn->close();
