<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
session_start();

include_once '../conexao.php'; // Inclui a conexão com o banco de dados (central)

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

$usuario_id = $_SESSION['idusuario']; // ID do usuário logado

// Recuperar notificações para o usuário
$sql = "SELECT n.id AS notificacao_id, n.mensagem, nu.lida 
        FROM notificacoes_gerais n 
        JOIN notificacoes_usuarios nu ON n.id = nu.notificacao_id 
        WHERE nu.usuario_id = ? AND nu.lida = 0
        ORDER BY n.data_criacao DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

// Array para armazenar notificações
$notificacoes = [];

while ($row = $result->fetch_assoc()) {
    $notificacoes[] = [
        'notificacao_id' => $row['notificacao_id'],
        'mensagem' => $row['mensagem'],
        'lida' => (bool)$row['lida'] // Converte o valor para booleano
    ];
}

$stmt->close();
$conn->close();

// Retornar notificações em formato JSON
echo json_encode(['success' => true, 'notificacoes' => $notificacoes]);
