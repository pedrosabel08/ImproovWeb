<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../helpers/referencia_arquitetonica_helper.php';
require_once __DIR__ . '/../helpers/mapa_modelo_3d_helper.php';
function check3d(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function reject3d(callable $callback, string $class, int $code = 0): void {
    try { $callback(); } catch (Throwable $error) { check3d($error instanceof $class && (!$code || $error->getCode() === $code), $error->getMessage()); return; }
    throw new RuntimeException('Operação inválida foi aceita.');
}
check3d(mp3d_floor_name('PAVTO_LAZER_01_001') === 'PAVTO_LAZER_01', 'Sufixo da peça confundido com pavimento.');
check3d(mp3d_floor_name('PAVTO_LAZER_01') === 'PAVTO_LAZER_01', 'Nó único não reconhecido.');
check3d(mp3d_floor_name('A_47_LAZER_047_001001') === null, 'Objeto de contexto virou pavimento.');
check3d(mp3d_floor_name('PAVTO_EMBASAMENTO_001001') === null, 'Nomenclatura antiga ambígua virou pavimento.');
reject3d(fn()=>mp3d_file('../Projeto-TES_TES.glb'), InvalidArgumentException::class);
$names = mp3d_floor_names(mp3d_file('Projeto-TES_TES.glb'));
check3d($names === ['PAVTO_LAZER_01', 'PAVTO_TIPO_01'], 'Pavimentos reais do GLB não reconhecidos.');
echo "OK: GLB real, grupo/objeto, prefixos e validação de caminho.\n";

if (!in_array('--integration', $argv, true)) exit;
require_once __DIR__ . '/../conexaoMain.php';
$conn = conectarBanco(); $obra = 74; $file = 'PAVTO_TIPO_01.glb'; $modelId = 0;
$name = 'QA 3D ' . bin2hex(random_bytes(8));
// Não reutiliza nem remove um cadastro preexistente.
check3d(!mp_row($conn,'SELECT id FROM mapa_modelos_3d WHERE obra_id=? AND arquivo=?','is',[$obra,$file]), 'O GLB de fixture já está cadastrado; teste interrompido sem alterar dados.');
$snapshot = mp_rows($conn,'SELECT * FROM mapa_modelo_pavimentos WHERE modelo_id=1 ORDER BY object_name');
$author = (int) mp_row($conn,'SELECT criado_por FROM referencias_arquitetonicas WHERE obra_id=? LIMIT 1','i',[$obra])['criado_por'];
try {
    mp_query($conn,'INSERT INTO mapa_modelos_3d(obra_id,nome,arquivo) VALUES(?,?,?)','iss',[$obra,$name,$file])->close(); $modelId = $conn->insert_id;
    $object = 'PAVTO_TIPO_01';
    reject3d(fn()=>mp3d_model($conn, $obra+1, $modelId), RuntimeException::class, 404);
    reject3d(fn()=>mp3d_save_link($conn,$obra,$modelId,'PAVTO_INEXISTENTE_01',3,0,$author), InvalidArgumentException::class);
    $outside = mp_row($conn,'SELECT s.id FROM subtipo_imagem s WHERE NOT EXISTS(SELECT 1 FROM imagens_cliente_obra i WHERE i.obra_id=? AND i.subtipo_id=s.id) AND NOT EXISTS(SELECT 1 FROM referencias_arquitetonicas r WHERE r.obra_id=? AND r.subtipo_id=s.id) AND NOT EXISTS(SELECT 1 FROM mapa_modelo_pavimentos v JOIN mapa_modelos_3d m ON m.id=v.modelo_id WHERE m.obra_id=? AND v.subtipo_id=s.id) LIMIT 1','iii',[$obra,$obra,$obra]);
    check3d((bool)$outside, 'Nenhum subtipo externo disponível para o teste negativo.');
    reject3d(fn()=>mp3d_save_link($conn,$obra,$modelId,$object,(int)$outside['id'],0,$author), InvalidArgumentException::class);
    mp3d_save_link($conn,$obra,$modelId,$object,3,0,$author);
    $link = mp3d_links($conn,$modelId)[0]; check3d($link['versao']===1 && $link['subtipo_id']===3 && $link['mapa_configurado'], 'Vínculo não persistido.');
    reject3d(fn()=>mp3d_save_link($conn,$obra,$modelId,$object,1,0,$author), RuntimeException::class, 409);
    mp3d_save_link($conn,$obra,$modelId,$object,1,1,$author);
    $link = mp3d_links($conn,$modelId)[0]; check3d($link['versao']===2 && $link['subtipo_id']===1, 'Alteração de vínculo não persistida.');
    // Fixture apenas no modelo temporário: um subtipo vinculado sem referência.
    mp_query($conn,'UPDATE mapa_modelo_pavimentos SET subtipo_id=? WHERE modelo_id=?','ii',[(int)$outside['id'],$modelId])->close();
    $link = mp3d_links($conn,$modelId)[0]; check3d(!$link['mapa_configurado'] && $link['referencia_id']===null, 'Ausência de mapa tratada incorretamente.');
    check3d(mp_rows($conn,'SELECT * FROM mapa_modelo_pavimentos WHERE modelo_id=1 ORDER BY object_name')===$snapshot, 'O modelo original foi alterado.');
    echo "OK: isolamento por obra/modelo, subtipo externo recusado, persistência, edição, conflito e ausência de mapa.\n";
} finally {
    if ($modelId) mp_query($conn,'DELETE FROM mapa_modelos_3d WHERE id=? AND nome=? AND arquivo=?','iss',[$modelId,$name,$file])->close();
    $conn->close();
}
