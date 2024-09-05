<?php
// Conexão com o banco de dados
$servername = "localhost";
$username = "root";
$password = "improov";
$dbname = "improov";

// Cria a conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verifica a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Obtém os dados do POST
$imagem_id = (int)$_POST['imagem_id'];
$caderno_id = (int)$_POST['caderno_id'];
$status_caderno = $_POST['status_caderno'];
$prazo_caderno = $_POST['prazo_caderno'];
$obs_caderno = $_POST['obs_caderno'];
$comp_id = (int)$_POST['comp_id'];
$status_comp = $_POST['status_comp'];
$prazo_comp = $_POST['prazo_comp'];
$obs_comp = $_POST['obs_comp'];
$model_id = (int)$_POST['model_id'];
$status_modelagem = $_POST['status_modelagem'];
$prazo_modelagem = $_POST['prazo_modelagem'];
$obs_modelagem = $_POST['obs_modelagem'];
$final_id = (int)$_POST['final_id'];
$status_finalizacao = $_POST['status_finalizacao'];
$prazo_finalizacao = $_POST['prazo_finalizacao'];
$obs_finalizacao = $_POST['obs_finalizacao'];
$pos_id = (int)$_POST['pos_id'];
$status_pos = $_POST['status_pos'];
$prazo_pos = $_POST['prazo_pos'];
$obs_pos = $_POST['obs_pos'];
$planta_id = (int)$_POST['planta_id'];
$status_planta_humanizada = $_POST['status_planta_humanizada'];
$prazo_planta_humanizada = $_POST['prazo_planta_humanizada'];
$obs_planta_humanizada = $_POST['obs_planta_humanizada'];

// Mapeamento dos textos para IDs das funções
$funcao_ids = [
    'Caderno' => 1,
    'Modelagem' => 2,
    'Composição' => 3,
    'Finalização' => 4,
    'Pós-Produção' => 5,
    'Planta Humanizada' => 6
];

// Obtém os textos das tags <p> do POST
$textos = $_POST['textos'];
$caderno_texto = $textos['caderno'];
$comp_texto = $textos['comp'];
$modelagem_texto = $textos['modelagem'];
$finalizacao_texto = $textos['finalizacao'];
$pos_texto = $textos['pos'];
$planta_humanizada_texto = $textos['planta_humanizada'];

// Obtém os IDs das funções correspondentes
$caderno_funcao_id = $funcao_ids[$caderno_texto] ?? null;
$comp_funcao_id = $funcao_ids[$comp_texto] ?? null;
$modelagem_funcao_id = $funcao_ids[$modelagem_texto] ?? null;
$finalizacao_funcao_id = $funcao_ids[$finalizacao_texto] ?? null;
$pos_funcao_id = $funcao_ids[$pos_texto] ?? null;
$planta_funcao_id = $funcao_ids[$planta_humanizada_texto] ?? null;

// Verifica se todas as funções foram mapeadas corretamente
if ($caderno_funcao_id === null || $comp_funcao_id === null || $modelagem_funcao_id === null || $finalizacao_funcao_id === null || $pos_funcao_id === null || $planta_funcao_id === null) {
    die("Erro: Função não encontrada.");
}

$sql = "INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id, prazo, status, observacao) 
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        colaborador_id = VALUES(colaborador_id),
        prazo = VALUES(prazo),
        status = VALUES(status),
        observacao = VALUES(observacao)";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Erro ao preparar a declaração: " . $conn->error);
}

// Inicia uma transação
$conn->begin_transaction();

try {
    // Executa a declaração para cada função
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

    $stmt->bind_param("iiisss", $imagem_id, $planta_id, $planta_funcao_id, $prazo_planta_humanizada, $status_planta_humanizada, $obs_planta_humanizada);
    $stmt->execute();

    // Confirma a transação
    $conn->commit();
    echo "Dados inseridos/atualizados com sucesso!";
} catch (Exception $e) {
    // Em caso de erro, desfaz a transação
    $conn->rollback();
    die("Erro ao executar a declaração: " . $e->getMessage());
}

// Fecha a declaração e a conexão
$stmt->close();
$conn->close();
