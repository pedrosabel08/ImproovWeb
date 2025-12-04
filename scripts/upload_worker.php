<?php
// CLI worker: processa arquivos em uploads/staging e os envia via SFTP ao NAS.
// Uso: php scripts/upload_worker.php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../conexao.php';

use phpseclib3\Net\SFTP;
use phpseclib3\Exception\UnableToConnectException;
use Predis\Client as PredisClient;

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

function enviarArquivoSFTP($host, $usuario, $senha, $arquivoLocal, $arquivoRemoto, $porta = 2222, callable $onProgress = null)
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

        // If phpseclib provides fopen/fwrite on the SFTP object we can stream and report progress.
        // Some distributions/versions may not expose these methods; provide a safe fallback to put().
        if (method_exists($sftp, 'fopen') && method_exists($sftp, 'fwrite') && method_exists($sftp, 'fclose')) {
            $localHandle = @fopen($arquivoLocal, 'rb');
            if (!$localHandle) return [false, 'Erro ao abrir arquivo local para leitura.'];

            $remoteHandle = $sftp->fopen($arquivoRemoto, 'wb');
            if ($remoteHandle === false) {
                fclose($localHandle);
                return [false, 'Erro ao abrir arquivo remoto para escrita.'];
            }

            $totalSize = filesize($arquivoLocal);
            $sent = 0;
            $chunkSize = 8192;
            while (!feof($localHandle)) {
                $buffer = fread($localHandle, $chunkSize);
                if ($buffer === false) break;
                $written = $sftp->fwrite($remoteHandle, $buffer);
                if ($written === false) {
                    fclose($localHandle);
                    $sftp->fclose($remoteHandle);
                    return [false, 'Erro ao escrever no remoto durante upload.'];
                }
                $sent += strlen($buffer);
                if ($onProgress && $totalSize > 0) {
                    try { $onProgress($sent, $totalSize); } catch (Exception $e) {}
                }
            }

            fclose($localHandle);
            $sftp->fclose($remoteHandle);

            // basic verification: if we reached total size consider OK
            if ($totalSize > 0 && $sent >= $totalSize) {
                return [true, 'OK'];
            }

            return [false, 'Upload incompleto via SFTP.'];
        } else {
            // Fallback: use put() with local file source. This does not provide per-chunk callbacks
            // on all phpseclib builds but will reliably transfer the file. We still call the
            // onProgress callback before/after to indicate start and end.
            if ($onProgress) {
                try { $onProgress(0, filesize($arquivoLocal)); } catch (Exception $e) {}
            }
            if ($sftp->put($arquivoRemoto, $arquivoLocal, SFTP::SOURCE_LOCAL_FILE)) {
                if ($onProgress) {
                    try { $onProgress(filesize($arquivoLocal), filesize($arquivoLocal)); } catch (Exception $e) {}
                }
                return [true, 'OK'];
            }
            return [false, 'Erro ao enviar o arquivo via SFTP (put fallback).'];
        }
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

function publishProgress(?PredisClient $redis, string $id, int $progress, string $status = 'running', string $message = '')
{
    // log attempt for observability (will go to journal when run under systemd)
    error_log("[upload_worker] publishProgress called id={$id} progress={$progress} status={$status} message={$message}");
    if (!$redis || !$id) {
        error_log("[upload_worker] publishProgress: Predis client missing or invalid id");
        return;
    }
    $payload = json_encode(['id' => $id, 'status' => $status, 'progress' => $progress, 'message' => $message]);
    try {
        $redis->publish("upload_progress:{$id}", $payload);
        $redis->setex("upload_status:{$id}", 3600, $payload);
        error_log("[upload_worker] publishProgress: published id={$id} progress={$progress}");
    } catch (Exception $e) {
        error_log("[upload_worker] publishProgress: Redis publish failed: " . $e->getMessage());
    }
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

        // try to connect to Redis for progress publishing
        $redis = null;
        try {
            if (class_exists('\Predis\Client')) {
                $redis = new PredisClient();
            }
        } catch (Exception $e) {
            $redis = null;
        }

        $staged = $meta['staged_path'] ?? null;
        if (!$staged || !file_exists($staged)) {
            echo "Arquivo staged não encontrado: $staged\n";
            rename($processingMeta, $failedDir . '/' . basename($processingMeta));
            continue;
        }

        // ensure attempts count
        $meta['attempts'] = isset($meta['attempts']) ? (int)$meta['attempts'] : 0;

        $jobId = $meta['id'] ?? pathinfo($processingMeta, PATHINFO_FILENAME);
        publishProgress($redis, $jobId, 0, 'claimed', 'Job claimed by worker');

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
            publishProgress($redis, $jobId, 0, 'failed', 'Destino remoto não encontrado');
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

            publishProgress($redis, $jobId, 10, 'connecting', 'Conectando ao SFTP');
            // attempt upload with progress callback
            list($ok, $msg) = enviarArquivoSFTP($ftp_host, $ftp_user, $ftp_pass, $staged, $remote_path, $ftp_port, function($sent, $total) use ($redis, $jobId) {
                $pct = (int) round(($sent / max(1, $total)) * 100);
                // don't spam too frequently: publish at increments of 1% or when reaching 100%
                static $lastPct = null;
                if ($lastPct === null || $pct >= $lastPct + 1 || $pct === 100) {
                    publishProgress($redis, $jobId, $pct, 'uploading', "Transferindo ({$sent}/{$total})");
                    $lastPct = $pct;
                }
            });

            if ($ok) {
                publishProgress($redis, $jobId, 100, 'done', 'Enviado com sucesso');
            } else {
                publishProgress($redis, $jobId, min(95, 10 + $meta['attempts'] * 15), 'retry', $msg);
            }
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
            publishProgress($redis, $jobId, 100, 'done', 'Finalizado e movido para sent');
        } else {
            echo "Falha ao enviar (todas tentativas ou erro irreversível): $lastMsg\n";
            file_put_contents($failedDir . '/' . basename($processingMeta) . '.err', date('c') . " - $lastMsg\n", FILE_APPEND);
            rename($staged, $failedDir . '/' . basename($staged));
            rename($processingMeta, $failedDir . '/' . basename($processingMeta));
            publishProgress($redis, $jobId, 0, 'failed', $lastMsg);
        }
    }

    if (!$daemon) break;
    if (!$shutdown) sleep($sleepWhenIdle);
} while (!$shutdown);

echo "Worker finalizado.\n";
