<?php
require_once __DIR__ . '/../vendor/autoload.php';

use phpseclib3\Net\SFTP;

include '../conexao.php';
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: https://improov.com.br");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// --- Small dotenv loader (no external dependency) ---
function load_dotenv($path)
{
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        // set into environment
        putenv("$k=$v");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}

// --- Slack helpers ---
function send_slack_webhook($webhookUrl, $text, &$log)
{
    if (!$webhookUrl) { $log[] = "Slack webhook not configured"; return false; }
    $payload = json_encode(['text' => $text]);
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false || $code >= 400) {
        $log[] = "Slack webhook failed (code=$code): $err / resp=" . substr($res ?: '',0,200);
        return false;
    }
    $log[] = "Slack webhook sent (code=$code)";
    return true;
}

function send_slack_token_message($token, $channel, $text, &$log)
{
    if (!$token || !$channel) { $log[] = "Slack token or channel missing"; return false; }
    // If $channel is not an ID (starts with U/C/D), try to resolve it using users.list
    if (!preg_match('/^[UCD][A-Z0-9]+$/', $channel)) {
        $resolved = resolve_slack_user_id($token, $channel, $log);
        if ($resolved) {
            $log[] = "Resolved Slack identifier '$channel' => user id $resolved";
            $channel = $resolved;
        } else {
            $log[] = "Could not resolve Slack identifier: $channel";
            // continue and let chat.postMessage return channel_not_found if still invalid
        }
    }

    $url = 'https://slack.com/api/chat.postMessage';
    $body = json_encode(['channel' => $channel, 'text' => $text]);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        $log[] = "Slack API (token) request failed: $err";
        return false;
    }
    $json = json_decode($res, true);
    if (!$json || empty($json['ok'])) {
        $log[] = "Slack API error: " . ($json['error'] ?? substr($res,0,200));
        return false;
    }
    $log[] = "Slack message sent via token to $channel";
    return true;
}

/**
 * Resolve a human-readable Slack identifier (username, display name or email) to a Slack user ID using users.list.
 * Returns the user ID (e.g. U1234...) or null if not found.
 */
function resolve_slack_user_id($token, $identifier, &$log)
{
    if (!$token || !$identifier) return null;
    $ch = curl_init('https://slack.com/api/users.list');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        $log[] = "Slack users.list request failed: $err";
        return null;
    }
    $json = json_decode($res, true);
    if (!$json || empty($json['ok']) || empty($json['members'])) {
        $log[] = "Slack users.list error: " . ($json['error'] ?? 'no members');
        return null;
    }

    $needle = mb_strtolower(trim($identifier), 'UTF-8');
    foreach ($json['members'] as $m) {
        // skip bots and deleted
        if (!empty($m['deleted']) || !empty($m['is_bot'])) continue;
        $candidates = [];
        if (!empty($m['name'])) $candidates[] = $m['name'];
        if (!empty($m['profile']['display_name'])) $candidates[] = $m['profile']['display_name'];
        if (!empty($m['profile']['real_name'])) $candidates[] = $m['profile']['real_name'];
        if (!empty($m['profile']['email'])) $candidates[] = $m['profile']['email'];
        foreach ($candidates as $cand) {
            if (mb_strtolower($cand, 'UTF-8') === $needle) {
                return $m['id'];
            }
        }
    }
    return null;
}

// Load .env (if present) from project root
load_dotenv(__DIR__ . '/../.env');

// Slack config from env
$SLACK_WEBHOOK_URL = getenv('SLACK_WEBHOOK_URL') ?: null;
$FLOW_TOKEN = getenv('FLOW_TOKEN') ?: null;

$host = "imp-nas.ddns.net";
$port = 2222;
$username = "flow";
$password = "flow@2025";

// ---------- Dados FTP ----------
$ftp_host = "ftp.improov.com.br";
$ftp_port = 21; // porta padrão FTP
$ftp_user = "improov";
$ftp_pass = "Impr00v";
$ftp_base = "/www/sistema/uploads/angulo_definido";

// Variáveis do log
$log = [];
$success = [];
$errors = [];

// Dados do formulário
$obra_id      = intval($_POST['obra_id']);
$tipo_arquivo = $_POST['tipo_arquivo'] ?? "outros";
$descricao    = $_POST['descricao'] ?? "";
$flag_master  = !empty($_POST['flag_master']) ? 1 : 0;
$substituicao = !empty($_POST['flag_substituicao']);
$tiposImagem  = $_POST['tipo_imagem'] ?? [];
$categoria  = intval($_POST['tipo_categoria'] ?? 0);
$refsSkpModo = $_POST['refsSkpModo'] ?? 'geral';
$descricao    = $_POST['descricao'] ?? "";
$sufixo       = $_POST['sufixo'] ?? "";

$log[] = "Recebido: obra_id=$obra_id, tipo_arquivo=$tipo_arquivo, substituicao=" . ($substituicao ? 'SIM' : 'NAO');
$log[] = "Tipos imagem: " . json_encode($tiposImagem);
$log[] = "Sufixo: " . ($sufixo ?: '(vazio)');

