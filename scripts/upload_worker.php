<?php
// CLI worker: processa arquivos em uploads/staging e os envia via SFTP ao NAS.
// Uso: php scripts/upload_worker.php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../conexao.php';

use phpseclib3\Net\SFTP;
use phpseclib3\Exception\UnableToConnectException;

$baseDir = __DIR__ . '/../uploads/staging';
$processedDir = __DIR__ . '/../uploads/sent';
$failedDir = __DIR__ . '/../uploads/failed';

if (!is_dir($processedDir)) mkdir($processedDir, 0777, true);
if (!is_dir($failedDir)) mkdir($failedDir, 0777, true);

// Config SFTP (pode ajustar conforme necessário)
$ftp_user = "flow";
$ftp_pass = "flow@2025";
$ftp_host = "imp-nas.ddns.net";
$ftp_port = 2222;

function enviarArquivoSFTP($host, $usuario, $senha, $arquivoLocal, $arquivoRemoto, $porta = 2222)
{
    if (!file_exists($arquivoLocal)) {
        return [false, "Arquivo local não encontrado: $arquivoLocal"];
    }

    try {
        $sftp = new SFTP($host, $porta);
        if (!$sftp->login($usuario, $senha)) {
            return [false, "Falha na autenticação SFTP."];
        }

        $diretorio = dirname($arquivoRemoto);
        if (!$sftp->is_dir($diretorio)) {
            return [false, "Diretório remoto não existe: $diretorio"];
        }

        if ($sftp->put($arquivoRemoto, $arquivoLocal, SFTP::SOURCE_LOCAL_FILE)) {
            return [true, 'OK'];
        }

        return [false, 'Erro ao enviar o arquivo via SFTP.'];
    } catch (UnableToConnectException $e) {
        return [false, 'Erro ao conectar SFTP: ' . $e->getMessage()];
    } catch (Exception $e) {
        return [false, 'Exceção: ' . $e->getMessage()];
    }
}

// Função auxiliar: mapear função -> pasta (mesma lógica simplificada de uploadFinal.php)
function mapFuncaoParaPasta($nome_funcao)
{
    $mapa = [
        'Caderno' => '02.Projetos',
        'Filtro de assets' => '02.Projetos',
        'modelagem' => '03.Models',
        'composição' => '03.Models',
        'finalização' => '03.Models',
        'pré-finalização' => '03.Models',
        'alteração' => '03.Models',
        'Pós-Produção' => '04.Finalizacao',
        'Planta Humanizada' => '04.Finalizacao',
    ];

    foreach ($mapa as $k => $v) {
        if (mb_strtolower($k, 'UTF-8') === mb_strtolower($nome_funcao, 'UTF-8')) return $v;
    }
    return '';
}

// --- New: daemon mode, atomic claim, retries, signal handling ---

$opts = getopt('', ['daemon', 'sleep:']);
$daemon = isset($opts['daemon']);
$sleepWhenIdle = isset($opts['sleep']) ? (int)$opts['sleep'] : 3;

$shutdown = false;
// pcntl extension may be missing on some PHP CLI builds (shared hosting).
// Guard calls to pcntl functions to avoid fatal errors.
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
}
if (function_exists('pcntl_signal') && defined('SIGTERM') && defined('SIGINT')) {
    pcntl_signal(SIGTERM, function() use (&$shutdown) { $shutdown = true; });
    pcntl_signal(SIGINT, function() use (&$shutdown) { $shutdown = true; });
} else {
    // pcntl not available: worker will not respond to SIGTERM gracefully.
    echo "Warning: pcntl extension not available; graceful shutdown disabled.\n";
}

// helper to claim a job by renaming the meta file atomically
function claimJob(string $metaFile)
{
    $processing = $metaFile . '.processing';
    if (@rename($metaFile, $processing)) return $processing;
    return false;
}

