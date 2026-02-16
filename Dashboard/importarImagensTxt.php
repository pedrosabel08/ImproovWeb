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

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sem conexão com o banco de dados.']);
    exit;
}

function remove_accents(string $s): string
{
    if ($s === '') return $s;

    // Prefer intl transliterator / normalizer when available (more reliable than iconv)
    if (function_exists('transliterator_transliterate')) {
        // Decompose + remove diacritics
        $t = @transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $s);
        if (is_string($t) && $t !== '') {
            return $t;
        }
    }

    if (class_exists('Normalizer')) {
        $d = Normalizer::normalize($s, Normalizer::FORM_D);
        if (is_string($d) && $d !== '') {
            // Remove combining marks
            $d = preg_replace('/\p{Mn}+/u', '', $d);
            $c = Normalizer::normalize($d, Normalizer::FORM_C);
            if (is_string($c) && $c !== '') {
                return $c;
            }
            return $d;
        }
    }

    // Tenta iconv (mais comum)
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($converted !== false && $converted !== '') {
            return $converted;
        }
    }

    // Fallback básico
    $map = [
        'Á' => 'A',
        'À' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'A',
        'á' => 'a',
        'à' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'a',
        'É' => 'E',
        'È' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'é' => 'e',
        'è' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'Í' => 'I',
        'Ì' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'í' => 'i',
        'ì' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'Ó' => 'O',
        'Ò' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'O',
        'ó' => 'o',
        'ò' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        'Ú' => 'U',
        'Ù' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'ú' => 'u',
        'ù' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'Ç' => 'C',
        'ç' => 'c',
    ];
    return strtr($s, $map);
}

