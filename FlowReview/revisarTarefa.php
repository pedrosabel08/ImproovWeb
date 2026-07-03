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

include_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../Entregas/p00_delivery_helpers.php';
require_once __DIR__ . '/../Entregas/pendencias_entrega_helper.php';
require_once __DIR__ . '/../helpers/aprovacao_interna_helper.php';
require_once __DIR__ . '/approval_media_schema.php';
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

function improov_review_normalize_delivery_file_name(string $filePath): string
{
    $originalName = basename($filePath);
    $normalizedName = preg_replace('/(_\d+)+(\.([^.]+))$/', '$2', $originalName);
    if ($normalizedName === $originalName) {
        return $originalName;
    }

    return $normalizedName;
}

function improov_review_resolve_p00_modelagem_dir(SFTP $sftp, string $base, string $nomenclaturaObra, array &$logs): ?string
{
    $modelsRoot = rtrim("{$base}/{$nomenclaturaObra}/03.Models", '/');
    if (!$sftp->is_dir($modelsRoot)) {
        $logs[] = "Diretório {$modelsRoot} não existe.";
        return null;
    }

    $modelagemDir = $modelsRoot . '/Modelagem_Fachada';
    if ($sftp->is_dir($modelagemDir)) {
        return $modelagemDir;
    }

    if ($sftp->mkdir($modelagemDir, -1, true) || $sftp->is_dir($modelagemDir)) {
        $logs[] = "Diretório {$modelagemDir} criado com sucesso.";
        return $modelagemDir;
    }

    $logs[] = "Falha ao criar diretório {$modelagemDir}.";
    return null;
}

function improov_review_fetch_batch_histories(mysqli $conn, int $funcaoImagemId, ?int $historicoId = null): array
{
    $batchIndex = 0;

    if ($historicoId) {
        $stmtBatch = $conn->prepare('SELECT COALESCE(indice_envio, 0) AS indice_envio FROM historico_aprovacoes_imagens WHERE id = ? AND funcao_imagem_id = ? LIMIT 1');
        if ($stmtBatch) {
            $stmtBatch->bind_param('ii', $historicoId, $funcaoImagemId);
            $stmtBatch->execute();
            $stmtBatch->bind_result($batchIndexFound);
            if ($stmtBatch->fetch()) {
                $batchIndex = (int) $batchIndexFound;
            }
            $stmtBatch->close();
        }
    } else {
        $stmtBatch = $conn->prepare('SELECT COALESCE(MAX(indice_envio), 0) AS indice_envio FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ?');
        if ($stmtBatch) {
            $stmtBatch->bind_param('i', $funcaoImagemId);
            $stmtBatch->execute();
            $stmtBatch->bind_result($batchIndexFound);
            if ($stmtBatch->fetch()) {
                $batchIndex = (int) $batchIndexFound;
            }
            $stmtBatch->close();
        }
    }

    if ($batchIndex > 0) {
        $stmtHist = $conn->prepare('SELECT id, COALESCE(nome_arquivo, \'\') AS nome_arquivo, imagem, indice_envio FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? AND indice_envio = ? ORDER BY id ASC');
        if (!$stmtHist) {
            return [];
        }

        $stmtHist->bind_param('ii', $funcaoImagemId, $batchIndex);
        $stmtHist->execute();
        $result = $stmtHist->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmtHist->close();

        return $rows;
    }

    if ($historicoId) {
        $stmtHist = $conn->prepare('SELECT id, COALESCE(nome_arquivo, \'\') AS nome_arquivo, imagem, COALESCE(indice_envio, 0) AS indice_envio FROM historico_aprovacoes_imagens WHERE id = ? AND funcao_imagem_id = ? LIMIT 1');
        if (!$stmtHist) {
            return [];
        }

        $stmtHist->bind_param('ii', $historicoId, $funcaoImagemId);
    } else {
        $stmtHist = $conn->prepare('SELECT id, COALESCE(nome_arquivo, \'\') AS nome_arquivo, imagem, COALESCE(indice_envio, 0) AS indice_envio FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? ORDER BY id DESC LIMIT 1');
        if (!$stmtHist) {
            return [];
        }

        $stmtHist->bind_param('i', $funcaoImagemId);
    }

    $stmtHist->execute();
    $result = $stmtHist->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmtHist->close();

    return $row ? [$row] : [];
}

