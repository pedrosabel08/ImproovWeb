<?php
header('Content-Type: application/json');

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

$colaboradorId = intval($_GET['colaborador_id']);
$mes = isset($_GET['mes']) ? $_GET['mes'] : '';
$ano = isset($_GET['ano']) ? $_GET['ano'] : '';
$obraId = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : '';
$funcaoId = isset($_GET['funcao_id']) ? intval($_GET['funcao_id']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$prioridade = isset($_GET['prioridade']) ? $_GET['prioridade'] : '';

$sql = "SELECT
            ico.idimagens_cliente_obra AS imagem_id,
            ico.imagem_nome,
            fi.status,
            fi.prazo,
            f.nome_funcao,
            pc.prioridade,
            fi.idfuncao_imagem,
            fi.funcao_id,
            ico.obra_id
        FROM funcao_imagem fi
        JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
        JOIN funcao f on fi.funcao_id = f.idfuncao
        JOIN prioridade_funcao pc ON fi.idfuncao_imagem = pc.funcao_imagem_id
        WHERE fi.colaborador_id = ?";

if ($mes) {
    $sql .= " AND MONTH(fi.prazo) = ?";
}
if ($ano) {
    $sql .= " AND YEAR(fi.prazo) = ?";
}
if ($obraId) {
    $sql .= " AND ico.obra_id = ?";
}
if ($funcaoId) {
    $sql .= " AND f.idfuncao = ?";
}
if ($status) {
    $sql .= " AND fi.status = ?";
}
if ($prioridade) {
    $sql .= " AND pc.prioridade = ?";
}

$sql .= " ORDER BY FIELD(fi.status,'Não iniciado', 'Em andamento', 'Ajuste', 'Em aprovação', 'Aprovado com ajustes', 'Aprovado', 'Finalizado'), pc.prioridade ASC, imagem_id";

$stmt = $conn->prepare($sql);

$bindParams = [$colaboradorId];
$types = 'i';

if ($mes) {
    $types .= 's';
    $bindParams[] = $mes;
}
if ($ano) {
    $types .= 's';
    $bindParams[] = $ano;
}
if ($obraId) {
    $types .= 'i';
    $bindParams[] = $obraId;
}
if ($funcaoId) {
    $types .= 'i';
    $bindParams[] = $funcaoId;
}
if ($status) {
    $types .= 's';
    $bindParams[] = $status;
}
if ($prioridade) {
    $types .= 's';
    $bindParams[] = $prioridade;
}

$stmt->bind_param($types, ...$bindParams);

$stmt->execute();
$result = $stmt->get_result();


$funcoes = [];
while ($row = $result->fetch_assoc()) {
    $funcoes[] = $row;
}

$response = [];

$ordemFuncoes = [
    1 => 'Caderno',
    8 => 'Filtro de assets',
    2 => 'Modelagem',
    3 => 'Composição',
    9 => 'Pré-Finalização',
    4 => 'Finalização',
    5 => 'Pós-produção',
    6 => 'Alteração',
    7 => 'Planta Humanizada'
];

foreach ($funcoes as $funcao) {
    // Obtendo o ID da função atual
    $funcaoAtualId = $funcao['funcao_id'];
    $imagemId = $funcao['imagem_id']; // Adicionando o ID da imagem para a consulta

    // Verificando a função anterior com base no array $ordemFuncoes
    $ordem = array_keys($ordemFuncoes); // Invertendo o array para facilitar a busca
    $indiceAtual = array_search($funcaoAtualId, $ordem);

    $statusAnterior = null;
    $liberada = false;
    $funcaoAnteriorId = null;
    $prazoAnterior = null;

    if ($indiceAtual !== false) {
        // Tentar pegar a última função anterior válida
        for ($i = $indiceAtual - 1; $i >= 0; $i--) {
            $funcaoAnteriorId = $ordem[$i];

            // Agora buscamos o status da função anterior
            $sqlAnterior = "SELECT fi.status, fi.prazo
                            FROM funcao_imagem fi
                            WHERE fi.imagem_id = ? AND fi.funcao_id = ?";
            $stmtAnterior = $conn->prepare($sqlAnterior);
            $stmtAnterior->bind_param('ii', $imagemId, $funcaoAnteriorId);
            $stmtAnterior->execute();
            $resultAnterior = $stmtAnterior->get_result();

            if ($rowAnterior = $resultAnterior->fetch_assoc()) {
                $statusAnterior = $rowAnterior['status'];
                $prazoAnterior = $rowAnterior['prazo'];
                $stmtAnterior->close();
                break; // Encontrou a função anterior válida, sair do loop
            }

            $stmtAnterior->close();
        }
    }

    // Verificando se a imagem pode ser liberada
    if ($statusAnterior && ($statusAnterior == 'Finalizado' || $statusAnterior == 'Aprovado' || $statusAnterior = 'Aprovado com ajustes')) {
        $liberada = true;
    }

    // Adicionando a função ao response com o status de "liberação" da imagem e status anterior
    $response[] = [
        'imagem_id' => $funcao['imagem_id'],
        'imagem_nome' => $funcao['imagem_nome'],
        'status' => $funcao['status'],
        'prazo' => $funcao['prazo'],
        'nome_funcao' => $funcao['nome_funcao'],
        'prioridade' => $funcao['prioridade'],
        'funcao_id' => $funcao['funcao_id'],
        'status_funcao_anterior' => $statusAnterior,
        'prazo_funcao_anterior' => $prazoAnterior,
        'liberada' => $liberada,
        'funcaoAnteriorId' => $funcaoAnteriorId,
        'obra_id' => $funcao['obra_id']
    ];
}

echo json_encode($response);

$stmt->close();
$conn->close();
