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
    $isAnimacao = isset($_GET['is_animacao']) && (int) $_GET['is_animacao'] === 1;

    // ==========================================================
    // 1) Núcleo do card (funcao_imagem ou funcao_animacao)
    // ==========================================================
    if ($isAnimacao) {
        $sqlFuncoes = "SELECT
                img.clima,
                img.imagem_nome,
                img.idimagens_cliente_obra AS idimagem,
                f.nome_funcao,
                col.idcolaborador AS colaborador_id,
                col.nome_colaborador,
                fa.prazo,
                fa.status,
                fa.observacao,
                fa.id AS id,
                a.idanimacao AS animacao_id,
                a.tipo_animacao,
                a.duracao,
                CONCAT('Animação - ', UCASE(SUBSTRING(a.tipo_animacao, 1, 1)), LOWER(SUBSTRING(a.tipo_animacao, 2))) AS nome_animacao,
                1 AS is_animacao
            FROM funcao_animacao fa
            JOIN animacao a ON a.idanimacao = fa.animacao_id
            JOIN imagens_cliente_obra img ON img.idimagens_cliente_obra = a.imagem_id
            LEFT JOIN colaborador col ON fa.colaborador_id = col.idcolaborador
            LEFT JOIN funcao f ON fa.funcao_id = f.idfuncao
            WHERE fa.id = $idFuncaoImagem
            LIMIT 1";
    } else {
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
                fi.idfuncao_imagem AS id,
                0 AS is_animacao
            FROM imagens_cliente_obra img
            LEFT JOIN funcao_imagem fi ON img.idimagens_cliente_obra = fi.imagem_id
            LEFT JOIN colaborador col ON fi.colaborador_id = col.idcolaborador
            LEFT JOIN funcao f ON fi.funcao_id = f.idfuncao
            WHERE fi.idfuncao_imagem = $idFuncaoImagem
            LIMIT 1";
    }
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
    if (!$isAnimacao && $idFuncaoImagem > 0) {
        // Fetch logs in DESC order for the UI display (most recent first)
        // Also fetch the image status name that was active at the time of the log
        $sqlLog = "SELECT la.idlog, la.funcao_imagem_id, COALESCE(la.status_anterior, 'Tarefa criada') AS status_anterior, la.status_novo, la.data,
                   la.colaborador_id, c.nome_colaborador AS responsavel,
                   fi.imagem_id,
                   s_im.nome_status AS imagem_status_at_update
            FROM log_alteracoes la
            LEFT JOIN colaborador c ON la.colaborador_id = c.idcolaborador
            LEFT JOIN funcao_imagem fi ON la.funcao_imagem_id = fi.idfuncao_imagem
            LEFT JOIN historico_imagens hi ON hi.imagem_id = fi.imagem_id
                AND hi.data_movimento = (
                    SELECT MAX(h2.data_movimento) FROM historico_imagens h2
                    WHERE h2.imagem_id = fi.imagem_id AND h2.data_movimento <= la.data
                )
            LEFT JOIN status_imagem s_im ON s_im.idstatus = hi.status_id
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
        $sqlLogAsc = "SELECT la.idlog, la.funcao_imagem_id, COALESCE(la.status_anterior, 'Tarefa criada') AS status_anterior, la.status_novo, la.data,
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
                if ($diff < 0)
                    $diff = 0;
                $durations_by_log[(int) $current['idlog']] = $diff;
            }
            // last log has no next event => null (or 0). Keep as null to indicate open interval.
            $lastLog = $logAsc[$countDur - 1];
            $durations_by_log[(int) $lastLog['idlog']] = null;
        }

        // Compute total production time and personal production time
        $tempo_total_seg = 0;
        $tempo_pessoal_seg = 0;

        // determine assigned collaborator for this funcao_imagem (if available)
        $assigned_colab_id = null;
        if (!empty($funcoes) && isset($funcoes[0]['colaborador_id'])) {
            $assigned_colab_id = (int) $funcoes[0]['colaborador_id'];
        }

        // active states considered as 'production' for personal time
        $active_states = ['Em andamento', 'Ajuste'];

        $countAsc = count($logAsc);
        if ($countAsc >= 2) {
            for ($i = 0; $i < $countAsc - 1; $i++) {
                $start = new DateTime($logAsc[$i]['data']);
                $end = new DateTime($logAsc[$i + 1]['data']);
                $interval_sec = $end->getTimestamp() - $start->getTimestamp();
                if ($interval_sec < 0)
                    $interval_sec = 0;
                $tempo_total_seg += $interval_sec;

                $status_novo = isset($logAsc[$i]['status_novo']) ? $logAsc[$i]['status_novo'] : null;
                $log_colab_id = isset($logAsc[$i]['colaborador_id']) ? (int) $logAsc[$i]['colaborador_id'] : null;

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
            if ($days > 0)
                $parts[] = $days . 'd';
            if ($hours > 0)
                $parts[] = $hours . 'h';
            if ($minutes > 0)
                $parts[] = $minutes . 'm';
            if ($seconds > 0)
                $parts[] = $seconds . 's';
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
                $idlog = isset($entry['idlog']) ? (int) $entry['idlog'] : null;
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
    $obraIdFromImage = null;
    $tipoImagemName = null;
    $sqlTipo = "SELECT tipo_imagem, obra_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = " . $idImagemSelecionada . " LIMIT 1";
    if ($resTipo = $conn->query($sqlTipo)) {
        if ($rowTipo = $resTipo->fetch_assoc()) {
            // tipo_imagem in imagens_cliente_obra is a varchar (name). Keep as string.
            $tipoImagemName = isset($rowTipo['tipo_imagem']) ? $rowTipo['tipo_imagem'] : null;
            $obraIdFromImage = isset($rowTipo['obra_id']) ? (int) $rowTipo['obra_id'] : null;
        }
    }

    // Query arquivos directly linked to the image
    $sqlArquivosImg = "SELECT a.idarquivo, a.obra_id, a.imagem_id, a.tipo_imagem_id, a.nome_interno, a.caminho, a.tipo, a.categoria_id, a.recebido_em, a.status,
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
            $obraFilter = ' AND a.obra_id = ' . (int) $obraIdFromImage;
        }

        $sqlArquivosTipo = "SELECT a.idarquivo, a.obra_id, a.imagem_id, a.tipo_imagem_id, a.nome_interno, a.caminho, a.tipo, a.categoria_id, a.recebido_em, a.status,
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
            INNER JOIN (
                SELECT al2.funcao_imagem_id, MAX(al2.criado_em) AS max_criado_em
                FROM arquivo_log al2
                LEFT JOIN funcao_imagem fi2 ON al2.funcao_imagem_id = fi2.idfuncao_imagem
                WHERE fi2.imagem_id = " . $idImagemSelecionada . " AND al2.status IN ('atualizado', 'concluido')
                GROUP BY al2.funcao_imagem_id
            ) ultimo ON al.funcao_imagem_id = ultimo.funcao_imagem_id AND al.criado_em = ultimo.max_criado_em
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
            FROM notificacoes_gerais n
            LEFT JOIN funcao_imagem fi ON n.funcao_imagem_id = fi.idfuncao_imagem
            WHERE fi.idfuncao_imagem = " . $idFuncaoImagem . " AND n.lida = 0
            ORDER BY n.data DESC";

        if ($resNotificacoes = $conn->query($sqlNotificacoes)) {
            while ($row = $resNotificacoes->fetch_assoc()) {
                $notificacoes[] = $row;
            }
        }
    }

    // ==========================================================
    // 8) Briefing da obra
    // ==========================================================
    $briefing_obra = [];
    if (!empty($obraIdFromImage)) {
        $obraIdBriefing = (int) $obraIdFromImage;
        $stmtBriefing = $conn->prepare(
            "SELECT nivel, conceito, valor_media, outro_padrao, vidro, esquadria, soleira, acab_calcadas, assets, comp_planta FROM briefing WHERE obra_id = ? LIMIT 1"
        );
        if ($stmtBriefing) {
            $stmtBriefing->bind_param("i", $obraIdBriefing);
            $stmtBriefing->execute();
            $resBriefing = $stmtBriefing->get_result();
            if ($row = $resBriefing->fetch_assoc()) {
                $briefing_obra = $row;
            }
            $stmtBriefing->close();
        }

        // obra links (link_drive, link_review, fotografico)
        $obra_links = [];
        $stmtLinks = $conn->prepare(
            "SELECT link_drive, link_review, fotografico FROM obra WHERE idobra = ? LIMIT 1"
        );
        if ($stmtLinks) {
            $stmtLinks->bind_param("i", $obraIdBriefing);
            $stmtLinks->execute();
            $resLinks = $stmtLinks->get_result();
            if ($rowLinks = $resLinks->fetch_assoc()) {
                $obra_links = $rowLinks;
            }
            $stmtLinks->close();
        }
    }

    // ==========================================================
    // 9) Observações da obra (observacao_obra)
    // ==========================================================
    $observacoes_obra = [];
    if (!empty($obraIdFromImage)) {
        $obraIdObs = (int) $obraIdFromImage;
        $stmtObs = $conn->prepare(
            "SELECT id, descricao, data FROM observacao_obra WHERE obra_id = ? ORDER BY ordem ASC, data DESC"
        );
        if ($stmtObs) {
            $stmtObs->bind_param("i", $obraIdObs);
            $stmtObs->execute();
            $resObs = $stmtObs->get_result();
            while ($row = $resObs->fetch_assoc()) {
                $observacoes_obra[] = $row;
            }
            $stmtObs->close();
        }
    }

    // ====================================
    // Resposta final
    // ==========================================================
    echo json_encode([
        "funcoes" => $funcoes,
        "status_imagem" => $statusImagem,
        "colaboradores" => $colaboradores,
        "log_alteracoes" => $logAlteracoes,
        "tempo_total_producao" => $tempo_total_producao,
        "tempo_pessoal_producao" => $tempo_pessoal_producao,
        "arquivos_imagem" => $arquivos_imagem,
        "arquivos_tipo" => $arquivos_tipo,
        "arquivos_anteriores" => $arquivos_anteriores,
        "notificacoes" => $notificacoes,
        "briefing_obra" => $briefing_obra,
        "obra_links" => $obra_links ?? [],
        "observacoes_obra" => $observacoes_obra,

    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["error" => "Método de requisição inválido."]);
}

$conn->close();
