<?php
/**
 * buscar_marcacoes.php
 * Retorna TODAS as plantas ativas de uma obra (suporte multi-planta)
 * com todas as marcações de cada planta e a cor calculada dinamicamente.
 *
 * GET params:
 *   obra_id (int, obrigatório)
 *
 * Regra de cor:
 *   sem imagem vinculada              → "branco"
 *   funcao_imagem.status = Finalizado → "verde"
 *   funcao_imagem existe mas não fin. → "amarelo"
 *   imagem vinculada sem funcao fin.  → "amarelo" (Em andamento)
 */

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit();
}

$obraId = isset($_GET['obra_id']) ? (int) $_GET['obra_id'] : 0;
if ($obraId <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'obra_id inválido.']);
    exit();
}

$conn = conectarBanco();

// --- Buscar TODAS as plantas ativas da obra ---
$stmtPlantas = $conn->prepare(
    "SELECT pc.id, pc.versao, pc.imagem_path, pc.imagem_id,
            pc.arquivo_id, pc.arquivo_ids_json, pc.pdf_unificado_path,
            ico.imagem_nome,
            a.nome_original AS arquivo_nome
     FROM planta_compatibilizacao pc
     LEFT JOIN imagens_cliente_obra ico
            ON ico.idimagens_cliente_obra = pc.imagem_id
     LEFT JOIN arquivos a
            ON a.idarquivo = pc.arquivo_id
     WHERE pc.obra_id = ? AND pc.ativa = 1
     ORDER BY ico.imagem_nome ASC, pc.versao ASC"
);
$stmtPlantas->bind_param('i', $obraId);
$stmtPlantas->execute();
$plantasRows = $stmtPlantas->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPlantas->close();

if (empty($plantasRows)) {
    $conn->close();
    echo json_encode(['sucesso' => true, 'plantas' => [], 'marcacoes' => []]);
    exit();
}

// Montar array de plantas com imagem_url
$plantas = [];
$plantaIds = [];
foreach ($plantasRows as $p) {
    $plantas[] = [
        'id'               => (int) $p['id'],
        'versao'           => (int) $p['versao'],
        'imagem_url'       => $p['imagem_path'],
        'imagem_id'        => $p['imagem_id'] ? (int) $p['imagem_id'] : null,
        'imagem_nome'      => $p['imagem_nome'] ?? null,
        'arquivo_id'          => $p['arquivo_id'] ? (int) $p['arquivo_id'] : null,
        'arquivo_ids_json'    => $p['arquivo_ids_json'] ?? null,
        'pdf_unificado_path'  => $p['pdf_unificado_path'] ?? null,
        'arquivo_nome'        => $p['arquivo_nome'] ?? null,
    ];
    $plantaIds[] = (int) $p['id'];
}

// --- Buscar marcações de todas as plantas ---
$in    = implode(',', array_fill(0, count($plantaIds), '?'));
$types = str_repeat('i', count($plantaIds));

$sql = "
    SELECT
        pm.id,
        pm.planta_id,
        pm.nome_ambiente,
        pm.imagem_id,
        pm.coordenadas_json,
        pm.pagina_pdf,
        pm.criado_em,
        ico.imagem_nome,
        (
            SELECT fi.status
            FROM funcao_imagem fi
            INNER JOIN funcao f ON f.idfuncao = fi.funcao_id
            WHERE fi.imagem_id = pm.imagem_id
              AND LOWER(f.nome_funcao) LIKE '%finaliz%'
            ORDER BY fi.idfuncao_imagem DESC
            LIMIT 1
        ) AS status_finalizacao
    FROM planta_marcacoes pm
    LEFT JOIN imagens_cliente_obra ico
           ON ico.idimagens_cliente_obra = pm.imagem_id
    WHERE pm.planta_id IN ($in)
    ORDER BY pm.planta_id ASC, pm.criado_em ASC
";

$stmtM = $conn->prepare($sql);
$stmtM->bind_param($types, ...$plantaIds);
$stmtM->execute();
$rows = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtM->close();
$conn->close();

// --- Calcular cor para cada marcação ---
$marcacoes = [];
foreach ($rows as $row) {
    $sf = $row['status_finalizacao'];

    if (empty($row['imagem_id'])) {
        $cor = 'branco'; $statusTexto = 'Sem imagem vinculada';
    } elseif ($sf === 'Finalizado') {
        $cor = 'verde';  $statusTexto = 'Finalizado';
    } elseif ($sf !== null) {
        $cor = 'amarelo'; $statusTexto = $sf;
    } else {
        $cor = 'amarelo'; $statusTexto = 'Em andamento';
    }

    $marcacoes[] = [
        'id'               => (int) $row['id'],
        'planta_id'        => (int) $row['planta_id'],
        'nome_ambiente'    => $row['nome_ambiente'],
        'imagem_id'        => $row['imagem_id'] ? (int) $row['imagem_id'] : null,
        'imagem_nome'      => $row['imagem_nome'],
        'coordenadas_json' => $row['coordenadas_json'],
        'pagina_pdf'       => $row['pagina_pdf'] !== null ? (int) $row['pagina_pdf'] : null,
        'criado_em'        => $row['criado_em'],
        'cor'              => $cor,
        'status_texto'     => $statusTexto,
    ];
}

echo json_encode([
    'sucesso'   => true,
    'plantas'   => $plantas,
    'marcacoes' => $marcacoes,
]);
