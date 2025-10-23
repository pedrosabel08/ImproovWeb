<?php

header('Content-Type: application/json; charset=utf-8');

include __DIR__ . '/../conexao.php';

// Hardcode obra_id = 1 as requested
$obra_id = 74;

// Retorna lista de imagens da obra com status derivado de followup_angles quando existir
$sql = "SELECT
    i.idimagens_cliente_obra AS id,
    i.imagem_nome AS nome_imagem,
    o.nomenclatura AS obra_nomenclatura,
    -- Se existir algum ângulo PENDENTE na tabela followup_angles, marcamos como 'Pendente'
    CASE
        WHEN EXISTS(SELECT 1 FROM followup_angles fa WHERE fa.imagem_id = i.idimagens_cliente_obra AND fa.status = 'pendente') THEN 'Pendente'
        WHEN EXISTS(SELECT 1 FROM followup_angles fa WHERE fa.imagem_id = i.idimagens_cliente_obra) THEN 'Com ângulos'
        ELSE 'Em produção'
    END AS followup_status
FROM imagens_cliente_obra i
JOIN obra o ON i.obra_id = o.idobra
WHERE i.obra_id = ?
ORDER BY i.idimagens_cliente_obra DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'Erro ao preparar query', 'mysqli_error' => $conn->error]);
    exit;
}

$stmt->bind_param('i', $obra_id);
$stmt->execute();
$result = $stmt->get_result();

$imagens = [];
$obra_nomenclatura = null;
while ($row = $result->fetch_assoc()) {
    $obra_nomenclatura = $row['obra_nomenclatura'];
    $imagens[] = [
        'id' => (int)$row['id'],
        'nome_imagem' => $row['nome_imagem'],
        'followup_status' => $row['followup_status']
    ];
}

// calcular métricas por imagem para evitar duplicação por joins
$metrics = ['chosen' => 0, 'pending' => 0, 'total_images' => count($imagens)];

$sqlImgs = "SELECT i.idimagens_cliente_obra AS id,
    (SELECT COUNT(*) FROM followup_angles fa JOIN render_alta r ON fa.render_id = r.idrender_alta WHERE r.imagem_id = i.idimagens_cliente_obra AND fa.status = 'escolhido') AS chosen_count,
    (SELECT COUNT(*) FROM followup_angles fa JOIN render_alta r ON fa.render_id = r.idrender_alta WHERE r.imagem_id = i.idimagens_cliente_obra AND fa.status = 'pendente') AS pending_count
FROM imagens_cliente_obra i
WHERE i.obra_id = ?";

if ($ms = $conn->prepare($sqlImgs)) {
    $ms->bind_param('i', $obra_id);
    $ms->execute();
    $r = $ms->get_result();
    while ($rowImg = $r->fetch_assoc()) {
        $chosen = (int)$rowImg['chosen_count'];
        $pending = (int)$rowImg['pending_count'];
        if ($chosen > 0) {
            $metrics['chosen']++;
        } elseif ($pending > 0) {
            $metrics['pending']++;
        }
    }
    $ms->close();
}


echo json_encode(['obra_id' => $obra_id, 'obra_nomenclatura' => $obra_nomenclatura, 'imagens' => $imagens, 'metrics' => $metrics], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
