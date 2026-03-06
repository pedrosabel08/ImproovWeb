<?php

/**
 * sftp_upload.php — Endpoint chamado pelo frontend após o usuário resolver um conflito
 * de arquivo SFTP (substituir ou adicionar com novo sufixo).
 *
 * Parâmetros POST (JSON):
 *   idfuncao_imagem  int     – ID da função de imagem aprovada
 *   imagem_id        int     – ID da imagem (imagens_cliente_obra)
 *   sftp_action      string  – 'replace' | 'add'
 *   sftp_suffix      string  – sufixo a acrescentar ao nome quando sftp_action='add'
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/secure_env.php';

if (!isset($_SESSION['idusuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

include '../conexao.php';
require_once __DIR__ . '/vendor/autoload.php';

use phpseclib3\Net\SFTP;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$data            = json_decode(file_get_contents('php://input'), true);
$idfuncao_imagem  = $data['idfuncao_imagem']  ?? null;
$imagem_id        = $data['imagem_id']        ?? null;
$sftp_action      = $data['sftp_action']      ?? null; // 'replace' | 'add'
$sftp_suffix      = $data['sftp_suffix']      ?? null;
$sftp_remote_path = $data['sftp_remote_path'] ?? null; // caminho exato do conflito

if (!$idfuncao_imagem || !$sftp_action) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros insuficientes.']);
    exit;
}

$result = ['success' => false, 'logs' => []];

// ── Busca o nome base do arquivo no histórico ─────────────────────────────────
$stmtArquivo = $conn->prepare("SELECT nome_arquivo FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? ORDER BY id DESC LIMIT 1");
$stmtArquivo->bind_param("i", $idfuncao_imagem);
$stmtArquivo->execute();
$stmtArquivo->bind_result($nome_arquivo_base);
$stmtArquivo->fetch();
$stmtArquivo->close();

if (!$nome_arquivo_base) {
    echo json_encode(['success' => false, 'message' => 'Arquivo não encontrado no histórico.']);
    exit;
}

// ── Busca a nomenclatura da obra ──────────────────────────────────────────────
$stmtNomen = $conn->prepare("SELECT o.nomenclatura FROM funcao_imagem fi JOIN imagens_cliente_obra ic ON fi.imagem_id = ic.idimagens_cliente_obra JOIN obra o ON ic.obra_id = o.idobra WHERE fi.idfuncao_imagem = ?");
$stmtNomen->bind_param("i", $idfuncao_imagem);
$stmtNomen->execute();
$stmtNomen->bind_result($nomenclatura);
$stmtNomen->fetch();
$stmtNomen->close();

if (!$nomenclatura) {
    echo json_encode(['success' => false, 'message' => 'Nomenclatura da obra não encontrada.']);
    exit;
}

// ── Localiza o arquivo (local primeiro, depois VPS) ─────────────────────────
$uploadDir        = dirname(__DIR__) . "/uploads/";
$arquivosPossiveis = glob($uploadDir . $nome_arquivo_base . '.*');
$arquivoTempVps   = null;

if (empty($arquivosPossiveis)) {
    // Fallback: tenta baixar do VPS via SFTP
    try {
        $vpsCfg  = improov_sftp_config('IMPROOV_VPS_SFTP');
        $vpsBase = rtrim((string)improov_env('IMPROOV_VPS_SFTP_REMOTE_PATH'), '/');
        $vpsDir  = $vpsBase . '/uploads/';
        $vsftp   = new SFTP($vpsCfg['host'], (int)$vpsCfg['port']);
        if ($vsftp->login($vpsCfg['user'], $vpsCfg['pass'])) {
            $listaRemota = $vsftp->nlist($vpsDir);
            if (is_array($listaRemota)) {
                foreach ($listaRemota as $remoteFile) {
                    if (pathinfo($remoteFile, PATHINFO_FILENAME) === $nome_arquivo_base) {
                        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $remoteFile;
                        if ($vsftp->get($vpsDir . $remoteFile, $tempPath)) {
                            $arquivosPossiveis = [$tempPath];
                            $arquivoTempVps    = $tempPath;
                            $result['logs'][] = "Arquivo baixado do VPS: {$remoteFile}";
                        } else {
                            $result['logs'][] = "Falha ao baixar '{$remoteFile}' do VPS.";
                        }
                        break;
                    }
                }
            }
        } else {
            $result['logs'][] = "Falha ao conectar no VPS SFTP.";
        }
    } catch (RuntimeException $e) {
        $result['logs'][] = "VPS SFTP config ausente: " . $e->getMessage();
    }
}

if (empty($arquivosPossiveis)) {
    echo json_encode(['success' => false, 'message' => "Arquivo '$nome_arquivo_base' não encontrado localmente nem no VPS.", 'logs' => $result['logs']]);
    exit;
}

$caminho_local       = $arquivosPossiveis[0];
$nome_arquivo_original = basename($caminho_local);

// Remove índices numéricos finais antes da extensão: ex. _EF_5_1.jpg → _EF.jpg
$nome_arquivo = preg_replace('/(_\d+)+(\.([^.]+))$/', '$2', $nome_arquivo_original);
if ($nome_arquivo === $nome_arquivo_original) {
    $nome_arquivo = $nome_arquivo_original;
}

// ── Aplica sufixo quando sftp_action='add' ────────────────────────────────────
$nome_arquivo_sftp = $nome_arquivo;
if ($sftp_action === 'add' && !empty($sftp_suffix)) {
    $ext_sftp  = pathinfo($nome_arquivo_sftp, PATHINFO_EXTENSION);
    $base_sftp = pathinfo($nome_arquivo_sftp, PATHINFO_FILENAME);
    $nome_arquivo_sftp = $base_sftp . '_' . $sftp_suffix . '.' . $ext_sftp;
}

// ── SFTP upload ───────────────────────────────────────────────────────────────
try {
    $sftpCfg = improov_sftp_config();
} catch (RuntimeException $e) {
    $result['logs'][] = 'config_sftp_ausente';
    $sftpCfg = null;
}

if ($sftpCfg === null) {
    echo json_encode(['success' => false, 'message' => 'Configuração SFTP não disponível.', 'logs' => $result['logs']]);
    exit;
}

$ftp_host = $sftpCfg['host'];
$ftp_user = $sftpCfg['user'];
$ftp_pass = $sftpCfg['pass'];
$ftp_port = $sftpCfg['port'];
$bases    = ['/mnt/clientes/2024', '/mnt/clientes/2025', '/mnt/clientes/2026'];
$enviado  = false;

// Extrai a revisão do nome do arquivo (ex.: _EF, _P00, _R01…)
preg_match_all('/_[A-Z0-9]{2,3}/i', $nome_arquivo, $matchesRev);
$revisao = isset($matchesRev[0]) && count($matchesRev[0]) > 0
    ? strtoupper(str_replace('_', '', end($matchesRev[0])))
    : 'P00';

if (!empty($sftp_remote_path)) {
    // ── Caminho exato conhecido (vem do conflito detectado em revisarTarefa.php) ──
    $resolved_path = $sftp_remote_path;
    if ($sftp_action === 'add' && !empty($sftp_suffix)) {
        $ext_r  = pathinfo($resolved_path, PATHINFO_EXTENSION);
        $base_r = pathinfo($resolved_path, PATHINFO_FILENAME);
        $resolved_path = dirname($resolved_path) . '/' . $base_r . '_' . $sftp_suffix . '.' . $ext_r;
    }
    try {
        $sftp = new SFTP($ftp_host, $ftp_port);
        if (!$sftp->login($ftp_user, $ftp_pass)) {
            $result['logs'][] = "Falha ao autenticar no SFTP.";
        } elseif ($sftp->put($resolved_path, $caminho_local, SFTP::SOURCE_LOCAL_FILE)) {
            $result['logs'][]       = "Arquivo enviado com sucesso para $resolved_path.";
            $result['success']      = true;
            $result['sftp_enviado'] = true;
            $enviado                = true;
        } else {
            $result['logs'][] = "Falha ao enviar arquivo para $resolved_path.";
        }
    } catch (Throwable $e) {
        $result['logs'][] = "SFTP error: " . $e->getMessage();
    }
} else {
    foreach ($bases as $base) {
        try {
            $sftp = new SFTP($ftp_host, $ftp_port);
            if (!$sftp->login($ftp_user, $ftp_pass)) {
                $result['logs'][] = "Falha ao conectar em $ftp_host:$ftp_port para base $base.";
                continue;
            }
            $result['logs'][] = "Conectado a $ftp_host para base $base.";
        } catch (Throwable $e) {
            $result['logs'][] = "SFTP connection error for base $base: " . $e->getMessage();
            continue;
        }

        $finalizacaoDir = "$base/$nomenclatura/04.Finalizacao";
        if (!$sftp->is_dir($finalizacaoDir)) {
            $result['logs'][] = "Diretório $finalizacaoDir não existe.";
            continue;
        }

        $revisaoDir = "$finalizacaoDir/$revisao";
        if (!$sftp->is_dir($revisaoDir)) {
            if (!$sftp->mkdir($revisaoDir, -1, true)) {
                $result['logs'][] = "Falha ao criar diretório $revisaoDir.";
                continue;
            }
            $result['logs'][] = "Diretório $revisaoDir criado.";
        }

        $remote_path = "$revisaoDir/$nome_arquivo_sftp";

        try {
            if ($sftp->put($remote_path, $caminho_local, SFTP::SOURCE_LOCAL_FILE)) {
                $result['logs'][]       = "Arquivo enviado com sucesso para $remote_path.";
                $result['success']      = true;
                $result['sftp_enviado'] = true;
                $enviado                = true;
                break;
            } else {
                $result['logs'][] = "Falha ao enviar para $remote_path.";
            }
        } catch (Throwable $e) {
            $result['logs'][] = "SFTP put error for $remote_path: " . $e->getMessage();
        }
    }
}

if (!$enviado && !$result['success']) {
    $result['message'] = 'Não foi possível enviar o arquivo via SFTP.';
}

// Remove arquivo temporário baixado do VPS (se existir)
if ($arquivoTempVps && is_file($arquivoTempVps)) {
    @unlink($arquivoTempVps);
}

$conn->close();
echo json_encode($result);
