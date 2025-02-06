<?php
// Conectar ao banco de dados
include '../conexao.php';

// Lê os dados enviados via POST (JSON)
$data = json_decode(file_get_contents("php://input"), true);

// Verifica se os dados existem
if ($data && isset($data['imagem_id'])) {
    $imagem_id = $data['imagem_id'];
    $colaborador_id = $data['colaborador_id'];

    // Inicia uma transação para garantir que todas as operações ocorram corretamente
    $conn->begin_transaction();

    try {
        // 1. Primeiro, conta quantas alterações já existem para essa imagem ANTES de inserir a nova
        $stmt1 = $conn->prepare("SELECT COUNT(*) as total FROM alteracoes WHERE imagem_id = ?");
        $stmt1->bind_param("i", $imagem_id);
        $stmt1->execute();
        $result = $stmt1->get_result();
        $row = $result->fetch_assoc();
        $total_alteracoes = $row['total'];
        $stmt1->close();

        // 2. Definir o novo status com base na contagem ATUAL (antes da nova inserção)
        if ($total_alteracoes == 0) {
            $novo_status = 3;
            $numero_revisao = 1;
        } elseif ($total_alteracoes == 1) {
            $novo_status = 4;
            $numero_revisao = 2;
        } elseif ($total_alteracoes == 2) {
            $novo_status = 5;
            $numero_revisao = 3;
        } elseif ($total_alteracoes == 3) {
            $novo_status = 14;
            $numero_revisao = 4;
        } elseif ($total_alteracoes == 4) {
            $novo_status = 15;
            $numero_revisao = 5;
        } else {
            $novo_status = 15;
            $numero_revisao = 5;
        }

        // 3. Atualiza o status da imagem primeiro
        $stmt2 = $conn->prepare("UPDATE imagens_cliente_obra SET status_id = ? WHERE idimagens_cliente_obra = ?");
        if (!$stmt2) {
            die(json_encode([
                'status' => 'erro',
                'message' => 'Erro ao preparar a query UPDATE: ' . $conn->error
            ]));
        }
        $stmt2->bind_param("ii", $novo_status, $imagem_id);
        $stmt2->execute();
        $stmt2->close();

        // 4. Agora insere a nova alteração na tabela alteracoes
        $stmt3 = $conn->prepare("INSERT INTO alteracoes (imagem_id, colaborador_id, numero_revisao) VALUES (?, ?, ?)");
        $stmt3->bind_param("iii", $imagem_id, $colaborador_id, $numero_revisao);
        $stmt3->execute();
        $stmt3->close();

        // Confirma a transação
        $conn->commit();

        echo json_encode([
            'status' => 'sucesso',
            'message' => "Imagem ID '$imagem_id' status atualizado para '$novo_status' e alteração registrada."
        ]);
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
