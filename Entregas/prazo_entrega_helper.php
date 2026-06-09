<?php

function entregas_valid_date(?string $date): bool
{
    if (!$date) {
        return false;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

function entregas_table_has_column(mysqli $conn, string $table, string $column): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }

    $tableEscaped = $conn->real_escape_string($table);
    $columnEscaped = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$tableEscaped}` LIKE '{$columnEscaped}'");

    return $result && $result->num_rows > 0;
}

function entregas_ensure_data_recebimento_schema(mysqli $conn): void
{
    if (!entregas_table_has_column($conn, 'entregas', 'data_recebimento')) {
        $conn->query(
            "ALTER TABLE entregas
             ADD COLUMN data_recebimento DATE NULL AFTER obra_id"
        );
    }
}

function entregas_feriados_moveis(int $ano): array
{
    $pascoa = easter_date($ano);

    return [
        date('Y-m-d', $pascoa),
        date('Y-m-d', strtotime('-2 days', $pascoa)),
        date('Y-m-d', strtotime('+60 days', $pascoa)),
        date('Y-m-d', strtotime('+47 days', $pascoa)),
        date('Y-m-d', strtotime('-48 days', $pascoa)),
        date('Y-m-d', strtotime('-49 days', $pascoa)),
    ];
}

function entregas_adicionar_dias_uteis(string $dataInicial, int $diasUteis): string
{
    $diasAdicionados = 0;
    $data = strtotime($dataInicial);
    $feriadosFixos = ['01-01', '04-21', '05-01', '09-07', '10-12', '11-02', '11-15', '12-25'];

    while ($diasAdicionados < $diasUteis) {
        $data = strtotime('+1 day', $data);
        $diaSemana = (int) date('N', $data);
        $mesDia = date('m-d', $data);
        $ano = (int) date('Y', $data);

        if ($diaSemana >= 6) {
            continue;
        }

        if (in_array($mesDia, $feriadosFixos, true)) {
            continue;
        }

        if (in_array(date('Y-m-d', $data), entregas_feriados_moveis($ano), true)) {
            continue;
        }

        $diasAdicionados++;
    }

    return date('Y-m-d', $data);
}

function entregas_buscar_codigo_etapa(mysqli $conn, int $statusId): string
{
    $stmt = $conn->prepare('SELECT nome_status FROM status_imagem WHERE idstatus = ? LIMIT 1');
    if (!$stmt) {
        return '';
    }

    $stmt->bind_param('i', $statusId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return strtoupper(trim((string) ($row['nome_status'] ?? '')));
}

function entregas_buscar_prazo_contratual_still(mysqli $conn, int $obraId): ?int
{
    $stmt = $conn->prepare(
        "SELECT prazo_contratual
         FROM obra_pacote
         WHERE obra_id = ?
           AND tipo = 'STILL'
           AND prazo_contratual IS NOT NULL
         ORDER BY
           CASE status
             WHEN 'ATIVO' THEN 0
             WHEN 'HOLD' THEN 1
             WHEN 'CONCLUIDO' THEN 2
             ELSE 3
           END,
           idobra_pacote DESC
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || $row['prazo_contratual'] === null) {
        return null;
    }

    $prazo = (int) $row['prazo_contratual'];
    return $prazo > 0 ? $prazo : null;
}

function entregas_calcular_prazo_previsto(mysqli $conn, int $obraId, int $statusId, string $dataRecebimento): ?array
{
    if ($obraId <= 0 || $statusId <= 0 || !entregas_valid_date($dataRecebimento)) {
        return null;
    }

    $codigoEtapa = entregas_buscar_codigo_etapa($conn, $statusId);
    $codigoNormalizado = preg_replace('/[^A-Z0-9]/', '', $codigoEtapa);

    if ($statusId === 2 || $codigoNormalizado === 'R00') {
        $prazoContratual = entregas_buscar_prazo_contratual_still($conn, $obraId);
        if ($prazoContratual === null) {
            return null;
        }

        return [
            'data_prevista' => entregas_adicionar_dias_uteis($dataRecebimento, $prazoContratual),
            'tipo_calculo' => 'prazo_contratual_still',
            'dias_uteis' => $prazoContratual,
        ];
    }

    $etapasSeteDias = [3, 4, 5, 6, 14, 15];
    if (in_array($statusId, $etapasSeteDias, true) || preg_match('/^(R0[1-5]|EF)$/', $codigoNormalizado)) {
        return [
            'data_prevista' => entregas_adicionar_dias_uteis($dataRecebimento, 7),
            'tipo_calculo' => 'sete_dias_uteis',
            'dias_uteis' => 7,
        ];
    }

    return null;
}
