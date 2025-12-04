<?php
header('Content-Type: application/json');


include '../conexao.php';

// Verify DB connection (match pattern used in saveImages.php)
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sem conexão com o banco de dados.']);
    exit;
}

// Read JSON payload
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido: ' . json_last_error_msg()]);
    exit;
}

// Validate required fields
$clienteId = isset($data['opcaoCliente']) ? (int)$data['opcaoCliente'] : 0;
$obraId = isset($data['opcaoObra']) ? (int)$data['opcaoObra'] : 0;
$imagem = isset($data['imagem']) ? trim($data['imagem']) : '';
$recebimento_arquivos = $data['arquivo'] ?? null;
$data_inicio = $data['data_inicio'] ?? null;
$prazo = $data['prazo'] ?? null;
$tipo_imagem = $data['tipo'] ?? null;
$antecipada = (isset($data['antecipada']) && ($data['antecipada'] == '1' || $data['antecipada'] === 1)) ? 1 : 0;
$animacao = (isset($data['animacao']) && ($data['animacao'] == '1' || $data['animacao'] === 1)) ? 1 : 0;
$clima = $data['clima'] ?? '';

// Provide default for dias_trabalhados required by DB
$dias_trabalhados = 0;

if ($clienteId <= 0 || $obraId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cliente e obra são necessários e devem ser inteiros.']);
    exit;
}

// Normalize date fields: accept only YYYY-MM-DD, otherwise NULL
$dateFields = ['recebimento_arquivos' => &$recebimento_arquivos, 'data_inicio' => &$data_inicio, 'prazo' => &$prazo];
foreach ($dateFields as $k => &$val) {
    if ($val === '' || $val === null) {
        $val = null;
    } else {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            $val = null;
        }
    }
}
unset($val);

// Ensure clima is not null (table may require NOT NULL)
if ($clima === null) $clima = '';

// Prepare safe INSERT using binded parameters (types mirror saveImages.php)
$sql = "INSERT INTO imagens_cliente_obra (cliente_id, obra_id, imagem_nome, recebimento_arquivos, data_inicio, prazo, tipo_imagem, antecipada, animacao, clima, dias_trabalhados)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao preparar consulta: ' . $conn->error]);
    exit;
}

// Bind types: i i s s s s s i i s i => "iisssssiisi"
if (!$stmt->bind_param('iisssssiisi', $clienteId, $obraId, $imagem, $recebimento_arquivos, $data_inicio, $prazo, $tipo_imagem, $antecipada, $animacao, $clima, $dias_trabalhados)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Falha ao bind_param: ' . $stmt->error]);
    exit;
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao inserir imagem: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

$lastId = $stmt->insert_id ?? null;
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'message' => 'Imagem cadastrada com sucesso!', 'insert_id' => $lastId]);
