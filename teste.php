<?php
// Dados FTP
$ftp_host = "ftp.improov.com.br";
$ftp_port = 21; // FTP padrão
$ftp_user = "improov";
$ftp_pass = "Impr00v";
$ftp_dir  = "/www/sistema/uploads/"; // pasta remota

// Conectar ao servidor FTP
$conn_id = ftp_connect($ftp_host, $ftp_port, 10); // timeout 10s

if (!$conn_id) {
    die("❌ Não foi possível conectar ao servidor FTP.");
}

// Autenticar
if (!ftp_login($conn_id, $ftp_user, $ftp_pass)) {
    ftp_close($conn_id);
    die("❌ Falha na autenticação FTP.");
}

// Ativar modo passivo (muito útil em redes com firewall/NAT)
ftp_pasv($conn_id, true);

// Listar arquivos da pasta (apenas para teste)
$files = ftp_nlist($conn_id, $ftp_dir);

if ($files !== false) {
    echo "✅ Conexão FTP bem-sucedida!\n";
    echo "Arquivos na pasta remota '$ftp_dir':\n";
    print_r($files);
} else {
    echo "⚠ Não foi possível listar arquivos na pasta '$ftp_dir'.";
}

// Fechar conexão
ftp_close($conn_id);
