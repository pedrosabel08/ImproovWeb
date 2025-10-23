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
                $sql = "UPDATE render_alta SET status = '$status', data = NOW() WHERE idrender_alta = $idrender_alta";
                if ($conn->query($sql) === TRUE) {
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
                        $conn->query($createSql);

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
                                if ($rImg && isset($rImg['imagem_id'])) $imagem_id = $rImg['imagem_id'];
                                $stmtImg->close();
                            }

                            $insertStmt = $conn->prepare("INSERT IGNORE INTO followup_angles (render_id, imagem_id, filename, uploaded_at, status) VALUES (?, ?, ?, ?, 'pendente')");
                            while ($row = $resPre->fetch_assoc()) {
                                $filename = $row['filename'];
                                $uploaded_at = $row['uploaded_at'] ?: null;
                                $insertStmt->bind_param('iiss', $idrender_alta, $imagem_id, $filename, $uploaded_at);
                                $insertStmt->execute();
                            }
                            if ($insertStmt) $insertStmt->close();
                            $stmtPre->close();
                        }
                    }
                    echo json_encode(['status' => 'sucesso', 'message' => 'Render atualizado com sucesso']);
                } else {
                    echo json_encode(['status' => 'erro', 'message' => 'Erro ao atualizar o render']);
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
