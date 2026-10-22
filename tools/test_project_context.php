<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/config/page_metadata.php';
require_once dirname(__DIR__) . '/Dashboard/obra-context.php';
$checks = 0;
$failures = [];
$check = static function ($ok, $label) use (&$checks, &$failures) { $checks++; if (!$ok) $failures[] = $label; };
$cases = [
    [[], null], [['tab' => 'review'], null], [['obra_id' => '85'], 85],
    [['idobra' => '85'], 85], [['id' => '85'], 85], [['obra_id' => 85], 85],
    [['obra_id' => '00085'], 85], [['obra_id' => '85', 'idobra' => '118'], 85],
    [['obra_id' => '0'], 0], [['obra_id' => '-85'], 0], [['obra_id' => '85.1'], 0],
    [['obra_id' => '8.5e1'], 0], [['obra_id' => ''], 0], [['obra_id' => null], 0],
    [['obra_id' => ['85']], 0], [['obra_id' => true], 0], [['obra_id' => '//example.com'], 0],
    [['obra_id' => '999999999999999999999999999999999'], 0],
    [['obra_id' => 'invalid', 'id' => '85'], 0],
];
foreach ($cases as $index => [$query, $expected]) $check(flow_obra_id_from_query($query) === $expected, 'Project query case ' . $index);
$_SERVER['REQUEST_URI'] = '/ImproovWeb/Dashboard/obra?obra_id=85';
$_SESSION = ['idusuario' => 123, 'idcolaborador' => 456];
$html = flow_obra_context_scripts(85);
preg_match('~id="flow-obra-context">(.*?)</script>~s', $html, $match);
$seed = json_decode($match[1], true, 16, JSON_THROW_ON_ERROR);
$check($seed === ['obraId' => '85', 'usuarioId' => 123, 'colaboradorId' => 456], 'Context not initialized from the current session');
$check(str_contains($html, '/ImproovWeb/Dashboard/obra-context.js?v='), 'Project bootstrap script missing/version not set');
$public = flow_metadata_html('projeto');
$check(!str_contains($public, 'obra_id=') && !str_contains($public, 'flow-obra-context'), 'Private project context leaked into public metadata');
echo json_encode(compact('checks', 'failures'), JSON_PRETTY_PRINT) . PHP_EOL;
exit($failures ? 1 : 0);
