<?php
require_once dirname(__DIR__, 2) . '/config/session_bootstrap.php';
session_start();
include '../../conexao.php';
header('Content-Type: application/json; charset=utf-8');

$idcolaborador = 4;

if (!$idcolaborador) {
    echo json_encode(['error' => 'Colaborador não autenticado']);
    exit;
}

$today = date('Y-m-d');
$next7 = date('Y-m-d', strtotime('+7 days'));

$response = [];

// 1) Tarefas do dia (prazo = hoje)
$sqlHoje = "SELECT f.idfuncao_imagem, f.status, f.prazo, f.observacao, f.funcao_id, fun.nome_funcao, i.imagem_nome, i.obra_id
FROM funcao_imagem f
LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
WHERE f.colaborador_id = ? AND DATE(f.prazo) = ? ORDER BY f.prazo ASC";
$stmtHoje = $conn->prepare($sqlHoje);
$stmtHoje->bind_param('is', $idcolaborador, $today);
$stmtHoje->execute();
$resHoje = $stmtHoje->get_result();
$response['tarefasHoje'] = $resHoje ? $resHoje->fetch_all(MYSQLI_ASSOC) : [];
$stmtHoje->close();

// 2) Tarefas atrasadas (prazo < hoje, não finalizadas)
$sqlAtrasadas = "SELECT f.idfuncao_imagem, f.status, f.prazo, f.observacao, f.funcao_id, fun.nome_funcao, i.imagem_nome
FROM funcao_imagem f
LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
WHERE f.colaborador_id = ? AND DATE(f.prazo) < ? AND f.status = 'Em andamento' ORDER BY f.prazo ASC LIMIT 50";
$stmtAtr = $conn->prepare($sqlAtrasadas);
$stmtAtr->bind_param('is', $idcolaborador, $today);
$stmtAtr->execute();
$resAtr = $stmtAtr->get_result();
$response['tarefasAtrasadas'] = $resAtr ? $resAtr->fetch_all(MYSQLI_ASSOC) : [];
$stmtAtr->close();

// 3) Tarefas próximas (prazo > hoje and <= next7)
$sqlProximas = "SELECT f.idfuncao_imagem, f.status, f.prazo, f.observacao, f.funcao_id, fun.nome_funcao, i.imagem_nome
FROM funcao_imagem f
LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
WHERE f.colaborador_id = ? AND DATE(f.prazo) > ? AND DATE(f.prazo) <= ? ORDER BY f.prazo ASC LIMIT 50";
$stmtProx = $conn->prepare($sqlProximas);
$stmtProx->bind_param('iss', $idcolaborador, $today, $next7);
$stmtProx->execute();
$resProx = $stmtProx->get_result();
$response['tarefasProximas'] = $resProx ? $resProx->fetch_all(MYSQLI_ASSOC) : [];
$stmtProx->close();

// 4) Últimos ajustes (status contendo 'Ajust' ou 'Aprovado com ajustes' ou status = 'Ajuste')
$sqlAjustes = "SELECT 
    fi.idfuncao_imagem AS taskId,
    i.imagem_nome AS taskTitle,
    fi.status AS type,
    c.texto AS excerpt,
    c.data AS date,
    CONCAT(u.nome_colaborador) AS author
FROM funcao_imagem fi
JOIN funcao f ON fi.funcao_id = f.idfuncao
LEFT JOIN historico_aprovacoes_imagens h 
    ON h.funcao_imagem_id = fi.idfuncao_imagem
LEFT JOIN comentarios_imagem c
    ON c.ap_imagem_id = h.id
LEFT JOIN colaborador u
    ON u.idcolaborador = fi.colaborador_id
LEFT JOIN imagens_cliente_obra i
    ON i.idimagens_cliente_obra = fi.imagem_id
WHERE fi.status = 'Ajuste' AND fi.colaborador_id = ?
  AND c.data = (
      SELECT MAX(c2.data)
      FROM comentarios_imagem c2
      JOIN historico_aprovacoes_imagens h2
        ON h2.id = c2.ap_imagem_id
      WHERE h2.funcao_imagem_id = fi.idfuncao_imagem
  )
ORDER BY fi.idfuncao_imagem DESC";
$stmtAjust = $conn->prepare($sqlAjustes);
$stmtAjust->bind_param('i', $idcolaborador);
$stmtAjust->execute();
$resAjust = $stmtAjust->get_result();
$response['ultimosAjustes'] = $resAjust ? $resAjust->fetch_all(MYSQLI_ASSOC) : [];
$stmtAjust->close();

echo json_encode($response, JSON_UNESCAPED_UNICODE);

$conn->close();

?>
