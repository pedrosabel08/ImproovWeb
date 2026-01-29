<?php

class ContratoDateService
{
    public function buildCompetencia(?DateTimeInterface $dt = null): string
    {
        $dt = $dt ?: new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
        return $dt->format('Y-m');
    }

    public function getInicioFimPrazo(?DateTimeInterface $dt = null): array
    {
        $dt = $dt ?: new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
        $tz = new DateTimeZone('America/Sao_Paulo');
        $inicio = new DateTimeImmutable($dt->format('Y-m-d'), $tz);
        $fim = $inicio->modify('last day of this month');
        $prazoDias = (int)$fim->diff($inicio)->format('%a') + 1;

        return [
            'inicio' => $inicio,
            'fim' => $fim,
            'prazo_dias' => $prazoDias,
        ];
    }

    public function formatDataPtBr(DateTimeInterface $dt): string
    {
        $meses = [
            1 => 'janeiro',
            2 => 'fevereiro',
            3 => 'março',
            4 => 'abril',
            5 => 'maio',
            6 => 'junho',
            7 => 'julho',
            8 => 'agosto',
            9 => 'setembro',
            10 => 'outubro',
            11 => 'novembro',
            12 => 'dezembro'
        ];
        $dia = (int)$dt->format('d');
        $mes = (int)$dt->format('m');
        $ano = $dt->format('Y');
        $mesNome = $meses[$mes] ?? $dt->format('m');
        return $dia . ' de ' . $mesNome . ' de ' . $ano;
    }

    public function getCompetenciaInfo(string $competencia): array
    {
        $ano = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y');
        $mes = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('m');

        if (preg_match('/^(\d{4})-(\d{2})$/', $competencia, $m)) {
            $ano = $m[1];
            $mes = $m[2];
        }

        $mesNum = (int)$mes;
        $meses = [
            1 => 'JANEIRO',
            2 => 'FEVEREIRO',
            3 => 'MARÇO',
            4 => 'ABRIL',
            5 => 'MAIO',
            6 => 'JUNHO',
            7 => 'JULHO',
            8 => 'AGOSTO',
            9 => 'SETEMBRO',
            10 => 'OUTUBRO',
            11 => 'NOVEMBRO',
            12 => 'DEZEMBRO'
        ];
        $mesNome = $meses[$mesNum] ?? $mes;
        return [
            'ano' => $ano,
            'mes' => $mes,
            'mes_nome' => $mesNome,
            'label' => $mesNome . '/' . $ano,
        ];
    }
}
