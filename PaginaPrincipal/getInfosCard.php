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

    // defaults for computed times (will be overwritten if logs exist)
    $tempo_total_producao = [
        'seconds' => 0,
        'hours' => 0,
        'readable' => '0s'
    ];
    $tempo_pessoal_producao = [
        'seconds' => 0,
        'hours' => 0,
        'readable' => '0s'
    ];

    $logAlteracoes = [];
    if ($idFuncaoImagem > 0) {
        // Fetch logs in DESC order for the UI display (most recent first)
        $sqlLog = "SELECT la.idlog, la.funcao_imagem_id, la.status_anterior, la.status_novo, la.data,
                   la.colaborador_id, c.nome_colaborador AS responsavel
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

        // --- Additional query (ASC) to compute time intervals between consecutive logs ---
        $logAsc = [];
        $sqlLogAsc = "SELECT la.idlog, la.funcao_imagem_id, la.status_anterior, la.status_novo, la.data,
                          la.colaborador_id
            FROM log_alteracoes la
            WHERE la.funcao_imagem_id = $idFuncaoImagem
            ORDER BY la.data ASC
        ";
        if ($resAsc = $conn->query($sqlLogAsc)) {
            while ($r = $resAsc->fetch_assoc()) {
                $logAsc[] = $r;
            }
        }

        // --- build durations map per log id (time until next log) ---
        $durations_by_log = []; // idlog => seconds
        $countDur = count($logAsc);
        if ($countDur >= 2) {
            for ($i = 0; $i < $countDur - 1; $i++) {
                $current = $logAsc[$i];
                $next = $logAsc[$i + 1];
                $startTs = (new DateTime($current['data']))->getTimestamp();
                $endTs = (new DateTime($next['data']))->getTimestamp();
                $diff = $endTs - $startTs;
                if ($diff < 0) $diff = 0;
                $durations_by_log[(int)$current['idlog']] = $diff;
            }
            // last log has no next event => null (or 0). Keep as null to indicate open interval.
            $lastLog = $logAsc[$countDur - 1];
            $durations_by_log[(int)$lastLog['idlog']] = null;
        }

        // Compute total production time and personal production time
        $tempo_total_seg = 0;
        $tempo_pessoal_seg = 0;

        // determine assigned collaborator for this funcao_imagem (if available)
        $assigned_colab_id = null;
        if (!empty($funcoes) && isset($funcoes[0]['colaborador_id'])) {
            $assigned_colab_id = (int)$funcoes[0]['colaborador_id'];
        }

        // active states considered as 'production' for personal time
        $active_states = ['Em andamento', 'Ajuste'];

        $countAsc = count($logAsc);
        if ($countAsc >= 2) {
            for ($i = 0; $i < $countAsc - 1; $i++) {
                $start = new DateTime($logAsc[$i]['data']);
                $end = new DateTime($logAsc[$i + 1]['data']);
                $interval_sec = $end->getTimestamp() - $start->getTimestamp();
                if ($interval_sec < 0) $interval_sec = 0;
                $tempo_total_seg += $interval_sec;

                $status_novo = isset($logAsc[$i]['status_novo']) ? $logAsc[$i]['status_novo'] : null;
                $log_colab_id = isset($logAsc[$i]['colaborador_id']) ? (int)$logAsc[$i]['colaborador_id'] : null;

                // Count as personal production if the interval starts with an active state
                // and the actor matches the assigned collaborator (if known).
                if (in_array($status_novo, $active_states, true)) {
                    if ($assigned_colab_id === null || $log_colab_id === $assigned_colab_id) {
                        $tempo_pessoal_seg += $interval_sec;
                    }
                }
            }
        }

        // helper readable format
        function secs_to_readable($s)
        {
            $days = floor($s / 86400);
            $s -= $days * 86400;
            $hours = floor($s / 3600);
            $s -= $hours * 3600;
            $minutes = floor($s / 60);
            $seconds = $s - $minutes * 60;
            $parts = [];
            if ($days > 0) $parts[] = $days . 'd';
            if ($hours > 0) $parts[] = $hours . 'h';
            if ($minutes > 0) $parts[] = $minutes . 'm';
            if ($seconds > 0) $parts[] = $seconds . 's';
            return implode(' ', $parts) ?: '0s';
        }

        // attach computed values to a variable for later JSON response
        $tempo_total_producao = [
            'seconds' => $tempo_total_seg,
            'hours' => round($tempo_total_seg / 3600, 2),
            'readable' => secs_to_readable($tempo_total_seg)
        ];
        $tempo_pessoal_producao = [
            'seconds' => $tempo_pessoal_seg,
            'hours' => round($tempo_pessoal_seg / 3600, 2),
            'readable' => secs_to_readable($tempo_pessoal_seg)
        ];

        // --- enrich logAlteracoes (which was fetched DESC) with duration fields ---
        if (!empty($logAlteracoes)) {
            foreach ($logAlteracoes as $idx => $entry) {
                $idlog = isset($entry['idlog']) ? (int)$entry['idlog'] : null;
                if ($idlog !== null && array_key_exists($idlog, $durations_by_log)) {
                    $secs = $durations_by_log[$idlog];
                    if ($secs === null) {
                        $logAlteracoes[$idx]['tempo_segundos'] = null;
                        $logAlteracoes[$idx]['tempo_horas'] = null;
                        $logAlteracoes[$idx]['tempo_readable'] = null;
                    } else {
                        $logAlteracoes[$idx]['tempo_segundos'] = $secs;
                        $logAlteracoes[$idx]['tempo_horas'] = round($secs / 3600, 4);
                        $logAlteracoes[$idx]['tempo_readable'] = secs_to_readable($secs);
                    }
                } else {
                    // if we couldn't compute, keep nulls
                    $logAlteracoes[$idx]['tempo_segundos'] = null;
                    $logAlteracoes[$idx]['tempo_horas'] = null;
                    $logAlteracoes[$idx]['tempo_readable'] = null;
                }
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
            WHERE fi.imagem_id = " . $idImagemSelecionada . " AND al.status IN ('atualizado', 'concluido')
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
        "tempo_total_producao" => $tempo_total_producao,
        "tempo_pessoal_producao" => $tempo_pessoal_producao,
        "arquivos_imagem" => $arquivos_imagem,
        "arquivos_tipo" => $arquivos_tipo,
        "arquivos_anteriores" => $arquivos_anteriores,
        "notificacoes"  => $notificacoes

    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["error" => "Método de requisição inválido."]);
}

$conn->close();
