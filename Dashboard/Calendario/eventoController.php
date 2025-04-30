<?php
include '../../conexao.php';
session_start();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

header('Content-Type: application/json');

try {
    if ($method === 'POST') {
        $titulo = $data['title'] ?? '';
        $data_evento = $data['start'] ?? '';
        $obra_id = $data['obra_id'] ?? null;
        $responsavel_id = $_SESSION['idcolaborador'] ?? null;
        $tipo_evento = $data['type'] ?? null;

        if (!$titulo || !$data_evento || !$obra_id || !$responsavel_id) {
            throw new Exception("Dados incompletos para criar o evento.");
        }

        $sql = "INSERT INTO eventos_obra (descricao, data_evento, tipo_evento, obra_id, responsavel_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssii", $titulo, $data_evento, $tipo_evento, $obra_id, $responsavel_id);

        if ($stmt->execute()) {
            echo json_encode(['id' => $conn->insert_id, 'message' => 'Evento criado com sucesso.']);
        } else {
            echo json_encode(['error' => true, 'message' => 'Erro ao criar evento.' + $stmt->error]);
        }
    } elseif ($method === 'PUT') {
        $id = $data['id'] ?? null;
        $titulo = $data['title'] ?? '';
        $data_evento = $data['start'] ?? '';
        $tipo_evento = $data['type'] ?? null;


        if (!$id || !$titulo || !$data_evento) {
            throw new Exception("Dados incompletos para atualizar o evento.");
        }

        $sql = "UPDATE eventos_obra SET descricao = ?, data_evento = ?, tipo_evento = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $titulo, $data_evento, $tipo_evento, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Evento atualizado com sucesso.']);
        } else {
            echo json_encode(['error' => true, 'message' => 'Erro ao atualizar evento.']);
        }
    } elseif ($method === 'DELETE') {
        $id = $data['id'] ?? null;
        if (!$id) {
            throw new Exception("ID do evento não informado.");
        }

        $sql = "DELETE FROM eventos_obra WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['id' => $conn->insert_id, 'message' => 'Evento excluído com sucesso.']);
        } else {
            echo json_encode(['error' => true, 'message' => 'Erro ao excluir evento.']);
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