// Arquivos principais
$arquivosTmp  = $_FILES['arquivos']['tmp_name'] ?? [];
$arquivosName = $_FILES['arquivos']['name'] ?? [];

// Arquivos por imagem (refs/skp)
$arquivosPorImagem = $_FILES['arquivos_por_imagem'] ?? [];

$sftp = new SFTP($host, $port);
if (!$sftp->login($username, $password)) {
    echo json_encode(['errors' => ["Erro ao conectar no servidor SFTP."], 'log' => $log]);
    exit;
}
$log[] = "Conectado no servidor SFTP.";

// Helper: tenta enviar via SFTP e registra erros detalhados; faz fallback para enviar o conteúdo do arquivo
function sftpPutWithFallback($sftp, $remotePath, $localFile, &$log, &$errors) {
    // tentativa 1: enviar como arquivo local
    try {
        if ($sftp->put($remotePath, $localFile, SFTP::SOURCE_LOCAL_FILE)) {
            // aplicar permissões 0777 no arquivo enviado, se possível
            if (method_exists($sftp, 'chmod')) {
                try { $sftp->chmod(0777, $remotePath); $log[] = "Permissões 0777 aplicadas em $remotePath"; } catch (Exception $e) { $log[] = "Falha chmod após put(local): " . $e->getMessage(); }
            }
            return true;
        }
    } catch (Exception $e) {
        $log[] = "Exception during sftp->put(local): " . $e->getMessage();
    }

    // registrar info diagnóstica
    if (method_exists($sftp, 'getSFTPErrors')) {
        $errs = $sftp->getSFTPErrors();
        $log[] = "SFTP errors: " . json_encode($errs);
    } elseif (method_exists($sftp, 'getLastSFTPError')) {
        $log[] = "SFTP last error: " . $sftp->getLastSFTPError();
    } elseif (method_exists($sftp, 'getErrors')) {
        $log[] = "SFTP errors (generic): " . json_encode($sftp->getErrors());
    } else {
        $log[] = "SFTP put failed but no error retrieval method available on phpseclib instance.";
    }

    // tentativa 2: enviar o conteúdo do arquivo (fallback)
    if (file_exists($localFile) && is_readable($localFile)) {
        $data = @file_get_contents($localFile);
        if ($data !== false) {
            try {
                if ($sftp->put($remotePath, $data)) {
                    $log[] = "Fallback: enviado via conteúdo para $remotePath";
                    // aplicar permissões 0777 no arquivo enviado, se possível
                    if (method_exists($sftp, 'chmod')) {
                        try { $sftp->chmod(0777, $remotePath); $log[] = "Permissões 0777 aplicadas em $remotePath"; } catch (Exception $e) { $log[] = "Falha chmod após put(content): " . $e->getMessage(); }
                    }
                    return true;
                } else {
                    $log[] = "Fallback put retornou false para $remotePath";
                }
            } catch (Exception $e) {
                $log[] = "Exception during sftp->put(content): " . $e->getMessage();
            }
        } else {
            $log[] = "Falha ao ler o arquivo local para fallback: $localFile";
        }
    } else {
        $log[] = "Arquivo local não existe ou não legível para fallback: $localFile";
    }

    $errors[] = "Falha ao enviar: $remotePath";
    return false;
}

// Funções auxiliares
function buscarTipoImagemId($conn, $nomeTipo, &$log)
{
    $nomeTipo = $conn->real_escape_string($nomeTipo);
    $res = $conn->query("SELECT id_tipo_imagem FROM tipo_imagem WHERE nome='$nomeTipo'");
    $log[] = "Query buscarTipoImagemId: $nomeTipo (" . ($res && $res->num_rows > 0 ? "ENCONTRADO" : "NÃO ENCONTRADO") . ")";
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return $row['id_tipo_imagem'];
    }
    return null;
}

function buscarNomeCategoria($categoriaId)
{
    $categorias = [
        1 => 'Arquitetonico',
        2 => 'Referencias',
        3 => 'Paisagismo',
        4 => 'Luminotecnico',
        5 => 'Estrutural',
        6 => 'Alteracoes',
        7 => 'Angulo definido'
    ];
    return $categorias[$categoriaId] ?? 'Outros';
}

// Normaliza um nome para uso em pasta: remove acentos, caracteres perigosos e substitui espaços por '_'
function sanitizeDirName($str)
{
    $map = [
        '/[áàãâä]/ui' => 'a',
        '/[éèêë]/ui' => 'e',
        '/[íìîï]/ui' => 'i',
        '/[óòõôö]/ui' => 'o',
        '/[úùûü]/ui' => 'u',
        '/[ç]/ui' => 'c'
    ];
    $str = preg_replace(array_keys($map), array_values($map), $str);
    // remove caracteres não alfanuméricos exceto espaço e underscore
    $str = preg_replace('/[^A-Za-z0-9 _-]/', '', $str);
    $str = preg_replace('/\s+/', '_', trim($str));
    return $str;
}

