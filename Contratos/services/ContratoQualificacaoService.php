<?php

class ContratoQualificacaoService
{
    public function buildQualificacaoCompleta(array $colab): string
    {
        $nomeEmpresarial = $this->v($colab, 'nome_empresarial');
        $cnpj = $this->v($colab, 'cnpj');
        $estadoCivil = $this->v($colab, 'estado_civil');
        $cpf = $this->v($colab, 'cpf');
        $nome = $this->v($colab, 'nome_colaborador');

        $enderecoColaborador = $this->montarEnderecoPessoa(
            $this->v($colab, 'rua'),
            $this->v($colab, 'numero'),
            $this->v($colab, 'bairro'),
            $this->v($colab, 'complemento'),
            $this->v($colab, 'cep')
        );

        $enderecoCnpj = $this->montarEnderecoCnpj(
            $this->v($colab, 'rua_cnpj'),
            $this->v($colab, 'numero_cnpj'),
            $this->v($colab, 'bairro_cnpj'),
            $this->v($colab, 'localidade_cnpj'),
            $this->v($colab, 'uf_cnpj'),
            $this->v($colab, 'cep_cnpj')
        );

        $texto = "De outro, {$nomeEmpresarial}, CNPJ: {$cnpj}, com endereço/sede na {$enderecoCnpj}; se seguir denominado simplesmente parte CONTRATADA; neste ato representado por {$nome}, brasileiro(a), {$estadoCivil}, inscrito(a) no CPF sob o nº {$cpf}, residente e domiciliado na {$enderecoColaborador}.";

        return $this->sanitize($texto);
    }

    private function montarEnderecoPessoa(string $rua, string $numero, string $bairro, string $complemento, string $cep): string
    {
        $parts = [];
        if ($rua !== '') $parts[] = $rua;
        if ($numero !== '') $parts[] = $numero;
        if ($bairro !== '') $parts[] = $bairro;
        if ($complemento !== '') $parts[] = $complemento;
        if ($cep !== '') $parts[] = 'CEP: ' . $cep;
        return implode(', ', $parts);
    }

    private function montarEnderecoCnpj(string $rua, string $numero, string $bairro, string $localidade, string $uf, string $cep): string
    {
        $parts = [];
        if ($rua !== '') $parts[] = $rua;
        if ($numero !== '') $parts[] = $numero;
        if ($bairro !== '') $parts[] = $bairro;
        if ($localidade !== '') $parts[] = $localidade;
        if ($uf !== '') $parts[] = $uf;
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
}