function improov_review_locate_history_file(array $historyRow, string $uploadDir, array &$logs): ?array
{
    $imageDbPath = trim((string) ($historyRow['imagem'] ?? ''));
    $nameBase = trim((string) ($historyRow['nome_arquivo'] ?? ''));

    if ($imageDbPath !== '') {
        $directPath = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $imageDbPath), '/');
        if (is_file($directPath)) {
            return ['path' => $directPath, 'cleanup' => null];
        }

        $imageBaseName = basename(str_replace('\\', '/', $imageDbPath));
        if ($imageBaseName !== '') {
            $uploadCandidate = $uploadDir . $imageBaseName;
            if (is_file($uploadCandidate)) {
                return ['path' => $uploadCandidate, 'cleanup' => null];
            }

            if ($nameBase === '') {
                $nameBase = pathinfo($imageBaseName, PATHINFO_FILENAME);
            }
        }
    }

    if ($nameBase !== '') {
        $globCandidates = glob($uploadDir . $nameBase . '.*') ?: [];
        if (!empty($globCandidates)) {
            return ['path' => $globCandidates[0], 'cleanup' => null];
        }
    }

    try {
        $vpsCfg = improov_sftp_config('IMPROOV_VPS_SFTP');
        $vpsBase = rtrim((string) improov_env('IMPROOV_VPS_SFTP_REMOTE_PATH'), '/');
        $vpsDir = $vpsBase . '/uploads/';
        $vsftp = new SFTP($vpsCfg['host'], (int) $vpsCfg['port']);
        if (!$vsftp->login($vpsCfg['user'], $vpsCfg['pass'])) {
            $logs[] = 'Falha ao conectar no VPS SFTP para buscar lote de modelagem.';
            return null;
        }

        $remoteList = $vsftp->nlist($vpsDir);
        if (!is_array($remoteList)) {
            return null;
        }

        foreach ($remoteList as $remoteFile) {
            $remoteBaseName = basename($remoteFile);
            $remoteBaseNoExt = pathinfo($remoteBaseName, PATHINFO_FILENAME);
            if ($nameBase !== '' && $remoteBaseNoExt !== $nameBase) {
                continue;
            }

            $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $remoteBaseName;
            if ($vsftp->get($vpsDir . $remoteBaseName, $tempPath)) {
                $logs[] = "Arquivo do lote baixado do VPS: {$remoteBaseName}";
                return ['path' => $tempPath, 'cleanup' => $tempPath];
            }
        }
    } catch (RuntimeException $e) {
        $logs[] = 'VPS SFTP config ausente para lote de modelagem: ' . $e->getMessage();
    }

    return null;
}

$resultadoFinal = ['logs' => []];

