<?php
include 'conexao.php';

if (isset($_POST['obra_id'], $_POST['prazo'], $_POST['tipo_entrega'], $_POST['assuntoEntrega'], $_POST['usuarios_ids'])) {
    $obra_id = intval($_POST['obra_id']);
    $prazo = $_POST['prazo'];
    $tipo_entrega = $_POST['tipo_entrega'];
    $assunto_entrega = $_POST['assuntoEntrega'];
    $usuariosIds = $_POST['usuarios_ids'];

    // Obter o nome da obra
    $stmt = $conn->prepare("SELECT nome_obra FROM obra WHERE idobra = ?");
    $stmt->bind_param("i", $obra_id);
    $stmt->execute();
    $resultadoNomeObra = $stmt->get_result();

    if ($resultadoNomeObra->num_rows > 0) {
        $linha = $resultadoNomeObra->fetch_assoc();
        $nomeObra = $linha['nome_obra'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Obra não encontrada']);
        exit();
    }

    // Inserir a notificação na tabela notificacoes
    $mensagem = "Prazo para $tipo_entrega na obra: $nomeObra, com prazo até $prazo.";
    $dataCriacao = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO notificacoes (mensagem, data_criacao, tipo_notificacao, obra_id) VALUES (?, ?, 'entrega', ?)");
    $stmt->bind_param("ssi", $mensagem, $dataCriacao, $obra_id);

    if ($stmt->execute()) {
        $notificacao_id = $conn->insert_id;

        // Inserir o prazo na tabela obra_prazo e associar a notificação
        $stmt = $conn->prepare("INSERT INTO obra_prazo (obra_id, prazo, tipo_entrega, assunto_entrega, notificacoes_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $obra_id, $prazo, $tipo_entrega, $assunto_entrega, $notificacao_id);

        if ($stmt->execute()) {
            $prazo_id = $conn->insert_id;

            // Associar a notificação a cada usuário selecionado
            $stmt = $conn->prepare("INSERT INTO notificacoes_usuarios (usuario_id, notificacao_id, lida) VALUES (?, ?, 0)");

            foreach ($usuariosIds as $usuarioId) {
                $stmt->bind_param("ii", $usuarioId, $notificacao_id);
                if (!$stmt->execute()) {
                    echo json_encode(['success' => false, 'message' => 'Erro ao inserir notificação para o usuário ' . $usuarioId]);
                    exit();
                }
            }

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao inserir o prazo: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao inserir notificação: ' . $conn->error]);
    }

    $stmt->close(); // Fecha o prepared statement
} else {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
}

$conn->close();
