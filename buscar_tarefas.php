<?php
session_start();
include 'conexao.php'; // Inclua a conexão com o banco de dados.

$idusuario = $_SESSION['idusuario']; // ID do usuário logado.

if (!$idusuario) {
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit;
}

// Definir condições para o SELECT com base no ID do usuário
if ($idusuario == 1) {
    $sql = "SELECT funcao_id, status, check_funcao, imagem_id, colaborador_id 
            FROM funcao_imagem 
            WHERE funcao_id BETWEEN 2 AND 3 AND check_funcao = 0 AND status = 'Em aprovação'";
} elseif ($idusuario == 2) {
    $sql = "SELECT funcao_id, status, check_funcao, imagem_id, colaborador_id 
            FROM funcao_imagem 
            WHERE funcao_id = 4 AND check_funcao = 0 AND status = 'Em aprovação'";
} else {
    echo json_encode([]); // Sem tarefas para outros usuários
    exit;
}

// Executar a consulta
$result = $conn->query($sql);

if ($result === false) {
    echo json_encode(['error' => 'Erro ao executar a consulta']);
    exit;
}

// Obter os resultados
$tarefas = [];
while ($row = $result->fetch_assoc()) {
    $tarefas[] = $row;
}

echo json_encode($tarefas);
