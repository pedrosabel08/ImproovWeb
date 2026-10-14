<?php
// CLI: php scripts/migrate_mapa_espacial.php [--obra=74 --latitude=-27.0992256 --longitude=-48.6179817]
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/referencia_arquitetonica_helper.php';
require_once __DIR__ . '/../helpers/mapa_espacial_helper.php';
$options = getopt('', ['obra:', 'latitude:', 'longitude:']);
$seed = null;
if ($options) {
    $obra = filter_var($options['obra'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$obra || !isset($options['latitude'], $options['longitude'])) throw new InvalidArgumentException('Use --obra=ID --latitude=DECIMAL --longitude=DECIMAL.');
    $seed = mpe_values(['latitude' => $options['latitude'], 'longitude' => $options['longitude'], 'norte_graus' => null]);
}
$conn = conectarBanco();
try {
    if ($seed && !mp_row($conn, 'SELECT idobra FROM obra WHERE idobra=?', 'i', [$obra])) throw new RuntimeException('Obra não encontrada.');
    $conn->multi_query(file_get_contents(__DIR__ . '/../sql/2026-10-13_mapa_configuracoes_espaciais.sql'));
    do { if ($result = $conn->store_result()) $result->free(); if (!$conn->more_results()) break; $conn->next_result(); } while (true);
    if (!mp_row($conn, "SHOW COLUMNS FROM mapa_obra_configuracoes_espaciais LIKE 'timezone'")) {
        $conn->query(file_get_contents(__DIR__ . '/../sql/2026-10-13_mapa_configuracoes_timezone.sql'));
    }
    // Reexecução não sobrescreve coordenadas nem Norte já configurados.
    if ($seed) mp_query($conn, 'INSERT INTO mapa_obra_configuracoes_espaciais(obra_id,latitude,longitude) VALUES(?,?,?) ON DUPLICATE KEY UPDATE obra_id=VALUES(obra_id)', 'idd', [$obra, $seed['latitude'], $seed['longitude']])->close();
    echo json_encode(['sucesso' => true, 'configuracao' => $seed ? mpe_get($conn, $obra) : null], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
} finally { $conn->close(); }
