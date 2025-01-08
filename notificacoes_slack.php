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
            //Modelagem
            'canal' => 'C087WRC2ZME', // Substitua pelo canal correto no Slack
            'query' => "SELECT  f.idfuncao_imagem,
                            f.funcao_id, 
                        fun.nome_funcao, 
                        f.status, 
                        f.check_funcao, 
                        f.imagem_id, 
                        i.imagem_nome, 
                        f.colaborador_id, 
                        c.nome_colaborador
                        FROM funcao_imagem f
                    LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
                    LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
                    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
                    WHERE f.funcao_id = 2 AND f.check_funcao = 0 AND f.status = 'Em aprovação'"
        ],
        [
            //Composição
            'canal' => 'C087LMQJLGH', // Substitua pelo canal correto no Slack
            'query' => "SELECT  f.idfuncao_imagem,
                            f.funcao_id, 
                        fun.nome_funcao, 
                        f.status, 
                        f.check_funcao, 
                        f.imagem_id, 
                        i.imagem_nome, 
                        f.colaborador_id, 
                        c.nome_colaborador
                        FROM funcao_imagem f
                    LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
                    LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
                    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
                    WHERE f.funcao_id = 3 AND f.check_funcao = 0 AND f.status = 'Em aprovação'"
        ],
        [
            //Finalização
            'canal' => 'C086TFA7JJ3',
            'query' => "SELECT  f.idfuncao_imagem,
                            f.funcao_id, 
                        fun.nome_funcao, 
                        f.status, 
                        f.check_funcao, 
                        f.imagem_id, 
                        i.imagem_nome, 
                        f.colaborador_id, 
                        c.nome_colaborador
                        FROM funcao_imagem f
                    LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
                    LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
                    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
                    WHERE f.funcao_id = 4 AND f.check_funcao = 0 AND f.status = 'Em aprovação'"
        ],
        [
            //Pós-produção
            'canal' => 'C08781CH95G',
            'query' => "SELECT  f.idfuncao_imagem,
                            f.funcao_id, 
                        fun.nome_funcao, 
                        f.status, 
                        f.check_funcao, 
                        f.imagem_id, 
                        i.imagem_nome, 
                        f.colaborador_id, 
                        c.nome_colaborador
                        FROM funcao_imagem f
                    LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
                    LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
                    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
                    WHERE f.funcao_id = 5 AND f.check_funcao = 0 AND f.status = 'Em aprovação'"
        ],
        [
            //Planta Humanizada
            'canal' => 'C087FR3640J',
            'query' => "SELECT  f.idfuncao_imagem,
                            f.funcao_id, 
                        fun.nome_funcao, 
                        f.status, 
                        f.check_funcao, 
                        f.imagem_id, 
                        i.imagem_nome, 
                        f.colaborador_id, 
                        c.nome_colaborador
                        FROM funcao_imagem f
                    LEFT JOIN funcao fun ON fun.idfuncao = f.funcao_id
                    LEFT JOIN colaborador c ON c.idcolaborador = f.colaborador_id
                    LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
                    WHERE f.funcao_id = 7 AND f.check_funcao = 0 AND f.status = 'Em aprovação'"
        ]

    ];

    // Executa cada consulta e envia notificações ao Slack
    foreach ($consultas as $consulta) {
        $stmt = $pdo->query($consulta['query']);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $link = "https://improov.com.br/sistema/Revisao/";  // Substitua pelo seu link real

        if (count($resultados) > 0) {
            if (count($resultados) > 2) {
                // Mensagem genérica para mais de 2 tarefas pendentes
                $mensagem = "⚠️ Existem " . count($resultados) . " imagens pendentes para revisão.\n";
                $mensagem .= "Clique aqui para mais detalhes: <{$link}|Detalhes>";

                // Envia a mensagem ao canal Slack
                enviarMensagemSlack($consulta['canal'], $mensagem);
            } else {
                // Notificação individual para até 2 tarefas pendentes
                foreach ($resultados as $linha) {
                    $mensagem = "⚠️ Tarefa pendente encontrada:\n";
                    $mensagem .= "- Função: {$linha['nome_funcao']}\n";
                    $mensagem .= "- Status: {$linha['status']}\n";
                    $mensagem .= "Clique aqui para mais detalhes: <{$link}|Detalhes>";

                    // Envia a mensagem ao canal Slack
                    enviarMensagemSlack($consulta['canal'], $mensagem);
                }
            }
        } else {
            echo "Nenhuma tarefa pendente para o canal {$consulta['canal']}.\n";
        }
    }
} catch (PDOException $e) {
    echo "Erro na conexão com o banco de dados: " . $e->getMessage();
}
