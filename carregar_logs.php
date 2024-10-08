<?php
// Incluir a conexão
include 'conexao.php';

// Verificar se o ID do colaborador foi passado
if (isset($_GET['colaboradorId']) && is_numeric($_GET['colaboradorId'])) {
    $colaboradorId = intval($_GET['colaboradorId']);
    $obraId = isset($_GET['obraId']) && is_numeric($_GET['obraId']) ? intval($_GET['obraId']) : 0;

    // Preparar a consulta com JOIN e filtragem por obra se obraId for diferente de 0
    $sql = "SELECT l.funcao_imagem_id, l.status_anterior, l.status_novo, l.data, i.imagem_nome, o.nome_obra
            FROM log_alteracoes l
            INNER JOIN funcao_imagem f on f.idfuncao_imagem = l.funcao_imagem_id 
            INNER JOIN imagens_cliente_obra i on f.imagem_id = i.idimagens_cliente_obra
            INNER JOIN obra o on i.obra_id = o.idobra
            WHERE l.colaborador_id = ?";

    // Adicionar filtro de obra se obraId for diferente de 0
    if ($obraId > 0) {
        $sql .= " AND i.obra_id = ?";
    }

    $sql .= " ORDER BY l.data DESC";

    // Usar prepared statement
    if ($stmt = $conn->prepare($sql)) {
        if ($obraId > 0) {
            $stmt->bind_param('ii', $colaboradorId, $obraId); // 'ii' para dois inteiros
        } else {
            $stmt->bind_param('i', $colaboradorId); // 'i' para um inteiro
        }

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
