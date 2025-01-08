<?php
// Arquivo: revisao.php
session_start();
include '../conexao.php'; // Conexão com o banco de dados

// Verifique se o usuário está autenticado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../index.html");
    exit();
}

$idusuario = $_SESSION['idusuario'];

// Buscar as tarefas de revisão do banco de dados
if ($idusuario == 1 || $idusuario == 2) {
    //Pedro e André
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
            WHERE f.funcao_id IN (1, 2, 3, 4, 5, 6, 7) AND f.check_funcao = 0 AND f.status = 'Em aprovação'";
// } elseif ($idusuario == 2) {
//     //André
//     $sql = "SELECT  f.idfuncao_imagem,
//             f.funcao_id, 
//             fun.nome_funcao, 
//             f.status, 
//             f.prazo,
//             f.check_funcao, 
//             f.imagem_id, 
//             i.imagem_nome, 
//             f.colaborador_id, 
//             c.nome_colaborador,
//             c.telefone  
//             FROM funcao_imagem f
//             LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
//             LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
//             LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
//             WHERE f.funcao_id in (4, 5) AND f.check_funcao = 0 AND f.status = 'Em aprovação'";
} elseif ($idusuario == 9) {
    //Nicolle
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
        l.data,
        c.telefone
    FROM funcao_imagem f
    LEFT JOIN log_alteracoes l ON f.idfuncao_imagem = l.funcao_imagem_id
    LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
    LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
    WHERE f.funcao_id IN (1, 2, 3)
      AND f.check_funcao = 0 
      AND l.status_novo = 'Em aprovação'
    ORDER BY l.data DESC";
}

$result = $conn->query($sql);

$tarefas = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tarefas[] = $row;
    }
}

// Retorna as tarefas no formato JSON
echo json_encode($tarefas);
