<?php

class ContratoQualificacaoService
{
    public function buildQualificacaoCompleta(array $colab): string
    {
        $nomeEmpresarial = $this->v($colab, 'nome_empresarial');
        $cnpj = $this->formatCnpj($this->v($colab, 'cnpj'));
        $estadoCivil = $this->v($colab, 'estado_civil');
        $cpf = $this->formatCpf($this->v($colab, 'cpf'));
        $nome = $this->v($colab, 'nome_colaborador');

        $enderecoColaborador = $this->montarEnderecoPessoa(
            $this->v($colab, 'rua'),
            $this->v($colab, 'numero'),
            $this->v($colab, 'complemento'),
            $this->v($colab, 'bairro'),
            $this->v($colab, 'localidade'),
            $this->v($colab, 'uf'),
            $this->formatCep($this->v($colab, 'cep'))
        );

        $enderecoCnpj = $this->montarEnderecoCnpj(
            $this->v($colab, 'rua_cnpj'),
            $this->v($colab, 'numero_cnpj'),
            $this->v($colab, 'complemento_cnpj'),
            $this->v($colab, 'bairro_cnpj'),
            $this->v($colab, 'localidade_cnpj'),
            $this->v($colab, 'uf_cnpj'),
            $this->formatCep($this->v($colab, 'cep_cnpj'))
        );

        $texto = "De outro, {$nomeEmpresarial}, CNPJ: {$cnpj}, com endereço/sede na {$enderecoCnpj}; se seguir denominado simplesmente parte CONTRATADA; neste ato representada por {$nome}, brasileiro(a), {$estadoCivil}(a), inscrito(a) no CPF sob o nº {$cpf}, residente e domiciliado(a) na {$enderecoColaborador}, doravante denominada parte CONTRATADA.";

        return $this->sanitize($texto);
    }

    private function montarEnderecoPessoa(string $rua, string $numero, string $complemento, string $bairro, string $localidade, string $uf, string $cep): string
    {
        $parts = [];
        if ($rua !== '') $parts[] = $this->normalizeRua($rua);
        if ($numero !== '') $parts[] = 'nº ' . $numero;
        if ($complemento !== '') $parts[] = $complemento;
        if ($bairro !== '') $parts[] = 'Bairro ' . $bairro;
        if ($localidade !== '' && $uf !== '') $parts[] = "$localidade/" . $uf;

        if ($cep !== '') $parts[] = 'CEP: ' . $cep;
        return implode(', ', $parts);
    }

    private function montarEnderecoCnpj(string $rua, string $numero, string $complemento, string $bairro, string $localidade, string $uf, string $cep): string
    {
        // normalizar entradas
        $rua = $this->normalizeRua(trim($rua));
        $numero = trim($numero);
        $complemento = trim($complemento);
        $bairro = trim($bairro);
        $localidade = trim($localidade);
        $uf = mb_strtoupper(trim($uf), 'UTF-8');
        $cep = trim($cep);

        // deixar localidade em Title Case para apresentação
        if ($localidade !== '') {
            $localidade = mb_convert_case($localidade, MB_CASE_TITLE, 'UTF-8');
        }

        $parts = [];
        if ($rua !== '') $parts[] = $rua;
        if ($numero !== '') $parts[] = 'nº ' . $numero;
        if ($complemento !== '') $parts[] = $complemento;
        if ($bairro !== '') $parts[] = 'Bairro ' . $bairro;

        if ($localidade !== '' && $uf !== '') {
            $parts[] = $localidade . '/' . $uf;
        } elseif ($localidade !== '') {
            $parts[] = $localidade;
        } elseif ($uf !== '') {
            $parts[] = $uf;
        }

        if ($cep !== '') $parts[] = 'CEP: ' . $cep;
        return implode(', ', $parts);
    }

    private function v(array $row, string $key): string
    {
        $val = isset($row[$key]) ? (string)$row[$key] : '';
        return trim($val);
    }

    private function sanitize(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function formatCpf(string $cpf): string
    {
        $digits = $this->onlyDigits($cpf);
        if (strlen($digits) !== 11) {
            return $cpf;
        }
        return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
    }

    private function formatCnpj(string $cnpj): string
    {
        $digits = $this->onlyDigits($cnpj);
        if (strlen($digits) !== 14) {
            return $cnpj;
        }
        return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
    }

    private function formatCep(string $cep): string
    {
        $digits = $this->onlyDigits($cep);
        if (strlen($digits) !== 8) {
            return $cep;
        }
        return substr($digits, 0, 5) . '-' . substr($digits, 5, 3);
    }

    private function normalizeRua(string $rua): string
    {
        $rua = trim($rua);
        if ($rua === '') return $rua;

        $prefixes = [
            'rua', 'r.', 'r',
            'avenida', 'av.', 'av',
            'travessa', 'tv.', 'tv',
            'alameda', 'al.', 'al',
            'estrada', 'est.', 'est',
            'rodovia', 'rod.', 'rod',
            'praça', 'praca', 'pça', 'pca',
            'largo', 'via',
        ];

        $lower = mb_strtolower($rua, 'UTF-8');
        foreach ($prefixes as $p) {
            if (strpos($lower, $p . ' ') === 0 || $lower === $p) {
                return $rua;
            }
        }

        return 'Rua ' . $rua;
    }
}
