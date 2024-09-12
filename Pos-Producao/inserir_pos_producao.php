<?php
// Conectar ao banco de dados
$conn = new mysqli('192.168.0.202', 'admin', 'admin', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Verificar se os dados foram enviados via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $colaborador_id = $_POST['final_id'];
    $cliente_id = $_POST['cliente_id'];
    $obra_id = $_POST['obra_id'];
    $data_pos = date('Y-m-d'); // Data atual no formato 'YYYY-MM-DD'
    $imagem_id = $_POST['imagem_id'];
    $caminho_pasta = $_POST['caminho_pasta'];
    $numero_bg = $_POST['numero_bg'];
    $refs = $_POST['refs'];
    $obs = $_POST['obs'];
    $status_pos = 1; // Aqui você pode definir o status como '1' ou deixar que o usuário escolha
    $status_id = $_POST['status_id'];

    // Inserir os dados na tabela
    $stmt = $conn->prepare("INSERT INTO pos_producao (colaborador_id, cliente_id, obra_id, data_pos, imagem_id, caminho_pasta, numero_bg, refs, obs, status_pos, status_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiisissssii", $colaborador_id, $cliente_id, $obra_id, $data_pos, $imagem_id, $caminho_pasta, $numero_bg, $refs, $obs, $status_pos, $status_id);

    if ($stmt->execute()) {
        echo "Dados inseridos com sucesso!";
    } else {
        echo "Erro ao inserir dados: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
}