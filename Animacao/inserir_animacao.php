<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

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
    $data_anima = date('Y-m-d');
    $imagem_id = $_POST['imagem_id'];
    $duracao = $_POST['duracao'];
    $status_anima = $_POST['status_anima'];

    // Verificar se a animação já existe
    $stmt_check = $conn->prepare("SELECT idanimacao FROM animacao WHERE imagem_id = ? AND colaborador_id = ? AND cliente_id = ? AND obra_id = ?");
    $stmt_check->bind_param('iiii', $imagem_id, $colaborador_id, $cliente_id, $obra_id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        // Se a animação já existe, obter o ID
        $stmt_check->bind_result($animacao_id);
        $stmt_check->fetch();

        // Atualizar dados na tabela `animacao`
        $stmt_update_animacao = $conn->prepare("UPDATE animacao SET duracao = ?, status_anima = ?, data_anima = ? WHERE idanimacao = ?");
        $stmt_update_animacao->bind_param('issi', $duracao, $status_anima, $data_anima, $animacao_id);
        $stmt_update_animacao->execute();

        // Atualizar dados na tabela `cena`
        $status_cena = $_POST['status_cena'];
        $prazo_cena = $_POST['prazo_cena'];
        $stmt_update_cena = $conn->prepare("UPDATE cena SET status = ?, prazo = ? WHERE animacao_id = ?");
        $stmt_update_cena->bind_param('ssi', $status_cena, $prazo_cena, $animacao_id);
        $stmt_update_cena->execute();

        // Atualizar dados na tabela `render`
        $status_render = $_POST['status_render'];
        $prazo_render = $_POST['prazo_render'];
        $stmt_update_render = $conn->prepare("UPDATE render SET status = ?, prazo = ? WHERE animacao_id = ?");
        $stmt_update_render->bind_param('ssi', $status_render, $prazo_render, $animacao_id);
        $stmt_update_render->execute();

        // Atualizar dados na tabela `pos`
        $status_pos = $_POST['status_pos'];
        $prazo_pos = $_POST['prazo_pos'];
        $stmt_update_pos = $conn->prepare("UPDATE pos SET status = ?, prazo = ? WHERE animacao_id = ?");
        $stmt_update_pos->bind_param('ssi', $status_pos, $prazo_pos, $animacao_id);
        $stmt_update_pos->execute();

        echo "Dados atualizados com sucesso!";
    } else {
        // Se a animação não existe, inserir novos dados
        $stmt_insert = $conn->prepare("INSERT INTO animacao (imagem_id, colaborador_id, cliente_id, obra_id, duracao, status_anima, data_anima) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_insert->bind_param('siiiiss', $imagem_id, $colaborador_id, $cliente_id, $obra_id, $duracao, $status_anima, $data_anima);

        // Verificar se a inserção foi bem-sucedida
        if ($stmt_insert->execute()) {
            // Obter o ID da animação recém-inserida
            $animacao_id = $stmt_insert->insert_id;

            // Inserir dados na tabela `cena`
            $status_cena = $_POST['status_cena'];
            $prazo_cena = $_POST['prazo_cena'];
            $stmt_cena = $conn->prepare("INSERT INTO cena (animacao_id, status, prazo) VALUES (?, ?, ?)");
            $stmt_cena->bind_param('iss', $animacao_id, $status_cena, $prazo_cena);
            $stmt_cena->execute();

            // Inserir dados na tabela `render`
            $status_render = $_POST['status_render'];
            $prazo_render = $_POST['prazo_render'];
            $stmt_render = $conn->prepare("INSERT INTO render (animacao_id, status, prazo) VALUES (?, ?, ?)");
            $stmt_render->bind_param('iss', $animacao_id, $status_render, $prazo_render);
            $stmt_render->execute();

            // Inserir dados na tabela `pos`
            $status_pos = $_POST['status_pos'];
            $prazo_pos = $_POST['prazo_pos'];
            $stmt_pos = $conn->prepare("INSERT INTO pos (animacao_id, status, prazo) VALUES (?, ?, ?)");
            $stmt_pos->bind_param('iss', $animacao_id, $status_pos, $prazo_pos);
            $stmt_pos->execute();

            echo "Dados inseridos com sucesso!";
        } else {
            echo "Erro ao inserir dados: " . $conn->error;
        }
    }

    // Fechar declarações e conexão
    $stmt_check->close();
    if (isset($stmt_insert)) $stmt_insert->close();
    if (isset($stmt_update_animacao)) $stmt_update_animacao->close();
    if (isset($stmt_update_cena)) $stmt_update_cena->close();
    if (isset($stmt_update_render)) $stmt_update_render->close();
    if (isset($stmt_update_pos)) $stmt_update_pos->close();
    $conn->close();
}
