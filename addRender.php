<?php

// Conectar ao banco de dados
include 'conexao.php';
session_start();

$responsavel_id = $_SESSION['idcolaborador'] ?? null;

// Lê os dados enviados via POST (JSON)
$data = json_decode(file_get_contents("php://input"), true);

// Verifica se os dados existem
if ($data && isset($data['imagem_id'])) {
    $imagem_id = $data['imagem_id'];
    $status_id = $data['status_id'];

    // Inicia uma transação para garantir que ambas as consultas sejam executadas com segurança
    $conn->begin_transaction();

    try {
        // Verifica se o ID existe na tabela imagens_cliente_obra
        $stmt_check_exists = $conn->prepare("SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?");
        $stmt_check_exists->bind_param("i", $imagem_id);
        $stmt_check_exists->execute();
        $stmt_check_exists->store_result();

        if ($stmt_check_exists->num_rows === 0) {
            echo json_encode([
                'status' => 'erro',
                'message' => 'ID não encontrado na tabela imagens_cliente_obra.'
            ]);
            $stmt_check_exists->close();
            exit;
        }

        // Verifica o status atual da imagem
        $stmt_check_status = $conn->prepare("SELECT status_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?");
        $stmt_check_status->bind_param("i", $imagem_id);
        $stmt_check_status->execute();
        $stmt_check_status->bind_result($current_status);
        $stmt_check_status->fetch();
        $stmt_check_status->close();

        // Primeira consulta: Insere na tabela render_alta
        $stmt1 = $conn->prepare("INSERT INTO render_alta (imagem_id, responsavel_id, status_id) VALUES (?, ?, ?)");
        $stmt1->bind_param("iii", $imagem_id, $responsavel_id, $status_id);
        $stmt1->execute();
        $stmt1->close();

        // Segunda consulta: Atualiza o status na tabela imagens_cliente_obra apenas se o status não for 13
        if ($current_status != 13) {
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

            $stmt_update_funcao = $conn->prepare("UPDATE funcao_imagem SET status = 'Não iniciado' WHERE imagem_id = ? AND funcao_id = 5");
            $stmt_update_funcao->bind_param("i", $imagem_id);
            $stmt_update_funcao->execute();

            // Verifica se a atualização foi bem-sucedida
            if ($stmt_update_funcao->affected_rows > 0) {
                echo json_encode([
                    'status' => 'sucesso',
                    'message' => "Status da funcao_id 5 atualizado para 'Não iniciado' para o imagem_id '$imagem_id'."
                ]);
            } else {
                echo json_encode([
                    'status' => 'aviso',
                    'message' => "Nenhuma linha foi atualizada para funcao_id 5 no imagem_id '$imagem_id'."
                ]);
            }

            $stmt_update_funcao->close();
        } else {
            // Confirma a transação mesmo sem atualizar o status
            $conn->commit();
            echo json_encode([
                'status' => 'sucesso',
                'message' => "Imagem ID '$imagem_id' inserida, mas o status já era 13."
            ]);
        }
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
