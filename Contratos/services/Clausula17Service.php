<?php

class Clausula17Service
{
    public function buildClausula17(array $funcoes): array
    {
        $funcoesNomes = array_map(function ($f) {
            return mb_strtolower(trim($f['nome_funcao'] ?? ''), 'UTF-8');
        }, $funcoes);

        $trechos = [];
        $trechos[] = 'CLÁUSULA 17ª - DA REMUNERAÇÃO E TABELAS DE VALORES';
        $trechos[] = '17.1. A remuneração pelos serviços prestados observará as funções efetivamente desempenhadas pelo CONTRATADO, conforme tabelas abaixo:';

        $tabelas = $this->montarTabelasPorFuncao($funcoesNomes);
        if (!empty($tabelas)) {
            $trechos[] = implode("\n\n", $tabelas);
        }

        $trechos[] = '17.2. Os valores apresentados podem ser atualizados mediante comunicação prévia entre as partes, preservadas as condições previamente pactuadas para os serviços já iniciados.';
        $trechos[] = '17.3. A forma de pagamento seguirá a política vigente da CONTRATANTE, podendo ocorrer por entrega, por etapa ou por competência mensal, conforme o tipo de serviço.';

        if (in_array('animação', $funcoesNomes, true) || in_array('animacao', $funcoesNomes, true)) {
            $trechos[] = '17.4. Para serviços de ANIMAÇÃO, o pagamento poderá ser dividido por marcos de entrega previamente definidos em cronograma.';
        }

        if (in_array('modelagem', $funcoesNomes, true) || in_array('composição', $funcoesNomes, true) || in_array('composicao', $funcoesNomes, true)) {
            $trechos[] = '17.5. Para serviços de MODELAGEM/COMPOSIÇÃO, o pagamento seguirá a tabela por unidade/tarefa conforme especificação técnica enviada pela CONTRATANTE.';
        }

        if (in_array('finalização', $funcoesNomes, true) || in_array('finalizacao', $funcoesNomes, true)) {
            $trechos[] = '17.6. Para serviços de FINALIZAÇÃO, o pagamento seguirá a tabela por imagem aprovada, incluindo possíveis ajustes conforme padrão de qualidade.';
        }

        $texto = implode("\n\n", $trechos);

        return [
            'texto' => $texto,
            'tabelas' => $tabelas,
            'funcoes' => $funcoesNomes,
        ];
    }

    private function montarTabelasPorFuncao(array $funcoesNomes): array
    {
        $tabelas = [];

        if (in_array('finalização', $funcoesNomes, true) || in_array('finalizacao', $funcoesNomes, true)) {
            $tabelas[] = "Tabela Finalização:\n- Finalização Parcial: R$ [VALOR]\n- Finalização Completa: R$ [VALOR]";
        }

        if (in_array('modelagem', $funcoesNomes, true)) {
            $tabelas[] = "Tabela Modelagem:\n- Modelagem Base: R$ [VALOR]\n- Modelagem Avançada: R$ [VALOR]";
        }

        if (in_array('composição', $funcoesNomes, true) || in_array('composicao', $funcoesNomes, true)) {
            $tabelas[] = "Tabela Composição:\n- Composição Simples: R$ [VALOR]\n- Composição Completa: R$ [VALOR]";
        }

        if (in_array('animação', $funcoesNomes, true) || in_array('animacao', $funcoesNomes, true)) {
            $tabelas[] = "Tabela Animação:\n- Animação Curta: R$ [VALOR]\n- Animação Longa: R$ [VALOR]";
        }

        if (empty($tabelas)) {
            $tabelas[] = "Tabela Geral:\n- Serviço Padrão: R$ [VALOR]";
        }

        return $tabelas;
    }
}
