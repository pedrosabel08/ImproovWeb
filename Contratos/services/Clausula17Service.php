<?php

class Clausula17Service
{
    private const BONUS_21_30 = 1;
    private const BONUS_31_PLUS = 2;

    public function buildClausula17(array $funcoes, ?int $colaboradorId = null): array
    {
        // Exceção: colaborador 16 tem preço fixo e ignora demais funções
        if ($colaboradorId === 16) {
            $trechos = [];
            $trechos[] = '<strong>VII. DO PREÇO E DAS CONDIÇÕES DE PAGAMENTO</strong>';
            $trechos[] = '<strong>Cláusula 17ª.</strong> Em contrapartida à efetiva execução do objeto do presente contrato, a CONTRATANTE pagará à parte CONTRATADA o valor gradual de acordo com a quantidade de imagens virtuais entregues com suas respectivas imagens conforme exposto na tabela abaixo:<br>';
            $linhas = [];
            $linhas[] = '<span class="titulo-funcao">Modelagem de fachada:</span>';
            $linhas[] = '<span>Valor fixo de R$ 1.000,00 (Mil reais) por modelagem de fachada de ambiente virtual desenvolvido.</span>';
            $trechos[] = implode("\n", $linhas);
            $trechos[] = $this->buildParagrafos(false);
            $texto = implode("\n", $trechos);

            return [
                'texto' => $texto,
                'funcoes' => [],
            ];
        }
        // Exceção: colaborador 13 tem preço fixo e ignora demais funções
        if ($colaboradorId === 13) {
            $trechos = [];
            $trechos[] = '<strong>VII. DO PREÇO E DAS CONDIÇÕES DE PAGAMENTO</strong>';
            $trechos[] = '<strong>Cláusula 17ª.</strong> Em contrapartida à efetiva execução do objeto do presente contrato, a CONTRATANTE pagará à parte CONTRATADA o valor gradual de acordo com a quantidade de imagens virtuais entregues com suas respectivas imagens conforme exposto na tabela abaixo:<br>';
            $linhas = [];
            $linhas[] = '<span class="titulo-funcao">Animação:</span>';
            $linhas[] = '<span>Valor fixo de R$ 175,00 (Cento e setenta e cinco reais) por cena de animação preview entregue e R$ 175,00 (cento e setenta e cinco reais) por cena de animação renderizada e finalizada com pós-produção.</span>';
            $trechos[] = implode("\n", $linhas);
            $trechos[] = $this->buildParagrafos(false);
            $texto = implode("\n", $trechos);

            return [
                'texto' => $texto,
                'funcoes' => [],
            ];
        }

        $funcoesNomes = array_map(function ($f) {
            return mb_strtolower(trim($f['nome_funcao'] ?? ''), 'UTF-8');
        }, $funcoes);

        $temFinalizacao = $this->temFinalizacao($funcoesNomes);
        $temPlantaHumanizada = $this->hasFuncao($funcoesNomes, ['planta humanizada', 'planta-humanizada']);
        $temPosProducao = $this->hasFuncao($funcoesNomes, ['pós-produção', 'pos-producao', 'pos producao', 'pos-produção']);
        $temModelagem = $this->hasFuncao($funcoesNomes, ['modelagem']);
        $temComposicao = $this->hasFuncao($funcoesNomes, ['composição', 'composicao']);
        $temAlteracao = $this->hasFuncao($funcoesNomes, ['alteração', 'alteracao']);
        $temCaderno = $this->hasFuncao($funcoesNomes, ['caderno']);
        $nivelFinalizacao = $this->getNivelFinalizacao($funcoes);
        $finalizacaoInfo = $this->getFinalizacaoInfo($nivelFinalizacao);

        // Se tiver planta humanizada + finalização, prioriza planta (não exibe bloco/parágrafos de finalização)
        $temFinalizacaoEfetiva = $temFinalizacao && !$temPlantaHumanizada;

        $trechos = [];
        $trechos[] = '<strong>VII. DO PREÇO E DAS CONDIÇÕES DE PAGAMENTO</strong>';
        $trechos[] = '<strong>Cláusula 17ª.</strong> Em contrapartida à efetiva execução do objeto do presente contrato, a CONTRATANTE pagará à parte CONTRATADA o valor gradual de acordo com a quantidade de imagens virtuais entregues com suas respectivas imagens conforme exposto na tabela abaixo:<br>';

        $linhas = [];
        if ($temPosProducao) {
            $linhas[] = '<span class="titulo-funcao">Pós-produção:</span>';
            $linhas[] = '<span>Valor fixo de R$ 60,00 (Sessenta reais) por desenvolvimento de pós-produção para os ambientes virtuais.</span>';
        }
        if ($temCaderno) {
            $linhas[] = '<span class="titulo-funcao">Caderno e filtro de assets:</span>';
            $linhas[] = '<span>Valor fixo de R$ 50,00 (Cinquenta reais) por desenvolvimento de caderno de interiores e R$ 20,00 (vinte reais) pela separação de assets para a produção dos interiores dos ambientes virtuais.</span>';
        }

        if (($temModelagem && $temComposicao) || $temAlteracao) {
            $linhas[] = '<span class="titulo-funcao">Modelagem e composição:</span>';
            $linhas[] = '<span>Valor fixo de R$ 50,00 (Cinquenta reais) por modelagem e R$ 50,00 (Cinquenta reais) por composição por ambiente.</span>';
        } else {
            if ($temModelagem) {
                $linhas[] = '<span class="titulo-funcao">Modelagem:</span>';
                $linhas[] = '<span>Valor fixo de R$ 50,00 (Cinquenta reais) por modelagem por ambiente.</span>';
            }
            if ($temComposicao) {
                $linhas[] = '<span class="titulo-funcao">Composição:</span>';
                $linhas[] = '<span>Valor fixo de R$ 50,00 (Cinquenta reais) por composição por ambiente.</span>';
            }
        }

        if ($temPlantaHumanizada) {
            $linhas[] = $this->buildPlantaHumanizadaBlock($finalizacaoInfo);
        } elseif ($temFinalizacaoEfetiva) {
            $linhas[] = $this->buildFinalizacaoBlock($finalizacaoInfo);
        }

        if ($temAnimacao = $this->hasFuncao($funcoesNomes, ['animação', 'animacao'])) {
            $linhas[] = $this->buildAnimacaoBlock($finalizacaoInfo);
        }

        if (!empty($linhas)) {
            $trechos[] = implode("\n", $linhas);
        }

        $trechos[] = $this->buildParagrafos($temFinalizacaoEfetiva);

        $texto = implode("\n", $trechos);

        return [
            'texto' => $texto,
            'funcoes' => $funcoesNomes,
        ];
    }

