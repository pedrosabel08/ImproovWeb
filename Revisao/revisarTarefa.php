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

$slackToken = $_ENV['SLACK_TOKEN'] ?? null;  // Tenta acessar via $_ENV
// var_dump($slackToken);  // Verifica se o token foi carregado

// Verifique se a solicitação é POST e contém os dados necessários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Leia os dados enviados via JSON
    $data = json_decode(file_get_contents('php://input'), true);
    $idfuncao_imagem = $data['idfuncao_imagem'] ?? null;
    // $nome_colaborador = $data['nome_colaborador'] ?? null;
    $nome_colaborador = 'Pedro Sabel';
    $imagem_nome = $data['imagem_nome'] ?? null;
    $nome_funcao = $data['nome_funcao'] ?? null;

    // Verifique se o ID da função foi fornecido
    if ($idfuncao_imagem === null) {
        echo json_encode(['success' => false, 'message' => 'ID da tarefa não fornecido.']);
        exit;
    }

    // Prepare a consulta para atualizar a tarefa
    $stmt = $conn->prepare("UPDATE funcao_imagem SET check_funcao = 1 WHERE idfuncao_imagem = ?");
    if ($stmt) {
        $stmt->bind_param("i", $idfuncao_imagem);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Tarefa atualizada com sucesso.']);

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

            // Enviar mensagem para o Slack com o ID do usuário
            $slackMessage = [
                "channel" => $userID,  // Usando o ID do usuário encontrado
                "text" => "A {$nome_funcao} da {$imagem_nome} está revisada!",
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
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar a tarefa.']);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao preparar a consulta.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método de solicitação inválido.']);
}

// Feche a conexão com o banco de dados
$conn->close();
