<?php
// Arquivo: revisao.php
require_once __DIR__ . '/../config/session_bootstrap.php';
session_start();
include '../conexao.php'; // Conexão com o banco de dados

// Verifique se o usuário está autenticado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../index.html");
    exit();
}


// Buscar as tarefas de cada colaborador do banco de dados
$sql = "SELECT
    ico.idimagens_cliente_obra AS imagem_id,
    ico.imagem_nome,
    fi.status,
    f.nome_funcao,
    pc.prioridade,
    l.data AS data_mais_recente,
    c.nome_colaborador
FROM funcao_imagem fi
JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
JOIN obra o ON ico.obra_id = o.idobra
JOIN funcao f ON fi.funcao_id = f.idfuncao
JOIN prioridade_funcao pc ON fi.idfuncao_imagem = pc.funcao_imagem_id
JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
LEFT JOIN (
    SELECT funcao_imagem_id, MAX(data) AS data
    FROM log_alteracoes
    GROUP BY funcao_imagem_id
) l ON l.funcao_imagem_id = fi.idfuncao_imagem
WHERE fi.status = 'Em andamento' ORDER BY data_mais_recente DESC";

$result = $conn->query($sql);

$colaboradores = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $colaboradores[] = $row;
    }
}

// Retorna as tarefas no formato JSON
echo json_encode($colaboradores);
