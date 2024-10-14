<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!empty($data['ids']) && isset($data['valor'])) {
        include '../conexao.php';

        $ids = implode(',', array_map('intval', $data['ids']));
        $valor = floatval($data['valor']); // Pega o valor diretamente do input

        $sql = "UPDATE funcao_imagem SET valor = ? WHERE idfuncao_imagem IN ($ids)";

        // Prepare the statement
        $stmt = $conn->prepare($sql);

        // Check if prepare was successful
        if ($stmt === false) {
            die(json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]));
        }

        // A vinculação do valor
        $stmt->bind_param("d", $valor);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }

        $stmt->close(); // Fecha a declaração
    } else {
        echo json_encode(['success' => false, 'error' => 'IDs ou valor inválidos.']);
    }
}
