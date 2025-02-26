<?php
header('Content-Type: application/json');
include '../conexao.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['ids']) && is_array($data['ids'])) {
    $colaborador_id = intval($data['colaborador_id']);
    $data_pagamento = date('Y-m'); // Formato Ano-Mês para o agrupamento

    foreach ($data['ids'] as $item) {
        $funcao_id = intval($conn->real_escape_string($item['funcao_id']));

        // Inserir no histórico completo
        $sql = "INSERT INTO historico_pagamento (colaborador_id, funcao_id, data_pagamento) 
                VALUES ('$colaborador_id', '$funcao_id', CURDATE())";
        if (!$conn->query($sql)) {
            echo json_encode(['success' => false, 'error' => 'Erro ao inserir histórico: ' . $conn->error]);
            $conn->close();
            exit;
        }

        // Atualizar o agrupamento
        $sql_update = "INSERT INTO resumo_pagamento (data, colaborador_id, funcao_id, total_imagens) 
                       VALUES ('$data_pagamento', '$colaborador_id', '$funcao_id', 1) 
                       ON DUPLICATE KEY UPDATE total_imagens = total_imagens + 1";

        if (!$conn->query($sql_update)) {
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar resumo: ' . $conn->error]);
            $conn->close();
            exit;
        }
    }

    $conn->close();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Entrada inválida']);
}
