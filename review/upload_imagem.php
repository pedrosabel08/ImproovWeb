<?php

include '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['imagem']) && isset($_POST['imagem_id'])) {
    $imagem_id = intval($_POST['imagem_id']);
    $arquivoTmp = $_FILES['imagem']['tmp_name'];
    $nomeOriginal = $_FILES['imagem']['name'];
    $tipoArquivo = $_FILES['imagem']['type'];

    // Verifica e cria a pasta se não existir
    $pastaDestino = __DIR__ . '/../uploads/imagens/';
    if (!is_dir($pastaDestino)) mkdir($pastaDestino, 0755, true);

    // Gera nome: img_<imagem_id>_<hash>.<ext>
    $extensao = pathinfo($nomeOriginal, PATHINFO_EXTENSION);
    $hash = substr(md5(time() . $nomeOriginal), 0, 8);
    $novoNome = "img_{$imagem_id}_{$hash}." . $extensao;
    $caminhoFinal = $pastaDestino . $novoNome;

    // Move o arquivo para a pasta final
    if (!move_uploaded_file($arquivoTmp, $caminhoFinal)) {
        die(json_encode(['sucesso' => false, 'erro' => 'Erro ao mover arquivo']));
    }

    // Também salva o conteúdo binário se necessário
    $conteudo = file_get_contents($caminhoFinal);

    $stmt = $conn->prepare("INSERT INTO review_uploads (imagem_id, arquivo, nome_arquivo, tipo_arquivo) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ibss", $imagem_id, $conteudo, $novoNome, $tipoArquivo);

    if ($stmt->execute()) {
        echo json_encode(['sucesso' => true, 'nome_arquivo' => $novoNome]);
    } else {
        echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar no banco']);
    }
} else {
    echo json_encode(['sucesso' => false, 'erro' => 'Requisição inválida']);
}
