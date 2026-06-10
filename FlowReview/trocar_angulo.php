<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/secure_env.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Usuario nao autenticado.']);
    exit;
}

include_once __DIR__ . '/../conexao.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

function fr_troca_sanitize_dir_name($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return 'SemNome';
    }
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($converted !== false) {
        $value = $converted;
    }
    $value = preg_replace('/[^A-Za-z0-9._ -]+/', '', $value);
    $value = preg_replace('/\s+/', '_', trim((string)$value));
    $value = trim((string)$value, '._- ');
    return $value !== '' ? $value : 'SemNome';
}

function fr_troca_normalize_funcao($value)
{
    $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$value);
    $value = strtolower((string)$value);
    $value = preg_replace('/[^a-z0-9]+/', '', $value);
    return $value;
}

function fr_troca_resolver_arquivo_local($pathAngulo, &$logs)
{
    $relative = ltrim(str_replace('\\', '/', (string)$pathAngulo), '/');
    $localPath = dirname(__DIR__) . '/' . $relative;
    if (is_file($localPath)) {
        return [$localPath, null];
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'ang_');
    if ($tmpPath === false) {
        $logs[] = 'tmp_file_create_failed';
        return [null, null];
    }

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $isHttps ? 'https' : 'http';
    $basePrefix = '';
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $pos = strpos($scriptName, '/FlowReview/');
    if ($pos !== false) {
        $basePrefix = substr($scriptName, 0, $pos + 1);
    }

    $candidateUrls = [];
    if ($host !== '') {
        $candidateUrls[] = $scheme . '://' . $host . $basePrefix . $relative;
    }
    $candidateUrls[] = 'https://improov.com.br/flow/ImproovWeb/' . $relative;
    if (preg_match('/^https?:\/\//i', (string)$pathAngulo)) {
        $candidateUrls[] = (string)$pathAngulo;
    }

    foreach (array_values(array_unique($candidateUrls)) as $urlRaw) {
        $url = str_replace(' ', '%20', $urlRaw);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $content = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($content !== false && strlen((string)$content) > 0 && ($httpCode === 0 || $httpCode < 400)) {
            if (@file_put_contents($tmpPath, $content) === false) {
                @unlink($tmpPath);
                $logs[] = 'tmp_file_write_failed';
                return [null, null];
            }
            $logs[] = 'angle_downloaded_tmp=' . $url;
            return [$tmpPath, $tmpPath];
        }

        $logs[] = 'download_angle_failed=' . $url . '|' . ($curlError ?: ('http_' . $httpCode));
    }

    @unlink($tmpPath);
    return [null, null];
}

function fr_troca_resolver_extensao($pathAngulo, $localPath)
{
    $ext = strtolower((string)pathinfo((string)$pathAngulo, PATHINFO_EXTENSION));
    if ($ext !== '') {
        return $ext;
    }
    $ext = strtolower((string)pathinfo((string)$localPath, PATHINFO_EXTENSION));
    return $ext !== '' ? $ext : 'jpeg';
}

function fr_troca_resolver_nome_original($pathAngulo, $localPath)
{
    $fromPath = basename(str_replace('\\', '/', (string)$pathAngulo));
    if ($fromPath !== '' && strpos($fromPath, '.') !== false) {
        return $fromPath;
    }
    return basename((string)$localPath);
}

function fr_troca_tipo_imagem_id($conn, $nomeTipo)
{
    $idTipo = null;
    if ($st = $conn->prepare('SELECT id_tipo_imagem FROM tipo_imagem WHERE nome = ? LIMIT 1')) {
        $st->bind_param('s', $nomeTipo);
        $st->execute();
        $st->bind_result($idTipo);
        $st->fetch();
        $st->close();
    }
    return $idTipo ? (int)$idTipo : 0;
}

