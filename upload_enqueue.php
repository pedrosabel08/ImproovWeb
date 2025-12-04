<?php

// Pequeno endpoint para enfileirar uploads para processamento em background.
// Ele salva o arquivo enviado em `uploads/staging` e grava um arquivo .json com metadados.

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: https://improov.com.br");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

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
    file_put_contents($metaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    // Permitir que o worker consiga renomear/remover o JSON
    @chmod($metaFile, 0666);

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
            file_put_contents($metaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
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

    $results[] = ['arquivo' => $originalName, 'status' => 'enfileirado', 'id' => $id, 'meta' => $metaFile];
}

echo json_encode($results);
