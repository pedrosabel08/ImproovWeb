<?php

header("Access-Control-Allow-Origin: *"); // Allows all domains
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Allow specific methods
header("Access-Control-Allow-Headers: Content-Type");

include_once __DIR__ . '/../conexao.php';

// garante charset caso conexao.php não tenha setado
if (isset($conn) && method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

// Verificar a conexão
if (!isset($conn) || (isset($conn->connect_error) && $conn->connect_error)) {
    die("Falha na conexão: " . ($conn->connect_error ?? 'conexão indisponível'));
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $idFuncaoSelecionada = $_GET['ajid'];

    // Proteção contra SQL Injection
    $idFuncaoSelecionada = $conn->real_escape_string($idFuncaoSelecionada);

    // Query para buscar o histórico
    $sqlHistorico = "SELECT 
        h.*, 
        h.responsavel, 
        c.nome_colaborador AS colaborador_nome, 
        c2.nome_colaborador AS responsavel_nome,
        i.imagem_nome, 
        fun.nome_funcao,
        s.nome_status,
        i.idimagens_cliente_obra AS imagem_id
    FROM historico_aprovacoes h
    LEFT JOIN colaborador c ON h.colaborador_id = c.idcolaborador
    LEFT JOIN colaborador c2 ON h.responsavel = c2.idcolaborador
    LEFT JOIN funcao_imagem f ON f.idfuncao_imagem = h.funcao_imagem_id
    LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
    LEFT JOIN status_imagem s ON i.status_id = s.idstatus
    WHERE h.funcao_imagem_id = $idFuncaoSelecionada";

    $resultHistorico = $conn->query($sqlHistorico);
    $historico = array();
    if ($resultHistorico->num_rows > 0) {
        while ($row = $resultHistorico->fetch_assoc()) {
            $historico[] = $row;
        }
    }

    // Decide when we should prefer showing a PDF instead of JPG.
    // Rule: if funcao_id is 1 (caderno) or 8 (filtro de assets) AND the latest status is "Em aprovação".
    $pdf = null;
    try {
        $sqlInfo = "SELECT 
                f.funcao_id,
                fun.nome_funcao,
                f.idfuncao_imagem,
                f.status AS status_atual,
                (SELECT h2.status_novo
                 FROM historico_aprovacoes h2
                 WHERE h2.funcao_imagem_id = f.idfuncao_imagem
                 ORDER BY h2.data_aprovacao DESC
                 LIMIT 1) AS status_ultimo
            FROM funcao_imagem f
            LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
            WHERE f.idfuncao_imagem = $idFuncaoSelecionada
            LIMIT 1";
        $infoRes = $conn->query($sqlInfo);
        $info = ($infoRes && $infoRes->num_rows > 0) ? $infoRes->fetch_assoc() : null;

        if ($info) {
            $statusUlt = mb_strtolower((string)($info['status_ultimo'] ?? ''), 'UTF-8');
            $statusAtual = mb_strtolower((string)($info['status_atual'] ?? ''), 'UTF-8');

            $funcaoId = isset($info['funcao_id']) ? intval($info['funcao_id']) : 0;
            $isCadernoOuFiltro = in_array($funcaoId, [1, 8], true);
            $isEmAprovacao = ($statusUlt === 'em aprovação' || $statusUlt === 'em aprovacao' || $statusAtual === 'em aprovação' || $statusAtual === 'em aprovacao');
            $funcaoImagemId = isset($info['idfuncao_imagem']) ? intval($info['idfuncao_imagem']) : 0;

            if ($isCadernoOuFiltro && $isEmAprovacao) {
                $sqlPdf = "SELECT id, nome_arquivo, caminho
                           FROM arquivo_log
                           WHERE funcao_imagem_id = $funcaoImagemId
                             AND UPPER(tipo) = 'PDF'
                           ORDER BY id DESC
                           LIMIT 1";
                $pdfRes = $conn->query($sqlPdf);
                if ($pdfRes && $pdfRes->num_rows > 0) {
                    $pdf = $pdfRes->fetch_assoc();
                }
            }
        }
    } catch (Exception $e) {
        // ignore and keep $pdf null
    }

    // Query para buscar imagens associadas
    $sqlImagens = "SELECT 
    hi.*,
    COUNT(ci.id) AS comment_count,
    CASE 
        WHEN COUNT(ci.id) > 0 THEN true
        ELSE false
    END AS has_comments
FROM historico_aprovacoes_imagens hi
LEFT JOIN comentarios_imagem ci ON ci.ap_imagem_id = hi.id
WHERE hi.funcao_imagem_id = $idFuncaoSelecionada
GROUP BY hi.id ORDER BY has_comments DESC, comment_count DESC";

    $resultImagens = $conn->query($sqlImagens);
    $imagens = array();
    if ($resultImagens->num_rows > 0) {
        while ($row = $resultImagens->fetch_assoc()) {
            $imagens[] = $row;
        }
    }

    $response = array(
        'historico' => $historico,
        'imagens' => $imagens,
        'pdf' => $pdf
    );

    header('Content-Type: application/json');
    echo json_encode($response);
}

$conn->close();