// Lê sessão para verificar permissões de aprovação dupla
$idusuario_session   = isset($_SESSION['idusuario'])    ? (int)$_SESSION['idusuario']    : 0;
$idcolaborador_session = isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        fr_approval_media_ensure_schema($conn);
        $data = json_decode(file_get_contents('php://input'), true);
        $idfuncao_imagem = isset($data['idfuncao_imagem']) ? (int)$data['idfuncao_imagem'] : 0;
        $tipo_tarefa = strtolower((string)($data['tipo_tarefa'] ?? 'imagem'));
        $funcao_animacao_id = isset($data['funcao_animacao_id']) ? (int)$data['funcao_animacao_id'] : 0;
        $is_animacao_review = $tipo_tarefa === 'animacao' || $funcao_animacao_id > 0;
        if ($is_animacao_review && $funcao_animacao_id <= 0) {
            $funcao_animacao_id = $idfuncao_imagem;
        }
        $tipoRevisao = $data['tipoRevisao'] ?? null;
        $imagem_nome = $data['imagem_nome'] ?? null;
        $nome_funcao = $data['nome_funcao'] ?? null;
        $colaborador_id = isset($data['colaborador_id']) ? (int)$data['colaborador_id'] : 0;
        $responsavel = isset($data['responsavel']) ? (int)$data['responsavel'] : 0;
        $imagem_id = isset($data['imagem_id']) ? (int)$data['imagem_id'] : 0;
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

        if ($responsavel <= 0) {
            $responsavel = $idcolaborador_session;
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

        if ($is_animacao_review) {
            $stmtAnimContext = $conn->prepare(
                "SELECT fa.status, fa.colaborador_id, fa.funcao_id, fun.nome_funcao, i.imagem_nome, u.nome_slack
                 FROM funcao_animacao fa
                 LEFT JOIN funcao fun ON fun.idfuncao = fa.funcao_id
                 LEFT JOIN animacao a ON a.idanimacao = fa.animacao_id
                 LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = a.imagem_id
                 LEFT JOIN colaborador c ON c.idcolaborador = fa.colaborador_id
                 LEFT JOIN usuario u ON u.idcolaborador = c.idcolaborador
                 WHERE fa.id = ?
                 LIMIT 1"
            );
            if (!$stmtAnimContext) {
                echo json_encode(['success' => false, 'message' => 'Erro ao preparar consulta da animacao.']);
                exit;
            }

            $stmtAnimContext->bind_param("i", $funcao_animacao_id);
            $stmtAnimContext->execute();
            $animContext = $stmtAnimContext->get_result()->fetch_assoc();
            $stmtAnimContext->close();

            if (!$animContext) {
                echo json_encode(['success' => false, 'message' => 'Funcao de animacao nao encontrada.']);
                exit;
            }

            $status_anterior = (string)($animContext['status'] ?? '');
            $colaborador_id = (int)($animContext['colaborador_id'] ?? $colaborador_id);
            $nome_funcao_animacao = (string)($animContext['nome_funcao'] ?? ($nome_funcao ?: 'Animacao'));
            $imagem_nome_animacao = (string)($animContext['imagem_nome'] ?? ($imagem_nome ?: ''));

            $stmtAnimUpdate = $conn->prepare("UPDATE funcao_animacao SET status = ? WHERE id = ?");
            if (!$stmtAnimUpdate) {
                echo json_encode(['success' => false, 'message' => 'Erro ao preparar atualizacao da animacao.']);
                exit;
            }
            $stmtAnimUpdate->bind_param("si", $status, $funcao_animacao_id);
            if (!$stmtAnimUpdate->execute()) {
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar animacao: ' . $stmtAnimUpdate->error]);
                $stmtAnimUpdate->close();
                exit;
            }
            $stmtAnimUpdate->close();

            $stmtAnimHist = $conn->prepare(
                "INSERT INTO historico_aprovacoes
                    (funcao_imagem_id, funcao_animacao_id, status_anterior, status_novo, colaborador_id, responsavel)
                 VALUES (NULL, ?, ?, ?, ?, ?)"
            );
            if (!$stmtAnimHist) {
                echo json_encode(['success' => false, 'message' => 'Erro ao preparar historico da animacao.']);
                exit;
            }
            $stmtAnimHist->bind_param("issii", $funcao_animacao_id, $status_anterior, $status, $colaborador_id, $responsavel);
            if (!$stmtAnimHist->execute()) {
                echo json_encode(['success' => false, 'message' => 'Erro ao registrar historico da animacao: ' . $stmtAnimHist->error]);
                $stmtAnimHist->close();
                exit;
            }
            $stmtAnimHist->close();

            if (!empty($animContext['nome_slack'])) {
                $slackLog = [];
                $mensagemAnimacao = "A {$nome_funcao_animacao} da imagem {$imagem_nome_animacao} foi revisada por {$nome_responsavel}. Status: {$status}.";
                enviarNotificacaoSlack($animContext['nome_slack'], $mensagemAnimacao, $slackLog);
                $resultadoFinal['logs'] = array_merge($resultadoFinal['logs'], $slackLog);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Revisao de animacao registrada com sucesso.',
                'status' => $status,
                'tipo_tarefa' => 'animacao',
                'funcao_animacao_id' => $funcao_animacao_id,
                'logs' => $resultadoFinal['logs'],
            ]);
            exit;
        }

        $funcao_id_context = null;
        $nome_funcao_db = null;
        $imagem_id_context = $imagem_id ? (int)$imagem_id : null;
        $colaborador_id_context = 0;
        $status_funcao_context = null;
        $stmtFuncaoContext = $conn->prepare("SELECT fi.funcao_id, fun.nome_funcao, fi.imagem_id, fi.colaborador_id, fi.status
            FROM funcao_imagem fi
            LEFT JOIN funcao fun ON fun.idfuncao = fi.funcao_id
            WHERE fi.idfuncao_imagem = ?
            LIMIT 1");
        if ($stmtFuncaoContext) {
            $stmtFuncaoContext->bind_param("i", $idfuncao_imagem);
            $stmtFuncaoContext->execute();
            $stmtFuncaoContext->bind_result($funcao_id_context, $nome_funcao_db, $imagem_id_context_db, $colaborador_id_context, $status_funcao_context);
            $stmtFuncaoContext->fetch();
            $stmtFuncaoContext->close();
            if ($imagem_id_context_db) {
                $imagem_id_context = (int)$imagem_id_context_db;
            }
            if ($colaborador_id_context) {
                $colaborador_id = (int)$colaborador_id_context;
            }
        }

        $nomeFuncaoLower = mb_strtolower((string)($nome_funcao_db ?: $nome_funcao), 'UTF-8');
        $nomeFuncaoKey = normalize_name((string)($nome_funcao_db ?: $nome_funcao));

        // ── Aprovação dupla de Pós-produção/Alteração ────────────────────────────
        // Quando o aprovador operacional (colaborador 1) aprova pós-produção ou
        // alteração, a tarefa aguarda validação da direção. Somente direção
        // (colaboradores 21 ou 2) segue para o fluxo final/SFTP.
        $aguardandoDirecao = false;
        $aprovadorDirecaoId = (int)($responsavel ?: $idcolaborador_session);
        $isFuncaoComDirecao = (
            in_array((int)$funcao_id_context, [4, 5, 6], true)
            || in_array($nomeFuncaoKey, ['finalizacao', 'posproducao', 'pos producao', 'alteracao'], true)
            || in_array($nomeFuncaoLower, ['pós-produção', 'alteração'], true)
        );
        $isTipoAprovacaoComDirecao = in_array($tipoRevisao, ['aprovado', 'aprovado_com_ajustes'], true);
        $isPrimeiroAprovadorDirecao = ($aprovadorDirecaoId === 1);
        $isDirecaoAprovador = in_array($aprovadorDirecaoId, [21, 9, 31], true);
        $isFinalizadorAprovadorDirecao = (
            !$isDirecaoAprovador
            && $aprovadorDirecaoId > 0
            && (int)$funcao_id_context === 4
            && (int)$colaborador_id === $aprovadorDirecaoId
        );

        if (
            !$isFinalizadorAprovadorDirecao
            && !$isDirecaoAprovador
            && $aprovadorDirecaoId > 0
            && $imagem_id_context
            && in_array((int)$funcao_id_context, [5, 6], true)
        ) {
            $stmtFinalizadorDirecao = $conn->prepare(
                "SELECT 1
                 FROM funcao_imagem
                 WHERE imagem_id = ?
                   AND colaborador_id = ?
                   AND funcao_id IN (4, 6)
                   AND idfuncao_imagem <> ?
                 LIMIT 1"
            );
            if ($stmtFinalizadorDirecao) {
                $stmtFinalizadorDirecao->bind_param("iii", $imagem_id_context, $aprovadorDirecaoId, $idfuncao_imagem);
                $stmtFinalizadorDirecao->execute();
                $stmtFinalizadorDirecao->store_result();
                $isFinalizadorAprovadorDirecao = ($stmtFinalizadorDirecao->num_rows > 0);
                $stmtFinalizadorDirecao->close();
            }
        }

        if ($isFuncaoComDirecao && $isTipoAprovacaoComDirecao && ($isPrimeiroAprovadorDirecao || $isFinalizadorAprovadorDirecao) && !$isDirecaoAprovador) {
            $status_dir = "Aguardando Direção";
            $status_ant_dir = $status_funcao_context ?: "Em aprovação";
            $novoHistoricoDirecao = false;
            $historicoDirecaoId = 0;

            $conn->begin_transaction();
            try {
                $ultimoStatusHistorico = null;
                $observacoesDirecaoAtual = null;
                $stmtChkDir = $conn->prepare(
                    "SELECT id, status_novo, observacoes FROM historico_aprovacoes WHERE funcao_imagem_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE"
                );
                if (!$stmtChkDir) {
                    throw new RuntimeException('Falha ao preparar verificacao de aprovacao da direcao: ' . $conn->error);
                }
                $stmtChkDir->bind_param("i", $idfuncao_imagem);
                $stmtChkDir->execute();
                $stmtChkDir->bind_result($historicoDirecaoId, $ultimoStatusHistorico, $observacoesDirecaoAtual);
                $temHistoricoAnterior = $stmtChkDir->fetch();
                $jaAguardandoDirecao = ($temHistoricoAnterior && $ultimoStatusHistorico === $status_dir);
                $stmtChkDir->close();

                $observacaoDirecao = json_encode([
                    'aprovacao_operacional' => $status,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if (!$jaAguardandoDirecao) {
                    $stmtDir = $conn->prepare(
                        "INSERT INTO historico_aprovacoes (funcao_imagem_id, status_anterior, status_novo, colaborador_id, responsavel, observacoes) VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    if (!$stmtDir) {
                        throw new RuntimeException('Falha ao preparar historico de aguardando direcao: ' . $conn->error);
                    }
                    $stmtDir->bind_param("issiis", $idfuncao_imagem, $status_ant_dir, $status_dir, $colaborador_id, $responsavel, $observacaoDirecao);
                    if (!$stmtDir->execute()) {
                        $erroInsertDirecao = $stmtDir->error;
                        $stmtDir->close();
                        throw new RuntimeException('Falha ao registrar historico de aguardando direcao: ' . $erroInsertDirecao);
                    }
                    $historicoDirecaoId = (int)$conn->insert_id;
                    $novoHistoricoDirecao = true;
                    $stmtDir->close();
                } else {
                    $obsAtual = json_decode((string)$observacoesDirecaoAtual, true);
                    if (!is_array($obsAtual) || empty($obsAtual['aprovacao_operacional'])) {
                        $stmtObsDir = $conn->prepare(
                            "UPDATE historico_aprovacoes SET observacoes = ? WHERE id = ?"
                        );
                        if (!$stmtObsDir) {
                            throw new RuntimeException('Falha ao preparar complemento da aprovacao operacional: ' . $conn->error);
                        }
                        $stmtObsDir->bind_param("si", $observacaoDirecao, $historicoDirecaoId);
                        if (!$stmtObsDir->execute()) {
                            $erroObsDirecao = $stmtObsDir->error;
                            $stmtObsDir->close();
                            throw new RuntimeException('Falha ao complementar aprovacao operacional: ' . $erroObsDirecao);
                        }
                        $stmtObsDir->close();
                    }
                }

                $stmtStatusDir = $conn->prepare(
                    "UPDATE funcao_imagem SET status = ?, prioridade_aprovacao = 0 WHERE idfuncao_imagem = ?"
                );
                if (!$stmtStatusDir) {
                    throw new RuntimeException('Falha ao preparar status de aguardando direcao: ' . $conn->error);
                }
                $stmtStatusDir->bind_param("si", $status_dir, $idfuncao_imagem);
                if (!$stmtStatusDir->execute()) {
                    $erroStatusDirecao = $stmtStatusDir->error;
                    $stmtStatusDir->close();
                    throw new RuntimeException('Falha ao atualizar status para aguardando direcao: ' . $erroStatusDirecao);
                }
                $stmtStatusDir->close();

                $stmtVerifyDir = $conn->prepare(
                    "SELECT h.id
                     FROM historico_aprovacoes h
                     JOIN funcao_imagem fi ON fi.idfuncao_imagem = h.funcao_imagem_id
                     WHERE h.funcao_imagem_id = ?
                       AND h.status_novo = ?
                       AND fi.status = ?
                       AND NOT EXISTS (
                           SELECT 1
                           FROM historico_aprovacoes h2
                           WHERE h2.funcao_imagem_id = h.funcao_imagem_id
                             AND h2.id > h.id
                       )
                     ORDER BY h.id DESC
                     LIMIT 1"
                );
                if (!$stmtVerifyDir) {
                    throw new RuntimeException('Falha ao preparar verificacao final de aguardando direcao: ' . $conn->error);
                }
                $stmtVerifyDir->bind_param("iss", $idfuncao_imagem, $status_dir, $status_dir);
                $stmtVerifyDir->execute();
                $stmtVerifyDir->store_result();
                $gravacaoDirecaoOk = ($stmtVerifyDir->num_rows > 0);
                $stmtVerifyDir->close();

                if (!$gravacaoDirecaoOk) {
                    throw new RuntimeException('Aprovacao de direcao nao foi confirmada no banco.');
                }

                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }

            if ($novoHistoricoDirecao) {
                $stmtDirSlack = $conn->prepare(
                    "SELECT u.nome_slack FROM usuario u WHERE u.idcolaborador IN (21, 2) AND u.nome_slack IS NOT NULL AND u.nome_slack != ''"
                );
                $stmtDirSlack->execute();
                $resDirSlack = $stmtDirSlack->get_result();
                $funcaoDirecaoLabel = $nome_funcao_db ?: $nome_funcao;
                $mensagemDirecao = "⏳ A {$funcaoDirecaoLabel} de {$imagem_resumida} aguarda validação da direção (aprovada por {$nome_responsavel}).";
                while ($rowSlack = $resDirSlack->fetch_assoc()) {
                    enviarNotificacaoSlack($rowSlack['nome_slack'], $mensagemDirecao, $resultadoFinal['logs']);
                }
                $stmtDirSlack->close();
            }

            $resultadoFinal['success']          = true;
            $resultadoFinal['message']          = 'Aprovação registrada. Aguardando validação da direção.';
            $resultadoFinal['aguardando_direcao'] = true;
            $resultadoFinal['status_aprovacao'] = $status;
            $resultadoFinal['historico_direcao_id'] = $historicoDirecaoId;
            echo json_encode($resultadoFinal);
            $conn->close();
            exit;
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

        entregas_pendencias_ensure_schema($conn);
        aprovacao_interna_ensure_schema($conn);
        $conn->begin_transaction();

        if (
            $isDirecaoAprovador
            && $tipoRevisao === 'aprovado'
            && normalize_name((string)$status_funcao_context) === 'aguardando direcao'
        ) {
            $stmtOrigemDirecao = $conn->prepare(
                "SELECT observacoes
                 FROM historico_aprovacoes
                 WHERE funcao_imagem_id = ?
                   AND status_novo = 'Aguardando Direção'
                 ORDER BY id DESC
                 LIMIT 1"
            );
            if ($stmtOrigemDirecao) {
                $stmtOrigemDirecao->bind_param("i", $idfuncao_imagem);
                $stmtOrigemDirecao->execute();
                $stmtOrigemDirecao->bind_result($observacoesDirecao);
                if ($stmtOrigemDirecao->fetch()) {
                    $origemDirecao = json_decode((string)$observacoesDirecao, true);
                    if (
                        is_array($origemDirecao)
                        && ($origemDirecao['aprovacao_operacional'] ?? null) === 'Aprovado com ajustes'
                    ) {
                        $status = 'Aprovado com ajustes';
                    }
                }
                $stmtOrigemDirecao->close();
            }
        }

        $stmt = $conn->prepare("UPDATE funcao_imagem SET status = ? WHERE idfuncao_imagem = ?");
        $stmt->bind_param("si", $status, $idfuncao_imagem);

        if ($stmt->execute()) {
            $stmt->close();

            // Reset prioridade ao aprovar (qualquer tipo de aprovação)
            if (in_array($tipoRevisao, ['aprovado', 'aprovado_com_ajustes'])) {
                $stmtPrio = $conn->prepare(
                    "UPDATE funcao_imagem SET prioridade_aprovacao = 0 WHERE idfuncao_imagem = ?"
                );
                $stmtPrio->bind_param("i", $idfuncao_imagem);
                $stmtPrio->execute();
                $stmtPrio->close();
            }

            $status_anterior = $status_funcao_context ?: "Em aprovação";
            $stmt = $conn->prepare("INSERT INTO historico_aprovacoes (funcao_imagem_id, status_anterior, status_novo, colaborador_id, responsavel) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issii", $idfuncao_imagem, $status_anterior, $status, $colaborador_id, $responsavel);
            $stmt->execute();
            $historicoAprovacaoId = (int)$conn->insert_id;
            $stmt->close();

            $resultadoFinal['success'] = true;
            $resultadoFinal['message'] = 'Tarefa atualizada com sucesso.';

            $tipo_imagem_nome = null;
            $status_nome_imagem = null;
            $img_status_id_context = null;
            $img_obra_id_context = null;
            $nomenclatura_obra = null;
            $funcao_id_context = null;
            $nome_funcao_db = null;
            if ($imagem_id) {
                $stmtTipo = $conn->prepare("SELECT i.tipo_imagem, i.status_id, i.obra_id, s.nome_status, o.nomenclatura
                    FROM imagens_cliente_obra i
                    JOIN status_imagem s ON s.idstatus = i.status_id
                    JOIN obra o ON o.idobra = i.obra_id
                    WHERE i.idimagens_cliente_obra = ?");
                $stmtTipo->bind_param("i", $imagem_id);
                $stmtTipo->execute();
                $stmtTipo->bind_result($tipo_imagem_nome, $img_status_id_context, $img_obra_id_context, $status_nome_imagem, $nomenclatura_obra);
                $stmtTipo->fetch();
                $stmtTipo->close();
            }

            $stmtFuncaoContext = $conn->prepare("SELECT fi.funcao_id, fun.nome_funcao
                FROM funcao_imagem fi
                LEFT JOIN funcao fun ON fun.idfuncao = fi.funcao_id
                WHERE fi.idfuncao_imagem = ?
                LIMIT 1");
            if ($stmtFuncaoContext) {
                $stmtFuncaoContext->bind_param("i", $idfuncao_imagem);
                $stmtFuncaoContext->execute();
                $stmtFuncaoContext->bind_result($funcao_id_context, $nome_funcao_db);
                $stmtFuncaoContext->fetch();
                $stmtFuncaoContext->close();
            }

            $nomeFuncaoLower = mb_strtolower((string)($nome_funcao_db ?: $nome_funcao), 'UTF-8');
            $isAlteracaoHumanizadaRender = (
                (int)$funcao_id_context === 6
                && stripos((string)$tipo_imagem_nome, 'humanizada') !== false
            );

            if (
                in_array($status, ['Aprovado', 'Aprovado com ajustes'], true)
                && $isDirecaoAprovador
                && (int)$funcao_id_context === 6
                && !$isAlteracaoHumanizadaRender
            ) {
                $alteracaoAprovacao = aprovacao_interna_resolver_alteracao_por_funcao($conn, (int)$idfuncao_imagem);
                if (
                    $alteracaoAprovacao
                    && !aprovacao_interna_render_existe_na_etapa($conn, $alteracaoAprovacao['imagem_id'], $alteracaoAprovacao['status_id'])
                ) {
                    $aprovacaoRegistrada = aprovacao_interna_registrar(
                        $conn,
                        $alteracaoAprovacao['funcao_imagem_id'],
                        $alteracaoAprovacao['imagem_id'],
                        $alteracaoAprovacao['status_id'],
                        'flowreview',
                        $aprovadorDirecaoId,
                        null,
                        $historicoAprovacaoId,
                        null
                    );
                    $resultadoFinal['logs'][] = $aprovacaoRegistrada
                        ? 'aprovacao_interna.flowreview_registrada'
                        : 'aprovacao_interna.flowreview_nao_registrada';
                } else {
                    $resultadoFinal['logs'][] = 'aprovacao_interna.flowreview_ignorada_render_existente';
                }
            }

            $isP00ModelagemReview = (
                in_array($status, ['Aprovado'], true)
                && $nomeFuncaoLower === 'modelagem'
                && $status_nome_imagem === 'P00'
            );

            // Mensagem Slack específica para modelagem de fachada P00
            if ($nomeFuncaoLower === 'modelagem' && $status_nome_imagem === 'P00') {
                $projectLabel = (string) ($nomenclatura_obra ?? $imagem_resumida);
                if ($tipoRevisao === 'aprovado') {
                    $mensagemSlack = "A modelagem de fachada do projeto {$projectLabel} foi aprovada.";
                } elseif ($tipoRevisao === 'ajuste') {
                    $mensagemSlack = "A modelagem de fachada do projeto {$projectLabel} teve ajustes.";
                } else {
                    $mensagemSlack = "A modelagem de fachada do projeto {$projectLabel} foi aprovada com ajustes.";
                }
            }

            $p00EntregaAtual = null;
            $p00VersaoAtual = null;

            if ($isP00ModelagemReview) {
                $p00EntregaAtual = improov_p00_fetch_latest_delivery($conn, (int) $img_obra_id_context, (int) $img_status_id_context);
                if (!$p00EntregaAtual) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Entrega P00 não encontrada para a obra. Crie a entrega antes de aprovar a modelagem.']);
                    exit;
                }

                $p00VersaoAtual = improov_p00_fetch_latest_version($conn, (int) $p00EntregaAtual['id']);
                if (!$p00VersaoAtual) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Versão P00 não encontrada para a entrega.']);
                    exit;
                }
            }

            // Ao aprovar uma função, atualizar vínculos de entrega
            // - Para P00 + Finalização: vincular TODOS os ângulos (historico_aprovacoes_imagens) ao item de entrega via angulos_imagens.entrega_item_id
            // - Para demais (R00..EF): atualizar entregas_itens.historico_id com o id correspondente (último)
            $isImagemHumanizadaEntrega = stripos((string)$tipo_imagem_nome, 'humanizada') !== false;
            $deveAtualizarEntregaAprovada = (
                $nomeFuncaoLower === 'pós-produção'
                || ($nomeFuncaoLower === 'finalização' && $isImagemHumanizadaEntrega)
                || ($nomeFuncaoLower === 'alteração' && $isImagemHumanizadaEntrega && $isDirecaoAprovador)
            );
            $statusPermiteAtualizarEntrega = (
                in_array($status, ['Aprovado'], true)
                || (
                    $status === 'Aprovado com ajustes'
                    && $nomeFuncaoLower === 'alteração'
                    && $isImagemHumanizadaEntrega
                    && $isDirecaoAprovador
                )
            );

            if (
                $statusPermiteAtualizarEntrega &&
                $deveAtualizarEntregaAprovada
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

                        // Encontra o item dessa imagem em qualquer entrega da mesma obra/etapa.
                        // Pode haver mais de uma entrega R01/R02/etc.; a entrega mais recente nem sempre contem a imagem.
                        $stmtItem = $conn->prepare("SELECT ei.id, ei.entrega_id
                            FROM entregas_itens ei
                            JOIN entregas e ON e.id = ei.entrega_id
                            WHERE e.status_id = ? AND e.obra_id = ? AND ei.imagem_id = ?
                            ORDER BY e.id DESC, ei.id DESC
                            LIMIT 1");
                        if ($stmtItem) {
                            $stmtItem->bind_param("iii", $img_status_id, $img_obra_id, $imagem_id);
                            $stmtItem->execute();
                            $stmtItem->bind_result($entrega_item_id, $entrega_id_found);
                            if ($stmtItem->fetch()) {
                                $stmtItem->close();

                                resolver_pendencias_entrega(
                                    $conn,
                                    (int) $entrega_id_found,
                                    (int) $imagem_id,
                                    (int) $entrega_item_id,
                                    isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null,
                                    $resultadoFinal['logs']
                                );

                                // Verifica se é o caso especial de P00 + função finalização
                                $isFinalizacao = ($nomeFuncaoLower === 'finalização');
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
                                $hist_img_id_pendente = null;
                                $stmtHistPend = $conn->prepare("SELECT id FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? ORDER BY id DESC LIMIT 1");
                                if ($stmtHistPend) {
                                    $stmtHistPend->bind_param("i", $idfuncao_imagem);
                                    $stmtHistPend->execute();
                                    $stmtHistPend->bind_result($hist_img_id_pendente);
                                    $stmtHistPend->fetch();
                                    $stmtHistPend->close();
                                }

                                $motivoPendencia = "Aprovacao sem entrega_item para obra_id={$img_obra_id}, status_id={$img_status_id}, imagem_id={$imagem_id}.";
                                $pendenciaId = registrar_pendencia_entrega(
                                    $conn,
                                    (int) $img_obra_id,
                                    (int) $img_status_id,
                                    (int) $imagem_id,
                                    (int) $idfuncao_imagem,
                                    $hist_img_id_pendente ? (int) $hist_img_id_pendente : null,
                                    $motivoPendencia,
                                    $resultadoFinal['logs']
                                );
                                $resultadoFinal['entrega_pendencia_id'] = $pendenciaId;
                                $resultadoFinal['logs'][] = "entregas_itens para status_id=$img_status_id obra_id=$img_obra_id imagem_id=$imagem_id não encontrado; pendencia registrada.";
                            }
                        } else {
                            $resultadoFinal['logs'][] = "Falha ao preparar busca de entregas_itens para status_id=$img_status_id obra_id=$img_obra_id imagem_id=$imagem_id.";
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
            $isP00ModelagemReview
            ||
            (
                (in_array($nomeFuncaoLower, ['pós-produção'])) &&
                in_array($status, ['Aprovado'])
            )
            ||
            (
                // 🔸 Finalização ou Alteração de Planta Humanizada
                in_array($nomeFuncaoLower, ['finalização', 'alteração'], true) &&
                stripos((string)$tipo_imagem_nome, 'humanizada') !== false &&
                $status === 'Aprovado'
            )
        ) {
            if ($isP00ModelagemReview) {
                $uploadDir = dirname(__DIR__) . "/uploads/";
                $reviewDir = $uploadDir . 'review/';
                if (!is_dir($reviewDir)) {
                    mkdir($reviewDir, 0777, true);
                }

                $batchHistories = improov_review_fetch_batch_histories($conn, (int) $idfuncao_imagem, $historico_id ?: null);
                if (empty($batchHistories)) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo encontrado no lote atual da modelagem.']);
                    exit;
                }

                $resolvedFiles = [];
                $tempFiles = [];
                foreach ($batchHistories as $historyRow) {
                    $locatedFile = improov_review_locate_history_file($historyRow, $uploadDir, $resultadoFinal['logs']);
                    if (!$locatedFile || empty($locatedFile['path']) || !is_file($locatedFile['path'])) {
                        foreach ($tempFiles as $tempFile) {
                            if ($tempFile && is_file($tempFile)) {
                                @unlink($tempFile);
                            }
                        }
                        $conn->rollback();
                        echo json_encode(['success' => false, 'message' => 'Nem todos os arquivos do lote atual da modelagem foram localizados para envio.']);
                        exit;
                    }

                    $normalizedFileName = improov_review_normalize_delivery_file_name($locatedFile['path']);
                    $reviewTarget = $reviewDir . $normalizedFileName;
                    if (!copy($locatedFile['path'], $reviewTarget)) {
                        $resultadoFinal['logs'][] = "Falha ao copiar {$normalizedFileName} para a pasta review.";
                    } else {
                        $resultadoFinal['logs'][] = "Arquivo do lote copiado para review: {$reviewTarget}";
                    }

                    $resolvedFiles[] = [
                        'historico_id' => (int) ($historyRow['id'] ?? 0),
                        'local_path' => $locatedFile['path'],
                        'file_name' => $normalizedFileName,
                    ];
                    if (!empty($locatedFile['cleanup'])) {
                        $tempFiles[] = $locatedFile['cleanup'];
                    }
                }

                try {
                    $sftpCfg = improov_sftp_config();
                } catch (RuntimeException $e) {
                    foreach ($tempFiles as $tempFile) {
                        if ($tempFile && is_file($tempFile)) {
                            @unlink($tempFile);
                        }
                    }
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Configuração SFTP não disponível para envio da modelagem.']);
                    exit;
                }

                $bases = ['/mnt/clientes/2024', '/mnt/clientes/2025', '/mnt/clientes/2026'];
                $modelagemNasPath = '03.Models/Modelagem_Fachada';
                $versionLabel = (string) ($p00VersaoAtual['versao_label'] ?? 'V1');
                $primaryHistoryId = $historico_id ?: (int) ($batchHistories[0]['id'] ?? 0);
                $primaryFileName = null;
                $uploadedTargets = [];
                $allUploaded = false;

                foreach ($bases as $base) {
                    try {
                        $sftp = new SFTP($sftpCfg['host'], (int) $sftpCfg['port'], 60);
                        if (!$sftp->login($sftpCfg['user'], $sftpCfg['pass'])) {
                            $resultadoFinal['logs'][] = "Falha ao autenticar no SFTP para a base {$base}.";
                            continue;
                        }
                    } catch (Throwable $e) {
                        $resultadoFinal['logs'][] = "Erro ao conectar no SFTP para {$base}: " . $e->getMessage();
                        continue;
                    }

                    $modelagemDir = improov_review_resolve_p00_modelagem_dir($sftp, $base, (string) $nomenclatura_obra, $resultadoFinal['logs']);
                    if (!$modelagemDir) {
                        continue;
                    }

                    $toonsDir = $modelagemDir . '/Toons';
                    if (!$sftp->is_dir($toonsDir) && !$sftp->mkdir($toonsDir, -1, true)) {
                        $resultadoFinal['logs'][] = "Falha ao criar diretório {$toonsDir}.";
                        continue;
                    }

                    $versionDir = $toonsDir . '/' . $versionLabel;
                    if (!$sftp->is_dir($versionDir) && !$sftp->mkdir($versionDir, -1, true)) {
                        $resultadoFinal['logs'][] = "Falha ao criar diretório {$versionDir}.";
                        continue;
                    }

                    $uploadedTargets = [];
                    $baseUploadOk = true;
                    foreach ($resolvedFiles as $fileData) {
                        $remoteToonsPath = $versionDir . '/' . $fileData['file_name'];
                        if (!$sftp->put($remoteToonsPath, $fileData['local_path'], SFTP::SOURCE_LOCAL_FILE)) {
                            $resultadoFinal['logs'][] = "Falha ao enviar {$fileData['file_name']} para {$remoteToonsPath}.";
                            $baseUploadOk = false;
                            break;
                        }

                        $uploadedTargets[] = [
                            'historico_id' => (int) $fileData['historico_id'],
                            'file_name' => $fileData['file_name'],
                            'remote_path' => $remoteToonsPath,
                        ];
                        if ((int) $fileData['historico_id'] === (int) $primaryHistoryId) {
                            $primaryFileName = $fileData['file_name'];
                        }
                    }

                    if ($baseUploadOk) {
                        $allUploaded = true;
                        $resultadoFinal['logs'][] = 'Lote de modelagem P00 enviado para Toons com sucesso.';
                        break;
                    }
                }

                foreach ($tempFiles as $tempFile) {
                    if ($tempFile && is_file($tempFile)) {
                        @unlink($tempFile);
                    }
                }

                if (!$allUploaded) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Erro ao enviar o lote completo da modelagem para o NAS.']);
                    exit;
                }

                improov_p00_mark_version_ready($conn, (int) $p00VersaoAtual['id'], [
                    'status' => 'Entrega pendente',
                    'funcao_imagem_id' => (int) $idfuncao_imagem,
                    'historico_id' => (int) $primaryHistoryId,
                    'arquivo_principal' => $primaryFileName,
                    'arquivos_json' => json_encode($uploadedTargets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'nas_path' => $modelagemNasPath . '/Toons/' . $versionLabel,
                ]);

                $resultadoFinal['sftp_enviado'] = true;
            } else {
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
                                preg_match('/(_[A-Z0-9]{2,3})(?!.*_[A-Z0-9]{2,3})/i', $nome_arquivo, $lastMatchParts);
                                $lastMatch = $lastMatchParts[1] ?? null;
                                $revisao = $lastMatch !== null
                                    ? strtoupper(str_replace('_', '', (string) $lastMatch))
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
