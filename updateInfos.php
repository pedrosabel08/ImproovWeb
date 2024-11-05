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

    // Captura e escapamento de dados
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
    $cnpj = $mysqli->real_escape_string($_POST['cnpj']);
    $rua_cnpj = $mysqli->real_escape_string($_POST['rua_cnpj']);
    $numero_cnpj = $mysqli->real_escape_string($_POST['numero_cnpj']);
    $bairro_cnpj = $mysqli->real_escape_string($_POST['bairro_cnpj']);
    $complemento_cnpj = $mysqli->real_escape_string($_POST['complemento_cnpj']);
    $cep_cnpj = $mysqli->real_escape_string($_POST['cep_cnpj']);

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
    $stmt->close();

    if ($row['count'] > 0) {
        // UPDATE
        $updateQuery = "
            UPDATE usuario u
            JOIN informacoes_usuario iu ON u.idusuario = iu.usuario_id
            JOIN endereco e ON u.idusuario = e.usuario_id
            JOIN endereco_cnpj ec ON u.idusuario = ec.usuario_id
            SET 
                u.nome_usuario = ?, 
                u.senha = ?, 
                u.email = ?, 
                iu.telefone = ?, 
                iu.data_nascimento = ?, 
                iu.estado_civil = ?, 
                iu.filhos = ?, 
                iu.cnpj = ?, 
                e.rua = ?, 
                e.numero = ?, 
                e.bairro = ?, 
                e.complemento = ?, 
                e.cep = ?, 
                ec.rua_cnpj = ?, 
                ec.numero_cnpj = ?, 
                ec.bairro_cnpj = ?, 
                ec.complemento_cnpj = ?, 
                ec.cep_cnpj = ? 
            WHERE u.idusuario = ?
        ";

        $stmt = $mysqli->prepare($updateQuery);
        if ($stmt === false) {
            die("Erro na preparação da query de atualização: " . $mysqli->error);
        }

        $stmt->bind_param(
            "ssssssssssssssssssi",
            $nome,
            $senha,
            $email,
            $telefone,
            $data_nascimento,
            $estado_civil,
            $filhos,
            $cnpj,
            $rua,
            $numero,
            $bairro,
            $complemento,
            $cep,
            $rua_cnpj,
            $numero_cnpj,
            $bairro_cnpj,
            $complemento_cnpj,
            $cep_cnpj,
            $usuario_id
        );

        if ($stmt->execute()) {
            header("Location: infos.php?status=success&message=Informações atualizadas com sucesso!");
        } else {
            die("Erro ao atualizar informações: " . $stmt->error);
        }
        $stmt->close();
    } else {
        // Inserção de dados para um novo usuário (caso não exista)
        $insertQuery1 = "
            INSERT INTO informacoes_usuario (usuario_id, telefone, data_nascimento, estado_civil, filhos, cnpj) 
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        $stmt1 = $mysqli->prepare($insertQuery1);
        if ($stmt1 === false) {
            die("Erro na preparação da query de inserção: " . $mysqli->error);
        }
        $stmt1->bind_param(
            "isssss",
            $usuario_id,
            $telefone,
            $data_nascimento,
            $estado_civil,
            $filhos,
            $cnpj
        );
        if (!$stmt1->execute()) {
            die("Erro ao inserir dados na tabela informacoes_usuario: " . $stmt1->error);
        }
        $stmt1->close();

        // Inserção de dados na tabela endereco_cnpj
        $insertQuery3 = "
            INSERT INTO endereco_cnpj (usuario_id, rua_cnpj, numero_cnpj, bairro_cnpj, complemento_cnpj, cep_cnpj)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        $stmt3 = $mysqli->prepare($insertQuery3);
        if ($stmt3 === false) {
            die("Erro na preparação da query de inserção para endereco_cnpj: " . $mysqli->error);
        }
        $stmt3->bind_param(
            "isssss",
            $usuario_id,
            $rua_cnpj,
            $numero_cnpj,
            $bairro_cnpj,
            $complemento_cnpj,
            $cep_cnpj
        );
        if (!$stmt3->execute()) {
            die("Erro ao inserir dados na tabela endereco_cnpj: " . $stmt3->error);
        }
        $stmt3->close();
    }

    $mysqli->close();
}
