<?php
include '../conexao.php';
header('Content-Type: application/json');

// Lidar com as ações de AJAX
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'getRenders':
            // Buscar todos os renders
            $sql = "SELECT 
    c.nome_colaborador, 
    idrender_alta, 
    imagem_id, 
    status, 
    data, 
    s.nome_status, 
    i.imagem_nome 
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
            r.status != 'Finalizado' 
            OR (r.status = 'Finalizado' AND r.data >= CURDATE() - INTERVAL 5 DAY)
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
                $sql = "SELECT idrender_alta, imagem_id, status, data, i.imagem_nome FROM render_alta r join imagens_cliente_obra i on r.imagem_id = i.idimagens_cliente_obra WHERE idrender_alta = $idrender_alta";
                $result = $conn->query($sql);
                $render = $result->fetch_assoc();

                echo json_encode(['status' => 'sucesso', 'render' => $render]);
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
                    echo json_encode(['status' => 'sucesso', 'message' => 'Render atualizado com sucesso']);
                } else {
                    echo json_encode(['status' => 'erro', 'message' => 'Erro ao atualizar o render']);
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
