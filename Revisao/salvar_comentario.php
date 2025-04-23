<?php
include '../conexao.php';
session_start();

// Agora os dados vêm de $_POST e $_FILES
$ap_imagem_id = $_POST['ap_imagem_id'];
$x = $_POST['x'];
$y = $_POST['y'];
$texto = $_POST['texto'];
$responsavel = $_SESSION['idcolaborador'];

// Processar imagem (se enviada)
$imagem_path = null;
if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/comentarios/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true); // Cria a pasta se não existir
    }

    $filename = uniqid('coment_', true) . '.' . pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
    $imagem_path = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['imagem']['tmp_name'], $imagem_path)) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'erro' => 'Falha ao salvar imagem.']);
        exit;
    }
}

// 1. Buscar o número do comentário
$stmt = $conn->prepare('SELECT IFNULL(MAX(numero_comentario), 0) + 1 AS proximo_numero FROM comentarios_imagem WHERE ap_imagem_id = ?');
$stmt->bind_param('i', $ap_imagem_id);
$stmt->execute();
$result = $stmt->get_result();
$numero_comentario = $result->fetch_assoc()['proximo_numero'];

// 2. Inserir comentário
$stmt = $conn->prepare('
    INSERT INTO comentarios_imagem (ap_imagem_id, numero_comentario, x, y, texto, imagem, responsavel_id, data)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
');
$stmt->bind_param('iiddssi', $ap_imagem_id, $numero_comentario, $x, $y, $texto, $imagem_path, $responsavel);
$stmt->execute();

$comentario_id = $conn->insert_id; // ID do comentário recém-inserido

// 3. Procurar por menções (@Nome)
preg_match_all('/@([\wÀ-ú]+)/u', $texto, $matches);
$mencionados = $matches[1]; // Array de nomes mencionados

if (!empty($mencionados)) {
    // 4. Buscar IDs dos colaboradores mencionados
    $placeholders = implode(',', array_fill(0, count($mencionados), '?'));
    $tipos = str_repeat('s', count($mencionados));
    $stmt = $conn->prepare("SELECT idcolaborador FROM colaborador WHERE nome_colaborador IN ($placeholders)");
    $stmt->bind_param($tipos, ...$mencionados);
    $stmt->execute();
    $result = $stmt->get_result();

    // 5. Inserir menções
    $stmtInsert = $conn->prepare("INSERT INTO mencoes (comentario_id, mencionado_id) VALUES (?, ?)");
    while ($row = $result->fetch_assoc()) {
        $mencionado_id = $row['idcolaborador'];
        $stmtInsert->bind_param("ii", $comentario_id, $mencionado_id);
        $stmtInsert->execute();
    }
}

$response = [
    'sucesso' => true,
    'comentario_id' => $comentario_id // Caso precise usar depois
];

header('Content-Type: application/json');
echo json_encode($response);
