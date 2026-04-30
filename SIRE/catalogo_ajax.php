<?php

/**
 * SIRE — Catálogo de Referências
 * Endpoint AJAX: catalogo_ajax.php
 */
require_once __DIR__ . '/../config/session_bootstrap.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'erro', 'message' => 'Não autorizado.']);
    exit();
}

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

include_once __DIR__ . '/../conexaoMain.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action === 'getRefs') {
    $conn = conectarBanco();

    $sql = "
        SELECT
            ri.id,
            ri.funcao_imagem_id,
            ri.nomenclatura,
            ri.nome_arquivo,
            ri.importado_em,
            i.obra_id,
            i.tipo_imagem as ambiente,
            o.nomenclatura AS obra_nomenclatura
        FROM referencias_imagens ri
        LEFT JOIN funcao_imagem fi ON fi.idfuncao_imagem = ri.funcao_imagem_id
        LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
        LEFT JOIN obra o ON o.idobra = i.obra_id
        ORDER BY ri.importado_em DESC
        LIMIT 5000
    ";

    $res = $conn->query($sql);

    if (!$res) {
        $conn->close();
        echo json_encode(['status' => 'erro', 'message' => 'Erro ao consultar banco: ' . $conn->error]);
        exit();
    }

    $refs = [];
    while ($row = $res->fetch_assoc()) {
        $refs[] = $row;
    }
    $res->free();
    $conn->close();

    echo json_encode([
        'status' => 'sucesso',
        'total'  => count($refs),
        'refs'   => $refs,
    ]);
    exit();
}

echo json_encode(['status' => 'erro', 'message' => 'Ação inválida.']);
