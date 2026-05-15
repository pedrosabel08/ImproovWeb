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
define('ALERT_TYPE', 'slack_overdue_daily');

include_once __DIR__ . '/../conexao.php';

// -------------------------------------------------------------------------

function formatarDataSlack($value)
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime((string) $value);
    if (!$timestamp) {
        return '-';
    }

    return date('d/m/Y', $timestamp);
}

function montarLinhaTarefa(array $row)
{
    $imagem = $row['imagem_nome'] ?: ('#' . ($row['imagem_id'] ?? ''));
    $funcao = $row['nome_funcao'] ?: ('id:' . ($row['funcao_id'] ?? '-'));
    $colab = $row['nome_colaborador'] ?: ('id:' . ($row['colaborador_id'] ?? '-'));
    $obra = trim((string) ($row['nomenclatura'] ?? ''));
    $prazoOriginal = formatarDataSlack($row['prazo_original'] ?? null);
    $prazoAtual = formatarDataSlack($row['prazo_atual'] ?? null);
    $diasAtraso = max(1, (int) ($row['dias_em_atraso'] ?? 0));

    $linha = "• *Imagem:* {$imagem} — *Função:* {$funcao} — *Responsável:* {$colab}";
    if ($obra !== '') {
        $linha .= " — *Obra:* {$obra}";
    }
    $linha .= " — *Prazo original:* {$prazoOriginal} — *Prazo atual:* {$prazoAtual} — *Atraso:* {$diasAtraso} dia(s)";

    return $linha;
}

function quebrarMensagensSlack(array $rows)
{
    $header = ':rotating_light: *Tarefas atrasadas (Em andamento)* — ' . count($rows) . ' item(ns)';
    $limite = 3500;
    $mensagens = [];
    $buffer = $header;

    foreach ($rows as $row) {
        $line = montarLinhaTarefa($row);
        $prefixo = $buffer === $header ? "\n\n" : "\n";
        $candidate = $buffer . $prefixo . $line;

        if (strlen($candidate) > $limite && $buffer !== $header) {
            $mensagens[] = $buffer;
            $buffer = $header . "\n\n" . $line;
            continue;
        }

        $buffer = $candidate;
    }

    if ($buffer !== '') {
        $mensagens[] = $buffer;
    }

    return $mensagens;
}

function enviarMensagemSlack($canal, $mensagem, int $tentativas = 3)
{
    $payload = [
        'channel' => $canal,
        'text' => $mensagem,
        'mrkdwn' => true
    ];
    $body = json_encode($payload);
    $ultimoErro = null;

    for ($t = 1; $t <= $tentativas; $t++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, SLACK_API_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . SLACK_TOKEN,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        // Força IPv4: evita falhas silenciosas de resolução IPv6 em ambientes de cron
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $resposta = curl_exec($ch);
        $err      = curl_error($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);

        if ($err) {
            $ultimoErro = "cURL error ($errno): $err [tentativa $t/$tentativas]";
            error_log('[slack_overdue_daily] ' . $ultimoErro);
            if ($t < $tentativas) {
                sleep($t * 2); // backoff: 2s, 4s
                continue;
            }
            throw new Exception($ultimoErro);
        }

        $resultado = json_decode($resposta, true);
        if (!$resultado || empty($resultado['ok'])) {
            $errMsg = $resultado['error'] ?? 'unknown_error';
            throw new Exception('Slack API error: ' . $errMsg . ' - response: ' . $resposta);
        }

        return true;
    }

    throw new Exception($ultimoErro ?? 'Falha após ' . $tentativas . ' tentativas.');
}

try {
    if (SLACK_TOKEN === '' || SLACK_CHANNEL === '') {
        throw new Exception('SLACK_TOKEN ou SLACK_CHANNEL não configurado(s).');
    }

    if (!isset($servername, $dbname, $username, $password)) {
        throw new Exception('Variáveis de conexão não carregadas a partir de conexao.php.');
    }

    $dsn = "mysql:host={$servername};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Busca funções em andamento com prazo anterior a hoje e ainda não notificadas hoje.
    $sql = "SELECT fi.idfuncao_imagem,
                   fi.prazo AS prazo_atual,
                   fi.imagem_id,
                   i.imagem_nome,
                   o.nomenclatura,
                   fi.funcao_id,
                   f.nome_funcao,
                   fi.colaborador_id,
                   c.nome_colaborador,
                   DATEDIFF(CURDATE(), DATE(fi.prazo)) AS dias_em_atraso,
                   COALESCE(hist.prazo_original, fi.prazo) AS prazo_original
            FROM funcao_imagem fi
            LEFT JOIN funcao f ON f.idfuncao = fi.funcao_id
            LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
            LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
            LEFT JOIN obra o ON o.idobra = i.obra_id
            LEFT JOIN (
                SELECT h1.funcao_imagem_id,
                       COALESCE(h1.prazo_anterior, h1.prazo_novo) AS prazo_original
                FROM funcao_imagem_prazo_historico h1
                INNER JOIN (
                    SELECT funcao_imagem_id, MIN(id) AS min_id
                    FROM funcao_imagem_prazo_historico
                    GROUP BY funcao_imagem_id
                ) hmin ON hmin.min_id = h1.id
            ) hist ON hist.funcao_imagem_id = fi.idfuncao_imagem
            LEFT JOIN sla_notificacoes_enviadas sne
                   ON sne.funcao_imagem_id = fi.idfuncao_imagem
                  AND sne.tipo_alerta = :alert_type
                  AND sne.data_referencia = CURDATE()
                  AND sne.canal = :channel
            WHERE fi.status = 'Em andamento'
              AND fi.prazo IS NOT NULL
              AND DATE(fi.prazo) < CURDATE()
              AND o.status_obra = 0
              AND sne.id IS NULL
            ORDER BY fi.prazo ASC, fi.idfuncao_imagem ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':alert_type' => ALERT_TYPE,
        ':channel' => SLACK_CHANNEL,
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows || count($rows) === 0) {
        echo "Nenhuma nova tarefa em andamento atrasada encontrada para notificar hoje.\n";
        exit(0);
    }

    foreach (quebrarMensagensSlack($rows) as $mensagem) {
        enviarMensagemSlack(SLACK_CHANNEL, $mensagem);
    }

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO sla_notificacoes_enviadas (
            tipo_alerta,
            data_referencia,
            funcao_imagem_id,
            canal,
            payload_hash
        ) VALUES (?, CURDATE(), ?, ?, ?)'
    );

    $pdo->beginTransaction();
    foreach ($rows as $row) {
        $hash = sha1(json_encode($row, JSON_UNESCAPED_UNICODE));
        $insert->execute([
            ALERT_TYPE,
            (int) $row['idfuncao_imagem'],
            SLACK_CHANNEL,
            $hash,
        ]);
    }
    $pdo->commit();

    echo 'Mensagem enviada ao Slack (canal=' . SLACK_CHANNEL . ') com ' . count($rows) . " item(ns).\n";
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Erro: " . $e->getMessage() . "\n";
    exit(2);
}
