<?php
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json; charset=utf-8');

$obraId = isset($_GET['obra_id']) ? (int) $_GET['obra_id'] : 0;
$etapaId = isset($_GET['etapa_id']) ? (int) $_GET['etapa_id'] : 0;
$tipoImagem = isset($_GET['tipo_imagem']) ? trim((string) $_GET['tipo_imagem']) : '';
$funcaoId = isset($_GET['funcao_id']) ? (int) $_GET['funcao_id'] : 0;

$funcaoSequencia = [1, 8, 2, 3, 9, 4, 5, 6];
$funcaoIndice = [];
foreach ($funcaoSequencia as $idx => $fid) {
    $funcaoIndice[(int) $fid] = $idx;
}

if ($obraId <= 0) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Parâmetro obra_id inválido.'
    ]);
    exit;
}

$sql = "WITH status_inicio AS (
            SELECT
                hi.imagem_id,
                hi.status_id,
                hi.substatus_id,
                hi.data_movimento,
                CASE
                    WHEN LAG(hi.status_id) OVER (
                        PARTITION BY hi.imagem_id
                        ORDER BY hi.data_movimento, hi.idhistorico
                    ) IS NULL THEN 1
                    WHEN LAG(hi.status_id) OVER (
                        PARTITION BY hi.imagem_id
                        ORDER BY hi.data_movimento, hi.idhistorico
                    ) <> hi.status_id THEN 1
                    ELSE 0
                END AS novo_status
            FROM historico_imagens hi
            INNER JOIN imagens_cliente_obra ico
                ON ico.idimagens_cliente_obra = hi.imagem_id
            WHERE ico.obra_id = ?
        ),
        etapa_periodo AS (
            SELECT
                si.imagem_id,
                si.status_id,
                si.data_movimento AS inicio_etapa,
                COALESCE(
                    (
                        SELECT MIN(hf.data_movimento)
                        FROM historico_imagens hf
                        WHERE hf.imagem_id = si.imagem_id
                          AND hf.status_id = si.status_id
                          AND hf.data_movimento >= si.data_movimento
                          AND hf.substatus_id IN (6, 9)
                    ),
                    (
                        SELECT DATE_SUB(MIN(ns.data_movimento), INTERVAL 1 SECOND)
                        FROM status_inicio ns
                        WHERE ns.imagem_id = si.imagem_id
                          AND ns.novo_status = 1
                          AND ns.data_movimento > si.data_movimento
                    ),
                    NOW()
                ) AS fim_etapa
            FROM status_inicio si
            WHERE si.novo_status = 1
        ),
        funcao_origem AS (
            SELECT
                fi.idfuncao_imagem,
                fi.imagem_id,
                fi.funcao_id,
                fi.status AS status_funcao_atual,
                ico.data_inicio,
                ico.recebimento_arquivos,
                fi.prazo AS prazo_funcao,
                MIN(CASE
                    WHEN la.status_novo IS NOT NULL
                     AND la.status_novo <> ''
                     AND la.status_novo <> 'Não iniciado'
                    THEN la.data
                END) AS inicio_log,
                MAX(CASE
                    WHEN la.status_novo IN ('Finalizado', 'Aprovado', 'Aprovado com Ajustes')
                    THEN la.data
                END) AS fim_log
            FROM funcao_imagem fi
            INNER JOIN imagens_cliente_obra ico
                ON ico.idimagens_cliente_obra = fi.imagem_id
            LEFT JOIN log_alteracoes la
                ON la.funcao_imagem_id = fi.idfuncao_imagem
            WHERE ico.obra_id = ?
            GROUP BY
                fi.idfuncao_imagem,
                fi.imagem_id,
                fi.funcao_id,
                fi.status,
                ico.data_inicio,
                ico.recebimento_arquivos,
                fi.prazo
        ),
        funcao_periodo AS (
            SELECT
                fo.idfuncao_imagem,
                fo.imagem_id,
                fo.funcao_id,
                COALESCE(
                    fo.inicio_log,
                    CASE
                        WHEN fo.status_funcao_atual IS NOT NULL
                         AND fo.status_funcao_atual <> 'Não iniciado'
                        THEN CAST(COALESCE(fo.data_inicio, fo.recebimento_arquivos) AS DATETIME)
                    END
                ) AS inicio_funcao,
                COALESCE(
                    fo.fim_log,
                    CASE
                        WHEN fo.status_funcao_atual IN ('Finalizado', 'Aprovado', 'Aprovado com Ajustes')
                         AND fo.prazo_funcao IS NOT NULL
                        THEN CAST(CONCAT(fo.prazo_funcao, ' 23:59:59') AS DATETIME)
                    END
                ) AS fim_funcao
            FROM funcao_origem fo
        ),
        timeline AS (
            SELECT
                ico.idimagens_cliente_obra AS imagem_id,
                ico.imagem_nome,
                ico.obra_id,
                ico.tipo_imagem,
                ep.status_id,
                si.nome_status AS status_nome,
                fp.funcao_id,
                f.nome_funcao,
                GREATEST(ep.inicio_etapa, fp.inicio_funcao) AS inicio_no_status,
                LEAST(ep.fim_etapa, COALESCE(fp.fim_funcao, NOW())) AS fim_no_status
            FROM imagens_cliente_obra ico
            INNER JOIN etapa_periodo ep
                ON ep.imagem_id = ico.idimagens_cliente_obra
            INNER JOIN funcao_periodo fp
                ON fp.imagem_id = ico.idimagens_cliente_obra
            LEFT JOIN status_imagem si
                ON si.idstatus = ep.status_id
            LEFT JOIN funcao f
                ON f.idfuncao = fp.funcao_id
            WHERE ico.obra_id = ?
              AND fp.inicio_funcao IS NOT NULL
              AND GREATEST(ep.inicio_etapa, fp.inicio_funcao)
                  <= LEAST(ep.fim_etapa, COALESCE(fp.fim_funcao, NOW()))
        )
        SELECT
                        obra_id,
                        status_id,
                        status_nome,
            tipo_imagem,
            funcao_id,
            nome_funcao,
                        MIN(inicio_no_status) AS inicio_no_status,
                        MAX(fim_no_status) AS fim_no_status,
                        TIMESTAMPDIFF(DAY, MIN(inicio_no_status), MAX(fim_no_status)) + 1 AS duracao_dias,
                        COUNT(DISTINCT imagem_id) AS total_imagens,
                        COUNT(*) AS total_registros
        FROM timeline
                WHERE (? = 0 OR status_id = ?)
                    AND (? = '' OR tipo_imagem = ?)
                    AND (? = 0 OR funcao_id = ?)
                GROUP BY obra_id, status_id, status_nome, tipo_imagem, funcao_id, nome_funcao
                ORDER BY status_id, tipo_imagem, nome_funcao";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Falha ao preparar consulta da timeline.',
        'details' => $conn->error
    ]);
    exit;
}

