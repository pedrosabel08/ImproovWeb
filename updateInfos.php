<?php
session_start();
include 'conexao.php';

$response = ["success" => false, "message" => "Erro ao atualizar informações."];

if (!isset($_SESSION['idcolaborador'])) {
    die("Erro: usuário não autenticado.");
}


if (isset($_SESSION['idusuario'])) {
    $usuario_id = $_SESSION['idusuario'];
    $colaborador_id = $_SESSION['idcolaborador'];

    // Receber e validar os dados do formulário
    $nome = $_POST['nome'] ?? null;
    $senha = $_POST['senha'] ?? null;
    $email = $_POST['email'] ?? null;
    $telefone = $_POST['telefone'] ?? null;
    $cpf = $_POST['cpf'] ?? null;
    $cep = $_POST['cep'] ?? null;
    $bairro = $_POST['bairro'] ?? null;
    $rua = $_POST['rua'] ?? null;
    $numero = $_POST['numero'] ?? null;
    $complemento = $_POST['complemento'] ?? null;
    $cnpj = $_POST['cnpj'] ?? null;
    $nome_empresarial = $_POST['nome_empresarial'] ?? null;
    $nome_fantasia = $_POST['nome_fantasia'] ?? null;
    $cep_cnpj = $_POST['cep_cnpj'] ?? null;
    $bairro_cnpj = $_POST['bairro_cnpj'] ?? null;
    $rua_cnpj = $_POST['rua_cnpj'] ?? null;
    $numero_cnpj = $_POST['numero_cnpj'] ?? null;
    $complemento_cnpj = $_POST['complemento_cnpj'] ?? null;
    $uf_cnpj = $_POST['uf_cnpj'] ?? null;
    $localidade_cnpj = $_POST['localidade_cnpj'] ?? null;
    $data_nascimento = $_POST['data'] ?? null;
    $estado_civil = $_POST['estado_civil'] ?? null;
    $filhos = $_POST['filho'] ?? null;
    $horario_disponivel = $_POST['horario_disponivel'] ?? '';
    $modalidade         = $_POST['modalidade'] ?? '';
    $tamanho_camisa     = $_POST['tamanho_camisa'] ?? '';
    $tamanho_calcado    = $_POST['tamanho_calcado'] ?? '';
    $observacoes        = $_POST['observacoes'] ?? '';

    // Atualizando o usuário (tabela usuario)
    $queryUsuario = "INSERT INTO usuario (idusuario, nome_usuario, senha, email)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            nome_usuario = VALUES(nome_usuario),
            senha = VALUES(senha),
            email = VALUES(email);
    ";

    // Aqui bind_param deve ter 4 elementos: "isss" (3 strings e 1 inteiro)
    $stmtUsuario = $conn->prepare($queryUsuario);
    $stmtUsuario->bind_param("isss", $usuario_id, $nome, $senha, $email);
    $stmtUsuario->execute();
    $stmtUsuario->close();

    // Atualizando informações do usuário (tabela informacoes_usuario)
    $queryInformacoes = "INSERT INTO informacoes_usuario (usuario_id, telefone, data_nascimento, estado_civil, filhos, cnpj, nome_empresarial, nome_fantasia, cpf)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            telefone = VALUES(telefone),
            data_nascimento = VALUES(data_nascimento),
            estado_civil = VALUES(estado_civil),
            filhos = VALUES(filhos),
            cnpj = VALUES(cnpj),
            nome_empresarial = VALUES(nome_empresarial),
            nome_fantasia = VALUES(nome_fantasia),
            cpf = VALUES(cpf);
    ";

    // Aqui bind_param deve ter 6 elementos: "ssssss" (5 strings e 1 inteiro)
    $stmtInformacoes = $conn->prepare($queryInformacoes);
    $stmtInformacoes->bind_param("issssssss", $usuario_id, $telefone, $data_nascimento, $estado_civil, $filhos, $cnpj, $nome_empresarial, $nome_fantasia, $cpf);
    $stmtInformacoes->execute();
    $stmtInformacoes->close();

    // Atualizando o endereço (tabela endereco)
    $queryEndereco = "INSERT INTO endereco (usuario_id, rua, numero, bairro, complemento, cep)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            rua = VALUES(rua),
            numero = VALUES(numero),
            bairro = VALUES(bairro),
            complemento = VALUES(complemento),
            cep = VALUES(cep);
    ";

    // Aqui bind_param deve ter 6 elementos: "issssss" (6 strings)
    $stmtEndereco = $conn->prepare($queryEndereco);
    $stmtEndereco->bind_param("isssss", $usuario_id, $rua, $numero, $bairro, $complemento, $cep);
    $stmtEndereco->execute();
    $stmtEndereco->close();

    // Atualizando o endereço do CNPJ (tabela endereco_cnpj)
    $queryEnderecoCnpj = "INSERT INTO endereco_cnpj (usuario_id, rua_cnpj, numero_cnpj, bairro_cnpj, complemento_cnpj, cep_cnpj, uf_cnpj, localidade_cnpj)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            rua_cnpj = VALUES(rua_cnpj),
            numero_cnpj = VALUES(numero_cnpj),
            bairro_cnpj = VALUES(bairro_cnpj),
            complemento_cnpj = VALUES(complemento_cnpj),
            cep_cnpj = VALUES(cep_cnpj),
            uf_cnpj = VALUES(uf_cnpj),
            localidade_cnpj = VALUES(localidade_cnpj);
    ";

    // Aqui bind_param deve ter 6 elementos: "issssss" (6 strings)
    $stmtEnderecoCnpj = $conn->prepare($queryEnderecoCnpj);
    $stmtEnderecoCnpj->bind_param("isssssss", $usuario_id, $rua_cnpj, $numero_cnpj, $bairro_cnpj, $complemento_cnpj, $cep_cnpj, $uf_cnpj, $localidade_cnpj);
    $stmtEnderecoCnpj->execute();
    $stmtEnderecoCnpj->close();

    $response["success"] = true;
    $response["message"] = "Informações atualizadas com sucesso!";

    $sql = "INSERT INTO perfil_colaborador (
        colaborador_id, 
        horario_disponivel, 
        modalidade, 
        tamanho_camisa, 
        tamanho_calcado, 
        observacoes
    ) VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        horario_disponivel = VALUES(horario_disponivel),
        modalidade = VALUES(modalidade),
        tamanho_camisa = VALUES(tamanho_camisa),
        tamanho_calcado = VALUES(tamanho_calcado),
        observacoes = VALUES(observacoes)";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Erro ao preparar a query: " . $conn->error);
    }

    $stmt->bind_param(
        "isssss",
        $colaborador_id,
        $horario_disponivel,
        $modalidade,
        $tamanho_camisa,
        $tamanho_calcado,
        $observacoes
    );

    $stmt->execute();
    $stmt->close();
} else {

    $response["message"] = "Usuário não autenticado.";
}

$conn->close();

// Retorna a resposta como JSON
header('Content-Type: application/json');
echo json_encode($response);
