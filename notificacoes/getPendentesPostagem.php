<?php
header('Content-Type: application/json; charset=utf-8');

// Retorna a lista de pendências na tabela postagem_pendentes (status = 'pending')
// Caminho esperado: /notificacoes/getPendentesPostagem.php

require_once __DIR__ . '/../conexao.php'; // fornece $conn (mysqli)

$response = ['success' => false, 'pendentes' => []];

try {
    $sql = "SELECT p.id, p.imagem_id, p.funcao_imagem_id, p.criado_em, i.imagem_nome
            FROM postagem_pendentes p
            LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = p.imagem_id
            WHERE p.status = 'pending'
            ORDER BY p.criado_em ASC
            LIMIT 200";

    $res = $conn->query($sql);
    if ($res === false) {
        throw new Exception('Query error: ' . $conn->error);
    }

    $pendentes = [];
    while ($row = $res->fetch_assoc()) {
        $pendentes[] = [
            'id' => (int)$row['id'],
            'imagem_id' => (int)$row['imagem_id'],
            'funcao_imagem_id' => (int)$row['funcao_imagem_id'],
            'imagem_nome' => $row['imagem_nome'] ?? null,
            'criado_em' => $row['criado_em'] ?? null
        ];
    }

    $response['success'] = true;
    $response['pendentes'] = $pendentes;

} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

?>