    private function temFinalizacao(array $funcoesNomes): bool
    {
        return $this->hasFuncao($funcoesNomes, ['finalização', 'finalizacao']);
    }

    private function hasFuncao(array $funcoesNomes, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $funcoesNomes, true)) {
                return true;
            }
        }
        return false;
    }

    private function getNivelFinalizacao(array $funcoes): ?int
    {
        foreach ($funcoes as $f) {
            $nome = mb_strtolower(trim($f['nome_funcao'] ?? ''), 'UTF-8');
            if ($nome === 'finalização' || $nome === 'finalizacao') {
                $nivel = isset($f['nivel_finalizacao']) ? (int) $f['nivel_finalizacao'] : null;
                if ($nivel && in_array($nivel, [1, 2, 3], true)) {
                    return $nivel;
                }
            }
        }
        return null;
    }

    private function getFinalizacaoInfo(?int $nivel): array
    {
        switch ($nivel) {
            case 1:
                return ['titulo' => 'Artista digital Nivel I - Heartstarter', 'valor' => 'R$ 250,00'];
            case 2:
                return ['titulo' => 'Artista digital Nivel II - Heartmaker', 'valor' => 'R$ 300,00'];
            case 3:
                return ['titulo' => 'Artista digital Nivel III - Heartmaster', 'valor' => 'R$ 380,00'];
            default:
                return ['titulo' => 'Artista digital Nivel não definido', 'valor' => 'valor a definir'];
        }
    }

    private function buildFinalizacaoBlock(array $finalizacaoInfo): string
    {
        $valor = $finalizacaoInfo['valor'] ?? 'valor a definir';
        $titulo = $finalizacaoInfo['titulo'] ?? '';

        $html = [];
        $html[] = '<p>';
        $html[] = '<span class="titulo-funcao">Finalização:</span>';
        $html[] = '<span class="titulo-funcao">' . $this->escapeHtmlInline($titulo) . '</span>';
        $html[] = '<span class="lista-seta-bullet">›</span><span class="lista-seta-text">Produção mínima esperada de 20 imagens finalizadas no processo de prévias / R0;</span>';
        $html[] = '<span class="lista-seta-bullet">›</span><span class="lista-seta-text">Pagamento de ' . $this->escapeHtmlInline($valor) . ' por imagem produzida e finalizada no processo de prévias / R0;</span>';
        $html[] = '<span class="lista-seta-bullet">›</span><span class="lista-seta-text">Bônus quando produzir de 21 a 30 imagens finalizadas no processo de prévias / R0;</span>';
        $html[] = '<span class="lista-seta-sub">Pagamento normal das imagens produzidas + pagamento de ' . self::BONUS_21_30 . ' imagem' . (self::BONUS_21_30 > 1 ? 's' : '') . ' como bônus.</span>';
        $html[] = '<span class="lista-seta-bullet">›</span><span class="lista-seta-text">Bônus quando produzir acima de 31 imagens finalizadas no processo de prévias / R0;</span>';
        $html[] = '<span class="lista-seta-sub">Pagamento normal das imagens produzidas + pagamento de ' . self::BONUS_31_PLUS . ' imagens como bônus.</span>';
        $html[] = '</p>';

        return implode("\n", $html);
    }

    private function buildPlantaHumanizadaBlock(array $finalizacaoInfo): string
    {
        // Por enquanto, os valores informados são do Nível 1 (Heartstarter).
        // Se vier outro nível, mantemos o título vindo do nível para não perder a informação.
        $titulo = $finalizacaoInfo['titulo'] ?? 'Artista Nivel 1 - Heartstarter';

        $html = [];
        $html[] = '<p>';
        $html[] = '<span class="titulo-funcao">Planta Humanizada:</span>';
        $html[] = '<span class="titulo-funcao">' . $this->escapeHtmlInline($titulo) . '</span>';
        $html[] = '<span class="lista-seta-bullet">›</span><span class="lista-seta-text">Apto individual- R$ 130,00</span>';
        $html[] = '<span class="lista-seta-bullet">›</span><span class="lista-seta-text">Apto Variação (repetição com alteração de poucos itens)- R$ 80,00</span>';
        $html[] = '<span class="lista-seta-bullet">›</span><span class="lista-seta-text">Pavimento aptos/garagem- R$ 150,00</span>';
        $html[] = '<span class="lista-seta-bullet">›</span><span class="lista-seta-text">Pavimento de garagem(repetição com alteração de poucos itens)- R$ 80,00</span>';
        $html[] = '<span class="lista-seta-bullet">›</span><span class="lista-seta-text">Pavimento Lazer/Implantação grande R$ 200,00</span>';
        $html[] = '</p>';

        return implode("\n", $html);
    }

    private function buildAnimacaoBlock(array $finalizacaoInfo): string
    {
        // Por enquanto, os valores informados são do Nível 1 (Heartstarter).
        // Se vier outro nível, mantemos o título vindo do nível para não perder a informação.

        $html = [];
        $html[] = '<p>';
        $html[] = '<span class="titulo-funcao">Animação:</span>';
        $html[] = '<span>Até 9 cenas: R$ 130,00 por cena</span>';
        $html[] = '<span>A partir de 10 cenas: R$ 140,00 por cena</span>';
        $html[] = '<span>A partir de 20 cenas: R$ 150,00 por cena</span>';
        $html[] = '<span>A partir de 30 cenas: R$ 160,00 por cena</span>';
        $html[] = '</p>';

        return implode("\n", $html);
    }

    private function escapeHtmlInline(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function buildParagrafos(bool $temFinalizacao): string
    {
        if ($temFinalizacao) {
            return implode("<br>", [
                '<p class="paragrafo"><strong>Parágrafo primeiro. </strong> As imagens que estiverem com status P00 finalizado será pago 50% do valor da imagem de acordo com a tabela de valores do nível da parte CONTRATADA.</p>',
                '<p class="paragrafo"><strong>Parágrafo segundo.</strong> Para recebimento dos valores descritos acima a parte CONTRATADA deverá fazer a entrega das imagens P00 (ângulos) R00 (imagem prévia) dentro do prazo de cada etapa do serviço que será estipulado entre as partes e deverá ter a aprovação final do cliente.</p>',
                '<p class="paragrafo"><strong>Parágrafo terceiro.</strong> Em caso de entrega das imagens e pagamento dos valores referente aos serviços aqui prestados, fica ciente a parte CONTRATADA que a imagem deverá seguir o processo até ser aprovada pelo cliente da parte CONTRATANTE, a parte CONTRATADA deverá fazer todas as revisões necessárias até a entrega final da imagem em alta resolução conforme as especificações de cada trabalho e a entrega dos arquivos correspondentes.</p>',
                '<p class="paragrafo"><strong>Parágrafo quarto.</strong> O pagamento da prestação de serviço será feito no 5º (quinto) dia útil do mês subsequente a contratação.</p>',
                '<p class="paragrafo"><strong>Parágrafo quinto.</strong> Para recebimento dos valores descritos acima, a parte CONTRATADA deverá apresentar à parte CONTRATANTE a respectiva NOTA FISCAL/FATURA, com antecedência mínima de 05 (cinco) dias da data do seu recebimento, sob pena de ser prorrogar o prazo para o pagamento por igual número de dias ao do atraso.</p>'
            ]);
        }

        return implode("<br>", [
            '<p class="paragrafo"><strong>Parágrafo primeiro.</strong> Para recebimento dos valores descritos acima parte CONTRATANTE deverá fazer a entrega das imagens dentro do prazo de cada etapa do serviço que será estipulado entre as partes e deverá ter a aprovação final do cliente.</p>',
            '<p class="paragrafo"><strong>Parágrafo segundo.</strong> Em caso de entrega das imagens e pagamento dos valores referente aos serviços aqui prestados, fica ciente a parte CONTRATADA que a imagem deverá seguir o processo até ser aprovada pelo cliente da parte CONTRATANTE, a parte CONTRATADA deverá fazer todas as revisões necessárias até a entrega final da imagem em alta resolução conforme as especificações de cada trabalho e a entrega dos arquivos correspondentes.</p>',
            '<p class="paragrafo"><strong>Parágrafo terceiro.</strong> O pagamento da prestação de serviço será feito no 5º (quinto) dia útil do mês subsequente a contratação.</p>',
            '<p class="paragrafo"><strong>Parágrafo quarto.</strong> Para recebimento dos valores descritos acima, a parte CONTRATADA deverá apresentar à parte CONTRATANTE a respectiva NOTA FISCAL/FATURA, com antecedência mínima de 05 (cinco) dias da data do seu recebimento, sob pena de ser prorrogar o prazo para o pagamento por igual número de dias ao do atraso.</p>'
        ]);
    }
}
