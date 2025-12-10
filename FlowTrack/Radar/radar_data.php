<?php
require_once __DIR__ . '/../../conexao.php';
header('Content-Type: application/json; charset=utf-8');

function jsonOut($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Normalize status to only three buckets
function normalize_status($s)
{
    if ($s === null) return 'Não iniciado';
    $low = mb_strtolower(trim($s));
    if (preg_match('/\b(n[aã]o iniciado|nao iniciado|sem inicio|sem início|pendente)\b/u', $low)) return 'Não iniciado';
    if (preg_match('/andam|em andamento|em aprov|aprovac|aprovad.*ajust|ajust|ajuste|ajustes|em ajust|revis/i', $low)) return 'Em andamento';
    if (preg_match('/finaliz|finalizado|conclu|conclu[ií]do|aprovado\b|aprovado$/u', $low)) return 'Finalizado';
    return 'Em andamento';
}

try {
    // cache and helper to determine if any Filtro (funcao 8) in an obra has been started
    $obraFiltroCache = [];
    function obraFiltroStarted($conn, $obraId, array &$cache)
    {
        if ($obraId === null) return false;
        if (array_key_exists($obraId, $cache)) return $cache[$obraId];
        $sql = "SELECT fi.status
                FROM funcao_imagem fi
                JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
                WHERE fi.funcao_id = 8 AND ico.obra_id = ? LIMIT 1";
        $started = false;
        if ($stmt2 = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt2, 'i', $obraId);
            mysqli_stmt_execute($stmt2);
            $res2 = mysqli_stmt_get_result($stmt2);
            if ($res2) {
                while ($r2 = mysqli_fetch_assoc($res2)) {
                    $s = normalize_status($r2['status'] ?? null);
                    if ($s !== 'Não iniciado') {
                        $started = true;
                        break;
                    }
                }
                mysqli_free_result($res2);
            }
            mysqli_stmt_close($stmt2);
        }
        $cache[$obraId] = $started;
        return $started;
    }
    // function pipeline (adjust if needed)
    $functions = [
        1 => ['nome' => 'Caderno',        'prev' => null],
        8 => ['nome' => 'Filtro de assets', 'prev' => 1],
        2 => ['nome' => 'Modelagem',      'prev' => 8],
        3 => ['nome' => 'Composição',     'prev' => 2],
        4 => ['nome' => 'Finalização',    'prev' => 3],
        5 => ['nome' => 'Pós-produção',   'prev' => 4],
        6 => ['nome' => 'Alteração',      'prev' => 4],
    ];

    // Get active collaborators per function (only active collaborators)
    $collabByFunc = [];
    $sqlCol = "SELECT DISTINCT fi.funcao_id, fi.colaborador_id, c.nome_colaborador
               FROM funcao_imagem fi
               JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
               WHERE c.ativo = 1";
    if ($res = mysqli_query($conn, $sqlCol)) {
        while ($row = mysqli_fetch_assoc($res)) {
            $fid = (int)$row['funcao_id'];
            if (!isset($collabByFunc[$fid])) $collabByFunc[$fid] = [];
            $collabByFunc[$fid][] = [
                'id' => (int)$row['colaborador_id'],
                'nome' => $row['nome_colaborador'] ?? ''
            ];
        }
        mysqli_free_result($res);
    }

    $functionsOut = [];

    foreach ($functions as $funcId => $meta) {
        $prevId = $meta['prev'];

        // Query suggestions: tasks with no collaborator on current func, previous func not "Não iniciado"
        $params = [];
        $types = '';
        $where = ["o.status_obra = 0", "ico.status_id IN (1,2)", "(ico.substatus_id IS NULL OR ico.substatus_id <> 7)"];

        $joinPrev = '';
        if ($prevId !== null) {
            $joinPrev = "LEFT JOIN funcao_imagem pf ON pf.imagem_id = ico.idimagens_cliente_obra AND pf.funcao_id = ?";
            $params[] = $prevId;
            $types .= 'i';
        } else {
            $joinPrev = "LEFT JOIN funcao_imagem pf ON pf.imagem_id = ico.idimagens_cliente_obra AND 1=0"; // no prev
        }

        $joinCur = "LEFT JOIN funcao_imagem cf ON cf.imagem_id = ico.idimagens_cliente_obra AND cf.funcao_id = ?";
        $params[] = $funcId;
        $types .= 'i';

        $sql = "SELECT ico.idimagens_cliente_obra AS imagem_id,
                   ico.obra_id,
                   ico.imagem_nome,
                   ico.tipo_imagem,
                   ico.substatus_id,
                   o.nomenclatura AS obra_nome,
                   pf.status AS prev_status,
                   pf.funcao_id AS prev_funcao_id,
               fl.status AS filtro_status,
                   cf.colaborador_id AS cur_colaborador,
                   cf.status AS cur_status
            FROM imagens_cliente_obra ico
                {$joinPrev}
            LEFT JOIN funcao_imagem fl ON fl.imagem_id = ico.idimagens_cliente_obra AND fl.funcao_id = 8
                {$joinCur}
                LEFT JOIN obra o ON o.idobra = ico.obra_id
                WHERE " . implode(' AND ', $where);

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) throw new Exception('Erro preparando radar query');
        if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);

        $sugestoes = [];
        $obraModelagemDone = []; // obra_id => true when modelagem fachada/externa is Finalizado
        $obraModelagemSuggested = []; // ensure single suggestion per obra for fachada/externa
        if ($r) {
            while ($row = mysqli_fetch_assoc($r)) {
                $tipoImg = $row['tipo_imagem'] ?? '';
                $obraId = $row['obra_id'] ?? null;

                // For modelagem (funcao_id = 2), only consider allowed types
                if ($funcId === 2) {
                    if (!in_array($tipoImg, ['Fachada', 'Imagem externa', 'Interna', 'Unidades'], true)) {
                        continue;
                    }
                }

                // Do not suggest Caderno (funcao 1) for fachada or planta humanizada
                if ($funcId === 1 && in_array($tipoImg, ['Fachada', 'Planta Humanizada'], true)) {
                    continue;
                }
                if ($funcId === 8 && in_array($tipoImg, ['Fachada', 'Planta Humanizada'], true)) {
                    continue;
                }

                $prevStatus = normalize_status($row['prev_status'] ?? null);
                $curColab = $row['cur_colaborador'];
                $curStatus = normalize_status($row['cur_status'] ?? null);
                $filtroStatus = normalize_status($row['filtro_status'] ?? null);

                $noAllocation = ($curColab === null);

                $ignorePrev = in_array($tipoImg, ['Fachada', 'Planta Humanizada', 'Imagem externa'], true);

                if ($prevId === null) {
                    // first function in chain: require TO-DO (substatus_id=2), unless ignored
                    $prevOk = $ignorePrev ? true : ((int)($row['substatus_id'] ?? 0) === 2);
                } else {
                    $prevOk = $ignorePrev ? true : ($prevStatus !== 'Não iniciado');
                }

                // Modelagem de fachada/externa é o primeiro processo: não depende de etapa anterior
                if ($funcId === 2 && in_array($tipoImg, ['Fachada', 'Imagem externa'], true)) {
                    $prevOk = true;
                }

                // Modelagem de Interna/Unidades depende do Filtro (funcao 8) na obra ter sido iniciado
                if ($funcId === 2 && in_array($tipoImg, ['Interna', 'Unidades'], true)) {
                    $filtroStarted = false;
                    if ($filtroStatus !== 'Não iniciado') {
                        $filtroStarted = true;
                    } else {
                        // fallback: checar se qualquer imagem da obra tem Filtro iniciado (por obra)
                        $filtroStarted = obraFiltroStarted($conn, $obraId, $obraFiltroCache);
                    }
                    if (!$filtroStarted) {
                        $prevOk = false;
                    }
                }

                // For modelagem (funcao_id = 2) on fachada/externa, if any image of the obra is Finalizado, skip suggestions for the whole obra.
                if ($funcId === 2 && in_array($tipoImg, ['Fachada', 'Imagem externa'], true)) {
                    if ($curStatus === 'Finalizado') {
                        if ($obraId !== null) {
                            $obraModelagemDone[$obraId] = true;
                            // remove any suggestion already queued for this obra (garante que finalized prevalece)
                            if (!empty($sugestoes)) {
                                $sugestoes = array_values(array_filter($sugestoes, function ($s) use ($obraId) {
                                    return !isset($s['obra_id']) || (int)$s['obra_id'] !== (int)$obraId;
                                }));
                            }
                            unset($obraModelagemSuggested[$obraId]);
                        }
                        continue;
                    }
                    if ($obraId !== null && isset($obraModelagemDone[$obraId])) {
                        continue; // obra already done for modelagem fachada/externa
                    }
                }

                if ($noAllocation && $prevOk) {
                    if ($funcId === 2 && in_array($tipoImg, ['Fachada', 'Imagem externa'], true) && $obraId !== null) {
                        if (isset($obraModelagemSuggested[$obraId])) {
                            continue; // só conta uma por obra
                        }
                        $obraModelagemSuggested[$obraId] = true;
                    }
                    $sugestoes[] = [
                        'imagem_id' => $row['imagem_id'],
                        'obra_id' => $obraId,
                        'imagem_nome' => $row['imagem_nome'],
                        'tipo_imagem' => $row['tipo_imagem'],
                        'substatus_id' => $row['substatus_id'] ?? null,
                        'obra_nome' => $row['obra_nome'],
                        'prev_status' => $prevStatus,
                        'cur_status' => $curStatus,
                        'prev_funcao_id' => $prevId,
                        'funcao_id' => $funcId
                    ];
                }
            }
            mysqli_free_result($r);
        }
        mysqli_stmt_close($stmt);

        $functionsOut[] = [
            'funcao_id' => $funcId,
            'funcao_nome' => $meta['nome'],
            'prev_funcao_id' => $prevId,
            'prev_funcao_nome' => ($prevId && isset($functions[$prevId])) ? $functions[$prevId]['nome'] : null,
            'colaboradores' => $collabByFunc[$funcId] ?? [],
            'sugestoes' => $sugestoes
        ];
    }

    jsonOut(['functions' => $functionsOut]);
} catch (Throwable $e) {
    http_response_code(500);
    jsonOut(['error' => true, 'message' => $e->getMessage()]);
}
