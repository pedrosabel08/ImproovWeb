<?php
session_start();

if (!isset($_SESSION['idusuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

include '../conexao.php';
require_once __DIR__ . '/vendor/autoload.php';

use phpseclib3\Net\SFTP;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$slackToken = $_ENV['SLACK_TOKEN'] ?? null;

function enviarNotificacaoSlack($slackUserId, $mensagem, &$log)
{
    global $slackToken;

    $slackMessage = [
        "channel" => $slackUserId,
        "text" => $mensagem,
    ];

    $ch = curl_init("https://slack.com/api/chat.postMessage");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$slackToken}",
        "Content-Type: application/json",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($slackMessage));

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $log[] = 'Erro ao enviar mensagem para o Slack: ' . curl_error($ch);
        curl_close($ch);
        return false;
    }

    $responseData = json_decode($response, true);
    curl_close($ch);

    if (!$responseData['ok']) {
        $log[] = 'Erro ao enviar mensagem para o Slack: ' . $responseData['error'];
        return false;
    }

    $log[] = 'Mensagem enviada para Slack com sucesso.';
    return true;
}

$resultadoFinal = ['logs' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $idfuncao_imagem = $data['idfuncao_imagem'] ?? null;
    $tipoRevisao = $data['tipoRevisao'] ?? null;
    $imagem_nome = $data['imagem_nome'] ?? null;
    $nome_funcao = $data['nome_funcao'] ?? null;
    $colaborador_id = $data['colaborador_id'] ?? null;
    $responsavel = $data['responsavel'] ?? null;
    $imagem_id = $data['imagem_id'] ?? null;
    $nome_colaborador = 'Pedro Sabel';

    if (!$idfuncao_imagem || !$tipoRevisao) {
        echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
        exit;
    }

    $stmt2 = $conn->prepare("SELECT nome_colaborador FROM colaborador WHERE idcolaborador = ?");
    $stmt2->bind_param("i", $responsavel);
    $stmt2->execute();
    $stmt2->bind_result($nome_responsavel);
    $stmt2->fetch();
    $stmt2->close();

    if (preg_match('/^\d+\.\s+\S+/', $imagem_nome, $matches)) {
        $imagem_resumida = $matches[0];
    } else {
        $imagem_resumida = $imagem_nome;
    }

    switch ($tipoRevisao) {
        case "aprovado":
            $status = "Aprovado";
            $mensagemSlack = "A {$nome_funcao} da imagem {$imagem_resumida} está revisada por {$nome_responsavel}!";
            break;
        case "ajuste":
            $status = "Ajuste";
            $mensagemSlack = "A {$nome_funcao} da imagem {$imagem_resumida} possui alteração, analisada por {$nome_responsavel}! 😓";
            break;
        case "aprovado_com_ajustes":
            $status = "Aprovado com ajustes";
            $mensagemSlack = "A {$nome_funcao} da imagem {$imagem_resumida} foi aprovada com ajustes por {$nome_responsavel}.";
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Tipo de revisão inválido.']);
            exit;
    }

    if ($tipoRevisao === "ajuste") {
        $stmtNotif = $conn->prepare("INSERT INTO notificacoes (mensagem, colaborador_id) VALUES (?, ?)");
        $stmtNotif->bind_param("si", $mensagemSlack, $colaborador_id);
        $stmtNotif->execute();
        $stmtNotif->close();
    }

    $stmt = $conn->prepare("UPDATE funcao_imagem SET status = ? WHERE idfuncao_imagem = ?");
    $stmt->bind_param("si", $status, $idfuncao_imagem);

    if ($stmt->execute()) {
        $stmt->close();

        $status_anterior = "Em aprovação";
        $stmt = $conn->prepare("INSERT INTO historico_aprovacoes (funcao_imagem_id, status_anterior, status_novo, colaborador_id, responsavel) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issii", $idfuncao_imagem, $status_anterior, $status, $colaborador_id, $responsavel);
        $stmt->execute();
        $stmt->close();

        $resultadoFinal['success'] = true;
        $resultadoFinal['message'] = 'Tarefa atualizada com sucesso.';
    } else {
        $resultadoFinal['success'] = false;
        $resultadoFinal['message'] = 'Erro ao atualizar tarefa.';
        echo json_encode($resultadoFinal);
        exit;
    }

    // SFTP envio final
    if (
        in_array(mb_strtolower($nome_funcao, 'UTF-8'), ['pós-produção', 'alteração']) &&
        in_array($status, ['Aprovado', 'Aprovado com ajustes'])
    ) {
        $stmtArquivo = $conn->prepare("SELECT nome_arquivo FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? ORDER BY id DESC LIMIT 1");
        $stmtArquivo->bind_param("i", $idfuncao_imagem);
        $stmtArquivo->execute();
        $stmtArquivo->bind_result($nome_arquivo_base);
        $stmtArquivo->fetch();
        $stmtArquivo->close();

        $stmtNomen = $conn->prepare("SELECT o.nomenclatura FROM funcao_imagem fi JOIN imagens_cliente_obra ic ON fi.imagem_id = ic.idimagens_cliente_obra JOIN obra o ON ic.obra_id = o.idobra WHERE fi.idfuncao_imagem = ?");
        $stmtNomen->bind_param("i", $idfuncao_imagem);
        $stmtNomen->execute();
        $stmtNomen->bind_result($nomenclatura);
        $stmtNomen->fetch();
        $stmtNomen->close();

        $uploadDir = dirname(__DIR__) . "/uploads/";
        $arquivosPossiveis = glob($uploadDir . $nome_arquivo_base . '.*'); // tenta encontrar qualquer extensão

        if (!empty($arquivosPossiveis)) {
            $caminho_local = $arquivosPossiveis[0];
            $nome_arquivo = basename($caminho_local); // nome final com extensão

            $reviewDir = $uploadDir . "review/";
            if (!is_dir($reviewDir)) {
                mkdir($reviewDir, 0777, true);
            }
            $destinoReview = $reviewDir . $nome_arquivo;
            if (!copy($caminho_local, $destinoReview)) {
                $resultadoFinal['logs'][] = "Falha ao copiar arquivo para pasta review.";
            } else {
                $resultadoFinal['logs'][] = "Arquivo copiado para pasta review: $destinoReview";
            }

            // Busca a maior versão já existente para o imagem_id
            $versao = 1;
            $stmtVer = $conn->prepare("SELECT MAX(versao) as max_versao FROM review_uploads WHERE imagem_id = ?");
            $stmtVer->bind_param("i", $imagem_id);
            $stmtVer->execute();
            $stmtVer->bind_result($max_versao);
            if ($stmtVer->fetch() && $max_versao !== null) {
                $versao = $max_versao + 1;
            }
            $stmtVer->close();

            $stmt = $conn->prepare("INSERT INTO review_uploads (imagem_id, nome_arquivo, versao) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $imagem_id, $nome_arquivo, $versao);

            if ($stmt->execute()) {
                $resultadoFinal['logs'][] = "Arquivo inserido no banco de dados: $nome_arquivo";
            } else {
                $resultadoFinal['logs'][] = "Falha ao inserir a imagem no banco.";
            }

            $ftp_host = 'imp-nas.ddns.net';
            $ftp_user = 'flow';
            $ftp_pass = 'flow@2025';
            $ftp_port = 2222;
            $bases = ['/mnt/clientes/2024', '/mnt/clientes/2025'];
            $enviado = false;

            foreach ($bases as $base) {
                $sftp = new SFTP($ftp_host, $ftp_port);
                if (!$sftp->login($ftp_user, $ftp_pass)) {
                    $resultadoFinal['logs'][] = "Falha ao conectar no host $ftp_host:$ftp_port para base $base.";
                    continue;
                }
                $resultadoFinal['logs'][] = "Conectado ao host $ftp_host para base $base.";

                // Extrai a revisão do nome do arquivo, ex: "_P00", "_P01", etc.
                preg_match_all('/_[A-Z0-9]{2,3}/i', $nome_arquivo, $matches);
                $revisao = isset($matches[0]) && count($matches[0]) > 0
                    ? strtoupper(str_replace('_', '', end($matches[0])))
                    : 'P00'; // padrão se nada for encontrado

                $finalizacaoDir = "$base/$nomenclatura/04.Finalizacao";

                if (!$sftp->is_dir($finalizacaoDir)) {
                    $resultadoFinal['logs'][] = "Diretório $finalizacaoDir não existe.";
                    continue;
                }

                $revisaoDir = "$finalizacaoDir/$revisao";
                if (!$sftp->is_dir($revisaoDir)) {
                    if ($sftp->mkdir($revisaoDir, -1, true)) {
                        $resultadoFinal['logs'][] = "Diretório $revisaoDir criado com sucesso.";
                    } else {
                        $resultadoFinal['logs'][] = "Falha ao criar diretório $revisaoDir.";
                        continue;
                    }
                }

                $remote_path = "$revisaoDir/$nome_arquivo";
                if ($sftp->put($remote_path, $caminho_local, SFTP::SOURCE_LOCAL_FILE)) {
                    $resultadoFinal['logs'][] = "Arquivo enviado com sucesso para $remote_path.";
                    $enviado = true;
                    break;
                } else {
                    $resultadoFinal['logs'][] = "Falha ao enviar arquivo para $remote_path.";
                }
            }

            $resultadoFinal['sftp_enviado'] = $enviado;
        } else {
            $resultadoFinal['logs'][] = "Arquivo com base '$nome_arquivo_base' não encontrado em $uploadDir.";
        }
    }

    // Slack envio final
    $userID = null;
    $ch = curl_init("https://slack.com/api/users.list");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$slackToken}",
        "Content-Type: application/json",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    // Verifica se a resposta é válida
    if (!isset($responseData['ok']) || !$responseData['ok']) {
        $resultadoFinal['logs'][] = "Erro na API do Slack: " . ($responseData['error'] ?? 'Resposta inválida');
    } elseif (!isset($responseData['members']) || !is_array($responseData['members'])) {
        $resultadoFinal['logs'][] = "API do Slack não retornou 'members'. Resposta: " . json_encode($responseData);
    } else {
        foreach ($responseData['members'] as $member) {
            if (isset($member['real_name']) && strtolower($member['real_name']) === strtolower($nome_colaborador)) {
                $userID = $member['id'];
                break;
            }
        }
    }

    if ($userID) {
        enviarNotificacaoSlack($userID, $mensagemSlack, $resultadoFinal['logs']);
    } else {
        $resultadoFinal['logs'][] = "Usuário {$nome_colaborador} não encontrado no Slack.";
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$conn->close();
echo json_encode($resultadoFinal);
