<?php
// Conectar ao banco de dados
$conn = new mysqli('192.168.0.202', 'admin', 'admin', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

if (isset($_GET['obra_id'])) {
    $obra_id = intval($_GET['obra_id']);

    // Consulta para buscar as imagens associadas à obra
    $sql = "SELECT idimagens_cliente_obra, imagem_nome 
            FROM imagens_cliente_obra 
            WHERE obra_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $obra_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<option value="' . htmlspecialchars($row['idimagens_cliente_obra']) . '">'
                . htmlspecialchars($row['imagem_nome']) . '</option>';
        }
    } else {
        echo '<option value="">Nenhuma imagem encontrada</option>';
    }

    $stmt->close();
}

$conn->close();
