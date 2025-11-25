<?php

$servername = '72.60.137.192';
$username = 'improov';
$password = 'Impr00v@';
$dbname = 'flowdb';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');
