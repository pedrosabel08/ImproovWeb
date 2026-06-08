<?php

function heatmap_normalize_filters(array $source): array
{
    $mes = isset($source['mes']) ? (int) $source['mes'] : (int) date('m');
    $ano = isset($source['ano']) ? (int) $source['ano'] : (int) date('Y');
    $funcaoId = isset($source['funcao_id']) ? (int) $source['funcao_id'] : 0;
    $tipoImagem = isset($source['tipo_imagem']) ? trim((string) $source['tipo_imagem']) : '';

    if ($mes < 1 || $mes > 12 || $ano < 2020) {
        $mes = (int) date('m');
        $ano = (int) date('Y');
    }

    return [
        'mes' => $mes,
        'ano' => $ano,
        'funcao_id' => max(0, $funcaoId),
        'tipo_imagem' => $tipoImagem,
    ];
}

function heatmap_build_optional_filter_sql(int $funcaoId, string $tipoImagem, string $functionAlias = 'fi', string $imageAlias = 'ico'): array
{
    $where = '';
    $types = '';
    $values = [];

    if ($funcaoId > 0) {
        $where .= " AND {$functionAlias}.funcao_id = ?";
        $types .= 'i';
        $values[] = $funcaoId;
    }

    if ($tipoImagem !== '') {
        $where .= " AND {$imageAlias}.tipo_imagem = ?";
        $types .= 's';
        $values[] = $tipoImagem;
    }

    return [$where, $types, $values];
}

function heatmap_fetch_dataset(mysqli $conn, int $mes, int $ano, int $funcaoId = 0, string $tipoImagem = ''): array
{
    $joinBase = "
        JOIN funcao_imagem fi ON fi.idfuncao_imagem = la.funcao_imagem_id
        JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
    ";

    $whereBase = "
        LOWER(TRIM(la.status_novo)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
        AND fi.colaborador_id NOT IN (21, 15)
        AND ico.obra_id != 74
        AND NOT (fi.funcao_id = 4 AND fi.colaborador_id IN (7, 34))
    ";

    [$extraWhere, $extraTypes, $extraVals] = heatmap_build_optional_filter_sql($funcaoId, $tipoImagem);

    $sqlDias = "
        SELECT DATE(la.data) AS dia,
               COUNT(DISTINCT fi.idfuncao_imagem) AS total
        FROM log_alteracoes la
        {$joinBase}
        WHERE YEAR(la.data) = ? AND MONTH(la.data) = ?
          AND {$whereBase}
          {$extraWhere}
        GROUP BY DATE(la.data)
        ORDER BY dia ASC
    ";

    $typesQ1 = 'ii' . $extraTypes;
    $paramsQ1 = array_merge([$ano, $mes], $extraVals);
    $stmtD = $conn->prepare($sqlDias);
    $rowsDias = [];
    if ($stmtD) {
        $stmtD->bind_param($typesQ1, ...$paramsQ1);
        $stmtD->execute();
        $rowsDias = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtD->close();
    }

    $porDia = [];
    foreach ($rowsDias as $row) {
        $porDia[$row['dia']] = (int) $row['total'];
    }

    $inicioHistorico = date('Y-m-d', mktime(0, 0, 0, $mes - 6, 1, $ano));
    $fimHistorico = date('Y-m-d', mktime(0, 0, 0, $mes, 0, $ano));
    $diasPeriodo = max(1, (int) round((strtotime($fimHistorico) - strtotime($inicioHistorico)) / 86400) + 1);

    $sqlMedia = "
        SELECT COUNT(DISTINCT fi.idfuncao_imagem) AS total_hist
        FROM log_alteracoes la
        {$joinBase}
        WHERE la.data >= ? AND la.data <= ?
          AND {$whereBase}
          {$extraWhere}
    ";

    $typesQ2 = 'ss' . $extraTypes;
    $paramsQ2 = array_merge([$inicioHistorico, $fimHistorico], $extraVals);
    $stmtM = $conn->prepare($sqlMedia);
    $totalHist = 0;
    if ($stmtM) {
        $stmtM->bind_param($typesQ2, ...$paramsQ2);
        $stmtM->execute();
        $totalHist = (int) ($stmtM->get_result()->fetch_assoc()['total_hist'] ?? 0);
        $stmtM->close();
    }

    $mediaDiaria = round($totalHist / $diasPeriodo, 2);
    $t1 = max(1, (int) floor($mediaDiaria));
    $t2 = max($t1 + 1, (int) floor($mediaDiaria * 2));

    return [
        'por_dia' => $porDia,
        'media_diaria' => $mediaDiaria,
        't1' => $t1,
        't2' => $t2,
        'mes' => $mes,
        'ano' => $ano,
    ];
}

function heatmap_fetch_filter_options(mysqli $conn): array
{
    $sqlFuncoes = "
        SELECT DISTINCT f.idfuncao AS id, f.nome_funcao AS nome
        FROM funcao f
        JOIN funcao_imagem fi ON fi.funcao_id = f.idfuncao
        WHERE fi.colaborador_id NOT IN (21, 15)
        ORDER BY f.nome_funcao
    ";
    $resFuncoes = $conn->query($sqlFuncoes);
    $funcoes = $resFuncoes ? $resFuncoes->fetch_all(MYSQLI_ASSOC) : [];

    $sqlTipos = "
        SELECT DISTINCT tipo_imagem
        FROM imagens_cliente_obra
        WHERE tipo_imagem IS NOT NULL AND tipo_imagem <> ''
        ORDER BY tipo_imagem
    ";
    $resTipos = $conn->query($sqlTipos);
    $tiposImagem = [];
    if ($resTipos) {
        while ($row = $resTipos->fetch_assoc()) {
            $tiposImagem[] = $row['tipo_imagem'];
        }
    }

    return [
        'funcoes' => $funcoes,
        'tipos_imagem' => $tiposImagem,
    ];
}

function heatmap_fetch_response(mysqli $conn, array $filters): array
{
    $normalized = heatmap_normalize_filters($filters);
    $dataset = heatmap_fetch_dataset(
        $conn,
        $normalized['mes'],
        $normalized['ano'],
        $normalized['funcao_id'],
        $normalized['tipo_imagem']
    );
    $options = heatmap_fetch_filter_options($conn);

    return array_merge($dataset, $options);
}
