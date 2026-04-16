<?php
include '../conexao.php';
$r = $conn->query("SELECT idcolaborador, nome FROM colaborador ORDER BY nome");
$rows = $r->fetch_all(MYSQLI_ASSOC);
echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
