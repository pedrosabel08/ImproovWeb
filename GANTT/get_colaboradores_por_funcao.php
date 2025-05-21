<?php
require '../conexao.php';

if (isset($_GET['funcao_id'], $_GET['data_inicio'], $_GET['data_fim'])) {
    $funcao_id = intval($_GET['funcao_id']);
    $data_inicio = $_GET['data_inicio'];
    $data_fim = $_GET['data_fim'];

    $stmt = $conn->prepare("SELECT 
    c.idcolaborador,
    c.nome_colaborador,
    GROUP_CONCAT(DISTINCT o_conflitante.nomenclatura SEPARATOR ', ') AS obras_conflitantes,
    MIN(g_conflitante.data_inicio) AS data_inicio_conflito,
    MAX(g_conflitante.data_fim) AS data_fim_conflito,
    GROUP_CONCAT(DISTINCT conflito.etapa SEPARATOR ', ') AS etapas_conflitantes,
    COUNT(conflito.id) AS total_conflitos,
    CASE 
        WHEN COUNT(conflito.id) >= f.limite THEN 1 ELSE 0
    END AS ocupado
FROM colaborador c
LEFT JOIN funcao_colaborador fc ON fc.colaborador_id = c.idcolaborador
LEFT JOIN funcao f ON f.idfuncao = fc.funcao_id
LEFT JOIN (
    SELECT 
        ec.colaborador_id,
        g.data_inicio,
        g.data_fim,
        g.id,
        o.nomenclatura,
        g.etapa
    FROM etapa_colaborador ec
    INNER JOIN gantt_prazos g ON g.id = ec.gantt_id
    INNER JOIN obra o ON o.idobra = g.obra_id
    WHERE (
        (g.data_inicio <= ? AND g.data_fim >= ?) OR
        (g.data_inicio BETWEEN ? AND ?) OR
        (g.data_fim BETWEEN ? AND ?) OR
        (? BETWEEN g.data_inicio AND g.data_fim) OR
        (? BETWEEN g.data_inicio AND g.data_fim)
    )

) AS conflito ON conflito.colaborador_id = c.idcolaborador
LEFT JOIN obra o_conflitante ON o_conflitante.nomenclatura = conflito.nomenclatura
LEFT JOIN gantt_prazos g_conflitante ON g_conflitante.id = conflito.id
WHERE fc.funcao_id = ?
  AND c.ativo = 1
GROUP BY c.idcolaborador, c.nome_colaborador, f.limite
ORDER BY c.nome_colaborador

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
