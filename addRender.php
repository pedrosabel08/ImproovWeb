<?php 
include 'conexao.php';

session_start();

header('Content-Type: application/json'); // Verificar se o usuário está logado 

if (!isset($_SESSION['idcolaborador'])) {
    echo json_encode(['status' => 'erro', 'message' => 'Sessão expirada. Faça login novamente.']);
    exit;
}
$responsavel_id = $_SESSION['idcolaborador'];
$data = json_decode(file_get_contents("php://input"), true);
$response = []; // <- vamos acumular as respostas aqui!

if ($data && isset($data['imagem_id'])) {
    $imagem_id = $data['imagem_id'];
    $status_id = $data['status_id'];

    $conn->begin_transaction();

    try {
        // Verifica se a imagem existe na tabela imagens_cliente_obra
        $stmt_check_exists = $conn->prepare("SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?");
        $stmt_check_exists->bind_param("i", $imagem_id);
        $stmt_check_exists->execute();
        $stmt_check_exists->store_result();

        if ($stmt_check_exists->num_rows === 0) {
            $response = [
                'status' => 'erro',
                'message' => 'ID não encontrado na tabela imagens_cliente_obra.'
            ];
            $stmt_check_exists->close();
            echo json_encode($response);
            exit;
        }

        $stmt_check_exists->close();

        // Verifica se já existe uma combinação de imagem_id e status_id em render_alta
        $stmt_check_render = $conn->prepare("SELECT idrender_alta FROM render_alta WHERE imagem_id = ? AND status_id = ?");
        $stmt_check_render->bind_param("ii", $imagem_id, $status_id);
        $stmt_check_render->execute();
        $stmt_check_render->store_result();

        if ($stmt_check_render->num_rows > 0) {
            // Já existe um render com essa combinação de imagem_id e status_id
            $response = [
                'status' => 'erro',
                'message' => 'Render com esta combinação de imagem e status já existe.'
            ];
            $stmt_check_render->close();
            echo json_encode($response);
            exit;
        }

        $stmt_check_render->close();

        // Se passar pelas verificações, prossegue com a inserção
        $stmt1 = $conn->prepare("INSERT INTO render_alta (imagem_id, responsavel_id, status_id) VALUES (?, ?, ?)");
        $stmt1->bind_param("iii", $imagem_id, $responsavel_id, $status_id);
        $stmt1->execute();
        $idRenderAdicionado = $conn->insert_id;
        $stmt1->close();

        $response['idrender'] = $idRenderAdicionado;

        // Atualiza o status na tabela imagens_cliente_obra
        $stmt_check_status = $conn->prepare("SELECT status_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?");
        $stmt_check_status->bind_param("i", $imagem_id);
        $stmt_check_status->execute();
        $stmt_check_status->bind_result($current_status);
        $stmt_check_status->fetch();
        $stmt_check_status->close();

        if ($current_status != 13) {
            $stmt2 = $conn->prepare("UPDATE imagens_cliente_obra SET status_id = 13 WHERE idimagens_cliente_obra = ?");
            $stmt2->bind_param("i", $imagem_id);
            $stmt2->execute();
            $stmt2->close();

            $stmt_update_funcao = $conn->prepare("UPDATE funcao_imagem SET status = 'Não iniciado' WHERE imagem_id = ? AND funcao_id = 5");
            $stmt_update_funcao->bind_param("i", $imagem_id);
            $stmt_update_funcao->execute();
            $stmt_update_funcao->close();
        }

        $conn->commit();

        $response['status'] = 'sucesso';
        $response['message'] = 'Render criado e status atualizado com sucesso.';
    } catch (Exception $e) {
        $conn->rollback();
        $response = [
            'status' => 'erro',
            'message' => 'Erro ao executar as consultas: ' . $e->getMessage()
        ];
    }
} else {
    $response = [
        'status' => 'erro',
        'message' => 'Dados incompletos ou inválidos.'
    ];
}

$conn->close();

echo json_encode($response);
