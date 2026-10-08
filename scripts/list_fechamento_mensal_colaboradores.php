<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../conexao.php';
$has=(int)$conn->query("SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='colaborador' AND COLUMN_NAME='tipo_remuneracao'")->fetch_assoc()['n'];
$tipo=$has?'c.tipo_remuneracao':'NULL tipo_remuneracao';
$hasParticipacao=(int)$conn->query("SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='colaborador' AND COLUMN_NAME='participa_fechamento_mensal'")->fetch_assoc()['n'];
$participacao=$hasParticipacao?'c.participa_fechamento_mensal':'NULL participa_fechamento_mensal';
$rows=$conn->query("SELECT c.idcolaborador,c.nome_colaborador,c.valor_fixo,$tipo,$participacao,
 GROUP_CONCAT(DISTINCT CASE WHEN fc.tipo_atuacao='PRINCIPAL' THEN f.nome_funcao END ORDER BY f.nome_funcao SEPARATOR ', ') funcoes
 FROM colaborador c LEFT JOIN funcao_colaborador fc ON fc.colaborador_id=c.idcolaborador
 LEFT JOIN funcao f ON f.idfuncao=fc.funcao_id WHERE c.ativo=1 GROUP BY c.idcolaborador ORDER BY c.nome_colaborador")->fetch_all(MYSQLI_ASSOC);
$md="# Colaboradores ativos — Fechamento Mensal V1\n\nCadastro observado em 06/10/2026. Nenhuma classificação foi inferida ou salva. Somente participação explícita Sim entra no fechamento.\n\n| Nome | Valor fixo atual | Funções principais | Remuneração | Participa do fechamento? |\n|---|---:|---|---|---|\n";
foreach($rows as $r) {
 $valor=$r['valor_fixo']===null?'Não configurado':'R$ '.number_format((float)$r['valor_fixo'],2,',','.');
 $participa=$r['participa_fechamento_mensal']===null?'Ainda não definido':((int)$r['participa_fechamento_mensal']===1?'Sim':'Não');
 $md.='| '.str_replace('|','/',$r['nome_colaborador']).' | '.$valor.' | '.($r['funcoes']?:'—').' | '.($r['tipo_remuneracao']?:'Não definido').' | '.$participa.' |'."\n";
}
file_put_contents(__DIR__.'/../docs/fechamento-mensal-v1-colaboradores.md',$md);
echo json_encode(['ativos'=>count($rows),'participacao_pendente'=>count(array_filter($rows,fn($r)=>$r['participa_fechamento_mensal']===null)),'participantes'=>count(array_filter($rows,fn($r)=>$r['participa_fechamento_mensal']!==null&&(int)$r['participa_fechamento_mensal']===1))])."\n";
