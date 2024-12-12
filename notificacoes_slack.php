<?php
// Configuração do token do Slack
define('SLACK_TOKEN', '?');
define('SLACK_API_URL', 'https://slack.com/api/chat.postMessage');

// Função para enviar mensagem ao Slack
function enviarMensagemSlack($canal, $mensagem)
{
    $payload = [
        'channel' => $canal,
        'text' => $mensagem,
    ];



    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SLACK_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . SLACK_TOKEN,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $resposta = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Erro no cURL: ' . curl_error($ch);
    }

    curl_close($ch);

    $resultado = json_decode($resposta, true);

    if ($resultado['ok']) {
        echo "Mensagem enviada ao canal $canal com sucesso.\n";
    } else {
        echo "Erro ao enviar mensagem ao canal $canal: " . $resultado['error'] . "\n";
    }
}

// Configurações do banco de dados
$host = 'mysql.improov.com.br';
$dbname = 'improov';
$user = 'improov';
$password = 'Impr00v';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Definição das consultas e canais
    $consultas = [
        [
            'canal' => '#teste2', // Substitua pelo canal correto no Slack
            'query' => "SELECT funcao_id, status, check_funcao, imagem_id, colaborador_id 
                        FROM funcao_imagem 
                        WHERE funcao_id BETWEEN 2 AND 3 AND check_funcao = 0 AND status = 'Em aprovação'"
        ],
        [
            'canal' => '#teste2',
            'query' => "SELECT funcao_id, status, check_funcao, imagem_id, colaborador_id 
                        FROM funcao_imagem 
                        WHERE funcao_id = 4 AND check_funcao = 0 AND status = 'Em aprovação'"
        ]
        // Adicione mais consultas e canais, se necessário
    ];

    // Executa cada consulta e envia notificações ao Slack
    foreach ($consultas as $consulta) {
        $stmt = $pdo->query($consulta['query']);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($resultados) > 0) {
            foreach ($resultados as $linha) {
                $link = "https://improov.com.br/Revisao/";  // Substitua pelo seu link real
                $mensagem = "⚠️ Tarefa pendente encontrada:\n";
                $mensagem .= "- Função: {$linha['funcao_id']}\n";
                $mensagem .= "- Status: {$linha['status']}\n";
                $mensagem .= "- ID da Imagem: {$linha['imagem_id']}\n";
                $mensagem .= "Clique aqui para mais detalhes: <{$link}|Detalhes>";

                // Envia a mensagem ao canal Slack
                enviarMensagemSlack($consulta['canal'], $mensagem);
            }
        } else {
            echo "Nenhuma tarefa pendente para o canal {$consulta['canal']}.\n";
        }
    }
} catch (PDOException $e) {
    echo "Erro na conexão com o banco de dados: " . $e->getMessage();
}
