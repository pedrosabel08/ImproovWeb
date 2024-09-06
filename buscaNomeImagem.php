<?php
header('Content-Type: application/json');

$conn = new mysqli('192.168.0.202', 'admin', 'admin', 'improov');

if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $idImagemSelecionada = $_GET['ajid'];

    $sqlNomeImagem = "SELECT 
                        imagem_nome, 
                        ico.status_id AS status_id 
                      FROM imagens_cliente_obra ico 
                      WHERE ico.idimagens_cliente_obra = $idImagemSelecionada";

    $resultNomeImagem = $conn->query($sqlNomeImagem);

    if ($resultNomeImagem->num_rows > 0) {
        $nomeImagem = $resultNomeImagem->fetch_assoc();
        echo json_encode($nomeImagem);
    } else {
        echo json_encode(["nome_imagem" => null, "status_id" => null]);
    }
} else {
    echo json_encode(["error" => "Método de requisição inválido."]);
}

$conn->close();
