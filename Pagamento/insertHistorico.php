<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/pagamento_auth.php';
pagamento_require_gestor(true);
require_once __DIR__ . '/../conexao.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['ids']) && is_array($data['ids'])) {
    $colaborador_id = intval($data['colaborador_id'] ?? 0);
    $mes = intval($data['mes'] ?? 0);
    $ano = intval($data['ano'] ?? 0);
    $data_pagamento = ($mes >= 1 && $mes <= 12 && $ano >= 2000)
        ? sprintf('%04d-%02d', $ano, $mes)
        : date('Y-m');

    if ($colaborador_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Colaborador inválido.']);
        exit;
    }

    foreach ($data['ids'] as $item) {
        $funcao_id = intval($conn->real_escape_string($item['funcao_id']));

        // Inserir no histórico completo
        $sql = "INSERT INTO historico_pagamento (colaborador_id, funcao_id, data_pagamento) 
                VALUES ('$colaborador_id', '$funcao_id', CURDATE())";
        if (!$conn->query($sql)) {
            error_log('Pagamento history insert failed: ' . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Não foi possível atualizar o histórico de pagamento.']);
            $conn->close();
            exit;
        }

        // Atualizar o agrupamento
        $sql_update = "INSERT INTO resumo_pagamento (data, colaborador_id, funcao_id, total_imagens) 
                       VALUES ('$data_pagamento', '$colaborador_id', '$funcao_id', 1) 
                       ON DUPLICATE KEY UPDATE total_imagens = total_imagens + 1";

        if (!$conn->query($sql_update)) {
            error_log('Pagamento summary update failed: ' . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Não foi possível atualizar o resumo de pagamento.']);
            $conn->close();
            exit;
        }
    }

    $conn->close();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Entrada inválida']);
}
