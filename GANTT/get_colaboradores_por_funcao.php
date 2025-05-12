<?php
require '../conexao.php';

if (isset($_GET['funcao_id'], $_GET['data_inicio'], $_GET['data_fim'])) {
    $funcao_id = intval($_GET['funcao_id']);
    $data_inicio = $_GET['data_inicio'];
    $data_fim = $_GET['data_fim'];

    $stmt = $conn->prepare("SELECT 
    c.idcolaborador,
    c.nome_colaborador,
    o.nomenclatura,
    EXISTS (
        SELECT 1 
        FROM etapa_colaborador ec
        INNER JOIN gantt_prazos g ON g.id = ec.gantt_id
        WHERE ec.colaborador_id = c.idcolaborador 
        AND (
            -- Verifica se a etapa do colaborador sobrepõe o período da nova etapa
            (g.data_inicio <= ? AND g.data_fim >= ?) OR
            (g.data_inicio BETWEEN ? AND ?) OR
            (g.data_fim BETWEEN ? AND ?) OR
            (? BETWEEN g.data_inicio AND g.data_fim) OR
            (? BETWEEN g.data_inicio AND g.data_fim)
        )
    ) AS ocupado
FROM colaborador c
LEFT JOIN funcao_colaborador fc ON fc.colaborador_id = c.idcolaborador
LEFT JOIN etapa_colaborador ecol on ecol.colaborador_id = c.idcolaborador
LEFT JOIN gantt_prazos g2 ON g2.id = ecol.gantt_id
LEFT JOIN obra o ON o.idobra = g2.obra_id  -- A tabela obra agora é conectada corretamente
WHERE fc.funcao_id = ?;
;
");

    if ($stmt) {
        // Ajustando o número de parâmetros e a definição de tipos
        $stmt->bind_param(
            "ssssssssi",  // 9 parâmetros: 8 para datas e 1 para o inteiro (funcao_id)
            $data_inicio,
            $data_fim,
            $data_inicio,
            $data_fim,
            $data_inicio,
            $data_fim,
            $data_inicio,
            $data_fim,
            $funcao_id
        );

        $stmt->execute();
        // Verificando se a execução foi bem-sucedida
        if ($result = $stmt->get_result()) {
            $colaboradores = [];
            while ($row = $result->fetch_assoc()) {
                $row['ocupado'] = (bool)$row['ocupado']; // converte 0/1 para true/false
                $colaboradores[] = $row;
            }

            echo json_encode($colaboradores);
        } else {
            http_response_code(500);
            echo json_encode(['erro' => 'Erro ao executar a consulta']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro na preparação da consulta']);
    }
} else {
    http_response_code(400);
    echo json_encode(['erro' => 'Parâmetros funcao_id, data_inicio e data_fim são obrigatórios']);
}
