<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include_once __DIR__ . '/../conexao.php';

if (isset($conn) && method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

if (!isset($conn) || (isset($conn->connect_error) && $conn->connect_error)) {
    die("Falha na conexão: " . ($conn->connect_error ?? 'conexão indisponível'));
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $idFuncaoSelecionada = isset($_GET['ajid']) ? (int) $_GET['ajid'] : 0;

    if ($idFuncaoSelecionada <= 0) {
        echo json_encode(['erro' => 'Parâmetro ajid inválido']);
        exit;
    }

    // Primeiro buscamos os dados da funcao_imagem selecionada (inclui status / obra)
    $sqlBase = "SELECT 
            f.idfuncao_imagem,
            f.funcao_id,
            fun.nome_funcao,
            f.status,
            f.imagem_id,
            i.imagem_nome,
            i.status_id,
            s.nome_status,
            i.obra_id
        FROM funcao_imagem f
        LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
        LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
        LEFT JOIN status_imagem s ON i.status_id = s.idstatus
        WHERE f.idfuncao_imagem = ?";

    $stmtBase = $conn->prepare($sqlBase);
    $stmtBase->bind_param("i", $idFuncaoSelecionada);
    $stmtBase->execute();
    $resultBase = $stmtBase->get_result();
    $baseRow = $resultBase->fetch_assoc();
    $stmtBase->close();

    if (!$baseRow) {
        echo json_encode(['erro' => 'Função/imagem não encontrada']);
        exit;
    }

    $statusId = (int) $baseRow['status_id'];
    $obraId   = (int) $baseRow['obra_id'];

    // Histórico apenas da funcao_imagem selecionada (igual ao historico.php)
    $sqlHistorico = "SELECT 
        h.*, 
        h.responsavel, 
        c.nome_colaborador AS colaborador_nome, 
        c2.nome_colaborador AS responsavel_nome,
        i.imagem_nome, 
        fun.nome_funcao,
        s.nome_status,
        i.idimagens_cliente_obra AS imagem_id,
        hi.id AS historico_imagem_id
    FROM historico_aprovacoes h
    JOIN colaborador c ON h.colaborador_id = c.idcolaborador
    JOIN colaborador c2 ON h.responsavel = c2.idcolaborador
    LEFT JOIN funcao_imagem f ON f.idfuncao_imagem = h.funcao_imagem_id
    LEFT JOIN historico_aprovacoes_imagens hi ON f.idfuncao_imagem = hi.funcao_imagem_id
    LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
    LEFT JOIN status_imagem s ON i.status_id = s.idstatus
    WHERE h.funcao_imagem_id = ?";

    $stmtHist = $conn->prepare($sqlHistorico);
    $stmtHist->bind_param("i", $idFuncaoSelecionada);
    $stmtHist->execute();
    $resultHistorico = $stmtHist->get_result();
    $historico = [];
    while ($row = $resultHistorico->fetch_assoc()) {
        $historico[] = $row;
    }
    $stmtHist->close();

    // Agora buscamos TODAS as imagens de pós-produção (funcao_id=5) da mesma obra e MESMO status
    // isso permitirá preencher o image_wrapper com todas as imagens daquele status
    $sqlImagensStatus = "SELECT 
            hi.*,
            COUNT(ci.id) AS comment_count,
            CASE WHEN COUNT(ci.id) > 0 THEN TRUE ELSE FALSE END AS has_comments,
            i.imagem_nome
        FROM historico_aprovacoes_imagens hi
        JOIN funcao_imagem f ON f.idfuncao_imagem = hi.funcao_imagem_id
        JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
        WHERE f.funcao_id = 5
          AND i.obra_id = ?
          AND i.status_id = ?
        LEFT JOIN comentarios_imagem ci ON ci.ap_imagem_id = hi.id
        GROUP BY hi.id
        ORDER BY hi.indice_envio DESC, hi.data_envio DESC";

    // Ajuste de sintaxe SQL: precisamos colocar o LEFT JOIN antes do WHERE
    $sqlImagensStatus = "SELECT 
            hi.*,
            COUNT(ci.id) AS comment_count,
            CASE WHEN COUNT(ci.id) > 0 THEN TRUE ELSE FALSE END AS has_comments,
            i.imagem_nome
        FROM historico_aprovacoes_imagens hi
        JOIN funcao_imagem f ON f.idfuncao_imagem = hi.funcao_imagem_id
        JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
        LEFT JOIN comentarios_imagem ci ON ci.ap_imagem_id = hi.id
        WHERE f.funcao_id = 5
          AND i.obra_id = ?
          AND i.status_id = ?
        GROUP BY hi.id
        ORDER BY hi.indice_envio DESC, hi.data_envio DESC";

    $stmtImgs = $conn->prepare($sqlImagensStatus);
    $stmtImgs->bind_param("ii", $obraId, $statusId);
    $stmtImgs->execute();
    $resultImagens = $stmtImgs->get_result();
    $imagens = [];
    while ($row = $resultImagens->fetch_assoc()) {
        $imagens[] = $row;
    }
    $stmtImgs->close();

    $response = [
        'historico' => $historico,
        'imagens'   => $imagens,
        'status'    => $baseRow['nome_status'],
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
}

$conn->close();
