<?php

// Enable verbose errors for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

header("Access-Control-Allow-Origin: *"); // Allows all domains
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Allow specific methods
header("Access-Control-Allow-Headers: Content-Type");

// Basic sanity checks to avoid silent 500s
// Check DB connection file
if (!file_exists(__DIR__ . '/../conexao.php')) {
    http_response_code(500);
    echo "Missing required file: conexao.php";
    error_log('Pos-Producao/inserir_pos_producao.php: conexao.php not found');
    exit;
}

// Conectar ao banco de dados central
include_once __DIR__ . '/../conexao.php';

date_default_timezone_set('America/Sao_Paulo');

// Check for composer autoload (Dotenv)
if (!file_exists(__DIR__ . '/../Revisao/vendor/autoload.php')) {
    http_response_code(500);
    echo "Missing composer autoload (Revisao/vendor/autoload.php). Run 'composer install' in Revisao/ directory.";
    error_log('Pos-Producao/inserir_pos_producao.php: vendor/autoload.php not found');
    exit;
}

require_once __DIR__ . '/../Revisao/vendor/autoload.php';

use Dotenv\Dotenv;

$envPath = __DIR__ . '/../Revisao/.env';

if (!file_exists($envPath)) {
    http_response_code(500);
    echo "Arquivo .env não encontrado em: $envPath";
    error_log('Pos-Producao/inserir_pos_producao.php: .env not found at ' . $envPath);
    exit;
}

$dotenv = Dotenv::createImmutable(__DIR__ . '/../Revisao');
$dotenv->load();

$slackWebhookUrl = $_ENV['SLACK_WEBHOOK_POS_URL'] ?? null;

if (!$slackWebhookUrl) {
    http_response_code(500);
    echo 'Erro: Variável SLACK_WEBHOOK_POS_URL não encontrada no .env';
    error_log('Pos-Producao/inserir_pos_producao.php: SLACK_WEBHOOK_POS_URL not set in .env');
    exit;
}


// Verificar se os dados foram enviados via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $colaborador_id = $_POST['final_id'];
    $obra_id = $_POST['obra_id'];
    $data_pos = date('Y-m-d H:i');
    $imagem_id = $_POST['imagem_id_pos'];
    $caminho_pasta = $_POST['caminho_pasta'];
    $numero_bg = $_POST['numero_bg'];
    $refs = $_POST['refs'];
    $obs = $_POST['obs'];
    $status_pos = isset($_POST['status_pos']) ? 0 : 1;
    $status_id = $_POST['status_id'];
    $render_id = $_POST['render_id_pos'] ?? null; // Adicionando render_id como opcional

    // Buscar o nome da imagem
    $sql_imagem = "SELECT imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?";
    $stmt_imagem = $conn->prepare($sql_imagem);
    $stmt_imagem->bind_param("i", $imagem_id);
    $stmt_imagem->execute();
    $resultado_imagem = $stmt_imagem->get_result();
    $nome_imagem = $resultado_imagem->fetch_assoc()['imagem_nome'] ?? 'Imagem não encontrada';

    // Buscar o nome da obra
    $sql_obra = "SELECT nome_obra FROM obra WHERE idobra = ?";
    $stmt_obra = $conn->prepare($sql_obra);
    $stmt_obra->bind_param("i", $obra_id);
    $stmt_obra->execute();
    $resultado_obra = $stmt_obra->get_result();
    $nome_obra = $resultado_obra->fetch_assoc()['nome_obra'] ?? 'Obra não encontrada';

    // Verificar se a obra já está cadastrada na tabela pos_producao
    $sql_verificar = "SELECT responsavel_id FROM pos_producao WHERE obra_id = ? LIMIT 1";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bind_param("i", $obra_id);
    $stmt_verificar->execute();
    $resultado_verificar = $stmt_verificar->get_result();
    $responsavel_existente = $resultado_verificar->fetch_assoc()['responsavel_id'] ?? null;

    // Verificar se um responsavel_id foi enviado pelo formulário
    if (!empty($_POST['responsavel_id'])) {
        $responsavel_id = $_POST['responsavel_id']; // Usar o valor enviado pelo formulário
    } elseif ($responsavel_existente === null) {
        $responsavel_id = rand(0, 1) ? 14 : 28; // Sorteia entre 14 ou 28 se não houver um existente
    } else {
        $responsavel_id = $responsavel_existente; // Reutiliza o responsavel_id existente
    }


    // Inserir ou atualizar dados
    $sql = "INSERT INTO pos_producao (colaborador_id, obra_id, data_pos, imagem_id, caminho_pasta, numero_bg, refs, obs, status_pos, status_id, responsavel_id, render_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            colaborador_id = VALUES(colaborador_id),
            obra_id = VALUES(obra_id),
            data_pos = VALUES(data_pos),
            caminho_pasta = VALUES(caminho_pasta),
            numero_bg = VALUES(numero_bg),
            refs = VALUES(refs),
            obs = VALUES(obs),
            status_pos = VALUES(status_pos),
            status_id = VALUES(status_id),
            responsavel_id = VALUES(responsavel_id),
            render_id = VALUES(render_id)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "iissssssiiii",
        $colaborador_id,
        $obra_id,
        $data_pos,
        $imagem_id,
        $caminho_pasta,
        $numero_bg,
        $refs,
        $obs,
        $status_pos,
        $status_id,
        $responsavel_id,
        $render_id
    );

    if ($stmt->execute()) {
        echo "Dados inseridos ou atualizados com sucesso!";

        // Definir a mensagem com base no status_pos
        if ($status_pos == 0) {
            // Notificar o Slack
            $slackMessage = [
                'text' => "A imagem $nome_imagem foi feita a pós.",
            ];

            $sqlImagem = 'UPDATE imagens_cliente_obra SET substatus_id = 8, status_id = ? WHERE idimagens_cliente_obra = ?';

            // Atualizar o status na tabela imagens_cliente_obra
            $stmtUpdate = $conn->prepare($sqlImagem);
            if ($stmtUpdate) {
                $stmtUpdate->bind_param("ii", $status_id, $imagem_id);
                if ($stmtUpdate->execute()) {
                    echo "Status da imagem atualizado com sucesso!";
                } else {
                    echo "Erro ao atualizar o status da imagem: " . $stmtUpdate->error;
                }
                $stmtUpdate->close();
            } else {
                echo "Erro ao preparar o UPDATE da imagem: " . $conn->error;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $slackWebhookUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($slackMessage));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $slackResponse = curl_exec($ch);
            if (curl_errno($ch)) {
                error_log('Erro ao enviar mensagem para o Slack: ' . curl_error($ch));
            } else {
                echo "Mensagem enviada para o Slack com sucesso!";
            }
            curl_close($ch);
        }
    } else {
        echo "Erro ao inserir ou atualizar dados: " . $conn->error;
    }

    $stmt->close();
    $stmt_imagem->close();
    $stmt_obra->close();
    $conn->close();
}
