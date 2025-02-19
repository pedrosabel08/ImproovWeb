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
    c.telefone,
    (SELECT MAX(h.data_aprovacao)
     FROM historico_aprovacoes h
     WHERE h.funcao_imagem_id = f.idfuncao_imagem) AS data_aprovacao,
    (SELECT h.status_novo
     FROM historico_aprovacoes h
     WHERE h.funcao_imagem_id = f.idfuncao_imagem
     ORDER BY h.data_aprovacao DESC 
     LIMIT 1) AS status_novo
FROM funcao_imagem f
LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
WHERE f.funcao_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9) 
  AND f.check_funcao = 0 
  AND f.status = 'Em aprovação'
ORDER BY data_aprovacao DESC;
";
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
    c.telefone,
    -- Subconsulta para pegar a última data de aprovação
    (SELECT MAX(h.data_aprovacao)
     FROM historico_aprovacoes h
     WHERE h.funcao_imagem_id = f.idfuncao_imagem) AS data_aprovacao
FROM funcao_imagem f
LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
WHERE f.funcao_id IN (1, 2, 3, 4, 5, 6, 7, 8, 9) 
  AND f.check_funcao = 0 
  AND f.status = 'Em aprovação'
  ORDER BY data_aprovacao DESC";
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
