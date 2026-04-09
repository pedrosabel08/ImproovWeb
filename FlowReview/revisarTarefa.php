<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/secure_env.php';

if (!isset($_SESSION['idusuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

// Ensure JSON responses and helpful error reporting during debugging
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

include '../conexao.php';
require_once __DIR__ . '/vendor/autoload.php';

use phpseclib3\Net\SFTP;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
// Prefer getenv() (root .env / secure_env.php); fallback to $_ENV
$slackToken = getenv('SLACK_TOKEN') ?: ($_ENV['SLACK_TOKEN'] ?? null);
$slackTokenPresent = !empty($slackToken);

function enviarNotificacaoSlack($slackUserId, $mensagem, &$log)
{
    global $slackToken;
    global $slackTokenPresent;

    if (!$slackTokenPresent) {
        $log[] = 'Slack token ausente — notificação ignorada.';
        return false;
    }
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
    // timeouts: short connect, slightly longer total
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $log[] = 'Erro ao enviar mensagem para o Slack: ' . curl_error($ch);
        curl_close($ch);
        return false;
    }

    $responseData = json_decode($response, true);
    curl_close($ch);

    if (!is_array($responseData) || empty($responseData['ok'])) {
        $log[] = 'Erro ao enviar mensagem para o Slack: ' . ($responseData['error'] ?? ('resposta inválida: ' . substr((string)$response, 0, 200)));
        return false;
    }

    $log[] = 'Mensagem enviada para Slack com sucesso.';
    return true;
}

/**
 * Normaliza nomes para comparação: remove acentos, pontuação e deixa em minúsculas.
 */
function normalize_name($s)
{
    if (!$s)
        return '';
    // tenta transliterar acentos
    $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    $s = strtolower($s);
    // remove caracteres que não são letras, números ou espaços
    $s = preg_replace('/[^a-z0-9\s]/', '', $s);
    // normaliza espaços
    $s = preg_replace('/\s+/', ' ', trim($s));
    return $s;
}

$resultadoFinal = ['logs' => []];

// Lê sessão para verificar permissões de aprovação dupla
$idusuario_session   = isset($_SESSION['idusuario'])    ? (int)$_SESSION['idusuario']    : 0;
$idcolaborador_session = isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $idfuncao_imagem = $data['idfuncao_imagem'] ?? null;
        $tipoRevisao = $data['tipoRevisao'] ?? null;
        $imagem_nome = $data['imagem_nome'] ?? null;
        $nome_funcao = $data['nome_funcao'] ?? null;
        $colaborador_id = $data['colaborador_id'] ?? null;
        $responsavel = $data['responsavel'] ?? null;
        $imagem_id = $data['imagem_id'] ?? null;
        // SFTP conflict resolution params (passed on 2nd call by the frontend)
        $sftp_action      = $data['sftp_action']      ?? null; // 'replace' | 'add' | null
        $sftp_suffix      = $data['sftp_suffix']      ?? null; // suffix string when action='add'
        $sftp_remote_path = $data['sftp_remote_path'] ?? null; // exact remote path returned on conflict
        $historico_id     = isset($data['historico_id']) ? (int)$data['historico_id'] : null; // ID exato da imagem sendo revisada
        // Pode conter múltiplos nomes que serão aceitos ao buscar o usuário no Slack
        $nome_colaboradores = ['Pedro Sabel', 'Andre L. de Souza'];

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

        // ── Aprovação dupla de Pós-produção ──────────────────────────────────────
        // Quando um finalizador (não-admin, não-direção) aprova uma pós-produção pela
        // 1ª vez, a tarefa não muda de status: apenas registramos um histórico intermediário
        // com status_novo='Aguardando Direção' e notificamos a direção via Slack.
        // A 2ª aprovação (pela direção) segue o fluxo normal (SFTP incluído).
        $aguardandoDirecao = false;
        $isPosProducao = (mb_strtolower((string)$nome_funcao, 'UTF-8') === 'pós-produção');
        $isAdminAprovador = in_array($idusuario_session, [1, 2]);
        $isDirecaoAprovador = in_array($idcolaborador_session, [9, 21]);

        if ($isPosProducao && $tipoRevisao === 'aprovado' && !$isAdminAprovador && !$isDirecaoAprovador) {
            // Verifica se já existe um histórico 'Aguardando Direção' para esta tarefa
            $stmtChkDir = $conn->prepare(
                "SELECT id FROM historico_aprovacoes WHERE funcao_imagem_id = ? AND status_novo = 'Aguardando Direção' LIMIT 1"
            );
            $stmtChkDir->bind_param("i", $idfuncao_imagem);
            $stmtChkDir->execute();
            $stmtChkDir->store_result();
            $isSegundaAprovacao = ($stmtChkDir->num_rows > 0);
            $stmtChkDir->close();

            if (!$isSegundaAprovacao) {
                // 1ª aprovação pelo finalizador: NÃO atualiza funcao_imagem.status
                // Registra apenas no histórico como intermediário
                $status_ant_dir = "Em aprovação";
                $status_dir     = "Aguardando Direção";
                $stmtDir = $conn->prepare(
                    "INSERT INTO historico_aprovacoes (funcao_imagem_id, status_anterior, status_novo, colaborador_id, responsavel) VALUES (?, ?, ?, ?, ?)"
                );
                $stmtDir->bind_param("issii", $idfuncao_imagem, $status_ant_dir, $status_dir, $colaborador_id, $responsavel);
                $stmtDir->execute();
                $stmtDir->close();

                // Notifica direção via Slack (busca nome_slack dos colaboradores 9 e 21)
                $stmtDirSlack = $conn->prepare(
                    "SELECT u.nome_slack FROM usuario u WHERE u.idcolaborador IN (9, 21) AND u.nome_slack IS NOT NULL AND u.nome_slack != ''"
                );
                $stmtDirSlack->execute();
                $resDirSlack = $stmtDirSlack->get_result();
                $mensagemDirecao = "⏳ A Pós-produção de {$imagem_resumida} aguarda validação da direção (aprovada por {$nome_responsavel}).";
                while ($rowSlack = $resDirSlack->fetch_assoc()) {
                    enviarNotificacaoSlack($rowSlack['nome_slack'], $mensagemDirecao, $resultadoFinal['logs']);
                }
                $stmtDirSlack->close();

                $resultadoFinal['success']          = true;
                $resultadoFinal['message']          = 'Aprovação registrada. Aguardando validação da direção.';
                $resultadoFinal['aguardando_direcao'] = true;
                echo json_encode($resultadoFinal);
                $conn->close();
                exit;
            }
            // Se já existe histórico 'Aguardando Direção', é a 2ª aprovação — deixa passar normalmente
        }
        // ─────────────────────────────────────────────────────────────────────────

        // Para P00 + Finalização, só permite aprovar a função se TODOS os ângulos estiverem liberados.
        if (in_array($status, ['Aprovado'], true) && $imagem_id) {
            $isFinalizacao = (mb_strtolower((string) $nome_funcao, 'UTF-8') === 'finalização');
            if ($isFinalizacao) {
                $status_nome_atual = null;
                $stmtSt = $conn->prepare("SELECT s.nome_status
                FROM imagens_cliente_obra i
                JOIN status_imagem s ON s.idstatus = i.status_id
                WHERE i.idimagens_cliente_obra = ?
                LIMIT 1");
                if ($stmtSt) {
                    $stmtSt->bind_param("i", $imagem_id);
                    $stmtSt->execute();
                    $stmtSt->bind_result($status_nome_atual);
                    $stmtSt->fetch();
                    $stmtSt->close();
                }

                if ($status_nome_atual === 'P00') {
                    $total = 0;
                    $aprovados = 0;
                    $sql = "SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN ai.liberada = 1 AND ai.sugerida = 0 THEN 1 ELSE 0 END) AS aprovados
                    FROM historico_aprovacoes_imagens hi
                    LEFT JOIN angulos_imagens ai
                        ON ai.historico_id = hi.id AND ai.imagem_id = ?
                    WHERE hi.funcao_imagem_id = ?";
                    if ($chk = $conn->prepare($sql)) {
                        $chk->bind_param('ii', $imagem_id, $idfuncao_imagem);
                        $chk->execute();
                        $res = $chk->get_result();
                        if ($res && ($row = $res->fetch_assoc())) {
                            $total = (int) ($row['total'] ?? 0);
                            $aprovados = (int) ($row['aprovados'] ?? 0);
                        }
                        $chk->close();
                    }

                    if ($total <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Nenhum ângulo importado para aprovação (P00).']);
                        exit;
                    }
                    if ($aprovados < $total) {
                        echo json_encode(['success' => false, 'message' => "Ainda existem ângulos pendentes/ajuste ($aprovados/$total)."]);
                        exit;
                    }
                }
            }
        }

        if ($tipoRevisao === "ajuste") {
            $stmtNotif = $conn->prepare("insert into notificacoes_gerais (mensagem, colaborador_id) VALUES (?, ?)");
            $stmtNotif->bind_param("si", $mensagemSlack, $colaborador_id);
            $stmtNotif->execute();
            $stmtNotif->close();
        }

        $conn->begin_transaction();

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

            $tipo_imagem_nome = null;
            if ($imagem_id) {
                $stmtTipo = $conn->prepare("SELECT tipo_imagem FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?");
                $stmtTipo->bind_param("i", $imagem_id);
                $stmtTipo->execute();
                $stmtTipo->bind_result($tipo_imagem_nome);
                $stmtTipo->fetch();
                $stmtTipo->close();
            }

            $nomeFuncaoLower = mb_strtolower((string)$nome_funcao, 'UTF-8');
            // Ao aprovar uma função, atualizar vínculos de entrega
            // - Para P00 + Finalização: vincular TODOS os ângulos (historico_aprovacoes_imagens) ao item de entrega via angulos_imagens.entrega_item_id
            // - Para demais (R00..EF): atualizar entregas_itens.historico_id com o id correspondente (último)
            if (
                in_array($status, ['Aprovado', 'Aprovado com ajustes']) &&
                (
                    $nomeFuncaoLower === 'pós-produção' ||
                    ($nomeFuncaoLower === 'finalização' && stripos((string)$tipo_imagem_nome, 'humanizada') !== false)
                )
            ) {
                if ($imagem_id) {
                    // obtém status_id e obra_id da imagem
                    $stmtImg = $conn->prepare("SELECT status_id, obra_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?");
                    $stmtImg->bind_param("i", $imagem_id);
                    $stmtImg->execute();
                    $stmtImg->bind_result($img_status_id, $img_obra_id);
                    if ($stmtImg->fetch()) {
                        $stmtImg->close();

                        // Descobre o nome do status (ex.: P00, R00, EF...)
                        $status_nome_atual = null;
                        $stmtSt = $conn->prepare("SELECT nome_status FROM status_imagem WHERE idstatus = ? LIMIT 1");
                        if ($stmtSt) {
                            $stmtSt->bind_param("i", $img_status_id);
                            $stmtSt->execute();
                            $stmtSt->bind_result($status_nome_atual);
                            $stmtSt->fetch();
                            $stmtSt->close();
                        }

                        // encontra a entrega correspondente (escolhe a mais recente)
                        $stmtEnt = $conn->prepare("SELECT id FROM entregas WHERE status_id = ? AND obra_id = ? ORDER BY id DESC LIMIT 1");
                        $stmtEnt->bind_param("ii", $img_status_id, $img_obra_id);
                        $stmtEnt->execute();
                        $stmtEnt->bind_result($entrega_id_found);
                        if ($stmtEnt->fetch()) {
                            $stmtEnt->close();

                            // encontra o item da entrega para essa imagem
                            $stmtItem = $conn->prepare("SELECT id FROM entregas_itens WHERE entrega_id = ? AND imagem_id = ? LIMIT 1");
                            $stmtItem->bind_param("ii", $entrega_id_found, $imagem_id);
                            $stmtItem->execute();
                            $stmtItem->bind_result($entrega_item_id);
                            if ($stmtItem->fetch()) {
                                $stmtItem->close();

                                // Verifica se é o caso especial de P00 + função finalização
                                $isFinalizacao = (mb_strtolower($nome_funcao, 'UTF-8') === 'finalização');
                                $isP00 = ($status_nome_atual === 'P00');

                                if ($isFinalizacao && $isP00) {
                                    // Garante coluna entrega_item_id em angulos_imagens (migração leve e segura durante a execução)
                                    $colExists = false;
                                    if ($chk = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'angulos_imagens' AND COLUMN_NAME = 'entrega_item_id'")) {
                                        $chk->execute();
                                        $resChk = $chk->get_result();
                                        $colExists = ($resChk && $resChk->num_rows > 0);
                                        $chk->close();
                                    }
                                    if (!$colExists) {
                                        // Tenta adicionar a coluna e índice (ignora erro se já existir por condição de corrida)
                                        @$conn->query("ALTER TABLE angulos_imagens ADD COLUMN entrega_item_id INT NULL AFTER historico_id");
                                        @$conn->query("CREATE INDEX idx_angulos_entrega_item ON angulos_imagens(entrega_item_id)");
                                    }

                                    // Para P00: não sobrescreve decisões de ângulo.
                                    // Apenas vincula entrega_item_id aos ângulos existentes dessa função.
                                    if (
                                        $upAi = $conn->prepare("UPDATE angulos_imagens ai
                                    JOIN historico_aprovacoes_imagens hi ON hi.id = ai.historico_id
                                    SET ai.entrega_item_id = ?
                                    WHERE ai.imagem_id = ? AND hi.funcao_imagem_id = ?")
                                    ) {
                                        $upAi->bind_param('iii', $entrega_item_id, $imagem_id, $idfuncao_imagem);
                                        $upAi->execute();
                                        $upAi->close();
                                    }

                                    // Atualiza o status do item da entrega para pendente de envio ao cliente
                                    if ($up = $conn->prepare("UPDATE entregas_itens SET status = 'Entrega pendente' WHERE id = ?")) {
                                        $up->bind_param("i", $entrega_item_id);
                                        $up->execute();
                                        $up->close();
                                    }

                                    $resultadoFinal['logs'][] = "P00: entrega_item_id=$entrega_item_id vinculado aos ângulos (sem sobrescrever status).";
                                } else {
                                    // Fluxo padrão (R00..EF): usa o último historico para preencher entregas_itens.historico_id
                                    $stmtHistImg = $conn->prepare("SELECT id FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? ORDER BY id DESC LIMIT 1");
                                    $stmtHistImg->bind_param("i", $idfuncao_imagem);
                                    $stmtHistImg->execute();
                                    $stmtHistImg->bind_result($hist_img_id);
                                    if ($stmtHistImg->fetch()) {
                                        $stmtHistImg->close();

                                        // atualiza entregas_itens.historico_id
                                        $stmtUpd = $conn->prepare("UPDATE entregas_itens SET historico_id = ?, status = 'Entrega pendente' WHERE id = ?");
                                        $stmtUpd->bind_param("ii", $hist_img_id, $entrega_item_id);
                                        if ($stmtUpd->execute()) {
                                            $resultadoFinal['logs'][] = "entregas_itens id=$entrega_item_id atualizado com historico_id=$hist_img_id.";
                                        } else {
                                            $resultadoFinal['logs'][] = "Falha ao atualizar entregas_itens id=$entrega_item_id.";
                                        }
                                        $stmtUpd->close();
                                    } else {
                                        $stmtHistImg->close();
                                        $resultadoFinal['logs'][] = "historico_aprovacoes_imagens para funcao_imagem_id=$idfuncao_imagem não encontrado.";
                                    }
                                }
                            } else {
                                $stmtItem->close();
                                $resultadoFinal['logs'][] = "entregas_itens para entrega_id=$entrega_id_found imagem_id=$imagem_id não encontrado.";
                            }
                        } else {
                            $stmtEnt->close();
                            $resultadoFinal['logs'][] = "entrega com status_id=$img_status_id e obra_id=$img_obra_id não encontrada.";
                        }
                    } else {
                        $stmtImg->close();
                        $resultadoFinal['logs'][] = "imagem id=$imagem_id não encontrada na tabela imagens_cliente_obra.";
                    }
                } else {
                    $resultadoFinal['logs'][] = "imagem_id não fornecido; pulando atualização de entregas_itens.";
                }
            }
        } else {
            $conn->rollback();
            $resultadoFinal['success'] = false;
            $resultadoFinal['message'] = 'Erro ao atualizar tarefa.';
            echo json_encode($resultadoFinal);
            exit;
        }

        // SFTP envio final
        if (
            (
                in_array(mb_strtolower($nome_funcao, 'UTF-8'), ['pós-produção', 'alteração']) &&
                in_array($status, ['Aprovado'])
            )
            ||
            (
                // 🔸 Nova condição: finalização de planta humanizada
                mb_strtolower($nome_funcao, 'UTF-8') === 'finalização' &&
                stripos((string)$tipo_imagem_nome, 'humanizada') !== false &&
                in_array($status, ['Aprovado'])
            )
        ) {
            // Busca o arquivo exato: usa historico_id quando disponível (imagem sendo visualizada),
            // caso contrário cai no registro mais recente da função.
            if ($historico_id) {
                $stmtArquivo = $conn->prepare("SELECT nome_arquivo, imagem FROM historico_aprovacoes_imagens WHERE id = ? AND funcao_imagem_id = ? LIMIT 1");
                $stmtArquivo->bind_param("ii", $historico_id, $idfuncao_imagem);
            } else {
                $stmtArquivo = $conn->prepare("SELECT nome_arquivo, imagem FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? ORDER BY id DESC LIMIT 1");
                $stmtArquivo->bind_param("i", $idfuncao_imagem);
            }
            $stmtArquivo->execute();
            $stmtArquivo->bind_result($nome_arquivo_base, $imagem_db_path);
            $stmtArquivo->fetch();
            $stmtArquivo->close();

            $stmtNomen = $conn->prepare("SELECT o.nomenclatura FROM funcao_imagem fi JOIN imagens_cliente_obra ic ON fi.imagem_id = ic.idimagens_cliente_obra JOIN obra o ON ic.obra_id = o.idobra WHERE fi.idfuncao_imagem = ?");
            $stmtNomen->bind_param("i", $idfuncao_imagem);
            $stmtNomen->execute();
            $stmtNomen->bind_result($nomenclatura);
            $stmtNomen->fetch();
            $stmtNomen->close();

            $uploadDir = dirname(__DIR__) . "/uploads/";
            $arquivosPossiveis = [];

            // 1ª tentativa: usa o caminho exato registrado na coluna `imagem` do histórico
            if (!empty($imagem_db_path)) {
                $caminho_direto = dirname(__DIR__) . '/' . ltrim($imagem_db_path, '/');
                if (is_file($caminho_direto)) {
                    $arquivosPossiveis = [$caminho_direto];
                    $resultadoFinal['logs'][] = "Arquivo localizado via caminho direto do BD: {$caminho_direto}";
                }
            }

            // 2ª tentativa: glob pelo nome-base
            if (empty($arquivosPossiveis)) {
                $arquivosPossiveis = glob($uploadDir . $nome_arquivo_base . '.*') ?: []; // tenta encontrar qualquer extensão
            }

            // Fallback: arquivo não encontrado localmente → busca no VPS via SFTP
            $arquivoTempVps = null;
            if (empty($arquivosPossiveis)) {
                try {
                    $vpsCfg    = improov_sftp_config('IMPROOV_VPS_SFTP');
                    $vpsBase   = rtrim((string)improov_env('IMPROOV_VPS_SFTP_REMOTE_PATH'), '/');
                    $vpsDir    = $vpsBase . '/uploads/';
                    $vsftp     = new SFTP($vpsCfg['host'], (int)$vpsCfg['port']);
                    if ($vsftp->login($vpsCfg['user'], $vpsCfg['pass'])) {
                        $listaRemota = $vsftp->nlist($vpsDir);
                        if (is_array($listaRemota)) {
                            foreach ($listaRemota as $remoteFile) {
                                // nlist pode retornar path completo ou só basename — normaliza
                                $remoteBasename = basename($remoteFile);
                                if (pathinfo($remoteBasename, PATHINFO_FILENAME) === $nome_arquivo_base) {
                                    $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $remoteBasename;
                                    if ($vsftp->get($vpsDir . $remoteBasename, $tempPath)) {
                                        $arquivosPossiveis = [$tempPath];
                                        $arquivoTempVps    = $tempPath;
                                        $resultadoFinal['logs'][] = "Arquivo baixado do VPS: {$remoteBasename}";
                                    } else {
                                        $resultadoFinal['logs'][] = "Falha ao baixar '{$remoteBasename}' do VPS.";
                                    }
                                    break;
                                }
                            }
                        }
                    } else {
                        $resultadoFinal['logs'][] = "Falha ao conectar no VPS SFTP para buscar uploads.";
                    }
                } catch (RuntimeException $e) {
                    $resultadoFinal['logs'][] = "VPS SFTP config ausente: " . $e->getMessage();
                }
            }

            if (!empty($arquivosPossiveis)) {
                $caminho_local = $arquivosPossiveis[0];
                $nome_arquivo_original = basename($caminho_local); // nome original com possível índice
                // Remove índices numéricos finais antes da extensão: ex. _EF_5_1.jpg → _EF.jpg
                $nome_arquivo = preg_replace('/(_\d+)+(\.([^.]+))$/', '$2', $nome_arquivo_original);
                if ($nome_arquivo === $nome_arquivo_original) {
                    // Não havia índice – mantém o original
                    $nome_arquivo = $nome_arquivo_original;
                }

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

                try {
                    $sftpCfg = improov_sftp_config();
                } catch (RuntimeException $e) {
                    $resultadoFinal['logs'][] = 'config_sftp_ausente: ' . $e->getMessage();
                    $sftpCfg = null;
                }
                if ($sftpCfg === null) {
                    $resultadoFinal['sftp_enviado'] = false;
                } else {
                    $ftp_host = $sftpCfg['host'];
                    $ftp_user = $sftpCfg['user'];
                    $ftp_pass = $sftpCfg['pass'];
                    $ftp_port = $sftpCfg['port'];
                    $bases = ['/mnt/clientes/2024', '/mnt/clientes/2025', '/mnt/clientes/2026'];
                    $enviado = false;

                    // Ensure local file exists before attempting SFTP
                    if (!is_file($caminho_local)) {
                        $resultadoFinal['logs'][] = "Arquivo local não encontrado: $caminho_local";
                        $resultadoFinal['sftp_enviado'] = false;
                    } elseif (in_array($sftp_action, ['replace', 'add'], true) && !empty($sftp_remote_path)) {
                        // ── Resolução de conflito: usa o caminho exato devolvido na 1ª chamada ──
                        $resolved_path = $sftp_remote_path;
                        if ($sftp_action === 'add' && !empty($sftp_suffix)) {
                            $ext_r  = pathinfo($resolved_path, PATHINFO_EXTENSION);
                            $base_r = pathinfo($resolved_path, PATHINFO_FILENAME);
                            $resolved_path = dirname($resolved_path) . '/' . $base_r . '_' . $sftp_suffix . '.' . $ext_r;
                        }
                        try {
                            $sftp = new SFTP($ftp_host, $ftp_port, 60);
                            if (!$sftp->login($ftp_user, $ftp_pass)) {
                                $resultadoFinal['logs'][] = "Falha ao autenticar no SFTP para resolução de conflito.";
                            } else {
                                if ($sftp->put($resolved_path, $caminho_local, SFTP::SOURCE_LOCAL_FILE)) {
                                    $resultadoFinal['logs'][] = "Arquivo enviado com sucesso para $resolved_path.";
                                    $enviado = true;
                                } else {
                                    $resultadoFinal['logs'][] = "Falha ao enviar arquivo para $resolved_path.";
                                }
                            }
                        } catch (Throwable $e) {
                            $resultadoFinal['logs'][] = "SFTP put error (resolução conflito): " . $e->getMessage();
                        }
                    } else {
                        foreach ($bases as $base) {
                            try {
                                $sftp = new SFTP($ftp_host, $ftp_port, 60);
                                if (!$sftp->login($ftp_user, $ftp_pass)) {
                                    $resultadoFinal['logs'][] = "Falha ao conectar no host $ftp_host:$ftp_port para base $base.";
                                    continue;
                                }
                                $resultadoFinal['logs'][] = "Conectado ao host $ftp_host para base $base.";
                            } catch (Throwable $e) {
                                $resultadoFinal['logs'][] = "SFTP connection error for base $base: " . $e->getMessage();
                                continue;
                            }

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

                            // Verifica se já existe um arquivo com o mesmo nome no servidor
                            if ($sftp->stat($remote_path) !== false) {
                                $resultadoFinal['sftp_conflict']      = true;
                                $resultadoFinal['sftp_nome_arquivo']  = $nome_arquivo;
                                $resultadoFinal['sftp_remote_path']   = $remote_path;
                                $resultadoFinal['sftp_caminho_local'] = $caminho_local;
                                $resultadoFinal['logs'][] = "Conflito SFTP: arquivo $remote_path já existe.";
                                $enviado = false; // não sinaliza como enviado; frontend resolverá
                                break;
                            }

                            try {
                                if ($sftp->put($remote_path, $caminho_local, SFTP::SOURCE_LOCAL_FILE)) {
                                    $resultadoFinal['logs'][] = "Arquivo enviado com sucesso para $remote_path.";
                                    $enviado = true;
                                    break;
                                } else {
                                    $resultadoFinal['logs'][] = "Falha ao enviar arquivo para $remote_path.";
                                }
                            } catch (Throwable $e) {
                                $resultadoFinal['logs'][] = "SFTP put error for $remote_path: " . $e->getMessage();
                            }
                        }
                    }
                    $resultadoFinal['sftp_enviado'] = $enviado;
                }
            } else {
                $resultadoFinal['logs'][] = "Arquivo com base '$nome_arquivo_base' não encontrado em $uploadDir nem no VPS.";
            }

            // Remove arquivo temporário baixado do VPS (se existir)
            if ($arquivoTempVps && is_file($arquivoTempVps)) {
                @unlink($arquivoTempVps);
            }

            // ── SFTP falhou sem conflito: reverte status e notifica ───────────────
            if (!isset($resultadoFinal['sftp_conflict']) && empty($resultadoFinal['sftp_enviado'])) {
                $conn->rollback();
                $resultadoFinal['success'] = false;
                $resultadoFinal['message'] = 'Erro no envio SFTP. Status da tarefa não foi alterado. Tente novamente.';
                $stmtSlackErr = $conn->prepare(
                    "SELECT u.nome_slack FROM usuario u
                     JOIN colaborador c ON u.idcolaborador = c.idcolaborador
                     WHERE c.nome_colaborador IN ('Pedro Sabel', 'Andre L. de Souza')
                       AND u.nome_slack IS NOT NULL AND u.nome_slack != ''"
                );
                $stmtSlackErr->execute();
                $resSlackErr = $stmtSlackErr->get_result();
                $msgErroSftp = "\u26a0\ufe0f Falha no envio SFTP: *{$imagem_resumida}* ({$nome_funcao}). Status da tarefa *n\u00e3o foi alterado*. Verifique a conex\u00e3o com o servidor.";
                while ($rowErr = $resSlackErr->fetch_assoc()) {
                    enviarNotificacaoSlack($rowErr['nome_slack'], $msgErroSftp, $resultadoFinal['logs']);
                }
                $stmtSlackErr->close();
                echo json_encode($resultadoFinal);
                $conn->close();
                exit;
            }
            // ─────────────────────────────────────────────────────────────────────
        }

        // Commit: BD confirmado (SFTP enviado, conflito pendente ou SFTP não necessário)
        $conn->commit();

        // Slack envio final — só na 1ª chamada (não reenvia na resolução de conflito SFTP)
        if ($sftp_action !== null) {
            $resultadoFinal['logs'][] = 'Slack: notificação pulada (resolução de conflito SFTP).';
        } else {
            // Slack envio final — busca paginada de usuários e envia notificação
            $normalizedTargets = array_map('normalize_name', $nome_colaboradores);
            $slackFoundIDs = [];
            $slackCursor   = null;
            $slackPage     = 0;

            do {
                $slackPage++;
                $slackUrl = 'https://slack.com/api/users.list?limit=200';
                if ($slackCursor) {
                    $slackUrl .= '&cursor=' . urlencode($slackCursor);
                }

                $chList = curl_init($slackUrl);
                curl_setopt($chList, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$slackToken}"]);
                curl_setopt($chList, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chList, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($chList, CURLOPT_TIMEOUT, 8);
                curl_setopt($chList, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($chList, CURLOPT_SSL_VERIFYHOST, false);

                $slackRaw = curl_exec($chList);
                if (curl_errno($chList)) {
                    $err = curl_error($chList);
                    if (stripos($err, 'Resolving timed out') !== false || stripos($err, 'Could not resolve host') !== false) {
                        $resultadoFinal['logs'][] = 'Erro curl users.list (p.' . $slackPage . '): ' . $err . ' — verifique DNS/rotas de saída do servidor.';
                    } else {
                        $resultadoFinal['logs'][] = 'Erro curl users.list (p.' . $slackPage . '): ' . $err;
                    }
                    curl_close($chList);
                    break;
                }
                curl_close($chList);

                $slackData = json_decode($slackRaw, true);
                if (!is_array($slackData) || empty($slackData['ok'])) {
                    $resultadoFinal['logs'][] = 'Erro API Slack users.list: ' . ($slackData['error'] ?? ('resposta inválida: ' . substr((string)$slackRaw, 0, 300)));
                    break;
                }

                foreach ($slackData['members'] as $member) {
                    if (!empty($member['deleted']) || !empty($member['is_bot'])) {
                        continue;
                    }
                    $candidates = array_values(array_filter([
                        $member['real_name'] ?? null,
                        $member['profile']['real_name_normalized'] ?? null,
                        $member['profile']['display_name'] ?? null,
                        $member['profile']['display_name_normalized'] ?? null,
                    ]));
                    $normalizedCandidates = array_map('normalize_name', $candidates);
                    $candidateStr = implode(' ', $normalizedCandidates);

                    foreach ($normalizedTargets as $t) {
                        if ($t === '' || isset($slackFoundIDs[$member['id']])) {
                            continue;
                        }
                        // Exact match
                        if (in_array($t, $normalizedCandidates, true)) {
                            $slackFoundIDs[$member['id']] = true;
                            $resultadoFinal['logs'][] = 'Slack match exato: ' . $member['id'] . ' (' . implode(', ', array_slice($candidates, 0, 2)) . ') → ' . $t;
                            continue;
                        }
                        // Token-subset fallback (tokens >= 3 chars)
                        $tokens = array_values(array_filter(explode(' ', $t), fn($tok) => strlen($tok) >= 3));
                        if (!empty($tokens)) {
                            $allPresent = true;
                            foreach ($tokens as $tok) {
                                if (strpos($candidateStr, $tok) === false) {
                                    $allPresent = false;
                                    break;
                                }
                            }
                            if ($allPresent) {
                                $slackFoundIDs[$member['id']] = true;
                                $resultadoFinal['logs'][] = 'Slack match token: ' . $member['id'] . ' (' . implode(', ', array_slice($candidates, 0, 2)) . ') → ' . $t;
                            }
                        }
                    }
                }

                $slackCursor = $slackData['response_metadata']['next_cursor'] ?? null;
            } while (
                !empty($slackCursor) &&
                count($slackFoundIDs) < count($nome_colaboradores) &&
                $slackPage < 10
            );

            $slackFoundIDs = array_keys($slackFoundIDs);

            if (!empty($slackFoundIDs)) {
                foreach ($slackFoundIDs as $uid) {
                    enviarNotificacaoSlack($uid, $mensagemSlack, $resultadoFinal['logs']);
                }
            } else {
                $resultadoFinal['logs'][] = 'Usuário(s) ' . implode(', ', $nome_colaboradores) . ' não encontrado(s) no Slack.';
            }
        } // fim do bloco Slack (if $sftp_action === null)
    } catch (Throwable $e) {
        http_response_code(500);
        $resultadoFinal['success'] = false;
        $resultadoFinal['message'] = 'Erro interno: ' . $e->getMessage();
        $resultadoFinal['exception'] = ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
        // Attempt to close connection if available
        if (isset($conn) && $conn) {
            $conn->close();
        }
        echo json_encode($resultadoFinal);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$conn->close();
echo json_encode($resultadoFinal);
