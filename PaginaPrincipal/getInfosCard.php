<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    // --- Parâmetro ---
    $idImagemSelecionada = (int) $_GET['imagem_id']; // segurança
    $idFuncaoImagem = isset($_GET['idfuncao']) ? (int) $_GET['idfuncao'] : 0;

    // ==========================================================
    // 1) Funções da imagem (mantém sua lógica atual)
    // ==========================================================
    $sqlFuncoes = "SELECT 
            img.clima, 
            img.imagem_nome,
            img.idimagens_cliente_obra AS idimagem,
            f.nome_funcao, 
            col.idcolaborador AS colaborador_id, 
            col.nome_colaborador, 
            fi.prazo, 
            fi.status,
            fi.observacao,
            fi.idfuncao_imagem AS id
        FROM imagens_cliente_obra img
        LEFT JOIN funcao_imagem fi ON img.idimagens_cliente_obra = fi.imagem_id
        LEFT JOIN colaborador col ON fi.colaborador_id = col.idcolaborador
        LEFT JOIN funcao f ON fi.funcao_id = f.idfuncao
        WHERE fi.idfuncao_imagem = $idFuncaoImagem
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
    // 5) Arquivos relacionados
    // - arquivos_imagem: arquivos vinculados diretamente à imagem (imagem_id)
    // - arquivos_tipo: arquivos vinculados ao tipo de imagem (tipo_imagem_id)
    // Retornamos as colunas: obra_id, imagem_id, tipo_imagem_id, nome_interno, caminho, tipo, categoria_id, recebido_em
    // ==========================================================
    $arquivos_imagem = [];
    $arquivos_tipo = [];

    // fetch tipo_imagem (name) from imagens_cliente_obra
    $tipoImagemName = null;
    $sqlTipo = "SELECT tipo_imagem, obra_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = " . $idImagemSelecionada . " LIMIT 1";
    if ($resTipo = $conn->query($sqlTipo)) {
        if ($rowTipo = $resTipo->fetch_assoc()) {
            // tipo_imagem in imagens_cliente_obra is a varchar (name). Keep as string.
            $tipoImagemName = isset($rowTipo['tipo_imagem']) ? $rowTipo['tipo_imagem'] : null;
            $obraIdFromImage = isset($rowTipo['obra_id']) ? (int)$rowTipo['obra_id'] : null;
        }
    }

    // Query arquivos directly linked to the image
    $sqlArquivosImg = "SELECT a.obra_id, a.imagem_id, a.tipo_imagem_id, a.nome_interno, a.caminho, a.tipo, a.categoria_id, a.recebido_em, a.status,
        c.nome_categoria AS categoria_nome, a.descricao, a.sufixo
        FROM arquivos a
        LEFT JOIN categorias c ON c.idcategoria = a.categoria_id
        WHERE a.status = 'atualizado' AND a.imagem_id = " . $idImagemSelecionada . " ORDER BY a.recebido_em DESC";
    if ($resArquivosImg = $conn->query($sqlArquivosImg)) {
        while ($row = $resArquivosImg->fetch_assoc()) {
            $arquivos_imagem[] = $row;
        }
    }

    // Query arquivos linked to the tipo_imagem (if available)
    if ($tipoImagemName) {
        // escape the string for SQL
        $tipoEscaped = $conn->real_escape_string($tipoImagemName);

        // The schema can store tipo_imagem as a name (varchar) or as an id referencing table tipo_imagem.
        // To be robust, left-join tipo_imagem and accept rows where either:
        //  - arquivos.tipo_imagem_id equals the name, or
        //  - arquivos.tipo_imagem_id equals the id of a tipo_imagem row whose nome matches the name.

        // Restrict to the same obra (if we have it) so we don't pull files from other obras
        $obraFilter = '';
        if (!empty($obraIdFromImage)) {
            $obraFilter = ' AND a.obra_id = ' . (int)$obraIdFromImage;
        }

        $sqlArquivosTipo = "SELECT a.obra_id, a.imagem_id, a.tipo_imagem_id, a.nome_interno, a.caminho, a.tipo, a.categoria_id, a.recebido_em, a.status,
                                c.nome_categoria AS categoria_nome, a.descricao, a.sufixo
                        FROM arquivos a
                        LEFT JOIN tipo_imagem t ON (t.id_tipo_imagem = a.tipo_imagem_id OR t.nome = a.tipo_imagem_id)
                        LEFT JOIN categorias c ON c.idcategoria = a.categoria_id
                        WHERE (a.tipo_imagem_id = '" . $tipoEscaped . "' OR t.nome = '" . $tipoEscaped . "')
                            AND (a.imagem_id IS NULL OR a.imagem_id = 0)" . $obraFilter . " AND a.status = 'atualizado'
                        ORDER BY a.recebido_em DESC";

        if ($resArquivosTipo = $conn->query($sqlArquivosTipo)) {
            while ($row = $resArquivosTipo->fetch_assoc()) {
                $arquivos_tipo[] = $row;
            }
        }

        // ==========================================================
        // 6) Arquivos de tarefas anteriores (arquivo_log)
        // Recupera registros de arquivo_log associados a funções desta imagem
        // ==========================================================
        $arquivos_anteriores = [];
        $sqlArquivosAnteriores = "SELECT al.id, al.funcao_imagem_id, al.caminho, al.nome_arquivo, al.tamanho, al.tipo, al.colaborador_id, al.status, al.criado_em,
                fi.funcao_id, f.nome_funcao
            FROM arquivo_log al
            LEFT JOIN funcao_imagem fi ON al.funcao_imagem_id = fi.idfuncao_imagem
            LEFT JOIN funcao f ON fi.funcao_id = f.idfuncao
            WHERE fi.imagem_id = " . $idImagemSelecionada . " AND al.status = 'atualizado'
            ORDER BY al.criado_em DESC";

        if ($resAnteriores = $conn->query($sqlArquivosAnteriores)) {
            while ($row = $resAnteriores->fetch_assoc()) {
                $arquivos_anteriores[] = $row;
            }
        }

        // ==========================================================
        // 7) Notificações da funcao_imagem
        // ==========================================================
        $notificacoes = [];
        $sqlNotificacoes = "SELECT n.id, n.funcao_imagem_id, n.mensagem, n.data
            FROM notificacoes n
            LEFT JOIN funcao_imagem fi ON n.funcao_imagem_id = fi.idfuncao_imagem
            WHERE fi.idfuncao_imagem = " . $idFuncaoImagem . " AND n.lida = 0
            ORDER BY n.data DESC";

        if ($resNotificacoes = $conn->query($sqlNotificacoes)) {
            while ($row = $resNotificacoes->fetch_assoc()) {
                $notificacoes[] = $row;
            }
        }
    }

    // ====================================
    // Resposta final
    // ==========================================================
    echo json_encode([
        "funcoes"       => $funcoes,
        "status_imagem" => $statusImagem,
        "colaboradores" => $colaboradores,
        "log_alteracoes" => $logAlteracoes,
        "arquivos_imagem" => $arquivos_imagem,
        "arquivos_tipo" => $arquivos_tipo,
        "arquivos_anteriores" => $arquivos_anteriores,
        "notificacoes"  => $notificacoes

    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["error" => "Método de requisição inválido."]);
}

$conn->close();
