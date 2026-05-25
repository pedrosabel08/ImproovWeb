<?php
// Arquivo: buscar_cliente.php

include 'conexao.php'; // Inclua seu arquivo de conexão
require_once __DIR__ . '/../contact_architecture.php';

if (isset($_POST['id'])) {
    $idCliente = intval($_POST['id']); // Obtenha o ID do cliente

    $stmt = $conn->prepare("SELECT idcliente, nome_cliente FROM cliente WHERE idcliente = ? LIMIT 1");

    if ($stmt === false) {
        echo json_encode(["error" => "Falha ao preparar a consulta: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("i", $idCliente);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        $contatos = contact_arch_fetch_client_contacts($conn, $idCliente, null);
        $cliente['emails'] = implode(';', array_filter(array_map(static function ($contato) {
            return trim((string) ($contato['email'] ?? ''));
        }, $contatos)));
        $cliente['nomes_contato'] = implode(';', array_filter(array_map(static function ($contato) {
            return trim((string) ($contato['name'] ?? ''));
        }, $contatos)));
        $cliente['cargos'] = implode(';', array_filter(array_map(static function ($contato) {
            return trim((string) ($contato['role'] ?? ''));
        }, $contatos)));
        $cliente['contatos'] = $contatos;
        echo json_encode($cliente);
    } else {
        echo json_encode(["error" => "Cliente não encontrado."]);
    }

    $stmt->close();
} else {
    echo json_encode(["error" => "Parâmetro inválido."]);
}

$conn->close();
