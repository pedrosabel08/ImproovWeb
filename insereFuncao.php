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
    return isset($value) ? $value : null;
}
$data = $_POST;
$imagem_id = isset($data['imagem_id']) ? (int)$data['imagem_id'] : null;
$status_id = (int)emptyToNull($data['status_id']);


// Função para processar cada seção de dados
function processSection($conn, $imagem_id, $colaborador_id, $funcao_id, $prazo, $status, $obs, $check_funcao)
{
    $check_funcao = $check_funcao ? 1 : 0;

    $sql = "INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id, prazo, status, observacao, check_funcao) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            colaborador_id = VALUES(colaborador_id),
            prazo = VALUES(prazo),
            status = VALUES(status),
            observacao = VALUES(observacao),
            check_funcao = VALUES(check_funcao)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Erro ao preparar a declaração: " . $conn->error);
    }
    $stmt->bind_param("iiisssi", $imagem_id, $colaborador_id, $funcao_id, $prazo, $status, $obs, $check_funcao);
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
    'Filtro de assets' => 8,
    'Pré-Finalização' => 9
];

$textos = $data['textos'];
$sections = [
    'Caderno' => ['id' => (int)emptyToNull($data['caderno_id']), 'status' => emptyToNull($data['status_caderno']), 'prazo' => emptyToNull($data['prazo_caderno']), 'obs' => emptyToNull($data['obs_caderno']), 'check_funcao' => isset($data['check_caderno']) ? (int)$data['check_caderno'] : 0],
    'Composição' => ['id' => (int)emptyToNull($data['comp_id']), 'status' => emptyToNull($data['status_comp']), 'prazo' => emptyToNull($data['prazo_comp']), 'obs' => emptyToNull($data['obs_comp']), 'check_funcao' => isset($data['check_comp']) ? (int)$data['check_comp'] : 0],
    'Modelagem' => ['id' => (int)emptyToNull($data['model_id']), 'status' => emptyToNull($data['status_modelagem']), 'prazo' => emptyToNull($data['prazo_modelagem']), 'obs' => emptyToNull($data['obs_modelagem']), 'check_funcao' => isset($data['check_model']) ? (int)$data['check_model'] : 0],
    'Finalização' => ['id' => (int)emptyToNull($data['final_id']), 'status' => emptyToNull($data['status_finalizacao']), 'prazo' => emptyToNull($data['prazo_finalizacao']), 'obs' => emptyToNull($data['obs_finalizacao']),         'check_funcao' => isset($data['check_final']) ? (int)$data['check_final'] : 0],
    'Pós-Produção' => ['id' => (int)emptyToNull($data['pos_id']), 'status' => emptyToNull($data['status_pos']), 'prazo' => emptyToNull($data['prazo_pos']), 'obs' => emptyToNull($data['obs_pos']), 'check_funcao' => isset($data['check_pos']) ? (int)$data['check_pos'] : 0],
    'Alteração' => ['id' => (int)emptyToNull($data['alteracao_id']), 'status' => emptyToNull($data['status_alteracao']), 'prazo' => emptyToNull($data['prazo_alteracao']), 'obs' => emptyToNull($data['obs_alteracao']), 'check_funcao' => isset($data['check_alt']) ? (int)$data['check_alt'] : 0],
    'Planta Humanizada' => ['id' => (int)emptyToNull($data['planta_id']), 'status' => emptyToNull($data['status_planta']), 'prazo' => emptyToNull($data['prazo_planta']), 'obs' => emptyToNull($data['obs_planta']), 'check_funcao' => isset($data['check_planta']) ? (int)$data['check_planta'] : 0],
    'Filtro de assets' => ['id' => (int)emptyToNull($data['filtro_id']), 'status' => emptyToNull($data['status_filtro']), 'prazo' => emptyToNull($data['prazo_filtro']), 'obs' => emptyToNull($data['obs_filtro']), 'check_funcao' => isset($data['check_filtro']) ? (int)$data['check_filtro'] : 0],
    'Pré-Finalização' => ['id' => (int)emptyToNull($data['pre_id']), 'status' => emptyToNull($data['status_pre']), 'prazo' => emptyToNull($data['prazo_pre']), 'obs' => emptyToNull($data['obs_pre']), 'check_funcao' => isset($data['check_pre']) ? (int)$data['check_pre'] : 0]
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

    // Variáveis para armazenar os nomes
    $funcao_status = [];
    $maior_funcao_nome = "";
    $maior_funcao_status = "";
    $imagem_nome = "";

    foreach ($sections as $secao => $info) {
        $funcao_id = $funcao_ids[$secao];

        // Insira ou atualize os dados da função
        processSection($conn, $imagem_id, $info['id'], $funcao_id, $info['prazo'], $info['status'], $info['obs'], $info['check_funcao']);

        // Obter o nome da função a partir do ID
        $sql_funcao = "SELECT nome_funcao FROM funcao WHERE idfuncao = ?";
        $stmt_funcao = $conn->prepare($sql_funcao);
        $stmt_funcao->bind_param("i", $funcao_id);
        $stmt_funcao->execute();
        $result_funcao = $stmt_funcao->get_result();

        if ($result_funcao->num_rows > 0) {
            $funcao_nome = $result_funcao->fetch_assoc()['nome_funcao'];
        } else {
            $funcao_nome = 'Desconhecida'; // Caso não encontre o nome da função
        }

        // Obter o nome da imagem
        $sql_imagem = "SELECT imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?";
        $stmt_imagem = $conn->prepare($sql_imagem);
        $stmt_imagem->bind_param("i", $imagem_id);
        $stmt_imagem->execute();
        $result_imagem = $stmt_imagem->get_result();

        if ($result_imagem->num_rows > 0) {
            $imagem_nome = $result_imagem->fetch_assoc()['imagem_nome'];
        } else {
            $imagem_nome = 'Desconhecida'; // Caso não encontre o nome da imagem
        }

        // Adiciona os dados da função e imagem ao array
        $funcao_status[] = [
            'funcao_nome' => $funcao_nome,  // Nome da função
            'imagem_nome' => $imagem_nome,  // Nome da imagem
            'status' => $info['status']     // Status
        ];

        // Verifica se a função atual tem o maior ID
        if ($funcao_id > $maior_funcao_id) {
            $maior_funcao_id = $funcao_id;
            $maior_funcao_nome = $funcao_nome; // Nome da função
        }
    }

    $conn->commit();
    echo json_encode([
        "success" => "Dados inseridos/atualizados com sucesso!",
        "funcao_nome" => $maior_funcao_nome,  // Nome da função com maior ID
        "imagem_nome" => $imagem_nome,       // Nome da imagem
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["error" => "Erro ao executar a transação: " . $e->getMessage()]);
}

$conn->close();
