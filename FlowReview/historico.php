<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/approval_media_schema.php';

function ensureConcluidoColsHistorico(mysqli $conn): bool
{
    static $checked = false;
    if ($checked) return true;
    $checked = true;
    $res = $conn->query(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comentarios_imagem' AND COLUMN_NAME = 'concluido' LIMIT 1"
    );
    if (!$res || $res->num_rows === 0) {
        @$conn->query("ALTER TABLE comentarios_imagem ADD COLUMN concluido TINYINT(1) NOT NULL DEFAULT 0");
        @$conn->query("ALTER TABLE comentarios_imagem ADD COLUMN concluido_por INT NULL");
        @$conn->query("ALTER TABLE comentarios_imagem ADD COLUMN concluido_em DATETIME NULL");
        return false;
    }
    return true;
}

if (isset($conn) && method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

if (!isset($conn) || (isset($conn->connect_error) && $conn->connect_error)) {
    die("Falha na conexao: " . ($conn->connect_error ?? 'conexao indisponivel'));
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    ensureConcluidoColsHistorico($conn);
    fr_approval_media_ensure_schema($conn);

    $idFuncaoSelecionada = (int)($_GET['ajid'] ?? 0);
    $tipoTarefa = strtolower((string)($_GET['tipo_tarefa'] ?? 'imagem'));
    $isAnimacao = $tipoTarefa === 'animacao' || (isset($_GET['is_animacao']) && (int)$_GET['is_animacao'] === 1);

    if ($isAnimacao) {
        $sqlHistorico = "SELECT
            h.*,
            h.responsavel,
            c.nome_colaborador AS colaborador_nome,
            fa.colaborador_id,
            c2.nome_colaborador AS responsavel_nome,
            i.imagem_nome,
            fun.nome_funcao,
            s.nome_status,
            i.idimagens_cliente_obra AS imagem_id,
            o.nomenclatura,
            a.idanimacao AS animacao_id,
            a.tipo_animacao,
            fa.id AS funcao_animacao_id,
            fa.id AS idfuncao_imagem,
            'animacao' AS tipo_tarefa
        FROM historico_aprovacoes h
        LEFT JOIN funcao_animacao fa ON fa.id = h.funcao_animacao_id
        LEFT JOIN colaborador c ON fa.colaborador_id = c.idcolaborador
        LEFT JOIN colaborador c2 ON h.responsavel = c2.idcolaborador
        LEFT JOIN funcao fun ON fun.idfuncao = fa.funcao_id
        LEFT JOIN animacao a ON a.idanimacao = fa.animacao_id
        LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = a.imagem_id
        LEFT JOIN status_imagem s ON i.status_id = s.idstatus
        LEFT JOIN obra o ON o.idobra = i.obra_id
        WHERE h.funcao_animacao_id = $idFuncaoSelecionada
        ORDER BY h.data_aprovacao DESC";
    } else {
        $sqlHistorico = "SELECT
            h.*,
            h.responsavel,
            c.nome_colaborador AS colaborador_nome,
            f.colaborador_id,
            c2.nome_colaborador AS responsavel_nome,
            i.imagem_nome,
            fun.nome_funcao,
            s.nome_status,
            i.idimagens_cliente_obra AS imagem_id,
            o.nomenclatura,
            NULL AS funcao_animacao_id,
            'imagem' AS tipo_tarefa
        FROM historico_aprovacoes h
        LEFT JOIN funcao_imagem f ON f.idfuncao_imagem = h.funcao_imagem_id
        LEFT JOIN colaborador c ON f.colaborador_id = c.idcolaborador
        LEFT JOIN colaborador c2 ON h.responsavel = c2.idcolaborador
        LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
        LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
        LEFT JOIN status_imagem s ON i.status_id = s.idstatus
        LEFT JOIN obra o ON o.idobra = i.obra_id
        WHERE h.funcao_imagem_id = $idFuncaoSelecionada
        ORDER BY h.data_aprovacao DESC";
    }

    $resultHistorico = $conn->query($sqlHistorico);
    $historico = [];
    if ($resultHistorico && $resultHistorico->num_rows > 0) {
        while ($row = $resultHistorico->fetch_assoc()) {
            $historico[] = $row;
        }
    }

    if ($isAnimacao && empty($historico)) {
        $sqlAnimacaoContexto = "SELECT
            NULL AS id,
            NULL AS funcao_imagem_id,
            fa.id AS funcao_animacao_id,
            fa.status AS status_novo,
            fa.status AS status,
            NULL AS status_anterior,
            NULL AS data_aprovacao,
            NULL AS responsavel,
            c.nome_colaborador AS colaborador_nome,
            fa.colaborador_id,
            NULL AS responsavel_nome,
            i.imagem_nome,
            fun.nome_funcao,
            s.nome_status,
            i.idimagens_cliente_obra AS imagem_id,
            o.nomenclatura,
            a.idanimacao AS animacao_id,
            a.tipo_animacao,
            fa.id AS idfuncao_imagem,
            'animacao' AS tipo_tarefa
        FROM funcao_animacao fa
        LEFT JOIN colaborador c ON fa.colaborador_id = c.idcolaborador
        LEFT JOIN funcao fun ON fun.idfuncao = fa.funcao_id
        LEFT JOIN animacao a ON a.idanimacao = fa.animacao_id
        LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = a.imagem_id
        LEFT JOIN status_imagem s ON i.status_id = s.idstatus
        LEFT JOIN obra o ON o.idobra = i.obra_id
        WHERE fa.id = $idFuncaoSelecionada
        LIMIT 1";
        $ctxRes = $conn->query($sqlAnimacaoContexto);
        if ($ctxRes && $ctxRes->num_rows > 0) {
            $historico[] = $ctxRes->fetch_assoc();
        }
    }

    $pdf = null;
    if (!$isAnimacao) {
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
                $statusUlt = function_exists('mb_strtolower')
                    ? mb_strtolower((string)($info['status_ultimo'] ?? ''), 'UTF-8')
                    : strtolower((string)($info['status_ultimo'] ?? ''));
                $statusAtual = function_exists('mb_strtolower')
                    ? mb_strtolower((string)($info['status_atual'] ?? ''), 'UTF-8')
                    : strtolower((string)($info['status_atual'] ?? ''));

                $funcaoId = isset($info['funcao_id']) ? intval($info['funcao_id']) : 0;
                $isCadernoOuFiltro = in_array($funcaoId, [1, 8], true);
                $possibleStatuses = [
                    'em aprovacao',
                    'em aprovação',
                    'ajuste',
                    'ajustes',
                    'aprovado com ajustes',
                    'aprovado com ajuste',
                    'aprovado_com_ajustes',
                    'aprovado_com_ajuste'
                ];
                $isEmAprovacao = in_array(trim($statusUlt), $possibleStatuses, true) || in_array(trim($statusAtual), $possibleStatuses, true);

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
            $pdf = null;
        }
    }

    if ($isAnimacao) {
        $sqlImagens = "SELECT
            hi.*,
            COALESCE(hi.media_tipo, 'imagem') AS media_tipo,
            hi.mime_type,
            hi.tamanho,
            hi.duracao_ms,
            hi.poster_path,
            s.idstatus AS status_id_envio,
            s.nome_status AS nome_status_envio,
            COUNT(ci.id) AS comment_count,
            CASE WHEN COUNT(ci.id) > 0 THEN true ELSE false END AS has_comments,
            SUM(COALESCE(ci.concluido, 0)) AS concluidos_count,
            (COUNT(ci.id) - SUM(COALESCE(ci.concluido, 0))) AS pending_count,
            0 AS angulo_liberada,
            0 AS angulo_sugerida,
            '' AS angulo_motivo
        FROM historico_aprovacoes_imagens hi
        LEFT JOIN comentarios_imagem ci ON ci.ap_imagem_id = hi.id
        LEFT JOIN funcao_animacao fa ON fa.id = hi.funcao_animacao_id
        LEFT JOIN animacao a ON a.idanimacao = fa.animacao_id
        LEFT JOIN imagens_cliente_obra fimg ON fimg.idimagens_cliente_obra = a.imagem_id
        LEFT JOIN status_imagem s ON fimg.status_id = s.idstatus
        WHERE hi.funcao_animacao_id = $idFuncaoSelecionada
        GROUP BY hi.id
        ORDER BY has_comments DESC, comment_count DESC, hi.data_envio DESC";
    } else {
        $sqlImagens = "SELECT
            hi.*,
            COALESCE(hi.media_tipo, 'imagem') AS media_tipo,
            hi.mime_type,
            hi.tamanho,
            hi.duracao_ms,
            hi.poster_path,
            COALESCE(
                (
                    SELECT hs.idstatus
                    FROM historico_imagens him
                    INNER JOIN status_imagem hs ON hs.idstatus = him.status_id
                    WHERE him.imagem_id = fimg.imagem_id
                      AND him.data_movimento <= hi.data_envio
                    ORDER BY him.data_movimento DESC, him.idhistorico DESC
                    LIMIT 1
                ),
                (
                    SELECT hs2.idstatus
                    FROM historico_imagens him2
                    INNER JOIN status_imagem hs2 ON hs2.idstatus = him2.status_id
                    WHERE him2.imagem_id = fimg.imagem_id
                    ORDER BY him2.data_movimento DESC, him2.idhistorico DESC
                    LIMIT 1
                )
            ) AS status_id_envio,
            COALESCE(
                (
                    SELECT hs.nome_status
                    FROM historico_imagens him
                    INNER JOIN status_imagem hs ON hs.idstatus = him.status_id
                    WHERE him.imagem_id = fimg.imagem_id
                      AND him.data_movimento <= hi.data_envio
                    ORDER BY him.data_movimento DESC, him.idhistorico DESC
                    LIMIT 1
                ),
                (
                    SELECT hs2.nome_status
                    FROM historico_imagens him2
                    INNER JOIN status_imagem hs2 ON hs2.idstatus = him2.status_id
                    WHERE him2.imagem_id = fimg.imagem_id
                    ORDER BY him2.data_movimento DESC, him2.idhistorico DESC
                    LIMIT 1
                )
            ) AS nome_status_envio,
            COUNT(ci.id) AS comment_count,
            CASE WHEN COUNT(ci.id) > 0 THEN true ELSE false END AS has_comments,
            SUM(COALESCE(ci.concluido, 0)) AS concluidos_count,
            (COUNT(ci.id) - SUM(COALESCE(ci.concluido, 0))) AS pending_count,
            MAX(COALESCE(ai.liberada, 0)) AS angulo_liberada,
            MAX(COALESCE(ai.sugerida, 0)) AS angulo_sugerida,
            MAX(COALESCE(ai.motivo_sugerida, '')) AS angulo_motivo
        FROM historico_aprovacoes_imagens hi
        LEFT JOIN comentarios_imagem ci ON ci.ap_imagem_id = hi.id
        LEFT JOIN funcao_imagem fimg ON fimg.idfuncao_imagem = hi.funcao_imagem_id
        LEFT JOIN angulos_imagens ai ON ai.historico_id = hi.id AND ai.imagem_id = fimg.imagem_id
        WHERE hi.funcao_imagem_id = $idFuncaoSelecionada
        GROUP BY hi.id
        ORDER BY angulo_liberada DESC, has_comments DESC, comment_count DESC";
    }

    $resultImagens = $conn->query($sqlImagens);
    $imagens = [];
    if ($resultImagens && $resultImagens->num_rows > 0) {
        while ($row = $resultImagens->fetch_assoc()) {
            $imagens[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'historico' => $historico,
        'imagens' => $imagens,
        'pdf' => $pdf
    ]);
}

$conn->close();
