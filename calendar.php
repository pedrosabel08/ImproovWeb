<?php
require_once __DIR__ . '/FlowReview/vendor/autoload.php'; // Instale via composer require omarusman/ics-parser

use ICal\ICal;

use Dotenv\Dotenv;

$envPath = __DIR__ . '/FlowReview/.env';

if (!file_exists($envPath)) {
    die("Arquivo .env não encontrado em: $envPath");
}

$dotenv = Dotenv::createImmutable(__DIR__ . '/FlowReview');
$dotenv->load();

$webhookUrl = $_ENV['SLACK_WEBHOOK_URL'] ?? null;

if (!$webhookUrl) {
    die('Erro: Variável SLACK_WEBHOOK_URL não encontrada no .env');
}


// URL do arquivo ICS
$icsUrl = 'https://calendar.google.com/calendar/ical/trafegoimproov%40gmail.com/private-faa5fcb5e3fde4c0234e8ae543118324/basic.ics';

try {
    $ical = new ICal($icsUrl);
    $eventos = $ical->eventsFromRange('now', '+7 days'); // Eventos nos próximos 7 dias

    $notificacaoEventos = '';

    // Acumula os eventos para enviar uma notificação única
    foreach ($eventos as $evento) {
        $titulo = $evento->summary; // Nome da obra - Tipo de entrega

        // Ajuste o fuso horário para o horário de Brasília (GMT-3)
        $data = new DateTime($evento->dtstart, new DateTimeZone('UTC'));
        $data->modify('+1 day'); // Ajustar manualmente se necessário
        $data->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        $dataFormatada = $data->format('d/m/Y');

        // Acumula o evento na string de notificação
        $notificacaoEventos .= "🗂 *$titulo*\n📆 *Data:* $dataFormatada\n\n";
    }

    // Enviar notificação se houver eventos
    if (!empty($notificacaoEventos)) {
        enviarNotificacaoSlack($notificacaoEventos, $webhookUrl);
    }
} catch (\Exception $e) {
    echo 'Erro ao processar o ICS: ' . $e->getMessage();
}

function enviarNotificacaoSlack($notificacaoEventos, $webhookUrl)
{
    $mensagem = "📅 *Entrega dos próximos 7 Dias!*\n\n" . $notificacaoEventos;

    $payload = json_encode(['text' => $mensagem]);
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}
