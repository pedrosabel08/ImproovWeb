<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../conexao.php';
require_once __DIR__.'/../Pagamento/services/FechamentoFinanceiroRepository.php';
$ref=$argv[1]??'2026-09';
$cols=$conn->query("SELECT idcolaborador,nome_colaborador,valor_fixo,tipo_remuneracao,participa_fechamento_mensal FROM colaborador WHERE nome_colaborador IN ('Nicolle','Anderson','Pedro Henrique','Bruna') ORDER BY idcolaborador")->fetch_all(MYSQLI_ASSOC);
foreach($cols as $col){
 $b=(int)$col['idcolaborador'];$leitura=(new FechamentoFinanceiroRepository($conn,true))->carregar($b,$ref);
 $result=(new FechamentoFinanceiroRules())->calcular($leitura['dados'],$b,$ref,$leitura['snapshot'],true);
 $stats=[];foreach($result['itens_analisados'] as $i){$s=$i['situacao'];$stats[$s]=($stats[$s]??0)+1;}
 $origens=array_map(fn($i)=>['id'=>$i['identidade']['origem_id'],'imagem'=>$i['descricao']['imagem'],'funcao'=>$i['descricao']['funcao'],'situacao'=>$i['situacao'],'base'=>$i['base_centavos'],'pago'=>$i['pago_centavos'],'saldo'=>$i['saldo_centavos'],'pendencias'=>array_column($i['divergencias'],'codigo')],array_slice($result['itens_analisados'],0,6));
 $stmt=$conn->prepare('SELECT d.id,d.estado,d.modelo_json FROM pagamento_fechamento_documento d JOIN pagamento_fechamento f ON f.id=d.fechamento_id WHERE f.colaborador_id=? AND f.competencia=? ORDER BY d.id DESC LIMIT 1');
 $stmt->bind_param('is',$b,$ref);$stmt->execute();$doc=$stmt->get_result()->fetch_assoc();$stmt->close();
 $modelo=$doc?json_decode($doc['modelo_json'],true):null;
 $documento=$modelo?['id'=>(int)$doc['id'],'estado'=>$doc['estado'],'tarefas'=>count($modelo['servicos']),'rubricas'=>$modelo['rubricas'],'total'=>$modelo['total_centavos'],'brinquedoteca_finalizacao'=>(bool)array_filter($modelo['servicos'],fn($i)=>$i['identidade']['origem_id']===109178)]:null;
 echo json_encode(['cadastro'=>$col,'candidatos'=>count($leitura['dados']['origens']),'situacoes'=>$stats,'subtotal'=>$result['subtotal_servicos_centavos'],'documento_atual'=>$documento,'itens'=>$origens],JSON_UNESCAPED_UNICODE)."\n";
}
$q="SELECT fi.idfuncao_imagem,fi.colaborador_id,fi.imagem_id,fi.status,fi.prazo,fi.pagamento,fi.valor,ico.imagem_nome,pi.idpagamento_item,pi.origem_id,pi.valor valor_pago,pi.observacao,p.colaborador_id beneficiario,p.mes_ref,p.status pagamento_status FROM funcao_imagem fi JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra=fi.imagem_id LEFT JOIN pagamento_itens pi ON pi.origem='funcao_imagem' AND pi.origem_id=fi.idfuncao_imagem LEFT JOIN pagamentos p ON p.idpagamento=pi.pagamento_id WHERE fi.funcao_id=4 AND ico.imagem_nome LIKE '9.WER_RIO Brinquedoteca%' ORDER BY fi.idfuncao_imagem,pi.idpagamento_item";
echo 'brinquedoteca: '.json_encode($conn->query($q)->fetch_all(MYSQLI_ASSOC),JSON_UNESCAPED_UNICODE)."\n";
