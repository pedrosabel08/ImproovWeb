<?php
require_once __DIR__ . '/../../conexao.php';
header('Content-Type: application/json; charset=utf-8');

function jsonOut($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // functions to include in report
    $funcs = [1, 8, 2, 3, 4, 5, 6];

    // Fetch relevant images (only active obras and relevant image statuses)
    $sql = "SELECT ico.idimagens_cliente_obra AS imagem_id,
                   ico.tipo_imagem,
                   ico.obra_id,
                   o.nomenclatura AS obra_nome,
                   fi.funcao_id,
                   fi.status AS status_funcao
            FROM imagens_cliente_obra ico
            LEFT JOIN funcao_imagem fi ON fi.imagem_id = ico.idimagens_cliente_obra AND fi.funcao_id IN (" . implode(',', $funcs) . ")
            LEFT JOIN obra o ON o.idobra = ico.obra_id
            WHERE o.status_obra = 0
              AND ico.status_id IN (1,2)
              AND (ico.substatus_id IS NULL OR ico.substatus_id <> 7)
            ORDER BY o.nomenclatura, ico.tipo_imagem";

    $res = mysqli_query($conn, $sql);
    if ($res === false) throw new Exception('Erro na consulta');

    // helper to normalize status and map priority
    function normalize_status($s)
    {
        // Normalize any raw status into one of three values:
        // 'Não iniciado', 'Em andamento', 'Finalizado'
        if ($s === null) return 'Não iniciado';
        $low = mb_strtolower(trim($s));

        // explicit not started
        if (preg_match('/\b(n[aã]o iniciado|nao iniciado|n[aã]o iniciado|não iniciado|sem inicio|sem início|pendente)\b/u', $low)) {
            return 'Não iniciado';
        }

        // patterns that indicate ongoing work (Em andamento)
        // includes 'em andamento', 'em aprovação', 'aprovado com ajustes', 'em ajuste', 'ajuste(s)', 'aprovacao', 'revis', etc.
        if (preg_match('/andam|em andamento|em aprov|aprovacao|aprovac|aprovad.*ajust|aprovad.*ajuste|ajust|ajuste|ajustes|em ajust|revis/i', $low)) {
            return 'Em andamento';
        }

        // patterns that indicate finished
        if (preg_match('/finaliz|finalizado|conclu|conclu[ií]do|aprovado\b|aprovado$/u', $low)) {
            return 'Finalizado';
        }

        // fallback: treat unknown statuses as 'Em andamento'
        return 'Em andamento';
    }

    function status_priority($s)
    {
        if ($s === 'Em andamento') return 3;
        if ($s === 'Finalizado') return 2;
        if ($s === 'Não iniciado') return 1;
        return 2; // default to neutral
    }

    $data = [];

    while ($row = mysqli_fetch_assoc($res)) {
        $obra_id = $row['obra_id'] ?? 0;
        $obra_nome = $row['obra_nome'] ?? 'Sem obra';
        $tipo = $row['tipo_imagem'] ?? 'Sem tipo';
        $funcao_id = $row['funcao_id'];
        $raw_status = $row['status_funcao'];

        if (!isset($data[$obra_id])) {
            $data[$obra_id] = ['obra_id' => $obra_id, 'obra_nome' => $obra_nome, 'tipos' => []];
        }
        if (!isset($data[$obra_id]['tipos'][$tipo])) {
            // initialize all functions as '-' (not allocated)
            $data[$obra_id]['tipos'][$tipo] = ['tipo' => $tipo, 'funcoes' => []];
            foreach ($funcs as $f) $data[$obra_id]['tipos'][$tipo]['funcoes'][$f] = '-';
        }

        if ($funcao_id !== null) {
            $nstatus = normalize_status($raw_status);
            $cur = $data[$obra_id]['tipos'][$tipo]['funcoes'][$funcao_id] ?? '-';
            // if current is '-' (not allocated) always replace
            if ($cur === '-') {
                $data[$obra_id]['tipos'][$tipo]['funcoes'][$funcao_id] = $nstatus;
            } else {
                // pick highest priority between current and this
                $curP = status_priority($cur);
                $newP = status_priority($nstatus);
                if ($newP > $curP) {
                    $data[$obra_id]['tipos'][$tipo]['funcoes'][$funcao_id] = $nstatus;
                } elseif ($newP == $curP) {
                    // if equal priority prefer more informative (Finalizado over generic)
                    $data[$obra_id]['tipos'][$tipo]['funcoes'][$funcao_id] = $nstatus;
                }
            }
        }
    }
    mysqli_free_result($res);

    // compute etapa per tipo
    foreach ($data as $obra_id => &$obra) {
        foreach ($obra['tipos'] as $tipo_key => &$tipo) {
            $all = $tipo['funcoes'];
            $allValues = array_values($all);
            // consider only allocated functions (not '-') when computing etapa
            $allocated = array_values(array_filter($allValues, function ($v) {
                return $v !== '-';
            }));
            if (count($allocated) === 0) {
                // no functions allocated for this tipo -> treat as TO-DO
                $tipo['etapa'] = 'TO-DO';
            } else {
                $allNotStarted = array_reduce($allocated, function ($acc, $v) {
                    return $acc && ($v === 'Não iniciado');
                }, true);
                $allFinal = array_reduce($allocated, function ($acc, $v) {
                    return $acc && ($v === 'Finalizado');
                }, true);
                $anyRunning = array_reduce($allocated, function ($acc, $v) {
                    return $acc || ($v === 'Em andamento');
                }, false);
                if ($allNotStarted) $tipo['etapa'] = 'TO-DO';
                elseif ($allFinal) $tipo['etapa'] = 'OK';
                elseif ($anyRunning) $tipo['etapa'] = 'TEA';
                else $tipo['etapa'] = 'TEA';
            }
        }
        // reindex tipos
        $obra['tipos'] = array_values($obra['tipos']);
    }
    unset($obra);

    // --- Fetch pending entregas per obra ---
    // We'll consider entregas with status not in the concluded set as pending.
    $concluded_names = [
        'Entregue no prazo',
        'Entregue com atraso',
        'Entrega antecipada',
        'Concluída'
    ];
    $obras_ids = array_keys($data);
    $entregas_by_obra = [];
    if (!empty($obras_ids)) {
        // build comma-separated int list (safe because we cast to int)
        $in_list = implode(',', array_map('intval', $obras_ids));
        // include nome_status from status_imagem when available
        $sqlE = "SELECT e.id, e.obra_id, e.data_prevista, e.status, e.status_id, s.nome_status AS status_nome
                 FROM entregas e
                 LEFT JOIN status_imagem s ON e.status_id = s.idstatus
                 WHERE e.obra_id IN ($in_list)
                   AND (e.status IS NULL OR e.status NOT IN ('" . implode("','", $concluded_names) . "'))
                 ORDER BY e.obra_id, e.data_prevista ASC";
        $resE = mysqli_query($conn, $sqlE);
        if ($resE) {
            while ($r = mysqli_fetch_assoc($resE)) {
                $oid = $r['obra_id'] ?? 0;
                if (!isset($entregas_by_obra[$oid])) $entregas_by_obra[$oid] = [];
                $entregas_by_obra[$oid][] = [
                    'id' => intval($r['id']),
                    'data_prevista' => $r['data_prevista'],
                    'status' => $r['status'],
                    'status_id' => isset($r['status_id']) ? intval($r['status_id']) : null,
                    'status_nome' => $r['status_nome'] ?? null
                ];
            }
            mysqli_free_result($resE);
        }
    }

    // function labels (ordered)
    $func_labels = [
        1 => 'Caderno',
        8 => 'Filtro de assets',
        2 => 'Modelagem',
        3 => 'Composição',
        4 => 'Finalização',
        5 => 'Pós-produção',
        6 => 'Alteração'
    ];

    // build ordered functions array according to $funcs
    $out_funcs = [];
    foreach ($funcs as $fid) {
        $out_funcs[] = ['id' => $fid, 'label' => ($func_labels[$fid] ?? (string)$fid)];
    }

    // attach entregas pendentes to each obra entry
    $out_obras = [];
    foreach ($data as $obra_id => $obra) {
        $obra['entregas_pendentes'] = $entregas_by_obra[$obra_id] ?? [];
        $out_obras[] = $obra;
    }

    jsonOut(['obras' => $out_obras, 'funcoes' => $out_funcs]);
} catch (Throwable $e) {
    http_response_code(500);
    jsonOut(['error' => true, 'message' => $e->getMessage()]);
}