function fr_troca_resolve_categoria_dir($sftp, $pastaBase, $categoriaNome)
{
    $candidates = [
        $categoriaNome,
        str_replace(' ', '', $categoriaNome),
        str_replace(' ', '_', $categoriaNome),
        fr_troca_sanitize_dir_name($categoriaNome),
    ];

    foreach ($candidates as $cand) {
        $path = rtrim($pastaBase, '/') . '/' . $cand;
        if ($sftp->is_dir($path)) {
            return $cand;
        }
    }

    $safe = fr_troca_sanitize_dir_name($categoriaNome);
    $pathSafe = rtrim($pastaBase, '/') . '/' . $safe;
    if (!$sftp->is_dir($pathSafe)) {
        $sftp->mkdir($pathSafe, 0777, true);
    }
    return $safe;
}

function fr_troca_buscar_pasta_base_sftp($sftp, $conn, $obraId)
{
    $nomen = null;
    if ($st = $conn->prepare('SELECT nomenclatura FROM obra WHERE idobra = ? LIMIT 1')) {
        $st->bind_param('i', $obraId);
        $st->execute();
        $st->bind_result($nomen);
        $st->fetch();
        $st->close();
    }
    if (!$nomen) {
        return null;
    }

    $bases = ['/mnt/clientes/2024', '/mnt/clientes/2025', '/mnt/clientes/2026'];
    $clientesRoot = '/mnt/clientes';
    $list = @$sftp->nlist($clientesRoot);
    if (is_array($list)) {
        foreach ($list as $item) {
            $name = basename((string)$item);
            if (preg_match('/^20\d{2}$/', $name)) {
                $bases[] = $clientesRoot . '/' . $name;
            }
        }
    }

    foreach (array_values(array_unique($bases)) as $base) {
        $candidate = $base . '/' . $nomen . '/05.Exchange/01.Input';
        if ($sftp->is_dir($candidate)) {
            return $candidate;
        }
    }
    return null;
}

function fr_troca_sftp_put($sftp, $remotePath, $localPath, &$logs)
{
    try {
        if ($sftp->put($remotePath, $localPath, phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE)) {
            return true;
        }
    } catch (Throwable $e) {
        $logs[] = 'sftp_put_local_exception=' . $e->getMessage();
    }

    if (is_file($localPath) && is_readable($localPath)) {
        $content = @file_get_contents($localPath);
        if ($content !== false) {
            try {
                if ($sftp->put($remotePath, $content)) {
                    $logs[] = 'sftp_fallback_content_ok';
                    return true;
                }
            } catch (Throwable $e) {
                $logs[] = 'sftp_put_content_exception=' . $e->getMessage();
            }
        }
    }

    return false;
}

function fr_troca_nome_token($value)
{
    $value = fr_troca_sanitize_dir_name((string)$value);
    $value = strtoupper(str_replace(' ', '_', $value));
    $value = preg_replace('/[^A-Z0-9_-]/', '', $value);
    return trim((string)$value, '_-');
}

