<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die(json_encode(["status" => "erro", "mensagem" => "Conexão falhou: " . $conn->connect_error]));
}

function emptyToNull($value)
{
    return $value === '' ? null : $value;
}

$data = $_POST;

$imagem_id = isset($data['imagem_id']) ? (int)$data['imagem_id'] : null;
$caderno_id = (int)emptyToNull($_POST['caderno_id']);
$status_caderno = emptyToNull($_POST['status_caderno']);
$prazo_caderno = emptyToNull($_POST['prazo_caderno']);
$obs_caderno = emptyToNull($_POST['obs_caderno']);
$comp_id = (int)emptyToNull($_POST['comp_id']);
$status_comp = emptyToNull($_POST['status_comp']);
$prazo_comp = emptyToNull($_POST['prazo_comp']);
$obs_comp = emptyToNull($_POST['obs_comp']);
$model_id = (int)emptyToNull($_POST['model_id']);
$status_modelagem = emptyToNull($_POST['status_modelagem']);
$prazo_modelagem = emptyToNull($_POST['prazo_modelagem']);
$obs_modelagem = emptyToNull($_POST['obs_modelagem']);
$final_id = (int)emptyToNull($_POST['final_id']);
$status_finalizacao = emptyToNull($_POST['status_finalizacao']);
$prazo_finalizacao = emptyToNull($_POST['prazo_finalizacao']);
$obs_finalizacao = emptyToNull($_POST['obs_finalizacao']);
$pos_id = (int)emptyToNull($_POST['pos_id']);
$status_pos = emptyToNull($_POST['status_pos']);
$prazo_pos = emptyToNull($_POST['prazo_pos']);
$obs_pos = emptyToNull($_POST['obs_pos']);
$alteracao_id = (int)emptyToNull($_POST['alteracao_id']);
$status_alteracao = emptyToNull($_POST['status_alteracao']);
$prazo_alteracao = emptyToNull($_POST['prazo_alteracao']);
$obs_alteracao = emptyToNull($_POST['obs_alteracao']);
$planta_id = (int)emptyToNull($_POST['planta_id']);
$status_planta = emptyToNull($_POST['status_planta']);
$prazo_planta = emptyToNull($_POST['prazo_planta']);
$obs_planta = emptyToNull($_POST['obs_planta']);
$status_id = (int)emptyToNull($_POST['status_id']);


// Verificação de função e mapeamento
$funcao_ids = [
    'Caderno' => 1,
    'Modelagem' => 2,
    'Composição' => 3,
    'Finalização' => 4,
    'Pós-Produção' => 5,
    'Alteração' => 6,
    'Planta Humanizada' => 7
];

$textos = $_POST['textos'];
$caderno_texto = $textos['caderno'] ?? '';
$comp_texto = $textos['comp'] ?? '';
$modelagem_texto = $textos['modelagem'] ?? '';
$finalizacao_texto = $textos['finalizacao'] ?? '';
$pos_texto = $textos['pos'] ?? '';
$alteracao_texto = $textos['alteracao'] ?? '';
$planta_texto = $textos['planta'] ?? '';

// Obtém os IDs das funções correspondentes
$caderno_funcao_id = $funcao_ids[$caderno_texto] ?? null;
$comp_funcao_id = $funcao_ids[$comp_texto] ?? null;
$modelagem_funcao_id = $funcao_ids[$modelagem_texto] ?? null;
$finalizacao_funcao_id = $funcao_ids[$finalizacao_texto] ?? null;
$pos_funcao_id = $funcao_ids[$pos_texto] ?? null;
$alteracao_funcao_id = $funcao_ids[$alteracao_texto] ?? null;
$planta_funcao_id = $funcao_ids[$planta_texto] ?? null;

if ($caderno_funcao_id === null || $comp_funcao_id === null || $modelagem_funcao_id === null || $finalizacao_funcao_id === null || $pos_funcao_id === null || $alteracao_funcao_id === null || $planta_funcao_id === null) {
    echo json_encode(["status" => "erro", "mensagem" => "Erro: Função não encontrada."]);
    exit;
}
$conn->begin_transaction();

try {
    // Atualize o status da imagem
    $status_sql = "UPDATE imagens_cliente_obra SET status_id = ? WHERE idimagens_cliente_obra = ?";
    $status_stmt = $conn->prepare($status_sql);
    $status_stmt->bind_param("ii", $status_id, $imagem_id);
    $status_stmt->execute();
    $status_stmt->close();

    // Insira ou atualize dados nas funções
    $sql = "INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id, prazo, status, observacao) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            colaborador_id = VALUES(colaborador_id),
            prazo = VALUES(prazo),
            status = VALUES(status),
            observacao = VALUES(observacao)";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Erro ao preparar a declaração: " . $conn->error);
    }

    // Execute para cada função (caderno, composição, etc.)
    $stmt->bind_param("iiisss", $imagem_id, $caderno_id, $caderno_funcao_id, $prazo_caderno, $status_caderno, $obs_caderno);
    $stmt->execute();

    $stmt->bind_param("iiisss", $imagem_id, $comp_id, $comp_funcao_id, $prazo_comp, $status_comp, $obs_comp);
    $stmt->execute();

    $stmt->bind_param("iiisss", $imagem_id, $model_id, $modelagem_funcao_id, $prazo_modelagem, $status_modelagem, $obs_modelagem);
    $stmt->execute();

    $stmt->bind_param("iiisss", $imagem_id, $final_id, $finalizacao_funcao_id, $prazo_finalizacao, $status_finalizacao, $obs_finalizacao);
    $stmt->execute();

    $stmt->bind_param("iiisss", $imagem_id, $pos_id, $pos_funcao_id, $prazo_pos, $status_pos, $obs_pos);
    $stmt->execute();

    $stmt->bind_param("iiisss", $imagem_id, $alteracao_id, $alteracao_funcao_id, $prazo_alteracao, $status_alteracao, $obs_alteracao);
    $stmt->execute();

    $stmt->bind_param("iiisss", $imagem_id, $planta_id, $planta_funcao_id, $prazo_planta, $status_planta, $obs_planta);
    $stmt->execute();


    $conn->commit();

    $response['success'] = "Dados inseridos/atualizados com sucesso!";
    echo json_encode($response);
} catch (Exception $e) {
    $conn->rollback();
    $response['error'] = "Erro ao executar a declaração: " . $e->getMessage();
    echo json_encode($response);
    exit();
}

$stmt->close();
$conn->close();