// helper to write meta back
function writeMeta(string $path, array $meta)
{
    @file_put_contents($path, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// Process loop (single run or daemon loop)
do {
    $metaFiles = glob($baseDir . '/*.json');
    if (!$metaFiles) {
        if ($daemon && !$shutdown) {
            sleep($sleepWhenIdle);
            continue;
        }
        echo "Nenhum item na fila.\n";
        break;
    }

    foreach ($metaFiles as $metaFile) {
        if ($shutdown) break 2;

        echo "Tentando claim: $metaFile\n";
        $processingMeta = claimJob($metaFile);
        if (!$processingMeta) {
            echo "Falha ao claim (outro worker talvez esteja processando): $metaFile\n";
            continue;
        }

        echo "Processando $processingMeta\n";
        $meta = json_decode(file_get_contents($processingMeta), true);
        if (!$meta) {
            echo "Erro ao ler metadados: $processingMeta\n";
            rename($processingMeta, $failedDir . '/' . basename($processingMeta));
            continue;
        }

        $staged = $meta['staged_path'] ?? null;
        if (!$staged || !file_exists($staged)) {
            echo "Arquivo staged não encontrado: $staged\n";
            rename($processingMeta, $failedDir . '/' . basename($processingMeta));
            continue;
        }

        // ensure attempts count
        $meta['attempts'] = isset($meta['attempts']) ? (int)$meta['attempts'] : 0;

        // extract post fields
        $nomenclatura = $meta['post']['nomenclatura'] ?? '';
        $nome_funcao = $meta['post']['nome_funcao'] ?? '';
        $numeroImagem = $meta['post']['numeroImagem'] ?? '';
        $primeiraPalavra = $meta['post']['primeiraPalavra'] ?? '';
        $nome_imagem = $meta['post']['nome_imagem'] ?? '';
        $nomeStatus = $meta['post']['status_nome'] ?? '';

        $pasta_funcao = mapFuncaoParaPasta($nome_funcao);
        if (!$pasta_funcao) {
            echo "Função sem pasta mapeada: $nome_funcao\n";
            rename($staged, $failedDir . '/' . basename($staged));
            rename($processingMeta, $failedDir . '/' . basename($processingMeta));
            continue;
        }

        $clientes_base = ['/mnt/clientes/2024', '/mnt/clientes/2025'];
        $upload_ok = '';
        foreach ($clientes_base as $base) {
            $destino_base = $base . '/' . $nomenclatura . '/' . $pasta_funcao;
            $sftp = new SFTP($ftp_host, $ftp_port);
            if (@$sftp->login($ftp_user, $ftp_pass) && @$sftp->is_dir($destino_base)) {
                $upload_ok = $destino_base;
                break;
            }
        }

        if (!$upload_ok) {
            echo "Destino não encontrado para nomenclatura: $nomenclatura\n";
            file_put_contents($failedDir . '/' . basename($processingMeta) . '.err', date('c') . " - destino não encontrado\n", FILE_APPEND);
            rename($staged, $failedDir . '/' . basename($staged));
            rename($processingMeta, $failedDir . '/' . basename($processingMeta));
            continue;
        }

        $ext = pathinfo($staged, PATHINFO_EXTENSION);
        $tipo = in_array(strtolower($ext), ['pdf']) ? 'PDF' : 'IMG';
        $semAcento = preg_replace(['/[áàãâä]/ui','/[éèêë]/ui','/[íìîï]/ui','/[óòõôö]/ui','/[úùûü]/ui','/[ç]/ui'], ['a','e','i','o','u','c'], $nome_funcao);
        $processo = strtoupper(mb_substr($semAcento, 0, 3, 'UTF-8'));
        $nome_base = "{$numeroImagem}.{$nomenclatura}-{$primeiraPalavra}-{$tipo}-{$processo}";
        $revisao = $nomeStatus ?: 'R00';
        $remote_path = "$upload_ok/{$nome_base}-{$revisao}.{$ext}";

        // retry loop with exponential backoff
        $maxAttempts = 5;
        $ok = false;
        $lastMsg = '';
        while ($meta['attempts'] < $maxAttempts && !$ok && !$shutdown) {
            $meta['attempts']++;
            writeMeta($processingMeta, $meta);

            list($ok, $msg) = enviarArquivoSFTP($ftp_host, $ftp_user, $ftp_pass, $staged, $remote_path, $ftp_port);
            $lastMsg = $msg;
            if ($ok) break;

            // classify as retryable by message or assume retryable for connection issues
            $retryable = true;
            if (stripos($msg, 'Diretório remoto não existe') !== false || stripos($msg, 'Falha na autenticação') !== false) {
                $retryable = false;
            }

            if (!$retryable) break;

            // backoff
            $backoff = min(60, pow(2, $meta['attempts']) * 1);
            echo "Tentativa {$meta['attempts']} falhou, aguardando {$backoff}s antes de nova tentativa...\n";
            sleep($backoff);
        }

        if ($ok) {
            echo "Enviado com sucesso: $remote_path\n";
            // mover arquivos para sent
            rename($staged, $processedDir . '/' . basename($staged));
            rename($processingMeta, $processedDir . '/' . basename($processingMeta));
        } else {
            echo "Falha ao enviar (todas tentativas ou erro irreversível): $lastMsg\n";
            file_put_contents($failedDir . '/' . basename($processingMeta) . '.err', date('c') . " - $lastMsg\n", FILE_APPEND);
            rename($staged, $failedDir . '/' . basename($staged));
            rename($processingMeta, $failedDir . '/' . basename($processingMeta));
        }
    }

    if (!$daemon) break;
    if (!$shutdown) sleep($sleepWhenIdle);
} while (!$shutdown);

echo "Worker finalizado.\n";
