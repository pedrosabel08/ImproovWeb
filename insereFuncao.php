<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include 'conexao.php';

function emptyToNull($value)
{
    return ($value !== '' && $value !== null) ? $value : null;
}

$data = $_POST;
$imagem_id = isset($data['imagem_id']) ? (int)$data['imagem_id'] : null;
$status_id = isset($data['status_id']) ? (int)$data['status_id'] : null;


$funcao_ids = [
    'Caderno' => 1,
    'Modelagem' => 2,
    'Composição' => 3,
    'Finalização' => 4,
    'Pós-Produção' => 5,
    'Alteração' => 6,
    'Planta Humanizada' => 7,
    'Filtro de assets' => 8,
    'Pré-Finalização' => 9
];

$funcao_parametros = [
    'Caderno' => 'caderno',
    'Modelagem' => 'modelagem',
    'Composição' => 'comp',
    'Finalização' => 'finalizacao',
    'Pós-Produção' => 'pos',
    'Alteração' => 'alteracao',
    'Planta Humanizada' => 'planta',
    'Filtro de assets' => 'filtro',
    'Pré-Finalização' => 'pre'
];

$conn->begin_transaction();

try {
    $update_image_status = $conn->prepare("UPDATE imagens_cliente_obra SET status_id = ? WHERE idimagens_cliente_obra = ?");
    $update_image_status->bind_param('ii', $status_id, $imagem_id);
    $update_image_status->execute();
    $update_image_status->close();

    $stmt = $conn->prepare("INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id, prazo, status, observacao, check_funcao)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE colaborador_id = VALUES(colaborador_id), prazo = VALUES(prazo), 
                            status = VALUES(status), observacao = VALUES(observacao), check_funcao = VALUES(check_funcao)");

    foreach ($funcao_ids as $funcao => $funcao_id) {
        $parametro = $funcao_parametros[$funcao];

        // Verifica se o parâmetro existe no array $data
        if (!empty($data[$parametro . '_id'])) {
            
            $colaborador_id = (int)emptyToNull($data[$parametro . '_id']);

            $prazo = emptyToNull($data['prazo_' . $parametro]);
            $status = emptyToNull($data['status_' . $parametro]);
            $obs = emptyToNull($data['obs_' . $parametro]);
            $check_funcao = isset($data['check_' . $parametro]) ? 1 : 0;

            // Verifique se o colaborador_id existe na tabela colaborador
            $check_colaborador = $conn->prepare("SELECT COUNT(*) FROM colaborador WHERE idcolaborador = ?");
            $check_colaborador->bind_param("i", $colaborador_id);
            $check_colaborador->execute();
            $check_colaborador->bind_result($exists);
            $check_colaborador->fetch();
            $check_colaborador->close();

            if (!$exists) {
                throw new Exception("Colaborador ID $colaborador_id não encontrado na tabela colaborador. parametro_id = {$parametro}_id");
            }

            $stmt->bind_param("iiisssi", $imagem_id, $colaborador_id, $funcao_id, $prazo, $status, $obs, $check_funcao);
            $stmt->execute();
        }
    }

    $stmt->close();
    $conn->commit();

    echo json_encode(['success' => 'Dados inseridos/atualizados com sucesso!']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => 'Erro ao executar a transação: ' . $e->getMessage()]);
}

$conn->close();
