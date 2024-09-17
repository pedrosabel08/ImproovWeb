<?php
// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// Verifica se o ID da obra e o ID da imagem foram passados
$obra_id = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : null;
$id_imagem = isset($_GET['idimagem']) && $_GET['idimagem'] !== '' ? intval($_GET['idimagem']) : null;

echo "obra_id: " . $obra_id . " idimagem: " . ($id_imagem ?? 'Nenhum') . "<br>";

if ($id_imagem) {
    // Se o idimagem foi fornecido, busca uma imagem específica
    echo "Buscando imagem específica com idimagem: " . $id_imagem . "<br>";
    $sql = "SELECT idimagens_cliente_obra, imagem_nome 
            FROM imagens_cliente_obra 
            WHERE idimagens_cliente_obra = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Erro na preparação da consulta: " . $conn->error);
    }
    $stmt->bind_param('i', $id_imagem);
} elseif ($obra_id) {
    // Se apenas o obra_id foi fornecido, busca todas as imagens da obra
    echo "Buscando imagens da obra com obra_id: " . $obra_id . "<br>";

    $sql = "SELECT idimagens_cliente_obra, imagem_nome 
            FROM imagens_cliente_obra 
            WHERE obra_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Erro na preparação da consulta: " . $conn->error);
    }
    $stmt->bind_param('i', $obra_id);
} else {
    echo '<option value="">Nenhum parâmetro fornecido</option>';
    exit;
}

// Execute a consulta
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

// Feche o statement e a conexão
$stmt->close();
$conn->close();