$stmt->bind_param(
    'iiiiissii',
    $obraId,
    $obraId,
    $obraId,
    $etapaId,
    $etapaId,
    $tipoImagem,
    $tipoImagem,
    $funcaoId,
    $funcaoId
);

$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$minDate = null;
$maxDate = null;

while ($row = $result->fetch_assoc()) {
    $inicio = $row['inicio_no_status'];
    $fim = $row['fim_no_status'];

    if ($inicio && ($minDate === null || $inicio < $minDate)) {
        $minDate = $inicio;
    }

    if ($fim && ($maxDate === null || $fim > $maxDate)) {
        $maxDate = $fim;
    }

    $rows[] = [
        'obra_id' => (int) $row['obra_id'],
        'tipo_imagem' => $row['tipo_imagem'] ?: 'Sem tipo',
        'status_id' => (int) $row['status_id'],
        'status_nome' => $row['status_nome'] ?: 'Sem status',
        'funcao_id' => (int) $row['funcao_id'],
        'nome_funcao' => $row['nome_funcao'] ?: 'Sem função',
        'inicio_no_status' => $inicio,
        'fim_no_status' => $fim,
        'duracao_dias' => max(1, (int) $row['duracao_dias']),
        'total_imagens' => (int) $row['total_imagens'],
        'total_registros' => (int) $row['total_registros']
    ];
}

