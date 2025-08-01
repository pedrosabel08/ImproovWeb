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

    // Busca a maior versão já existente para o imagem_id
    $versao = 1;
    $stmtVer = $conn->prepare("SELECT MAX(versao) as max_versao FROM review_uploads WHERE imagem_id = ?");
    $stmtVer->bind_param("i", $imagem_id);
    $stmtVer->execute();
    $stmtVer->bind_result($max_versao);
    if ($stmtVer->fetch() && $max_versao !== null) {
        $versao = $max_versao + 1;
    }
    $stmtVer->close();

    $stmt = $conn->prepare("INSERT INTO review_uploads (imagem_id, nome_arquivo, versao) VALUES (?, ?, ?, ?)");
    $null = NULL; // Para o bind_param de blob
    $stmt->bind_param("ibsi", $imagem_id, $null, $novoNome, $versao);
    $stmt->send_long_data(1, $conteudo);

    if ($stmt->execute()) {
        echo json_encode(['sucesso' => true, 'nome_arquivo' => $novoNome, 'versao' => $versao]);
    } else {
        echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar no banco']);
    }
} else {
    echo json_encode(['sucesso' => false, 'erro' => 'Requisição inválida']);
}