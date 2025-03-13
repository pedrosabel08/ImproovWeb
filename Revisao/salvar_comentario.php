<?php
$pdo = new PDO('mysql:host=mysql.improov.com.br;dbname=improov', 'improov', 'Impr00v');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$data = json_decode(file_get_contents('php://input'), true);

$ap_imagem_id = $data['ap_imagem_id'];
$x = $data['x'];
$y = $data['y'];
$texto = $data['texto'];

// Busca o último número de comentário para a imagem
$stmt = $pdo->prepare('SELECT IFNULL(MAX(numero_comentario), 0) + 1 AS proximo_numero FROM comentarios_imagem WHERE ap_imagem_id = ?');
$stmt->execute([$ap_imagem_id]);
$numero_comentario = $stmt->fetch(PDO::FETCH_ASSOC)['proximo_numero'];

// Insere o novo comentário com o número gerado
$stmt = $pdo->prepare('INSERT INTO comentarios_imagem (ap_imagem_id, numero_comentario, x, y, texto) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$ap_imagem_id, $numero_comentario, $x, $y, $texto]);

$response = [
    'sucesso' => true
];

header('Content-Type: application/json');
echo json_encode($response);
?>