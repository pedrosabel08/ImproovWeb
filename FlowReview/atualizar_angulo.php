<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/secure_env.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

include_once __DIR__ . '/../conexao.php';

$logs = [];
$slackToken = getenv('SLACK_TOKEN') ?: null;

try {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        if (class_exists('Dotenv\\Dotenv')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
            $dotenv->safeLoad();
            $slackToken = $_ENV['SLACK_TOKEN'] ?? $slackToken;
        }
    }
} catch (Throwable $e) {
    $logs[] = 'dotenv_error=' . $e->getMessage();
}

function normalize_name($s)
{
    if (!$s) {
        return '';
    }
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    $s = strtolower((string)$s);
    $s = preg_replace('/[^a-z0-9\s]/', '', $s);
    $s = preg_replace('/\s+/', ' ', trim($s));
    return $s;
}

function slack_post_message($token, $channel, $text, &$logs)
{
    if (!$token || !$channel) {
        $logs[] = 'slack_skip_missing_token_or_channel';
        return false;
    }

    $payload = ['channel' => $channel, 'text' => $text];
    $ch = curl_init('https://slack.com/api/chat.postMessage');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $resp = curl_exec($ch);
    if (curl_errno($ch)) {
        $logs[] = 'slack_curl_error=' . curl_error($ch);
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    $data = json_decode((string)$resp, true);
    if (!is_array($data) || empty($data['ok'])) {
        $logs[] = 'slack_not_ok=' . (is_array($data) ? ($data['error'] ?? 'unknown') : 'invalid_json');
        return false;
    }

    $logs[] = 'slack_ok';
    return true;
}

function resolve_slack_user_id_by_colaborador($conn, $colaborador_id, $token, &$logs)
{
    $nomeSlack = null;
    $nomeColab = null;

    if ($st = $conn->prepare('SELECT nome_slack FROM usuario WHERE idcolaborador = ? LIMIT 1')) {
        $st->bind_param('i', $colaborador_id);
        $st->execute();
        $st->bind_result($nomeSlack);
        $st->fetch();
        $st->close();
    }

    if ($st2 = $conn->prepare('SELECT nome_colaborador FROM colaborador WHERE idcolaborador = ? LIMIT 1')) {
        $st2->bind_param('i', $colaborador_id);
        $st2->execute();
        $st2->bind_result($nomeColab);
        $st2->fetch();
        $st2->close();
    }

    $nomeSlack = trim((string)$nomeSlack);
    if ($nomeSlack !== '' && preg_match('/^U[A-Z0-9]+$/', $nomeSlack)) {
        return $nomeSlack;
    }

    $target = $nomeSlack !== '' ? $nomeSlack : (string)$nomeColab;
    $targetNorm = normalize_name($target);
    if (!$token || $targetNorm === '') {
        return null;
    }

    $ch = curl_init('https://slack.com/api/users.list');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    if (curl_errno($ch)) {
        $logs[] = 'slack_users_list_curl_error=' . curl_error($ch);
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $data = json_decode((string)$resp, true);
    if (!is_array($data) || empty($data['ok']) || !is_array($data['members'] ?? null)) {
        return null;
    }

    foreach ($data['members'] as $m) {
        $candidates = [];
        if (!empty($m['real_name'])) $candidates[] = $m['real_name'];
        if (!empty($m['profile']['real_name_normalized'])) $candidates[] = $m['profile']['real_name_normalized'];
        if (!empty($m['profile']['display_name'])) $candidates[] = $m['profile']['display_name'];
        if (!empty($m['profile']['display_name_normalized'])) $candidates[] = $m['profile']['display_name_normalized'];

        foreach ($candidates as $cand) {
            if (normalize_name($cand) === $targetNorm) {
                return $m['id'] ?? null;
            }
        }
    }

    return null;
}

function sanitize_dir_name($str)
{
    $str = preg_replace(['/[áàãâä]/ui', '/[éèêë]/ui', '/[íìîï]/ui', '/[óòõôö]/ui', '/[úùûü]/ui', '/[ç]/ui'], ['a', 'e', 'i', 'o', 'u', 'c'], (string)$str);
    $str = preg_replace('/[^A-Za-z0-9 _-]/', '', $str);
    $str = preg_replace('/\s+/', '_', trim($str));
    return $str;
}

function resolve_categoria_dir($sftp, $pastaBase, $categoriaNome)
{
    $candidates = [
        $categoriaNome,
        str_replace(' ', '', $categoriaNome),
        str_replace(' ', '_', $categoriaNome),
        sanitize_dir_name($categoriaNome),
    ];

    foreach ($candidates as $cand) {
        $path = rtrim($pastaBase, '/') . '/' . $cand;
        if ($sftp->is_dir($path)) {
            return $cand;
        }
    }

    $safe = sanitize_dir_name($categoriaNome);
    $pathSafe = rtrim($pastaBase, '/') . '/' . $safe;
    if (!$sftp->is_dir($pathSafe)) {
        $sftp->mkdir($pathSafe, 0777, true);
    }

    return $safe;
}

function buscar_pasta_base_sftp($sftp, $conn, $obra_id)
{
    $nomen = null;
    if ($st = $conn->prepare('SELECT nomenclatura FROM obra WHERE idobra = ? LIMIT 1')) {
        $st->bind_param('i', $obra_id);
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
    $bases = array_values(array_unique($bases));
    foreach ($bases as $base) {
        $candidate = $base . '/' . $nomen . '/05.Exchange/01.Input';
        if ($sftp->is_dir($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function sftp_put_with_fallback($sftp, $remotePath, $localPath, &$logs)
{
    try {
        if ($sftp->put($remotePath, $localPath, phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE)) {
            return true;
        }
    } catch (Throwable $e) {
        $logs[] = 'sftp_put_local_exception=' . $e->getMessage();
    }

    if (file_exists($localPath) && is_readable($localPath)) {
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

function resolver_arquivo_angulo_local($pathAngulo, &$logs)
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
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $basePrefix = (strpos($requestUri, '/flow/ImproovWeb/') !== false) ? '/flow/ImproovWeb/' : '/ImproovWeb/';

    $candidateUrls = [];
    if ($host !== '') {
        $candidateUrls[] = $scheme . '://' . $host . $basePrefix . $relative;
    }
    $candidateUrls[] = 'https://improov.com.br/flow/ImproovWeb/' . $relative;
    if (preg_match('/^https?:\/\//i', (string)$pathAngulo)) {
        $candidateUrls[] = (string)$pathAngulo;
    }

    $content = false;
    $usedUrl = '';
    foreach (array_values(array_unique($candidateUrls)) as $urlRaw) {
        $url = str_replace(' ', '%20', $urlRaw);
        $content = false;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $content = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (curl_errno($ch) || $httpCode >= 400 || $content === false) {
                $logs[] = 'download_angle_curl_failed=' . $url . '|' . (curl_errno($ch) ? curl_error($ch) : ('http_' . $httpCode));
                $content = false;
            }
            curl_close($ch);
        }

        if ($content === false) {
            $context = stream_context_create(['http' => ['timeout' => 20]]);
            $content = @file_get_contents($url, false, $context);
        }

        if ($content !== false && strlen((string)$content) > 0) {
            $usedUrl = $url;
            break;
        }
    }

    if ($content === false || strlen((string)$content) === 0) {
        @unlink($tmpPath);
        $logs[] = 'download_angle_failed_all_urls';
        return [null, null];
    }

    if (@file_put_contents($tmpPath, $content) === false) {
        @unlink($tmpPath);
        $logs[] = 'tmp_file_write_failed';
        return [null, null];
    }

    $logs[] = 'angle_downloaded_tmp=' . $usedUrl;
    return [$tmpPath, $tmpPath];
}

function resolver_nome_arquivo_angulo($pathAngulo, $localPath)
{
    $fromPath = basename(str_replace('\\', '/', (string)$pathAngulo));
    if ($fromPath !== '' && strpos($fromPath, '.') !== false) {
        return $fromPath;
    }

    return basename((string)$localPath);
}

function sanitize_nome_padrao_token($value)
{
    $value = sanitize_dir_name((string)$value);
    $value = strtoupper(str_replace(' ', '_', $value));
    $value = preg_replace('/[^A-Z0-9_-]/', '', $value);
    return trim((string)$value, '_-');
}

function resolver_extensao_arquivo($pathAngulo, $localPath)
{
    $ext = strtolower((string)pathinfo((string)$pathAngulo, PATHINFO_EXTENSION));
    if ($ext !== '') {
        return $ext;
    }

    $extLocal = strtolower((string)pathinfo((string)$localPath, PATHINFO_EXTENSION));
    if ($extLocal !== '') {
        return $extLocal;
    }

    return 'jpeg';
}

function proxima_versao_nome_padrao($sftp, $destDir, $baseNome)
{
    $max = 0;
    $list = @$sftp->nlist($destDir);
    if (!is_array($list)) {
        return 1;
    }

    $regex = '/^' . preg_quote($baseNome, '/') . '-v(\d+)\.[A-Za-z0-9]+$/i';
    foreach ($list as $item) {
        $name = basename((string)$item);
        if ($name === '.' || $name === '..') {
            continue;
        }
        if (preg_match($regex, $name, $m)) {
            $v = (int)($m[1] ?? 0);
            if ($v > $max) {
                $max = $v;
            }
        }
    }

    return $max + 1;
}

function gerar_nome_padrao_angulo($sftp, $destDir, $nomenclatura, $tipoImagem, $indiceEnvio, $ext)
{
    $nomen = sanitize_nome_padrao_token($nomenclatura);
    if ($nomen === '') {
        $nomen = 'OBRA';
    }

    $sufixo = sanitize_nome_padrao_token($tipoImagem);
    $envio = sprintf('env%02d', max(1, (int)$indiceEnvio));

    $base = $nomen . '-ANG-IMG';
    if ($sufixo !== '') {
        $base .= '-' . $sufixo;
    }
    $base .= '-' . $envio;

    $versao = proxima_versao_nome_padrao($sftp, $destDir, $base);
    return [$base . '-v' . $versao . '.' . strtolower($ext), $versao];
}

function ensure_ftp_dir($ftp, $path, &$logs)
{
    $parts = array_filter(explode('/', $path), 'strlen');
    $cur = '/';
    $orig = @ftp_pwd($ftp);

    foreach ($parts as $p) {
        $cur = rtrim($cur, '/') . '/' . $p;
        if (@ftp_chdir($ftp, $cur) === false) {
            if (@ftp_mkdir($ftp, $cur) === false) {
                $logs[] = 'ftp_mkdir_failed=' . $cur;
                if ($orig) {
                    @ftp_chdir($ftp, $orig);
                }
                return false;
            }
            $logs[] = 'ftp_dir_created=' . $cur;
        }
    }

    if ($orig) {
        @ftp_chdir($ftp, $orig);
    }

    return true;
}

function carregar_config_sftp_vps(&$logs)
{
    $cfgPath = dirname(__DIR__) . '/.vscode/sftp.json';
    if (is_file($cfgPath)) {
        $json = @file_get_contents($cfgPath);
        $cfg = json_decode((string)$json, true);
        if (is_array($cfg) && !empty($cfg['host']) && !empty($cfg['username']) && !empty($cfg['remotePath'])) {
            return [
                'host' => (string)$cfg['host'],
                'port' => (int)($cfg['port'] ?? 22),
                'username' => (string)$cfg['username'],
                'password' => (string)($cfg['password'] ?? ''),
                'remotePath' => rtrim((string)$cfg['remotePath'], '/'),
            ];
        }
        $logs[] = 'sftp_json_invalid';
    } else {
        $logs[] = 'sftp_json_not_found';
    }

    try {
        $vpsCfg = improov_sftp_config('IMPROOV_VPS_SFTP');
        return [
            'host' => (string)$vpsCfg['host'],
            'port' => (int)$vpsCfg['port'],
            'username' => (string)$vpsCfg['user'],
            'password' => (string)$vpsCfg['pass'],
            'remotePath' => rtrim((string)improov_env('IMPROOV_VPS_SFTP_REMOTE_PATH'), '/'),
        ];
    } catch (RuntimeException $e) {
        $logs[] = 'vps_sftp_env_missing';
    }

    return [
        'host' => '',
        'port' => 22,
        'username' => '',
        'password' => '',
        'remotePath' => '',
    ];
}

function ensure_sftp_dir_recursive($sftp, $path, &$logs)
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
            $logs[] = 'sftp_dir_created=' . $cur;
        }
    }
    return true;
}

function enviar_angulo_para_vps($localPath, $nomenclatura, $categoriaDir, $tipoImagem, $nomeImagemDir, $fileName, &$logs)
{
    $cfg = carregar_config_sftp_vps($logs);
    if (empty($cfg['host']) || empty($cfg['username']) || empty($cfg['password']) || empty($cfg['remotePath'])) {
        $logs[] = 'vps_sftp_config_invalid';
        return ['remote' => false, 'local' => false];
    }

    $targetDirRel = '/uploads/angulo_definido/' . sanitize_dir_name((string)$nomenclatura) . '/' . $categoriaDir . '/' . $tipoImagem . '/IMG/' . $nomeImagemDir;
    $localFallbackOk = false;

    $sftp = new phpseclib3\Net\SFTP((string)$cfg['host'], (int)$cfg['port']);
    if (!$sftp->login((string)$cfg['username'], (string)$cfg['password'])) {
        $logs[] = 'vps_sftp_login_failed';
    } else {
        $base = rtrim((string)$cfg['remotePath'], '/');
        $targetDir = $base . $targetDirRel;
        if (!ensure_sftp_dir_recursive($sftp, $targetDir, $logs)) {
            $logs[] = 'vps_sftp_dir_failed=' . $targetDir;
        } else {
            $oldDir = rtrim($targetDir, '/') . '/OLD';
            if (!ensure_sftp_dir_recursive($sftp, $oldDir, $logs)) {
                $logs[] = 'vps_sftp_old_dir_failed=' . $oldDir;
            } else {
                $targetFile = rtrim($targetDir, '/') . '/' . $fileName;
                if ($sftp->file_exists($targetFile)) {
                    $backup = $oldDir . '/' . pathinfo($fileName, PATHINFO_FILENAME) . '_' . date('Ymd_His') . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
                    $sftp->rename($targetFile, $backup);
                }

                $ok = sftp_put_with_fallback($sftp, $targetFile, $localPath, $logs);
                if ($ok) {
                    if (method_exists($sftp, 'chmod')) {
                        @$sftp->chmod(0777, $targetFile);
                    }
                    $logs[] = 'vps_sftp_upload_ok=' . $targetFile;
                    return ['remote' => true, 'local' => false];
                }

                $logs[] = 'vps_sftp_put_failed=' . $targetFile;
            }
        }
    }

    // fallback local APENAS se solicitado explicitamente
    $allowLocalFallback = isset($_GET['vps_local_fallback']) && (string)$_GET['vps_local_fallback'] === '1';
    if ($allowLocalFallback) {
        $localBase = dirname(__DIR__);
        $localTargetDir = rtrim($localBase, '/') . str_replace('/', DIRECTORY_SEPARATOR, $targetDirRel);
        if (!is_dir($localTargetDir) && !@mkdir($localTargetDir, 0777, true)) {
            $logs[] = 'vps_local_mkdir_failed=' . $localTargetDir;
        } else {
            $localTargetFile = rtrim($localTargetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
            if (@copy($localPath, $localTargetFile)) {
                $localFallbackOk = true;
                $logs[] = 'vps_local_copy_ok=' . $localTargetFile;
            } else {
                $logs[] = 'vps_local_copy_failed=' . $localTargetFile;
            }
        }
    } else {
        $logs[] = 'vps_local_fallback_disabled';
    }

    return ['remote' => false, 'local' => $localFallbackOk];
}

function buscar_tipo_imagem_id_por_nome($conn, $nomeTipo)
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

function inserir_arquivo_angulo_no_banco($conn, $obraId, $tipoImagemNome, $imagemId, $nomeOriginal, $nomeInterno, $caminhoRemoto, $versao, $localPath, $observacao, $colaboradorId, &$logs)
{
    $tipoImagemId = buscar_tipo_imagem_id_por_nome($conn, $tipoImagemNome);
    if ($tipoImagemId <= 0) {
        $logs[] = 'tipo_imagem_id_not_found=' . $tipoImagemNome;
        return false;
    }

    $tipo = 'IMG';
    $categoriaId = 7;
    $origem = 'upload_web';
    $recebidoPor = 'sistema';
    $status = 'atualizado';
    $sufixo = '';
    $descricao = trim((string)$observacao) !== '' ? trim((string)$observacao) : 'Ângulo definido via Flow Review';
    $tamanho = (is_file($localPath) ? (string)filesize($localPath) : '0');
    $recebidoEm = date('Y-m-d H:i:s');
    $colabBind = ((int)$colaboradorId > 0) ? (int)$colaboradorId : null;

    $sql = "INSERT INTO arquivos
        (obra_id, tipo_imagem_id, imagem_id, nome_original, nome_interno, caminho, tipo, versao, status, origem, recebido_por, recebido_em, categoria_id, sufixo, descricao, tamanho, colaborador_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($st = $conn->prepare($sql)) {
        $st->bind_param(
            'iiissssissssisssi',
            $obraId,
            $tipoImagemId,
            $imagemId,
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

function inserir_angulo_servidor_clientes($conn, $imagem_id, $historico_id, $pathAngulo, $observacao, $colaboradorId, &$logs)
{
    if (!class_exists('phpseclib3\\Net\\SFTP')) {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
    }

    if (!class_exists('phpseclib3\\Net\\SFTP')) {
        $logs[] = 'sftp_class_missing';
        return false;
    }

    list($localPath, $tmpToDelete) = resolver_arquivo_angulo_local($pathAngulo, $logs);
    if (!$localPath || !is_file($localPath)) {
        $logs[] = 'angle_source_not_found';
        return false;
    }

    $obraId = 0;
    $tipoImagem = '';
    $nomeImagem = '';
    if ($st = $conn->prepare('SELECT obra_id, tipo_imagem, imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ? LIMIT 1')) {
        $st->bind_param('i', $imagem_id);
        $st->execute();
        $st->bind_result($obraId, $tipoImagem, $nomeImagem);
        $st->fetch();
        $st->close();
    }

    if (!$obraId) {
        $logs[] = 'obra_not_found_for_imagem';
        return false;
    }

    try {
        $sftpCfg = improov_sftp_config();
    } catch (RuntimeException $e) {
        $logs[] = 'sftp_env_missing';
        return false;
    }
    $host = $sftpCfg['host'];
    $port = (int)$sftpCfg['port'];
    $user = $sftpCfg['user'];
    $pass = $sftpCfg['pass'];

    $sftp = new phpseclib3\Net\SFTP($host, $port);
    if (!$sftp->login($user, $pass)) {
        $logs[] = 'sftp_login_failed';
        return false;
    }

    $base = buscar_pasta_base_sftp($sftp, $conn, (int)$obraId);
    if (!$base) {
        $logs[] = 'base_cliente_not_found';
        return false;
    }

    $categoriaDir = resolve_categoria_dir($sftp, $base, 'Angulo definido');
    $tipoDir = trim((string)$tipoImagem) !== '' ? trim((string)$tipoImagem) : 'SemTipo';
    $nomeImagemDir = trim((string)$nomeImagem) !== '' ? sanitize_dir_name($nomeImagem) : ('imagem_' . (int)$imagem_id);
    $destDir = rtrim($base, '/') . '/' . $categoriaDir . '/' . $tipoDir . '/IMG/' . $nomeImagemDir;

    if (!$sftp->is_dir($destDir)) {
        $sftp->mkdir($destDir, 0777, true);
    }

    $oldDir = $destDir . '/OLD';
    if (!$sftp->is_dir($oldDir)) {
        $sftp->mkdir($oldDir, 0777, true);
    }

    $nomenclatura = '';
    if ($stN = $conn->prepare('SELECT nomenclatura FROM obra WHERE idobra = ? LIMIT 1')) {
        $stN->bind_param('i', $obraId);
        $stN->execute();
        $stN->bind_result($nomenclatura);
        $stN->fetch();
        $stN->close();
    }

    $indiceEnvio = 1;
    if ($stEnv = $conn->prepare('SELECT indice_envio FROM historico_aprovacoes_imagens WHERE id = ? LIMIT 1')) {
        $stEnv->bind_param('i', $historico_id);
        $stEnv->execute();
        $stEnv->bind_result($indiceEnvioDb);
        if ($stEnv->fetch() && $indiceEnvioDb !== null) {
            $indiceEnvio = (int)$indiceEnvioDb;
        }
        $stEnv->close();
    }

    $ext = resolver_extensao_arquivo($pathAngulo, $localPath);
    list($fileName, $versaoNome) = gerar_nome_padrao_angulo($sftp, $destDir, $nomenclatura, $tipoImagem, $indiceEnvio, $ext);
    $logs[] = 'nome_padrao_gerado=' . $fileName;
    $destFile = $destDir . '/' . $fileName;
    if ($sftp->file_exists($destFile)) {
        $backup = $oldDir . '/' . pathinfo($fileName, PATHINFO_FILENAME) . '_' . date('Ymd_His') . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
        $sftp->rename($destFile, $backup);
    }

    $ok = sftp_put_with_fallback($sftp, $destFile, $localPath, $logs);
    $vpsOk = false;
    if ($ok) {
        $logs[] = 'sftp_upload_ok=' . $destFile;

        $nomeOriginal = resolver_nome_arquivo_angulo($pathAngulo, $localPath);
        inserir_arquivo_angulo_no_banco(
            $conn,
            (int)$obraId,
            (string)$tipoImagem,
            (int)$imagem_id,
            (string)$nomeOriginal,
            (string)$fileName,
            (string)$destFile,
            (int)$versaoNome,
            (string)$localPath,
            (string)$observacao,
            (int)$colaboradorId,
            $logs
        );

        $vpsUploadResult = enviar_angulo_para_vps(
            (string)$localPath,
            (string)$nomenclatura,
            (string)$categoriaDir,
            (string)$tipoDir,
            (string)$nomeImagemDir,
            (string)$fileName,
            $logs
        );
        $vpsOk = is_array($vpsUploadResult) ? (bool)($vpsUploadResult['remote'] ?? false) : (bool)$vpsUploadResult;
        if (is_array($vpsUploadResult) && !empty($vpsUploadResult['local'])) {
            $logs[] = 'vps_local_only_success';
        }
    }

    if (!empty($tmpToDelete) && is_file($tmpToDelete)) {
        @unlink($tmpToDelete);
    }

    return ['clientes' => (bool)$ok, 'vps' => (bool)$vpsOk];
}

$data = json_decode(file_get_contents('php://input'), true);
$imagem_id = isset($data['imagem_id']) ? intval($data['imagem_id']) : 0;
$funcao_imagem_id = isset($data['funcao_imagem_id']) ? intval($data['funcao_imagem_id']) : 0;
$historico_id = isset($data['historico_id']) ? intval($data['historico_id']) : 0;
$acaoRaw = isset($data['acao']) ? trim((string)$data['acao']) : '';
$observacao = trim((string)($data['observacao'] ?? $data['motivo'] ?? ''));
$debug = isset($_GET['debug']) && (string)$_GET['debug'] === '1';

$mapAcao = [
    'aprovar' => 'escolhido',
    'ajuste' => 'ajustes',
    'escolhido' => 'escolhido',
    'escolhido_com_ajustes' => 'escolhido_com_ajustes',
    'ajustes' => 'ajustes',
];
$acao = $mapAcao[$acaoRaw] ?? '';

if (!$imagem_id || !$funcao_imagem_id || !$historico_id || $acao === '') {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

$responsavelColab = isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : 0;
if ($responsavelColab <= 0 && isset($_SESSION['idusuario'])) {
    $idusuarioSess = (int)$_SESSION['idusuario'];
    if ($idusuarioSess > 0 && ($stResp = $conn->prepare('SELECT idcolaborador FROM usuario WHERE idusuario = ? LIMIT 1'))) {
        $stResp->bind_param('i', $idusuarioSess);
        $stResp->execute();
        $stResp->bind_result($tmpColab);
        if ($stResp->fetch()) {
            $responsavelColab = (int)$tmpColab;
        }
        $stResp->close();
    }
}

$okHist = false;
if ($st = $conn->prepare('SELECT 1 FROM historico_aprovacoes_imagens WHERE id = ? AND funcao_imagem_id = ? LIMIT 1')) {
    $st->bind_param('ii', $historico_id, $funcao_imagem_id);
    $st->execute();
    $res = $st->get_result();
    $okHist = $res && $res->num_rows > 0;
    $st->close();
}
if (!$okHist) {
    echo json_encode(['success' => false, 'message' => 'Imagem/Histórico inválido para esta função.']);
    exit;
}

$funcao_id = 0;
$colaborador_id = 0;
$status_funcao_atual = '';
$imagem_nome = '';
$status_nome = '';
$path_angulo = '';

if (
    $st = $conn->prepare("SELECT f.funcao_id, f.colaborador_id, f.status, i.imagem_nome, s.nome_status
        FROM funcao_imagem f
        JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
        JOIN status_imagem s ON s.idstatus = i.status_id
        WHERE f.idfuncao_imagem = ? AND f.imagem_id = ?
        LIMIT 1")
) {
    $st->bind_param('ii', $funcao_imagem_id, $imagem_id);
    $st->execute();
    $st->bind_result($funcao_id, $colaborador_id, $status_funcao_atual, $imagem_nome, $status_nome);
    $st->fetch();
    $st->close();
}

if ((int)$funcao_id !== 4 || mb_strtolower(trim((string)$status_nome), 'UTF-8') !== 'p00') {
    echo json_encode(['success' => false, 'message' => 'Ação disponível apenas para P00 + Finalização.']);
    exit;
}

if ($st = $conn->prepare('SELECT imagem FROM historico_aprovacoes_imagens WHERE id = ? LIMIT 1')) {
    $st->bind_param('i', $historico_id);
    $st->execute();
    $st->bind_result($path_angulo);
    $st->fetch();
    $st->close();
}

if ($ins = $conn->prepare('INSERT IGNORE INTO angulos_imagens (imagem_id, historico_id, liberada, sugerida, motivo_sugerida) VALUES (?, ?, 0, 0, "")')) {
    $ins->bind_param('ii', $imagem_id, $historico_id);
    $ins->execute();
    $ins->close();
}

$statusNovo = '';
$mensagemSlack = '';
$statusIdR00 = 0;

if ($acao === 'ajustes') {
    $statusNovo = 'Ajuste';
    $mensagemSlack = "⚠️ Ajustes solicitados para o ângulo da imagem {$imagem_nome} (P00).";
} elseif ($acao === 'escolhido_com_ajustes') {
    $statusNovo = 'Aprovado com ajustes';
    $mensagemSlack = "✅ Ângulo escolhido com ajustes para a imagem {$imagem_nome} (P00).";
} else {
    $statusNovo = 'Não iniciado';
    $mensagemSlack = "✅ Ângulo escolhido para a imagem {$imagem_nome} (P00).";
}

if (in_array($acao, ['escolhido', 'escolhido_com_ajustes'], true)) {
    if ($stR00 = $conn->prepare('SELECT idstatus FROM status_imagem WHERE UPPER(nome_status) = "R00" LIMIT 1')) {
        $stR00->execute();
        $stR00->bind_result($tmpStatusR00);
        if ($stR00->fetch()) {
            $statusIdR00 = (int)$tmpStatusR00;
        }
        $stR00->close();
    }

    if ($statusIdR00 <= 0) {
        echo json_encode(['success' => false, 'message' => 'Status R00 não encontrado em status_imagem.']);
        exit;
    }
}

$conn->begin_transaction();
try {
    if ($acao === 'ajustes') {
        if ($up = $conn->prepare('UPDATE angulos_imagens SET liberada = 0, sugerida = 1, motivo_sugerida = ? WHERE imagem_id = ? AND historico_id = ?')) {
            $up->bind_param('sii', $observacao, $imagem_id, $historico_id);
            if (!$up->execute()) {
                throw new Exception('Erro ao salvar ajuste: ' . $up->error);
            }
            $up->close();
        }
    } elseif ($acao === 'escolhido_com_ajustes') {
        if ($up = $conn->prepare('UPDATE angulos_imagens SET liberada = 1, sugerida = 1, motivo_sugerida = ? WHERE imagem_id = ? AND historico_id = ?')) {
            $up->bind_param('sii', $observacao, $imagem_id, $historico_id);
            if (!$up->execute()) {
                throw new Exception('Erro ao salvar escolhido com ajustes: ' . $up->error);
            }
            $up->close();
        }
    } else {
        if ($up = $conn->prepare('UPDATE angulos_imagens SET liberada = 1, sugerida = 0, motivo_sugerida = "" WHERE imagem_id = ? AND historico_id = ?')) {
            $up->bind_param('ii', $imagem_id, $historico_id);
            if (!$up->execute()) {
                throw new Exception('Erro ao salvar escolhido: ' . $up->error);
            }
            $up->close();
        }
    }

    $statusAnterior = $status_funcao_atual;
    if ($stStatus = $conn->prepare('SELECT status FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1 FOR UPDATE')) {
        $stStatus->bind_param('i', $funcao_imagem_id);
        $stStatus->execute();
        $stStatus->bind_result($statusDb);
        if ($stStatus->fetch()) {
            $statusAnterior = $statusDb;
        }
        $stStatus->close();
    }

    if ($stFi = $conn->prepare('UPDATE funcao_imagem SET status = ? WHERE idfuncao_imagem = ?')) {
        $stFi->bind_param('si', $statusNovo, $funcao_imagem_id);
        if (!$stFi->execute()) {
            throw new Exception('Erro ao atualizar status da função: ' . $stFi->error);
        }
        $stFi->close();
    }

    if (in_array($acao, ['escolhido', 'escolhido_com_ajustes'], true)) {
        if ($stImg = $conn->prepare('UPDATE imagens_cliente_obra SET status_id = ? WHERE idimagens_cliente_obra = ?')) {
            $stImg->bind_param('ii', $statusIdR00, $imagem_id);
            if (!$stImg->execute()) {
                throw new Exception('Erro ao atualizar etapa da imagem para R00: ' . $stImg->error);
            }
            $stImg->close();
        }
    }

    $respHist = $responsavelColab > 0 ? $responsavelColab : (int)$colaborador_id;
    if ($respHist <= 0) {
        throw new Exception('Responsável inválido para histórico.');
    }

    if ($insHist = $conn->prepare('INSERT INTO historico_aprovacoes (funcao_imagem_id, status_anterior, status_novo, colaborador_id, responsavel) VALUES (?, ?, ?, ?, ?)')) {
        $sa = $statusAnterior ?? '';
        $colabHist = $colaborador_id ? (int)$colaborador_id : 0;
        $insHist->bind_param('issii', $funcao_imagem_id, $sa, $statusNovo, $colabHist, $respHist);
        if (!$insHist->execute()) {
            throw new Exception('Erro ao inserir histórico: ' . $insHist->error);
        }
        $insHist->close();
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$sftpInserido = false;
$vpsInserido = false;
if (in_array($acao, ['escolhido', 'escolhido_com_ajustes'], true)) {
    $resultUploads = inserir_angulo_servidor_clientes($conn, $imagem_id, $historico_id, $path_angulo, $observacao, $responsavelColab, $logs);
    $sftpInserido = is_array($resultUploads) ? (bool)($resultUploads['clientes'] ?? false) : (bool)$resultUploads;
    $vpsInserido = is_array($resultUploads) ? (bool)($resultUploads['vps'] ?? false) : false;
}

$uidSlack = null;
if ($colaborador_id) {
    $uidSlack = resolve_slack_user_id_by_colaborador($conn, (int)$colaborador_id, $slackToken, $logs);
}
if ($uidSlack) {
    $msg = $mensagemSlack;
    if ($observacao !== '') {
        $msg .= "\nObservação: {$observacao}";
    }
    slack_post_message($slackToken, $uidSlack, $msg, $logs);
}

$response = [
    'success' => true,
    'status' => $statusNovo,
    'sftp_inserido' => $sftpInserido,
    'vps_inserido' => $vpsInserido,
];
if ($debug) {
    $response['debug'] = $logs;
}

echo json_encode($response);
$conn->close();
