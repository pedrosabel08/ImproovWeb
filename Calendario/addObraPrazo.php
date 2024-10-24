<?php
include 'conexao.php';

if (isset($_POST['obra_id']) && isset($_POST['prazo']) && isset($_POST['tipo_entrega']) && isset($_POST['assuntoEntrega']) && isset($_POST['usuarios_ids'])) {
    $obra_id = $_POST['obra_id'];
    $prazo = $_POST['prazo'];
    $tipo_entrega = $_POST['tipo_entrega'];
    $assunto_entrega = $_POST['assuntoEntrega'];
    $usuariosIds = $_POST['usuarios_ids'];

    // Obter o nome da obra
    $sqlNomeObra = "SELECT nome_obra FROM obra WHERE idobra = '$obra_id'";
    $resultadoNomeObra = $conn->query($sqlNomeObra);

    if ($resultadoNomeObra->num_rows > 0) {
        $linha = $resultadoNomeObra->fetch_assoc();
        $nomeObra = $linha['nome_obra'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Obra não encontrada']);
        exit();
    }

    $sqlPrazo = "INSERT INTO obra_prazo (obra_id, prazo, tipo_entrega, assunto_entrega) 
                  VALUES ('$obra_id', '$prazo', '$tipo_entrega', '$assunto_entrega')";

    if ($conn->query($sqlPrazo) === TRUE) {
        $prazo_id = $conn->insert_id;

        // Mensagem com o nome da obra
        $mensagem = "Prazo para $tipo_entrega na obra: $nomeObra, com prazo até $prazo.";
        $dataCriacao = date('Y-m-d H:i:s');

        $sqlNotificacao = "INSERT INTO notificacoes (mensagem, data_criacao, tipo_notificacao) VALUES ('$mensagem', '$dataCriacao', 'entrega')";
        if ($conn->query($sqlNotificacao) === TRUE) {
            $notificacao_id = $conn->insert_id;

            foreach ($usuariosIds as $usuarioId) {
                $sqlNotificacaoUsuario = "INSERT INTO notificacoes_usuarios (usuario_id, notificacao_id, lida) VALUES ('$usuarioId', '$notificacao_id', 0)";
                mysqli_query($conn, $sqlNotificacaoUsuario);
            }

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao inserir notificação: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao inserir o prazo: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
}

$conn->close();
