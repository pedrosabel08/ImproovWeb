<?php
// Arquivo: buscar_cliente.php

include 'conexao.php'; // Inclua seu arquivo de conexão

if (isset($_POST['id'])) {
    $idCliente = intval($_POST['id']); // Obtenha o ID do cliente

    // Prepare a consulta com LEFT JOIN e GROUP_CONCAT
    $stmt = $conn->prepare("
        SELECT c.idcliente, c.nome_cliente,
               GROUP_CONCAT(cc.email SEPARATOR ';') AS emails,
               GROUP_CONCAT(cc.nome_contato SEPARATOR ';') AS nomes_contato,
               GROUP_CONCAT(cc.cargo SEPARATOR ';') AS cargos
        FROM cliente AS c
        LEFT JOIN contato_cliente AS cc ON c.idcliente = cc.cliente_id
        WHERE c.idcliente = ?
        GROUP BY c.idcliente
    ");

    // Verifica se a preparação da consulta falhou
    if ($stmt === false) {
        echo json_encode(["error" => "Falha ao preparar a consulta: " . $conn->error]);
        exit;
    }

    // Bind e execute a consulta
    $stmt->bind_param("i", $idCliente);
    $stmt->execute();

    // Obtenha o resultado
    $resultado = $stmt->get_result();

    // Verifique se encontrou o cliente
    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        // Retorna as informações como JSON
        echo json_encode($cliente);
    } else {
        // Caso não encontre o cliente
        echo json_encode(["error" => "Cliente não encontrado."]);
    }

    // Fecha a declaração
    $stmt->close();
} else {
    echo json_encode(["error" => "Parâmetro inválido."]);
}

// Fecha a conexão
$conn->close();
