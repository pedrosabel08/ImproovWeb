<?php
require_once __DIR__ . '/config/session_bootstrap.php';
include 'conexao.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['idcolaborador'])) {
    echo json_encode(['status' => 'erro', 'message' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

$responsavel_id = $_SESSION['idcolaborador'];
$data = json_decode(file_get_contents("php://input"), true);
$response = [];

if (!$data || !isset($data['imagem_id']) || !isset($data['status_id'])) {
    echo json_encode(['status' => 'erro', 'message' => 'Dados incompletos ou inválidos.']);
    exit;
}

$imagem_id = $data['imagem_id'];
$status_id = $data['status_id'];
$notificar = isset($data['notificar']) && $data['notificar'] == "1";
$finalizador_id = isset($data['finalizador']) ? intval($data['finalizador']) : null;
$data_id_funcao = isset($data['data_id_funcao']) ? intval($data['data_id_funcao']) : null;

// Se notificar for verdadeiro, apenas envia a notificação e encerra
if ($notificar && $finalizador_id) {
    // Busca o nome da imagem
    $stmt_nome = $conn->prepare("SELECT imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?");
    if (!$stmt_nome) {
        echo json_encode(['status' => 'erro', 'message' => 'Erro ao preparar consulta de imagem: ' . $conn->error]);
        exit;
    }
    $stmt_nome->bind_param("i", $imagem_id);
    $stmt_nome->execute();
    $stmt_nome->bind_result($imagem_nome);
    $stmt_nome->fetch();
    $stmt_nome->close();

    // Busca o nome do status
    $stmt_status = $conn->prepare("SELECT nome_status FROM status_imagem WHERE idstatus = ?");
    if (!$stmt_status) {
        echo json_encode(['status' => 'erro', 'message' => 'Erro ao preparar consulta de status: ' . $conn->error]);
        exit;
    }
    $stmt_status->bind_param("i", $status_id);
    $stmt_status->execute();
    $stmt_status->bind_result($nome_status);
    $stmt_status->fetch();
    $stmt_status->close();

    $mensagem = "Imagem {$imagem_nome} pode ser feito o render {$nome_status}";

    // Corrigido: não envia data, deixa o banco preencher
    $stmt_notif = $conn->prepare("insert into notificacoes_gerais (colaborador_id, mensagem, lida, funcao_imagem_id) VALUES (?, ?, 0, ?)");
    if (!$stmt_notif) {
        echo json_encode(['status' => 'erro', 'message' => 'Erro no prepare: ' . $conn->error]);
        exit;
    }
    $stmt_notif->bind_param("isi", $finalizador_id, $mensagem, $data_id_funcao);
    $stmt_notif->execute();
    $stmt_notif->close();

    // Adiciona em render_alta com status 'Não iniciado'
    $stmt_render = $conn->prepare("INSERT INTO render_alta (status, imagem_id, responsavel_id, status_id) VALUES ('Não iniciado',?, ?, ?)");
    $stmt_render->bind_param("iii", $imagem_id, $finalizador_id, $status_id);
    $stmt_render->execute();
    $idRenderAdicionado = $conn->insert_id;
    $stmt_render->close();

    $response = [
        'status' => 'sucesso',
        'notificado' => true,
        'mensagem_notificacao' => $mensagem,
        'idrender' => $idRenderAdicionado
    ];

    echo json_encode($response);
    exit;
}
// Se não for apenas notificação, continua com o fluxo normal:
$conn->begin_transaction();

try {
    // Verifica se imagem existe
    $stmt_check_exists = $conn->prepare("SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?");
    $stmt_check_exists->bind_param("i", $imagem_id);
    $stmt_check_exists->execute();
    $stmt_check_exists->store_result();

    if ($stmt_check_exists->num_rows === 0) {
        $stmt_check_exists->close();
        echo json_encode(['status' => 'erro', 'message' => 'ID não encontrado na tabela imagens_cliente_obra.']);
        exit;
    }
    $stmt_check_exists->close();

    // Verifica duplicidade na render_alta
    $stmt_check_render = $conn->prepare("SELECT idrender_alta FROM render_alta WHERE imagem_id = ? AND status_id = ?");
    $stmt_check_render->bind_param("ii", $imagem_id, $status_id);
    $stmt_check_render->execute();
    $stmt_check_render->store_result();

    if ($stmt_check_render->num_rows > 0) {
        $stmt_check_render->close();
        echo json_encode(['status' => 'erro', 'message' => 'Render com esta combinação de imagem e status já existe.']);
        exit;
    }
    $stmt_check_render->close();

    // Insere em render_alta
    $stmt1 = $conn->prepare("INSERT INTO render_alta (imagem_id, responsavel_id, status_id) VALUES (?, ?, ?)");
    $stmt1->bind_param("iii", $imagem_id, $responsavel_id, $status_id);
    $stmt1->execute();
    $idRenderAdicionado = $conn->insert_id;
    $stmt1->close();

    $response['idrender'] = $idRenderAdicionado;

    // Verifica e atualiza status da imagem
    $stmt_check_status = $conn->prepare("SELECT substatus_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?");
    $stmt_check_status->bind_param("i", $imagem_id);
    $stmt_check_status->execute();
    $stmt_check_status->bind_result($current_status);
    $stmt_check_status->fetch();
    $stmt_check_status->close();

    if ($current_status != 5) {
        $stmt2 = $conn->prepare("UPDATE imagens_cliente_obra SET substatus_id = 5 WHERE idimagens_cliente_obra = ?");
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
    $response['notificado'] = false;
} catch (Exception $e) {
    $conn->rollback();
    $response = ['status' => 'erro', 'message' => 'Erro ao executar as consultas: ' . $e->getMessage()];
}

$conn->close();
echo json_encode($response);
