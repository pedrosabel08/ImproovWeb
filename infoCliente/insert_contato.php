<?php

header("Access-Control-Allow-Origin: *"); // Allows all domains
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Allow specific methods
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../contact_architecture.php';

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// Verificar se os dados foram enviados via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idcontato = isset($_POST['idcontato']) ? (int) $_POST['idcontato'] : 0;
    $idcliente = isset($_POST['idcliente']) ? (int) $_POST['idcliente'] : 0;

    $contact = [
        'name' => $_POST['nome_contato'] ?? '',
        'email' => $_POST['email'] ?? '',
        'role' => $_POST['cargo'] ?? '',
        'type' => $_POST['tipo'] ?? 'OUTRO',
        'notes' => $_POST['observacoes'] ?? '',
        'phone' => $_POST['telefone'] ?? '',
    ];

    try {
        if ($idcontato > 0) {
            $existing = contact_arch_fetch_contact_row($conn, $idcontato);
            if (!$existing) {
                throw new RuntimeException('Contato nao encontrado para atualizacao.');
            }

            if ($idcliente <= 0) {
                $idcliente = (int) ($existing['cliente_id'] ?? 0);
            }

            contact_arch_update_client_contact_by_id($conn, $idcontato, $contact);
            echo "Dados atualizados com sucesso!";
        } else {
            if ($idcliente <= 0) {
                throw new RuntimeException('Cliente invalido para inserir contato.');
            }

            contact_arch_save_client_contact($conn, $idcliente, $contact);
            echo "Dados inseridos com sucesso!";
        }
    } catch (Throwable $throwable) {
        echo "Erro ao salvar dados: " . $throwable->getMessage();
    }

    $conn->close();
}
