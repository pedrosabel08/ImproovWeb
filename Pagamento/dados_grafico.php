<?php
require_once __DIR__.'/pagamento_auth.php';
pagamento_require_gestor(false);
require_once __DIR__.'/../conexao.php';
require_once __DIR__.'/services/FechamentoCompetenciaService.php';
$b=filter_input(INPUT_GET,'colaborador_id',FILTER_VALIDATE_INT);
if (!$b || $b<1) pagamento_json(['success'=>false,'error'=>'Colaborador inválido.'],400);
$stmt=$conn->prepare("SELECT DATE_FORMAT(prazo,'%Y-%m') mes,COUNT(*) total_funcoes,SUM(valor) total_valor FROM funcao_imagem WHERE colaborador_id=? AND DATE_FORMAT(prazo,'%Y-%m')<? GROUP BY mes ORDER BY mes");
$inicio=pagamento_competencia_inicio();
$stmt->bind_param('is',$b,$inicio); $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
if (FechamentoCompetenciaService::disponivel($conn)) {
 $stmt=$conn->prepare("SELECT c.competencia mes,m.total_centavos/100 total_valor,r.snapshot_json FROM pagamento_competencia c JOIN pagamento_competencia_colaborador m ON m.competencia_id=c.id JOIN pagamento_fechamento f ON f.id=m.fechamento_id JOIN pagamento_fechamento_revisao r ON r.id=m.revisao_id WHERE f.colaborador_id=? AND c.estado='CONCLUIDO' AND c.competencia>=? ORDER BY c.competencia");
 $stmt->bind_param('is',$b,$inicio); $stmt->execute();
 foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
  $snapshot=json_decode($row['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
  $row['total_funcoes']=count(array_filter($snapshot['composicao']['producao_consulta']['itens_analisados']??[],fn($s)=>$s['elegibilidade']['elegivel']));
  unset($row['snapshot_json']); $row['fonte']='FECHAMENTO'; $rows[]=$row;
 }
 $stmt->close();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
