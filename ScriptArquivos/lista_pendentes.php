<?php
require 'conexao.php';

// Cabeçalho para retorno JSON
header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT 
            a.idarquivo, 
            a.nome_original, 
            a.status, 
            a.caminho, 
            c.nome_colaborador AS recebido_por, 
            a.recebido_em, 
            o.nome_obra AS obra,
            a.obra_id
        FROM arquivos a
        JOIN colaborador c ON c.idcolaborador = a.recebido_por
        JOIN obra o ON o.idobra = a.obra_id";

$result = $conn->query($sql);

$arquivos = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $arquivos[] = $row;
    }
}

// Retorna JSON
echo json_encode($arquivos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
