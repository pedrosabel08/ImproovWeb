<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!empty($data['ids'])) {
        include 'conexao.php';

        $ids = implode(',', array_map('intval', $data['ids']));

        $sql = "UPDATE funcao_imagem SET pagamento = 1 WHERE idfuncao_imagem IN ($ids)";

        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    } else {
        echo json_encode(['success' => false]);
    }
}
