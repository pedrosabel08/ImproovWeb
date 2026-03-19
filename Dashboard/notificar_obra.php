<?php

/**
 * Endpoint AJAX para enviar notificação dentro de uma obra.
 *
 * GET  ?obra_id=X  → retorna colaboradores do projeto agrupados por função
 * POST             → cria notificação e insere destinatários
 */

require_once __DIR__ . '/../config/session_bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit();
}

include_once __DIR__ . '/../conexaoMain.php';
$conn = conectarBanco();

/* ───────── GET: listar pessoas da obra ───────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $obraId = (int)($_GET['obra_id'] ?? 0);
    if ($obraId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'obra_id inválido.']);
        exit();
    }

    // Busca colaboradores distintos com suas funções nesta obra
    $sql = "SELECT DISTINCT c.idcolaborador,
                   c.nome_colaborador,
                   f.idfuncao      AS funcao_id,
                   f.nome_funcao
            FROM funcao_imagem fi
            JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
            JOIN colaborador c            ON c.idcolaborador = fi.colaborador_id
            JOIN funcao f                 ON f.idfuncao = fi.funcao_id
            WHERE ico.obra_id = ? AND c.ativo = 1
            ORDER BY f.idfuncao, c.nome_colaborador";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao preparar consulta.']);
        exit();
    }
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $res = $stmt->get_result();

    $pessoas = [];      // id → { id, nome, funcoes: [{ id, nome }] }
    $funcoes = [];       // id → nome

    while ($row = $res->fetch_assoc()) {
        $cid = (int)$row['idcolaborador'];
        $fid = (int)$row['funcao_id'];
        $funcoes[$fid] = $row['nome_funcao'];

        if (!isset($pessoas[$cid])) {
            $pessoas[$cid] = [
                'id'      => $cid,
                'nome'    => $row['nome_colaborador'],
                'funcoes' => [],
            ];
        }
        // Adiciona função se ainda não tiver
        $already = false;
        foreach ($pessoas[$cid]['funcoes'] as $pf) {
            if ($pf['id'] === $fid) {
                $already = true;
                break;
            }
        }
        if (!$already) {
            $pessoas[$cid]['funcoes'][] = ['id' => $fid, 'nome' => $row['nome_funcao']];
        }
    }
    $stmt->close();

    // Busca usuario_id correspondente a cada colaborador
    $colaboradorIds = array_keys($pessoas);
    if (!empty($colaboradorIds)) {
        $placeholders = implode(',', array_fill(0, count($colaboradorIds), '?'));
        $types = str_repeat('i', count($colaboradorIds));
        $sqlU = "SELECT idusuario, idcolaborador FROM usuario WHERE idcolaborador IN ($placeholders)";
        $stmtU = $conn->prepare($sqlU);
        if ($stmtU) {
            $stmtU->bind_param($types, ...$colaboradorIds);
            $stmtU->execute();
            $resU = $stmtU->get_result();
            $mapColab = [];
            while ($r = $resU->fetch_assoc()) {
                $mapColab[(int)$r['idcolaborador']] = (int)$r['idusuario'];
            }
            $stmtU->close();
            foreach ($pessoas as &$p) {
                $p['usuario_id'] = $mapColab[$p['id']] ?? null;
            }
            unset($p);
        }
    }

    $conn->close();
    echo json_encode([
        'pessoas' => array_values($pessoas),
        'funcoes' => $funcoes,
    ]);
    exit();
}

/* ───────── POST: enviar notificação ───────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        // Fallback a POST form
        $input = $_POST;
    }

    $obraId      = (int)($input['obra_id'] ?? 0);
    $titulo      = trim((string)($input['titulo'] ?? ''));
    $mensagem    = trim((string)($input['mensagem'] ?? ''));
    $usuarioIds  = $input['usuario_ids'] ?? [];

    if (!is_array($usuarioIds)) {
        $usuarioIds = [];
    }
    $usuarioIds = array_values(array_filter(array_map('intval', $usuarioIds)));

    // Garante que os usuários 1, 2 e 9 sempre recebam a notificação
    $usuarioIds = array_values(array_unique(array_merge($usuarioIds, [1, 2, 9])));

    if ($titulo === '' || $mensagem === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Título e mensagem são obrigatórios.']);
        exit();
    }
    if (empty($usuarioIds)) {
        http_response_code(400);
        echo json_encode(['error' => 'Selecione ao menos um destinatário.']);
        exit();
    }

    $tipo = 'info';
    $canal = 'modal';
    $segmentacao_tipo = 'pessoa';
    $prioridade = 0;
    $ativa = 1;
    $fixa = 0;
    $fechavel = 1;
    $exige_confirmacao = 0;
    $criado_por = (int)($_SESSION['idusuario'] ?? 0);

    $sql = "INSERT INTO notificacoes
                (titulo, mensagem, tipo, canal, segmentacao_tipo, prioridade,
                 ativa, fixa, fechavel, exige_confirmacao, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao preparar INSERT.']);
        exit();
    }

    $stmt->bind_param(
        'sssssiiiiii',
        $titulo,
        $mensagem,
        $tipo,
        $canal,
        $segmentacao_tipo,
        $prioridade,
        $ativa,
        $fixa,
        $fechavel,
        $exige_confirmacao,
        $criado_por
    );

    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao salvar notificação.']);
        exit();
    }

    $notificacaoId = (int)$conn->insert_id;
    $stmt->close();

    // Inserir alvos (tipo=pessoa)
    $stmtAlvo = $conn->prepare('INSERT INTO notificacoes_alvos (notificacao_id, tipo, alvo_id) VALUES (?, ?, ?)');
    if ($stmtAlvo) {
        foreach ($usuarioIds as $uid) {
            $stmtAlvo->bind_param('isi', $notificacaoId, $segmentacao_tipo, $uid);
            $stmtAlvo->execute();
        }
        $stmtAlvo->close();
    }

    // Inserir destinatários
    $stmtDest = $conn->prepare('INSERT INTO notificacoes_destinatarios (notificacao_id, usuario_id) VALUES (?, ?)');
    $inserted = 0;
    if ($stmtDest) {
        foreach ($usuarioIds as $uid) {
            $stmtDest->bind_param('ii', $notificacaoId, $uid);
            if ($stmtDest->execute()) $inserted++;
        }
        $stmtDest->close();
    }

    $conn->close();
    echo json_encode([
        'ok'              => true,
        'notificacao_id'  => $notificacaoId,
        'destinatarios'   => $inserted,
    ]);
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Método não permitido.']);
