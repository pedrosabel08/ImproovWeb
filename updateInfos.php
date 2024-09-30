<?php
session_start();

if (!isset($_SESSION['idusuario'])) {
    header("Location: login.php");
    exit();
}

$mysqli = new mysqli("mysql.improov.com.br", "improov", "Impr00v", "improov");

if ($mysqli->connect_error) {
    die("Erro na conexão com o banco de dados: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario_id = $_SESSION['idusuario'];

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
    if ($stmt === false) {
        die("Erro na preparação da query: " . $mysqli->error);
    }
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        // UPDATE
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
        if ($stmt === false) {
            die("Erro na preparação da query: " . $mysqli->error);
        }

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
        // INSERT - Executa as duas queries separadamente
        $insertQuery1 = "
            INSERT INTO informacoes_usuario (usuario_id, telefone, data_nascimento, estado_civil, filhos) 
            VALUES (?, ?, ?, ?, ?)
        ";

        $stmt1 = $mysqli->prepare($insertQuery1);
        if ($stmt1 === false) {
            die("Erro na preparação da query: " . $mysqli->error);
        }
        $stmt1->bind_param(
            "issss",
            $usuario_id,
            $telefone,
            $data_nascimento,
            $estado_civil,
            $filhos
        );
        $stmt1->execute();
        $stmt1->close();

        $insertQuery2 = "
            INSERT INTO endereco (usuario_id, rua, numero, bairro, complemento, cep)
            VALUES (?, ?, ?, ?, ?, ?)
        ";

        $stmt2 = $mysqli->prepare($insertQuery2);
        if ($stmt2 === false) {
            die("Erro na preparação da query: " . $mysqli->error);
        }
        $stmt2->bind_param(
            "isssss",
            $usuario_id,
            $rua,
            $numero,
            $bairro,
            $complemento,
            $cep
        );
        $stmt2->execute();
        $stmt2->close();
    }

    if ($stmt->execute()) {
        header("Location: infos.php?status=success&message=Informações atualizadas com sucesso!");
    } else {
        header("Location: infos.php?status=error&message=Algo deu errado, tente novamente.");
    }

    $stmt->close();
    $mysqli->close();
}
