<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!empty($data['ids']) && isset($data['valor'])) {
        include '../conexao.php';

        $valor = floatval($data['valor']);

        foreach ($data['ids'] as $item) {
            $id = intval($item['id']);
            $origem = $item['origem'];
            $funcao_id = intval($item['funcao_id']);

            // Escolhe a tabela com base na origem
            if ($origem === 'funcao_imagem') {
                // Se funcao_id for 6, some ao valor atual
                if ($funcao_id === 6) {
                    $sql = "UPDATE funcao_imagem SET valor = valor + ? WHERE idfuncao_imagem = ?";
                } else {
                    $sql = "UPDATE funcao_imagem SET valor = ? WHERE idfuncao_imagem = ?";
                }
            } elseif ($origem === 'acompanhamento') {
                $sql = "UPDATE acompanhamento SET valor = ? WHERE idacompanhamento = ?";
            } elseif ($origem === 'animacao') {
                $sql = "UPDATE animacao SET valor = ? WHERE idanimacao = ?";
            } else {
                continue; // Ignorar caso origem desconhecida
            }

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("di", $valor, $id);

            if (!$stmt->execute()) {
                echo json_encode(['success' => false, 'error' => $stmt->error]);
                $stmt->close();
                exit;
            }

            $stmt->close();
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'IDs ou valor inválidos.']);
    }
}
