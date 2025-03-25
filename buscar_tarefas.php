<?php
session_start();
include 'conexao.php'; // Inclua a conexão com o banco de dados.

$idusuario = $_SESSION['idusuario']; // ID do usuário logado.
$idcolaborador = $_SESSION['idcolaborador'];

if (!$idusuario) {
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit;
}

// Definir condições para o SELECT com base no ID do usuário
if ($idusuario == 1 || $idusuario == 2) {
    $sql = "SELECT  f.idfuncao_imagem,
            f.funcao_id, 
            fun.nome_funcao, 
            f.status, 
            f.prazo,
            f.check_funcao, 
            f.imagem_id, 
            i.imagem_nome, 
            f.colaborador_id, 
            c.nome_colaborador,
            c.telefone  
            FROM funcao_imagem f
            LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
            LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
            LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
            WHERE f.funcao_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9) AND f.check_funcao = 0 AND f.status = 'Em aprovação'";
} elseif ($idusuario == 9) {
    // Nicolle
    $sql = "SELECT 
        f.idfuncao_imagem,
        f.funcao_id, 
        fun.nome_funcao, 
        f.status, 
        f.check_funcao, 
        f.imagem_id, 
        i.imagem_nome, 
        f.colaborador_id, 
        c.nome_colaborador, 
        c.telefone
    FROM funcao_imagem f
    LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
    LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
    WHERE f.funcao_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9)
      AND f.check_funcao = 0 
      AND f.status = 'Em aprovação'";
} else {
    $sql = "SELECT 
    f.idfuncao_imagem,
    f.funcao_id, 
    fun.nome_funcao, 
    f.status, 
    f.check_funcao, 
    f.imagem_id, 
    i.imagem_nome, 
    f.colaborador_id, 
    c.nome_colaborador, 
    c.telefone
    FROM funcao_imagem f
    LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
    LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
    WHERE f.funcao_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9)
      AND f.check_funcao = 0 
      AND f.status = 'Ajuste'
      AND c.idcolaborador = ?";

    // Preparar a consulta
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idcolaborador); // Vincula o parâmetro
    $stmt->execute();
    $result = $stmt->get_result(); // Obtém o resultado da consulta
}

// Executar a consulta (apenas para os casos sem bind_param)
if (!isset($stmt)) {
    $result = $conn->query($sql);

    if ($result === false) {
        echo json_encode(['error' => 'Erro ao executar a consulta']);
        exit;
    }
}

// Obter os resultados
$tarefas = [];
while ($row = $result->fetch_assoc()) {
    $tarefas[] = $row;
}

echo json_encode($tarefas);
