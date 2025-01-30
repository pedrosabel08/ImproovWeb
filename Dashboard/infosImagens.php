<?php

include '../conexao.php';

// Obtém os dados enviados pelo JavaScript
$data = json_decode(file_get_contents("php://input"), true);

// Verifica se o parâmetro 'obraId' foi enviado
if (!isset($data['obraId'])) {
    http_response_code(400); // Retorna erro se o ID da obra não foi enviado
    echo json_encode(["error" => "ID da obra não enviado"]);
    exit;
}

$obraId = $data['obraId'];

try {
    // Prepara a consulta para buscar imagens relacionadas à obra
    $stmt = $conn->prepare(
        "SELECT 
            i.idimagens_cliente_obra AS idimagem, 
            i.recebimento_arquivos, 
            i.data_inicio, 
            i.prazo, 
            MAX(i.imagem_nome) AS imagem_nome,
            s.nome_status, 
            i.tipo_imagem, 
            i.antecipada,
            i.clima,
            i.animacao 
        FROM imagens_cliente_obra i
        JOIN obra o ON i.obra_id = o.idobra 
        LEFT JOIN funcao_imagem fi ON i.idimagens_cliente_obra = fi.imagem_id 
        LEFT JOIN status_imagem s ON i.status_id = s.idstatus
        WHERE o.idobra = ?
        GROUP BY i.idimagens_cliente_obra"
    );

    // Associa o parâmetro 'obraId' à consulta
    $stmt->bind_param("i", $obraId);

    // Executa a consulta
    $stmt->execute();

    // Obtém os resultados
    $result = $stmt->get_result();
    $images = $result->fetch_all(MYSQLI_ASSOC);

    // Retorna os dados em formato JSON
    echo json_encode($images);
} catch (Exception $e) {
    http_response_code(500); // Erro interno do servidor
    echo json_encode(["error" => "Erro ao buscar imagens: " . $e->getMessage()]);
}