// Tenta resolver a pasta de categoria existente no SFTP; se não encontrar, cria uma pasta sanitizada
function resolveCategoriaDir($sftp, $pastaBase, $categoriaNome, &$log)
{
    $candidates = [];
    $candidates[] = $categoriaNome;
    $candidates[] = str_replace(' ', '', $categoriaNome);
    $candidates[] = str_replace(' ', '_', $categoriaNome);
    $candidates[] = sanitizeDirName($categoriaNome);

    foreach ($candidates as $cand) {
        $path = rtrim($pastaBase, '/') . '/' . $cand;
        if ($sftp->is_dir($path)) {
            $log[] = "Categoria encontrada: $path (usando candidato '$cand')";
            return $cand;
        }
    }

    // Não encontrou — cria com versão sanitizada
    $safe = sanitizeDirName($categoriaNome);
    $pathSafe = rtrim($pastaBase, '/') . '/' . $safe;
    if (!$sftp->is_dir($pathSafe)) {
        if ($sftp->mkdir($pathSafe, 0777, true)) {
            $log[] = "Criada pasta de categoria sanitizada: $pathSafe";
            // tentar ajustar permissões da pasta para 0777
            if (method_exists($sftp, 'chmod')) {
                try {
                    $sftp->chmod(0777, $pathSafe);
                    $log[] = "Permissões 0777 aplicadas em: $pathSafe";
                } catch (Exception $e) {
                    $log[] = "Falha ao aplicar chmod SFTP em $pathSafe: " . $e->getMessage();
                }
            }
        } else {
            $log[] = "Falha ao criar pasta de categoria: $pathSafe";
        }
    }
    return $safe;
}

// Aplica chmod 0777 via SFTP quando suportado (retorna true se aplicado)
function setSftpPermissions($sftp, $remotePath, &$log)
{
    if (!is_object($sftp)) return false;
    if (!method_exists($sftp, 'chmod')) {
        $log[] = "SFTP chmod não disponível para $remotePath";
        return false;
    }
    try {
        $ok = $sftp->chmod(0777, $remotePath);
        $log[] = $ok ? "SFTP chmod 0777 aplicado: $remotePath" : "SFTP chmod retornou false: $remotePath";
        return (bool)$ok;
    } catch (Exception $e) {
        $log[] = "Exception ao aplicar chmod SFTP em $remotePath: " . $e->getMessage();
        return false;
    }
}

// Tenta aplicar CHMOD 0777 via FTP (usa ftp_chmod se disponível, senão ftp_site)
function ensureFtpPermissions($ftpConn, $path, &$log)
{
    if (!$ftpConn) return false;
    if (function_exists('ftp_chmod')) {
        $res = @ftp_chmod($ftpConn, 0777, $path);
        $log[] = $res !== false ? "FTP chmod 0777 aplicado: $path" : "FTP chmod falhou: $path";
        return $res !== false;
    }
    // fallback: usar SITE CHMOD
    $res = @ftp_site($ftpConn, "CHMOD 0777 $path");
    $log[] = $res !== false ? "FTP SITE CHMOD aplicado: $path" : "FTP SITE CHMOD falhou: $path";
    return $res !== false;
}

function buscarNomenclatura($conn, $obra_id)
{
    $stmt = $conn->prepare("SELECT nomenclatura FROM obra WHERE idobra = ?");
    $stmt->bind_param("i", $obra_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        return $row['nomenclatura'];
    }

    return null;
}

// Garante que um caminho exista no FTP (cria recursivamente). Retorna true/false e adiciona logs.
function ensureFtpDir($ftp, $path, &$log)
{
    // Remove barras duplicadas e mantém caminho absoluto
    $parts = array_filter(explode('/', $path), 'strlen');
    $cur = '/';
    // Salva diretório atual
    $orig = @ftp_pwd($ftp);
    foreach ($parts as $p) {
        $cur = rtrim($cur, '/') . '/' . $p;
        // tenta mudar para o diretório
        if (@ftp_chdir($ftp, $cur) === false) {
            // tenta criar
            if (@ftp_mkdir($ftp, $cur) === false) {
                $log[] = "Falha ao criar pasta FTP: $cur";
                // tenta voltar
                if ($orig) @ftp_chdir($ftp, $orig);
                return false;
            } else {
                $log[] = "Criada pasta FTP: $cur";
            }
        }
    }
    // volta para o original, se possível
    if ($orig) @ftp_chdir($ftp, $orig);
    return true;
}

function buscarPastaBaseSFTP($sftp, $conn, $obra_id)
{
    // Bases de clientes (anos possíveis)
    $clientes_base = ['/mnt/clientes/2024', '/mnt/clientes/2025'];

    // Busca a nomenclatura da obra
    $stmt = $conn->prepare("SELECT nomenclatura FROM obra WHERE idobra = ?");
    $stmt->bind_param("i", $obra_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || !$row = $result->fetch_assoc()) {
        return null;
    }

    $nomenclatura = $row['nomenclatura'];

    // Verifica em qual base a obra existe **no SFTP**
    foreach ($clientes_base as $base) {
        $pasta = $base . "/" . $nomenclatura . "/05.Exchange/01.Input/";
        if ($sftp->is_dir($pasta)) {
            return $pasta; // Retorna caminho válido no servidor
        }
    }

    return null; // Não encontrado
}



