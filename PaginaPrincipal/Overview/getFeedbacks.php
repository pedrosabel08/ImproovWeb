<?php
include '../../conexao.php';
header('Content-Type: application/json');

$response = [];

// Busca funções com status 'Ajuste' e último comentário
$sql = "SELECT 
    fi.idfuncao_imagem AS taskId,
    i.imagem_nome AS taskTitle,
    fi.status AS type,
    c.texto AS excerpt,
    c.data AS date,
    CONCAT(u.nome_colaborador) AS author
FROM funcao_imagem fi
JOIN funcao f ON fi.funcao_id = f.idfuncao
LEFT JOIN historico_aprovacoes_imagens h 
    ON h.funcao_imagem_id = fi.idfuncao_imagem
LEFT JOIN comentarios_imagem c
    ON c.ap_imagem_id = h.id
LEFT JOIN colaborador u
    ON u.idcolaborador = fi.colaborador_id
LEFT JOIN imagens_cliente_obra i
    ON i.idimagens_cliente_obra = fi.imagem_id
WHERE fi.status = 'Ajuste' AND fi.colaborador_id = 35
  AND c.data = (
      SELECT MAX(c2.data)
      FROM comentarios_imagem c2
      JOIN historico_aprovacoes_imagens h2
        ON h2.id = c2.ap_imagem_id
      WHERE h2.funcao_imagem_id = fi.idfuncao_imagem
  )
ORDER BY fi.idfuncao_imagem DESC
";

$result = $conn->query($sql);
$response['feedbacks'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();

echo json_encode($response, JSON_UNESCAPED_UNICODE);
