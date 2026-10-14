<?php
/** Configuração por obra, independente de modelo e câmera. */
function mpe_coordinate($value, float $min, float $max, string $label): ?float
{
    if ($value === null || (is_string($value) && trim($value) === '')) return null;
    if (is_string($value)) $value = str_replace(',', '.', trim($value));
    if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)) {
        throw new InvalidArgumentException("$label deve ser uma coordenada decimal.");
    }
    $number = (float) $value;
    if (!is_finite($number) || $number < $min || $number > $max) {
        throw new InvalidArgumentException("$label deve estar entre $min e $max.");
    }
    return round($number, 7);
}

function mpe_timezone($value): string
{
    if (!is_string($value) || strlen($value) > 64 || !in_array(trim($value), DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
        throw new InvalidArgumentException('Informe um fuso horário IANA válido, como America/Sao_Paulo.');
    }
    return trim($value);
}

function mpe_values(array $input): array
{
    foreach (['latitude', 'longitude', 'norte_graus'] as $key) {
        if (!array_key_exists($key, $input)) throw new InvalidArgumentException('Informe todos os campos espaciais; use null para não definido.');
    }
    $north = $input['norte_graus'];
    if ($north !== null) {
        if ((!is_int($north) && !is_float($north)) || !is_finite((float) $north) || $north < 0 || $north >= 360) {
            throw new InvalidArgumentException('Norte deve estar entre 0° (inclusive) e 360° (exclusive).');
        }
        $north = fmod(round((float) $north, 1), 360.0);
    }
    $timezone = array_key_exists('timezone', $input) ? ['timezone' => mpe_timezone($input['timezone'])] : [];
    return $timezone + [
        'latitude' => mpe_coordinate($input['latitude'], -90, 90, 'Latitude'),
        'longitude' => mpe_coordinate($input['longitude'], -180, 180, 'Longitude'),
        'norte_graus' => $north,
    ];
}

function mpe_get(mysqli $conn, int $obra): array
{
    $row = mp_row($conn, 'SELECT latitude,longitude,norte_graus,timezone,versao FROM mapa_obra_configuracoes_espaciais WHERE obra_id=?', 'i', [$obra]);
    $result = ['obra_id' => $obra, 'latitude' => null, 'longitude' => null, 'norte_graus' => null, 'timezone' => 'America/Sao_Paulo', 'versao' => 0];
    if ($row) {
        foreach (['latitude', 'longitude', 'norte_graus'] as $key) $result[$key] = $row[$key] === null ? null : (float) $row[$key];
        $result['timezone'] = $row['timezone'];
        $result['versao'] = (int) $row['versao'];
    }
    return $result;
}

function mpe_save(mysqli $conn, int $obra, array $input, int $author): array
{
    $values = mpe_values($input);
    if (!isset($input['versao']) || !is_int($input['versao']) || $input['versao'] < 0) throw new InvalidArgumentException('Versão espacial inválida. Reabra as configurações.');
    $conn->begin_transaction();
    try {
        // Trava a obra para serializar inclusive a primeira criação.
        if (!mp_row($conn, 'SELECT idobra FROM obra WHERE idobra=? FOR UPDATE', 'i', [$obra])) throw new RuntimeException('Obra não encontrada.', 404);
        $current = mpe_get($conn, $obra);
        if ($current['versao'] !== $input['versao']) throw new RuntimeException('As configurações espaciais foram alteradas. Cancele e reabra para carregar a versão atual.', 409);
        $timezone = $values['timezone'] ?? $current['timezone'];
        if ($current['versao']) {
            mp_query($conn, 'UPDATE mapa_obra_configuracoes_espaciais SET latitude=?,longitude=?,norte_graus=?,timezone=?,versao=versao+1,atualizado_por=? WHERE obra_id=?', 'dddsii', [$values['latitude'], $values['longitude'], $values['norte_graus'], $timezone, $author, $obra])->close();
        } else {
            mp_query($conn, 'INSERT INTO mapa_obra_configuracoes_espaciais(obra_id,latitude,longitude,norte_graus,timezone,atualizado_por) VALUES(?,?,?,?,?,?)', 'idddsi', [$obra, $values['latitude'], $values['longitude'], $values['norte_graus'], $timezone, $author])->close();
        }
        $result = mpe_get($conn, $obra);
        $conn->commit();
        return $result;
    } catch (Throwable $error) { $conn->rollback(); throw $error; }
}
