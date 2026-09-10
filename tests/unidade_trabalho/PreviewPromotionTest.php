<?php

require_once dirname(__DIR__, 2) . '/conexao.php';
require_once dirname(__DIR__, 2) . '/helpers/unidade_trabalho_helper.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function preview_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$tipo = FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO;
$stmt = $conn->prepare(
    "SELECT ut.id unidade_id,
            MAX(CASE WHEN fi.funcao_id = ? THEN fi.idfuncao_imagem END) modelagem_id,
            MAX(CASE WHEN fi.funcao_id = ? THEN fi.idfuncao_imagem END) composicao_id
       FROM unidade_trabalho ut
       JOIN unidade_trabalho_item uti ON uti.unidade_trabalho_id = ut.id
       JOIN funcao_imagem fi ON fi.idfuncao_imagem = uti.funcao_imagem_id
      WHERE ut.tipo = ? AND ut.estado = 'ATIVA'
      GROUP BY ut.id
     HAVING modelagem_id IS NOT NULL AND composicao_id IS NOT NULL
      LIMIT 1"
);
$modelagemFuncaoId = FLOW_FUNCAO_MODELAGEM;
$composicaoFuncaoId = FLOW_FUNCAO_COMPOSICAO;
$stmt->bind_param('iis', $modelagemFuncaoId, $composicaoFuncaoId, $tipo);
$stmt->execute();
$candidate = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$candidate) {
    echo "PreviewPromotionTest: SKIP (sem unidade ativa)\n";
    exit(0);
}

$modelagemId = (int) $candidate['modelagem_id'];
$composicaoId = (int) $candidate['composicao_id'];

try {
    $conn->begin_transaction();
    $activate = $conn->prepare("UPDATE funcao_imagem SET status = 'Em andamento' WHERE idfuncao_imagem IN (?, ?)");
    $activate->bind_param('ii', $modelagemId, $composicaoId);
    $activate->execute();
    $activate->close();

    $result = flow_unidade_promover_composicao_principal(
        $conn,
        $modelagemId,
        'Em aprovação',
        null,
        null,
        'teste_integracao'
    );
    preview_assert(!empty($result['aplicada']), 'A regra de prévia não identificou a unidade explícita.');
    preview_assert((int) $result['tarefa_principal_id'] === $composicaoId, 'A Composição não foi definida como tarefa principal.');

    $status = $conn->query(
        "SELECT idfuncao_imagem, status FROM funcao_imagem WHERE idfuncao_imagem IN ($modelagemId, $composicaoId)"
    )->fetch_all(MYSQLI_ASSOC);
    $byId = [];
    foreach ($status as $row) {
        $byId[(int) $row['idfuncao_imagem']] = $row['status'];
    }
    preview_assert(($byId[$modelagemId] ?? null) === 'Finalizado', 'A Modelagem não foi finalizada no envio da prévia.');
    preview_assert(($byId[$composicaoId] ?? null) === 'Em aprovação', 'A Composição não foi enviada para aprovação.');

    $cards = flow_unidade_agrupar_funcoes_payload($conn, [
        ['idfuncao_imagem' => $modelagemId, 'status' => 'Finalizado', 'nome_funcao' => 'Modelagem'],
        ['idfuncao_imagem' => $composicaoId, 'status' => 'Em aprovação', 'nome_funcao' => 'Composição'],
    ]);
    preview_assert(count($cards) === 1, 'A unidade não foi projetada como um único card.');
    preview_assert((int) ($cards[0]['idfuncao_imagem'] ?? 0) === $composicaoId, 'O card agrupado não aponta para a Composição.');

    $hold = flow_unidade_promover_composicao_principal(
        $conn,
        $modelagemId,
        'HOLD',
        null,
        null,
        'teste_integracao'
    );
    preview_assert((int) $hold['tarefa_principal_id'] === $composicaoId, 'O HOLD não manteve a Composição como principal.');
    $holdStatus = $conn->query(
        "SELECT status FROM funcao_imagem WHERE idfuncao_imagem = $composicaoId"
    )->fetch_assoc();
    preview_assert(($holdStatus['status'] ?? null) === 'HOLD', 'O HOLD não foi aplicado à Composição.');

    $conn->rollback();
    echo "PreviewPromotionTest: OK (prévia, card principal e HOLD na Composição)\n";
} catch (Throwable $error) {
    $conn->rollback();
    throw $error;
}
