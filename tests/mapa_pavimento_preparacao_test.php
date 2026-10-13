<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../helpers/referencia_arquitetonica_helper.php';
function expect(bool $value, string $label): void { if (!$value) throw new RuntimeException($label); }
function rejects(callable $operation, string $label): void {
    try { $operation(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Deveria recusar: ' . $label);
}
$crop = ['x' => .1, 'y' => .2, 'width' => .8, 'height' => .6];
$legacy = ['version' => 1, 'coordinate_space' => 'normalized', 'points' => [[.2,.3],[.7,.3],[.7,.7],[.2,.7]]];
$converted = mp_geometry_rebase($legacy, null, $crop);
expect($converted['version'] === 2 && $converted['coordinate_space'] === 'useful_area_normalized', 'Nova base geométrica');
expect(abs($converted['points'][0][0] - .125) < 1e-8 && abs($converted['points'][0][1] - 1/6) < 1e-8, 'Conversão página para área útil');
$back = mp_geometry_rebase($converted, $crop, null);
foreach ($legacy['points'] as $i => $point) foreach ($point as $j => $value) expect(abs($back['points'][$i][$j] - $value) < 1e-8, 'Preservação da posição física');
$nextCrop = ['x' => .15, 'y' => .25, 'width' => .6, 'height' => .5];
$next = mp_geometry_rebase($converted, $crop, $nextCrop);
expect(abs($next['points'][0][0] - 1/12) < 1e-8, 'Rebase entre dois recortes');
rejects(fn() => mp_geometry_rebase($converted, $crop, ['x'=>.3,'y'=>.3,'width'=>.4,'height'=>.4]), 'recorte que elimina ambiente');
expect(mp_crop(['x'=>0,'y'=>0,'width'=>1,'height'=>1]) === null, 'Página inteira sem recorte');
$concave = ['version'=>3,'coordinate_space'=>'page_normalized','points'=>[[.1,.1],[.9,.1],[.9,.9],[.6,.9],[.6,.4],[.4,.4],[.4,.9],[.1,.9]]];
expect(mp_crop($concave) === $concave, 'Área útil aceita polígono côncavo');
expect(mp_crop($crop)['version'] === 3, 'Recorte retangular legado convertido em polígono');
$inside = ['version'=>1,'coordinate_space'=>'normalized','points'=>[[.15,.2],[.3,.2],[.3,.7],[.15,.7]]];
$insideRebased = mp_geometry_rebase($inside, null, $concave);
expect(abs($insideRebased['points'][0][0] - .0625) < 1e-8, 'Polígono usa caixa delimitadora normalizada');
$crossing = ['version'=>1,'coordinate_space'=>'normalized','points'=>[[.3,.6],[.7,.6],[.7,.8],[.3,.8]]];
rejects(fn() => mp_geometry_rebase($crossing, null, $concave), 'Aresta atravessando vazio mesmo com vértices dentro');
$boundary = ['version'=>1,'coordinate_space'=>'normalized','points'=>[[.1,.1],[.9,.1],[.9,.3],[.1,.3]]];
expect(count(mp_geometry_rebase($boundary,null,$concave)['points']) === 4, 'Vértices na borda são aceitos');
rejects(fn() => mp_crop(['x'=>.8,'y'=>0,'width'=>.3,'height'=>1]), 'recorte fora da página');
rejects(fn() => mp_crop(['x'=>0,'y'=>0,'width'=>0,'height'=>1]), 'retângulo sem área');
rejects(fn() => mp_crop(['x'=>'0','y'=>0,'width'=>1,'height'=>1]), 'coordenada textual');
rejects(fn() => mp_crop(['x'=>NAN,'y'=>0,'width'=>1,'height'=>1]), 'coordenada não finita');
$layers = ['version'=>1,'groups'=>[['id'=>'6R','name'=>'Arquitetura','visible'=>true],['id'=>'7R','name'=>'Cotas','visible'=>false]]];
expect(mp_layers($layers) === $layers, 'Configuração e nomes reais das camadas');
expect(mp_layers(['version'=>1,'groups'=>[]])['groups'] === [], 'PDF sem OCG');
rejects(fn() => mp_layers(['version'=>1,'groups'=>[$layers['groups'][0],$layers['groups'][0]]]), 'IDs de camada duplicados');
rejects(fn() => mp_layers(['version'=>1,'groups'=>[['id'=>'6R','name'=>'Cotas','visible'=>'false']]]), 'visibilidade deve ser booleana');
echo "OK: camadas, recortes e compatibilidade/conversão das demarcações.\n";
