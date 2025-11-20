<?php
session_start();
header('Content-Type: application/json');
require_once '../conexao.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$entrega_item_id = isset($data['entrega_item_id']) ? intval($data['entrega_item_id']) : 0;
$historico_imagem_id = isset($data['historico_imagem_id']) ? intval($data['historico_imagem_id']) : 0;
$decisao = isset($data['decisao']) ? trim($data['decisao']) : '';
$usuario_id = isset($_SESSION['idusuario']) ? intval($_SESSION['idusuario']) : null;

if ($entrega_item_id <= 0 || $historico_imagem_id <= 0 || $decisao === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos']);
    exit();
}

try {
    // Carrega variáveis de ambiente (.env neste diretório)
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
    $slackToken = $_ENV['SLACK_TOKEN'] ?? getenv('SLACK_TOKEN') ?? null;
    $slackChannel = 'C09V1SES7B2'; // Canal fixo solicitado

    // Insere decisão vinculando o item da entrega e a versão (historico_imagem)
    // Tabela sugerida: imagem_decisoes (id, entrega_item_id, historico_imagem_id, decisao, usuario_id, created_at)
    $sql = "INSERT INTO imagem_decisoes (entrega_item_id, historico_imagem_id, decisao, usuario_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                decisao = VALUES(decisao),
                usuario_id = VALUES(usuario_id),
                created_at = NOW()";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar statement: ' . $conn->error);
    }
    $stmt->bind_param('iisi', $entrega_item_id, $historico_imagem_id, $decisao, $usuario_id);
    $ok = $stmt->execute();

    if (!$ok) {
        throw new Exception('Erro ao executar statement: ' . $stmt->error);
    }

    // Recupera informações adicionais para enriquecer a mensagem
    $usuarioNome = null;
    $stmtUser = $conn->prepare("SELECT u.idusuario, nome_usuario FROM usuario_externo u WHERE u.idusuario = ? LIMIT 1");
    $stmtUser->bind_param('i', $usuario_id);
    if ($stmtUser->execute()) {
        $resUser = $stmtUser->get_result();
        if ($rowU = $resUser->fetch_assoc()) {
            $usuarioNome = $rowU['nome_usuario'];
        }
    }
    $stmtUser->close();

    $imagemNome = null;
    $arquivoVersao = null;
    $indiceEnvio = null;
    $dataEnvio = null;
    $entregaId = null;
    $imagemId = null;
    $revisaoCod = null;
    // junta dados de entrega_item + historico + imagem
    $stmtInfo = $conn->prepare("SELECT ei.entrega_id, ei.imagem_id, i.imagem_nome, hi.nome_arquivo, hi.indice_envio, ei.data_entregue FROM entregas_itens ei LEFT JOIN historico_aprovacoes_imagens hi ON hi.id = ei.historico_id LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = ei.imagem_id WHERE ei.id = ? LIMIT 1");
    $stmtInfo->bind_param('i', $entrega_item_id);
    if ($stmtInfo->execute()) {
        $resInfo = $stmtInfo->get_result();
        if ($rowI = $resInfo->fetch_assoc()) {
            $entregaId = $rowI['entrega_id'];
            $imagemId = $rowI['imagem_id'];
            $imagemNome = $rowI['imagem_nome'];
            $arquivoVersao = $rowI['nome_arquivo'];
            $indiceEnvio = $rowI['indice_envio'];
            $data_entregue = $rowI['data_entregue'];
        }
    }
    $stmtInfo->close();

    // extrai código de revisão do nome do arquivo (ex: _P00 / _R01)
    if ($arquivoVersao) {
        if (preg_match('/_([A-Z]\d{2})/i', $arquivoVersao, $m)) {
            $revisaoCod = strtoupper($m[1]);
        } elseif (preg_match('/_([A-Z]\d{3})/i', $arquivoVersao, $m3)) {
            $revisaoCod = strtoupper($m3[1]);
        }
    }

    $decisaoLabel = match ($decisao) {
        'aprovado' => 'Aprovado',
        'aprovado_com_ajustes' => 'Aprovado com ajustes',
        'ajuste' => 'Ajuste',
        default => ucfirst($decisao)
    };

    date_default_timezone_set('America/Sao_Paulo');
    $hora = date('d/m/Y H:i');
    $textoSlack = "Decisão registrada:\n";
    $textoSlack .= "• Imagem: #" . ($imagemId ?? 'N/D') . " - " . ($imagemNome ?? 'sem nome') . "\n";
    if ($data_entregue) {
        $dataEnvioFmt = date('d/m/Y H:i', strtotime($data_entregue));
        $textoSlack .= "• Data envio versão: $dataEnvioFmt\n";
    }
    $textoSlack .= "• Decisão: $decisaoLabel\n";
    $textoSlack .= "• Responsável: " . ($usuarioNome ?? 'Usuário') . "\n";
    $textoSlack .= "• Registrado em: $hora";

    // Envia para Slack se token disponível
    $slackStatus = null;
    if ($slackToken) {
        $payload = [
            'channel' => $slackChannel,
            'text' => $textoSlack,
        ];
        $ch = curl_init('https://slack.com/api/chat.postMessage');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $slackToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $resp = curl_exec($ch);
        if (curl_errno($ch)) {
            $slackStatus = 'erro_curl: ' . curl_error($ch);
        } else {
            $respData = json_decode($resp, true);
            $slackStatus = isset($respData['ok']) && $respData['ok'] ? 'enviado' : ('falha_api: ' . ($respData['error'] ?? 'desconhecido'));
        }
        curl_close($ch);
    } else {
        $slackStatus = 'token_indisponivel';
    }

    echo json_encode([
        'success' => true,
        'slack_status' => $slackStatus
    ]);
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