function fr_troca_proxima_versao_nome($sftp, $destDir, $baseNome)
{
    $max = 0;
    $list = @$sftp->nlist($destDir);
    if (!is_array($list)) {
        return 1;
    }

    $regex = '/^' . preg_quote($baseNome, '/') . '-v(\d+)\.[A-Za-z0-9]+$/i';
    foreach ($list as $item) {
        $name = basename((string)$item);
        if (preg_match($regex, $name, $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    return $max + 1;
}

function fr_troca_gerar_nome_padrao($sftp, $destDir, $nomenclatura, $tipoImagem, $indiceEnvio, $ext)
{
    $nomen = fr_troca_nome_token($nomenclatura);
    if ($nomen === '') {
        $nomen = 'OBRA';
    }
    $sufixo = fr_troca_nome_token($tipoImagem);
    $envio = sprintf('env%02d', max(1, (int)$indiceEnvio));

    $base = $nomen . '-ANG-IMG';
    if ($sufixo !== '') {
        $base .= '-' . $sufixo;
    }
    $base .= '-' . $envio;

    $versao = fr_troca_proxima_versao_nome($sftp, $destDir, $base);
    return [$base . '-v' . $versao . '.' . strtolower($ext), $versao];
}

function fr_troca_ensure_sftp_dir($sftp, $path, &$logs)
{
    $parts = array_filter(explode('/', (string)$path), 'strlen');
    $cur = '';
    foreach ($parts as $p) {
        $cur .= '/' . $p;
        if (!$sftp->is_dir($cur)) {
            if (!$sftp->mkdir($cur, 0777, true)) {
                $logs[] = 'sftp_mkdir_failed=' . $cur;
                return false;
            }
        }
    }
    return true;
}

function fr_troca_vps_config(&$logs)
{
    try {
        $vpsCfg = improov_sftp_config('IMPROOV_VPS_SFTP');
        return [
            'host' => (string)$vpsCfg['host'],
            'port' => (int)$vpsCfg['port'],
            'user' => (string)$vpsCfg['user'],
            'pass' => (string)$vpsCfg['pass'],
            'remotePath' => rtrim((string)improov_env('IMPROOV_VPS_SFTP_REMOTE_PATH'), '/'),
        ];
    } catch (RuntimeException $e) {
        $logs[] = 'vps_sftp_env_missing=' . $e->getMessage();
    }

    return null;
}

function fr_troca_insert_arquivo($conn, $ctx, $nomeOriginal, $nomeInterno, $caminhoRemoto, $versao, $localPath, $observacao, $colaboradorId, &$logs)
{
    $tipoImagemId = fr_troca_tipo_imagem_id($conn, $ctx['tipo_imagem']);
    if ($tipoImagemId <= 0) {
        $logs[] = 'tipo_imagem_id_not_found=' . $ctx['tipo_imagem'];
        return false;
    }

    $tipo = 'IMG';
    $categoriaId = 7;
    $origem = 'upload_web';
    $recebidoPor = 'sistema';
    $status = 'atualizado';
    $sufixo = '';
    $descricao = trim((string)$observacao) !== '' ? trim((string)$observacao) : 'Angulo definitivo trocado via Flow Review';
    $tamanho = (is_file($localPath) ? (string)filesize($localPath) : '0');
    $recebidoEm = date('Y-m-d H:i:s');
    $colabBind = ((int)$colaboradorId > 0) ? (int)$colaboradorId : null;

    $sql = "INSERT INTO arquivos
        (obra_id, tipo_imagem_id, imagem_id, nome_original, nome_interno, caminho, tipo, versao, status, origem, recebido_por, recebido_em, categoria_id, sufixo, descricao, tamanho, colaborador_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($st = $conn->prepare($sql)) {
        $st->bind_param(
            'iiissssissssisssi',
            $ctx['obra_id'],
            $tipoImagemId,
            $ctx['imagem_id'],
            $nomeOriginal,
            $nomeInterno,
            $caminhoRemoto,
            $tipo,
            $versao,
            $status,
            $origem,
            $recebidoPor,
            $recebidoEm,
            $categoriaId,
            $sufixo,
            $descricao,
            $tamanho,
            $colabBind
        );
        $ok = $st->execute();
        if (!$ok) {
            $logs[] = 'insert_arquivos_error=' . $st->error;
        } else {
            $logs[] = 'insert_arquivos_ok_id=' . $conn->insert_id;
        }
        $st->close();
        return $ok;
    }

    $logs[] = 'insert_arquivos_prepare_error=' . $conn->error;
    return false;
}

function fr_troca_find_arquivo_atual($conn, $imagemId)
{
    $row = null;
    $sql = "SELECT idarquivo, caminho, nome_interno
            FROM arquivos
            WHERE imagem_id = ?
              AND categoria_id = 7
              AND status = 'atualizado'
              AND (caminho LIKE '%/Angulo%' OR caminho LIKE '%/angulo%')
            ORDER BY idarquivo DESC
            LIMIT 1";
    if ($st = $conn->prepare($sql)) {
        $st->bind_param('i', $imagemId);
        $st->execute();
        $res = $st->get_result();
        if ($res) {
            $row = $res->fetch_assoc() ?: null;
        }
        $st->close();
    }
    return $row;
}

function fr_troca_arquivar_arquivo_nas($conn, $sftp, $arquivoAtual, $oldDir, &$logs)
{
    if (!$arquivoAtual || empty($arquivoAtual['caminho'])) {
        $logs[] = 'arquivo_atual_not_found_for_archive';
        return true;
    }

    $oldPath = (string)$arquivoAtual['caminho'];
    if (!$sftp->file_exists($oldPath)) {
        $logs[] = 'arquivo_atual_missing_on_nas=' . $oldPath;
        return true;
    }

    if (!$sftp->is_dir($oldDir)) {
        $sftp->mkdir($oldDir, 0777, true);
    }

    $base = pathinfo($oldPath, PATHINFO_FILENAME);
    $ext = pathinfo($oldPath, PATHINFO_EXTENSION);
    $backup = rtrim($oldDir, '/') . '/' . $base . '_' . date('Ymd_His') . ($ext ? '.' . $ext : '');
    if (!$sftp->rename($oldPath, $backup)) {
        $logs[] = 'archive_nas_failed=' . $oldPath;
        return false;
    }

    if ($up = $conn->prepare("UPDATE arquivos SET status = 'antigo', caminho = ?, descricao = CONCAT(COALESCE(descricao, ''), ' | Substituido por troca de angulo') WHERE idarquivo = ?")) {
        $arquivoId = (int)$arquivoAtual['idarquivo'];
        $up->bind_param('si', $backup, $arquivoId);
        $up->execute();
        $up->close();
    }
    $logs[] = 'archive_nas_ok=' . $backup;
    return true;
}

function fr_troca_enviar_vps($localPath, $ctx, $categoriaDir, $tipoDir, $nomeImagemDir, $fileName, $oldFileName, &$logs)
{
    $cfg = fr_troca_vps_config($logs);
    if (!$cfg || empty($cfg['host']) || empty($cfg['user']) || empty($cfg['pass']) || empty($cfg['remotePath'])) {
        $logs[] = 'vps_sftp_config_invalid';
        return false;
    }

    try {
        $sftp = new phpseclib3\Net\SFTP($cfg['host'], (int)$cfg['port']);
        if (!$sftp->login($cfg['user'], $cfg['pass'])) {
            $logs[] = 'vps_sftp_login_failed';
            return false;
        }

        $targetDir = rtrim($cfg['remotePath'], '/') . '/uploads/angulo_definido/' . fr_troca_sanitize_dir_name($ctx['nomenclatura']) . '/' . $categoriaDir . '/' . $tipoDir . '/IMG/' . $nomeImagemDir;
        if (!fr_troca_ensure_sftp_dir($sftp, $targetDir, $logs)) {
            return false;
        }
        $oldDir = $targetDir . '/OLD';
        fr_troca_ensure_sftp_dir($sftp, $oldDir, $logs);

        if ($oldFileName) {
            $oldPath = $targetDir . '/' . basename((string)$oldFileName);
            if ($sftp->file_exists($oldPath)) {
                $backup = $oldDir . '/' . pathinfo($oldFileName, PATHINFO_FILENAME) . '_' . date('Ymd_His') . '.' . pathinfo($oldFileName, PATHINFO_EXTENSION);
                if ($sftp->rename($oldPath, $backup)) {
                    $logs[] = 'vps_archive_ok=' . $backup;
                } else {
                    $logs[] = 'vps_archive_failed=' . $oldPath;
                }
            }
        }

        $targetFile = $targetDir . '/' . $fileName;
        $ok = fr_troca_sftp_put($sftp, $targetFile, $localPath, $logs);
        $logs[] = $ok ? ('vps_upload_ok=' . $targetFile) : ('vps_upload_failed=' . $targetFile);
        return $ok;
    } catch (Throwable $e) {
        $logs[] = 'vps_exception=' . $e->getMessage();
        return false;
    }
}

function fr_troca_publicar_angulo($conn, $ctx, $novoHistorico, $observacao, $colaboradorId, &$logs)
{
    if (!class_exists('phpseclib3\\Net\\SFTP')) {
        $logs[] = 'sftp_class_missing';
        return ['success' => false, 'message' => 'Biblioteca SFTP indisponivel.'];
    }

    [$localPath, $tmpToDelete] = fr_troca_resolver_arquivo_local($novoHistorico['imagem'], $logs);
    if (!$localPath || !is_file($localPath)) {
        return ['success' => false, 'message' => 'Arquivo do novo angulo nao encontrado.'];
    }

    try {
        $sftpCfg = improov_sftp_config();
    } catch (RuntimeException $e) {
        if ($tmpToDelete && is_file($tmpToDelete)) {
            @unlink($tmpToDelete);
        }
        $logs[] = 'sftp_env_missing=' . $e->getMessage();
        return ['success' => false, 'message' => 'Configuracao SFTP nao disponivel.'];
    }

    $sftp = new phpseclib3\Net\SFTP($sftpCfg['host'], (int)$sftpCfg['port']);
    if (!$sftp->login($sftpCfg['user'], $sftpCfg['pass'])) {
        if ($tmpToDelete && is_file($tmpToDelete)) {
            @unlink($tmpToDelete);
        }
        $logs[] = 'sftp_login_failed';
        return ['success' => false, 'message' => 'Falha ao autenticar no NAS.'];
    }

    $base = fr_troca_buscar_pasta_base_sftp($sftp, $conn, (int)$ctx['obra_id']);
    if (!$base) {
        if ($tmpToDelete && is_file($tmpToDelete)) {
            @unlink($tmpToDelete);
        }
        $logs[] = 'base_cliente_not_found';
        return ['success' => false, 'message' => 'Pasta da obra nao encontrada no NAS.'];
    }

    $categoriaDir = fr_troca_resolve_categoria_dir($sftp, $base, 'Angulo definido');
    $tipoDir = trim((string)$ctx['tipo_imagem']) !== '' ? trim((string)$ctx['tipo_imagem']) : 'SemTipo';
    $nomeImagemDir = trim((string)$ctx['imagem_nome']) !== '' ? fr_troca_sanitize_dir_name($ctx['imagem_nome']) : ('imagem_' . (int)$ctx['imagem_id']);
    $destDir = rtrim($base, '/') . '/' . $categoriaDir . '/' . $tipoDir . '/IMG/' . $nomeImagemDir;
    $oldDir = $destDir . '/OLD';

    if (!$sftp->is_dir($destDir)) {
        $sftp->mkdir($destDir, 0777, true);
    }
    if (!$sftp->is_dir($oldDir)) {
        $sftp->mkdir($oldDir, 0777, true);
    }

    $ext = fr_troca_resolver_extensao($novoHistorico['imagem'], $localPath);
    [$fileName, $versaoNome] = fr_troca_gerar_nome_padrao($sftp, $destDir, $ctx['nomenclatura'], $ctx['tipo_imagem'], $novoHistorico['indice_envio'], $ext);
    $destFile = $destDir . '/' . $fileName;
    $arquivoAtual = fr_troca_find_arquivo_atual($conn, (int)$ctx['imagem_id']);

    $okUpload = fr_troca_sftp_put($sftp, $destFile, $localPath, $logs);
    if (!$okUpload) {
        if ($tmpToDelete && is_file($tmpToDelete)) {
            @unlink($tmpToDelete);
        }
        return ['success' => false, 'message' => 'Nao foi possivel enviar o novo angulo para o NAS.'];
    }
    $logs[] = 'nas_upload_ok=' . $destFile;

    if (!fr_troca_arquivar_arquivo_nas($conn, $sftp, $arquivoAtual, $oldDir, $logs)) {
        try {
            if ($sftp->file_exists($destFile)) {
                $sftp->delete($destFile);
            }
        } catch (Throwable $e) {
            $logs[] = 'cleanup_new_upload_failed=' . $e->getMessage();
        }
        if ($tmpToDelete && is_file($tmpToDelete)) {
            @unlink($tmpToDelete);
        }
        return ['success' => false, 'message' => 'Nao foi possivel arquivar o angulo anterior no NAS.'];
    }

    $nomeOriginal = fr_troca_resolver_nome_original($novoHistorico['imagem'], $localPath);
    if (!fr_troca_insert_arquivo($conn, $ctx, $nomeOriginal, $fileName, $destFile, (int)$versaoNome, $localPath, $observacao, $colaboradorId, $logs)) {
        try {
            if ($sftp->file_exists($destFile)) {
                $sftp->delete($destFile);
            }
        } catch (Throwable $e) {
            $logs[] = 'cleanup_new_upload_after_db_failed=' . $e->getMessage();
        }
        if ($tmpToDelete && is_file($tmpToDelete)) {
            @unlink($tmpToDelete);
        }
        return ['success' => false, 'message' => 'Nao foi possivel registrar o novo angulo no banco.'];
    }

    $oldFileName = $arquivoAtual['nome_interno'] ?? ($arquivoAtual ? basename((string)$arquivoAtual['caminho']) : null);
    $vpsOk = fr_troca_enviar_vps($localPath, $ctx, $categoriaDir, $tipoDir, $nomeImagemDir, $fileName, $oldFileName, $logs);

    if ($tmpToDelete && is_file($tmpToDelete)) {
        @unlink($tmpToDelete);
    }

    return [
        'success' => true,
        'nas_path' => $destFile,
        'nome_interno' => $fileName,
        'vps_inserido' => (bool)$vpsOk,
    ];
}

function fr_troca_ensure_entrega_item_column($conn)
{
    $exists = false;
    if ($chk = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'angulos_imagens' AND COLUMN_NAME = 'entrega_item_id'")) {
        $chk->execute();
        $res = $chk->get_result();
        $exists = ($res && $res->num_rows > 0);
        $chk->close();
    }
    if (!$exists) {
        @$conn->query("ALTER TABLE angulos_imagens ADD COLUMN entrega_item_id INT NULL AFTER historico_id");
        @$conn->query("CREATE INDEX idx_angulos_entrega_item ON angulos_imagens(entrega_item_id)");
    }
}

$logs = [];
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Payload invalido.']);
    exit;
}

$imagemId = isset($data['imagem_id']) ? (int)$data['imagem_id'] : 0;
$funcaoImagemId = isset($data['funcao_imagem_id']) ? (int)$data['funcao_imagem_id'] : 0;
$novoHistoricoId = isset($data['novo_historico_id']) ? (int)$data['novo_historico_id'] : 0;
$observacao = trim((string)($data['observacao'] ?? ''));
$debug = isset($_GET['debug']) && (string)$_GET['debug'] === '1';

if ($imagemId <= 0 || $funcaoImagemId <= 0 || $novoHistoricoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

$idusuario = isset($_SESSION['idusuario']) ? (int)$_SESSION['idusuario'] : 0;
$idcolaboradorSessao = isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : 0;

$ctx = null;
$sqlCtx = "SELECT
        f.idfuncao_imagem,
        f.funcao_id,
        f.colaborador_id,
        f.status AS status_funcao,
        fun.nome_funcao,
        i.idimagens_cliente_obra AS imagem_id,
        i.imagem_nome,
        i.obra_id,
        i.tipo_imagem,
        s.nome_status,
        o.nomenclatura
    FROM funcao_imagem f
    JOIN funcao fun ON fun.idfuncao = f.funcao_id
    JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
    JOIN status_imagem s ON s.idstatus = i.status_id
    JOIN obra o ON o.idobra = i.obra_id
    WHERE f.idfuncao_imagem = ? AND f.imagem_id = ?
    LIMIT 1";
if ($st = $conn->prepare($sqlCtx)) {
    $st->bind_param('ii', $funcaoImagemId, $imagemId);
    $st->execute();
    $res = $st->get_result();
    $ctx = $res ? $res->fetch_assoc() : null;
    $st->close();
}

if (!$ctx) {
    echo json_encode(['success' => false, 'message' => 'Contexto da tarefa nao encontrado.']);
    exit;
}

$nomeFuncaoNorm = fr_troca_normalize_funcao($ctx['nome_funcao']);
if ((int)$ctx['funcao_id'] !== 4 || $nomeFuncaoNorm !== 'finalizacao') {
    echo json_encode(['success' => false, 'message' => 'Troca disponivel apenas para P00 + Finalizacao.']);
    exit;
}

$podeAprovar = in_array($idusuario, [1, 2, 3, 9, 20], true)
    || ($idusuario === 8 && in_array((int)$ctx['colaborador_id'], [23, 40], true))
    || ($idcolaboradorSessao > 0 && (int)$ctx['colaborador_id'] === $idcolaboradorSessao);

if (!$podeAprovar) {
    echo json_encode(['success' => false, 'message' => 'Usuario sem permissao para trocar o angulo.']);
    exit;
}

$novoHistorico = null;
$sqlNovo = "SELECT
        hi.id,
        hi.funcao_imagem_id,
        hi.imagem,
        hi.nome_arquivo,
        hi.indice_envio,
        hi.data_envio,
        COALESCE(
            (
                SELECT hs.nome_status
                FROM historico_imagens him
                INNER JOIN status_imagem hs ON hs.idstatus = him.status_id
                WHERE him.imagem_id = ?
                  AND him.data_movimento <= hi.data_envio
                ORDER BY him.data_movimento DESC, him.idhistorico DESC
                LIMIT 1
            ),
            ?
        ) AS nome_status_envio
    FROM historico_aprovacoes_imagens hi
    WHERE hi.id = ? AND hi.funcao_imagem_id = ?
    LIMIT 1";
if ($st = $conn->prepare($sqlNovo)) {
    $statusAtual = (string)$ctx['nome_status'];
    $st->bind_param('isii', $imagemId, $statusAtual, $novoHistoricoId, $funcaoImagemId);
    $st->execute();
    $res = $st->get_result();
    $novoHistorico = $res ? $res->fetch_assoc() : null;
    $st->close();
}

if (!$novoHistorico) {
    echo json_encode(['success' => false, 'message' => 'Novo angulo nao pertence a esta tarefa.']);
    exit;
}

if (mb_strtolower(trim((string)$novoHistorico['nome_status_envio']), 'UTF-8') !== 'p00') {
    echo json_encode(['success' => false, 'message' => 'O novo angulo precisa pertencer ao envio P00.']);
    exit;
}

fr_troca_ensure_entrega_item_column($conn);

$conn->begin_transaction();
try {
    $old = null;
    $sqlOld = "SELECT ai.id, ai.historico_id, ai.entrega_item_id
        FROM angulos_imagens ai
        JOIN historico_aprovacoes_imagens hi ON hi.id = ai.historico_id
        WHERE ai.imagem_id = ?
          AND hi.funcao_imagem_id = ?
          AND ai.liberada = 1
          AND COALESCE(ai.sugerida, 0) = 0
        ORDER BY ai.id DESC
        LIMIT 1
        FOR UPDATE";
    if ($st = $conn->prepare($sqlOld)) {
        $st->bind_param('ii', $imagemId, $funcaoImagemId);
        $st->execute();
        $res = $st->get_result();
        $old = $res ? $res->fetch_assoc() : null;
        $st->close();
    }

    if (!$old) {
        throw new Exception('Nenhum angulo definitivo atual encontrado.');
    }
    if ((int)$old['historico_id'] === $novoHistoricoId) {
        throw new Exception('Escolha um angulo diferente do atual.');
    }

    $entregaItemId = isset($old['entrega_item_id']) && $old['entrega_item_id'] !== null ? (int)$old['entrega_item_id'] : null;

    if ($entregaItemId !== null) {
        $ins = $conn->prepare('INSERT IGNORE INTO angulos_imagens (imagem_id, historico_id, entrega_item_id, liberada, sugerida, motivo_sugerida) VALUES (?, ?, ?, 0, 0, "")');
        $ins->bind_param('iii', $imagemId, $novoHistoricoId, $entregaItemId);
    } else {
        $ins = $conn->prepare('INSERT IGNORE INTO angulos_imagens (imagem_id, historico_id, liberada, sugerida, motivo_sugerida) VALUES (?, ?, 0, 0, "")');
        $ins->bind_param('ii', $imagemId, $novoHistoricoId);
    }
    if (!$ins || !$ins->execute()) {
        throw new Exception('Erro ao preparar novo angulo.');
    }
    $ins->close();

    $publicacao = fr_troca_publicar_angulo($conn, $ctx, $novoHistorico, $observacao, $idcolaboradorSessao ?: (int)$ctx['colaborador_id'], $logs);
    if (empty($publicacao['success'])) {
        throw new Exception($publicacao['message'] ?? 'Erro ao publicar novo angulo.');
    }

    $motivoSubstituido = 'Substituido por troca de angulo em ' . date('Y-m-d H:i:s');
    if ($observacao !== '') {
        $motivoSubstituido .= ' - ' . $observacao;
    }
    if ($upOld = $conn->prepare('UPDATE angulos_imagens SET liberada = 0, sugerida = 0, motivo_sugerida = ? WHERE id = ?')) {
        $oldId = (int)$old['id'];
        $upOld->bind_param('si', $motivoSubstituido, $oldId);
        if (!$upOld->execute()) {
            throw new Exception('Erro ao desmarcar angulo anterior.');
        }
        $upOld->close();
    }

    if ($entregaItemId !== null) {
        $upNew = $conn->prepare('UPDATE angulos_imagens SET entrega_item_id = ?, liberada = 1, sugerida = 0, motivo_sugerida = "" WHERE imagem_id = ? AND historico_id = ?');
        $upNew->bind_param('iii', $entregaItemId, $imagemId, $novoHistoricoId);
    } else {
        $upNew = $conn->prepare('UPDATE angulos_imagens SET liberada = 1, sugerida = 0, motivo_sugerida = "" WHERE imagem_id = ? AND historico_id = ?');
        $upNew->bind_param('ii', $imagemId, $novoHistoricoId);
    }
    if (!$upNew || !$upNew->execute()) {
        throw new Exception('Erro ao marcar novo angulo definitivo.');
    }
    $upNew->close();

    $statusAnterior = 'Angulo definitivo';
    $statusNovo = 'Angulo definitivo alterado';
    $respHist = $idcolaboradorSessao > 0 ? $idcolaboradorSessao : (int)$ctx['colaborador_id'];
    if ($insHist = $conn->prepare('INSERT INTO historico_aprovacoes (funcao_imagem_id, status_anterior, status_novo, colaborador_id, responsavel) VALUES (?, ?, ?, ?, ?)')) {
        $colabHist = (int)$ctx['colaborador_id'];
        $insHist->bind_param('issii', $funcaoImagemId, $statusAnterior, $statusNovo, $colabHist, $respHist);
        if (!$insHist->execute()) {
            throw new Exception('Erro ao inserir historico da troca.');
        }
        $insHist->close();
    }

    $conn->commit();

    $response = [
        'success' => true,
        'message' => 'Angulo definitivo trocado com sucesso.',
        'nas_path' => $publicacao['nas_path'] ?? null,
        'nome_interno' => $publicacao['nome_interno'] ?? null,
        'vps_inserido' => !empty($publicacao['vps_inserido']),
    ];
    if ($debug) {
        $response['debug'] = $logs;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $conn->rollback();
    $response = ['success' => false, 'message' => $e->getMessage()];
    if ($debug) {
        $response['debug'] = $logs;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

$conn->close();
