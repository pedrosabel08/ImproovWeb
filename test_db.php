<?php
$host = '72.60.137.192';
$user = 'improov';
$pass = 'Impr00v@';
$db = 'flowdb';
$port = 3306;

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    echo 'Erro: ' . $conn->connect_error;
} else {
    echo 'Conectado com sucesso. MySQL version: ' . $conn->server_info;
}
$conn->close();