<?php

require_once __DIR__ . '/../../Pagamento/services/FechamentoComposicaoRules.php';

/** Fatos persistidos/static source auditado. Não executa legado, modal ou PDF. */
final class ComparacaoComposicaoFinanceira
{
    public static function regraEspecialLegada(int $b): array
    {
        $source = file_get_contents(__DIR__ . '/../../Contratos/services/AdendoLocalService.php');
        $hash = hash('sha256', $source);
        if ($hash !== 'aba9ad09b2ca9e335a7fd1dcab390dbbbefaaf1e6e8933057f85aa855a1a18bb') throw new RuntimeException('AdendoLocalService mudou; auditar diagnóstico 1B.');
        $pattern = '/if\s*\(\$colaboradorId\s*===\s*(\d+)\)\s*\{\s*\/\/[^\n]*\n\s*\$extras\s*=\s*\[\[\x27categoria\x27\s*=>\s*\x27([^\x27]+)\x27,\s*\x27valor\x27\s*=>\s*([\d.]+)\]\]/';
        if (!preg_match($pattern, $source, $m)) throw new RuntimeException('Regra especial legada não reconhecida; auditar.');
        return ['aplicavel' => $b === (int)$m[1], 'valor_centavos' => $b === (int)$m[1] ? FechamentoComposicaoSupport::moeda($m[3]) : 0,
            'categoria' => $m[2], 'source_sha256' => $hash, 'limite' => 'Regra estática auditada, não geração/documento executado.'];
    }

    public static function comparar(array $contexto, array $r, array $especialLegado): array
    {
        $diff = []; $b = $contexto['colaborador_id']; $ref = $contexto['competencia'];
        $add = function(string $campo, $antes, $depois, ?string $regra, string $motivo) use (&$diff): void {
            $diff[] = ['campo'=>$campo,'antes'=>$antes,'depois'=>$depois,'classificacao'=>$regra?'EXPECTED_TARGET_CHANGE':'UNEXPECTED_DIFFERENCE','regra'=>$regra,'motivo'=>$motivo];
        };
        $raw = $contexto['fixo']['configurado'] ?? null;
        $cad = $raw === null ? null : FechamentoComposicaoSupport::moeda($raw);
        if ($cad !== $r['fixo']['configurado_centavos']) $add('fixo_configurado_centavos',$cad,$r['fixo']['configurado_centavos'],null,'Configuração canônica difere do fato carregado.');
        if ($especialLegado['valor_centavos'] !== $r['acompanhamento_especial']['valor_centavos']) {
            $add('especial_centavos',$especialLegado['valor_centavos'],$r['acompanhamento_especial']['valor_centavos'],null,'Rubrica especial difere da regra aprovada/auditada.');
        }
        if ($cad !== null && $cad > 0 && empty($contexto['fixo']['evidencias_liquidacao'])) {
            $correto = $r['fixo']['estado_liquidacao'] === 'LIQUIDACAO_INDETERMINADA' && $r['fixo']['pago_centavos'] === null && $r['fixo']['saldo_centavos'] === null;
            $add('liquidacao_fixo','NAO_DISCRIMINADA_NO_LEGADO',$r['fixo']['estado_liquidacao'],$correto?'D01/R11':null,
                $correto?'Configuração/status/assinatura/total não provam liquidação; nenhuma parcela é inferida.':'Fixo sem evidência foi determinado incorretamente.');
        }
        if (($contexto['extras']['estado'] ?? 'PENDENTE') === 'PENDENTE') {
            $correto = $r['extras']['estado'] === 'PENDENTE' && $r['extras']['subtotal_centavos'] === null;
            $add('extras_sem_decisao','NAO_PRESERVADOS_NO_LEGADO',$r['extras']['estado'],$correto?'D02/R12':null,
                'Falta de decisão/rubricas persistidas não equivale a SEM_BONUS.');
        }
        if (isset($r['total_final_determinado']) && $r['total_final_determinado']
            && ($r['bloqueado'] || $r['componentes_indeterminados'])) {
            $add('total_final_determinado',false,true,null,'Total final declarado apesar de bloqueio/componente indeterminado.');
        }
        $historicos=[];
        foreach($contexto['fixo']['evidencias_legadas']['adendos']??[] as $a) {
            $p=$a['payload_financeiro'];$mesCompativel=($p['COMPETENCIA']??null)===$ref;
            $historicos[]=['adendo_id'=>$a['id'],'status'=>$a['status'],'payload_financeiro'=>$p,
                'payload_competencia_compativel'=>$mesCompativel,'comparabilidade'=>'SEM_EQUIVALENCIA_COM_LIQUIDACAO_OU_TOTAL_ATUAL',
                'motivo'=>'Valor documental de outra preparação; não prova pago, decisão de bônus ou componentes atuais.'];
        }
        return ['colaborador_id'=>$b,'competencia'=>$ref,'snapshot_em'=>$r['snapshot_em']??null,
            'rule_version'=>FechamentoComposicaoRules::VERSION,'fixo_configurado_centavos'=>$cad,
            'regra_especial_legada'=>$especialLegado,'historicos_identificados'=>$historicos,
            'diferencas'=>$diff,'resumo_classificacoes'=>array_count_values(array_column($diff,'classificacao')),
            'limites'=>'Não compara totais históricos incompletos, não reconstrói extras perdidos, não usa modal/DOM e não executa PDF/legado.' ];
    }
}
