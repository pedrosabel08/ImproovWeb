<?php
// Somente CLI: php scripts/migrate_mapa_pavimento.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../conexaoMain.php';
$conn = conectarBanco();
require_once __DIR__ . '/mapa_pavimento_fase2_migration.php';
$associations=[];
foreach(array_slice($argv,1) as $argument) {
    if(!preg_match('/^--associar=(\d+):(\d+)$/',$argument,$match)) throw new InvalidArgumentException('Use --associar=ID_PAVIMENTO_LEGADO:ID_SUBTIPO');
    $associations[(int)$match[1]]=(int)$match[2];
}
if ($conn->query("SHOW TABLES LIKE 'referencias_arquitetonicas'")->num_rows
    && !$conn->query("SHOW COLUMNS FROM referencias_arquitetonicas LIKE 'pavimento_id'")->num_rows) {
    mp_migrate_phase2($conn,$associations); $conn->close(); exit;
}
$sql = file_get_contents(__DIR__ . '/../sql/2026-10-09_mapa_pavimento.sql');
$conn->multi_query($sql);
do {
    if ($result = $conn->store_result()) $result->free();
    if (!$conn->more_results()) break;
    $conn->next_result();
} while (true);
$preparation = file_get_contents(__DIR__ . '/../sql/2026-10-09_mapa_pavimento_preparacao.sql');
foreach (explode(';', $preparation) as $statement) {
    if (!preg_match('/ADD COLUMN (\w+)/', $statement, $match)) continue;
    $column = $match[1];
    $existing = $conn->query("SHOW COLUMNS FROM referencias_arquitetonicas LIKE '$column'");
    if ($existing->num_rows === 0) $conn->query(trim($statement));
    $existing->free();
}
foreach (['obra_pavimentos', 'referencias_arquitetonicas', 'ambientes', 'ambiente_demarcacoes', 'ambiente_imagens'] as $table) {
    $conn->query('SELECT 1 FROM ' . $table . ' LIMIT 0');
}
mp_migrate_phase2($conn,$associations);
$conn->close();
echo "Migration Mapa de Pavimento aplicada e verificada.\n";
