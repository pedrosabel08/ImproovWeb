<?php
/**
 * scripts/replace_imagem_nome.php
 *
 * Uso (CLI):
 *   php scripts/replace_imagem_nome.php --find="RIO_WER" --replace="WER_RIO" [--dry-run] [--limit=1000] [--where="obra_id=123"]
 *
 * O script faz um preview (dry-run) e, se --dry-run não for passado, aplica a substituição
 * usando SQL (REPLACE) em `imagens_cliente_obra.imagem_nome`. Ele é escrito para ser reutilizável para
 * outras substituições futuras.
 */

// Somente por CLI
if (php_sapi_name() !== 'cli') {
    echo "Este script deve ser executado via linha de comando (php).\n";
    exit(1);
}

$opts = getopt('', ['find:', 'replace:', 'dry-run', 'limit::', 'where::', 'help']);

if (isset($opts['help']) || empty($opts['find']) || !array_key_exists('replace', $opts)) {
    echo "Uso:\n";
    echo "  php scripts/replace_imagem_nome.php --find=FIND --replace=REPLACE [--dry-run] [--limit=1000] [--where=\"obra_id=123\"]\n";
    echo "Opções:\n";
    echo "  --find     Texto a ser buscado em imagem_nome (obrigatório)\n";
    echo "  --replace  Texto substituto (obrigatório)\n";
    echo "  --dry-run  Não aplica alterações, só mostra contagem e amostra\n";
    echo "  --limit    Limita a amostra/atualização (padrão: todos)\n";
    echo "  --where    Condição SQL adicional (por ex: \"obra_id=123\")\n";
    exit(0);
}

$find = $opts['find'];
$replace = $opts['replace'];
$dryRun = isset($opts['dry-run']);
$limit = isset($opts['limit']) && intval($opts['limit']) > 0 ? intval($opts['limit']) : null;
$whereExtra = isset($opts['where']) ? $opts['where'] : '';

// conexao
require_once __DIR__ . '/../conexao.php'; // fornece $conn (mysqli)
if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "Erro: não foi possível obter conexão com o banco de dados (conexao.php).\n";
    exit(1);
}

// Monta cláusula WHERE segura para LIKE
$likeParam = '%' . $conn->real_escape_string($find) . '%';
$whereClauses = ["imagem_nome LIKE ?"];
if ($whereExtra !== '') {
    // nota: whereExtra é inserido raw — o usuário deve garantir segurança; pode melhorar para parsing futuro
    $whereClauses[] = "($whereExtra)";
}
$whereSql = implode(' AND ', $whereClauses);

// Contar quantos registros serão afetados
$countSql = "SELECT COUNT(*) as cnt FROM imagens_cliente_obra WHERE $whereSql";
$stmt = $conn->prepare($countSql);
if (!$stmt) {
    echo "Erro ao preparar contagem: " . $conn->error . "\n";
    exit(1);
}
$stmt->bind_param('s', $likeParam);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$total = intval($row['cnt'] ?? 0);
$stmt->close();

echo "Encontrados $total registros com imagem_nome contendo '$find'" . PHP_EOL;

if ($total === 0) {
    exit(0);
}

// Pegar amostra (limitada)
$sampleLimit = $limit ?? 20;
$sampleSql = "SELECT idimagens_cliente_obra, imagem_nome FROM imagens_cliente_obra WHERE $whereSql ORDER BY idimagens_cliente_obra ASC LIMIT " . intval($sampleLimit);
$stmt = $conn->prepare($sampleSql);
if (!$stmt) {
    echo "Erro ao preparar select de amostra: " . $conn->error . "\n";
    exit(1);
}
$stmt->bind_param('s', $likeParam);
$stmt->execute();
$res = $stmt->get_result();
echo "Amostra (antes => depois):\n";
while ($r = $res->fetch_assoc()) {
    $id = $r['idimagens_cliente_obra'];
    $before = $r['imagem_nome'];
    $after = str_replace($find, $replace, $before);
    echo "[$id] " . $before . "  =>  " . $after . PHP_EOL;
}
$stmt->close();

if ($dryRun) {
    echo "\nDry-run: nenhuma alteração será feita. Use sem --dry-run para aplicar.\n";
    exit(0);
}

// Se limit foi passado, vamos selecionar os ids e atualizar por chunks para aplicar o limit
$ids = [];
if ($limit !== null) {
    $idSql = "SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE $whereSql ORDER BY idimagens_cliente_obra ASC LIMIT " . intval($limit);
    $stmt = $conn->prepare($idSql);
    if (!$stmt) { echo "Erro ao preparar select ids: " . $conn->error . "\n"; exit(1); }
    $stmt->bind_param('s', $likeParam);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $ids[] = (int)$r['idimagens_cliente_obra'];
    $stmt->close();
} else {
    // sem limit, atualizaremos todos — para evitar problemas de IN gigantesco, processamos em chunks de 1000 ids
    $chunkSize = 1000;
    $offset = 0;
    do {
        $pagedSql = "SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE $whereSql ORDER BY idimagens_cliente_obra ASC LIMIT ?, ?";
        $stmt = $conn->prepare($pagedSql);
        if (!$stmt) { echo "Erro ao preparar paged select ids: " . $conn->error . "\n"; exit(1); }
        // bind: like param + offset + chunkSize
        $stmt->bind_param('sii', $likeParam, $offset, $chunkSize);
        $stmt->execute();
        $res = $stmt->get_result();
        $fetched = 0;
        while ($r = $res->fetch_assoc()) { $ids[] = (int)$r['idimagens_cliente_obra']; $fetched++; }
        $stmt->close();
        $offset += $chunkSize;
    } while ($fetched === $chunkSize);
}

if (count($ids) === 0) {
    echo "Nenhum id coletado para atualização (inconsistência).\n";
    exit(0);
}

// Atualizar em transação, por chunks para evitar query grande demais
$conn->begin_transaction();
$updatedTotal = 0;
$chunkSize = 500;
try {
    for ($i = 0; $i < count($ids); $i += $chunkSize) {
        $chunk = array_slice($ids, $i, $chunkSize);
        // preparar placeholders
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $types = str_repeat('i', count($chunk));
        // UPDATE usando REPLACE
        $sql = "UPDATE imagens_cliente_obra SET imagem_nome = REPLACE(imagem_nome, ?, ?) WHERE idimagens_cliente_obra IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('Erro ao preparar UPDATE: ' . $conn->error);
        // bind params: first two são strings (find, replace), depois os ids
        $bindParams = array_merge([$find, $replace], $chunk);
        $bindTypes = 'ss' . $types;
        $refs = [];
        foreach ($bindParams as $k => $v) $refs[$k] = &$bindParams[$k];
        array_unshift($refs, $bindTypes);
        call_user_func_array([$stmt, 'bind_param'], $refs);
        if (!$stmt->execute()) throw new Exception('Falha no execute UPDATE: ' . $stmt->error);
        $affected = $stmt->affected_rows;
        $updatedTotal += $affected;
        $stmt->close();
    }
    $conn->commit();
    echo "Atualização concluída: $updatedTotal registros alterados.\n";
} catch (Exception $e) {
    $conn->rollback();
    echo "Erro durante atualização: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
