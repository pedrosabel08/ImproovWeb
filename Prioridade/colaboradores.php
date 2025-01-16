<?php
// Receber os dados do corpo da requisição (POST)
include '../conexao.php';

$data = json_decode(file_get_contents('php://input'), true);

// Extrair colaboradorId e obraId da requisição
$colaboradorId = $data['colaboradorId'];
$obraId = $data['obraId']; // Novo campo para filtrar pela obra

// Verificar se o colaboradorId foi fornecido
if ($colaboradorId) {
    // Iniciar a query SQL
    $sql = "SELECT fi.idfuncao_imagem, fi.funcao_id, i.imagem_nome, pc.prioridade, fi.status
        FROM funcao_imagem fi
        JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
        JOIN obra o ON i.obra_id = o.idobra
        JOIN prioridade_funcao pc ON fi.idfuncao_imagem = pc.funcao_imagem_id
        WHERE fi.colaborador_id = ? AND o.status_obra = 0";

    // Se a obraId for fornecida, adicionar o filtro de obra
    if ($obraId) {
        $sql .= " AND i.obra_id = ?";
    }

    $sql .= " ORDER BY pc.prioridade ASC, i.idimagens_cliente_obra";

    // Prepara e executa a consulta
    $stmt = $conn->prepare($sql);
    if ($obraId) {
        $stmt->bind_param("ii", $colaboradorId, $obraId);
    } else {
        $stmt->bind_param("i", $colaboradorId);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $imagens = [];
    while ($row = $result->fetch_assoc()) {
        $imagens[] = [
            'idfuncao_imagem' => $row['idfuncao_imagem'],
            'funcao_id' => $row['funcao_id'],
            'imagem_nome' => $row['imagem_nome'],
            'status' => $row['status'],
            'prioridade' => $row['prioridade']
        ];
    }

    // Retornar as imagens em formato JSON
    echo json_encode(['imagens' => $imagens]);

    $stmt->close();
} else {
    // Retorna vazio se colaboradorId não for fornecido
    echo json_encode(['imagens' => []]);
}

$conn->close();
