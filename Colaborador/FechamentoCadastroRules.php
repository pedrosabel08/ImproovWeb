<?php
/** Validação do cadastro, independente de função/setor e sem classificação inferida. */
final class FechamentoCadastroRules
{
    public static function normalizar(array $input, array $atual = [], bool $novo = false): array
    {
        $dados = array_replace($atual, array_intersect_key($input, array_flip(['participa_fechamento_mensal','tipo_remuneracao','valor_fixo'])));
        $participa = $dados['participa_fechamento_mensal'] ?? null;
        if ($participa === '') $participa = null;
        if ($participa !== null && !in_array($participa, [0,1,'0','1'], true)) throw new InvalidArgumentException('Defina a participação no fechamento como Sim ou Não.');
        $participa = $participa === null ? null : (int)$participa;
        if ($novo && $participa === null) throw new InvalidArgumentException('Informe se participa do fechamento mensal.');
        $tipo = $dados['tipo_remuneracao'] ?? null;
        if ($tipo === '') $tipo = null;
        if (($participa === 1 && $tipo === null) || ($tipo !== null && !in_array($tipo, ['FIXO','VARIAVEL','FIXO_VARIAVEL'], true))) throw new InvalidArgumentException('Defina uma forma de remuneração válida.');
        $fixo = $dados['valor_fixo'] ?? null;
        if (!is_scalar($fixo) && $fixo !== null) throw new InvalidArgumentException('Informe um valor fixo válido.');
        $fixo = trim((string)$fixo);
        if ($fixo === '') $fixo = null;
        else {
            if (!preg_match('/^\d{1,8}([.,]\d{1,2})?$/D', $fixo)) throw new InvalidArgumentException('Informe um valor fixo válido, sem separador de milhares.');
            $fixo = str_replace(',', '.', $fixo);
        }
        if ($participa === 1 && in_array($tipo, ['FIXO','FIXO_VARIAVEL'], true) && $fixo === null) throw new InvalidArgumentException('Informe o valor fixo mensal. Zero é válido.');
        return ['participa_fechamento_mensal'=>$participa,'tipo_remuneracao'=>$tipo,'valor_fixo'=>$fixo];
    }
}
