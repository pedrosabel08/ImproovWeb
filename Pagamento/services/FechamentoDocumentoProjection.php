<?php

require_once __DIR__.'/FechamentoRevisaoRepository.php';
require_once __DIR__.'/FechamentoComposicaoRules.php';
require_once __DIR__.'/AdendoDocumentalApresentacao.php';

/** Projeção apenas de apresentação; nenhum SELECT/cálculo de elegibilidade, saldo ou total. */
final class FechamentoDocumentoProjection
{
    public const VERSION = 'adendo_revisao_documental_v4';

    public static function validar(array $r, int $fechamento): void
    {
        $s = $r['snapshot'] ?? [];
        $c = $s['composicao'] ?? [];
        $a = $c['financeiro_servicos'] ?? [];
        if (($r['fechamento_id'] ?? null) !== $fechamento || ($r['estado'] ?? null) !== 'PRONTO'
            || ($s['schema_version'] ?? null) !== 'pagamento_fechamento_snapshot_v1'
            || ($s['canonical_version'] ?? null) !== FechamentoSnapshot::VERSION
            || ($c['rule_version'] ?? null) !== FechamentoComposicaoRules::VERSION
            || ($c['servicos_rule_version'] ?? null) !== FechamentoFinanceiroRules::VERSION
            || ($a['rule_version'] ?? null) !== FechamentoFinanceiroRules::VERSION
            || ($c['acompanhamento_especial']['config_version'] ?? null) !== 'rubricas_especiais_r09_v1') {
            throw new DomainException('Revisão/versão de regras não permitida para documento.');
        }
        if (!hash_equals($r['snapshot_hash'], FechamentoSnapshot::hash($s))) {
            throw new DomainException('Hash financeiro inválido.');
        }
        if (($c['colaborador_id'] ?? null) !== $r['colaborador_id'] || ($c['competencia'] ?? null) !== $r['competencia']
            || ($a['colaborador_id'] ?? null) !== $r['colaborador_id'] || ($a['competencia'] ?? null) !== $r['competencia']
            || ($c['snapshot_em'] ?? null) !== ($s['snapshot_em'] ?? null) || ($a['snapshot_em'] ?? null) !== $c['snapshot_em']) {
            throw new DomainException('Identidade/instante do snapshot inválidos.');
        }
        if (($c['total_final_determinado'] ?? null) !== true || ($c['bloqueado'] ?? null) !== false || ($a['bloqueado'] ?? null) !== false
            || ($a['subtotal_servicos_completo'] ?? null) !== true || ($c['situacao'] ?? null) !== 'PRONTO'
            || !is_int($c['total_final_centavos'] ?? null) || $c['total_final_centavos'] < 0
            || !is_array($c['pendencias'] ?? null) || array_filter($c['pendencias'], fn ($p) => !is_array($p) || ($p['bloqueante'] ?? true) || ($p['severidade'] ?? '') === 'BLOQUEANTE')) {
            throw new DomainException('Revisão pendente/bloqueada não gera PDF.');
        }
        foreach (['VALOR_FIXO','ACOMPANHAMENTO_ESPECIAL','BONUS_EXTRAS','SERVICOS'] as $key) {
            if (!is_int($c['componentes'][$key] ?? null) || $c['componentes'][$key] < 0) {
                throw new DomainException('Componente financeiro indeterminado.');
            }
        }
        if (isset($c['monthly_rule_version'])) {
            if ($c['monthly_rule_version'] !== 'fechamento_mensal_v1' || !in_array($c['tipo_remuneracao'] ?? null, ['FIXO','VARIAVEL','FIXO_VARIAVEL'], true)
                || !is_int($c['componentes']['BONUS_PRODUTIVIDADE'] ?? null) || $c['componentes']['BONUS_PRODUTIVIDADE'] < 0) {
                throw new DomainException('Composição mensal inválida.');
            }
        }
        if (!is_array($a['servicos_devidos'] ?? null) || !array_is_list($a['servicos_devidos'])
            || !is_array($c['extras']['itens'] ?? null) || !array_is_list($c['extras']['itens'])
            || !in_array($c['extras']['estado'] ?? null, ['SEM_BONUS','DEFINIDO'], true) || ($c['extras']['determinado'] ?? null) !== true
            || !is_int($c['fixo']['saldo_centavos'] ?? null) || $c['fixo']['saldo_centavos'] < 0
            || !is_bool($c['acompanhamento_especial']['aplicavel'] ?? null)
            || !is_int($c['acompanhamento_especial']['valor_centavos'] ?? null) || $c['acompanhamento_especial']['valor_centavos'] < 0) {
            throw new DomainException('Estrutura de apresentação do snapshot inválida.');
        }
    }

