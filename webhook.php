<?php
// webhook.php

// Capturar o payload do GitHub
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Salvar o payload no log para depuração
file_put_contents('webhook.log', print_r($data, true), FILE_APPEND);

// Verificar se há arquivos modificados no payload
if (isset($data['head_commit']['modified'])) {
    $arquivos_para_upload = $data['head_commit']['modified'];
} else {
    file_put_contents('webhook.log', "Nenhum arquivo modificado encontrado no payload.\n", FILE_APPEND);
    die("Nenhum arquivo modificado encontrado.");
}

// Configurações do FTP
$ftp_server = "ftp.improov.com.br"; // Substitua pelo seu servidor FTP
$ftp_user = "improov"; // Seu usuário FTP
$ftp_pass = "Impr00v"; // Sua senha FTP

// Conectar ao servidor FTP
$conn_id = ftp_connect($ftp_server) or die("Não foi possível conectar ao $ftp_server");

// Login no servidor FTP
$login_result = ftp_login($conn_id, $ftp_user, $ftp_pass);

// Checar conexão e login
if ((!$conn_id) || (!$login_result)) {
    file_put_contents('webhook.log', "Conexão ou login no FTP falhou.\n", FILE_APPEND);
    die("Conexão ou login no FTP falhou.");
}

file_put_contents('webhook.log', "Conectado ao FTP.\n", FILE_APPEND);

// Fazer upload dos arquivos modificados
foreach ($arquivos_para_upload as $arquivo) {
    // Caminho local do arquivo (precisa estar acessível pelo script)
    $local_file = __DIR__ . '/' . $arquivo;
    // Caminho no servidor FTP
    $remote_file = '/www/sistema/' . basename($arquivo);

    // Enviar o arquivo para o servidor FTP
    if (ftp_put($conn_id, $remote_file, $local_file, FTP_BINARY)) {
        file_put_contents('webhook.log', "Arquivo $arquivo enviado com sucesso para $remote_file.\n", FILE_APPEND);
    } else {
        file_put_contents('webhook.log', "Falha ao enviar o arquivo $arquivo.\n", FILE_APPEND);
    }
}

// Fechar a conexão FTP
ftp_close($conn_id);
file_put_contents('webhook.log', "Conexão FTP fechada.\n", FILE_APPEND);
