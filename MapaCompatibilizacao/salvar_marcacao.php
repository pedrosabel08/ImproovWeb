<?php
/**
 * salvar_marcacao.php
 * Insere ou atualiza uma marcação de ambiente na planta.
 * Usa INSERT … ON DUPLICATE KEY UPDATE sobre a UNIQUE (planta_id, nome_ambiente).
 *
 * POST params (JSON body ou form-data):
 *   planta_id        (int)
 *   nome_ambiente    (string)
 *   imagem_id        (int|null)
 *   coordenadas_json (string JSON)
 */

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';

header('Content-Type: application/json; charset=utf-8');

// --- Auth ---
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit();
}

$nivelAcesso = (int) ($_SESSION['nivel_acesso'] ?? 0);
if (!in_array($nivelAcesso, [1, 2])) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'erro' => 'Sem permissão para salvar marcações.']);
    exit();
}

// --- Aceita tanto JSON body quanto form-data ---
$body = [];
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $body = json_decode($rawInput, true) ?? [];
}
$input = array_merge($body, $_POST);

$plantaId = isset($input['planta_id']) ? (int) $input['planta_id'] : 0;
$nomeAmbiente = isset($input['nome_ambiente']) ? trim($input['nome_ambiente']) : '';
$imagemId = isset($input['imagem_id']) && $input['imagem_id'] !== '' && $input['imagem_id'] !== null
    ? (int) $input['imagem_id']
    : null;
$coordenadas = isset($input['coordenadas_json']) ? $input['coordenadas_json'] : '';
$criadoPor = (int) ($_SESSION['idcolaborador'] ?? 0);

// --- Validações ---
if ($plantaId <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'planta_id inválido.']);
    exit();
}
if ($nomeAmbiente === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'nome_ambiente é obrigatório.']);
    exit();
}
if ($coordenadas === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'coordenadas_json é obrigatório.']);
    exit();
}

// Validar se coordenadas_json é JSON válido
json_decode($coordenadas);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['sucesso' => false, 'erro' => 'coordenadas_json inválido.']);
    exit();
}

$conn = conectarBanco();

// --- Confirmar que a planta pertence a uma obra activa ---
$stmtCheck = $conn->prepare(
    "SELECT id FROM planta_compatibilizacao WHERE id = ? AND ativa = 1 LIMIT 1"
);
$stmtCheck->bind_param('i', $plantaId);
$stmtCheck->execute();
$plantaExiste = $stmtCheck->get_result()->fetch_assoc();
$stmtCheck->close();

if (!$plantaExiste) {
    $conn->close();
    echo json_encode(['sucesso' => false, 'erro' => 'Planta não encontrada ou inativa.']);
    exit();
}

// --- Upsert ---
$sql = "
    INSERT INTO planta_marcacoes
        (planta_id, nome_ambiente, imagem_id, coordenadas_json, criado_por)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        imagem_id        = VALUES(imagem_id),
        coordenadas_json = VALUES(coordenadas_json)
";

$stmtUpsert = $conn->prepare($sql);
$stmtUpsert->bind_param('isisi', $plantaId, $nomeAmbiente, $imagemId, $coordenadas, $criadoPor);

if (!$stmtUpsert->execute()) {
    $erro = $stmtUpsert->error;
    $stmtUpsert->close();
    $conn->close();
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar: ' . $erro]);
    exit();
}

// Obter o ID (seja INSERT novo ou UPDATE existente)
$novoId = (int) $conn->insert_id;
if ($novoId === 0) {
    // ON DUPLICATE KEY UPDATE — buscar o id existente
    $stmtId = $conn->prepare(
        "SELECT id FROM planta_marcacoes WHERE planta_id = ? AND nome_ambiente = ? LIMIT 1"
    );
    $stmtId->bind_param('is', $plantaId, $nomeAmbiente);
    $stmtId->execute();
    $novoId = (int) $stmtId->get_result()->fetch_assoc()['id'];
    $stmtId->close();
}

$stmtUpsert->close();
$conn->close();

echo json_encode(['sucesso' => true, 'id' => $novoId]);
