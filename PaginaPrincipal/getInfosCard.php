<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    // --- Parâmetro ---
    $idImagemSelecionada = (int) $_GET['imagem_id']; // segurança

    // ==========================================================
    // 1) Funções da imagem (mantém sua lógica atual)
    // ==========================================================
    $sqlFuncoes = "SELECT 
            img.clima, 
            img.imagem_nome,
            f.nome_funcao, 
            col.idcolaborador AS colaborador_id, 
            col.nome_colaborador, 
            fi.prazo, 
            fi.status,
            fi.observacao,
            fi.idfuncao_imagem AS id,
            fp.nome_pdf,
            (SELECT c.nome_colaborador 
             FROM historico_aprovacoes h 
             JOIN colaborador c ON h.responsavel = c.idcolaborador 
             WHERE h.funcao_imagem_id = fi.idfuncao_imagem 
             ORDER BY h.id DESC 
             LIMIT 1) AS responsavel_aprovacao,
            (SELECT DISTINCT GROUP_CONCAT(sh.justificativa SEPARATOR ',') 
             FROM status_hold sh 
             WHERE sh.imagem_id = $idImagemSelecionada) AS justificativa
        FROM imagens_cliente_obra img
        LEFT JOIN funcao_imagem fi ON img.idimagens_cliente_obra = fi.imagem_id
        LEFT JOIN colaborador col ON fi.colaborador_id = col.idcolaborador
        LEFT JOIN funcao f ON fi.funcao_id = f.idfuncao
        LEFT JOIN funcao_imagem_pdf fp ON fi.idfuncao_imagem = fp.funcao_imagem_id
        WHERE img.idimagens_cliente_obra = $idImagemSelecionada
    ";
    $resultFuncoes = $conn->query($sqlFuncoes);
    $funcoes = [];
    if ($resultFuncoes && $resultFuncoes->num_rows > 0) {
        while ($row = $resultFuncoes->fetch_assoc()) {
            $funcoes[] = $row;
        }
    }

    // ==========================================================
    // 2) Status da imagem
    // ==========================================================
    $sqlStatusImagem = "SELECT ico.status_id, s.nome_status
        FROM imagens_cliente_obra ico
        LEFT JOIN status_imagem s ON s.idstatus = ico.status_id
        WHERE ico.idimagens_cliente_obra = $idImagemSelecionada
    ";
    $statusImagem = null;
    if ($resultStatus = $conn->query($sqlStatusImagem)) {
        $statusImagem = $resultStatus->fetch_assoc();
    }

    // ==========================================================
    // 3) Colaboradores envolvidos em QUALQUER função da imagem
    // ==========================================================
$sqlColaboradores = "SELECT 
        c.idcolaborador, 
        c.nome_colaborador,
    GROUP_CONCAT(f.nome_funcao SEPARATOR ', ') AS funcoes
    FROM funcao_imagem fi
    JOIN colaborador c ON fi.colaborador_id = c.idcolaborador
    JOIN funcao f ON fi.funcao_id = f.idfuncao
    WHERE fi.imagem_id = $idImagemSelecionada
    GROUP BY c.idcolaborador, c.nome_colaborador
";

    $colaboradores = [];
    if ($resultColab = $conn->query($sqlColaboradores)) {
        while ($row = $resultColab->fetch_assoc()) {
            $colaboradores[] = $row;
        }
    }

    // ==========================================================
    // 4) Log de alterações da função selecionada
    // ==========================================================

    $idFuncaoImagem = isset($_GET['idfuncao']) ? (int) $_GET['idfuncao'] : 0;
    $logAlteracoes = [];
    if ($idFuncaoImagem > 0) {
        $sqlLog = "SELECT la.idlog, la.funcao_imagem_id, la.status_anterior, la.status_novo, la.data,
                   c.nome_colaborador AS responsavel
            FROM log_alteracoes la
            LEFT JOIN colaborador c ON la.colaborador_id = c.idcolaborador
            WHERE la.funcao_imagem_id = $idFuncaoImagem
            ORDER BY la.data DESC
        ";
        if ($resultLog = $conn->query($sqlLog)) {
            while ($row = $resultLog->fetch_assoc()) {
                $logAlteracoes[] = $row;
            }
        }
    }

    // ==========================================================
    // Resposta final
    // ==========================================================
    echo json_encode([
        "funcoes"       => $funcoes,
        "status_imagem" => $statusImagem,
        "colaboradores" => $colaboradores,
        "log_alteracoes" => $logAlteracoes
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["error" => "Método de requisição inválido."]);
}

$conn->close();
