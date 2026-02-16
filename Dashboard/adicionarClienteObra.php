<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

// Opcional: restringe para nível 1
if (!isset($_SESSION['nivel_acesso']) || (int)$_SESSION['nivel_acesso'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit;
}

include '../conexao.php';

// Composer autoload (para phpseclib)
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Dependência ausente: vendor/autoload.php não encontrada.']);
    exit;
}
require_once $vendorAutoload;

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sem conexão com o banco de dados.']);
    exit;
}

function table_has_column(mysqli $conn, string $table, string $column): bool
{
    // MySQL não permite placeholders (?) em comandos SHOW COLUMNS.
    // Usamos INFORMATION_SCHEMA para checar existência de colunas com segurança.
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    $stmt->close();
    return $ok;
}

function ensure_remote_project_folder(string $nomenclatura): void
{
    // Dados SFTP/SSH (mesmos do uploadFinal.php)
    $ftp_user = 'flow';
    $ftp_pass = 'flow@2025';
    $ftp_host = 'imp-nas.ddns.net';
    $ftp_port = 2222;

    $templateBase = '/mnt/clientes/00.Cliente_Padrao';
    $year = date('Y');
    $yearBase = "/mnt/clientes/{$year}";
    $dest = $yearBase . '/' . $nomenclatura;

    // Segurança: nomenclatura vira nome de pasta (não pode ter espaços / barras)
    if (!preg_match('/^[A-Za-z0-9._-]{1,32}$/', $nomenclatura)) {
        throw new Exception('Nomenclatura inválida para criação de pasta. Use apenas letras, números, ponto, underscore ou hífen.');
    }

    $ssh = new \phpseclib3\Net\SSH2($ftp_host, $ftp_port);
    if (!$ssh->login($ftp_user, $ftp_pass)) {
        throw new Exception('Falha na autenticação SSH/SFTP no servidor de arquivos.');
    }

    // Cria /mnt/clientes/<ano>/<nomenclatura> e copia o template para dentro
    // - falha se destino já existir
    // - usa cp -a (server-side) para copiar diretórios e arquivos
    $cmd = [];
    $cmd[] = 'set -e';
    $cmd[] = 'umask 000';
    $cmd[] = 'mkdir -p ' . escapeshellarg($yearBase);
    $cmd[] = 'if [ -d ' . escapeshellarg($dest) . ' ]; then echo "DEST_EXISTS"; exit 2; fi';
    $cmd[] = 'if [ ! -d ' . escapeshellarg($templateBase) . ' ]; then echo "TEMPLATE_MISSING"; exit 3; fi';
    $cmd[] = 'mkdir -p ' . escapeshellarg($dest);
    // Use recursive copy without preserving original timestamps so copied files
    // receive the timestamp of the copy operation (creation time).
    $cmd[] = 'cp -r ' . escapeshellarg($templateBase . '/.') . ' ' . escapeshellarg($dest . '/');
    $cmd[] = 'echo "OK"';
    $out = $ssh->exec(implode('; ', $cmd));

    if (strpos($out, 'DEST_EXISTS') !== false) {
        throw new Exception('A pasta do projeto já existe no servidor.');
    }
    if (strpos($out, 'TEMPLATE_MISSING') !== false) {
        throw new Exception('Template não encontrado no servidor (/mnt/clientes/00.Cliente_Padrao).');
    }
    if (strpos($out, 'OK') === false) {
        throw new Exception('Falha ao criar/copiar a pasta do projeto no servidor.');
    }
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
    exit;
}

$clienteNome = isset($data['cliente']) ? trim((string)$data['cliente']) : '';
$clienteIdFromReq = isset($data['cliente_id']) ? intval($data['cliente_id']) : null;
$obraNome = isset($data['obra']) ? trim((string)$data['obra']) : '';
$nomenclatura = isset($data['nomenclatura']) ? trim((string)$data['nomenclatura']) : '';
$nomeReal = isset($data['nome_real']) ? trim((string)$data['nome_real']) : '';

