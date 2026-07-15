<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['logado']) || empty($_SESSION['idcolaborador'])) {
    http_response_code(401); echo json_encode(['sucesso' => false, 'erro' => 'Sessão expirada.']); exit;
}
$conn->query("CREATE TABLE IF NOT EXISTS pos_referencias_comentarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, referencia_id BIGINT UNSIGNED NOT NULL,
    texto TEXT NULL, tipo ENUM('ponto','freehand') NOT NULL DEFAULT 'ponto', x DOUBLE NULL, y DOUBLE NULL,
    path_data LONGTEXT NULL, cor VARCHAR(20) NOT NULL DEFAULT '#f59e0b', espessura TINYINT UNSIGNED NOT NULL DEFAULT 2, responsavel_id INT NOT NULL,
    data DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_ref_comentarios (referencia_id, data),
    CONSTRAINT fk_pos_ref_comentario_ref FOREIGN KEY (referencia_id) REFERENCES pos_referencias_visuais(id) ON DELETE CASCADE,
    CONSTRAINT fk_pos_ref_comentario_autor FOREIGN KEY (responsavel_id) REFERENCES colaborador(idcolaborador) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$columnCheck = $conn->query("SHOW COLUMNS FROM pos_referencias_comentarios LIKE 'espessura'");
if ($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE pos_referencias_comentarios ADD COLUMN espessura TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER cor");
}
$referenceId = (int)($_REQUEST['referencia_id'] ?? 0);
if ($referenceId <= 0) { http_response_code(400); echo json_encode(['sucesso' => false, 'erro' => 'Referência inválida.']); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt=$conn->prepare('SELECT c.*, col.nome_colaborador FROM pos_referencias_comentarios c JOIN colaborador col ON col.idcolaborador=c.responsavel_id WHERE c.referencia_id=? ORDER BY c.data, c.id');
    $stmt->bind_param('i',$referenceId); $stmt->execute(); echo json_encode(['sucesso'=>true,'comentarios'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)]); exit;
}
$texto=trim((string)($_POST['texto'] ?? '')); $tipo=$_POST['tipo'] === 'freehand' ? 'freehand' : 'ponto';
$x=isset($_POST['x']) ? (float)$_POST['x'] : null; $y=isset($_POST['y']) ? (float)$_POST['y'] : null;
$path=$_POST['path_data'] ?? null; $cor=preg_match('/^#[0-9a-fA-F]{6}$/',(string)($_POST['cor']??'')) ? $_POST['cor'] : '#f59e0b';
$espessura=max(1,min(12,(int)($_POST['espessura'] ?? 2)));
if ($texto === '' && (!$path || $tipo !== 'freehand')) { http_response_code(400); echo json_encode(['sucesso'=>false,'erro'=>'Adicione um comentário ou rabisco.']); exit; }
$author=(int)$_SESSION['idcolaborador'];
$stmt=$conn->prepare('INSERT INTO pos_referencias_comentarios (referencia_id,texto,tipo,x,y,path_data,cor,espessura,responsavel_id) VALUES (?,?,?,?,?,?,?,?,?)');
$stmt->bind_param('issddssii',$referenceId,$texto,$tipo,$x,$y,$path,$cor,$espessura,$author); $stmt->execute();
echo json_encode(['sucesso'=>true,'comentario_id'=>$conn->insert_id]);
