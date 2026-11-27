<?php
require __DIR__ . '/../Pos-Producao/metricas_tempo.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
check(pos_mediana([]) === null, 'amostra vazia');
check(pos_mediana([9, 1, 5]) === 5.0, 'mediana ímpar');
check(pos_mediana([10, 1, 5, 2]) === 3.5, 'mediana par');
$ciclos = [
    ['envio' => '2026-09-28 23:59:59', 'segundos' => 900, 'revisao' => 'R00'],
    ['envio' => '2026-09-29 00:00:00', 'segundos' => 10, 'revisao' => 'R00'],
    ['envio' => '2026-10-28 23:59:59', 'segundos' => 30, 'revisao' => 'R00'],
    ['envio' => '2026-10-29 00:00:00', 'segundos' => 20, 'revisao' => 'R01'],
    ['envio' => '2026-11-01 00:00:00', 'segundos' => 40, 'revisao' => null],
    ['envio' => '2026-11-02 00:00:00', 'segundos' => 60, 'revisao' => 'R00'],
    ['envio' => '2026-11-28 00:00:00', 'segundos' => 80, 'revisao' => 'EF'],
    ['envio' => '2026-11-28 00:00:01', 'segundos' => 900, 'revisao' => 'R00'],
];
$calc = fn($rows) => pos_resumir_ciclos($rows, '2026-09-29 00:00:00', '2026-10-29 00:00:00', '2026-11-28 00:00:00');
$r = $calc($ciclos);
check($r['atual']['amostra'] === 4 && $r['anterior']['amostra'] === 2, 'limites dos períodos');
check($r['atual']['mediana_segundos'] === 50.0 && $r['anterior']['mediana_segundos'] === 20.0 && $r['variacao_percentual'] === 150.0, 'variação positiva');
check(array_column($r['revisoes'], 'revisao') === ['R00', 'R01', 'EF', 'Não identificada'], 'ordem e revisão não identificada');
$rows = [['envio'=>'2026-10-01 00:00:00','segundos'=>40,'revisao'=>'R00'], ['envio'=>'2026-11-01 00:00:00','segundos'=>20,'revisao'=>'R00']];
check($calc($rows)['variacao_percentual'] === -50.0, 'variação negativa');
$rows[1]['segundos'] = 40;
check($calc($rows)['variacao_percentual'] === 0.0, 'variação nula');
$rows[0]['segundos'] = 0;
check($calc($rows)['variacao_percentual'] === null, 'divisão por zero');
check($calc([])['variacao_percentual'] === null && $calc([])['atual']['mediana_segundos'] === null, 'sem dados');
check($calc([['envio'=>'2026-11-01 00:00:00','segundos'=>12,'revisao'=>'R10']])['variacao_percentual'] === null, 'sem período anterior');
check($calc([['envio'=>'2026-10-01 00:00:00','segundos'=>12,'revisao'=>'R10']])['variacao_percentual'] === null, 'sem período atual');
$grupos = $calc([
    ['envio'=>'2026-11-01 00:00:00','segundos'=>10,'revisao'=>'R10'],
    ['envio'=>'2026-11-01 00:00:01','segundos'=>30,'revisao'=>'R10'],
    ['envio'=>'2026-11-01 00:00:02','segundos'=>100,'revisao'=>'R02'],
]);
check(array_column($grupos['revisoes'], 'revisao') === ['R02', 'R10'] && $grupos['revisoes'][1]['segundos'] === 20.0, 'ordem numérica e média por revisão');
echo "Testes de mediana e períodos: OK\n";
