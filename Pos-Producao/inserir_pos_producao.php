<?php
// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

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
    $status_pos = isset($_POST['status_pos']) ? 0 : 1; // Define status_pos com base no checkbox
    $status_id = $_POST['status_id'];

    // Inserir ou atualizar dados
    $sql = "INSERT INTO pos_producao (colaborador_id, cliente_id, obra_id, data_pos, imagem_id, caminho_pasta, numero_bg, refs, obs, status_pos, status_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                colaborador_id = VALUES(colaborador_id),
                cliente_id = VALUES(cliente_id),
                obra_id = VALUES(obra_id),
                data_pos = VALUES(data_pos),
                caminho_pasta = VALUES(caminho_pasta),
                numero_bg = VALUES(numero_bg),
                refs = VALUES(refs),
                obs = VALUES(obs),
                status_pos = VALUES(status_pos),
                status_id = VALUES(status_id)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiissssssii", $colaborador_id, $cliente_id, $obra_id, $data_pos, $imagem_id, $caminho_pasta, $numero_bg, $refs, $obs, $status_pos, $status_id);

    if ($stmt->execute()) {
        echo "Dados inseridos ou atualizados com sucesso!";
    } else {
        echo "Erro ao inserir ou atualizar dados: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
}
?>