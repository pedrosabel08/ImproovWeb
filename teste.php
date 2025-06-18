<?php
$ftp_user = "flow";
$ftp_pass = "flow@2025";
$ftp_host = "imp-nas.ddns.net";
$ftp_port = 2121;

$nome_arquivo = "logo.jpg";
$local_file = "./assets/logo.jpg";

$remote_path = "/clientes/2025/MSA_HYD/02.Projetos/$nome_arquivo";
$ftp_url = "ftp://$ftp_host:$ftp_port$remote_path";

if (!file_exists($local_file)) {
    die("❌ Arquivo local não encontrado: $local_file");
}

$file = fopen($local_file, 'r');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ftp_url);
curl_setopt($ch, CURLOPT_USERPWD, "$ftp_user:$ftp_pass");
curl_setopt($ch, CURLOPT_UPLOAD, 1);
curl_setopt($ch, CURLOPT_INFILE, $file);
curl_setopt($ch, CURLOPT_INFILESIZE, filesize($local_file));
curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
curl_setopt($ch, CURLOPT_FTP_SSL, CURLFTPSSL_ALL);
curl_setopt($ch, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_TLS);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);

if ($response) {
    echo "✅ Upload do arquivo $nome_arquivo realizado com sucesso.";
} else {
    echo "❌ Erro no upload: " . curl_error($ch);
}

curl_close($ch);
fclose($file);
