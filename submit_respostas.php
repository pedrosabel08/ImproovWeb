<?php
require_once __DIR__ . '/Revisao/vendor/autoload.php'; // Instale via composer require omarusman/ics-parser

use ICal\ICal;

use Dotenv\Dotenv;

$envPath = __DIR__ . '/Revisao/.env';

if (!file_exists($envPath)) {
    die("Arquivo .env não encontrado em: $envPath");
}

$dotenv = Dotenv::createImmutable(__DIR__ . '/Revisao');
$dotenv->load();

$webhookUrl = $_ENV['SLACK_WEBHOOK_DAILY_URL'] ?? null;

if (!$webhookUrl) {
    die('Erro: Variável SLACK_WEBHOOK_URL não encontrada no .env');
}

include 'conexao.php';
session_start();

// Verifique se os dados foram enviados
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $finalizado = $_POST['finalizado'] ?? null;
    $hoje = $_POST['hoje'] ?? null;
    $bloqueio = $_POST['bloqueio'] ?? null;
    $colaborador_id = $_SESSION['idcolaborador'];
    $data = date('Y-m-d'); // Data atual

    // Verifique se todos os campos obrigatórios foram preenchidos
    if (!$finalizado || !$hoje || !$bloqueio || !$colaborador_id) {
        echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);
        exit;
    }

    // Prepare o comando SQL para inserir os dados
    $sql = "INSERT INTO respostas_diarias (colaborador_id, data, finalizado, hoje, bloqueio) 
            VALUES (?, ?, ?, ?, ?)";

    // Prepare a consulta
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Bind dos parâmetros
        $stmt->bind_param('issss', $colaborador_id, $data, $finalizado, $hoje, $bloqueio);

        // Executa a inserção
        if ($stmt->execute()) {
            // Enviar mensagem ao Slack
            $slack_webhook_url = 'https://hooks.slack.com/services/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'; // Substitua pela URL do seu webhook
            $colaborador_nome = $_SESSION['nome_usuario'] ?? 'Desconhecido'; // Certifique-se de que o nome do colaborador está na sessão
            $mensagem = [
                'text' => "*$colaborador_nome* respondeu o questionário: \n✅ *Finalizado:* $finalizado\n*⏳ Hoje:* $hoje\n*🚧 Bloqueio:* $bloqueio"
            ];

            // Enviar a mensagem usando cURL
            $ch = curl_init($slack_webhook_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($mensagem));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            curl_close($ch);

            echo json_encode(['success' => true, 'message' => 'Respostas enviadas com sucesso.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar as respostas no banco de dados.']);
        }

        // Fecha o statement
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao preparar a consulta.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método de requisição inválido.']);
}
