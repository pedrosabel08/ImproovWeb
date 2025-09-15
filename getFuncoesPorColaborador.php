<?php
header('Content-Type: application/json');

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}
$conn->set_charset('utf8mb4');

$colaboradorId = intval($_GET['colaborador_id']);
$mes = $_GET['mes'] ?? '';
$ano = $_GET['ano'] ?? '';
$obraId = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : '';
$funcaoId = $_GET['funcao_id'] ?? '';
$status = $_GET['status'] ?? '';
$funcaoIds = $funcaoId ? explode(',', $funcaoId) : [];
$statusList = $status ? explode(',', $status) : [];
$prioridade = $_GET['prioridade'] ?? '';

// ====================
// FUNÇÕES
// ====================
$sql = "SELECT
            ico.idimagens_cliente_obra AS imagem_id,
            ico.imagem_nome,
            fi.status,
            fi.prazo,
            f.nome_funcao,
            pc.prioridade,
            fi.idfuncao_imagem,
            fi.funcao_id,
            ico.obra_id,
            o.nomenclatura
        FROM funcao_imagem fi
        JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
        JOIN obra o ON o.idobra = ico.obra_id
        JOIN funcao f on fi.funcao_id = f.idfuncao
        JOIN prioridade_funcao pc ON fi.idfuncao_imagem = pc.funcao_imagem_id
        WHERE fi.colaborador_id = ? AND o.status_obra = 0";

if ($mes) $sql .= " AND MONTH(fi.prazo) = ?";
if ($ano) $sql .= " AND YEAR(fi.prazo) = ?";
if ($obraId) $sql .= " AND ico.obra_id = ?";
if ($funcaoId && count($funcaoIds) > 0) {
    $in = implode(',', array_fill(0, count($funcaoIds), '?'));
    $sql .= " AND f.idfuncao IN ($in)";
}
if ($status && count($statusList) > 0) {
    $inStatus = implode(',', array_fill(0, count($statusList), '?'));
    $sql .= " AND fi.status IN ($inStatus)";
}
if ($prioridade) $sql .= " AND pc.prioridade = ?";

$sql .= " ORDER BY prazo DESC, obra_id, FIELD(fi.status,'Não iniciado', 'Em andamento', 'Ajuste', 'Em aprovação', 'Aprovado com ajustes', 'Aprovado', 'Finalizado'), imagem_id";

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
foreach ($funcaoIds as $fid) {
    $types .= 'i';
    $bindParams[] = intval($fid);
}
foreach ($statusList as $st) {
    $types .= 's';
    $bindParams[] = $st;
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

// ====================
// TAREFAS
// ====================
$sqlTarefas = "SELECT id, titulo, descricao, prazo, status, prioridade 
               FROM tarefas 
               WHERE colaborador_id = ? ORDER BY prazo DESC";
$stmtTarefas = $conn->prepare($sqlTarefas);
$stmtTarefas->bind_param("i", $colaboradorId);
$stmtTarefas->execute();
$resultTarefas = $stmtTarefas->get_result();

$tarefas = [];
while ($row = $resultTarefas->fetch_assoc()) {
    $tarefas[] = $row;
}

// ====================
// Ajusta Funções (liberação, ordem, etc.)
// ====================
$imagemIds = array_column($funcoes, 'imagem_id');
$todasFuncoes = [];

if (count($imagemIds) > 0) {
    $inImagem = implode(',', array_fill(0, count($imagemIds), '?'));
    $sqlTodasFuncoes = "SELECT imagem_id, funcao_id, status, prazo FROM funcao_imagem WHERE imagem_id IN ($inImagem)";
    $stmtTodas = $conn->prepare($sqlTodasFuncoes);
    $typesTodas = str_repeat('i', count($imagemIds));
    $stmtTodas->bind_param($typesTodas, ...$imagemIds);
    $stmtTodas->execute();
    $resultTodas = $stmtTodas->get_result();

    while ($row = $resultTodas->fetch_assoc()) {
        $todasFuncoes[$row['imagem_id']][$row['funcao_id']] = $row;
    }
    $stmtTodas->close();
}

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

$funcoesFinal = [];
foreach ($funcoes as $funcao) {
    $funcaoAtualId = $funcao['funcao_id'];
    $imagemId = $funcao['imagem_id'];

    $ordemIds = array_keys($ordemFuncoes);
    $indiceAtual = array_search($funcaoAtualId, $ordemIds);

    $statusAnterior = null;
    $liberada = false;
    $funcaoAnteriorId = null;
    $prazoAnterior = null;

    if ($indiceAtual !== false && $indiceAtual > 0 && isset($todasFuncoes[$imagemId])) {
        for ($i = $indiceAtual - 1; $i >= 0; $i--) {
            $funcaoAnteriorId = $ordemIds[$i];
            if (isset($todasFuncoes[$imagemId][$funcaoAnteriorId])) {
                $rowAnterior = $todasFuncoes[$imagemId][$funcaoAnteriorId];
                $statusAnterior = $rowAnterior['status'];
                $prazoAnterior = $rowAnterior['prazo'];
                if (in_array($statusAnterior, ['Finalizado', 'Aprovado', 'Aprovado com ajustes'])) {
                    $liberada = true;
                }
                break;
            }
        }
    }

    $funcoesFinal[] = [
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
        'obra_id' => $funcao['obra_id'],
        'nomenclatura' => $funcao['nomenclatura'],
        'idfuncao_imagem' => $funcao['idfuncao_imagem']
    ];
}

// ====================
// RESPONSE FINAL ÚNICO
// ====================
$response = [
    "funcoes" => $funcoesFinal,
    "tarefas" => $tarefas
];

echo json_encode($response);

$stmt->close();
$stmtTarefas->close();
$conn->close();
