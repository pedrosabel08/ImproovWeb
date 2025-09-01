<?php
include "../conexao.php"; // conexão com seu MySQL

header('Content-Type: application/json; charset=utf-8');

$response = [];

if (isset($_POST['imagem_id'])) {
    $imagemId = intval($_POST['imagem_id']);

    $sql = "SELECT 
    h.imagem_id,
    h.status_id,
    s.nome_status AS status_nome,
    h.substatus_id,
    ss.nome_substatus AS substatus_nome,
    h.data_movimento AS data_inicio,
    LEAD(h.data_movimento) OVER (
        PARTITION BY h.imagem_id, h.status_id
        ORDER BY h.data_movimento
    ) AS data_fim
FROM historico_imagens h
LEFT JOIN status_imagem s ON h.status_id = s.idstatus
LEFT JOIN substatus_imagem ss ON h.substatus_id = ss.id
WHERE h.imagem_id = ?
ORDER BY h.data_movimento;
";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $imagemId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $response[] = [
                "imagem_id"      => $row['imagem_id'],
                "status_id"      => $row['status_id'],
                "status_nome"    => $row['status_nome'],
                "substatus_id"   => $row['substatus_id'],
                "substatus_nome" => $row['substatus_nome'],
                "data_inicio"    => $row['data_inicio'],
                "data_fim"       => $row['data_fim'], // opcional, se quiser mostrar
            ];
        }
    }
}

// Se não tiver resultado, devolve array vazio
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
