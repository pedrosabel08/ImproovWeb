<?php
// Inicie a sessão
session_start();

// Verifique se o usuário está autenticado
if (!isset($_SESSION['idusuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

// Inclua a conexão com o banco de dados
include '../conexao.php';

require_once __DIR__ . '/vendor/autoload.php'; // Para garantir que o Composer seja carregado

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$slackToken = $_ENV['SLACK_TOKEN'] ?? null;

function enviarNotificacaoSlack($slackUserId, $mensagem)
{
    global $slackToken;

    $slackMessage = [
        "channel" => $slackUserId,
        "text" => $mensagem,
    ];

    $slackMessageUrl = "https://slack.com/api/chat.postMessage";
    $ch = curl_init($slackMessageUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$slackToken}",
        "Content-Type: application/json",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($slackMessage));

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo json_encode(['success' => false, 'message' => 'Erro ao enviar mensagem para o Slack: ' . curl_error($ch)]);
        exit;
    }

    $responseData = json_decode($response, true);

    if (!$responseData['ok']) {
        echo json_encode(['success' => false, 'message' => 'Erro ao enviar mensagem para o Slack: ' . $responseData['error']]);
        exit;
    }

    curl_close($ch);
}

// Verifique se a solicitação é POST e contém os dados necessários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Leia os dados enviados via JSON
    $data = json_decode(file_get_contents('php://input'), true);
    $idfuncao_imagem = $data['idfuncao_imagem'] ?? null;
    $tipoRevisao = $data['tipoRevisao'] ?? null;
    $imagem_nome = $data['imagem_nome'] ?? null;
    $nome_funcao = $data['nome_funcao'] ?? null;
    $colaborador_id = $data['colaborador_id'] ?? null;
    $responsavel = $data['responsavel'] ?? null;
    $nome_colaborador = 'Pedro Sabel'; // Ajuste conforme necessário


    if ($idfuncao_imagem === null || $tipoRevisao === null) {
        echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
        exit;
    }

    // Primeiro: pega o nome do responsável
    $stmt2 = $conn->prepare("SELECT nome_colaborador FROM colaborador WHERE idcolaborador = ?");
    $stmt2->bind_param("i", $responsavel);
    $stmt2->execute();
    $stmt2->bind_result($nome_responsavel);
    $stmt2->fetch();
    $stmt2->close();


    // Depois: define status, check e mensagem Slack
    switch ($tipoRevisao) {
        case "aprovado":
            $status = "Aprovado";
            $check_funcao = 1;
            $mensagemSlack = "A {$nome_funcao} da {$imagem_nome} está revisada por {$nome_responsavel}!";
            break;
        case "ajuste":
            $status = "Ajuste";
            $check_funcao = 0;
            $mensagemSlack = "A {$nome_funcao} da {$imagem_nome} possui alteração, analisada por {$nome_responsavel}! Acesse: <https://improov.com.br/sistema/Revisao | Clique aqui para revisar>";
            break;
        case "aprovado_com_ajustes":
            $status = "Aprovado com ajustes";
            $check_funcao = 1;
            $mensagemSlack = "A {$nome_funcao} da {$imagem_nome} foi aprovada com ajustes por {$nome_responsavel}.";
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Tipo de revisão inválido.']);
            exit;
    }


    $stmt = $conn->prepare("UPDATE funcao_imagem SET status = ?, check_funcao = ? WHERE idfuncao_imagem = ?");
    $stmt->bind_param("sii", $status, $check_funcao, $idfuncao_imagem);

    if ($stmt->execute()) {
        $stmt->close();

        $status_anterior = "Em aprovação"; // ou você pode buscar o real anterior se quiser
        $stmt = $conn->prepare("INSERT INTO historico_aprovacoes 
            (funcao_imagem_id, status_anterior, status_novo, colaborador_id, responsavel) 
            VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issii", $idfuncao_imagem, $status_anterior, $status, $colaborador_id, $responsavel);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Tarefa atualizada com sucesso.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar tarefa.']);
    }

    // Buscar o ID do usuário no Slack
    $url = "https://slack.com/api/users.list";

    // Configuração do cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$slackToken}",
        "Content-Type: application/json",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Executar cURL
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar usuários do Slack: ' . curl_error($ch)]);
        exit;
    }

    $responseData = json_decode($response, true);

    // Verifique se a resposta do Slack foi bem-sucedida
    if (!$responseData['ok']) {
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar usuários do Slack: ' . $responseData['error']]);
        exit;
    }

    curl_close($ch);

    // Encontrar o ID do usuário com base no nome
    $userID = null;
    foreach ($responseData['members'] as $member) {
        if (isset($member['real_name']) && strtolower($member['real_name']) === strtolower($nome_colaborador)) {
            $userID = $member['id'];
            break;
        }
    }

    // Verifique se encontramos o ID do usuário
    if ($userID === null) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado no Slack.']);
        exit;
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

    // Prepare a consulta para buscar os dados da função
    $query = "SELECT imagem_id, funcao_id FROM funcao_imagem WHERE idfuncao_imagem = ?";
    $stmt3 = $conn->prepare($query);
    $stmt3->bind_param("i", $idfuncao_imagem);
    $stmt3->execute();
    $result = $stmt3->get_result();
    $dadosFuncao = $result->fetch_assoc();

    $funcaoAtualId = $dadosFuncao['funcao_id'];
    $imagemId = $dadosFuncao['imagem_id'];

    // Criar um array de chaves para buscar a posição da função atual
    $chaves = array_keys($ordemFuncoes);
    $posicaoAtual = array_search($funcaoAtualId, $chaves);

    if ($posicaoAtual !== false && isset($chaves[$posicaoAtual + 1])) {
        $proximaFuncaoId = $chaves[$posicaoAtual + 1];

        // Buscar no banco a próxima função dessa imagem
        $query = "SELECT fi.idfuncao_imagem, fi.colaborador_id, c.nome_colaborador, i.imagem_nome
            FROM funcao_imagem fi
            JOIN colaborador c ON fi.colaborador_id = c.idcolaborador
            JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra
            WHERE fi.imagem_id = ? AND fi.funcao_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $imagemId, $proximaFuncaoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $proximaFuncao = $result->fetch_assoc();
    }

    // Verifica se o status exige notificar o próximo
    if (in_array($status, ['Aprovado', 'Aprovado com ajustes']) && $proximaFuncao) {
        $nomeResponsavel = $proximaFuncao['nome_colaborador'];
        $mensagem = "Olá, *{$nomeResponsavel}*! A etapa *{$ordemFuncoes[$funcaoAtualId]}* da imagem *{$imagem_nome}* foi concluída. 🚀";

        enviarNotificacaoSlack($userID, $mensagem);
    }

    // Configuração da mensagem do Slack
    $slackMessage = [
        "channel" => $userID,
        "text" => $mensagemSlack
    ];

    // Enviar mensagem usando cURL
    $slackMessageUrl = "https://slack.com/api/chat.postMessage";
    $ch = curl_init($slackMessageUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$slackToken}",
        "Content-Type: application/json",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($slackMessage));

    // Executar cURL
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo json_encode(['success' => false, 'message' => 'Erro ao enviar mensagem para o Slack: ' . curl_error($ch)]);
        exit;
    }

    $responseData = json_decode($response, true);

    // Verificar se a resposta foi bem-sucedida
    if (!$responseData['ok']) {
        echo json_encode(['success' => false, 'message' => 'Erro ao enviar mensagem para o Slack: ' . $responseData['error']]);
        exit;
    }

    curl_close($ch);
} else {
    echo json_encode(['success' => false, 'message' => 'Método de solicitação inválido.']);
}

// Feche a conexão com o banco de dados
$conn->close();
