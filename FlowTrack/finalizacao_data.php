<?php
require_once __DIR__ . '/../conexao.php';
header('Content-Type: application/json; charset=utf-8');

function jsonOut($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

try {
    if ($action === 'filters') {
        // Obras: infer from imagens_cliente_obra or your obras table if present
        $obras = [];
        $sqlObras = "SELECT DISTINCT obra_id, nomenclatura AS obra_nome FROM obra o 
        JOIN imagens_cliente_obra im ON o.idobra = im.obra_id WHERE im.status_id IN (1, 2) AND im.substatus_id <> 7 AND o.status_obra = 0 ORDER BY obra_nome";
        if ($res = mysqli_query($conn, $sqlObras)) {
            while ($row = mysqli_fetch_assoc($res)) {
                $obras[] = $row;
            }
            mysqli_free_result($res);
        }

        // Finalizadores: infer from funcao_imagem where funcao_id = 4 (finalização)
        $finalizadores = [];
        $sqlFin = "SELECT DISTINCT fi.colaborador_id AS usuario_id, c.nome_colaborador AS usuario_nome
               FROM funcao_imagem fi
               LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
               WHERE fi.funcao_id = 4 AND c.ativo = 1";
        if ($res = mysqli_query($conn, $sqlFin)) {
            while ($row = mysqli_fetch_assoc($res)) {
                $finalizadores[] = $row;
            }
            mysqli_free_result($res);
        }

        // statuses available for finalização (fi.status)
        $statuses = [];
        $sqlStatuses = "SELECT DISTINCT fi.status as st FROM funcao_imagem fi WHERE fi.funcao_id = 4 AND fi.status IS NOT NULL";
        if ($res = mysqli_query($conn, $sqlStatuses)) {
            while ($row = mysqli_fetch_assoc($res)) {
                $statuses[] = ['key' => $row['st'], 'label' => ucfirst($row['st'])];
            }
            mysqli_free_result($res);
        }

        // tipo_imagem available from imagens_cliente_obra
        $tipo_imagens = [];
        $sqlTipos = "SELECT DISTINCT ico.tipo_imagem as tipo FROM imagens_cliente_obra ico WHERE ico.tipo_imagem IS NOT NULL";
        if ($res = mysqli_query($conn, $sqlTipos)) {
            while ($row = mysqli_fetch_assoc($res)) {
                $tipo_imagens[] = ['tipo' => $row['tipo'], 'label' => $row['tipo']];
            }
            mysqli_free_result($res);
        }

        jsonOut(['obras' => $obras, 'finalizadores' => $finalizadores, 'statuses' => $statuses, 'tipo_imagens' => $tipo_imagens]);
    }

    // list items
    $obra_id = isset($_GET['obra_id']) ? trim($_GET['obra_id']) : '';
    $finalizador_id = isset($_GET['finalizador_id']) ? trim($_GET['finalizador_id']) : '';
    $etapa = isset($_GET['etapa']) ? trim($_GET['etapa']) : '';
    $status_funcao = isset($_GET['status_funcao']) ? trim($_GET['status_funcao']) : '';
    $tipo_imagem = isset($_GET['tipo_imagem']) ? trim($_GET['tipo_imagem']) : '';

    $params = [];
    $where = [];

    // etapa filter mandatory to P00/R00 scope, but allow blank for both
    $where[] = "ico.status_id IN (1,2)";
    $where[] = "fi.funcao_id = 4";
    if ($obra_id !== '') {
        $where[] = "ico.obra_id = ?";
        $params[] = $obra_id;
    }
    if ($finalizador_id !== '') {
        $where[] = "fi.colaborador_id = ?";
        $params[] = $finalizador_id;
    }
    if ($etapa !== '') {
        $where[] = "ico.status_id = ?";
        $params[] = $etapa;
    }
    if ($tipo_imagem !== '') {
        $where[] = "ico.tipo_imagem = ?";
        $params[] = $tipo_imagem;
    }
    if ($status_funcao !== '') {
        $where[] = "fi.status = ?";
        $params[] = $status_funcao;
    }

    $sql = "SELECT ico.idimagens_cliente_obra AS imagem_id,
                 ico.imagem_nome,
                 ico.tipo_imagem,
                 s.nome_status AS etapa,
                 fi.colaborador_id AS usuario_id,
                 c.nome_colaborador AS finalizador_nome,
                 fi.prazo,
                 fi.status AS status_funcao,
                 fi.observacao,
                 ico.obra_id,
                 o.nomenclatura AS obra_nome
          FROM imagens_cliente_obra ico
          INNER JOIN funcao_imagem fi ON fi.imagem_id = ico.idimagens_cliente_obra AND fi.funcao_id = 4
          LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
          LEFT JOIN obra o ON o.idobra = ico.obra_id
          LEFT JOIN status_imagem s ON s.idstatus = ico.status_id
          WHERE " . implode(' AND ', $where) . "
          ORDER BY ico.status_id, fi.prazo ASC";

    // Prepared statement for safety
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        throw new Exception('Erro preparando consulta');
    }

    if (count($params) > 0) {
        // all params are strings; adjust types if numeric
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $items = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
        mysqli_free_result($result);
    }
    mysqli_stmt_close($stmt);

    // build derived info: available finalizadores and statuses and KPIs
    $available_finalizadores = [];
    $available_statuses = [];
    $kpis = ['total' => 0, 'p00' => 0, 'r00' => 0, 'by_status' => []];

    foreach ($items as $it) {
        $kpis['total']++;
        $et = isset($it['etapa']) ? $it['etapa'] : '';
        if (strtoupper($et) === 'P00')
            $kpis['p00']++;
        if (strtoupper($et) === 'R00')
            $kpis['r00']++;

        // finalizadores
        if (!empty($it['usuario_id'])) {
            $available_finalizadores[$it['usuario_id']] = [
                'usuario_id' => $it['usuario_id'],
                'usuario_nome' => $it['finalizador_nome'] ?? ''
            ];
        }

        // statuses
        $st = $it['status_funcao'] ?? '';
        if ($st !== '') {
            $available_statuses[$st] = ['key' => $st, 'label' => ucfirst($st)];
            if (!isset($kpis['by_status'][$st]))
                $kpis['by_status'][$st] = 0;
            $kpis['by_status'][$st]++;
        }
    }

    // reindex available lists
    $available_finalizadores = array_values($available_finalizadores);
    $available_statuses = array_values($available_statuses);

    jsonOut(['items' => $items, 'available_finalizadores' => $available_finalizadores, 'available_statuses' => $available_statuses, 'kpis' => $kpis]);

} catch (Throwable $e) {
    http_response_code(500);
    jsonOut(['error' => true, 'message' => $e->getMessage()]);
}
