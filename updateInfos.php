<?php
session_start();

if (!isset($_SESSION['idusuario'])) {
    // Redirecionar para a página de login se não estiver autenticado
    header("Location: login.php");
    exit();
}

// Conectar ao banco de dados
$mysqli = new mysqli("mysql.improov.com.br", "improov", "Impr00v", "improov");

if ($mysqli->connect_error) {
    die("Erro na conexão com o banco de dados: " . $mysqli->connect_error);
}

// Certificar-se de que a conexão está usando UTF-8
$mysqli->set_charset("utf8mb4");

// Capturar dados do formulário ao enviar
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obter o ID do usuário
    $usuario_id = $_SESSION['idusuario'];

    // Obter os dados do formulário
    $nome = $mysqli->real_escape_string($_POST['nome']);
    $senha = $mysqli->real_escape_string($_POST['senha']);
    $email = $mysqli->real_escape_string($_POST['email']);
    $telefone = $mysqli->real_escape_string($_POST['telefone']);
    $data_nascimento = $mysqli->real_escape_string($_POST['data']);
    $estado_civil = $mysqli->real_escape_string($_POST['estado_civil']);
    $filhos = $mysqli->real_escape_string($_POST['filho']);
    $rua = $mysqli->real_escape_string($_POST['rua']);
    $numero = $mysqli->real_escape_string($_POST['numero']);
    $bairro = $mysqli->real_escape_string($_POST['bairro']);
    $complemento = $mysqli->real_escape_string($_POST['complemento']);
    $cep = $mysqli->real_escape_string($_POST['cep']);

    // Verifica se já existe um registro para o usuário
    $checkQuery = "SELECT COUNT(*) as count FROM informacoes_usuario WHERE usuario_id = ?";
    $stmt = $mysqli->prepare($checkQuery);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        // Se o usuário já tem registro, fazemos o UPDATE
        $updateQuery = "
            UPDATE usuario u
            JOIN informacoes_usuario iu ON u.idusuario = iu.usuario_id
            JOIN endereco e ON u.idusuario = e.usuario_id
            SET 
                u.nome_usuario = ?, 
                u.senha = ?,
                u.email = ?, 
                iu.telefone = ?, 
                iu.data_nascimento = ?,
                iu.estado_civil = ?, 
                iu.filhos = ?, 
                e.rua = ?, 
                e.numero = ?, 
                e.bairro = ?, 
                e.complemento = ?, 
                e.cep = ?
            WHERE u.idusuario = ?
        ";

        $stmt = $mysqli->prepare($updateQuery);
        $stmt->bind_param(
            "ssssssssssssi",
            $nome,
            $senha,
            $email,
            $telefone,
            $data_nascimento,
            $estado_civil,
            $filhos,
            $rua,
            $numero,
            $bairro,
            $complemento,
            $cep,
            $usuario_id
        );
    } else {
        // Se não existe, fazemos o INSERT
        $insertQuery = "
            INSERT INTO informacoes_usuario (usuario_id, telefone, data_nascimento, estado_civil, filhos) 
            VALUES (?, ?, ?, ?, ?);
            INSERT INTO endereco (usuario_id, rua, numero, bairro, complemento, cep)
            VALUES (?, ?, ?, ?, ?, ?);
        ";

        $stmt = $mysqli->prepare($insertQuery);
        $stmt->bind_param(
            "issssssssss",
            $usuario_id,
            $telefone,
            $data_nascimento,
            $estado_civil,
            $filhos,
            $usuario_id,
            $rua,
            $numero,
            $bairro,
            $complemento,
            $cep
        );
    }

    // Executa a query e verifica sucesso
    if ($stmt->execute()) {
        header("Location: infos.php?status=success&message=Informações atualizadas com sucesso!");
    } else {
        header("Location: infos.php?status=error&message=Algo deu errado, tente novamente.");
    }

    $stmt->close();
    $mysqli->close();
}
