<?php
// Arquivo: buscar_cliente.php

include 'conexao.php'; // Inclua seu arquivo de conexão

if (isset($_POST['id'])) {
    $idCliente = intval($_POST['id']); // Obtenha o ID do cliente

    // Prepare a consulta
    $stmt = $conn->prepare("SELECT * FROM cliente WHERE idcliente = ?");

    // Verifica se a preparação da consulta falhou
    if ($stmt === false) {
        die(json_encode(["error" => "Falha ao preparar a consulta: " . $conn->error]));
    }

    // Bind e execute a consulta
    $stmt->bind_param("i", $idCliente);
    $stmt->execute();

    // Obtenha o resultado
    $resultado = $stmt->get_result();
    $cliente = $resultado->fetch_assoc();

    if ($cliente) {
        // Formate e retorne as informações
        echo "<p><strong>Nome:</strong> <input type='text' class='form-control' value='" . htmlspecialchars($cliente['nome_cliente']) . "'></p>";

        // Buscando contatos do cliente
        $stmtContatos = $conn->prepare("SELECT telefone, endereco FROM contato_cliente WHERE cliente_id = ?");
        $stmtContatos->bind_param("i", $idCliente);
        $stmtContatos->execute();
        $resultadoContatos = $stmtContatos->get_result();

        echo "<p><strong>Contatos:</strong> <br>";
        while ($contato = $resultadoContatos->fetch_assoc()) {
            echo "Telefone: <input type='text' class='form-control' value='" . htmlspecialchars($contato['telefone']) . "' style='margin-top: 5px;'> <br>";
            echo "Endereço: <input type='text' class='form-control' value='" . htmlspecialchars($contato['endereco']) . "' style='margin-top: 5px;'><br>";
        }
        echo "</p>";

        // Buscando responsáveis do cliente
        $stmtResponsaveis = $conn->prepare("SELECT resp, cargo FROM resp_cliente WHERE cliente_id = ?");
        $stmtResponsaveis->bind_param("i", $idCliente);
        $stmtResponsaveis->execute();
        $resultadoResponsaveis = $stmtResponsaveis->get_result();

        echo "<p><strong>Responsáveis:</strong> <br>";
        while ($responsavel = $resultadoResponsaveis->fetch_assoc()) {
            echo "Nome: <input type='text' class='form-control' value='" . htmlspecialchars($responsavel['resp']) . "' style='margin-top: 5px;'> - Cargo: <input type='text' class='form-control' value='" . htmlspecialchars($responsavel['cargo']) . "' style='margin-top: 5px;'><br>";
        }
        echo "</p>";

        // Botão para salvar alterações
        echo "<button id='saveChanges' class='btn btn-primary'>Salvar</button>";
    } else {
        echo "<p>Cliente não encontrado.</p>";
    }

    // Fecha a declaração
    $stmt->close();
    $stmtContatos->close();
    $stmtResponsaveis->close();
} else {
    echo "<p>Parâmetro inválido.</p>";
}

// Fecha a conexão
$conn->close();
