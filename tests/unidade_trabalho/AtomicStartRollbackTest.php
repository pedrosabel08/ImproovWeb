<?php

require_once dirname(__DIR__, 2) . '/conexao.php';
require_once dirname(__DIR__, 2) . '/helpers/inicio_conjunto_helper.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function atomic_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$tipo = FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO;
$stmt = $conn->prepare(
    "SELECT fm.idfuncao_imagem modelagem_id,
            fc.idfuncao_imagem composicao_id,
            fm.imagem_id,
            fm.colaborador_id
       FROM funcao_imagem fm
       JOIN funcao_imagem fc
         ON fc.imagem_id = fm.imagem_id
        AND fc.funcao_id = ?
        AND fc.colaborador_id = fm.colaborador_id
       LEFT JOIN unidade_trabalho ut
         ON ut.imagem_id = fm.imagem_id
        AND ut.tipo = ?
      WHERE fm.funcao_id = ?
        AND fm.status = 'Não iniciado'
        AND fc.status = 'Não iniciado'
        AND fm.colaborador_id IS NOT NULL
        AND ut.id IS NULL
      LIMIT 1"
);
$composicaoFuncaoId = FLOW_FUNCAO_COMPOSICAO;
$modelagemFuncaoId = FLOW_FUNCAO_MODELAGEM;
$stmt->bind_param('isi', $composicaoFuncaoId, $tipo, $modelagemFuncaoId);
$stmt->execute();
$candidate = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$candidate) {
    echo "AtomicStartRollbackTest: SKIP (sem par não iniciado disponível)\n";
    exit(0);
}

$modelagemId = (int) $candidate['modelagem_id'];
$composicaoId = (int) $candidate['composicao_id'];
$imagemId = (int) $candidate['imagem_id'];
$colaboradorId = (int) $candidate['colaborador_id'];

try {
    $conn->begin_transaction();

    $lock = $conn->prepare(
        'SELECT idfuncao_imagem FROM funcao_imagem WHERE idfuncao_imagem IN (?, ?) ORDER BY idfuncao_imagem FOR UPDATE'
    );
    $lock->bind_param('ii', $modelagemId, $composicaoId);
    $lock->execute();
    $lock->get_result()->fetch_all(MYSQLI_ASSOC);
    $lock->close();

    $logsBefore = $conn->query(
        "SELECT COUNT(*) total FROM log_alteracoes WHERE funcao_imagem_id IN ($modelagemId, $composicaoId)"
    )->fetch_assoc();

    $result = flow_inicio_conjunto_registrar($conn, [
        'joint_start_available' => true,
        'modelagem_id' => $modelagemId,
        'composicao_id' => $composicaoId,
        'imagem_id' => $imagemId,
        'colaborador_id' => $colaboradorId,
    ], '2099-12-31', $colaboradorId, null);

    $unitId = (int) ($result['unit_id'] ?? 0);
    atomic_assert($unitId > 0, 'A unidade não foi criada.');

    $status = $conn->query(
        "SELECT COUNT(*) total
           FROM funcao_imagem
          WHERE idfuncao_imagem IN ($modelagemId, $composicaoId)
            AND status = 'Em andamento'"
    )->fetch_assoc();
    atomic_assert((int) $status['total'] === 2, 'As duas tarefas não foram iniciadas atomicamente.');

    $prazos = $conn->query(
        "SELECT COUNT(*) total
           FROM funcao_imagem
          WHERE idfuncao_imagem IN ($modelagemId, $composicaoId)
            AND prazo = '2099-12-31'"
    )->fetch_assoc();
    atomic_assert((int) $prazos['total'] === 2, 'O prazo informado não foi aplicado às duas tarefas da unidade.');

    $items = $conn->query("SELECT COUNT(*) total FROM unidade_trabalho_item WHERE unidade_trabalho_id = $unitId")->fetch_assoc();
    atomic_assert((int) $items['total'] === 2, 'A unidade não preservou os dois registros independentes.');

    $events = $conn->query("SELECT COUNT(*) total FROM unidade_trabalho_evento WHERE unidade_trabalho_id = $unitId AND evento = 'INICIO_CONJUNTO'")->fetch_assoc();
    atomic_assert((int) $events['total'] === 1, 'O evento de auditoria não foi criado.');

    $logsAfter = $conn->query(
        "SELECT COUNT(*) total FROM log_alteracoes WHERE funcao_imagem_id IN ($modelagemId, $composicaoId)"
    )->fetch_assoc();
    atomic_assert(
        (int) $logsAfter['total'] - (int) $logsBefore['total'] === 2,
        'Os triggers devem gerar exatamente um histórico individual para cada tarefa.'
    );

    $conn->rollback();

    $restored = $conn->query(
        "SELECT COUNT(*) total
           FROM funcao_imagem
          WHERE idfuncao_imagem IN ($modelagemId, $composicaoId)
            AND status = 'Não iniciado'"
    )->fetch_assoc();
    atomic_assert((int) $restored['total'] === 2, 'O rollback não restaurou os estados originais.');

    $persisted = $conn->query(
        "SELECT COUNT(*) total FROM unidade_trabalho WHERE tipo = 'MODELAGEM_COMPOSICAO' AND imagem_id = $imagemId"
    )->fetch_assoc();
    atomic_assert((int) $persisted['total'] === 0, 'O teste deixou associação retroativa no banco.');

    echo "AtomicStartRollbackTest: OK (unidade, membros, estados, auditoria e rollback)\n";
} catch (Throwable $error) {
    $conn->rollback();
    throw $error;
}
