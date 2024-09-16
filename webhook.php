<?php
// webhook.php

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Processar o payload conforme necessário
file_put_contents('webhook.log', print_r($data, true), FILE_APPEND);
