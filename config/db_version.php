<?php

function improov_db_version_connect(): ?mysqli
{
    $servername = '72.60.137.192';
    $username = 'improov';
    $password = 'Impr00v@';
    $dbname = 'flowdb';

    $conn = @new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        return null;
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}
