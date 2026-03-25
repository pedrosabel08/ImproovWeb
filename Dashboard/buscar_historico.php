<?php
include "../conexao.php"; // conexão com seu MySQL

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['imagem_id'])) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$imagemId = intval($_POST['imagem_id']);

// 1. Buscar todos os movimentos históricos ordenados cronologicamente
$sql = "SELECT
    h.idhistorico,
    h.status_id,
    s.nome_status  AS status_nome,
    h.substatus_id,
    ss.nome_substatus AS substatus_nome,
    h.data_movimento
FROM historico_imagens h
LEFT JOIN status_imagem   s  ON h.status_id    = s.idstatus
LEFT JOIN substatus_imagem ss ON h.substatus_id = ss.id
WHERE h.imagem_id = ?
ORDER BY h.data_movimento ASC, h.idhistorico ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $imagemId);
$stmt->execute();
$result = $stmt->get_result();
$movimentos = [];
while ($row = $result->fetch_assoc()) {
    $movimentos[] = $row;
}
$stmt->close();

if (empty($movimentos)) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Buscar justificativas de HOLD para esta imagem
$sqlHold = "SELECT id, justificativa, data_hold
            FROM status_hold
            WHERE imagem_id = ?
            ORDER BY data_hold ASC";
$stmtHold = $conn->prepare($sqlHold);
$stmtHold->bind_param("i", $imagemId);
$stmtHold->execute();
$resultHold = $stmtHold->get_result();
$holds = [];
while ($row = $resultHold->fetch_assoc()) {
    $holds[] = $row;
}
$stmtHold->close();

// 3. Agrupar movimentos por etapa (agrupamento sequencial por status_id)
$etapas = [];
$currentIdx = -1;
foreach ($movimentos as $mov) {
    if ($currentIdx < 0 || $mov['status_id'] !== $etapas[$currentIdx]['status_id']) {
        $etapas[] = [
            'status_id'   => $mov['status_id'],
            'status_nome' => $mov['status_nome'],
            'data_inicio' => $mov['data_movimento'],
            'movimentos'  => [],
        ];
        $currentIdx = count($etapas) - 1;
    }
    $etapas[$currentIdx]['movimentos'][] = $mov;
}

// 4. Processar cada etapa
$hoje = new DateTime();
$etapasProcessadas = [];

foreach ($etapas as $idx => $etapa) {
    $movs       = $etapa['movimentos'];
    $dataInicio = new DateTime($etapa['data_inicio']);

    // data_fim da etapa = data_inicio da próxima etapa, ou hoje se for a última
    $proxEtapa  = isset($etapas[$idx + 1]) ? $etapas[$idx + 1] : null;
    if ($proxEtapa) {
        $dataFim    = new DateTime($proxEtapa['data_inicio']);
        $emAndamento = false;
    } else {
        $dataFim    = $hoje;
        $emAndamento = true;
    }

    $totalDias = (int) $dataInicio->diff($dataFim)->days;

    // 5. Processar substatuses dentro da etapa
    $substatuses = [];
    $holdsUsados = [];

    for ($i = 0; $i < count($movs); $i++) {
        $mov          = $movs[$i];
        $dataSubInicio = new DateTime($mov['data_movimento']);

        // data_fim do substatus = próximo movimento na mesma etapa, ou data_fim da etapa
        $dataSubFim = isset($movs[$i + 1])
            ? new DateTime($movs[$i + 1]['data_movimento'])
            : $dataFim;

        $diasSub = (int) $dataSubInicio->diff($dataSubFim)->days;

        // Para HOLD: buscar justificativa mais próxima por timestamp (janela de 48 h)
        $justificativa = null;
        if ($mov['substatus_id'] == 7) {
            $menorDiff = PHP_INT_MAX;
            $melhorIdx = -1;
            foreach ($holds as $hIdx => $hold) {
                if (in_array($hIdx, $holdsUsados, true)) continue;
                $dataHold = new DateTime($hold['data_hold']);
                $diff     = abs($dataSubInicio->getTimestamp() - $dataHold->getTimestamp());
                if ($diff < $menorDiff) {
                    $menorDiff = $diff;
                    $melhorIdx = $hIdx;
                }
            }
            if ($melhorIdx >= 0 && $menorDiff <= 172800) { // 48 h
                $justificativa = $holds[$melhorIdx]['justificativa'];
                $holdsUsados[] = $melhorIdx;
            }
        }

        // data_fim null = substatus ainda ativo (último da última etapa)
        $dataSubFimStr = ($emAndamento && !isset($movs[$i + 1])) ? null : $dataSubFim->format('Y-m-d H:i:s');

        $substatuses[] = [
            'substatus_id'   => (int) $mov['substatus_id'],
            'substatus_nome' => $mov['substatus_nome'] ?? '-',
            'data_inicio'    => $mov['data_movimento'],
            'data_fim'       => $dataSubFimStr,
            'dias'           => $diasSub,
            'justificativa'  => $justificativa,
        ];
    }

    // 6. Calcular tempo de espera em TO-DO antes da primeira entrada em TEA (3) ou APR (4)
    $tempoEsperaTodoDias = 0;
    $todoInicio = null;
    foreach ($movs as $mov) {
        if ($mov['substatus_id'] == 2 && $todoInicio === null) { // TO-DO
            $todoInicio = new DateTime($mov['data_movimento']);
        }
        if ($todoInicio !== null && in_array((int) $mov['substatus_id'], [3, 4], true)) { // TEA ou APR
            $tempoEsperaTodoDias = (int) $todoInicio->diff(new DateTime($mov['data_movimento']))->days;
            break;
        }
    }
    // Etapa que ficou apenas em TO-DO e nunca teve TEA/APR
    if ($todoInicio !== null && $tempoEsperaTodoDias === 0) {
        $temSubstatus = false;
        foreach ($movs as $mov) {
            if ((int) $mov['substatus_id'] !== 2) {
                $temSubstatus = true;
                break;
            }
        }
        if (!$temSubstatus) {
            $tempoEsperaTodoDias = $totalDias;
        }
    }

    $etapasProcessadas[] = [
        'status_id'              => (int) $etapa['status_id'],
        'status_nome'            => $etapa['status_nome'],
        'data_inicio'            => $dataInicio->format('Y-m-d H:i:s'),
        'data_fim'               => $emAndamento ? null : $dataFim->format('Y-m-d H:i:s'),
        'total_dias'             => $totalDias,
        'em_andamento'           => $emAndamento,
        'tempo_espera_todo_dias' => $tempoEsperaTodoDias,
        'substatuses'            => $substatuses,
    ];
}

echo json_encode($etapasProcessadas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
