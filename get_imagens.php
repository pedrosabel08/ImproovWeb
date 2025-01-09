<?php
// Receber o POST com o ID do colaborador e o contêiner ativo
include 'conexao.php';

$data = json_decode(file_get_contents('php://input'), true);
$colaboradorId = $data['colaboradorId'];
$container = isset($data['container']) ? $data['container'] : null;

if ($colaboradorId) {
    if ($container === 'andamento') {
        // Query para o contêiner "Em andamento"
        $sql = "SELECT fi.idfuncao_imagem, fi.funcao_id, i.imagem_nome, pc.prioridade
                FROM funcao_imagem fi
                JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
                JOIN prioridade_funcao pc ON fi.idfuncao_imagem = pc.funcao_imagem_id
                WHERE fi.colaborador_id = ? AND fi.status = 'Em andamento'";
    } elseif ($container === 'prioridade') {
        // Query para o contêiner "Prioridade"
        $sql = "SELECT fi.idfuncao_imagem, fi.funcao_id, i.imagem_nome, pc.prioridade
                FROM funcao_imagem fi
                JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
                JOIN prioridade_funcao pc ON fi.idfuncao_imagem = pc.funcao_imagem_id
                WHERE fi.colaborador_id = ?";
    } else {
        echo json_encode(['imagens' => []]); // Retorna vazio se o contêiner não for especificado
        exit;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $colaboradorId);
    $stmt->execute();
    $result = $stmt->get_result();

    $imagens = [];
    while ($row = $result->fetch_assoc()) {
        $imagens[] = [
            'idfuncao_imagem' => $row['idfuncao_imagem'],
            'funcao_id' => $row['funcao_id'],
            'imagem_nome' => $row['imagem_nome'],
            'prioridade' => $row['prioridade']
        ];
    }

    // Retornar as imagens em formato JSON
    echo json_encode(['imagens' => $imagens]);

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['imagens' => []]); // Nenhuma imagem se não houver ID
}
