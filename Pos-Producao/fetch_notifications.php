<?php
session_start();
include_once '../conexao.php';

$usuario_id = $_SESSION['idusuario']; // ID do usuário logado

$sql = "SELECT n.id AS notificacao_id, n.mensagem, nu.lida 
        FROM notificacoes n 
        JOIN notificacoes_usuarios nu ON n.id = nu.notificacao_id 
        WHERE nu.usuario_id = ?
        AND n.tipo_notificacao = 'pos'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$notificacoes = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($notificacoes);
