<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/secure_env.php';
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['logado'] ?? false) !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}
if (!in_array((int)($_SESSION['nivel_acesso'] ?? 0), [1, 5], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit;
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/onboarding_helpers.php';
$obraId = (int)($_GET['obra_id'] ?? 0);
if ($obraId <= 0 || !improov_usuario_pode_acessar_obra($conn, $obraId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem acesso ao projeto.']);
    exit;
}

$stmt = $conn->prepare('SELECT idobra_pacote, tipo, quantidade, segundos, prazo_contratual, prazo_dias_corridos, status FROM obra_pacote WHERE obra_id=? ORDER BY idobra_pacote');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível consultar os pacotes.']);
    exit;
}
$stmt->bind_param('i', $obraId);
$stmt->execute();
$result = $stmt->get_result();
$packages = [];
$labels = ['STILL' => 'Imagens Still', 'ANIMACAO' => 'Animações 3D', 'FILME' => 'Filmes'];
while ($row = $result->fetch_assoc()) {
    $type = strtoupper((string)$row['tipo']);
    if (!isset($labels[$type])) continue;
    $row['tipo'] = $type;
    $row['label'] = $labels[$type];
    $packages[] = $row;
}
$stmt->close();
$conn->close();
echo json_encode(['success' => true, 'packages' => $packages], JSON_UNESCAPED_UNICODE);
