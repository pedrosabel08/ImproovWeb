<?php
// CLI: php scripts/migrate_mapa_modelos_3d.php --obra=74 --arquivo=Projeto-TES_TES.glb --nome=TES_TES
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/referencia_arquitetonica_helper.php';
require_once __DIR__ . '/../helpers/mapa_modelo_3d_helper.php';
$options = getopt('', ['obra:', 'arquivo:', 'nome:']);
$obra = (int) ($options['obra'] ?? 0); $file = (string) ($options['arquivo'] ?? '');
if (!$obra || !$file) throw new InvalidArgumentException('Use --obra=ID --arquivo=ARQUIVO.glb [--nome=NOME].');
$path = mp3d_file($file); $floors = mp3d_floor_names($path);
if (!$floors) throw new RuntimeException('Nenhum pavimento PAVTO identificado no arquivo.');
$name = trim((string) ($options['nome'] ?? pathinfo($file, PATHINFO_FILENAME)));
if (!$name || mb_strlen($name) > 150) throw new InvalidArgumentException('Nome inválido.');
$conn = conectarBanco();
if (!mp_row($conn, 'SELECT idobra FROM obra WHERE idobra=?', 'i', [$obra])) throw new RuntimeException('Obra não encontrada.');
$conn->multi_query(file_get_contents(__DIR__ . '/../sql/2026-10-13_mapa_modelos_3d.sql'));
do { if ($result = $conn->store_result()) $result->free(); if (!$conn->more_results()) break; $conn->next_result(); } while (true);
mp_query($conn, 'INSERT INTO mapa_modelos_3d(obra_id,nome,arquivo) VALUES(?,?,?) ON DUPLICATE KEY UPDATE nome=VALUES(nome)', 'iss', [$obra, $name, $file])->close();
$model = mp_row($conn, 'SELECT id,obra_id,nome,arquivo FROM mapa_modelos_3d WHERE obra_id=? AND arquivo=?', 'is', [$obra, $file]);
echo json_encode(['modelo'=>$model,'pavimentos'=>$floors,'nota'=>'Os vínculos com subtipos são configurados pela interface.'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), PHP_EOL;
$conn->close();
