<?php
// status_tipo_report.php
// Agrupa métricas de tempo entre um status inicial e um substatus destino por status_id -> tipo_imagem
// Uso: status_tipo_report.php?end_substatus=6&start_status=2&tipo_imagem=Fachada

require_once __DIR__ . '/../../conexao.php';

// params
$start_status_param = isset($_GET['start_status']) && is_numeric($_GET['start_status']) ? (int) $_GET['start_status'] : null;
$filter_tipo = isset($_GET['tipo_imagem']) ? trim($_GET['tipo_imagem']) : '';

// mapping rule: for status_id 1 and 6 use end_substatus = 9, otherwise 6
function map_end_substatus_for_status($status_id) {
    if ($status_id === 1 || $status_id === 6) return 9;
    return 6;
}

function get_status_name($conn, $status_id) {
    $id = (int)$status_id;
    $qr = $conn->query("SELECT nome_status FROM status_imagem WHERE idstatus = " . $id . " LIMIT 1");
    if ($qr && $r = $qr->fetch_assoc()) return $r['nome_status'];
    return null;
}

function get_substatus_name($conn, $substatus_id) {
    $id = (int)$substatus_id;
    $qr = $conn->query("SELECT nome_substatus FROM substatus_imagem WHERE id = " . $id . " LIMIT 1");
    if ($qr && $r = $qr->fetch_assoc()) return $r['nome_substatus'];
    return null;
}

function run_query_for_status($conn, $start_status, $end_substatus, $filter_tipo)
{
    // build optional tipo filter
    $tipo_sql = '';
    if ($filter_tipo !== '') {
        $tipo_esc = $conn->real_escape_string($filter_tipo);
        $tipo_sql = "AND ico.tipo_imagem = '" . $tipo_esc . "'";
    }

    $sql = "WITH inicio AS (
        SELECT hi.imagem_id, MIN(hi.data_movimento) AS data_inicio
        FROM historico_imagens hi
        WHERE hi.status_id = " . (int) $start_status . "
        GROUP BY hi.imagem_id
    ),
    fim AS (
        SELECT hi.imagem_id, MIN(hi.data_movimento) AS data_fim
        FROM historico_imagens hi
        JOIN inicio i ON i.imagem_id = hi.imagem_id AND hi.data_movimento > i.data_inicio
        WHERE hi.substatus_id = " . (int) $end_substatus . "
        GROUP BY hi.imagem_id
    ),
    tempos AS (
        SELECT ico.idimagens_cliente_obra AS imagem_id, ico.tipo_imagem,
            TIMESTAMPDIFF(HOUR, i.data_inicio, f.data_fim) AS tempo_horas
        FROM imagens_cliente_obra ico
        JOIN inicio i ON i.imagem_id = ico.idimagens_cliente_obra
        JOIN fim f ON f.imagem_id = ico.idimagens_cliente_obra
        WHERE TIMESTAMPDIFF(HOUR, i.data_inicio, f.data_fim) > 0
        $tipo_sql
    ),
    ordenado AS (
        SELECT tipo_imagem, tempo_horas,
            ROW_NUMBER() OVER (PARTITION BY tipo_imagem ORDER BY tempo_horas) AS rn,
            COUNT(*) OVER (PARTITION BY tipo_imagem) AS total
        FROM tempos
    )
    SELECT tipo_imagem,
        COUNT(*) AS total_imagens,
        ROUND(AVG(tempo_horas), 1) AS media_horas,
        ROUND(
            AVG(
                CASE
                    WHEN rn IN (
                        FLOOR((total + 1) / 2),
                        CEIL((total + 1) / 2)
                    )
                    THEN tempo_horas
                END
            ), 1
        ) AS mediana_p50,
        MAX(CASE WHEN rn <= total * 0.75 THEN tempo_horas END) AS p75,
        MAX(CASE WHEN rn <= total * 0.90 THEN tempo_horas END) AS p90
    FROM ordenado
    GROUP BY tipo_imagem
    ORDER BY total_imagens DESC";

    $res = $conn->query($sql);
    if (!$res) {
        return ['error' => $conn->error, 'sql' => $sql];
    }
    $rows = [];
    while ($r = $res->fetch_assoc())
        $rows[] = $r;
    return $rows;
}

// get statuses to report
$statuses = [];
if ($start_status_param !== null) {
    $statuses[] = $start_status_param;
} else {
    $qr = $conn->query("SELECT DISTINCT status_id FROM historico_imagens WHERE status_id IS NOT NULL ORDER BY status_id");
    while ($r = $qr->fetch_assoc())
        $statuses[] = (int) $r['status_id'];
}

?><!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Relatório por status → tipo_imagem</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            padding: 18px;
            color: #222
        }

        h2 {
            color: #16325c
        }

        table {
            border-collapse: collapse;
            width: 100%;
            max-width: 980px;
            margin-bottom: 18px
        }

        th,
        td {
            border: 1px solid #e6eef9;
            padding: 8px;
            text-align: left
        }

        th {
            background: #f4f8ff
        }

        .meta {
            color: #666;
            font-size: 13px;
            margin-bottom: 12px
        }
    </style>
</head>

<body>
    <h1>Relatório: tempo entre status → substatus por `tipo_imagem`</h1>
    <div class="meta">Parâmetros:
        end_substatus=<?php echo htmlspecialchars($end_substatus); ?><?php if ($filter_tipo)
               echo ' — tipo_imagem=' . htmlspecialchars($filter_tipo); ?>
    </div>

    <?php
    foreach ($statuses as $st) {
        $end_sub = map_end_substatus_for_status($st);
        $status_name = get_status_name($conn, $st) ?: $st;
        $substatus_name = get_substatus_name($conn, $end_sub) ?: $end_sub;
        echo "<h2>Status inicial: " . htmlspecialchars($status_name) . " (id=" . htmlspecialchars($st) . ") → Substatus alvo: " . htmlspecialchars($substatus_name) . " (id=" . htmlspecialchars($end_sub) . ")</h2>\n";
        $data = run_query_for_status($conn, $st, $end_sub, $filter_tipo);
        if (isset($data['error'])) {
            echo "<div style=\"color:red\">Erro: " . htmlspecialchars($data['error']) . "</div>\n";
            continue;
        }
        if (count($data) === 0) {
            echo "<div>Nenhum registro encontrado para este status.</div>\n";
            continue;
        }

        echo "<table>\n<thead><tr><th>tipo_imagem</th><th>total_imagens</th><th>média (h)</th><th>mediana P50 (h)</th><th>P75 (h)</th><th>P90 (h)</th></tr></thead>\n<tbody>\n";
        foreach ($data as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['tipo_imagem']) . '</td>';
            echo '<td>' . htmlspecialchars($row['total_imagens']) . '</td>';
            echo '<td>' . htmlspecialchars($row['media_horas']) . '</td>';
            echo '<td>' . htmlspecialchars($row['mediana_p50']) . '</td>';
            echo '<td>' . htmlspecialchars($row['p75']) . '</td>';
            echo '<td>' . htmlspecialchars($row['p90']) . '</td>';
            echo '</tr>\n';
        }
        echo "</tbody></table>\n";
    }

    ?>
</body>

</html>