<?php
include '../conexao.php';
// header('Content-Type: application/json');

// Lidar com as ações de AJAX
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'getRenders':
            // Buscar todos os renders
            $sql = "SELECT 
    c.nome_colaborador, 
    s.nome_status, 
    i.imagem_nome,
    r.*
FROM 
    render_alta r
LEFT JOIN 
    imagens_cliente_obra i ON r.imagem_id = i.idimagens_cliente_obra
LEFT JOIN 
    colaborador c ON r.responsavel_id = c.idcolaborador
LEFT JOIN 
    status_imagem s ON r.status_id = s.idstatus
WHERE 
    (
        r.status != 'Arquivado'
        AND (
            r.status NOT IN ('Finalizado', 'Aprovado') 
            OR (r.status IN ('Finalizado', 'Aprovado') AND r.data >= CURDATE())
        )
    )
ORDER BY 
    data DESC";
            $result = $conn->query($sql);
            $renders = [];

            while ($row = $result->fetch_assoc()) {
                $renders[] = $row;
            }

            echo json_encode(['status' => 'sucesso', 'renders' => $renders]);
            break;

        case 'getRender':
            // Buscar um render específico
            if (isset($_GET['idrender_alta'])) {
                $idrender_alta = $_GET['idrender_alta'];
                $sql = "SELECT r.*, i.imagem_nome, c.nome_colaborador, s.nome_status  FROM render_alta r
                 join imagens_cliente_obra i on r.imagem_id = i.idimagens_cliente_obra 
                 join colaborador c on r.responsavel_id = c.idcolaborador
                 join status_imagem s on r.status_id = s.idstatus
                 WHERE idrender_alta = $idrender_alta";
                $result = $conn->query($sql);
                $render = $result->fetch_assoc();

                // Buscar previews associados ao render (se houver) e incluí-los na resposta
                $previews = [];
                $stmtPre = $conn->prepare("SELECT filename, uploaded_at FROM render_previews WHERE render_id = ? ORDER BY uploaded_at ASC, id ASC");
                if ($stmtPre) {
                    $stmtPre->bind_param('i', $idrender_alta);
                    $stmtPre->execute();
                    $resPre = $stmtPre->get_result();
                    while ($rowPre = $resPre->fetch_assoc()) {
                        $previews[] = $rowPre;
                    }
                    $stmtPre->close();
                }

                echo json_encode(['status' => 'sucesso', 'render' => $render, 'previews' => $previews]);
            }
            break;
    }
}

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'updateRender':
            // Atualizar o render
            if (isset($_POST['idrender_alta']) && isset($_POST['status'])) {
                $idrender_alta = $_POST['idrender_alta'];
                $status = $_POST['status'];
                $logs = [];
                $debug = isset($_POST['debug']) && (string)$_POST['debug'] === '1';
                $logs[] = "updateRender: idrender_alta={$idrender_alta}, status={$status}";

                $stmtUpd = $conn->prepare("UPDATE render_alta SET status = ?, data = NOW() WHERE idrender_alta = ?");
                if (!$stmtUpd) {
                    $logs[] = 'Erro prepare update: ' . $conn->error;
                    echo json_encode(['status' => 'erro', 'message' => 'Erro ao atualizar o render', 'logs' => $debug ? $logs : null]);
                    break;
                }
                $stmtUpd->bind_param('si', $status, $idrender_alta);
                $okUpd = $stmtUpd->execute();
                $stmtUpd->close();

                if ($okUpd === TRUE) {
                    // Se o novo status for 'Aprovado', preparar os ângulos para follow-up
                    if (strtolower($status) === 'aprovado') {
                        // Criar tabela followup_angles se não existir
                        $createSql = "CREATE TABLE IF NOT EXISTS followup_angles (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            render_id INT NOT NULL,
                            imagem_id INT DEFAULT NULL,
                            filename VARCHAR(255) NOT NULL,
                            uploaded_at DATETIME DEFAULT NULL,
                            status ENUM('pendente','escolhido','em_producao') DEFAULT 'pendente',
                            UNIQUE KEY uniq_render_file (render_id, filename)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                        if ($conn->query($createSql) === TRUE) {
                            $logs[] = 'followup_angles: ok (CREATE TABLE IF NOT EXISTS)';
                        } else {
                            $logs[] = 'followup_angles: erro ao criar/validar tabela: ' . $conn->error;
                        }

                        // Buscar previews associados ao render e inserir na tabela followup_angles
                        $stmtPre = $conn->prepare("SELECT filename, uploaded_at FROM render_previews WHERE render_id = ?");
                        if ($stmtPre) {
                            $stmtPre->bind_param('i', $idrender_alta);
                            $stmtPre->execute();
                            $resPre = $stmtPre->get_result();

                            // Obter imagem pai (imagem_id) do render_alta, se existir
                            $imagem_id = null;
                            $stmtImg = $conn->prepare("SELECT imagem_id FROM render_alta WHERE idrender_alta = ? LIMIT 1");
                            if ($stmtImg) {
                                $stmtImg->bind_param('i', $idrender_alta);
                                $stmtImg->execute();
                                $rImg = $stmtImg->get_result()->fetch_assoc();
                                if ($rImg && isset($rImg['imagem_id']))
                                    $imagem_id = $rImg['imagem_id'];
                                $stmtImg->close();
                            }

                            $logs[] = 'render_alta.imagem_id=' . ($imagem_id !== null ? $imagem_id : 'null');

                            $insertStmt = $conn->prepare("INSERT IGNORE INTO followup_angles (render_id, imagem_id, filename, uploaded_at, status) VALUES (?, ?, ?, ?, 'pendente')");
                            while ($row = $resPre->fetch_assoc()) {
                                $filename = $row['filename'];
                                $uploaded_at = $row['uploaded_at'] ?: null;
                                $insertStmt->bind_param('iiss', $idrender_alta, $imagem_id, $filename, $uploaded_at);
                                $insertStmt->execute();
                            }
                            if ($insertStmt)
                                $insertStmt->close();
                            $stmtPre->close();

                            // ---------- Flow Review (2ª etapa): importar ângulos quando imagem for P00 ----------
                            if ($imagem_id) {
                                $statusNome = null;
                                if ($stStatus = $conn->prepare("SELECT s.nome_status FROM imagens_cliente_obra i JOIN status_imagem s ON s.idstatus = i.status_id WHERE i.idimagens_cliente_obra = ? LIMIT 1")) {
                                    $stStatus->bind_param('i', $imagem_id);
                                    $stStatus->execute();
                                    $rowStatus = $stStatus->get_result()->fetch_assoc();
                                    $statusNome = $rowStatus['nome_status'] ?? null;
                                    $stStatus->close();
                                }
                                $logs[] = 'imagem.status_nome=' . ($statusNome ?? 'null');

                                $isP00 = mb_strtolower(trim((string)$statusNome), 'UTF-8') === 'p00';
                                if ($isP00) {
                                    $funcaoImagemId = null;

                                    // Preferencial: funcao_id=4 (Finalização)
                                    if ($stFi = $conn->prepare("SELECT idfuncao_imagem FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = 4 LIMIT 1")) {
                                        $stFi->bind_param('i', $imagem_id);
                                        $stFi->execute();
                                        $rowFi = $stFi->get_result()->fetch_assoc();
                                        $funcaoImagemId = isset($rowFi['idfuncao_imagem']) ? intval($rowFi['idfuncao_imagem']) : null;
                                        $stFi->close();
                                    }

                                    // Fallback por nome da função
                                    if (!$funcaoImagemId) {
                                        if ($stFi2 = $conn->prepare("SELECT fi.idfuncao_imagem FROM funcao_imagem fi JOIN funcao f ON f.idfuncao = fi.funcao_id WHERE fi.imagem_id = ? AND LOWER(f.nome_funcao) LIKE 'finaliza%' LIMIT 1")) {
                                            $stFi2->bind_param('i', $imagem_id);
                                            $stFi2->execute();
                                            $rowFi2 = $stFi2->get_result()->fetch_assoc();
                                            $funcaoImagemId = isset($rowFi2['idfuncao_imagem']) ? intval($rowFi2['idfuncao_imagem']) : null;
                                            $stFi2->close();
                                        }
                                    }

                                    $logs[] = 'finalizacao.funcao_imagem_id=' . ($funcaoImagemId ? $funcaoImagemId : 'null');

                                    if ($funcaoImagemId) {
                                        // garantir que apareça na revisão
                                        if ($stUpFi = $conn->prepare("UPDATE funcao_imagem SET status = 'Em aprovação' WHERE idfuncao_imagem = ?")) {
                                            $stUpFi->bind_param('i', $funcaoImagemId);
                                            $stUpFi->execute();
                                            $stUpFi->close();
                                        }

                                        // índice de envio (um lote por aprovação)
                                        $nextIndice = 1;
                                        if ($stMax = $conn->prepare("SELECT MAX(indice_envio) AS max_indice FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ?")) {
                                            $stMax->bind_param('i', $funcaoImagemId);
                                            $stMax->execute();
                                            $rowMax = $stMax->get_result()->fetch_assoc();
                                            $max = isset($rowMax['max_indice']) ? intval($rowMax['max_indice']) : 0;
                                            $nextIndice = $max + 1;
                                            $stMax->close();
                                        }
                                        $logs[] = 'historico_aprovacoes_imagens.next_indice_envio=' . $nextIndice;

                                        // Rebuscar previews para não depender do cursor já percorrido
                                        $previewsToImport = [];
                                        if ($stPrev2 = $conn->prepare("SELECT filename FROM render_previews WHERE render_id = ? ORDER BY uploaded_at ASC, id ASC")) {
                                            $stPrev2->bind_param('i', $idrender_alta);
                                            $stPrev2->execute();
                                            $resPrev2 = $stPrev2->get_result();
                                            while ($p = $resPrev2->fetch_assoc()) {
                                                if (!empty($p['filename'])) $previewsToImport[] = $p['filename'];
                                            }
                                            $stPrev2->close();
                                        }
                                        $logs[] = 'previews_to_import=' . count($previewsToImport);

                                        foreach ($previewsToImport as $fn) {
                                            $path = 'uploads/renders/' . $fn;
                                            $nomeArquivo = pathinfo($fn, PATHINFO_FILENAME);

                                            // idempotência: se já existir para este funcao_imagem_id+path, reaproveita
                                            $histId = null;
                                            if ($stEx = $conn->prepare("SELECT id FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? AND imagem = ? ORDER BY id DESC LIMIT 1")) {
                                                $stEx->bind_param('is', $funcaoImagemId, $path);
                                                $stEx->execute();
                                                $rowEx = $stEx->get_result()->fetch_assoc();
                                                $histId = isset($rowEx['id']) ? intval($rowEx['id']) : null;
                                                $stEx->close();
                                            }

                                            if (!$histId) {
                                                if ($stIns = $conn->prepare("INSERT INTO historico_aprovacoes_imagens (funcao_imagem_id, imagem, indice_envio, nome_arquivo, caminho_imagem) VALUES (?, ?, ?, ?, ?)")) {
                                                    $stIns->bind_param('isiss', $funcaoImagemId, $path, $nextIndice, $nomeArquivo, $path);
                                                    if ($stIns->execute()) {
                                                        $histId = $conn->insert_id;
                                                        $logs[] = 'import_ok: ' . $fn . ' -> historico_id=' . $histId;
                                                    } else {
                                                        $logs[] = 'import_erro: ' . $fn . ' -> ' . $stIns->error;
                                                    }
                                                    $stIns->close();
                                                } else {
                                                    $logs[] = 'import_prepare_erro: ' . $conn->error;
                                                }
                                            } else {
                                                $logs[] = 'import_skip_exists: ' . $fn . ' -> historico_id=' . $histId;
                                            }

                                            if ($histId) {
                                                if ($stAi = $conn->prepare("INSERT IGNORE INTO angulos_imagens (imagem_id, historico_id, entrega_item_id, liberada, sugerida, motivo_sugerida) VALUES (?, ?, NULL, 0, 0, '')")) {
                                                    $stAi->bind_param('ii', $imagem_id, $histId);
                                                    $stAi->execute();
                                                    $stAi->close();
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $resp = ['status' => 'sucesso', 'message' => 'Render atualizado com sucesso'];
                    if ($debug) $resp['logs'] = $logs;
                    echo json_encode($resp);
                } else {
                    $logs[] = 'Erro ao atualizar o render (execute=false): ' . $conn->error;
                    $resp = ['status' => 'erro', 'message' => 'Erro ao atualizar o render'];
                    if ($debug) $resp['logs'] = $logs;
                    echo json_encode($resp);
                }
            }
            break;

        case 'updatePOS':
            // Aprovar o render
            if (isset($_POST['render_id'])) {
                $render_id = $_POST['render_id'];
                $refs = $_POST['refs'];
                $obs = $_POST['obs'];

                // Atualiza a tabela pos
                $sql = "UPDATE pos_producao SET refs = '$refs', obs = '$obs' WHERE render_id = $render_id;";
                if ($conn->query($sql) === TRUE) {
                    echo json_encode(['status' => 'sucesso']);
                } else {
                    echo json_encode(['status' => 'erro', 'message' => $conn->error]);
                }
            }
            break;

        case 'deleteRender':
            // Excluir o render
            if (isset($_POST['idrender_alta'])) {
                $idrender_alta = $_POST['idrender_alta'];
                $sql = "DELETE FROM render_alta WHERE idrender_alta = $idrender_alta";
                if ($conn->query($sql) === TRUE) {
                    echo json_encode(['status' => 'sucesso', 'message' => 'Render excluído com sucesso']);
                } else {
                    echo json_encode([
                        'status' => 'erro',
                        'message' => 'Erro ao excluir o render: ' . $conn->error
                    ]);
                }
            }
            break;
    }
}

$conn->close();
