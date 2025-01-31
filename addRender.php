<?php
// Conectar ao banco de dados
include 'conexao.php';

// Lê os dados enviados via POST (JSON)
$data = json_decode(file_get_contents("php://input"), true);

// Verifica se os dados existem
if ($data && isset($data['imagem_id'])) {
    $imagem_id = $data['imagem_id'];

    // Inicia uma transação para garantir que ambas as consultas sejam executadas com segurança
    $conn->begin_transaction();

    try {
        // Primeira consulta: Insere na tabela render_alta
        $stmt1 = $conn->prepare("INSERT INTO render_alta (imagem_id) VALUES (?)");
        $stmt1->bind_param("i", $imagem_id);
        $stmt1->execute();
        $stmt1->close();

        // Segunda consulta: Atualiza o status na tabela imagens_cliente_obra
        $stmt2 = $conn->prepare("UPDATE imagens_cliente_obra SET status_id = 13 WHERE idimagens_cliente_obra = ?");
        $stmt2->bind_param("i", $imagem_id);
        $stmt2->execute();

        // Verifica se a atualização afetou alguma linha
        if ($stmt2->affected_rows > 0) {
            $conn->commit(); // Confirma a transação
            echo json_encode([
                'status' => 'sucesso',
                'message' => "Imagem ID '$imagem_id' inserida e status atualizado com sucesso."
            ]);
        } else {
            throw new Exception("Nenhuma linha foi atualizada. Verifique se o ID existe.");
        }

        $stmt2->close();
    } catch (Exception $e) {
        // Se ocorrer um erro, desfaz a transação
        $conn->rollback();

        echo json_encode([
            'status' => 'erro',
            'message' => 'Erro ao executar as consultas: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'erro',
        'message' => 'Dados incompletos ou inválidos.'
    ]);
}

// Fecha a conexão
$conn->close();
