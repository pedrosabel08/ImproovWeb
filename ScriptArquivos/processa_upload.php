<?php
require 'conexao.php';
require 'vendor/autoload.php';

use phpseclib3\Net\SFTP;

// Dados SFTP
$ftp_user = "flow";
$ftp_pass = "flow@2025";
$ftp_host = "imp-nas.ddns.net";
$ftp_port = 2222;

// Pasta raiz do input
$input_raiz = "/mnt/clientes/2025/TES_TES/05.Exchange/01.Input/";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projeto = $_POST['projeto'];       // ⚠️ confirmar se é ID ou código
    $responsavel = $_POST['responsavel'];
    $arquivo = $_FILES['arquivo'];

    if ($arquivo['error'] === UPLOAD_ERR_OK) {
        $nomeOriginal = basename($arquivo['name']);

        // Conecta via phpseclib
        $sftp = new SFTP($ftp_host, $ftp_port);
        if (!$sftp->login($ftp_user, $ftp_pass)) {
            die("Falha na autenticação SFTP.");
        }

        // Caminho final no servidor
        $destino = $input_raiz . $nomeOriginal;

        // Upload
        if (!$sftp->put($destino, file_get_contents($arquivo['tmp_name']))) {
            die("Falha ao enviar arquivo via SFTP.");
        }

        // --- BANCO DE DADOS ---
        // ⚠️ Aqui estou fixando projeto = 1 só para teste, depois buscamos no banco
        $projetoId = 1;

        // Insere no banco (arquivos)
        $stmt = $conn->prepare("INSERT INTO arquivos 
            (obra_id, nome_original, caminho, status, origem, recebido_por) 
            VALUES (?, ?, ?, 'pendente', 'Upload manual', ?)");
        $stmt->bind_param("isss", $projetoId, $nomeOriginal, $destino, $responsavel);
        $stmt->execute();
        $idArquivo = $stmt->insert_id;
        $stmt->close();

        // Insere tarefa
        $stmt2 = $conn->prepare("INSERT INTO tarefas_arquivos 
            (obra_id, arquivo_id, colaborador_id, descricao) 
            VALUES (?, ?, ?, 'Revisar arquivo recebido')");
        $stmt2->bind_param("iis", $projetoId, $idArquivo, $responsavel);
        $stmt2->execute();
        $stmt2->close();

        echo "✅ Arquivo enviado via SFTP e tarefa criada com sucesso!";
    } else {
        echo "Erro no upload.";
    }
}
