<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$allowedEditors = [1, 2, 9];
$userId = isset($_SESSION['idusuario']) ? intval($_SESSION['idusuario']) : 0;
if (!$userId || !in_array($userId, $allowedEditors, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para editar o handoff']);
    exit;
}

require '../conexao.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    // fallback: accept application/x-www-form-urlencoded
    $data = $_POST;
}

$obraId = isset($data['obra_id']) ? intval($data['obra_id']) : (isset($data['obraId']) ? intval($data['obraId']) : 0);
if (!$obraId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'obra_id inválido']);
    exit;
}

function toTinyIntOrNull($v) {
    if ($v === null) return null;
    $s = is_string($v) ? trim($v) : $v;
    if ($s === '' || $s === 'null') return null;
    if ($s === true || $s === '1' || $s === 1 || $s === 'true') return 1;
    if ($s === false || $s === '0' || $s === 0 || $s === 'false') return 0;
    return null;
}

function toIntOrNull($v) {
    if ($v === null) return null;
    $s = is_string($v) ? trim($v) : $v;
    if ($s === '' || $s === 'null') return null;
    return intval($s);
}

function toStrOrNull($v, $maxLen = 255) {
    if ($v === null) return null;
    $s = trim((string)$v);
    if ($s === '' || $s === 'null') return null;
    if (mb_strlen($s) > $maxLen) $s = mb_substr($s, 0, $maxLen);
    return $s;
}

function toDateOrNull($v) {
    if ($v === null) return null;
    $s = trim((string)$v);
    if ($s === '' || $s === 'null') return null;
    // expects YYYY-MM-DD
    return $s;
}

$payload = [
    // 1) Identificação
    'projeto_nome' => toStrOrNull($data['projeto_nome'] ?? null, 255),
    'projeto_tipo' => toStrOrNull($data['projeto_tipo'] ?? null, 50),
    'qtd_imagens_vendidas' => toIntOrNull($data['qtd_imagens_vendidas'] ?? null),
    'projeto_vitrine' => toTinyIntOrNull($data['projeto_vitrine'] ?? null),
    'responsavel_comercial' => toStrOrNull($data['responsavel_comercial'] ?? null, 255),
    'responsavel_producao' => toStrOrNull($data['responsavel_producao'] ?? null, 255),

    // 2) Escopo
    'escopo_fechado_validado' => toTinyIntOrNull($data['escopo_fechado_validado'] ?? null),
    'qtd_imagens_confirmada' => toIntOrNull($data['qtd_imagens_confirmada'] ?? null),
    'fotografico_aereo_incluso' => toTinyIntOrNull($data['fotografico_aereo_incluso'] ?? null),
    'fotografico_planejado_fluxo' => toTinyIntOrNull($data['fotografico_planejado_fluxo'] ?? null),
    'numero_revisoes' => toStrOrNull($data['numero_revisoes'] ?? null, 20),
    'limite_ajustes_definido' => toTinyIntOrNull($data['limite_ajustes_definido'] ?? null),
    'ajustes_permitidos' => toStrOrNull($data['ajustes_permitidos'] ?? null, 30),
    'entrega_antecipada' => toTinyIntOrNull($data['entrega_antecipada'] ?? null),
    'entrega_antecipada_quais' => toStrOrNull($data['entrega_antecipada_quais'] ?? null, 255),
    'entrega_antecipada_prazo' => toDateOrNull($data['entrega_antecipada_prazo'] ?? null),

    // 3) Prazos
    'prazo_final_prometido' => toDateOrNull($data['prazo_final_prometido'] ?? null),
    'datas_intermediarias' => toTinyIntOrNull($data['datas_intermediarias'] ?? null),
    'datas_intermediarias_info' => toStrOrNull($data['datas_intermediarias_info'] ?? null, 255),
    'deadline_externo' => toTinyIntOrNull($data['deadline_externo'] ?? null),
    'deadline_tipo' => toStrOrNull($data['deadline_tipo'] ?? null, 30),
    'prazo_compativel_complexidade' => toTinyIntOrNull($data['prazo_compativel_complexidade'] ?? null),
    'entrega_antecipada_impacta_fluxo' => toTinyIntOrNull($data['entrega_antecipada_impacta_fluxo'] ?? null),

    // 4) Criativo
    'cuidado_criativo_acima_media' => toTinyIntOrNull($data['cuidado_criativo_acima_media'] ?? null),
    'nivel_liberdade_criativa' => toStrOrNull($data['nivel_liberdade_criativa'] ?? null, 10),
    'riscos_criativos_identificados' => toTinyIntOrNull($data['riscos_criativos_identificados'] ?? null),
    'riscos_criativos_quais' => toStrOrNull($data['riscos_criativos_quais'] ?? null, 255),
    'observacoes_criativas' => toStrOrNull($data['observacoes_criativas'] ?? null, 500),

    // 5) Comercial
    'desconto_relevante' => toTinyIntOrNull($data['desconto_relevante'] ?? null),
    'promessa_especifica' => toTinyIntOrNull($data['promessa_especifica'] ?? null),
    'promessa_especifica_texto' => toStrOrNull($data['promessa_especifica_texto'] ?? null, 255),
    'parcela_final_atrelada_entrega' => toTinyIntOrNull($data['parcela_final_atrelada_entrega'] ?? null),

    // 6) Dependências
    'arquivos_iniciais_entregues' => toTinyIntOrNull($data['arquivos_iniciais_entregues'] ?? null),
    'materiais_pendentes_cliente' => toTinyIntOrNull($data['materiais_pendentes_cliente'] ?? null),
    'materiais_pendentes_texto' => toStrOrNull($data['materiais_pendentes_texto'] ?? null, 255),
    'depende_terceiros' => toTinyIntOrNull($data['depende_terceiros'] ?? null),
    'terceiros_tipo' => toStrOrNull($data['terceiros_tipo'] ?? null, 30),
    'dependencias_registradas_fluxo' => toTinyIntOrNull($data['dependencias_registradas_fluxo'] ?? null),

    // 7) Reunião
    'reuniao_handoff_realizada' => toTinyIntOrNull($data['reuniao_handoff_realizada'] ?? null),
    'comercial_apresentou_projeto' => toTinyIntOrNull($data['comercial_apresentou_projeto'] ?? null),
    'producao_esclareceu_duvidas' => toTinyIntOrNull($data['producao_esclareceu_duvidas'] ?? null),
    'riscos_pontos_sensiveis_discutidos' => toTinyIntOrNull($data['riscos_pontos_sensiveis_discutidos'] ?? null),
    'decisoes_relevantes_registradas' => toTinyIntOrNull($data['decisoes_relevantes_registradas'] ?? null),
];

