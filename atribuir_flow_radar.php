<?php
header('Content-Type: application/json');
// Flow Radar - Atribuir tarefa a colaborador
include 'conexao.php';

$conn = conectarBanco();

$data = json_decode(file_get_contents('php://input'), true);
$colaborador_id = isset($data['colaborador_id']) ? (int)$data['colaborador_id'] : 0;
$funcao_imagem_id = isset($data['funcao_imagem_id']) ? (int)$data['funcao_imagem_id'] : 0;

if (!$colaborador_id || !$funcao_imagem_id) {
    echo json_encode(['error' => 'Parâmetros obrigatórios: colaborador_id e funcao_imagem_id']);
    exit;
}

try {
    // Proteção: só atribuir se tarefa estiver sem colaborador
    $sql_check = "SELECT colaborador_id FROM funcao_imagem WHERE idfuncao_imagem = ?";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param('i', $funcao_imagem_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res && !empty($res['colaborador_id']) && $res['colaborador_id'] != 0) {
        echo json_encode(['error' => 'Tarefa já está atribuída a outro colaborador']);
        exit;
    }

    // Atualiza a função_imagem
    $sql_update = "UPDATE funcao_imagem SET colaborador_id = ?, status = 'Em andamento' WHERE idfuncao_imagem = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param('ii', $colaborador_id, $funcao_imagem_id);
    if ($stmt->execute()) {
        // Notificação simples (se existir tabela notificacoes)
        if ($conn->query("SHOW TABLES LIKE 'notificacoes'")->num_rows > 0) {
            $msg = 'Tarefa atribuída via Flow Radar';
            $ins = $conn->prepare("INSERT INTO notificacoes (colaborador_id, mensagem, data, lida, funcao_imagem_id) VALUES (?, ?, NOW(), 0, ?)");
            if ($ins) {
                $ins->bind_param('isi', $colaborador_id, $msg, $funcao_imagem_id);
                $ins->execute();
                $ins->close();
            }
        }

        echo json_encode(['success' => true, 'message' => 'Tarefa atribuída com sucesso.']);
    } else {
        echo json_encode(['error' => 'Falha ao atribuir tarefa', 'db_error' => $stmt->error]);
    }
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao atribuir tarefa', 'message' => $e->getMessage()]);
    exit;
}

?>
