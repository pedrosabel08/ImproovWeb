<?php
session_start();
include 'conexao.php';

$response = ["success" => false, "message" => "Erro ao atualizar informações."];

// DEBUG: write a start entry so we know the script was hit
$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/updateInfos.log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$startEntry = date('Y-m-d H:i:s') . ' | START | user: ' . (isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 'n/a') . ' | colaborador: ' . (isset($_SESSION['idcolaborador']) ? $_SESSION['idcolaborador'] : 'n/a') . ' | post: ' . json_encode($_POST ?? []) . PHP_EOL;
file_put_contents($logFile, $startEntry, FILE_APPEND | LOCK_EX);

function respond_and_exit($conn, &$response, $message, $sql_error = null)
{
    $response['success'] = false;
    $response['message'] = $message;
    if ($sql_error) {
        $response['sql_error'] = $sql_error;
    }
        // Write to log file
        $logDir = __DIR__ . '/logs';
        $logFile = $logDir . '/updateInfos.log';
        $userId = isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 'n/a';
        $colabId = isset($_SESSION['idcolaborador']) ? $_SESSION['idcolaborador'] : 'n/a';
        $postJson = json_encode($_POST ?? []);
        $logEntry = date('Y-m-d H:i:s') . ' | ' . $message;
        if ($sql_error) {
            $logEntry .= ' | SQL: ' . $sql_error;
        }
        $logEntry .= ' | user: ' . $userId . ' | colaborador: ' . $colabId . ' | post: ' . $postJson . PHP_EOL;
        // Attempt to write; suppress warnings but still proceed to return JSON
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        if ($conn) {
            $conn->close();
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
}

if (!isset($_SESSION['idcolaborador']) || !isset($_SESSION['idusuario'])) {
    respond_and_exit($conn, $response, 'Erro: usuário não autenticado.');
}


if (isset($_SESSION['idusuario'])) {
    $usuario_id = $_SESSION['idusuario'];
    $colaborador_id = $_SESSION['idcolaborador'];

    // Receber e validar os dados do formulário
    $nome = $_POST['nome'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $cpf = $_POST['cpf'] ?? '';
    $cep = $_POST['cep'] ?? '';
    $bairro = $_POST['bairro'] ?? '';
    $rua = $_POST['rua'] ?? '';
    $numero = $_POST['numero'] ?? 0;
    $complemento = $_POST['complemento'] ?? '';
    $cnpj = $_POST['cnpj'] ?? '';
    $nome_empresarial = $_POST['nome_empresarial'] ?? '';
    $nome_fantasia = $_POST['nome_fantasia'] ?? '';
    $cep_cnpj = $_POST['cep_cnpj'] ?? '';
    $bairro_cnpj = $_POST['bairro_cnpj'] ?? '';
    $rua_cnpj = $_POST['rua_cnpj'] ?? '';
    $numero_cnpj = $_POST['numero_cnpj'] ?? 0;
    $complemento_cnpj = $_POST['complemento_cnpj'] ?? '';
    $uf_cnpj = $_POST['uf_cnpj'] ?? '';
    $localidade_cnpj = $_POST['localidade_cnpj'] ?? '';
    $data_nascimento = $_POST['data'] ?? null;
    $estado_civil = $_POST['estado_civil'] ?? null;
    $filhos = $_POST['filho'] ?? null;
    $horario_disponivel = $_POST['horario_disponivel'] ?? '';
    $modalidade         = $_POST['modalidade'] ?? '';
    $tamanho_camisa     = $_POST['tamanho_camisa'] ?? '';
    $tamanho_calcado    = $_POST['tamanho_calcado'] ?? '';
    $observacoes        = $_POST['observacoes'] ?? '';

    // Atualizando o usuário (tabela usuario) com UPDATE para evitar erros com colunas NOT NULL não fornecidas
    $queryUsuario = "UPDATE usuario SET nome_usuario = ?, senha = ?, email = ? WHERE idusuario = ?";

    // bind_param: 3 strings e 1 inteiro (sssi)
    $stmtUsuario = $conn->prepare($queryUsuario);
    if ($stmtUsuario === false) {
        respond_and_exit($conn, $response, 'Erro ao preparar query Usuario', $conn->error);
    }
    if ($stmtUsuario->bind_param("sssi", $nome, $senha, $email, $usuario_id) === false) {
        respond_and_exit($conn, $response, 'Erro ao bind_param Usuario', $stmtUsuario->error);
    }
    if ($stmtUsuario->execute() === false) {
        respond_and_exit($conn, $response, 'Erro ao executar query Usuario', $stmtUsuario->error);
    }
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
    if ($stmtInformacoes === false) {
        respond_and_exit($conn, $response, 'Erro ao preparar query Informacoes', $conn->error);
    }
    if ($stmtInformacoes->bind_param("issssssss", $usuario_id, $telefone, $data_nascimento, $estado_civil, $filhos, $cnpj, $nome_empresarial, $nome_fantasia, $cpf) === false) {
        respond_and_exit($conn, $response, 'Erro ao bind_param Informacoes', $stmtInformacoes->error);
    }
    if ($stmtInformacoes->execute() === false) {
        respond_and_exit($conn, $response, 'Erro ao executar query Informacoes', $stmtInformacoes->error);
    }
    $stmtInformacoes->close();

    // Handle uploaded thumbnail (campo 'thumb')
    if (!empty($_FILES['thumb']) && isset($_FILES['thumb']['error']) && $_FILES['thumb']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['thumb'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            // ignore file but log
            @file_put_contents($logFile, date('Y-m-d H:i:s') . " | Thumb too large: {$file['size']}\n", FILE_APPEND | LOCK_EX);
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif'
            ];
            if (!isset($allowed[$mime])) {
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " | Thumb invalid mime: $mime\n", FILE_APPEND | LOCK_EX);
            } else {
                $ext = $allowed[$mime];
                $uploadDir = __DIR__ . '/uploads/colaboradores';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                $safeBase = preg_replace('/[^A-Za-z0-9_-]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
                $filename = $usuario_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . '/' . $filename;
                if (@move_uploaded_file($file['tmp_name'], $dest)) {
                    $thumbPath = 'uploads/colaboradores/' . $filename;
                    // attempt to update DB; if column missing, try to add it
                    $updateThumbSql = "UPDATE informacoes_usuario SET thumb = ? WHERE usuario_id = ?";
                    $stmtThumb = $conn->prepare($updateThumbSql);
                    if ($stmtThumb === false) {
                        // try to add column and prepare again
                        $alter = "ALTER TABLE informacoes_usuario ADD COLUMN thumb VARCHAR(255) DEFAULT NULL";
                        if ($conn->query($alter) === false) {
                            @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | Failed to add thumb column: ' . $conn->error . PHP_EOL, FILE_APPEND | LOCK_EX);
                        }
                        $stmtThumb = $conn->prepare($updateThumbSql);
                    }
                    if ($stmtThumb !== false) {
                        if ($stmtThumb->bind_param('si', $thumbPath, $usuario_id) !== false) {
                            if ($stmtThumb->execute() === false) {
                                @file_put_contents($logFile, date('Y-m-d H:i:s') . ' | Failed to update thumb: ' . $stmtThumb->error . PHP_EOL, FILE_APPEND | LOCK_EX);
                            } else {
                                // set session so header/avatar updates immediately
                                if (session_status() !== PHP_SESSION_ACTIVE) session_start();
                                $_SESSION['foto_colaborador'] = $thumbPath;
                                $response['thumb'] = $thumbPath;
                            }
                        }
                        $stmtThumb->close();
                    }
                } else {
                    @file_put_contents($logFile, date('Y-m-d H:i:s') . " | move_uploaded_file failed\n", FILE_APPEND | LOCK_EX);
                }
            }
        }
    }

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
    if ($stmtEndereco === false) {
        respond_and_exit($conn, $response, 'Erro ao preparar query Endereco', $conn->error);
    }
    if ($stmtEndereco->bind_param("isssss", $usuario_id, $rua, $numero, $bairro, $complemento, $cep) === false) {
        respond_and_exit($conn, $response, 'Erro ao bind_param Endereco', $stmtEndereco->error);
    }
    if ($stmtEndereco->execute() === false) {
        respond_and_exit($conn, $response, 'Erro ao executar query Endereco', $stmtEndereco->error);
    }
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
    if ($stmtEnderecoCnpj === false) {
        respond_and_exit($conn, $response, 'Erro ao preparar query EnderecoCnpj', $conn->error);
    }
    if ($stmtEnderecoCnpj->bind_param("isssssss", $usuario_id, $rua_cnpj, $numero_cnpj, $bairro_cnpj, $complemento_cnpj, $cep_cnpj, $uf_cnpj, $localidade_cnpj) === false) {
        respond_and_exit($conn, $response, 'Erro ao bind_param EnderecoCnpj', $stmtEnderecoCnpj->error);
    }
    if ($stmtEnderecoCnpj->execute() === false) {
        respond_and_exit($conn, $response, 'Erro ao executar query EnderecoCnpj', $stmtEnderecoCnpj->error);
    }
    $stmtEnderecoCnpj->close();

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
        respond_and_exit($conn, $response, 'Erro ao preparar query PerfilColaborador', $conn->error);
    }
    if ($stmt->bind_param(
        "isssss",
        $colaborador_id,
        $horario_disponivel,
        $modalidade,
        $tamanho_camisa,
        $tamanho_calcado,
        $observacoes
    ) === false) {
        respond_and_exit($conn, $response, 'Erro ao bind_param PerfilColaborador', $stmt->error);
    }
    if ($stmt->execute() === false) {
        respond_and_exit($conn, $response, 'Erro ao executar query PerfilColaborador', $stmt->error);
    }
    $stmt->close();

    $response["success"] = true;
    $response["message"] = "Informações atualizadas com sucesso!";
} else {

    $response["message"] = "Usuário não autenticado.";
}

$conn->close();

// Retorna a resposta como JSON
header('Content-Type: application/json');
echo json_encode($response);