$stmt->close();

$sqlEtapas = "SELECT DISTINCT s.idstatus, s.nome_status
              FROM historico_imagens hi
              INNER JOIN imagens_cliente_obra ico
                  ON ico.idimagens_cliente_obra = hi.imagem_id
              INNER JOIN status_imagem s
                  ON s.idstatus = hi.status_id
              WHERE ico.obra_id = ?
              ORDER BY s.idstatus";
$stmtEtapas = $conn->prepare($sqlEtapas);
$etapas = [];
if ($stmtEtapas) {
    $stmtEtapas->bind_param('i', $obraId);
    $stmtEtapas->execute();
    $resEtapas = $stmtEtapas->get_result();
    while ($e = $resEtapas->fetch_assoc()) {
        $etapas[] = [
            'id' => (int) $e['idstatus'],
            'nome' => $e['nome_status']
        ];
    }
    $stmtEtapas->close();
}

$sqlTipos = "SELECT DISTINCT tipo_imagem
             FROM imagens_cliente_obra
             WHERE obra_id = ?
               AND tipo_imagem IS NOT NULL
               AND tipo_imagem <> ''
             ORDER BY tipo_imagem";
$stmtTipos = $conn->prepare($sqlTipos);
$tipos = [];
if ($stmtTipos) {
    $stmtTipos->bind_param('i', $obraId);
    $stmtTipos->execute();
    $resTipos = $stmtTipos->get_result();
    while ($t = $resTipos->fetch_assoc()) {
        $tipos[] = $t['tipo_imagem'];
    }
    $stmtTipos->close();
}

$sqlFuncoes = "SELECT DISTINCT f.idfuncao, f.nome_funcao
               FROM funcao_imagem fi
               INNER JOIN imagens_cliente_obra ico
                   ON ico.idimagens_cliente_obra = fi.imagem_id
               INNER JOIN funcao f
                   ON f.idfuncao = fi.funcao_id
               WHERE ico.obra_id = ?
               ORDER BY f.nome_funcao";
$stmtFuncoes = $conn->prepare($sqlFuncoes);
$funcoes = [];
if ($stmtFuncoes) {
    $stmtFuncoes->bind_param('i', $obraId);
    $stmtFuncoes->execute();
    $resFuncoes = $stmtFuncoes->get_result();
    while ($f = $resFuncoes->fetch_assoc()) {
        $funcoes[] = [
            'id' => (int) $f['idfuncao'],
            'nome' => $f['nome_funcao']
        ];
    }
    $stmtFuncoes->close();
}

