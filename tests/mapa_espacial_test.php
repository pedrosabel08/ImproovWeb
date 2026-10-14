<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../helpers/referencia_arquitetonica_helper.php';
require_once __DIR__ . '/../helpers/mapa_espacial_helper.php';
function spatial_check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function spatial_reject(callable $call, string $class = InvalidArgumentException::class, int $code = 0): void {
    try { $call(); } catch (Throwable $error) { spatial_check($error instanceof $class && (!$code || $error->getCode() === $code), $error->getMessage()); return; }
    throw new RuntimeException('Entrada inválida foi aceita.');
}
$empty = ['latitude'=>null, 'longitude'=>null, 'norte_graus'=>null];
spatial_check(mpe_values($empty) === $empty, 'Valores não definidos não preservados.');
spatial_check(mpe_values(['latitude'=>'-27,0992256','longitude'=>'-48.6179817','norte_graus'=>32.5]) === ['latitude'=>-27.0992256,'longitude'=>-48.6179817,'norte_graus'=>32.5], 'Coordenadas negativas/precisão inválidas.');
foreach ([-90, 0, 90] as $value) spatial_check(mpe_values(array_replace($empty,['latitude'=>$value]))['latitude'] === (float)$value, 'Limite de latitude recusado.');
foreach ([-180, 0, 180] as $value) spatial_check(mpe_values(array_replace($empty,['longitude'=>$value]))['longitude'] === (float)$value, 'Limite de longitude recusado.');
foreach ([['latitude'=>90.00000001], ['latitude'=>-90.01], ['longitude'=>180.01], ['longitude'=>-180.01], ['latitude'=>true], ['longitude'=>[]], ['latitude'=>'NaN'], ['longitude'=>'leste'], ['norte_graus'=>-1], ['norte_graus'=>360], ['norte_graus'=>INF], ['norte_graus'=>'32.5']] as $invalid) spatial_reject(fn()=>mpe_values(array_replace($empty,$invalid)));
spatial_reject(fn()=>mpe_values([]));
spatial_check(mpe_timezone('Europe/Lisbon') === 'Europe/Lisbon', 'Fuso IANA válido recusado.');
spatial_check(mpe_timezone('UTC') === 'UTC', 'UTC recusado.');
foreach (['America/Invalido', '', 'UTC+3', true, null, []] as $invalidTimezone) spatial_reject(fn()=>mpe_timezone($invalidTimezone));
spatial_check(mpe_values(array_replace($empty,['norte_graus'=>359.99]))['norte_graus'] === 0.0, 'Arredondamento não normalizou 360°.');
echo "OK: limites, negativos, precisão, null e entradas inválidas.\n";
if (!in_array('--integration', $argv, true)) exit;
require_once __DIR__ . '/../conexaoMain.php';
$conn = conectarBanco();
try {
    // Tabela temporária de conexão: nenhum registro espacial real é alterado.
    $schema = file_get_contents(__DIR__ . '/../sql/2026-10-13_mapa_configuracoes_espaciais.sql');
    $schema = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $schema);
    $schema = preg_replace('/^\s*CONSTRAINT fk_mpe_.*\R/m', '', $schema);
    $conn->query($schema);
    $obra = 74;
    $author = (int)mp_row($conn,'SELECT criado_por FROM referencias_arquitetonicas WHERE obra_id=? LIMIT 1','i',[$obra])['criado_por'];
    spatial_check(mpe_get($conn,$obra)['versao'] === 0, 'Obra sem configuração não retornou estado vazio.');
    $input = ['latitude'=>-27.0992256,'longitude'=>-48.6179817,'norte_graus'=>32.5,'versao'=>0];
    $first = mpe_save($conn,$obra,$input,$author);
    spatial_check($first['versao'] === 1 && $first['norte_graus'] === 32.5, 'Criação não persistiu Norte.');
    spatial_check(mpe_get($conn,$obra) === $first, 'Leitura não recuperou configuração.');
    $other = mp_row($conn,'SELECT idobra FROM obra WHERE idobra<>? LIMIT 1','i',[$obra]);
    spatial_check(mpe_get($conn,(int)$other['idobra'])['latitude'] === null, 'Configuração vazou para outra obra.');
    spatial_reject(fn()=>mpe_save($conn,$obra,$input,$author), RuntimeException::class, 409);
    spatial_reject(fn()=>mpe_save($conn,$obra,array_replace($input,['versao'=>1,'latitude'=>91]),$author));
    spatial_check(mpe_get($conn,$obra) === $first, 'Erro alterou a configuração.');
    $updated = mpe_save($conn,$obra,array_replace($input,['versao'=>1,'norte_graus'=>0.0,'timezone'=>'Europe/Lisbon']),$author);
    spatial_check($updated['versao'] === 2 && $updated['norte_graus'] === 0.0, 'Norte 0 confundido com não definido.');
    $cleared = mpe_save($conn,$obra,$empty+['versao'=>2],$author);
    spatial_check($cleared === ['obra_id'=>$obra]+$empty+['timezone'=>'Europe/Lisbon','versao'=>3], 'Campos opcionais não foram limpos.');
    spatial_check($cleared['timezone'] === 'Europe/Lisbon', 'Cliente antigo sobrescreveu o fuso salvo.');
    echo "OK: banco (tabela temporária), criação, leitura, isolamento por obra, conflito, zero e limpeza.\n";
} finally { $conn->close(); }
