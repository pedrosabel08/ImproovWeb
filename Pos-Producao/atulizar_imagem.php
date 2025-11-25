<?php
include_once __DIR__ . '/../conexao.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idImagem = intval($_POST['idimagens_cliente_obra']);
    $nomeImagem = $_POST['imagem_nome'];
    $caminhoPasta = $_POST['caminho_pasta'];
    $numeroBG = $_POST['numero_bg'];
    $observacao = $_POST['obs'];

    $sql = "UPDATE imagens_cliente_obra 
            SET imagem_nome = ?, caminho_pasta = ?, numero_bg = ?, obs = ? 
            WHERE idimagens_cliente_obra = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssii', $nomeImagem, $caminhoPasta, $numeroBG, $observacao, $idImagem);

    if ($stmt->execute()) {
        echo "Atualização bem-sucedida";
    } else {
        echo "Erro ao atualizar: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();