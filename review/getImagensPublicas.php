<?php
include '../conexao.php';

$obraId = $_GET['obraId'] ?? null;

if (!$obraId) {
    echo json_encode(['error' => 'ID da obra não informado.']);
    exit;
}


// Prepara a consulta
$sql = "SELECT ir.*, i.imagem_nome
    FROM imagens_cliente_obra i
    INNER JOIN (
        SELECT imagem_id, MAX(id) AS max_id
        FROM review_uploads
        GROUP BY imagem_id
    ) ultimos ON i.idimagens_cliente_obra = ultimos.imagem_id
    INNER JOIN review_uploads ir ON ir.id = ultimos.max_id
    WHERE i.obra_id = ? AND ir.status <> 'wait'
    ORDER BY i.imagem_nome ASC
";

// Prepara e executa a consulta com mysqli
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $obraId);
    $stmt->execute();
    $result = $stmt->get_result();

    $imagens = [];
    while ($row = $result->fetch_assoc()) {
        $imagens[] = $row;
    }

    $json = json_encode(['imagens' => $imagens]);

    if ($json === false) {
        echo json_encode([
            'error' => 'Erro ao gerar JSON.',
            'json_last_error' => json_last_error(),
            'mensagem' => json_last_error_msg(),
            'obraId' => $obraId,
            'total' => count($imagens),
        ]);
    } else {
        echo $json;
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'Erro na preparação da consulta.']);
}

$conn->close();
