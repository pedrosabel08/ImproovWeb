<?php
// Temporariamente habilita exibição de erros para diagnosticar 500
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

include '../conexao.php';

// Verifica conexão
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sem conexão com o banco de dados.']);
    exit;
}

// Lê os dados enviados pelo JavaScript
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido: ' . json_last_error_msg(), 'raw' => $raw]);
    exit;
}

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nenhum dado recebido ou formato inválido.']);
    exit;
}

// Prepara a consulta SQL para atualizar as informações
$sql = "UPDATE imagens_cliente_obra 
    SET recebimento_arquivos = ?, 
        data_inicio = ?, 
        prazo = ?, 
        imagem_nome = ?, 
        tipo_imagem = ?,
        antecipada = ?,
        animacao = ?,
        clima = ?
    WHERE idimagens_cliente_obra = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao preparar a consulta SQL.', 'error' => $conn->error]);
    exit;
}

// Itera pelos dados e executa as atualizações
$success = true;
$errors = [];
foreach ($data as $idx => $image) {
    // Valida campos e aplica valores padrão
    $recebimento_arquivos = isset($image['recebimento_arquivos']) ? $image['recebimento_arquivos'] : null;
    $data_inicio = isset($image['data_inicio']) ? $image['data_inicio'] : null;
    $prazo = isset($image['prazo']) ? $image['prazo'] : null;
    $imagem_nome = isset($image['imagem_nome']) ? $image['imagem_nome'] : null;
    $tipo_imagem = isset($image['tipo_imagem']) ? $image['tipo_imagem'] : null;
    $antecipada = (isset($image['antecipada']) && ($image['antecipada'] == '1' || $image['antecipada'] === 1)) ? 1 : 0;
    $animacao = (isset($image['animacao']) && ($image['animacao'] == '1' || $image['animacao'] === 1)) ? 1 : 0;
    $clima = isset($image['clima']) ? $image['clima'] : null;
    $idimagem = isset($image['idimagem']) ? (int)$image['idimagem'] : 0;

    if ($idimagem <= 0) {
        $errors[] = "item $idx: idimagem inválido";
        $success = false;
        continue;
    }

    // Normaliza valores de data: MySQL em modos estritos não aceita string vazia para DATE
    $dateFields = ['recebimento_arquivos' => &$recebimento_arquivos, 'data_inicio' => &$data_inicio, 'prazo' => &$prazo];
    foreach ($dateFields as $key => &$val) {
        if ($val === '' || $val === null) {
            $val = null; // força NULL para o campo DATE
        } else {
            // aceita apenas formato YYYY-MM-DD, caso contrário força NULL
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                $val = null;
            }
        }
    }
    unset($val);

    // `clima` é NOT NULL na tabela; evita passar NULL
    if ($clima === null) $clima = '';

    // Tipos: recebimento_arquivos(s or null), data_inicio(s or null), prazo(s or null), imagem_nome(s), tipo_imagem(s), antecipada(i), animacao(i), clima(s), idimagem(i)
    if (!$stmt->bind_param("sssssiisi", $recebimento_arquivos, $data_inicio, $prazo, $imagem_nome, $tipo_imagem, $antecipada, $animacao, $clima, $idimagem)) {
        $errors[] = "bind_param falhou no item $idx: " . $stmt->error;
        $success = false;
        continue;
    }

    if (!$stmt->execute()) {
        $errors[] = "execute falhou no item $idx (id $idimagem): " . $stmt->error;
        $success = false;
        continue;
    }
}

// Fecha a consulta e a conexão
$stmt->close();
$conn->close();

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Alterações salvas com sucesso!']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar alterações.', 'errors' => $errors]);
}
