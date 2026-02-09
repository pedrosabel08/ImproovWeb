<?php

// Pequeno endpoint para enfileirar uploads para processamento em background.
// Ele salva o arquivo enviado em `uploads/staging` e grava um arquivo .json com metadados.

header('Content-Type: application/json');

// CORS: permite origens confiáveis (inclui localhost para testes locais)
// Allowed origins list for stricter control
$allowedOrigins = [
    'https://improov.com.br',
    'https://improov.com.br/',
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:3000',
    'http://127.0.0.1:5500'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
// Prefer echoing the exact Origin header when it's from our domain family (improov)
// This allows variations like https://improov (local dev host) while keeping control.
if (!empty($origin)) {
    $host = parse_url($origin, PHP_URL_HOST) ?: '';
    if (in_array($origin, $allowedOrigins, true) || stripos($host, 'improov') !== false) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        // fallback to primary origin
        header("Access-Control-Allow-Origin: https://improov.com.br");
    }
} else {
    header("Access-Control-Allow-Origin: https://improov.com.br");
}
// Indicate that response varies by Origin for caching proxies
header('Vary: Origin');
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verifica arquivo
if (!isset($_FILES['arquivo_final']) || empty($_FILES['arquivo_final']['name'])) {
    echo json_encode(['error' => 'Nenhum arquivo enviado']);
    exit;
}

$stagingDir = __DIR__ . '/uploads/staging';
if (!is_dir($stagingDir)) {
    mkdir($stagingDir, 0777, true);
}
// Garantir permissões amplas para que o serviço (systemd) consiga mover/excluir
@chmod($stagingDir, 0777);

// --- helpers: avoid race with worker claiming meta file ---
// If the worker already claimed `id.json` (renamed to `id.json.processing.*`),
// a later file_put_contents on the old path would recreate `id.json` and leave
// a dangling job that will fail with "Arquivo staged não encontrado".
function _resolve_current_meta_path(string $metaFile): string
{
    if (is_file($metaFile)) return $metaFile;
    $candidates = glob($metaFile . '.processing*') ?: [];
    if (empty($candidates)) return $metaFile;

    // pick the most recently modified processing file
    usort($candidates, function ($a, $b) {
        return (@filemtime($b) ?: 0) <=> (@filemtime($a) ?: 0);
    });
    return $candidates[0];
}

