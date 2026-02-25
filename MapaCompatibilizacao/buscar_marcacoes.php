<?php
/**
 * buscar_marcacoes.php
 * Retorna a planta ativa de uma obra com todas as suas marcações
 * e a cor calculada dinamicamente com base no status da funcao de Finalização.
 *
 * GET params:
 *   obra_id (int, obrigatório)
 *
 * Regra de cor (não salva no banco):
 *   funcao_imagem.status = 'Finalizado'  → "verde"
 *   funcao_imagem existe mas não Finalizado → "amarelo"
 *   sem imagem vinculada OU sem funcao_imagem de Finalização → "branco"
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

// --- Buscar planta ativa ---
$stmtPlanta = $conn->prepare(
    "SELECT id, versao, imagem_path
     FROM planta_compatibilizacao
     WHERE obra_id = ? AND ativa = 1
     LIMIT 1"
);
$stmtPlanta->bind_param('i', $obraId);
$stmtPlanta->execute();
$planta = $stmtPlanta->get_result()->fetch_assoc();
$stmtPlanta->close();

if (!$planta) {
    $conn->close();
    echo json_encode([
        'sucesso' => true,
        'planta' => null,
        'marcacoes' => [],
        'total_marcacoes' => 0,
        'finalizadas' => 0,
        'percentual_conclusao' => 0,
    ]);
    exit();
}

$plantaId = (int) $planta['id'];

// --- Buscar marcações com status da funcao de Finalização ---
// Usa subquery correlacionada para evitar linhas duplicadas quando uma imagem
// tem múltiplos registros em funcao_imagem.
$sql = "
    SELECT
        pm.id,
        pm.nome_ambiente,
        pm.imagem_id,
        pm.coordenadas_json,
        pm.criado_por,
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
    WHERE pm.planta_id = ?
    ORDER BY pm.criado_em ASC
";

$stmtMarcacoes = $conn->prepare($sql);
$stmtMarcacoes->bind_param('i', $plantaId);
$stmtMarcacoes->execute();
$rows = $stmtMarcacoes->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMarcacoes->close();
$conn->close();

// --- Calcular cor para cada marcação ---
$marcacoes = [];
$finalizadas = 0;

foreach ($rows as $row) {
    $statusFinalizacao = $row['status_finalizacao'];

    if (empty($row['imagem_id'])) {
        $cor = 'branco';
        $statusTexto = 'Sem imagem vinculada';
    } elseif ($statusFinalizacao === 'Finalizado') {
        $cor = 'verde';
        $statusTexto = 'Finalizado';
        $finalizadas++;
    } elseif ($statusFinalizacao !== null) {
        $cor = 'amarelo';
        $statusTexto = $statusFinalizacao;
    } else {
        // Imagem vinculada mas sem funcao de Finalização atribuída
        $cor = 'amarelo';
        $statusTexto = 'Em andamento';
    }

    $marcacoes[] = [
        'id' => (int) $row['id'],
        'nome_ambiente' => $row['nome_ambiente'],
        'imagem_id' => $row['imagem_id'] ? (int) $row['imagem_id'] : null,
        'imagem_nome' => $row['imagem_nome'],
        'coordenadas_json' => $row['coordenadas_json'],
        'criado_em' => $row['criado_em'],
        'cor' => $cor,
        'status_texto' => $statusTexto,
    ];
}

$total = count($marcacoes);
$percentual = $total > 0 ? round(($finalizadas / $total) * 100) : 0;

echo json_encode([
    'sucesso' => true,
    'planta' => [
        'id' => (int) $planta['id'],
        'versao' => (int) $planta['versao'],
        'imagem_url' => $planta['imagem_path'],
    ],
    'marcacoes' => $marcacoes,
    'total_marcacoes' => $total,
    'finalizadas' => $finalizadas,
    'percentual_conclusao' => $percentual,
]);
