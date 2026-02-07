<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

include_once __DIR__ . '/../conexao.php';

$historico_id = isset($_POST['historico_id']) ? intval($_POST['historico_id']) : 0;

if (!$historico_id || !isset($_FILES['imagem'])) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

// Busca contexto do histórico: funcao_imagem e imagem
$funcao_imagem_id = null;
$imagem_id = null;
$funcao_id = null;
$status_nome = null;

if (
    $st = $conn->prepare("SELECT hi.funcao_imagem_id, f.imagem_id, f.funcao_id, s.nome_status
    FROM historico_aprovacoes_imagens hi
    JOIN funcao_imagem f ON f.idfuncao_imagem = hi.funcao_imagem_id
    JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
    JOIN status_imagem s ON s.idstatus = i.status_id
    WHERE hi.id = ?
    LIMIT 1")
) {
    $st->bind_param('i', $historico_id);
    $st->execute();
    $st->bind_result($funcao_imagem_id, $imagem_id, $funcao_id, $status_nome);
    $st->fetch();
    $st->close();
}

$status_nome_norm = mb_strtolower(trim((string) $status_nome), 'UTF-8');
$isP00 = ($status_nome_norm === 'p00');

if ((int) $funcao_id !== 4 || !$isP00) {
    echo json_encode([
        'success' => false,
        'message' => 'Substituição disponível apenas para P00 + Finalização.',
        'debug' => [
            'funcao_id' => $funcao_id,
            'status_nome' => $status_nome
        ]
    ]);
    exit;
}

$file = $_FILES['imagem'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Falha no upload.']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
    echo json_encode(['success' => false, 'message' => 'Formato inválido. Use JPG/PNG.']);
    exit;
}

$destDir = __DIR__ . '/../uploads/renders';
if (!is_dir($destDir)) {
    @mkdir($destDir, 0777, true);
}

$novoNome = uniqid('render_angle_', true) . '.' . $ext;
$destPathFs = $destDir . '/' . $novoNome;

if (!move_uploaded_file($file['tmp_name'], $destPathFs)) {
    echo json_encode(['success' => false, 'message' => 'Não foi possível salvar o arquivo.']);
    exit;
}

$relPath = 'uploads/renders/' . $novoNome;

// Atualiza o histórico e reseta o status do ângulo para pendente
$conn->begin_transaction();
try {
    if ($up = $conn->prepare('UPDATE historico_aprovacoes_imagens SET imagem = ?, data_envio = NOW() WHERE id = ?')) {
        $up->bind_param('si', $relPath, $historico_id);
        $up->execute();
        $up->close();
    }

    if ($imagem_id) {
        if ($ang = $conn->prepare("UPDATE angulos_imagens SET liberada = 0, sugerida = 0, motivo_sugerida = '' WHERE imagem_id = ? AND historico_id = ?")) {
            $ang->bind_param('ii', $imagem_id, $historico_id);
            $ang->execute();
            $ang->close();
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'path' => $relPath]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Erro ao substituir ângulo.']);
}

$conn->close();
