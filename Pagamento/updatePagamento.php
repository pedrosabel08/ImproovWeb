<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!empty($data['ids'])) {
        include '../conexao.php';

        foreach ($data['ids'] as $item) {
            $id = intval($item['id']);
            $origem = $item['origem'];

            // Escolhe a tabela com base na origem
            if ($origem === 'funcao_imagem') {
                $sql = "UPDATE funcao_imagem SET pagamento = 1 WHERE idfuncao_imagem = ?";
            } elseif ($origem === 'acompanhamento') {
                $sql = "UPDATE acompanhamento SET pagamento = 1 WHERE idacompanhamento = ?";
            } elseif ($origem === 'animacao') {
                $sql = "UPDATE animacao SET pagamento = 1 WHERE idanimacao = ?";
            } else {
                continue; // Ignorar caso origem desconhecida
            }

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);

            if (!$stmt->execute()) {
                echo json_encode(['success' => false, 'error' => $stmt->error]);
                $stmt->close();
                exit;
            }

            $stmt->close();
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'IDs inválidos.']);
    }
}
