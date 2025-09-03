<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

include 'conexao.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['nome'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Nome não fornecido']);
    exit;
}

$nome = $conn->real_escape_string($data['nome']);

// Buscar obra_id
$sql = "SELECT obra_id FROM contatos WHERE nome='$nome'";
$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Contato não encontrado']);
    exit;
}

$row = $result->fetch_assoc();
$obra_id = $row['obra_id'];

// Inserção de novos itens
if (isset($data['section'])) {
    $section = $data['section'];

    if ($section === 'tarefas' && isset($data['descricao'], $data['data'])) {
        $descricao = $conn->real_escape_string($data['descricao']);
        $data_criacao = $conn->real_escape_string($data['data']);
        $sqlInsert = "INSERT INTO tarefas (obra_id, descricao, status, data_criacao) 
                      VALUES ('$obra_id', '$descricao', 'Pendente', '$data_criacao')";
        if ($conn->query($sqlInsert)) {
            echo json_encode(['status' => 'ok', 'mensagem' => 'Tarefa inserida com sucesso']);
        } else {
            echo json_encode(['status' => 'erro', 'mensagem' => $conn->error]);
        }
        exit;
    }

    if ($section === 'acomp' && isset($data['assunto'], $data['data'])) {
        $assunto = $conn->real_escape_string($data['assunto']);
        $data_acomp = $conn->real_escape_string($data['data']);
        $sqlInsert = "INSERT INTO acompanhamento_email (obra_id, assunto, data) 
                      VALUES ('$obra_id', '$assunto', '$data_acomp')";
        if ($conn->query($sqlInsert)) {
            echo json_encode(['status' => 'ok', 'mensagem' => 'Acompanhamento inserido com sucesso']);
        } else {
            echo json_encode(['status' => 'erro', 'mensagem' => $conn->error]);
        }
        exit;
    }

    // Seções fictícias
    if ($section === 'arquivos' || $section === 'eventos') {
        echo json_encode(['status' => 'ok', 'mensagem' => 'Item fictício inserido']);
        exit;
    }
}

// Buscar dados existentes das seções
$response = [];

// Tarefas
$sqlTarefas = "SELECT descricao, status, data_criacao FROM tarefas WHERE obra_id='$obra_id' ORDER BY data_criacao DESC";
$res = $conn->query($sqlTarefas);
$tarefas = [];
if ($res) while ($r = $res->fetch_assoc()) $tarefas[] = $r;
$response['tarefas'] = $tarefas;

// Acompanhamentos
$sqlAcomp = "SELECT assunto, data FROM acompanhamento_email WHERE obra_id='$obra_id' ORDER BY data DESC";
$res = $conn->query($sqlAcomp);
$acomp = [];
if ($res) while ($r = $res->fetch_assoc()) $acomp[] = $r;
$response['acompanhamentos'] = $acomp;

// Seções fictícias
$response['arquivos'] = [
    ['nome' => 'Arquivo exemplo 1.pdf', 'data' => '2025-09-01'],
    ['nome' => 'Arquivo exemplo 2.docx', 'data' => '2025-09-02']
];
$response['eventos'] = [
    ['evento' => 'Reunião de obra', 'data' => '2025-09-05'],
    ['evento' => 'Entrega de material', 'data' => '2025-09-06']
];

$response['status'] = 'ok';
$response['mensagem'] = 'Dados carregados';

echo json_encode($response);

$conn->close();
