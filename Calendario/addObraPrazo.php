<?php
include 'conexao.php';

if (isset($_POST['obra_id']) && isset($_POST['prazo']) && isset($_POST['tipo_entrega']) && isset($_POST['assuntoEntrega']) && isset($_POST['colab_ids'])) {
    $obra_id = $_POST['obra_id'];
    $prazo = $_POST['prazo'];
    $tipo_entrega = $_POST['tipo_entrega'];
    $assunto_entrega = $_POST['assuntoEntrega'];
    $colabIds = $_POST['colab_ids'];

    $sqlPrazo = "INSERT INTO obra_prazo (obra_id, prazo, tipo_entrega, assunto_entrega) 
                  VALUES ('$obra_id', '$prazo', '$tipo_entrega', '$assunto_entrega')";

    if ($conn->query($sqlPrazo) === TRUE) {

        $prazo_id = $conn->insert_id;

        $mensagem = "Novo prazo para a obra: $tipo_entrega, com prazo até $prazo.";
        $dataCriacao = date('Y-m-d H:i:s');

        $sqlNotificacao = "INSERT INTO notificacoes (mensagem, data_criacao) VALUES ('$mensagem', '$dataCriacao')";
        if ($conn->query($sqlNotificacao) === TRUE) {

            $notificacao_id = $conn->insert_id;

            foreach ($colabIds as $colabId) {
                $sqlNotificacaoUsuario = "INSERT INTO notificacoes_usuarios (usuario_id, notificacao_id, lida) VALUES ('$colabId', '$notificacao_id', 0)";
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