// Backfill orientado por sequência: [1,8,2,3,9,4,5,6]
// Para cada grupo (etapa + tipo), se a primeira função existente estiver adiantada,
// busca funções anteriores faltantes no log_alteracoes.
if ($funcaoId === 0) {
    $grupos = [];

    $primeiroStatusPorTipo = [];
    $sqlPrimeiroStatus = "SELECT
            ico.tipo_imagem,
            MIN(hi.status_id) AS primeiro_status_id
        FROM historico_imagens hi
        INNER JOIN imagens_cliente_obra ico
            ON ico.idimagens_cliente_obra = hi.imagem_id
        WHERE ico.obra_id = ?
          AND ico.tipo_imagem IS NOT NULL
          AND ico.tipo_imagem <> ''
        GROUP BY ico.tipo_imagem";

    $stmtPrimeiroStatus = $conn->prepare($sqlPrimeiroStatus);
    if ($stmtPrimeiroStatus) {
        $stmtPrimeiroStatus->bind_param('i', $obraId);
        $stmtPrimeiroStatus->execute();
        $resPrimeiroStatus = $stmtPrimeiroStatus->get_result();
        while ($ps = $resPrimeiroStatus->fetch_assoc()) {
            $tipoKeyPs = (string) $ps['tipo_imagem'];
            $primeiroStatusPorTipo[$tipoKeyPs] = (int) $ps['primeiro_status_id'];
        }
        $stmtPrimeiroStatus->close();
    }

    foreach ($rows as $row) {
        $chaveGrupo = $row['status_id'] . '||' . $row['tipo_imagem'];
        if (!isset($grupos[$chaveGrupo])) {
            $grupos[$chaveGrupo] = [
                'status_id' => (int) $row['status_id'],
                'status_nome' => $row['status_nome'] ?: 'Sem status',
                'tipo_imagem' => $row['tipo_imagem'],
                'funcoes' => []
            ];
        }
        $grupos[$chaveGrupo]['funcoes'][(int) $row['funcao_id']] = true;
    }

    $novos = [];
    foreach ($grupos as $grupo) {
        $tipoKey = (string) $grupo['tipo_imagem'];

        // Backfill apenas no primeiro status disponível daquele tipo de imagem
        if (isset($primeiroStatusPorTipo[$tipoKey]) && (int) $grupo['status_id'] !== (int) $primeiroStatusPorTipo[$tipoKey]) {
            continue;
        }

        if ($etapaId > 0 && (int) $grupo['status_id'] !== $etapaId) {
            continue;
        }

        $funcoesPresentes = array_keys($grupo['funcoes']);
        $menorIndicePresente = null;

        foreach ($funcoesPresentes as $fid) {
            if (!isset($funcaoIndice[(int) $fid])) {
                continue;
            }
            $indiceAtual = $funcaoIndice[(int) $fid];
            if ($menorIndicePresente === null || $indiceAtual < $menorIndicePresente) {
                $menorIndicePresente = $indiceAtual;
            }
        }

        if ($menorIndicePresente === null || $menorIndicePresente <= 0) {
            continue;
        }

        $faltantesAnteriores = array_slice($funcaoSequencia, 0, $menorIndicePresente);
        $faltantesAnteriores = array_values(array_filter($faltantesAnteriores, function ($fid) use ($grupo) {
            return !isset($grupo['funcoes'][(int) $fid]);
        }));

        if (empty($faltantesAnteriores)) {
            continue;
        }

        $placeholders = implode(',', array_fill(0, count($faltantesAnteriores), '?'));

        $sqlBackfill = "SELECT
                ico.tipo_imagem,
                fi.funcao_id,
                f.nome_funcao,
                MIN(CASE
                    WHEN la.status_novo IS NOT NULL
                     AND la.status_novo <> ''
                     AND la.status_novo <> 'Não iniciado'
                    THEN la.data
                END) AS inicio_log,
                MAX(CASE
                    WHEN la.status_novo IN ('Finalizado', 'Aprovado', 'Aprovado com Ajustes')
                    THEN la.data
                END) AS fim_log,
                MAX(la.data) AS ultimo_log,
                COUNT(DISTINCT ico.idimagens_cliente_obra) AS total_imagens
            FROM funcao_imagem fi
            INNER JOIN imagens_cliente_obra ico
                ON ico.idimagens_cliente_obra = fi.imagem_id
            INNER JOIN log_alteracoes la
                ON la.funcao_imagem_id = fi.idfuncao_imagem
            LEFT JOIN funcao f
                ON f.idfuncao = fi.funcao_id
            WHERE ico.obra_id = ?
              AND ico.tipo_imagem = ?
              AND fi.funcao_id IN ($placeholders)
            GROUP BY ico.tipo_imagem, fi.funcao_id, f.nome_funcao";

        $stmtBackfill = $conn->prepare($sqlBackfill);
        if (!$stmtBackfill) {
            continue;
        }

        $tiposBind = 'is' . str_repeat('i', count($faltantesAnteriores));
        $params = array_merge([$obraId, $grupo['tipo_imagem']], array_map('intval', $faltantesAnteriores));

        $bindValues = [];
        $bindValues[] = &$tiposBind;
        foreach ($params as $k => $v) {
            $bindValues[] = &$params[$k];
        }

        call_user_func_array([$stmtBackfill, 'bind_param'], $bindValues);
        $stmtBackfill->execute();
        $resBackfill = $stmtBackfill->get_result();

        while ($b = $resBackfill->fetch_assoc()) {
            $inicio = $b['inicio_log'] ?: $b['ultimo_log'];
            $fim = $b['fim_log'] ?: $b['ultimo_log'];

            if (!$inicio || !$fim) {
                continue;
            }

            $jaExiste = false;
            foreach ($rows as $r) {
                if (
                    (int) $r['status_id'] === (int) $grupo['status_id']
                    && (string) $r['tipo_imagem'] === (string) ($b['tipo_imagem'] ?: 'Sem tipo')
                    && (int) $r['funcao_id'] === (int) $b['funcao_id']
                ) {
                    $jaExiste = true;
                    break;
                }
            }

            if ($jaExiste) {
                continue;
            }

            $duracao = max(1, (int) floor((strtotime($fim) - strtotime($inicio)) / 86400) + 1);

            $novo = [
                'obra_id' => $obraId,
                'tipo_imagem' => $b['tipo_imagem'] ?: 'Sem tipo',
                'status_id' => (int) $grupo['status_id'],
                'status_nome' => $grupo['status_nome'] ?: 'Sem status',
                'funcao_id' => (int) $b['funcao_id'],
                'nome_funcao' => $b['nome_funcao'] ?: 'Sem função',
                'inicio_no_status' => $inicio,
                'fim_no_status' => $fim,
                'duracao_dias' => $duracao,
                'total_imagens' => (int) $b['total_imagens'],
                'total_registros' => 0,
                'inferred' => true,
                'source' => 'log_alteracoes_backfill'
            ];

            $novos[] = $novo;

            if ($minDate === null || $inicio < $minDate) {
                $minDate = $inicio;
            }
            if ($maxDate === null || $fim > $maxDate) {
                $maxDate = $fim;
            }
        }

        $stmtBackfill->close();
    }

    if (!empty($novos)) {
        $rows = array_merge($rows, $novos);

        usort($rows, function ($a, $b) use ($funcaoIndice) {
            if ((int) $a['status_id'] !== (int) $b['status_id']) {
                return (int) $a['status_id'] <=> (int) $b['status_id'];
            }

            $tipoCmp = strcmp((string) $a['tipo_imagem'], (string) $b['tipo_imagem']);
            if ($tipoCmp !== 0) {
                return $tipoCmp;
            }

            $idxA = $funcaoIndice[(int) $a['funcao_id']] ?? 999;
            $idxB = $funcaoIndice[(int) $b['funcao_id']] ?? 999;
            if ($idxA !== $idxB) {
                return $idxA <=> $idxB;
            }

            return strcmp((string) $a['inicio_no_status'], (string) $b['inicio_no_status']);
        });
    }
}

echo json_encode([
    'ok' => true,
    'filters' => [
        'etapas' => $etapas,
        'tipos_imagem' => $tipos,
        'funcoes' => $funcoes
    ],
    'meta' => [
        'total_itens' => count($rows),
        'inicio_geral' => $minDate,
        'fim_geral' => $maxDate
    ],
    'timeline' => $rows,
    'funcao_ordem' => $funcaoSequencia
]);
