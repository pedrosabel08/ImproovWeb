<?php
// Conexão com o banco de dados

include '../conexao.php';

function tableHasColumn(mysqli $conn, string $table, string $column): bool
{
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res && $res->num_rows > 0);
}

// Pega o ID (imagem JPG) ou arquivo_log (PDF)
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$arquivo_log_id = isset($_GET['arquivo_log_id']) ? intval($_GET['arquivo_log_id']) : null;
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : null;

// Busca os comentários no banco de dados
if ($arquivo_log_id) {
    if (!tableHasColumn($conn, 'comentarios_imagem', 'arquivo_log_id')) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }

    $query = "SELECT ci.*, c.nome_colaborador as nome_responsavel FROM comentarios_imagem ci
              JOIN colaborador c ON ci.responsavel_id = c.idcolaborador
              WHERE ci.arquivo_log_id = ?";

    if ($pagina !== null && tableHasColumn($conn, 'comentarios_imagem', 'pagina')) {
        $query .= " AND ci.pagina = ?";
        $query .= " ORDER BY ci.data DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $arquivo_log_id, $pagina);
    } else {
        $query .= " ORDER BY ci.data DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $arquivo_log_id);
    }
} else {
    $query = "SELECT ci.*, c.nome_colaborador as nome_responsavel FROM comentarios_imagem ci 
              JOIN colaborador c ON ci.responsavel_id = c.idcolaborador 
              WHERE ap_imagem_id = ? ORDER BY ci.data DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
}
$stmt->execute();
$result = $stmt->get_result();
$comentarios = [];

while ($comentario = $result->fetch_assoc()) {
    // Normalize imagem path: remove leading ../ if present so frontend gets consistent paths
    if (!empty($comentario['imagem'])) {
        $comentario['imagem'] = preg_replace('#^\.\./+#', '', $comentario['imagem']);
    }
    $comentario_id = $comentario['id'];
    $resQuery = $conn->prepare("SELECT id, texto, data, c.nome_colaborador as nome_responsavel FROM respostas_comentario r 
    JOIN colaborador c on r.responsavel = c.idcolaborador WHERE comentario_id = ?");
    $resQuery->bind_param('i', $comentario_id);
    $resQuery->execute();
    $resResult = $resQuery->get_result();
    $comentario['respostas'] = $resResult->fetch_all(MYSQLI_ASSOC);

    $comentarios[] = $comentario;
}

header('Content-Type: application/json');
echo json_encode($comentarios);