// Respeitar tamanhos do schema
if (strlen($clienteNome) > 45) $clienteNome = substr($clienteNome, 0, 45);
if (strlen($obraNome) > 45) $obraNome = substr($obraNome, 0, 45);
if (strlen($nomenclatura) > 10) $nomenclatura = substr($nomenclatura, 0, 10);
if (strlen($nomeReal) > 100) $nomeReal = substr($nomeReal, 0, 100);

if ((is_null($clienteIdFromReq) && $clienteNome === '') || $obraNome === '' || $nomenclatura === '' || $nomeReal === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preencha cliente (ou selecione existente), obra, nomenclatura e nome real.']);
    exit;
}

try {
    $conn->begin_transaction();
    // 1) Determinar cliente: usar cliente_id enviado (se válido) ou inserir novo cliente
    if (!is_null($clienteIdFromReq) && $clienteIdFromReq > 0) {
        $check = $conn->prepare('SELECT idcliente FROM cliente WHERE idcliente = ? LIMIT 1');
        if (!$check) throw new Exception('Erro ao preparar verificação de cliente: ' . $conn->error);
        $check->bind_param('i', $clienteIdFromReq);
        $check->execute();
        $resCheck = $check->get_result();
        $exists = $resCheck && $resCheck->num_rows > 0;
        $check->close();
        if (!$exists) {
            throw new Exception('Cliente selecionado não existe.');
        }
        $clienteId = $clienteIdFromReq;
    } else {
        $stmtCliente = $conn->prepare('INSERT INTO cliente (nome_cliente) VALUES (?)');
        if (!$stmtCliente) {
            throw new Exception('Erro ao preparar INSERT cliente: ' . $conn->error);
        }
        $stmtCliente->bind_param('s', $clienteNome);
        if (!$stmtCliente->execute()) {
            throw new Exception('Erro ao inserir cliente: ' . $stmtCliente->error);
        }
        $clienteId = (int)$stmtCliente->insert_id;
        $stmtCliente->close();
    }

    // 2) Inserir obra conforme schema enviado
    // - obra.cliente (FK para cliente.idcliente)
    // - obra.nome_real
    // - obra.status_obra (0 = ativa, conforme uso em conexaoMain.php)
    $cols = ['nome_obra'];
    $placeholders = ['?'];
    $types = 's';
    $values = [$obraNome];

    if (table_has_column($conn, 'obra', 'nomenclatura')) {
        $cols[] = 'nomenclatura';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $nomenclatura;
    }

    if (table_has_column($conn, 'obra', 'nome_real')) {
        $cols[] = 'nome_real';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $nomeReal;
    }

    // coluna é "cliente" na sua base
    if (table_has_column($conn, 'obra', 'cliente')) {
        $cols[] = 'cliente';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $clienteId;
    }

    if (table_has_column($conn, 'obra', 'status_obra')) {
        $cols[] = 'status_obra';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = 0;
    }

    $sqlObra = 'INSERT INTO obra (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmtObra = $conn->prepare($sqlObra);
    if (!$stmtObra) {
        throw new Exception('Erro ao preparar INSERT obra: ' . $conn->error);
    }

    $stmtObra->bind_param($types, ...$values);

    if (!$stmtObra->execute()) {
        throw new Exception('Erro ao inserir obra: ' . $stmtObra->error);
    }

    $obraId = (int)$stmtObra->insert_id;
    $stmtObra->close();

    // 3) Criar estrutura de pastas no servidor (template -> /mnt/clientes/<ano>/<nomenclatura>)
    ensure_remote_project_folder($nomenclatura);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'cliente_id' => $clienteId,
        'obra_id' => $obraId,
        'message' => 'Cliente e obra criados com sucesso.'
    ]);
} catch (Throwable $e) {
    if ($conn && $conn->errno === 0) {
        // ignore
    }
    if ($conn) {
        $conn->rollback();
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