$cols = array_keys($payload);
$placeholders = implode(',', array_fill(0, count($cols), '?'));

$updateParts = [];
foreach ($cols as $c) {
    $updateParts[] = "$c = VALUES($c)";
}
$updateParts[] = "updated_by = VALUES(updated_by)";

$sql = "INSERT INTO handoff_comercial (obra_id, " . implode(',', $cols) . ", created_by, updated_by)
        VALUES (?, $placeholders, ?, ?)
        ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao preparar query', 'details' => $conn->error]);
    exit;
}

$types = 'i';
$values = [$obraId];

foreach ($cols as $c) {
    $v = $payload[$c];
    if ($v === null) {
        $types .= 's';
        $values[] = null;
        continue;
    }
    if (is_int($v)) {
        $types .= 'i';
        $values[] = $v;
    } else {
        $types .= 's';
        $values[] = (string)$v;
    }
}

$types .= 'ii';
$values[] = $userId;
$values[] = $userId;

// bind_param requires references; use call_user_func_array for compatibility
$bind = [$types];
foreach ($values as $k => $v) {
    $bind[] = $values[$k];
}
// Convert to references
$refs = [];
foreach ($bind as $k => $v) {
    $refs[$k] = &$bind[$k];
}
call_user_func_array([$stmt, 'bind_param'], $refs);

$ok = $stmt->execute();
if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar', 'details' => $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();

// Return fresh data with user names
$sqlFetch = "SELECT 
                hc.*,
                u1.nome_usuario AS created_by_name,
                u2.nome_usuario AS updated_by_name
            FROM handoff_comercial hc
            LEFT JOIN usuario u1 ON u1.idusuario = hc.created_by
            LEFT JOIN usuario u2 ON u2.idusuario = hc.updated_by
            WHERE hc.obra_id = ?
            LIMIT 1";
$stmt2 = $conn->prepare($sqlFetch);
$row = null;
if ($stmt2) {
    $stmt2->bind_param('i', $obraId);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $row = $res2 ? $res2->fetch_assoc() : null;
    $stmt2->close();
}

$conn->close();

echo json_encode(['success' => true, 'data' => $row]);