$pastaBase = buscarPastaBaseSFTP($sftp, $conn, $obra_id);
if (!$pastaBase) {
    $errors[] = "Pasta da obra não encontrada no servidor SFTP para Obra ID $obra_id.";
    echo json_encode(['success' => $success, 'errors' => $errors, 'log' => $log]);
    exit;
}

// Variável de conexão FTP (inicializada apenas se necessária)
$ftp_conn = null;


function gerarNomeInterno($conn, $obra_id, $tipo_id, $categoria, $nomeTipo, $tipo_arquivo, $ext, &$log, $sufixo = '', $imagem_id = null, $fileOriginalName = null, $indiceEnvio = 1)
{
    // 🔹 Busca nomenclatura da obra
    $obraRes = $conn->query("SELECT nomenclatura FROM obra WHERE idobra = $obra_id");
    $nomenclatura = ($obraRes && $obraRes->num_rows > 0)
        ? $obraRes->fetch_assoc()['nomenclatura']
        : "OBRA{$obra_id}";

    // 🔹 Limpeza e redução dos nomes
    $nomeTipoLimpo = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nomeTipo), 0, 3));
    $categoriaNome = strtoupper(substr(buscarNomeCategoria($categoria), 0, 3));
    $tipoArquivoAbrev = strtoupper(substr($tipo_arquivo, 0, 3));

    // 🔹 Parte base do nome original (sem extensão)
    $fileOriginalBase = $fileOriginalName
        ? preg_replace('/[^A-Za-z0-9]/', '', pathinfo($fileOriginalName, PATHINFO_FILENAME))
        : '';

    // 🔹 Se for SKP ou IMG → buscar versão pelo campo versao
    if ($tipo_arquivo === 'SKP' || $tipo_arquivo === 'IMG') {
        // Primeiro tenta encontrar por nome_original (comportamento antigo)
        $sql = "SELECT versao FROM arquivos 
                WHERE obra_id = ? 
                  AND tipo_imagem_id = ? 
                  AND categoria_id = ? 
                  AND tipo = ? 
                  AND nome_original = ?";
        $params = [$obra_id, $tipo_id, $categoria, $tipo_arquivo, $fileOriginalName];
        $types = "iiiss";

        if ($imagem_id) {
            $sql .= " AND imagem_id = ?";
            $params[] = $imagem_id;
            $types .= "i";
        }

        $sql .= " ORDER BY versao DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $versao = 1;
        if ($row = $res->fetch_assoc()) {
            $versao = intval($row['versao']) + 1;
        } else {
            // Fallback: procurar última versão por obra/tipo_imagem/categoria/(imagem_id) independente do nome_original
            $sql2 = "SELECT versao FROM arquivos 
                     WHERE obra_id = ? 
                       AND tipo_imagem_id = ? 
                       AND categoria_id = ? 
                       AND tipo = ?";
            $params2 = [$obra_id, $tipo_id, $categoria, $tipo_arquivo];
            $types2 = "iiii";
            if ($imagem_id) {
                $sql2 .= " AND imagem_id = ?";
                $params2[] = $imagem_id;
                $types2 .= "i";
            }
            $sql2 .= " ORDER BY versao DESC LIMIT 1";
            $stmt2 = $conn->prepare($sql2);
            if ($stmt2) {
                $stmt2->bind_param($types2, ...$params2);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                if ($row2 = $res2->fetch_assoc()) {
                    $versao = intval($row2['versao']) + 1;
                }
                $stmt2->close();
            }
        }
    }
    // 🔹 Demais tipos → busca pela convenção antiga
    else {
        $sql = "SELECT nome_interno FROM arquivos 
                WHERE obra_id = ? 
                  AND tipo_imagem_id = ? 
                  AND categoria_id = ? 
                  AND tipo = ? 
                  AND nome_original = ?";
        $params = [$obra_id, $tipo_id, $categoria, $tipo_arquivo, $fileOriginalName];
        $types = "iiiss";

        $sql .= " ORDER BY idarquivo DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $versao = 1;
        if ($row = $res->fetch_assoc()) {
            if (preg_match('/_v(\d+)/', $row['nome_interno'], $m)) {
                $versao = intval($m[1]) + 1;
            }
        }
    }

    // sanitize sufixo for filename
    $sufixoSafe = $sufixo ? preg_replace('/[^A-Za-z0-9_-]/', '', strtoupper(str_replace(' ', '_', $sufixo))) : '';

    // 🔹 Montagem final do nome interno (inclui sufixo quando presente)
    $envioStr = sprintf("env%02d", $indiceEnvio);
    if ($sufixoSafe) {
        $nomeInterno = "{$nomenclatura}-{$categoriaNome}-{$tipoArquivoAbrev}-{$sufixoSafe}-{$envioStr}-v{$versao}.{$ext}";
    } else {
        $nomeInterno = "{$nomenclatura}-{$categoriaNome}-{$tipoArquivoAbrev}-{$envioStr}-v{$versao}.{$ext}";
    }

    $log[] = "Gerado nome interno: $nomeInterno (versão $versao, envio $indiceEnvio)";

    return [$nomeInterno, $versao];
}