function sanitize_text(string $s): string
{
    $s = trim($s);
    if ($s === '') return '';
    $s = remove_accents($s);
    // Regra: remove '/' (caractere especial); demais caracteres especiais são removidos
    $s = str_replace('/', '-', $s);
    $s = preg_replace('/[^A-Za-z0-9 \._\-]/', '', $s);
    // Normaliza espaços e underscores
    $s = preg_replace('/\s+/', ' ', $s);
    $s = preg_replace('/_+/', '_', $s);
    // Remove espaços ao redor de underscores (ex: "a / b" -> "a_b")
    $s = preg_replace('/\s*_\s*/', '_', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

function normalize_for_search(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    $s = remove_accents($s);
    return $s;
}

function detect_tipo_imagem(string $imagem_nome): string
{
    if (trim($imagem_nome) === '') return '';

    $s = normalize_for_search($imagem_nome);

    if (strpos($s, 'planta humanizada') !== false) return 'Planta Humanizada';
    if (strpos($s, 'piscina aquecida') !== false) return 'Imagem Interna';

    foreach (['fotomontagem', 'fachada', 'embasamento', 'foto insercao'] as $kw) {
        if (strpos($s, $kw) !== false) return 'Fachada';
    }

    foreach (['living', 'suite', 'suíte', 'teraco', 'terraço', 'duplex', 'quarto', 'sacada', 'varanda', 'apartamentos'] as $kw) {
        $kwN = normalize_for_search($kw);
        if (strpos($s, $kwN) !== false) return 'Unidade';
    }

    foreach (['academia', 'hall de entrada', 'salao de jogos', 'salon de jogos', 'salao de festas', 'salon de festas', 'saloes de festas', 'festas', 'jogos', 'coworking', 'lavanderia', 'gourmet', 'interno', 'grill', 'garagem', 'brinquedoteca', 'bistro', 'cinema', 'sauna', 'sala de massagem', 'espaco kids', 'pizza', 'grab and go', 'bwc', 'home market', 'lobby', 'espaço pet', 'fitness', 'espaco pet', 'pub', 'sports bar'] as $kw) {
        if (strpos($s, $kw) !== false) return 'Imagem Interna';
    }

    foreach (['piscina', 'playground', 'externo', 'quadra', 'lazer', 'fire place'] as $kw) {
        if (strpos($s, $kw) !== false) return 'Imagem Externa';
    }

    return '';
}

function format_name(string $imagem_nome, string $nomenclatura): string
{
    $imagem_nome = trim($imagem_nome);
    // Normaliza observação no final: "X (ambiente interno)" -> "X - ambiente interno"
    if (preg_match('/^(.*)\(([^)]*)\)\s*$/u', $imagem_nome, $m)) {
        $prefix = trim($m[1]);
        $inside = trim($m[2]);
        if ($inside !== '') {
            $imagem_nome = ($prefix !== '' ? ($prefix . ' - ' . $inside) : $inside);
        } else {
            $imagem_nome = $prefix;
        }
    }
    $nomenclatura = sanitize_text($nomenclatura);

    if ($nomenclatura === '') {
        return sanitize_text($imagem_nome);
    }

    if (preg_match('/^(\d+\.)\s*(.*)$/', $imagem_nome, $m)) {
        $prefix = $m[1];
        $rest = sanitize_text($m[2]);
        if ($rest !== '') return $prefix . $nomenclatura . ' ' . $rest;
        return $prefix . $nomenclatura;
    }

    $rest = sanitize_text($imagem_nome);
    if ($rest !== '') return $nomenclatura . ' ' . $rest;
    return $nomenclatura;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (!isset($_FILES['txtFile']) || !is_uploaded_file($_FILES['txtFile']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Envie um arquivo TXT.']);
    exit;
}

$clienteId = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
$obraId = isset($_POST['obra_id']) ? (int)$_POST['obra_id'] : 0;
$nomenclaturaOverride = isset($_POST['nomenclatura']) ? (string)$_POST['nomenclatura'] : '';

$tmp = $_FILES['txtFile']['tmp_name'];
$content = file_get_contents($tmp);
if ($content === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Não foi possível ler o arquivo.']);
    exit;
}

// Normaliza encoding (tenta UTF-8)
if (function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')) {
    $enc = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($enc && $enc !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $enc);
    }
}

$linesRaw = preg_split("/\r\n|\n|\r/", $content);
$lines = [];
foreach ($linesRaw as $line) {
    $line = trim((string)$line);
    if ($line === '') continue;
    if (isset($line[0]) && $line[0] === '#') continue;
    $lines[] = $line;
}

if (count($lines) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Arquivo vazio.']);
    exit;
}

// Detecta header: "cliente_id,obra_id" (apenas 2 números)
$firstParts = preg_split('/[\t,;]+/', $lines[0]);
$firstParts = array_values(array_filter(array_map('trim', $firstParts), fn($p) => $p !== ''));
$useHeader = (count($firstParts) === 2 && ctype_digit($firstParts[0]) && ctype_digit($firstParts[1]));

$offset = 0;
if ($useHeader) {
    if ($clienteId <= 0) $clienteId = (int)$firstParts[0];
    if ($obraId <= 0) $obraId = (int)$firstParts[1];
    $offset = 1;
}

if ($obraId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da obra inválido.']);
    exit;
}

// Busca nomenclatura
$nomenclatura = '';
$obraClienteId = 0;
$stmtNom = $conn->prepare('SELECT nomenclatura, cliente FROM obra WHERE idobra = ? LIMIT 1');
if ($stmtNom) {
    $stmtNom->bind_param('i', $obraId);
    if ($stmtNom->execute()) {
        $res = $stmtNom->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $nomenclatura = (string)($row['nomenclatura'] ?? '');
            $obraClienteId = (int)($row['cliente'] ?? 0);
        }
    }
    $stmtNom->close();
}

// Se possível, usa o cliente da obra (evita importar com cliente errado)
if ($obraClienteId > 0) {
    $clienteId = $obraClienteId;
}

if ($clienteId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Não foi possível obter o cliente da obra.']);
    exit;
}
if (trim($nomenclatura) === '' && trim($nomenclaturaOverride) !== '') {
    $nomenclatura = $nomenclaturaOverride;
}

$nomenclatura = sanitize_text($nomenclatura);
if ($nomenclatura === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Não foi possível obter a nomenclatura da obra.']);
    exit;
}

// Prepared insert (mesmo padrão de Dashboard/inserir_imagem.php)
$sql = "INSERT INTO imagens_cliente_obra (cliente_id, obra_id, imagem_nome, recebimento_arquivos, data_inicio, prazo, tipo_imagem, antecipada, animacao, clima, dias_trabalhados)
        VALUES (?, ?, ?, NULL, NULL, NULL, ?, 0, 0, '', 0)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao preparar insert: ' . $conn->error]);
    exit;
}

$inseridas = 0;
$erros = [];

for ($i = $offset; $i < count($lines); $i++) {
    $line = $lines[$i];

    // Suporta também formato por linha: cliente_id,obra_id,imagem_nome
    $parts = preg_split('/[\t,;]+/', $line, 3);
    $parts = array_values($parts);

    $imagemRaw = $line;
    if (count($parts) >= 3 && ctype_digit(trim($parts[0])) && ctype_digit(trim($parts[1]))) {
        // Se o arquivo veio com ids por linha, pegamos só o nome (IDs ficam os do formulário quando enviados)
        $imagemRaw = (string)$parts[2];
    }

    $imagemNome = format_name($imagemRaw, $nomenclatura);
    if ($imagemNome === '') {
        $erros[] = ['linha' => $i + 1, 'erro' => 'Nome de imagem inválido'];
        continue;
    }

    $tipoImagem = detect_tipo_imagem($imagemNome);
    if ($tipoImagem === '') $tipoImagem = 'Desconhecido';

    $stmt->bind_param('iiss', $clienteId, $obraId, $imagemNome, $tipoImagem);
    if (!$stmt->execute()) {
        $erros[] = ['linha' => $i + 1, 'erro' => $stmt->error];
        continue;
    }

    $inseridas++;
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'inseridas' => $inseridas,
    'erros' => $erros,
    'message' => 'Importação concluída.'
]);
