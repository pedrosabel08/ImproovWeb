<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../conexao.php';

$action = $_POST['action'] ?? '';

function response($success, $message)
{
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

function normalizarFuncoes($funcoes, $nivelFinalizacao)
{
    if (!is_array($funcoes)) {
        $funcoes = [];
    }

    $funcoes = array_values(array_unique(array_filter(array_map('intval', $funcoes), function ($id) {
        return $id > 0;
    })));

    return [$funcoes, $nivelFinalizacao === '' ? null : (int) $nivelFinalizacao];
}

function salvarFuncoesColaborador($conn, $idcolaborador, $funcoes, $nivelFinalizacao)
{
    $stmtFinalizacao = $conn->prepare("SELECT idfuncao FROM funcao WHERE nome_funcao = CONVERT(0x46696E616C697A61C3A7C3A36F USING utf8mb4) LIMIT 1");
    $stmtFinalizacao->execute();
    $finalizacao = $stmtFinalizacao->get_result()->fetch_assoc();
    $idFinalizacao = $finalizacao ? (int) $finalizacao['idfuncao'] : 0;

    if ($idFinalizacao > 0 && in_array($idFinalizacao, $funcoes, true) && !in_array($nivelFinalizacao, [1, 2, 3], true)) {
        throw new InvalidArgumentException('Selecione um nivel de finalizacao valido.');
    }

    $stmtDelete = $conn->prepare("DELETE FROM funcao_colaborador WHERE colaborador_id = ?");
    $stmtDelete->bind_param("i", $idcolaborador);
    $stmtDelete->execute();

    if (empty($funcoes)) {
        return;
    }

    $stmtInsert = $conn->prepare("INSERT INTO funcao_colaborador (colaborador_id, funcao_id, nivel_finalizacao) VALUES (?, ?, NULLIF(?, 0))");
    foreach ($funcoes as $idfuncao) {
        $nivel = $idfuncao === $idFinalizacao ? (int) $nivelFinalizacao : 0;
        $stmtInsert->bind_param("iii", $idcolaborador, $idfuncao, $nivel);
        $stmtInsert->execute();
    }
}

if ($action === 'create') {
    $nome_colaborador = trim($_POST['nome_colaborador'] ?? '');
    $nome_usuario = trim($_POST['nome_usuario'] ?? '');
    $login = trim($_POST['login'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $nivel_acesso = $_POST['nivel_acesso'] !== '' ? (int)$_POST['nivel_acesso'] : null;
    $cargos = $_POST['cargos'] ?? [];
    [$funcoes, $nivelFinalizacao] = normalizarFuncoes($_POST['funcoes'] ?? [], $_POST['nivel_finalizacao'] ?? '');

    if ($nome_colaborador === '' || $nome_usuario === '' || $login === '' || $senha === '') {
        response(false, 'Preencha os campos obrigatórios.');
    }

    $conn->begin_transaction();

    try {
        $stmtCol = $conn->prepare("INSERT INTO colaborador (nome_colaborador) VALUES (?)");
        $stmtCol->bind_param("s", $nome_colaborador);
        $stmtCol->execute();
        $idcolaborador = $conn->insert_id;

        $stmtUsu = $conn->prepare("INSERT INTO usuario (nome_usuario, login, senha, nivel_acesso, idcolaborador) VALUES (?, ?, ?, ?, ?)");
        $stmtUsu->bind_param("sssii", $nome_usuario, $login, $senha, $nivel_acesso, $idcolaborador);
        $stmtUsu->execute();
        $idusuario = $conn->insert_id;

        if (!empty($cargos)) {
            $stmtCargo = $conn->prepare("INSERT INTO usuario_cargo (usuario_id, cargo_id) VALUES (?, ?)");
            foreach ($cargos as $idcargo) {
                $idcargoInt = (int)$idcargo;
                $stmtCargo->bind_param("ii", $idusuario, $idcargoInt);
                $stmtCargo->execute();
            }
        }

        salvarFuncoesColaborador($conn, $idcolaborador, $funcoes, $nivelFinalizacao);

        $conn->commit();
        response(true, 'Colaborador criado com sucesso!');
    } catch (Throwable $e) {
        $conn->rollback();
        response(false, 'Erro ao criar colaborador.');
    }
}

if ($action === 'update') {
    $idusuario = (int)($_POST['idusuario'] ?? 0);
    $idcolaborador = (int)($_POST['idcolaborador'] ?? 0);
    $nome_colaborador = trim($_POST['nome_colaborador'] ?? '');
    $nome_usuario = trim($_POST['nome_usuario'] ?? '');
    $login = trim($_POST['login'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $nivel_acesso = $_POST['nivel_acesso'] !== '' ? (int)$_POST['nivel_acesso'] : null;
    $cargos = $_POST['cargos'] ?? [];
    [$funcoes, $nivelFinalizacao] = normalizarFuncoes($_POST['funcoes'] ?? [], $_POST['nivel_finalizacao'] ?? '');

    if ($idusuario <= 0 || $idcolaborador <= 0) {
        response(false, 'Colaborador inválido.');
    }

    $conn->begin_transaction();

    try {
        $stmtCol = $conn->prepare("UPDATE colaborador SET nome_colaborador = ? WHERE idcolaborador = ?");
        $stmtCol->bind_param("si", $nome_colaborador, $idcolaborador);
        $stmtCol->execute();

        if ($senha !== '') {
            $stmtUsu = $conn->prepare("UPDATE usuario SET nome_usuario = ?, login = ?, senha = ?, nivel_acesso = ? WHERE idusuario = ?");
            $stmtUsu->bind_param("sssii", $nome_usuario, $login, $senha, $nivel_acesso, $idusuario);
        } else {
            $stmtUsu = $conn->prepare("UPDATE usuario SET nome_usuario = ?, login = ?, nivel_acesso = ? WHERE idusuario = ?");
            $stmtUsu->bind_param("ssii", $nome_usuario, $login, $nivel_acesso, $idusuario);
        }
        $stmtUsu->execute();

        $stmtDel = $conn->prepare("DELETE FROM usuario_cargo WHERE usuario_id = ?");
        $stmtDel->bind_param("i", $idusuario);
        $stmtDel->execute();

        if (!empty($cargos)) {
            $stmtCargo = $conn->prepare("INSERT INTO usuario_cargo (usuario_id, cargo_id) VALUES (?, ?)");
            foreach ($cargos as $idcargo) {
                $idcargoInt = (int)$idcargo;
                $stmtCargo->bind_param("ii", $idusuario, $idcargoInt);
                $stmtCargo->execute();
            }
        }

        salvarFuncoesColaborador($conn, $idcolaborador, $funcoes, $nivelFinalizacao);

        $conn->commit();
        response(true, 'Colaborador atualizado com sucesso!');
    } catch (Throwable $e) {
        $conn->rollback();
        response(false, 'Erro ao atualizar colaborador.');
    }
}

if ($action === 'delete') {
    $idusuario = (int)($_POST['idusuario'] ?? 0);
    $idcolaborador = (int)($_POST['idcolaborador'] ?? 0);

    if ($idusuario <= 0 || $idcolaborador <= 0) {
        response(false, 'Colaborador inválido.');
    }

    $conn->begin_transaction();

    try {
        $stmtDelCargo = $conn->prepare("DELETE FROM usuario_cargo WHERE usuario_id = ?");
        $stmtDelCargo->bind_param("i", $idusuario);
        $stmtDelCargo->execute();

        $stmtDelUser = $conn->prepare("DELETE FROM usuario WHERE idusuario = ?");
        $stmtDelUser->bind_param("i", $idusuario);
        $stmtDelUser->execute();

        $stmtDelCol = $conn->prepare("DELETE FROM colaborador WHERE idcolaborador = ?");
        $stmtDelCol->bind_param("i", $idcolaborador);
        $stmtDelCol->execute();

        $conn->commit();
        response(true, 'Colaborador excluído com sucesso!');
    } catch (Exception $e) {
        $conn->rollback();
        response(false, 'Erro ao excluir colaborador.');
    }
}

if ($action === 'toggle_status') {
    $idusuario = (int)($_POST['idusuario'] ?? 0);
    $ativo = (int)($_POST['ativo'] ?? 0);

    if ($idusuario <= 0) {
        response(false, 'Usuário inválido.');
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("UPDATE usuario SET ativo = ? WHERE idusuario = ?");
        $stmt->bind_param("ii", $ativo, $idusuario);
        $stmt->execute();

        $stmtCol = $conn->prepare("UPDATE colaborador c JOIN usuario u ON u.idcolaborador = c.idcolaborador SET c.ativo = ? WHERE u.idusuario = ?");
        $stmtCol->bind_param("ii", $ativo, $idusuario);
        $stmtCol->execute();

        $conn->commit();
        response(true, 'Status atualizado com sucesso!');
    } catch (Exception $e) {
        $conn->rollback();
        response(false, 'Erro ao atualizar status.');
    }
}

response(false, 'Ação inválida.');
