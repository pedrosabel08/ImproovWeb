<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/alteracoes_helper.php';

function alt_json_error(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function alt_fetch_all(mysqli_stmt $stmt): array
{
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function alt_fetch_one(mysqli_stmt $stmt): ?array
{
    $rows = alt_fetch_all($stmt);
    return $rows[0] ?? null;
}

function alt_file_origin(array $row): string
{
    $raw = mb_strtolower(trim((string)($row['origem'] ?? '')), 'UTF-8');
    $cat = mb_strtolower(trim((string)($row['categoria_nome'] ?? '')), 'UTF-8');
    $desc = mb_strtolower(trim((string)($row['descricao'] ?? '')), 'UTF-8');
    $haystack = $raw . ' ' . $cat . ' ' . $desc;

    if (str_contains($haystack, 'triagem') || str_contains($haystack, 'pre-alt') || str_contains($haystack, 'pre alt')) {
        return 'triagem';
    }
    if (str_contains($haystack, 'intern')) {
        return 'interno';
    }
    if (str_contains($haystack, 'cliente') || str_contains($haystack, 'client')) {
        return 'cliente';
    }

    return 'cliente';
}

function alt_is_triage_file(array $row): bool
{
    return alt_file_origin($row) === 'triagem';
}

function alt_prepare_file(array $row, string $scope, ?string $origin = null): array
{
    $name = (string)($row['nome_interno'] ?? $row['nome_arquivo'] ?? $row['nome_original'] ?? '');
    if ($name === '' && !empty($row['caminho'])) {
        $parts = preg_split('/[\\\\\\/]+/', (string)$row['caminho']);
        $name = end($parts) ?: 'Arquivo';
    }

    return [
        'id' => isset($row['idarquivo']) ? (int)$row['idarquivo'] : (isset($row['id']) ? (int)$row['id'] : null),
        'name' => $name ?: 'Arquivo',
        'path' => (string)($row['caminho'] ?? ''),
        'type' => strtoupper((string)($row['tipo'] ?? pathinfo($name, PATHINFO_EXTENSION) ?: '')),
        'size' => (string)($row['tamanho'] ?? ''),
        'date' => (string)($row['recebido_em'] ?? $row['criado_em'] ?? ''),
        'origin' => $origin ?: alt_file_origin($row),
        'scope' => $scope,
        'category' => (string)($row['categoria_nome'] ?? ''),
        'description' => (string)($row['descricao'] ?? ''),
        'suffix' => (string)($row['sufixo'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    alt_json_error('Metodo invalido.', 405);
}

$imagemId = isset($_GET['imagem_id']) ? (int)$_GET['imagem_id'] : 0;
if ($imagemId <= 0) {
    alt_json_error('Imagem nao informada.');
}

alteracoes_ensure_schema($conn);

$stmt = $conn->prepare(
    "SELECT
        ico.idimagens_cliente_obra AS imagem_id,
        ico.imagem_nome,
        ico.recebimento_arquivos,
        ico.data_inicio,
        ico.prazo AS imagem_prazo,
        ico.tipo_imagem,
        ico.subtipo_imagem,
        ico.status_id,
        ico.substatus_id,
        si.nome_status,
        o.idobra AS obra_id,
        o.nomenclatura,
        o.nome_obra,
        o.nome_completo,
        o.link_review,
        o.link_drive,
        o.fotografico,
        fi.idfuncao_imagem AS alteracao_funcao_id,
        fi.colaborador_id,
        c.nome_colaborador,
        fi.prazo AS alteracao_prazo,
        COALESCE(NULLIF(TRIM(fi.status), ''), 'Não iniciado') AS alteracao_status,
        fi.observacao AS alteracao_observacao,
        a.idalt,
        a.data_recebimento,
        a.nivel_complexidade,
        sub.nome AS subtipo_nome
     FROM imagens_cliente_obra ico
     JOIN obra o ON o.idobra = ico.obra_id
     LEFT JOIN status_imagem si ON si.idstatus = ico.status_id
     LEFT JOIN funcao_imagem fi ON fi.imagem_id = ico.idimagens_cliente_obra AND fi.funcao_id = 6
     LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
     LEFT JOIN alteracoes a ON a.funcao_id = fi.idfuncao_imagem AND a.status_id = ico.status_id
     LEFT JOIN subtipo_imagem sub ON sub.id = ico.subtipo_id
     WHERE ico.idimagens_cliente_obra = ?
     LIMIT 1"
);
if (!$stmt) {
    alt_json_error('Erro ao preparar consulta da imagem.', 500);
}
$stmt->bind_param('i', $imagemId);
$image = alt_fetch_one($stmt);
if (!$image) {
    alt_json_error('Imagem nao encontrada.', 404);
}

$funcaoAlteracaoId = (int)($image['alteracao_funcao_id'] ?? 0);

$latest = null;
if ($stmt = $conn->prepare(
    "SELECT
        hai.id,
        hai.funcao_imagem_id,
        hai.indice_envio,
        hai.data_envio,
        hai.nome_arquivo,
        LEFT(CAST(hai.imagem AS CHAR), 500) AS imagem_path,
        hai.caminho_imagem,
        f.nome_funcao,
        c.nome_colaborador,
        COUNT(ci.id) AS comentarios_total,
        SUM(COALESCE(ci.concluido, 0)) AS comentarios_concluidos
     FROM historico_aprovacoes_imagens hai
     JOIN funcao_imagem fi ON fi.idfuncao_imagem = hai.funcao_imagem_id
     LEFT JOIN funcao f ON f.idfuncao = fi.funcao_id
     LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
     LEFT JOIN comentarios_imagem ci ON ci.ap_imagem_id = hai.id
     WHERE fi.imagem_id = ?
     GROUP BY hai.id
     ORDER BY hai.data_envio DESC, hai.id DESC
     LIMIT 1"
)) {
    $stmt->bind_param('i', $imagemId);
    $latest = alt_fetch_one($stmt);
}

$fallbackPreview = null;
if ($stmt = $conn->prepare(
    "SELECT idarquivo, caminho, nome_interno, nome_original, tipo, recebido_em
       FROM arquivos
      WHERE imagem_id = ? AND status = 'atualizado' AND caminho IS NOT NULL AND caminho <> ''
      ORDER BY CASE WHEN categoria_id = 7 THEN 0 ELSE 1 END, recebido_em DESC, idarquivo DESC
      LIMIT 1"
)) {
    $stmt->bind_param('i', $imagemId);
    $fallbackPreview = alt_fetch_one($stmt);
}

$filesImage = [];
$filesTriage = [];
if ($stmt = $conn->prepare(
    "SELECT a.*, cat.nome_categoria AS categoria_nome
       FROM arquivos a
       LEFT JOIN categorias cat ON cat.idcategoria = a.categoria_id
      WHERE a.status = 'atualizado' AND a.imagem_id = ?
      ORDER BY a.recebido_em DESC, a.idarquivo DESC"
)) {
    $stmt->bind_param('i', $imagemId);
    foreach (alt_fetch_all($stmt) as $row) {
        if (!alt_is_triage_file($row)) {
            $filesImage[] = alt_prepare_file($row, 'imagem');
        }
    }
}

$obraId = (int)$image['obra_id'];
$tipoImagem = (string)($image['tipo_imagem'] ?? '');

$filesType = [];
if ($tipoImagem !== '' && $stmt = $conn->prepare(
    "SELECT a.*, cat.nome_categoria AS categoria_nome
       FROM arquivos a
       LEFT JOIN tipo_imagem ti ON (ti.id_tipo_imagem = a.tipo_imagem_id OR ti.nome = a.tipo_imagem_id)
       LEFT JOIN categorias cat ON cat.idcategoria = a.categoria_id
      WHERE a.status = 'atualizado'
        AND a.obra_id = ?
        AND (a.imagem_id IS NULL OR a.imagem_id = 0)
        AND (a.tipo_imagem_id = ? OR ti.nome = ?)
        AND LOWER(COALESCE(a.descricao, '')) NOT LIKE '%triagem%'
      ORDER BY a.recebido_em DESC, a.idarquivo DESC"
)) {
    $stmt->bind_param('iss', $obraId, $tipoImagem, $tipoImagem);
    foreach (alt_fetch_all($stmt) as $row) {
        $filesType[] = alt_prepare_file($row, 'tipo');
    }
}

$filesProject = [];
if ($stmt = $conn->prepare(
    "SELECT a.*, cat.nome_categoria AS categoria_nome
       FROM arquivos a
       LEFT JOIN categorias cat ON cat.idcategoria = a.categoria_id
      WHERE a.status = 'atualizado'
        AND a.obra_id = ?
        AND (a.imagem_id IS NULL OR a.imagem_id = 0)
        AND LOWER(COALESCE(a.descricao, '')) NOT LIKE '%triagem%'
      ORDER BY a.recebido_em DESC, a.idarquivo DESC"
)) {
    $stmt->bind_param('i', $obraId);
    foreach (alt_fetch_all($stmt) as $row) {
        $filesProject[] = alt_prepare_file($row, 'projeto');
    }
}

if ($stmt = $conn->prepare(
    "SELECT a.*, cat.nome_categoria AS categoria_nome
       FROM arquivos a
       LEFT JOIN tipo_imagem ti ON (ti.id_tipo_imagem = a.tipo_imagem_id OR ti.nome = a.tipo_imagem_id)
       LEFT JOIN categorias cat ON cat.idcategoria = a.categoria_id
      WHERE a.status = 'atualizado'
        AND a.obra_id = ?
        AND LOWER(COALESCE(a.descricao, '')) LIKE '%triagem%'
        AND (
            a.imagem_id = ?
            OR (
                (a.imagem_id IS NULL OR a.imagem_id = 0)
                AND (
                    ? = ''
                    OR a.tipo_imagem_id = ?
                    OR ti.nome = ?
                    OR a.tipo_imagem_id IS NULL
                    OR a.tipo_imagem_id = ''
                    OR a.tipo_imagem_id = '0'
                )
            )
        )
      ORDER BY a.recebido_em DESC, a.idarquivo DESC"
)) {
    $stmt->bind_param('iisss', $obraId, $imagemId, $tipoImagem, $tipoImagem, $tipoImagem);
    foreach (alt_fetch_all($stmt) as $row) {
        $filesTriage[] = alt_prepare_file($row, 'triagem', 'triagem');
    }
}

$filesInternal = [];
if ($stmt = $conn->prepare(
    "SELECT al.*, f.nome_funcao
       FROM arquivo_log al
       JOIN funcao_imagem fi ON fi.idfuncao_imagem = al.funcao_imagem_id
       LEFT JOIN funcao f ON f.idfuncao = fi.funcao_id
      WHERE fi.imagem_id = ? AND al.status IN ('atualizado', 'concluido')
      ORDER BY al.criado_em DESC, al.id DESC"
)) {
    $stmt->bind_param('i', $imagemId);
    foreach (alt_fetch_all($stmt) as $row) {
        $prepared = alt_prepare_file($row, 'imagem', 'interno');
        $prepared['function'] = (string)($row['nome_funcao'] ?? '');
        $filesInternal[] = $prepared;
    }
}

$allUploaded = array_merge($filesTriage, $filesInternal);
$uploadedByOrigin = ['cliente' => [], 'interno' => [], 'triagem' => []];
foreach ($allUploaded as $file) {
    $origin = $file['origin'] ?? 'cliente';
    if (!isset($uploadedByOrigin[$origin])) {
        $origin = 'cliente';
    }
    $uploadedByOrigin[$origin][] = $file;
}

$preAlt = null;
if ($stmt = $conn->prepare(
    "SELECT
        pai.*,
        pal.status_id AS lote_status_id,
        pal.status AS lote_status,
        pal.prioridade AS lote_prioridade,
        pal.prazo AS lote_prazo,
        pal.data_finalizacao_cliente,
        resp.nome_colaborador AS responsavel_nome,
        creator.nome_colaborador AS criado_por_nome
     FROM pre_alt_itens pai
     JOIN pre_alt_lote pal ON pal.id = pai.pre_alt_lote_id
     LEFT JOIN colaborador resp ON resp.idcolaborador = pai.responsavel_id
     LEFT JOIN colaborador creator ON creator.idcolaborador = pal.created_by
     WHERE pai.imagem_id = ? AND pal.obra_id = ? AND pal.status <> 'CANCELADO'
     ORDER BY CASE WHEN pal.status_id = ? THEN 0 ELSE 1 END, pai.updated_at DESC, pai.id DESC
     LIMIT 1"
)) {
    $statusId = (int)$image['status_id'];
    $stmt->bind_param('iii', $imagemId, $obraId, $statusId);
    $preAlt = alt_fetch_one($stmt);
}

$preAltHistory = [];
if ($preAlt && $stmt = $conn->prepare(
    "SELECT h.*, c.nome_colaborador
       FROM pre_alt_lote_historico h
       LEFT JOIN colaborador c ON c.idcolaborador = h.colaborador_id
      WHERE h.pre_alt_lote_id = ? AND (h.item_id IS NULL OR h.item_id = ?)
      ORDER BY h.created_at DESC, h.id DESC
      LIMIT 12"
)) {
    $loteId = (int)$preAlt['pre_alt_lote_id'];
    $itemId = (int)$preAlt['id'];
    $stmt->bind_param('ii', $loteId, $itemId);
    $preAltHistory = alt_fetch_all($stmt);
}

$logs = [];
if ($funcaoAlteracaoId > 0 && $stmt = $conn->prepare(
    "SELECT la.*, c.nome_colaborador AS responsavel
       FROM log_alteracoes la
       LEFT JOIN colaborador c ON c.idcolaborador = la.colaborador_id
      WHERE la.funcao_imagem_id = ?
      ORDER BY la.data DESC, la.idlog DESC"
)) {
    $stmt->bind_param('i', $funcaoAlteracaoId);
    $logs = alt_fetch_all($stmt);
}

$approvals = [];
if ($funcaoAlteracaoId > 0 && $stmt = $conn->prepare(
    "SELECT ha.*, c.nome_colaborador AS colaborador_nome, r.nome_colaborador AS responsavel_nome
       FROM historico_aprovacoes ha
       LEFT JOIN colaborador c ON c.idcolaborador = ha.colaborador_id
       LEFT JOIN colaborador r ON r.idcolaborador = ha.responsavel
      WHERE ha.funcao_imagem_id = ?
      ORDER BY ha.data_aprovacao DESC, ha.id DESC
      LIMIT 12"
)) {
    $stmt->bind_param('i', $funcaoAlteracaoId);
    $approvals = alt_fetch_all($stmt);
}

$comments = [];
if ($latest && $stmt = $conn->prepare(
    "SELECT ci.id, ci.numero_comentario, ci.texto, ci.tipo, ci.data, ci.concluido, c.nome_colaborador AS responsavel
       FROM comentarios_imagem ci
       LEFT JOIN colaborador c ON c.idcolaborador = ci.responsavel_id
      WHERE ci.ap_imagem_id = ?
      ORDER BY ci.data DESC, ci.id DESC
      LIMIT 8"
)) {
    $latestId = (int)$latest['id'];
    $stmt->bind_param('i', $latestId);
    $comments = alt_fetch_all($stmt);
}

$latestPath = (string)($latest['caminho_imagem'] ?? '');
if ($latestPath === '') {
    $latestPath = (string)($latest['imagem_path'] ?? '');
}
if ($latestPath === '' || str_contains($latestPath, 'imagem_')) {
    $latestPath = (string)($fallbackPreview['caminho'] ?? '');
}

$lastUpdate = null;
foreach (
    [
        $logs[0]['data'] ?? null,
        $latest['data_envio'] ?? null,
        $preAlt['updated_at'] ?? null,
        $image['data_recebimento'] ?? null,
    ] as $candidate
) {
    if ($candidate) {
        $lastUpdate = $candidate;
        break;
    }
}

$summary = [
    'has_pre_alt' => $preAlt !== null,
    'has_real_change' => $preAlt ? ((string)$preAlt['resultado'] !== 'sem_alteracao') : null,
    'complexity' => $preAlt['nivel_complexidade'] ?? $image['nivel_complexidade'] ?? null,
    'triage_note' => $preAlt['acao'] ?? $image['alteracao_observacao'] ?? '',
    'type' => $preAlt['tipo_alteracao'] ?? '',
    'return_required' => $preAlt ? (bool)$preAlt['necessita_retorno'] : null,
    'comments_count' => $preAlt['quantidade_comentarios'] ?? ($latest['comentarios_total'] ?? 0),
    'responsible' => $preAlt['responsavel_nome'] ?? '',
    'date' => $preAlt['updated_at'] ?? $preAlt['created_at'] ?? null,
];

echo json_encode([
    'success' => true,
    'image' => $image,
    'latest_version' => $latest ? array_merge($latest, ['public_path' => $latestPath]) : ['public_path' => $latestPath],
    'pre_alteracao' => $preAlt,
    'pre_alteracao_summary' => $summary,
    'pre_alteracao_history' => $preAltHistory,
    'comments' => $comments,
    'files' => [
        'uploaded_by_origin' => $uploadedByOrigin,
        'references_by_scope' => [
            'projeto' => $filesProject,
            'tipo' => $filesType,
            'imagem' => $filesImage,
        ],
        'all_uploaded' => $allUploaded,
    ],
    'history' => [
        'logs' => $logs,
        'approvals' => $approvals,
    ],
    'links' => [
        'review_studio' => (string)($image['link_review'] ?? ''),
        'drive' => (string)($image['link_drive'] ?? ''),
        'fotografico' => (string)($image['fotografico'] ?? ''),
    ],
    'metrics' => [
        'comments_count' => (int)($latest['comentarios_total'] ?? 0),
        'files_count' => count($allUploaded),
        'references_count' => count($filesProject) + count($filesType) + count($filesImage),
        'last_update' => $lastUpdate,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
