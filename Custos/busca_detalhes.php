<?php
require '../conexao.php';

if (isset($_GET['imagem_id'])) {
    $imagem_id = intval($_GET['imagem_id']);

    $stmt = $conn->prepare("SELECT 
            f.nome_funcao, 
            c.nome_colaborador, 
            fi.valor 
        FROM funcao_imagem fi 
        JOIN colaborador c ON fi.colaborador_id = c.idcolaborador 
        JOIN funcao f ON fi.funcao_id = f.idfuncao 
        WHERE fi.imagem_id = ?
          AND (
            fi.valor IS NOT NULL AND fi.valor > 0
          )
    ");
    $stmt->bind_param("i", $imagem_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $dados = [];

    while ($row = $result->fetch_assoc()) {
        $dados[] = $row;
    }

    echo json_encode($dados);

    $stmt->close();
}

$conn->close();
