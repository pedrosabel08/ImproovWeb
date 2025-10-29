<?php
/**
 * Envia um resumo diário para um canal do Slack com as funções com status
 * "Em andamento" cujo prazo já passou (atrasadas).
 *
 * Uso: agende este script para rodar todo dia às 08:00 (cron / agendador Windows).
 * Configure as constantes SLACK_TOKEN e SLACK_CHANNEL abaixo.
 */

// --- Configurações (ajuste conforme seu ambiente) -----------------------
// Valores sensíveis foram movidos para `scripts/.env`.
// O arquivo `.env` deve conter linhas no formato: KEY="value"
// Exemplo:
// SLACK_TOKEN="xoxb-..."
// SLACK_CHANNEL="C09P9C6..."
// SLACK_API_URL="https://slack.com/api/chat.postMessage"

// Carrega um .env simples (chave=valor) e popula getenv/$_ENV/$_SERVER
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        // remove aspas simples/duplas
        $value = trim($value, "'\"");
        putenv("$name=$value");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

define('SLACK_TOKEN', getenv('SLACK_TOKEN') ?: '');
define('SLACK_CHANNEL', getenv('SLACK_CHANNEL') ?: '');
define('SLACK_API_URL', getenv('SLACK_API_URL') ?: 'https://slack.com/api/chat.postMessage');

// DB (use as credenciais usadas no projeto)
$dbHost = 'mysql.improov.com.br';
$dbName = 'improov';
$dbUser = 'improov';
$dbPass = 'Impr00v';

// -------------------------------------------------------------------------

function enviarMensagemSlack($canal, $mensagem)
{
    $payload = [
        'channel' => $canal,
        'text' => $mensagem,
        'mrkdwn' => true
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
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new Exception('cURL error: ' . $err);
    }

    $resultado = json_decode($resposta, true);
    if (!$resultado || empty($resultado['ok'])) {
        $errMsg = $resultado['error'] ?? 'unknown_error';
        throw new Exception('Slack API error: ' . $errMsg . ' - response: ' . $resposta);
    }

    return true;
}

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Busca funções em andamento com prazo anterior a hoje
    $sql = "SELECT fi.idfuncao_imagem, fi.prazo, fi.imagem_id, i.imagem_nome,
                    fi.funcao_id, f.nome_funcao, fi.colaborador_id, c.nome_colaborador
            FROM funcao_imagem fi
            LEFT JOIN funcao f ON f.idfuncao = fi.funcao_id
            LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
            LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
			LEFT JOIN obra o ON o.idobra = i.obra_id
            WHERE fi.status = 'Em andamento' AND fi.prazo IS NOT NULL AND o.status_obra = 0
              AND DATE(fi.prazo) < CURDATE()
            ORDER BY fi.prazo ASC";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows || count($rows) === 0) {
        echo "Nenhuma tarefa em andamento atrasada encontrada.\n";
        exit(0);
    }

    // Monta a mensagem usando markdown simples
    $lines = [];
    $lines[] = ":rotating_light: *Tarefas atrasadas (Em andamento)* — " . count($rows) . " itens";
    $lines[] = "\n";

    foreach ($rows as $r) {
        $imagem = $r['imagem_nome'] ?: ('#' . ($r['imagem_id'] ?? '')); 
        $funcao = $r['nome_funcao'] ?: ('id:' . ($r['funcao_id'] ?? '-'));
        $colab = $r['nome_colaborador'] ?: ('id:' . ($r['colaborador_id'] ?? '-'));
        $prazo = $r['prazo'] ? date('Y-m-d', strtotime($r['prazo'])) : '-';

        $lines[] = "• *Imagem:* {$imagem}  — *Função:* {$funcao}  — *Colaborador:* {$colab}  — *Prazo:* {$prazo}";
    }

    $message = implode("\n", $lines);

    // Envia para o canal configurado
    enviarMensagemSlack(SLACK_CHANNEL, $message);
    echo "Mensagem enviada ao Slack (canal=" . SLACK_CHANNEL . ") com " . count($rows) . " itens.\n";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(2);
}

?>
