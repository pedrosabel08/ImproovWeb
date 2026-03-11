<?php
// Mostrar erros em ambiente de desenvolvimento (remover em produção)
require_once __DIR__ . '/config/session_bootstrap.php';
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/config/secure_env.php';

$slack_webhook_url = improov_env('SLACK_WEBHOOK_DAILY_URL', null);
if (!$slack_webhook_url) {
    error_log('submit_respostas.php: SLACK_WEBHOOK_DAILY_URL ausente no .env');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: configuração Slack ausente.']);
    exit;
}

// Inclui conexão e inicia sessão
include 'conexao.php';
if (!isset($conn) || !$conn) {
    error_log('submit_respostas.php: conexão ($conn) não inicializada por conexao.php');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: falha na conexão ao banco.']);
    exit;
}

// session_start();

// Verifique se os dados foram enviados
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $finalizado = $_POST['finalizado'] ?? null;
    $hoje = $_POST['hoje'] ?? null;
    $bloqueio = $_POST['bloqueio'] ?? null;
    $colaborador_id = $_SESSION['idcolaborador'];
    date_default_timezone_set('America/Sao_Paulo');
    $data = date('Y-m-d');

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