function _write_meta_safely(string $metaFile, array $meta): void
{
    $target = _resolve_current_meta_path($metaFile);
    @file_put_contents($target, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    // Ensure worker can rename/remove
    @chmod($target, 0666);
}

// Helpers necessários para montar nome_final como no uploadFinal.php
if (!function_exists('removerTodosAcentos')) {
    function removerTodosAcentos($str)
    {
        return preg_replace(
            ['/[áàãâä]/ui', '/[éèêë]/ui', '/[íìîï]/ui', '/[óòõôö]/ui', '/[úùûü]/ui', '/[ç]/ui'],
            ['a', 'e', 'i', 'o', 'u', 'c'],
            $str
        );
    }
}

$arquivos = $_FILES['arquivo_final'];
$total = is_array($arquivos['name']) ? count($arquivos['name']) : 1;
$results = [];

for ($i = 0; $i < $total; $i++) {
    $originalName = is_array($arquivos['name']) ? $arquivos['name'][$i] : $arquivos['name'];
    $tmpName = is_array($arquivos['tmp_name']) ? $arquivos['tmp_name'][$i] : $arquivos['tmp_name'];
    $error = is_array($arquivos['error']) ? $arquivos['error'][$i] : $arquivos['error'];

    if ($error !== UPLOAD_ERR_OK) {
        $results[] = ['arquivo' => $originalName, 'status' => 'erro_upload', 'code' => $error];
        continue;
    }

    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    // allow client-provided id so the browser can subscribe to progress before upload completes
    if (isset($_POST['client_id']) && preg_match('/^[A-Za-z0-9_\-\.]+$/', $_POST['client_id'])) {
        $id = $_POST['client_id'];
    } else {
        $id = uniqid('upl_', true);
    }
    $destFile = "$stagingDir/{$id}.{$ext}";

    if (!move_uploaded_file($tmpName, $destFile)) {
        $results[] = ['arquivo' => $originalName, 'status' => 'erro_move'];
        continue;
    }
    // Ajusta permissões do arquivo para evitar problemas de remoção pelo serviço
    @chmod($destFile, 0666);

    // coleta metadados do POST
    $meta = [];
    $meta['original_name'] = $originalName;
    $meta['staged_path'] = $destFile;
    $meta['id'] = $id;
    $meta['uploaded_at'] = date('c');
    $meta['post'] = $_POST;
    // dataIdFuncoes pode estar serializado como JSON ou string
    if (isset($_POST['dataIdFuncoes'])) {
        $raw = $_POST['dataIdFuncoes'];
        $decoded = json_decode($raw, true);
        $meta['dataIdFuncoes'] = is_array($decoded) ? $decoded : [$raw];
    } else {
        $meta['dataIdFuncoes'] = [];
    }

    $metaFile = "$stagingDir/{$id}.json";
    _write_meta_safely($metaFile, $meta);

    // prepara resultado SFTP por arquivo (será retornado ao cliente)
    $sftpResult = [
        'attempted' => false,
        'success' => false,
        'remote_path' => null,
        'error' => null
    ];

    // Tenta enviar o arquivo enfileirado para o VPS via SFTP (se phpseclib disponível).
    try {
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        }
        if (class_exists('\\phpseclib3\\Net\\SFTP')) {
            $sftpHost = 'imp-nas.ddns.net';
            $sftpUser = 'flow';
            $sftpPass = 'flow@2025';
            $sftpPort = 2222;
            $remoteDir = '/uploads/staging';

            $sftp = new \phpseclib3\Net\SFTP($sftpHost, $sftpPort);
            $sftpResult['attempted'] = true;
            if ($sftp->login($sftpUser, $sftpPass)) {
                // tenta criar diretório remoto
                if (!$sftp->is_dir($remoteDir)) {
                    @$sftp->mkdir($remoteDir, -1, true);
                }
                $remotePath = rtrim($remoteDir, '/') . '/' . basename($destFile);
                if ($sftp->put($remotePath, $destFile, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE)) {
                    $meta['remote_staged_path'] = $remotePath;
                    _write_meta_safely($metaFile, $meta);
                    $sftpResult['success'] = true;
                    $sftpResult['remote_path'] = $remotePath;
                } else {
                    $sftpResult['error'] = 'failed_to_put_remote_file';
                }
            } else {
                $sftpResult['error'] = 'sftp_auth_failed';
            }
        }
    } catch (Exception $e) {
        $sftpResult['attempted'] = true;
        $sftpResult['error'] = $e->getMessage();
    }

    // Insert initial log row into arquivo_log with status 'enfileirado' (use existing table schema)
    try {
        require_once __DIR__ . '/conexao.php';
        $colaborador_id = isset($_POST['idcolaborador']) ? (int)$_POST['idcolaborador'] : null;
        $tipo = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) === 'pdf' ? 'PDF' : 'IMG';
        $status = 'enfileirado';
        $tamanho = is_array($arquivos['size']) ? ($arquivos['size'][$i] ?? 0) : $arquivos['size'];

        // If dataIdFuncoes provided, insert one row per funcao_imagem_id; else insert with NULL funcao_imagem_id
        $dataIdFuncoes = [];
        if (isset($_POST['dataIdFuncoes'])) {
            $rawF = $_POST['dataIdFuncoes'];
            $decF = json_decode($rawF, true);
            if (is_array($decF)) $dataIdFuncoes = $decF;
            else if (!empty($rawF)) $dataIdFuncoes = [$rawF];
        }

        $logIds = [];
        if (!empty($dataIdFuncoes)) {
            $stmt = $conn->prepare("INSERT INTO arquivo_log (funcao_imagem_id, caminho, nome_arquivo, tamanho, tipo, colaborador_id, status) VALUES (?,?,?,?,?,?,?)");
            if ($stmt) {
                foreach ($dataIdFuncoes as $fid) {
                    $fidInt = (int)$fid;
                    $stmt->bind_param('ississs', $fidInt, $destFile, $originalName, $tamanho, $tipo, $colaborador_id, $status);
                    $stmt->execute();
                    $logIds[] = $stmt->insert_id;
                }
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO arquivo_log (funcao_imagem_id, caminho, nome_arquivo, tamanho, tipo, colaborador_id, status) VALUES (NULL,?,?,?,?,?,?)");
            if ($stmt) {
                $stmt->bind_param('ssisss', $destFile, $originalName, $tamanho, $tipo, $colaborador_id, $status);
                $stmt->execute();
                $logIds[] = $stmt->insert_id;
                $stmt->close();
            }
        }

        if (!empty($logIds)) {
            $meta['log_ids'] = $logIds;
            _write_meta_safely($metaFile, $meta);
            // Se inserimos logs vinculados a funções, limpar a flag de pendência de upload
            try {
                if (!empty($dataIdFuncoes)) {
                    $upd = $conn->prepare("UPDATE funcao_imagem SET requires_file_upload = 0, file_uploaded_at = NOW() WHERE idfuncao_imagem = ?");
                    if ($upd) {
                        foreach ($dataIdFuncoes as $fid) {
                            $fidInt = (int)$fid;
                            $colIdInt = isset($colaborador_id) ? (int)$colaborador_id : 0;
                            $upd->bind_param('i', $fidInt);
                            @$upd->execute();
                            // após limpar a pendência, se a função estiver aprovada (com ou sem ajustes), marca como Finalizado
                            try {
                                // Apenas finaliza automaticamente quando a função estava 'Aprovado'.
                                // 'Aprovado com ajustes' será tratado visualmente no frontend quando houver arquivo,
                                // sem alterar o status da função no banco.
                                $updFinal = $conn->prepare("UPDATE funcao_imagem SET status = 'Finalizado' WHERE idfuncao_imagem = ? AND status = 'Aprovado'");
                                if ($updFinal) {
                                    $updFinal->bind_param('i', $fidInt);
                                    @$updFinal->execute();
                                    $updFinal->close();
                                }
                            } catch (Exception $e) {
                                // não bloquear o enqueue por falha nesta atualização
                            }
                        }
                            $upd->close();
                    }
                }
            } catch (Exception $e) {
                // não interromper o enqueue por falha na atualização
            }
        }
    } catch (Exception $e) {
        // ignore DB failures at enqueue to avoid blocking; worker will try again
    }

    // Replicar regras de uploadFinal.php: se for PDF, registrar em funcao_imagem_pdf; e
    // se a função for Caderno/Filtro de assets, colocar status 'Em aprovação' nas funções.
    try {
        require_once __DIR__ . '/conexao.php';
        // Coletar campos do POST para montar nome_final
        $nome_funcao = $_POST['nome_funcao'] ?? '';
        $numeroImagem = $_POST['numeroImagem'] ?? '';
        $nomenclatura = $_POST['nomenclatura'] ?? '';
        $primeiraPalavra = $_POST['primeiraPalavra'] ?? '';
        $nome_imagem = $_POST['nome_imagem'] ?? '';
        $nomeStatus = $_POST['status_nome'] ?? '';
        $extLower = strtolower($ext);

        // dataIdFuncoes pode estar enviado como JSON ou string única
        $dataIdFuncoes = [];
        if (isset($_POST['dataIdFuncoes'])) {
            $raw = $_POST['dataIdFuncoes'];
            $dec = json_decode($raw, true);
            if (is_array($dec)) $dataIdFuncoes = $dec;
            else if (!empty($raw)) $dataIdFuncoes = [$raw];
        }

        // 1) Se PDF: inserir em funcao_imagem_pdf (usando o mesmo nome_final calculado pelo servidor)
        if ($extLower === 'pdf' && !empty($dataIdFuncoes)) {
            $tipoCalc = 'PDF';
            $semAcento = removerTodosAcentos($nome_funcao);
            $processo = strtoupper(mb_substr($semAcento, 0, 3, 'UTF-8'));
            // Normalizar componentes para remover acentos no nome do arquivo
            $nomenclatura_clean = removerTodosAcentos($nomenclatura);
            $primeiraPalavra_clean = removerTodosAcentos($primeiraPalavra);
            $nome_imagem_clean = removerTodosAcentos($nome_imagem);
            $nome_base = "{$numeroImagem}.{$nomenclatura_clean}-{$primeiraPalavra_clean}-{$tipoCalc}-{$processo}";
            $revisao = $nomeStatus ?: 'R00';
            // Regras especiais: Pós-Produção e Planta Humanizada usam padrão diferente
            $funcao_normalizada = mb_strtolower($nome_funcao, 'UTF-8');
            if ($funcao_normalizada === 'pós-produção' || $funcao_normalizada === 'pos-producao' || $funcao_normalizada === 'planta humanizada') {
                $nome_final_pdf = "{$nome_imagem_clean}_{$revisao}.{$extLower}";
            } else {
                $nome_final_pdf = "{$nome_base}-{$revisao}.{$extLower}";
            }

            foreach ($dataIdFuncoes as $fid) {
                $fidInt = (int)$fid;
                $stmt = $conn->prepare("INSERT INTO funcao_imagem_pdf (funcao_imagem_id, nome_pdf) VALUES (?, ?) ON DUPLICATE KEY UPDATE nome_pdf = VALUES(nome_pdf)");
                if ($stmt) {
                    $stmt->bind_param('is', $fidInt, $nome_final_pdf);
                    @$stmt->execute();
                    $stmt->close();
                }
            }
        }

        // 2) Se função for Caderno/Filtro de assets: colocar em aprovação
        $func_lower = mb_strtolower($nome_funcao, 'UTF-8');
        if (!empty($dataIdFuncoes) && in_array($func_lower, ['caderno', 'filtro de assets'])) {
            foreach ($dataIdFuncoes as $fid) {
                $fidInt = (int)$fid;
                $stmt = $conn->prepare("UPDATE funcao_imagem SET status = 'Em aprovação' WHERE idfuncao_imagem = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $fidInt);
                    @$stmt->execute();
                    $stmt->close();
                }
            }
        }
    } catch (Exception $e) {
        // não bloquear enqueue por erro de DB aqui
    }

    // Publish initial status to Redis (if Predis available)
    try {
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        }
        if (class_exists('\Predis\Client')) {
            $redis = new \Predis\Client();
            $ch = "upload_progress:{$id}";
            $payload = json_encode(['id' => $id, 'status' => 'queued', 'progress' => 0, 'message' => 'Enfileirado no servidor']);
            $redis->publish($ch, $payload);
            // also set a key so WS can read latest state
            $redis->setex("upload_status:{$id}", 3600, $payload);
        }
    } catch (Exception $e) {
        // ignore Redis failures here - enqueue still works
    }

    $results[] = ['arquivo' => $originalName, 'status' => 'enfileirado', 'id' => $id, 'meta' => $metaFile, 'sftp' => $sftpResult];
}

echo json_encode($results);
