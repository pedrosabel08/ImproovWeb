<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
include '../conexao.php';
require_once __DIR__ . '/upload_comentario_vps.php';

// Suporta tanto JSON quanto multipart (quando há imagem)
$comentario_id = null;
$texto = '';
$mencionados = [];
if (!empty($_FILES['imagem'])) {
    $comentario_id = intval($_POST['comentario_id'] ?? 0);
    $texto         = $_POST['texto'] ?? '';
    $mencionados   = json_decode($_POST['mencionados'] ?? '[]', true) ?: [];
} else {
    $data = json_decode(file_get_contents("php://input"), true);
    $comentario_id = intval($data['comentario_id'] ?? 0);
    $texto         = $data['texto'] ?? '';
    $mencionados   = $data['mencionados'] ?? [];
}

$responsavel = $_SESSION['idcolaborador'];

$imagem_url = null;
if (!empty($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
    try {
        $imagem_url = uploadComentarioVps($_FILES['imagem']);
    } catch (Exception $e) {
        echo json_encode(["erro" => "Falha ao enviar imagem: " . $e->getMessage()]);
        exit;
    }
}

if ($imagem_url !== null) {
    $stmt = $conn->prepare("INSERT INTO respostas_comentario (comentario_id, texto, responsavel, imagem) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isis', $comentario_id, $texto, $responsavel, $imagem_url);
} else {
    $stmt = $conn->prepare("INSERT INTO respostas_comentario (comentario_id, texto, responsavel) VALUES (?, ?, ?)");
    $stmt->bind_param('isi', $comentario_id, $texto, $responsavel);
}
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $resposta_id = $stmt->insert_id;

    // Salva menções da resposta
    if (!empty($mencionados)) {
        $stmtMencao = $conn->prepare("INSERT INTO mencoes (resposta_id, mencionado_id) VALUES (?, ?)");
        foreach ($mencionados as $mid) {
            $mid = intval($mid);
            if ($mid > 0) {
                $stmtMencao->bind_param('ii', $resposta_id, $mid);
                $stmtMencao->execute();
            }
        }
        $stmtMencao->close();

        // Slack DM para cada mencionado
        require_once __DIR__ . '/mencao_slack_helper.php';
        $stmtCtx = $conn->prepare(
            "SELECT fun.nome_funcao, ico.imagem_nome, o.nome_obra
             FROM comentarios_imagem c
             INNER JOIN historico_aprovacoes_imagens hai ON hai.id = c.ap_imagem_id
             INNER JOIN funcao_imagem fi ON fi.idfuncao_imagem = hai.funcao_imagem_id
             INNER JOIN funcao fun ON fun.idfuncao = fi.funcao_id
             INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
             INNER JOIN obra o ON o.idobra = ico.obra_id
             WHERE c.id = ? LIMIT 1"
        );
        $stmtCtx->bind_param('i', $comentario_id);
        $stmtCtx->execute();
        $ctxMencao = $stmtCtx->get_result()->fetch_assoc();
        $stmtCtx->close();
        enviarSlackMencoes(
            $conn,
            $mencionados,
            $_SESSION['nome_usuario'] ?? 'Alguém',
            $ctxMencao['nome_funcao'] ?? '',
            $ctxMencao['imagem_nome'] ?? '',
            $ctxMencao['nome_obra'] ?? '',
            $responsavel
        );
    }

    $result = [
        "id"               => $resposta_id,
        "texto"            => $texto,
        "data"             => date("Y-m-d H:i:s"),
        "nome_responsavel" => $_SESSION['nome_usuario'],
    ];
    if ($imagem_url !== null) {
        $result['imagem'] = $imagem_url;
    }
    echo json_encode($result);
} else {
    echo json_encode(["erro" => "Erro ao salvar resposta"]);
}
