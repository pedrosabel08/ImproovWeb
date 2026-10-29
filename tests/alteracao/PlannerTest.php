<?php
/** CLI only. Database fixtures use connection-local TEMPORARY tables exclusively. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/helpers/alteracao_planner_helper.php';
$checks = 0;
function planner_assert(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
$week = flow_alt_semana('2026-10-29', '2026-10-29');
planner_assert($week['inicio'] === '2026-10-26' && $week['fim'] === '2026-10-30', 'Semana civil real, sem copiar datas inconsistentes do wireframe');
planner_assert(count($week['dias']) === 5 && $week['dias'][3]['hoje'], 'Cinco dias e hoje correto');
planner_assert($week['anterior'] === '2026-10-19' && $week['proxima'] === '2026-11-02', 'Navegação semanal');
planner_assert(!flow_alt_semana('2026-11-02')['dias'][0]['util'], 'Calendário Flow reconhece feriado');
planner_assert(flow_alt_semana('2026-10-31')['inicio'] === $week['inicio'], 'Fim de semana pertence à mesma semana');
try { flow_alt_semana('2026-02-30'); throw new RuntimeException('Data inválida aceita'); } catch (InvalidArgumentException $expected) { $checks++; }
planner_assert(flow_alt_concluida('Aprovado') && flow_alt_concluida('Finalizado'), 'Status encerrados');
planner_assert(!flow_alt_concluida('Aprovado com ajustes') && !flow_alt_concluida('Em aprovação'), 'Ajustes e aprovação continuam operacionais');

if (!in_array('--db', $argv, true)) { echo "$checks verificações puras passaram. Use --db para tabelas temporárias.\n"; exit; }
require_once dirname(__DIR__, 2) . '/config/database.php';
$conn = improov_db_connect();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$ddl = [
    'alteracoes' => 'funcao_id INT, status_id INT, nivel_complexidade INT NULL',
    'funcao_imagem' => 'idfuncao_imagem INT PRIMARY KEY, imagem_id INT, colaborador_id INT NULL, funcao_id INT, status VARCHAR(50)',
    'imagens_cliente_obra' => 'idimagens_cliente_obra INT PRIMARY KEY, imagem_nome VARCHAR(120), obra_id INT, status_id INT, prazo DATE',
    'obra' => 'idobra INT PRIMARY KEY, nomenclatura VARCHAR(30), nome_obra VARCHAR(50), status_obra INT',
    'colaborador' => 'idcolaborador INT PRIMARY KEY, nome_colaborador VARCHAR(50)',
    'status_imagem' => 'idstatus INT PRIMARY KEY, nome_status VARCHAR(20)',
    'pre_alt_itens' => 'id INT PRIMARY KEY, imagem_id INT',
    'pre_alt_liberacao_itens' => 'id INT PRIMARY KEY, pre_alt_item_id INT, entrega_destino_id INT',
    'entregas' => 'id INT PRIMARY KEY, status_id INT',
    'entregas_itens' => 'id INT PRIMARY KEY, entrega_id INT, imagem_id INT, data_entregue DATETIME NULL',
    'entrega_planejamento_producao' => 'id INT PRIMARY KEY, entrega_id INT, modelo VARCHAR(40), versao_atual_id INT NULL, baseline_versao_id INT NULL, rascunho_json TEXT NULL',
    'entrega_planejamento_item' => 'versao_id INT, entrega_item_id INT, funcao_imagem_id INT, fim_planejado DATE, responsavel_planejado_id INT, nivel_complexidade_snapshot INT NULL',
    'historico_aprovacoes_imagens' => 'funcao_imagem_id INT, imagem VARCHAR(100)',
    'janela_operacional_ciclo' => 'id INT PRIMARY KEY, entrega_item_id INT, situacao VARCHAR(20), motivo_encerramento VARCHAR(40)',
    'obra_pacote' => 'obra_id INT, tipo VARCHAR(20), status VARCHAR(20)',
];
foreach ($ddl as $table => $columns) $conn->query("CREATE TEMPORARY TABLE $table ($columns)");
$conn->query("INSERT INTO obra VALUES (1,'OBRA_A','Obra A',0),(2,'OBRA_B','Obra B',0)");
$conn->query("INSERT INTO obra_pacote VALUES (1,'STILL','ATIVO')");
$conn->query("INSERT INTO colaborador VALUES (1,'Ana'),(2,'Bruno')");
$conn->query("INSERT INTO status_imagem VALUES (3,'R01'),(4,'R02'),(15,'R05'),(6,'EF')");
for ($id=1; $id<=9; $id++) {
    $stage = $id===4 ? 15 : ($id===5 ? 6 : ($id===7 || $id===8 ? 4 : 3));
    $status = $id===6 ? 'Aprovado' : ($id===3 ? 'HOLD' : 'Não iniciado');
    $obra = $id===9 ? 2 : 1;
    $conn->query("INSERT INTO imagens_cliente_obra VALUES ($id,'Imagem $id',$obra,$stage,'2026-10-29')");
    $conn->query("INSERT INTO funcao_imagem VALUES ($id,$id,1,6,'$status')");
    $conn->query("INSERT INTO alteracoes VALUES ($id,$stage,1)");
}
function fixture_plan(mysqli $conn, int $task, int $stage, int $delivery, int $version, string $day, int $release): void {
    $conn->query("INSERT INTO pre_alt_itens VALUES ($release,$task)");
    $conn->query("INSERT INTO pre_alt_liberacao_itens VALUES ($release,$release,$delivery)");
    $conn->query("INSERT INTO entregas VALUES ($delivery,$stage)");
    $conn->query("INSERT INTO entregas_itens VALUES ($delivery,$delivery,$task,NULL)");
    $conn->query("INSERT INTO entrega_planejamento_producao VALUES ($delivery,$delivery,'IMAGENS_ALTERACAO',".($version ?: 'NULL').",".($version ?: 'NULL').",NULL)");
    if ($version) $conn->query("INSERT INTO entrega_planejamento_item VALUES ($version,$delivery,$task,'$day',2,3)");
}
fixture_plan($conn,2,3,12,102,'2026-10-28',12);
fixture_plan($conn,3,3,13,103,'2026-10-29',13);
fixture_plan($conn,4,15,14,104,'2026-10-29',14);
fixture_plan($conn,4,15,24,0,'',24); // New release of repeated R05 is only a draft.
fixture_plan($conn,6,3,16,106,'2026-10-26',16);
fixture_plan($conn,7,3,17,107,'2026-10-27',17);
fixture_plan($conn,7,4,27,0,'',27);
$conn->query("UPDATE entregas_itens SET data_entregue='2026-10-28 12:00:00' WHERE id=17");
fixture_plan($conn,8,3,18,108,'2026-10-28',18);
fixture_plan($conn,8,4,28,0,'',28);
$conn->query("INSERT INTO janela_operacional_ciclo VALUES (1,18,'ENCERRADO','CONCLUSAO')");
$conn->query("INSERT INTO historico_aprovacoes_imagens VALUES (2,'/private/sample.jpg')");
$gestao = ['modo'=>'gestao'];
$snapshot = static fn() => json_encode([flow_alt_rows($conn,'SELECT * FROM entrega_planejamento_producao'),flow_alt_rows($conn,'SELECT * FROM entrega_planejamento_item'),flow_alt_rows($conn,'SELECT * FROM funcao_imagem')]);
$before = $snapshot();
$data = flow_alt_planner_carregar($conn,$week,$gestao);
$byTask = [];
foreach ($data['items'] as $item) $byTask[$item['funcao_id']][] = $item;
planner_assert(!$byTask[1][0]['planejada'] && !$byTask[1][0]['dia_planejado'], 'Prazo legado não vira dia confirmado');
planner_assert($byTask[2][0]['dia_planejado']==='2026-10-28' && $byTask[2][0]['atrasada'], 'Atraso preserva o dia original');
planner_assert($byTask[2][0]['colaborador_id']===2 && $byTask[2][0]['nivel_complexidade']===3, 'Responsável e nível usam snapshot confirmado');
planner_assert($byTask[2][0]['thumbnail_url']!==null, 'Thumbnail agregada');
planner_assert($byTask[3][0]['status_funcao']==='HOLD' && !$byTask[3][0]['atrasada'], 'HOLD e hoje');
planner_assert(!$byTask[4][0]['planejada'] && $byTask[4][0]['entrega_id']===24, 'R05 repetida não herda versão antiga');
planner_assert($byTask[5][0]['is_ef'] && !$byTask[5][0]['planejada'], 'EF preservado fora dos dias');
planner_assert($byTask[6][0]['concluida'] && !$byTask[6][0]['atrasada'], 'Aprovada continua na semana');
planner_assert(count($byTask[7])===2 && count($byTask[8])===2, 'Rodadas concluídas continuam após avanço de etapa, por entrega ou ciclo');
planner_assert($before===$snapshot(), 'Leitura não altera tarefa, responsável, baseline, versão ou rascunho');
foreach (['Não iniciado','HOLD','Em andamento','Em aprovação','Ajuste','Aprovado com ajustes'] as $status) {
    $stmt=$conn->prepare('UPDATE funcao_imagem SET status=? WHERE idfuncao_imagem=3');
    $stmt->bind_param('s',$status); $stmt->execute(); $stmt->close();
    $statusData=flow_alt_planner_carregar($conn,$week,$gestao);
    $statusItem=array_values(array_filter($statusData['items'],fn($i)=>$i['funcao_id']===3))[0];
    planner_assert($statusItem['status_funcao']===$status && !$statusItem['concluida'], 'Preserva status operacional: '.$status);
}
$selectsBefore=(int)$conn->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetch_row()[1];
flow_alt_planner_carregar($conn,$week,$gestao);
$selectsAfter=(int)$conn->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetch_row()[1];
planner_assert($selectsAfter-$selectsBefore===3, 'Três SELECTs agregados, sem consultas por tarefa');
$conn->query("INSERT INTO entrega_planejamento_item VALUES (202,12,2,'2026-10-30',1,2)");
$conn->query("UPDATE entrega_planejamento_producao SET versao_atual_id=202,rascunho_json='{}' WHERE id=12");
$replan = flow_alt_planner_carregar($conn,$week,$gestao);
$moved = array_values(array_filter($replan['items'],fn($i)=>$i['funcao_id']===2))[0];
planner_assert($moved['dia_planejado']==='2026-10-30' && $moved['colaborador_id']===1, 'Nova versão vigente move item; rascunho não substitui confirmação');
planner_assert((int)flow_alt_rows($conn,'SELECT baseline_versao_id FROM entrega_planejamento_producao WHERE id=12')[0]['baseline_versao_id']===102, 'Baseline permanece original');
$next = flow_alt_planner_carregar($conn,flow_alt_semana('2026-11-02'),$gestao);
planner_assert(!array_filter($next['items'],fn($i)=>$i['planejada']), 'Semana seguinte não recebe tarefas automaticamente');
$restricted = flow_alt_planner_carregar($conn,$week,['modo'=>'producao','pacote_tipo'=>'STILL']);
planner_assert(!array_filter($restricted['items'],fn($i)=>$i['obra_id']===2), 'Permissões de obra mantidas');
$conn->query('ALTER TABLE entrega_planejamento_producao DROP COLUMN modelo');
$legacy = flow_alt_planner_carregar($conn,$week,$gestao);
planner_assert(!$legacy['planejamento_disponivel'] && !array_filter($legacy['items'],fn($i)=>$i['planejada']), 'Sem migration preserva fila sem criar schema');
echo "$checks verificações passaram, com tabelas temporárias e nenhuma mutação em dados reais.\n";
