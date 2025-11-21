<?php
header('Content-Type: application/json; charset=utf-8');
include '../conexao.php';

// Parâmetros: imagem_id (prioritário) ou entrega_item_id
$imagem_id = isset($_GET['imagem_id']) ? intval($_GET['imagem_id']) : 0;
$entrega_item_id = isset($_GET['entrega_item_id']) ? intval($_GET['entrega_item_id']) : 0;

if ($imagem_id <= 0 && $entrega_item_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Informe imagem_id ou entrega_item_id']);
    exit;
}

// Monta condição dinâmica
$where = '';
$params = [];
$types = '';
if ($imagem_id > 0) {
    $where = 'ai.imagem_id = ?';
    $params[] = $imagem_id;
    $types .= 'i';
} else {
    $where = 'ai.entrega_item_id = ?';
    $params[] = $entrega_item_id;
    $types .= 'i';
}

// Seleciona ângulos liberados para visualização
// Usa historico_aprovacoes_imagens para recuperar nome_arquivo ou imagem
$sql = "SELECT 
            ai.id AS angulo_id,
            ai.imagem_id,
            ai.entrega_item_id,
            ai.historico_id,
            ai.liberada,
            ai.sugerida,
            ai.motivo_sugerida,
            hi.nome_arquivo,
            hi.imagem,
            hi.indice_envio,
            hi.funcao_imagem_id,
            COALESCE(hi.nome_arquivo, hi.imagem) AS base_nome,
            hi.data_envio
        FROM angulos_imagens ai
        LEFT JOIN historico_aprovacoes_imagens hi ON hi.id = ai.historico_id
        WHERE $where
        ORDER BY ai.id ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Erro prepare: ' . $conn->error]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Erro execute: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$res = $stmt->get_result();
$angulos = [];
while ($row = $res->fetch_assoc()) {
    // Constrói URL provável (mantém lógica simples; front pode ajustar)
    $arquivoBase = $row['imagem'];
    $url = null;
    if ($arquivoBase) {
        // Se não houver extensão, assume .jpg
        $ext = strtolower(pathinfo($arquivoBase, PATHINFO_EXTENSION));
        if ($ext === '') {
            $arquivoBase .= '.jpg';
        }
        $url = 'https://improov.com.br/sistema/' . $arquivoBase;
    }
    $row['preview_url'] = $url;
    $angulos[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'count' => count($angulos),
    'angulos' => $angulos
], JSON_UNESCAPED_UNICODE);
?>