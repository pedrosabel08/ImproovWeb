<?php

class Clausula1Service
{
    public function buildClausula1(int $colaboradorId, array $funcoes): string
    {
        $funcoesOriginais = [];
        foreach ($funcoes as $f) {
            $nome = isset($f['nome_funcao']) ? trim((string)$f['nome_funcao']) : '';
            if ($nome !== '') {
                $funcoesOriginais[] = $nome;
            }
        }

        // Exceção: colaborador 16
        if ($colaboradorId === 16) {
            $funcoesTexto = 'modelagem de fachada';
            return $this->render(
                $this->buildTextoPadrao($funcoesTexto),
                $this->buildLista('LISTA DE PROJETOS:', $this->defaultProjetos()),
                '',
                $this->buildEtapasPadrao()
            );
        }

        $funcoesNomesNorm = array_map(fn($n) => $this->normalize($n), $funcoesOriginais);

        $hasCaderno = $this->hasAny($funcoesNomesNorm, ['caderno']);
        $hasFiltroAssets = $this->hasAny($funcoesNomesNorm, ['filtro de assets', 'filtro assets', 'filtro']);
        $hasModelagem = $this->hasAny($funcoesNomesNorm, ['modelagem']);
        $hasComposicao = $this->hasAny($funcoesNomesNorm, ['composicao', 'composição']);
        $hasPosProducao = $this->hasAny($funcoesNomesNorm, ['pos-producao', 'pós-produção', 'pos producao', 'pós producao']);
        $hasAnimacao = $this->hasAny($funcoesNomesNorm, ['animacao', 'animação']);

        // robusto: detectar finalização pelo nome ou pelo nível
        $hasFinalizacao = $this->hasFinalizacaoFunc($funcoes);

        // robusto: planta humanizada por nome
        $hasPlantaHumanizada = $this->hasPlantaHumanizadaFunc($funcoes);

        // Se já existir explicitamente "finalização de planta humanizada" (mesmo com variações)
        $hasPlantaFinalizacaoExplicit = $this->hasPlantaFinalizacaoExplicitFunc($funcoes);

        // Regra: se tiver planta humanizada + finalização, sempre funde e remove a finalização "única"
        if ($hasPlantaFinalizacaoExplicit || ($hasPlantaHumanizada && $hasFinalizacao)) {
            $funcoesOriginais = [];
            foreach ($funcoes as $f) {
                $nome = isset($f['nome_funcao']) ? trim((string)$f['nome_funcao']) : '';
                if ($nome === '') {
                    continue;
                }

                $nn = $this->normalize($nome);
                $nivel = $f['nivel_finalizacao'] ?? null;

                // remove qualquer planta humanizada
                if (strpos($nn, 'planta humanizada') !== false) {
                    continue;
                }

                // remove a própria combinada para evitar duplicar
                if (strpos($nn, 'finalizacao de planta humanizada') !== false) {
                    continue;
                }

                // remove finalização "solta" (por nome ou nível)
                if ($this->startsWith($nn, 'finalizacao')) {
                    continue;
                }
                if ($nivel !== null && $nivel !== '') {
                    continue;
                }

                $funcoesOriginais[] = $nome;
            }

            $funcoesOriginais[] = 'finalização de planta humanizada';
        }

        $funcoesTexto = $this->joinFuncoes($funcoesOriginais);

        // Caso caderno/filtro (confirmado: não possuem outras funções)
        if ($hasCaderno || $hasFiltroAssets) {
            $texto = $this->buildTextoCaderno($funcoesTexto);
            $lista = $this->buildLista('LISTA DE IMAGENS:', $this->defaultImagens());
            return $this->render($texto, $lista, '', '');
        }

        // Texto padrão da cláusula
        $texto = $this->buildTextoPadrao($funcoesTexto);

        // Lista de imagens sempre padrão (exceto id=16)
        $listaImagens = $this->buildLista('LISTA DE IMAGENS:', $this->defaultImagens());

        // Extra animação: lista de cenas (3 itens padrão)
        $listaCenas = $hasAnimacao
            ? $this->buildLista('LISTA DE CENAS DE ANIMAÇÕES 3D:', $this->defaultCenas())
            : '';

        // Etapas
        // Pós-produção muda somente a etapa; finalização "tudo padrão" (não altera aqui)
        $etapas = '';
        if ($hasPosProducao && !$hasModelagem && !$hasComposicao) {
            $etapas = $this->buildEtapasPosProducao();
        } elseif ($hasModelagem || $hasComposicao || $hasFinalizacao || $hasAnimacao || $hasPosProducao) {
            $etapas = $this->buildEtapasPadrao();
        }

        return $this->render($texto, $listaImagens, $listaCenas, $etapas);
    }

    private function buildTextoPadrao(string $funcoesTexto): string
    {
        return '<p><strong>Cláusula 1ª</strong>. Objetiva o presente contrato estabelecer, a prestação dos serviços de ' .
            $this->h($funcoesTexto) .
            ' para a produção, pela parte CONTRATADA conforme termos e condições estipuladas no presente instrumento.</p>';
    }

    private function buildTextoCaderno(string $funcoesTexto): string
    {
        return '<p><strong>Cláusula 1ª</strong>. Objetiva o presente contrato estabelecer, a prestação dos serviços de desenvolvimento de cadernos, acompanhamento da produção
de imagens, separação de assets para a produção, pela parte CONTRATADA conforme termos e condições estipuladas no presente instrumento.</p>';
    }