    public function projetar(array $r, array $identidade, string $dataDocumental): array
    {
        self::validar($r, $r['fechamento_id']);
        if ((int)($identidade['idcolaborador'] ?? 0) !== $r['colaborador_id'] || trim($identidade['nome_colaborador'] ?? '') === '') {
            throw new DomainException('Qualificação não corresponde ao beneficiário.');
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $dataDocumental, new DateTimeZone('America/Sao_Paulo'));
        if (!$dt || $dt->format('Y-m-d') !== $dataDocumental) {
            throw new InvalidArgumentException('Data documental inválida.');
        }
        $c = $r['snapshot']['composicao'];
        $fmt = new AdendoDocumentalApresentacao();
        $datas = new ContratoDateService();
        $mensal = isset($c['monthly_rule_version']);
        $compet = $datas->getCompetenciaInfo($r['competencia']);
        $contratante = $fmt->contratante($r['colaborador_id']);
        $rows = [];
        $html = '<table class="tabela"><thead><tr><th>No.</th><th>Nome da Imagem</th><th>Função</th><th class="cell-right">Valor (R$)</th></tr></thead><tbody>';
        $servicos = $c['financeiro_servicos']['servicos_devidos'];
        if ($mensal) {
            $servicos = array_values(array_filter(
                ($c['producao_consulta'] ?? $c['financeiro_servicos'])['itens_analisados'],
                fn ($i) => $i['elegibilidade']['elegivel'] && (in_array($i['situacao'], ['DEVIDO','SEM_VALOR_DEVIDO'], true)
                    || ($i['situacao'] === 'QUITADO' && self::pagoNaCompetencia($i, $r['competencia'])))
            ));
        }
        usort($servicos, static function (array $a, array $b): int {
            $compare = static fn ($x, $y) => strnatcasecmp((string)($x ?? ''), (string)($y ?? ''));
            return $compare($a['descricao']['obra_nome'] ?? $a['descricao']['obra_id'] ?? null, $b['descricao']['obra_nome'] ?? $b['descricao']['obra_id'] ?? null)
                ?: $compare($a['descricao']['imagem'] ?? null, $b['descricao']['imagem'] ?? null)
                ?: $compare($a['descricao']['animacao_id'] ?? null, $b['descricao']['animacao_id'] ?? null)
                ?: $compare($a['descricao']['funcao'] ?? null, $b['descricao']['funcao'] ?? null);
        });
        foreach ($servicos as $i) {
            if (!is_array($i) || !is_int($i['saldo_centavos'] ?? null) || $i['saldo_centavos'] < 0 || (!$mensal && $i['saldo_centavos'] === 0)
                || ($i['identidade']['beneficiario_id'] ?? null) !== $r['colaborador_id']
                || !is_string($i['descricao']['imagem'] ?? null) || !is_string($i['descricao']['funcao'] ?? null)) {
                throw new DomainException('Linha de serviço inválida no snapshot.');
            }
            $funcao = (string)($i['descricao']['funcao'] ?? '');
            if (($i['identidade']['classe'] ?? '') === FechamentoFinanceiroRules::COMISSAO) {
                $funcao = 'Comissão gestor - '.$funcao;
            }
            $pagoNaCompetencia = $mensal && $i['situacao'] === 'QUITADO' && self::pagoNaCompetencia($i, $r['competencia']);
            $valor = $pagoNaCompetencia ? self::valorPagoNaCompetencia($i, $r['competencia']) : ($mensal && $c['tipo_remuneracao'] === 'FIXO' ? 0 : $i['saldo_centavos']);
            $imagem = (string)($i['descricao']['imagem'] ?? '');
            if (($i['identidade']['origem'] ?? '') === 'funcao_animacao' && trim((string)($i['descricao']['tipo_animacao'] ?? '')) !== '') {
                $imagem .= ' - '.self::formatarTipoAnimacao((string)$i['descricao']['tipo_animacao']);
            }
            $row = ['identidade' => $i['identidade'],'imagem' => $imagem,'funcao' => $funcao,'valor_centavos' => $valor,
                'situacao' => $pagoNaCompetencia ? 'PAGO_NA_COMPETENCIA' : $i['situacao']];
            $rows[] = $row;
            $funcaoHtml = AdendoDocumentalApresentacao::h($funcao).($pagoNaCompetencia ? ' <strong>(Pago nesta competência)</strong>' : '');
            $html .= '<tr><td class="cell-center">'.count($rows).'</td><td>'.AdendoDocumentalApresentacao::h($row['imagem']).'</td><td>'.$funcaoHtml.'</td><td class="cell-right">'.($mensal && $valor === 0 ? '-' : AdendoDocumentalApresentacao::moeda($valor)).'</td></tr>';
        }
        if (!$rows) {
            $html .= '<tr><td colspan="4">Sem itens para este período.</td></tr>';
        }
        $html .= '</tbody></table>';
        // Exibir todos os componentes já determinados, sem somar ou decidir valores.
        $rubricas = $mensal ? [] : [['categoria' => 'Valor fixo','valor_centavos' => $c['fixo']['saldo_centavos']]];
        if (($c['bonus_produtividade']['valor_centavos'] ?? 0) > 0) {
            $rubricas[] = ['categoria' => 'Bônus produtividade — '.$c['bonus_produtividade']['faixa'],'valor_centavos' => $c['bonus_produtividade']['valor_centavos']];
        }
        if ($c['acompanhamento_especial']['aplicavel']) {
            $rubricas[] = ['categoria' => 'Acompanhamento','valor_centavos' => $c['acompanhamento_especial']['valor_centavos']];
        }
        foreach ($c['extras']['itens'] as $e) {
            $rubricas[] = ['categoria' => $e['categoria'],'valor_centavos' => $e['valor_centavos'],'referencia' => $e['referencia']];
        }
        if ($mensal && ($c['desconto']['valor_centavos'] ?? 0) > 0) {
            $rubricas[] = ['categoria' => 'Desconto — '.$c['desconto']['motivo'],'valor_centavos' => $c['desconto']['valor_centavos'],'desconto' => true];
        }
        if ($rubricas) {
            $html .= '<br><table class="tabela"><thead><tr><th>Categoria</th><th class="cell-right">Valor (R$)</th></tr></thead><tbody>';
        }
        foreach ($rubricas as $e) {
            if (!is_int($e['valor_centavos']) || $e['valor_centavos'] < 0) {
                throw new DomainException('Rubrica indeterminada no snapshot.');
            }
            $html .= '<tr><td>'.AdendoDocumentalApresentacao::h($e['categoria']).'</td><td class="cell-right">'.(!empty($e['desconto']) ? '- ' : '').AdendoDocumentalApresentacao::moeda($e['valor_centavos']).'</td></tr>';
        }
        if ($rubricas) {
            $html .= '</tbody></table>';
        }
        $totalDocumental = max(0, $c['total_final_centavos'] - (int)($c['pagamentos_competencia_incluidos_centavos'] ?? 0));
        $h = [AdendoDocumentalApresentacao::class,'h'];
        $p = ['titulo_adendo' => 'ADENDO CONTRATUAL - '.$compet['mes_nome'].' '.$compet['ano'],
            'contratante_nome' => $h($contratante['nome']),'contratante_cnpj' => $h($contratante['cnpj']),
            'dados_colaborador' => $fmt->qualificacao($identidade),'competencia_mes_nome' => $compet['mes_nome'],'competencia_ano' => $compet['ano'],
            'tabela_servicos' => $html,'valor_total' => AdendoDocumentalApresentacao::moeda($totalDocumental),
            'valor_total_extenso' => $h($fmt->extenso($totalDocumental)),'data_pagamento' => $h($fmt->pagamento($r['competencia'])),
            'data_atual' => $h($datas->formatDataPtBr($dt)),'contratado_nome' => $h($identidade['nome_colaborador']),
            'contratado_nome_empresarial' => $h($identidade['nome_empresarial'] ?? ''),'contratado_cpf' => $h($identidade['cpf'] ?? ''),
            'assinatura_cnpj_contratado' => empty($identidade['cnpj']) ? '' : '<div>CNPJ: '.$h($identidade['cnpj']).'</div>'];
        return ['version' => self::VERSION,'revisao_id' => $r['id'],'revisao_numero' => $r['numero'],'fechamento_id' => $r['fechamento_id'],
            'colaborador_id' => $r['colaborador_id'],'competencia' => $r['competencia'],'financial_snapshot_hash' => $r['snapshot_hash'],
            'identidade_legal' => $identidade,'contratante' => $contratante,'servicos' => $rows,'rubricas' => $rubricas,
            'total_centavos' => $c['total_final_centavos'],'total_documental_centavos' => $totalDocumental,'data_documental' => $dataDocumental,'placeholders' => $p];
    }

    private static function pagoNaCompetencia(array $item, string $competencia): bool
    {
        return self::valorPagoNaCompetencia($item, $competencia) > 0;
    }

    private static function valorPagoNaCompetencia(array $item, string $competencia): int
    {
        $total = 0;
        foreach ($item['pagamentos'] ?? [] as $pagamento) {
            if (($pagamento['competencia_pagamento'] ?? null) === $competencia) {
                $total += max(0, (int)($pagamento['valor_centavos'] ?? 0));
            }
        }
        return $total;
    }

    private static function formatarTipoAnimacao(string $tipo): string
    {
        $normalizado = mb_convert_case(trim($tipo),MB_CASE_TITLE,'UTF-8');
        return preg_replace('/(?<!\\p{L})Ia(?!\\p{L})/u','IA',$normalizado) ?? $normalizado;
    }
}
