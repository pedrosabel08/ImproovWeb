<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../helpers/mapa_improov_helper.php';
$checks = 0; $failures = [];
$check = static function ($ok, $label) use (&$checks, &$failures) { $checks++; if (!$ok) $failures[] = $label; };
foreach ([
    [null, null, null], ['', 2, null], [1, null, null], ['abc', 2, null], [[1], 2, null],
    [91, 0, null], [0, 181, null], [-91, 0, null], [0, -181, null], [INF, 0, null],
    [0, 0, [0.,0.]], [-90, -180, [-180.,-90.]], [90,180,[180.,90.]],
    ['-27,0992256','-48,6179817',[-48.6179817,-27.0992256]],
] as $index => [$lat,$lng,$expected]) {
    $check(mi_coordinates(['latitude'=>$lat,'longitude'=>$lng]) === $expected, 'Coordinates ' . $index);
}
$row = ['idobra'=>'102','nomenclatura'=>'CIB_HYP','nome_real'=>'Nome real','nome_obra'=>'Nome interno','status_obra'=>0,'local'=>null,'latitude'=>0,'longitude'=>0];
$p = mi_project($row, ['id'=>4]);
$check($p['nome'] === 'Nome real', 'Prefer registered public project name');
$check($p['coordinates'] === [0.,0.], 'Preserve zero coordinates');
$check($p['status'] === 'Ativo', 'Do not invent production status');
$check($p['has_glb'] && str_contains($p['viewer_url'], 'obra_id=102&modelo_id=4&origem=mapa-improov'), 'Viewer context');
$check(!isset($p['arquivo']) && $p['thumbnail'] === null, 'No storage paths');
$p = mi_project($row, null);
$check(!$p['has_glb'] && $p['viewer_url'] === null, 'Missing GLB');
$p = mi_project($row, ['id'=>4], false);
$check($p['has_glb'] && !$p['can_open_3d'] && $p['viewer_url'] === null, 'Registry access never grants viewer access');
echo json_encode(compact('checks','failures'), JSON_PRETTY_PRINT) . PHP_EOL;
exit($failures ? 1 : 0);
