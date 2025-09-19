<?php
header('Content-Type: application/json');
session_start();

include 'conexao.php';

// Verifica se usuário está logado (ajuste conforme sua autenticação)
if (!isset($_SESSION['idcolaborador'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

$criador_id = $_SESSION['idcolaborador']; // id do usuário logado

// Pega dados do POST
$titulo = trim($_POST['task-title'] ?? '');
$descricao = trim($_POST['task-desc'] ?? '');
$prioridade = trim($_POST['task-prioridade'] ?? '');
$prazo = trim($_POST['task-prazo-date'] ?? '');
$colaborador_id = trim($_POST['task-colab'] ?? $criador_id); // se não enviar, assume ele mesmo

// Validação básica
if (!$titulo || !$descricao || !$prioridade || !$prazo) {
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);
    exit;
}

// Validação de permissões
// Só permite definir outro colaborador se for id 9 ou 21
if ($colaborador_id != $criador_id && !in_array($criador_id, [9, 21])) {
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para atribuir tarefas a outros colaboradores.']);
    exit;
}

// Prepara e executa inserção
$stmt = $conn->prepare("INSERT INTO tarefas (titulo, descricao, prioridade, prazo, colaborador_id) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssssi", $titulo, $descricao, $prioridade, $prazo, $colaborador_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao adicionar a tarefa.']);
}

$stmt->close();
$conn->close();
