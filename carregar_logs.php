<?php
// Incluir a conexão
include 'conexao.php';

// Verificar se o ID do colaborador foi passado
if (isset($_GET['colaboradorId']) && is_numeric($_GET['colaboradorId'])) {
    $colaboradorId = intval($_GET['colaboradorId']);

    // Preparar a consulta com JOIN
    $sql = "SELECT l.funcao_imagem_id, l.status_anterior, l.status_novo, l.data 
            FROM log_alteracoes l
			WHERE l.colaborador_id = ? 
            ORDER BY l.data DESC";

    // Usar prepared statement
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $colaboradorId); // 'i' para inteiro
        $stmt->execute();
        $result = $stmt->get_result();

        // Buscar os resultados
        $logs = $result->fetch_all(MYSQLI_ASSOC);

        // Retornar os logs como JSON
        header('Content-Type: application/json');
        echo json_encode($logs);

        $stmt->close();
    } else {
        // Se houver erro na preparação da consulta
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Erro na preparação da consulta.']);
    }
} else {
    // Se o ID do colaborador não for válido, retornar um erro
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID do colaborador inválido.']);
}

// Fechar a conexão
$conn->close();
