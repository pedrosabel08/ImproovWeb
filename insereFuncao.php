<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die(json_encode(["status" => "erro", "mensagem" => "Conexão falhou: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

function emptyToNull($value)
{
    return $value === '' ? null : $value;
}

$data = $_POST;
$imagem_id = isset($data['imagem_id']) ? (int)$data['imagem_id'] : null;
$status_id = (int)emptyToNull($data['status_id']);

// Função para processar cada seção de dados
function processSection($conn, $imagem_id, $colaborador_id, $funcao_id, $prazo, $status, $obs)
{
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
    $stmt->bind_param("iiisss", $imagem_id, $colaborador_id, $funcao_id, $prazo, $status, $obs);
    $stmt->execute();
    $stmt->close();
}

// Mapeamento de funções
$funcao_ids = [
    'Caderno' => 1,
    'Modelagem' => 2,
    'Composição' => 3,
    'Finalização' => 4,
    'Pós-Produção' => 5,
    'Alteração' => 6,
    'Planta Humanizada' => 7,
    'Filtro de assets' => 8
];

$textos = $data['textos'];
$sections = [
    'Caderno' => ['id' => (int)emptyToNull($data['caderno_id']), 'status' => emptyToNull($data['status_caderno']), 'prazo' => emptyToNull($data['prazo_caderno']), 'obs' => emptyToNull($data['obs_caderno'])],
    'Composição' => ['id' => (int)emptyToNull($data['comp_id']), 'status' => emptyToNull($data['status_comp']), 'prazo' => emptyToNull($data['prazo_comp']), 'obs' => emptyToNull($data['obs_comp'])],
    'Modelagem' => ['id' => (int)emptyToNull($data['model_id']), 'status' => emptyToNull($data['status_modelagem']), 'prazo' => emptyToNull($data['prazo_modelagem']), 'obs' => emptyToNull($data['obs_modelagem'])],
    'Finalização' => ['id' => (int)emptyToNull($data['final_id']), 'status' => emptyToNull($data['status_finalizacao']), 'prazo' => emptyToNull($data['prazo_finalizacao']), 'obs' => emptyToNull($data['obs_finalizacao'])],
    'Pós-Produção' => ['id' => (int)emptyToNull($data['pos_id']), 'status' => emptyToNull($data['status_pos']), 'prazo' => emptyToNull($data['prazo_pos']), 'obs' => emptyToNull($data['obs_pos'])],
    'Alteração' => ['id' => (int)emptyToNull($data['alteracao_id']), 'status' => emptyToNull($data['status_alteracao']), 'prazo' => emptyToNull($data['prazo_alteracao']), 'obs' => emptyToNull($data['obs_alteracao'])],
    'Planta Humanizada' => ['id' => (int)emptyToNull($data['planta_id']), 'status' => emptyToNull($data['status_planta']), 'prazo' => emptyToNull($data['prazo_planta']), 'obs' => emptyToNull($data['obs_planta'])],
    'Filtro de assets' => ['id' => (int)emptyToNull($data['filtro_id']), 'status' => emptyToNull($data['status_filtro']), 'prazo' => emptyToNull($data['prazo_filtro']), 'obs' => emptyToNull($data['obs_filtro'])]
];

// Verifica se todas as funções existem
foreach ($sections as $secao => $info) {
    if (!isset($funcao_ids[$secao])) {
        echo json_encode(["status" => "erro", "mensagem" => "Erro: Função '$secao' não encontrada."]);
        exit;
    }
}

$conn->begin_transaction();
try {
    // Atualizar o status da imagem
    $status_sql = "UPDATE imagens_cliente_obra SET status_id = ? WHERE idimagens_cliente_obra = ?";
    $status_stmt = $conn->prepare($status_sql);
    $status_stmt->bind_param("ii", $status_id, $imagem_id);
    $status_stmt->execute();
    $status_stmt->close();

    // Itera sobre cada seção (Caderno, Composição, etc.) e insere/atualiza os dados
    foreach ($sections as $secao => $info) {
        $funcao_id = $funcao_ids[$secao];
        processSection($conn, $imagem_id, $info['id'], $funcao_id, $info['prazo'], $info['status'], $info['obs']);
    }

    $conn->commit();
    echo json_encode(["success" => "Dados inseridos/atualizados com sucesso!"]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["error" => "Erro ao executar a transação: " . $e->getMessage()]);
}

$conn->close();
