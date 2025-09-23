<?php

header("Access-Control-Allow-Origin: *"); // Allows all domains
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Allow specific methods
header("Access-Control-Allow-Headers: Content-Type");

$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

$conn->set_charset('utf8mb4');

// Verificar a conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $idFuncaoSelecionada = $_GET['ajid'];

    // Proteção contra SQL Injection
    $idFuncaoSelecionada = $conn->real_escape_string($idFuncaoSelecionada);

    // Query para buscar o histórico
    $sqlHistorico = "SELECT 
        h.*, 
        h.responsavel, 
        c.nome_colaborador AS colaborador_nome, 
        c2.nome_colaborador AS responsavel_nome,
        i.imagem_nome, 
        fun.nome_funcao,
        s.nome_status,
        i.idimagens_cliente_obra AS imagem_id,
        hi.id AS historico_imagem_id
    FROM historico_aprovacoes h
    JOIN colaborador c ON h.colaborador_id = c.idcolaborador
    JOIN colaborador c2 ON h.responsavel = c2.idcolaborador
    LEFT JOIN funcao_imagem f ON f.idfuncao_imagem = h.funcao_imagem_id
    LEFT JOIN historico_aprovacoes_imagens hi ON f.idfuncao_imagem = fi.funcao_imagem_id
    LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
    LEFT JOIN status_imagem s ON i.status_id = s.idstatus
    WHERE h.funcao_imagem_id = $idFuncaoSelecionada";

    $resultHistorico = $conn->query($sqlHistorico);
    $historico = array();
    if ($resultHistorico->num_rows > 0) {
        while ($row = $resultHistorico->fetch_assoc()) {
            $historico[] = $row;
        }
    }

    // Query para buscar imagens associadas
    $sqlImagens = "SELECT 
    hi.*,
    COUNT(ci.id) AS comment_count,
    CASE 
        WHEN COUNT(ci.id) > 0 THEN true
        ELSE false
    END AS has_comments
FROM historico_aprovacoes_imagens hi
LEFT JOIN comentarios_imagem ci ON ci.ap_imagem_id = hi.id
WHERE hi.funcao_imagem_id = $idFuncaoSelecionada
GROUP BY hi.id";

    $resultImagens = $conn->query($sqlImagens);
    $imagens = array();
    if ($resultImagens->num_rows > 0) {
        while ($row = $resultImagens->fetch_assoc()) {
            $imagens[] = $row;
        }
    }

    $response = array(
        'historico' => $historico,
        'imagens' => $imagens
    );

    header('Content-Type: application/json');
    echo json_encode($response);
}

$conn->close();
