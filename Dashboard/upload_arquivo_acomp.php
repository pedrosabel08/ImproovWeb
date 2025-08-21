<?php
include '../conexao.php';

$obra_id = 74;
$tipo_imagem = $_POST['tipo_imagem'];
$descricao = $_POST['descricao'];
$status = $_POST['status'];

$arquivo = $_FILES['arquivo'];
$nome_arquivo = $arquivo['name'];
$caminho_destino = "uploads/" . $nome_arquivo;

if (move_uploaded_file($arquivo['tmp_name'], $caminho_destino)) {
    // Buscar id_requisito do tipo de imagem + requisito
    $ext = pathinfo($nome_arquivo, PATHINFO_EXTENSION);
    $tipoArquivo = strtoupper($ext);

    $sql = "SELECT ra.id_requisito, ra.status FROM requisito_arquivo ra 
            JOIN tipo_imagem ti ON ti.id_tipo_imagem = ra.id_tipo_imagem
            WHERE ti.obra_id = ? AND ti.id_tipo_imagem = ? AND ra.nome_requisito = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $obra_id, $tipo_imagem, $tipoArquivo);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $id_requisito = $row['id_requisito'];
    $status_antigo = $row['status'];

    // Inserir na tabela entrega_arquivo
    $sqlInsert = "INSERT INTO entrega_arquivo (id_requisito, nome_arquivo, caminho, status, observacao)
                  VALUES (?, ?, ?, ?, ?)";
    $stmt2 = $conn->prepare($sqlInsert);
    $stmt2->bind_param("issss", $id_requisito, $nome_arquivo, $caminho_destino, $status, $descricao);
    $stmt2->execute();

    $id_entrega = $stmt2->insert_id;

    // Atualizar status do requisito se necessário
    if ($status == "Completo") {
        $sqlUpdate = "UPDATE requisito_arquivo SET status='Completo' WHERE id_requisito=?";
        $stmt3 = $conn->prepare($sqlUpdate);
        $stmt3->bind_param("i", $id_requisito);
        $stmt3->execute();
    } else {
        $sqlUpdate = "UPDATE requisito_arquivo SET status='Incompleto' WHERE id_requisito=?";
        $stmt3 = $conn->prepare($sqlUpdate);
        $stmt3->bind_param("i", $id_requisito);
        $stmt3->execute();
    }

    // Criar log de acompanhamento
    $colaborador_id = 1; // pegar do session
    $sqlLog = "INSERT INTO acompanhamento_recebimento (id_entrega, colaborador_id, status_anterior, status_novo, observacao)
               VALUES (?, ?, ?, ?, ?)";
    $stmt4 = $conn->prepare($sqlLog);
    $stmt4->bind_param("iisss", $id_entrega, $colaborador_id, $status_antigo, $status, $descricao);
    $stmt4->execute();

    echo json_encode(['sucesso' => true, 'mensagem' => "Arquivo enviado com sucesso."]);
} else {
    echo json_encode(['sucesso' => false, 'mensagem' => "Erro ao enviar o arquivo."]);
}