// =======================
// Upload principal
// =======================
if (!empty($arquivosTmp) && count($arquivosTmp) > 0 && ($refsSkpModo === 'geral' || $tipo_arquivo !== 'SKP')) {
    $indice = 1;

    foreach ($arquivosTmp as $index => $fileTmp) {
        $fileOriginalName = basename($arquivosName[$index]);
        $ext = pathinfo($fileOriginalName, PATHINFO_EXTENSION);
        $log[] = "Processando upload principal: $fileOriginalName";

        foreach ($tiposImagem as $nomeTipo) {
            $tipo_id = buscarTipoImagemId($conn, $nomeTipo, $log);
            if (!$tipo_id) {
                $errors[] = "Tipo de imagem '$nomeTipo' não encontrado.";
                continue;
            }

            $categoriaNome = buscarNomeCategoria($categoria);
            // resolve candidate folder name no SFTP (tenta variações e cria uma pasta sanitizada caso necessário)
            $categoriaDir = resolveCategoriaDir($sftp, $pastaBase, $categoriaNome, $log);

            $destDir = rtrim($pastaBase, '/') . '/' . $categoriaDir . '/' . $nomeTipo . '/' . $tipo_arquivo;
            if (!$sftp->is_dir($destDir)) {
                $sftp->mkdir($destDir, 0777, true);
                $log[] = "Criado diretório: $destDir";
                // tentar definir permissões da pasta
                if (method_exists($sftp, 'chmod')) {
                    try { $sftp->chmod(0777, $destDir); $log[] = "Permissões 0777 aplicadas em $destDir"; } catch (Exception $e) { $log[] = "Falha chmod dir $destDir: " . $e->getMessage(); }
                }
            }
            if (!$sftp->is_dir($destDir . "/OLD")) {
                $sftp->mkdir($destDir . "/OLD", 0777, true);
                $log[] = "Criado diretório: $destDir/OLD";
                if (method_exists($sftp, 'chmod')) {
                    try { $sftp->chmod(0777, $destDir . "/OLD"); $log[] = "Permissões 0777 aplicadas em $destDir/OLD"; } catch (Exception $e) { $log[] = "Falha chmod dir $destDir/OLD: " . $e->getMessage(); }
                }
            }

            list($fileNomeInterno, $versao) = gerarNomeInterno($conn, $obra_id, $tipo_id, $categoria, $nomeTipo, $tipo_arquivo, $ext, $log, $sufixo, null, $fileOriginalName, $indice);
            $destFile = $destDir . "/" . $fileNomeInterno;
            $log[] = "Destino final: $destFile";
            $indice++;

            // Substituição
            $check = $conn->prepare("SELECT * FROM arquivos 
    WHERE obra_id = ? 
      AND tipo_imagem_id = ? 
      AND tipo = ? 
      AND status = 'atualizado'
      AND nome_original = ?");
            $check->bind_param("iiss", $obra_id, $tipo_id, $tipo_arquivo, $fileOriginalName);
            $check->execute();
            $result = $check->get_result();
            $log[] = "Encontrados {$result->num_rows} arquivos antigos para $fileOriginalName.";
            if ($result->num_rows > 0 && $substituicao) {
                while ($old = $result->fetch_assoc()) {
                    $oldPath = $destDir . "/" . $old['nome_interno'];
                    $newPath = $destDir . "/OLD/" . $old['nome_interno'];
                    $log[] = "Movendo $oldPath => $newPath";
                    if (!$sftp->rename($oldPath, $newPath)) {
                        $errors[] = "Falha ao mover {$old['nome_interno']} para OLD.";
                    }
                    $conn->query("UPDATE arquivos SET status='antigo' WHERE idarquivo=" . $old['idarquivo']);
                }
            }

            if (!empty($fileTmp) && file_exists($fileTmp)) {
                if (sftpPutWithFallback($sftp, $destFile, $fileTmp, $log, $errors)) {
                    $stmt = $conn->prepare("INSERT INTO arquivos 
                    (obra_id, tipo_imagem_id, imagem_id, nome_original, nome_interno, caminho, tipo, versao, status, origem, recebido_por, categoria_id, sufixo, descricao) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'atualizado', 'upload_web', 'sistema', ?, ?, ?)");
                    // types: obra_id(i), tipo_imagem_id(i), imagem_id(i), nome_original(s), nome_interno(s), caminho(s), tipo(s), versao(i), categoria(i), sufixo(s), descricao(s)
                    $stmt->bind_param("iiissssiiss", $obra_id, $tipo_id, $imagem_id, $fileOriginalName, $fileNomeInterno, $destFile, $tipo_arquivo, $versao, $categoria, $sufixo, $descricao);

                    $stmt->execute();
                    $success[] = "Arquivo '$fileOriginalName' enviado para $nomeTipo como '$fileNomeInterno'";
                    $log[] = "Arquivo enviado com sucesso: $destFile";

                    // Se for categoria 7, também envia ao FTP secundário
                    if ($categoria == 7) {
                        if ($ftp_conn === null) {
                            $ftp_conn = @ftp_connect($ftp_host, $ftp_port, 10);
                            if ($ftp_conn && @ftp_login($ftp_conn, $ftp_user, $ftp_pass)) {
                                @ftp_pasv($ftp_conn, true);
                                $log[] = "Conectado no FTP secundário: $ftp_host";
                            } else {
                                $log[] = "Falha ao conectar no FTP secundário: $ftp_host";
                                $errors[] = "Falha ao conectar no FTP secundário.";
                                $ftp_conn = null;
                            }
                        }

                        if ($ftp_conn) {
                            $nomen = buscarNomenclatura($conn, $obra_id);
                            $ftpTargetDir = rtrim($ftp_base, '/') . '/' . ($nomen ? $nomen : 'OBRA' . $obra_id) . '/' . $categoriaDir . '/' . $nomeTipo . '/' . $tipo_arquivo;
                            if (ensureFtpDir($ftp_conn, $ftpTargetDir, $log)) {
                                $ftpDest = $ftpTargetDir . '/' . $fileNomeInterno;
                                if (@ftp_put($ftp_conn, $ftpDest, $fileTmp, FTP_BINARY)) {
                                    $log[] = "Arquivo enviado para FTP: $ftpDest";
                                    // tentar ajustar permissões no FTP
                                    ensureFtpPermissions($ftp_conn, $ftpDest, $log);
                                } else {
                                    $errors[] = "Erro ao enviar para FTP: $ftpDest";
                                    $log[] = "Falha FTP put: $ftpDest";
                                }
                            }
                        }
                    }
                } else {
                    $errors[] = "Erro ao enviar '$fileOriginalName' para $nomeTipo";
                    $log[] = "Falha ao enviar: $destFile";
                }
            }
        }
    }
}

// =======================
// Upload por imagem (permitir para todos os tipos quando modo = 'porImagem')
// =======================
if (!empty($arquivosPorImagem) && $refsSkpModo === 'porImagem') {

    $log[] = "Iniciando upload por imagem (modo porImagem)...";
    foreach ($arquivosPorImagem['name'] as $imagem_id => $arquivosArray) {
        // Substituição apenas uma vez por imagem
        // Filtra por obra_id, tipo, imagem_id e categoria_id para evitar mover arquivos de outras categorias
        $stmtCheck = $conn->prepare("SELECT * FROM arquivos 
        WHERE obra_id = ? AND tipo = ? AND imagem_id = ? AND categoria_id = ? AND status = 'atualizado'");
        if ($stmtCheck) {
            $stmtCheck->bind_param("isii", $obra_id, $tipo_arquivo, $imagem_id, $categoria);
            $stmtCheck->execute();
            $check = $stmtCheck->get_result();
            $log[] = "Encontrados {$check->num_rows} arquivos refs/skp antigos para imagem $imagem_id (categoria $categoria).";
            if ($check->num_rows > 0 && $substituicao) {
                while ($old = $check->fetch_assoc()) {
                    $oldPath = $old['caminho'];
                    $newPath = dirname($oldPath) . "/OLD/" . basename($oldPath);
                    $log[] = "Movendo $oldPath => $newPath";
                    if ($sftp->file_exists($oldPath)) {
                        if (!$sftp->rename($oldPath, $newPath)) {
                            $errors[] = "Falha ao mover {$old['nome_interno']} para OLD.";
                        }
                    } else {
                        $errors[] = "Arquivo antigo {$old['nome_interno']} não encontrado.";
                    }
                    $conn->query("UPDATE arquivos SET status='antigo' WHERE idarquivo=" . $old['idarquivo']);
                }
            }
            $stmtCheck->close();
        } else {
            $log[] = "Falha ao preparar consulta de verificação de arquivos antigos: " . $conn->error;
        }
        $indice = 1;

        // Agora envia todos os arquivos novos para essa imagem
        foreach ($arquivosArray as $index => $nomeOriginal) {
            $tmpFile = $arquivosPorImagem['tmp_name'][$imagem_id][$index];
            if (empty($tmpFile) || !file_exists($tmpFile)) {
                $log[] = "Arquivo vazio ou inexistente: $nomeOriginal";
                continue;
            }
            $ext = pathinfo($nomeOriginal, PATHINFO_EXTENSION);

            $nomeTipo = $tiposImagem[0] ?? '';
            $tipo_id = buscarTipoImagemId($conn, $nomeTipo, $log);
            if (!$tipo_id) {
                $errors[] = "Tipo de imagem '$nomeTipo' não encontrado.";
                continue;
            }

            $queryImagem = $conn->query("SELECT imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra=$imagem_id");
            if ($queryImagem->num_rows == 0) {
                $errors[] = "Imagem ID $imagem_id não encontrada.";
                $log[] = "Imagem ID $imagem_id não encontrada.";
                continue;
            }
            $nome_imagem = $queryImagem->fetch_assoc()['imagem_nome'];

            $categoriaNome = buscarNomeCategoria($categoria);
            $categoriaDir = resolveCategoriaDir($sftp, $pastaBase, $categoriaNome, $log);

            $destDir = rtrim($pastaBase, '/') . '/' . $categoriaDir . '/' . $nomeTipo . '/' . $tipo_arquivo . '/' . $nome_imagem;
            if (!$sftp->is_dir($destDir)) {
                $sftp->mkdir($destDir, 0777, true);
                $log[] = "Criado diretório: $destDir";
            }
            if (!$sftp->is_dir($destDir . "/OLD")) {
                $sftp->mkdir($destDir . "/OLD", 0777, true);
                $log[] = "Criado diretório: $destDir/OLD";
            }

            list($fileNomeInterno, $versao) = gerarNomeInterno($conn, $obra_id, $tipo_id, $categoria, $nomeTipo, $tipo_arquivo, $ext, $log, $sufixo, $imagem_id, $nomeOriginal, $indice);
            $destFile = "$destDir/$fileNomeInterno";
            $log[] = "Destino final: $destFile";

            $indice++;

                if (sftpPutWithFallback($sftp, $destFile, $tmpFile, $log, $errors)) {
                $stmt = $conn->prepare("INSERT INTO arquivos 
                (obra_id, tipo_imagem_id, imagem_id, nome_original, nome_interno, caminho, tipo, versao, status, origem, recebido_por, categoria_id, sufixo, descricao) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'atualizado', 'upload_web', 'sistema', ?, ?, ?)");
                // types: obra_id(i), tipo_imagem_id(i), imagem_id(i), nome_original(s), nome_interno(s), caminho(s), tipo(s), versao(i), categoria(i), sufixo(s), descricao(s)
                $stmt->bind_param("iiissssiiss", $obra_id, $tipo_id, $imagem_id, $nomeOriginal, $fileNomeInterno, $destFile, $tipo_arquivo, $versao, $categoria, $sufixo, $descricao);

                $stmt->execute();
                $success[] = "Arquivo '$nomeOriginal' enviado para Imagem $imagem_id";
                $log[] = "Arquivo enviado com sucesso: $destFile";

                // Se for categoria 7, envia também ao FTP secundário
                if ($categoria == 7) {
                    if ($ftp_conn === null) {
                        $ftp_conn = @ftp_connect($ftp_host, $ftp_port, 10);
                        if ($ftp_conn && @ftp_login($ftp_conn, $ftp_user, $ftp_pass)) {
                            @ftp_pasv($ftp_conn, true);
                            $log[] = "Conectado no FTP secundário: $ftp_host";
                        } else {
                            $log[] = "Falha ao conectar no FTP secundário: $ftp_host";
                            $errors[] = "Falha ao conectar no FTP secundário.";
                            $ftp_conn = null;
                        }
                    }

                    if ($ftp_conn) {
                        $nomen = buscarNomenclatura($conn, $obra_id);
                        $ftpTargetDir = rtrim($ftp_base, '/') . '/' . ($nomen ? $nomen : 'OBRA' . $obra_id) . '/' . $categoriaDir . '/' . $nomeTipo . '/' . $tipo_arquivo . '/' . $nome_imagem;
                        if (ensureFtpDir($ftp_conn, $ftpTargetDir, $log)) {
                            $ftpDest = $ftpTargetDir . '/' . $fileNomeInterno;
                            if (@ftp_put($ftp_conn, $ftpDest, $tmpFile, FTP_BINARY)) {
                                $log[] = "Arquivo enviado para FTP: $ftpDest";
                                // tentar ajustar permissões no FTP
                                ensureFtpPermissions($ftp_conn, $ftpDest, $log);
                            } else {
                                $errors[] = "Erro ao enviar para FTP: $ftpDest";
                                $log[] = "Falha FTP put: $ftpDest";
                            }
                        }
                    }
                }
                // Notificar colaborador(es) associados à funcao_id = 4 para esta imagem
                try {
                    $notifMsg = "Ângulo definido para a imagem, confira abaixo:";
                    $notifMsg2 = "Ângulo definido para a imagem";
                    $sel = $conn->prepare("SELECT colaborador_id, idfuncao_imagem FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = 4");
                    if ($sel) {
                        $sel->bind_param("i", $imagem_id);
                        $sel->execute();
                        $resSel = $sel->get_result();
                        if ($resSel && $resSel->num_rows > 0) {
                            $ins = $conn->prepare("INSERT INTO notificacoes (colaborador_id, mensagem, data, lida, funcao_imagem_id) VALUES (?, ?, NOW(), 0, ?)");
                            while ($rowNotif = $resSel->fetch_assoc()) {
                                $colabId = $rowNotif['colaborador_id'];
                                $funcaoImagemId = $rowNotif['idfuncao_imagem'];
                                if ($ins) {
                                    $ins->bind_param("isi", $colabId, $notifMsg, $funcaoImagemId);
                                    $ins->execute();
                                    $log[] = "Notificação criada para colaborador $colabId";

                                    // Atualiza o status da funcao_imagem para 'Não iniciado' quando o ângulo é definido
                                    try {
                                        $novoStatus = 'Não iniciado';
                                        $statusAnterior = null;
                                        $stmtStatus = $conn->prepare("SELECT status FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1");
                                        if ($stmtStatus) {
                                            $stmtStatus->bind_param('i', $funcaoImagemId);
                                            $stmtStatus->execute();
                                            $resStatus = $stmtStatus->get_result();
                                            if ($resStatus && $rowS = $resStatus->fetch_assoc()) {
                                                $statusAnterior = $rowS['status'];
                                            }
                                            $stmtStatus->close();
                                        }

                                        // Só atualiza se for diferente
                                        if ($statusAnterior !== $novoStatus) {
                                            $upd = $conn->prepare("UPDATE funcao_imagem SET status = ? WHERE idfuncao_imagem = ?");
                                            if ($upd) {
                                                $upd->bind_param('si', $novoStatus, $funcaoImagemId);
                                                $upd->execute();
                                                $log[] = "Status da funcao_imagem $funcaoImagemId atualizado: '" . ($statusAnterior ?? 'NULL') . "' => '$novoStatus'";
                                                $upd->close();

                                                // Insere no historico_aprovacoes para registrar a mudança
                                                $histColab = is_numeric($colabId) ? (int)$colabId : 0;
                                                $responsavel = 0; // upload automático / sistema
                                                $insHist = $conn->prepare("INSERT INTO historico_aprovacoes (funcao_imagem_id, status_anterior, status_novo, colaborador_id, responsavel) VALUES (?, ?, ?, ?, ?)");
                                                if ($insHist) {
                                                    // status_anterior pode ser NULL; passar como string vazia quando necessário
                                                    $sa = $statusAnterior ?? '';
                                                    $insHist->bind_param('issii', $funcaoImagemId, $sa, $novoStatus, $histColab, $responsavel);
                                                    $insHist->execute();
                                                    $insHist->close();
                                                    $log[] = "Histórico de status criado para funcao_imagem $funcaoImagemId";
                                                } else {
                                                    $log[] = "Falha ao preparar insert historico_aprovacoes: " . $conn->error;
                                                }
                                            } else {
                                                $log[] = "Falha ao preparar update funcao_imagem: " . $conn->error;
                                            }
                                        } else {
                                            $log[] = "Funcao_imagem $funcaoImagemId já está com status '$novoStatus' — sem alteração.";
                                        }
                                    } catch (Exception $e) {
                                        $log[] = "Erro ao atualizar status da funcao_imagem: " . $e->getMessage();
                                    }
                                    // Enviar notificação também para o Slack (se o usuário tiver nome_slack)
                                    try {
                                        $stmtSlack = $conn->prepare("SELECT nome_slack FROM usuario WHERE idcolaborador = ? LIMIT 1");
                                        if ($stmtSlack) {
                                            $stmtSlack->bind_param('i', $colabId);
                                            $stmtSlack->execute();
                                            $resSlack = $stmtSlack->get_result();
                                            if ($resSlack && $rowSlack = $resSlack->fetch_assoc()) {
                                                $nomeSlack = trim($rowSlack['nome_slack']);
                                                if ($nomeSlack) {
                                                    // prefer token-based chat.postMessage when FLOW_TOKEN is set
                                                    $text = "[IMPROOV] $notifMsg2: $nome_imagem\nAcesse: https://improov.com.br/sistema/inicio.php";
                                                    if (!empty($FLOW_TOKEN)) {
                                                        $ok = send_slack_token_message($FLOW_TOKEN, $nomeSlack, $text, $log);
                                                        if (!$ok) {
                                                            // Per request: do NOT fallback to webhook/channel — only token-based person messages
                                                            $log[] = "Slack token send failed for $nomeSlack; webhook fallback disabled per config.";
                                                        }
                                                    } else {
                                                        // No FLOW_TOKEN configured — skip sending to Slack for individual
                                                        $log[] = "FLOW_TOKEN not configured; skipping Slack notification for $nomeSlack per request.";
                                                    }
                                                } else {
                                                    $log[] = "Usuário $colabId sem nome_slack cadastrado";
                                                }
                                            }
                                            $stmtSlack->close();
                                        } else {
                                            $log[] = "Falha ao preparar select nome_slack: " . $conn->error;
                                        }
                                    } catch (Exception $e) {
                                        $log[] = "Erro ao enviar Slack: " . $e->getMessage();
                                    }
                                } else {
                                    $log[] = "Falha ao preparar insert notificacoes: " . $conn->error;
                                }
                            }
                            if ($ins) $ins->close();
                        } else {
                            $log[] = "Nenhum colaborador (funcao_id=4) encontrado para imagem $imagem_id";
                        }
                        $sel->close();
                    } else {
                        $log[] = "Falha ao preparar select funcao_imagem: " . $conn->error;
                    }
                } catch (Exception $e) {
                    $log[] = "Erro ao criar notificacoes: " . $e->getMessage();
                }
            } else {
                $errors[] = "Erro ao enviar '$nomeOriginal' para Imagem $imagem_id";
                $log[] = "Falha ao enviar: $destFile";
            }
        }
    }
}

$conn->close();
if ($ftp_conn) {
    @ftp_close($ftp_conn);
    $log[] = "Conexão FTP fechada.";
}
echo json_encode(['success' => $success, 'errors' => $errors, 'log' => $log]);
