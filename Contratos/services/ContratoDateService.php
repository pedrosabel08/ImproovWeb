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
        $inicio = new DateTimeImmutable($dt->format('Y-m-01'), new DateTimeZone('America/Sao_Paulo'));
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
}