    private function buildLista(string $titulo, array $itens): string
    {
        $li = [];
        foreach ($itens as $item) {
            $li[] = '<li>' . $this->h($item) . '</li>';
        }

        return '<p><strong>' . $this->h($titulo) . '</strong></p>' .
            '<ul class="list">' . implode('', $li) . '</ul>';
    }

    private function buildEtapasPadrao(): string
    {
        $itens = [
            'Kickoff para entendimento de briefing e de como será o desenvolvimento',
            'Análise e leitura de projetos',
            'Construção de cenas em ambientes virtuais',
            'Modelar, criar ou ajustar blocos em modelo 3D para composição dos ambientes virtuais',
            'Dar forma e volumes aos ambientes virtuais conforme instruções e leitura de projeto',
            'Realizar ajustes solicitados pela CONTRATANTE antes da entrega de cada revisão para o cliente',
            'Após aprovada, o ambiente deverá ser entregue em arquivo matriz e de acordo com possíveis instruções',
        ];

        return $this->buildEtapas($itens);
    }

    private function buildEtapasPosProducao(): string
    {
        $itens = [
            'Kickoff para entendimento de briefing e de como será o desenvolvimento',
            'Montagem de fotos a serem utilizadas nas pós-produções',
            'Realizar ajustes solicitados pela CONTRATANTE antes da entrega de cada revisão para o cliente',
            'Após aprovada, a imagem deverá ser finalizada na resolução e de acordo com possíveis instruções',
        ];

        return $this->buildEtapas($itens);
    }

    private function buildEtapas(array $itens): string
    {
        $li = [];
        foreach ($itens as $item) {
            $li[] = '<li>' . $this->h($item) . '</li>';
        }

        return '<p><strong>ETAPAS DE DESENVOLVIMENTO DE CADA IMAGEM:</strong></p>' .
            '<ol class="list">' . implode('', $li) . '</ol>';
    }

    private function render(string $texto, string $listaImagens, string $listaCenas, string $etapas): string
    {
        $parts = array_values(array_filter([$texto, $listaImagens, $listaCenas, $etapas], fn($v) => $v !== ''));
        return implode("\n", $parts);
    }

    private function defaultImagens(): array
    {
        return ['Imagem 1', 'Imagem 2', 'Imagem 3'];
    }

    private function defaultCenas(): array
    {
        return ['Cena 1', 'Cena 2', 'Cena 3'];
    }

    private function defaultProjetos(): array
    {
        return ['PROJETO 1', 'PROJETO 2', 'PROJETO 3', 'PROJETO 4'];
    }

    private function joinFuncoes(array $funcoes): string
    {
        $funcoes = array_values(array_filter($funcoes, fn($v) => trim((string)$v) !== ''));
        // garantir que o nome das funções começa com letra minúscula (multibyte)
        $funcoes = array_map(function ($f) {
            $f = (string)$f;
            $first = mb_substr($f, 0, 1, 'UTF-8');
            $rest = mb_substr($f, 1, null, 'UTF-8');
            return mb_strtolower($first, 'UTF-8') . $rest;
        }, $funcoes);
        $count = count($funcoes);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $funcoes[0];
        }
        if ($count === 2) {
            return $funcoes[0] . ' e ' . $funcoes[1];
        }
        $last = array_pop($funcoes);
        return implode(', ', $funcoes) . ' e ' . $last;
    }

    private function hasAny(array $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($this->normalize($needle), $haystack, true)) {
                return true;
            }
        }
        return false;
    }

    private function hasFinalizacaoFunc(array $funcoes): bool
    {
        foreach ($funcoes as $f) {
            $nivel = $f['nivel_finalizacao'] ?? null;
            if ($nivel !== null && $nivel !== '') {
                return true;
            }
            $nome = isset($f['nome_funcao']) ? (string)$f['nome_funcao'] : '';
            if ($nome !== '' && $this->startsWith($this->normalize($nome), 'finalizacao')) {
                return true;
            }
        }
        return false;
    }

    private function hasPlantaHumanizadaFunc(array $funcoes): bool
    {
        foreach ($funcoes as $f) {
            $nome = isset($f['nome_funcao']) ? (string)$f['nome_funcao'] : '';
            if ($nome !== '' && strpos($this->normalize($nome), 'planta humanizada') !== false) {
                return true;
            }
        }
        return false;
    }

    private function hasPlantaFinalizacaoExplicitFunc(array $funcoes): bool
    {
        foreach ($funcoes as $f) {
            $nome = isset($f['nome_funcao']) ? (string)$f['nome_funcao'] : '';
            if ($nome !== '' && strpos($this->normalize($nome), 'finalizacao de planta humanizada') !== false) {
                return true;
            }
        }
        return false;
    }

    private function hasContainsAny(array $haystack, array $needles): bool
    {
        $needles = array_map(fn($n) => $this->normalize((string)$n), $needles);
        foreach ($haystack as $item) {
            $item = (string)$item;
            foreach ($needles as $needle) {
                if ($needle !== '' && strpos($item, $needle) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    private function hasStartsWithAny(array $haystack, array $prefixes): bool
    {
        $prefixes = array_map(fn($p) => $this->normalize((string)$p), $prefixes);
        foreach ($haystack as $item) {
            $item = (string)$item;
            foreach ($prefixes as $prefix) {
                if ($prefix !== '' && $this->startsWith($item, $prefix)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function startsWith(string $value, string $prefix): bool
    {
        return $prefix === '' ? true : strncmp($value, $prefix, strlen($prefix)) === 0;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        // normalizar espaços (inclui NBSP)
        $value = str_replace(["\xC2\xA0", "\xA0"], ' ', $value);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            }
        }

        // substituir pontuação/separadores por espaço
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    private function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
