<?php
require_once __DIR__ . '/config/session_bootstrap.php';
require_once __DIR__ . '/conexaoMain.php';

header('Content-Type: application/json');

// Capturar os parÃ¢metros
$obraId = intval($_GET['obra_id'] ?? 0);

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(["error" => "Usuário não autenticado"]);
    exit;
}

$authConn = conectarBanco();
if (!improov_usuario_pode_acessar_obra($authConn, $obraId)) {
    $authConn->close();
    http_response_code(403);
    echo json_encode(["error" => "Acesso não permitido para esta obra"]);
    exit;
}
$authConn->close();

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

// Capturar os parâmetros
$statusImagem = intval($_GET['status_imagem']);
$tipoImagem = $_GET['tipo_imagem'];
$antecipada = $_GET['antecipada'] === "Antecipada" ? 1 : null; // Verifica se "Antecipada" foi selecionada

// Montar a consulta SQL com o filtro de status, tipo de imagem e antecipada
$sql = "SELECT
ico.imagem_nome,
s.nome_status AS imagem_status,
ico.prazo,
ico.antecipada,
MAX(CASE WHEN fi.funcao_id = 1 THEN fi.status END) AS caderno_status,
MAX(CASE WHEN fi.funcao_id = 2 THEN fi.status END) AS modelagem_status,
MAX(CASE WHEN fi.funcao_id = 3 THEN fi.status END) AS composicao_status,
MAX(CASE WHEN fi.funcao_id = 4 THEN fi.status END) AS finalizacao_status,
MAX(CASE WHEN fi.funcao_id = 5 THEN fi.status END) AS pos_producao_status,
MAX(CASE WHEN fi.funcao_id = 6 THEN fi.status END) AS alteracao_status,
MAX(CASE WHEN fi.funcao_id = 7 THEN fi.status END) AS planta_status,
MAX(CASE WHEN fi.funcao_id = 8 THEN fi.status END) AS filtro_status,
ico.tipo_imagem,
(SELECT SUM(CASE WHEN lf.status_novo IN (3, 4, 5) THEN 1 ELSE 0 END)
FROM log_followup lf
WHERE lf.imagem_id = ico.idimagens_cliente_obra) AS total_revisoes
FROM imagens_cliente_obra ico
LEFT JOIN funcao_imagem fi ON fi.imagem_id = ico.idimagens_cliente_obra
LEFT JOIN status_imagem s ON ico.status_id = s.idstatus
WHERE ico.obra_id = ?";

// Adicionar filtros conforme os parâmetros fornecidos
if ($statusImagem !== 0) {
    $sql .= " AND ico.status_id = ?";
}

if ($tipoImagem !== "0") {
    $sql .= " AND ico.tipo_imagem = ?";
}

if ($antecipada !== null) {
    $sql .= " AND ico.antecipada = ?";
}

$sql .= " GROUP BY ico.imagem_nome, ico.status_id, s.nome_status, ico.prazo
ORDER BY ico.idimagens_cliente_obra";

$stmt = $conn->prepare($sql);

// Vincular parâmetros com base nos filtros ativos
$types = 'i'; // tipo de dados de 'obraId'
$params = [$obraId];

if ($statusImagem !== 0) {
    $types .= 'i';
    $params[] = $statusImagem;
}

if ($tipoImagem !== "0") {
    $types .= 's';
    $params[] = $tipoImagem;
}

if ($antecipada !== null) {
    $types .= 'i';
    $params[] = $antecipada;
}

// Executa a instrução com os parâmetros corretos
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$imagem = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $imagem[] = $row;
    }
}

echo json_encode($imagem);

$stmt->close();
$conn->close();
