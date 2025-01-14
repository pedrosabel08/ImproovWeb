<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); // Allows all domains
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Allow specific methods
header("Access-Control-Allow-Headers: Content-Type");

session_start();

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

// Verificar se o método da requisição é POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obter os dados do POST e limpar
    $login = htmlspecialchars(trim($_POST['login']));
    $senha = htmlspecialchars(trim($_POST['senha']));

    // Preparar a consulta SQL
    $sql = "SELECT idusuario, nome_usuario, nivel_acesso, idcolaborador FROM usuario WHERE login = ? AND senha = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $login, $senha);
    $stmt->execute();
    $result = $stmt->get_result();

    // Verificar se a consulta encontrou algum usuário
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        $_SESSION['idusuario'] = $row['idusuario'];
        $_SESSION['nome_usuario'] = $row['nome_usuario'];
        $_SESSION['nivel_acesso'] = $row['nivel_acesso'];
        $_SESSION['idcolaborador'] = $row['idcolaborador'];

        $_SESSION['logado'] = true;

        // Atualizar o campo ultimo_acesso na tabela usuario
        $updateSql = "UPDATE usuario SET ultimo_acesso = NOW() WHERE idusuario = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $row['idusuario']);
        $updateStmt->execute();

        // Verificar se a atualização foi bem-sucedida
        if ($updateStmt->affected_rows > 0) {
            // Resposta JSON de sucesso
            echo json_encode([
                "status" => "success",
                "message" => "Login bem-sucedido!",
                "user" => [
                    "id" => $row['idusuario'],
                    "name" => $row['nome_usuario']
                ]
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Erro ao atualizar último acesso."
            ]);
        }

        // Fechar a declaração de atualização
        $updateStmt->close();
    } else {
        // Resposta JSON de falha
        echo json_encode([
            "status" => "error",
            "message" => "Login ou senha incorretos."
        ]);
    }

    // Fechar a declaração e a conexão
    $stmt->close();
    $conn->close();
}
