<?php
/* ----------------------------------------------------------
   atribuir_colaborador.php
   ---------------------------------------------------------- */
include '../conexao.php';   // arquivo de conexão ($conn)

/* ==========================================================
   1) Dados recebidos
   ========================================================== */
$input          = json_decode(file_get_contents('php://input'), true);
$gantt_id       = intval($input['gantt_id']       ?? 0);
$colaborador_id = intval($input['colaborador_id'] ?? 0);
$imagem_id      = intval($input['imagemId']       ?? 0);
$etapaNome = trim($input['etapaNome'] ?? '');

/* ----------------------------------------------------------
   Validação básica
   ---------------------------------------------------------- */
if (!$gantt_id || !$colaborador_id || !$imagem_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Todos os campos (gantt_id, colaborador_id e imagemId) são obrigatórios.'
    ]);
    exit;
}

/* ==========================================================
   3) Buscar informações da imagem selecionada
   ========================================================== */
/*  Assume-se que a tabela chama-se `imagens_cliente_obra`
    e contém: idimagens_cliente_obra, tipo_imagem, obra_id   */
$sqlImg = "SELECT tipo_imagem, obra_id
    FROM imagens_cliente_obra
    WHERE idimagens_cliente_obra = ?
    LIMIT 1
";
$stmtImg = $conn->prepare($sqlImg);
$stmtImg->bind_param("i", $imagem_id);
$stmtImg->execute();
$imagemInfo = $stmtImg->get_result()->fetch_assoc();

if (!$imagemInfo) {
    echo json_encode([
        'success' => false,
        'message' => 'Imagem não encontrada no banco.'
    ]);
    exit;
}

$tipoImagem = strtolower(trim($imagemInfo['tipo_imagem']));
$obra_id    = intval($imagemInfo['obra_id']);

/* ==========================================================
   4) Definir a lista de imagens que receberão a atribuição
   ========================================================== */
$imagensComGantt = []; // array de pares ['gantt_id' => ..., 'imagem_id' => ...]

if ($tipoImagem === 'fachada') {
    // Buscar todas as imagens de fachada da mesma obra
    $sqlFachadas = "SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE obra_id = ? AND LOWER(tipo_imagem) = 'fachada'";
    $stmtFach = $conn->prepare($sqlFachadas);
    $stmtFach->bind_param("i", $obra_id);
    $stmtFach->execute();
    $resultFach = $stmtFach->get_result();

    $fachadas = [];
    while ($row = $resultFach->fetch_assoc()) {
        $fachadas[] = intval($row['idimagens_cliente_obra']);
    }

    // Buscar gantt_id para cada imagem
    $sqlGantt = "SELECT id FROM gantt_prazos WHERE imagem_id = ? AND etapa = ? LIMIT 1";
    $stmtGantt = $conn->prepare($sqlGantt);

    foreach ($fachadas as $imgId) {
        $stmtGantt->bind_param("is", $imgId, $etapaNome);
        $stmtGantt->execute();
        $res = $stmtGantt->get_result()->fetch_assoc();
        if ($res) {
            $imagensComGantt[] = [
                'gantt_id' => intval($res['id']),
                'imagem_id' => $imgId
            ];
        }
    }
} else {
    // Para imagem única, usar o gantt_id recebido
    $imagensComGantt[] = [
        'gantt_id' => $gantt_id,
        'imagem_id' => $imagem_id
    ];
}

/* ==========================================================
   5) Inserir na tabela etapa_colaborador (em transação)
   ========================================================== */
$conn->begin_transaction();

try {
    $sqlInsert = "INSERT INTO etapa_colaborador (gantt_id, colaborador_id, imagem_id)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE colaborador_id = VALUES(colaborador_id)";
    $stmtInsert = $conn->prepare($sqlInsert);

    foreach ($imagensComGantt as $item) {
        $stmtInsert->bind_param("iii", $item['gantt_id'], $colaborador_id, $item['imagem_id']);
        $stmtInsert->execute();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Colaborador atribuído com sucesso a ' . count($imagensComGantt) . ' imagem(ns)' . ($tipoImagem === 'fachada' ? ' de fachada.' : '.')
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atribuir colaborador: ' . $e->getMessage()
    ]);
}
