<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/capacidade_colaborador_helper.php';

$action = $_POST['action'] ?? '';

function response($success, $message)
{
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

function normalizarFuncoes($funcoes, $nivelFinalizacao, $nivelArquitetura, $nivelAnimacao)
{
    if (!is_array($funcoes)) {
        $funcoes = [];
    }

    $funcoes = array_values(array_unique(array_filter(array_map('intval', $funcoes), function ($id) {
        return $id > 0;
    })));

    return [
        $funcoes,
        $nivelFinalizacao === '' ? null : (int) $nivelFinalizacao,
        $nivelArquitetura === '' ? null : (int) $nivelArquitetura,
        $nivelAnimacao === '' ? null : (int) $nivelAnimacao,
    ];
}

function normalizarAtuacoesFuncoes($atuacoes, array $funcoes): array
{
    if (!is_array($atuacoes)) {
        return [];
    }

    $resultado = [];
    foreach ($funcoes as $funcaoId) {
        $id = (int) $funcaoId;
        if (flow_capacidade_tipo_atuacao_informado($atuacoes, $id)) {
            $resultado[$id] = flow_capacidade_tipo_atuacao_para_funcao($atuacoes, $id);
        }
    }
    return $resultado;
}

/**
 * Sincroniza os vínculos sem apagar os que permanecem selecionados. Assim,
 * id, valor e tipo_atuacao sobrevivem a edições antigas que não enviam papel.
 */
function salvarFuncoesColaborador($conn, $idcolaborador, $funcoes, $nivelFinalizacao, $nivelArquitetura, $nivelAnimacao, array $atuacoes = [])
{
    // nome_funcao usa utf8mb4_unicode_ci, enquanto parâmetros preparados no
    // MySQL atual chegam em utf8mb4_0900_ai_ci. A collation explícita evita
    // que a própria validação de Finalização impeça qualquer salvamento.
    $stmtFinalizacao = $conn->prepare(
        'SELECT idfuncao
           FROM funcao
          WHERE nome_funcao = (CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci)
          LIMIT 1'
    );
    $nomeFinalizacao = 'Finalização';
    $stmtFinalizacao->bind_param('s', $nomeFinalizacao);
    $stmtFinalizacao->execute();
    $finalizacao = $stmtFinalizacao->get_result()->fetch_assoc();
    $idFinalizacao = $finalizacao ? (int) $finalizacao['idfuncao'] : 0;

    $funcoesArquitetura = [];
    $resultadoArquitetura = $conn->query(
        "SELECT idfuncao, nome_funcao
           FROM funcao
          WHERE nome_funcao IN ('Caderno', 'Filtro de assets')"
    );
    if ($resultadoArquitetura) {
        while ($funcaoArquitetura = $resultadoArquitetura->fetch_assoc()) {
            $funcoesArquitetura[(int) $funcaoArquitetura['idfuncao']] = true;
        }
    }

    $idAnimacao = 0;
    $resultadoAnimacao = $conn->query(
        "SELECT idfuncao
           FROM funcao
          WHERE nome_funcao = 'Animação'
          LIMIT 1"
    );
    if ($resultadoAnimacao && ($animacao = $resultadoAnimacao->fetch_assoc())) {
        $idAnimacao = (int) $animacao['idfuncao'];
    }

    if ($idFinalizacao > 0 && in_array($idFinalizacao, $funcoes, true) && !in_array($nivelFinalizacao, [1, 2, 3], true)) {
        throw new InvalidArgumentException('Selecione um nivel de finalizacao valido.');
    }
    if (array_intersect(array_keys($funcoesArquitetura), $funcoes) && !in_array($nivelArquitetura, [1, 2, 3], true)) {
        throw new InvalidArgumentException('Selecione um nivel de Arquitetura valido.');
    }
    if ($idAnimacao > 0 && in_array($idAnimacao, $funcoes, true) && !in_array($nivelAnimacao, [1, 2, 3], true)) {
        throw new InvalidArgumentException('Selecione um nivel de Animacao valido.');
    }

    $stmtExistentes = $conn->prepare(
        'SELECT idfuncao_colaborador, funcao_id, tipo_atuacao, nivel_finalizacao
           FROM funcao_colaborador
          WHERE colaborador_id = ?
          FOR UPDATE'
    );
    $stmtExistentes->bind_param('i', $idcolaborador);
    $stmtExistentes->execute();
    $existentes = [];
    $resultadoExistentes = $stmtExistentes->get_result();
    while ($linha = $resultadoExistentes->fetch_assoc()) {
        $existentes[(int) $linha['funcao_id']] = [
            'id' => (int) $linha['idfuncao_colaborador'],
            'tipo_atuacao' => flow_capacidade_normalizar_tipo_atuacao($linha['tipo_atuacao'] ?? null),
            'nivel_finalizacao' => $linha['nivel_finalizacao'] === null ? null : (int) $linha['nivel_finalizacao'],
        ];
    }
    $stmtExistentes->close();

    $selecionadas = array_fill_keys(array_map('intval', $funcoes), true);
    $stmtDelete = $conn->prepare('DELETE FROM funcao_colaborador WHERE idfuncao_colaborador = ?');
    foreach ($existentes as $funcaoId => $existente) {
        if (!isset($selecionadas[$funcaoId])) {
            $idVinculo = (int) $existente['id'];
            $stmtDelete->bind_param('i', $idVinculo);
            $stmtDelete->execute();
        }
    }
    $stmtDelete->close();

    $stmtInsert = $conn->prepare(
        'INSERT INTO funcao_colaborador (colaborador_id, funcao_id, tipo_atuacao, nivel_finalizacao)
         VALUES (?, ?, ?, NULLIF(?, 0))'
    );
    $stmtUpdate = $conn->prepare(
        'UPDATE funcao_colaborador
            SET tipo_atuacao = ?, nivel_finalizacao = NULLIF(?, 0)
          WHERE idfuncao_colaborador = ?'
    );
    foreach ($funcoes as $idfuncao) {
        $idfuncao = (int) $idfuncao;
        if ($idfuncao === $idFinalizacao) {
            $nivel = (int) $nivelFinalizacao;
        } elseif (isset($funcoesArquitetura[$idfuncao])) {
            $nivel = (int) $nivelArquitetura;
        } elseif ($idfuncao === $idAnimacao) {
            $nivel = (int) $nivelAnimacao;
        } else {
            $nivel = $existentes[$idfuncao]['nivel_finalizacao'] ?? 0;
        }
        if (isset($existentes[$idfuncao])) {
            $tipo = array_key_exists($idfuncao, $atuacoes)
                ? $atuacoes[$idfuncao]
                : $existentes[$idfuncao]['tipo_atuacao'];
            $idVinculo = (int) $existentes[$idfuncao]['id'];
            $stmtUpdate->bind_param('sii', $tipo, $nivel, $idVinculo);
            $stmtUpdate->execute();
            continue;
        }

        $tipo = $atuacoes[$idfuncao] ?? FLOW_TIPO_ATUACAO_SECUNDARIA;
        $stmtInsert->bind_param('iisi', $idcolaborador, $idfuncao, $tipo, $nivel);
        $stmtInsert->execute();
    }
    $stmtInsert->close();
    $stmtUpdate->close();
}

if ($action === 'create') {
    $nome_colaborador = trim($_POST['nome_colaborador'] ?? '');
    $nome_usuario = trim($_POST['nome_usuario'] ?? '');
    $login = trim($_POST['login'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $nivel_acesso = $_POST['nivel_acesso'] !== '' ? (int)$_POST['nivel_acesso'] : null;
    $cargos = $_POST['cargos'] ?? [];
    [$funcoes, $nivelFinalizacao, $nivelArquitetura, $nivelAnimacao] = normalizarFuncoes(
        $_POST['funcoes'] ?? [],
        $_POST['nivel_finalizacao'] ?? '',
        $_POST['nivel_arquitetura'] ?? '',
        $_POST['nivel_animacao'] ?? ''
    );
    $atuacoes = normalizarAtuacoesFuncoes($_POST['tipo_atuacao'] ?? [], $funcoes);
    $elegivelCapacidade = !array_key_exists('elegivel_capacidade', $_POST) || !empty($_POST['elegivel_capacidade']) ? 1 : 0;

    if ($nome_colaborador === '' || $nome_usuario === '' || $login === '' || $senha === '') {
        response(false, 'Preencha os campos obrigatórios.');
    }

    $conn->begin_transaction();

    try {
        $stmtCol = $conn->prepare("INSERT INTO colaborador (nome_colaborador, elegivel_capacidade) VALUES (?, ?)");
        $stmtCol->bind_param("si", $nome_colaborador, $elegivelCapacidade);
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

        salvarFuncoesColaborador($conn, $idcolaborador, $funcoes, $nivelFinalizacao, $nivelArquitetura, $nivelAnimacao, $atuacoes);

        $conn->commit();
        response(true, 'Colaborador criado com sucesso!');
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Erro ao criar colaborador: ' . $e->getMessage());
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
    [$funcoes, $nivelFinalizacao, $nivelArquitetura, $nivelAnimacao] = normalizarFuncoes(
        $_POST['funcoes'] ?? [],
        $_POST['nivel_finalizacao'] ?? '',
        $_POST['nivel_arquitetura'] ?? '',
        $_POST['nivel_animacao'] ?? ''
    );
    $atuacoes = normalizarAtuacoesFuncoes($_POST['tipo_atuacao'] ?? [], $funcoes);
    $elegivelCapacidadeInformada = array_key_exists('elegivel_capacidade', $_POST);
    $elegivelCapacidade = !empty($_POST['elegivel_capacidade']) ? 1 : 0;

    if ($idusuario <= 0 || $idcolaborador <= 0) {
        response(false, 'Colaborador inválido.');
    }

    $conn->begin_transaction();

    try {
        if ($elegivelCapacidadeInformada) {
            $stmtCol = $conn->prepare("UPDATE colaborador SET nome_colaborador = ?, elegivel_capacidade = ? WHERE idcolaborador = ?");
            $stmtCol->bind_param("sii", $nome_colaborador, $elegivelCapacidade, $idcolaborador);
        } else {
            $stmtCol = $conn->prepare("UPDATE colaborador SET nome_colaborador = ? WHERE idcolaborador = ?");
            $stmtCol->bind_param("si", $nome_colaborador, $idcolaborador);
        }
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

        salvarFuncoesColaborador($conn, $idcolaborador, $funcoes, $nivelFinalizacao, $nivelArquitetura, $nivelAnimacao, $atuacoes);

        $conn->commit();
        response(true, 'Colaborador atualizado com sucesso!');
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Erro ao atualizar colaborador: ' . $e->getMessage());
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
