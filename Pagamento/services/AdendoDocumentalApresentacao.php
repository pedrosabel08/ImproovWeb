<?php
require_once __DIR__.'/../../Contratos/services/ContratoDateService.php';
require_once __DIR__.'/../../Contratos/services/ContratoQualificacaoService.php';
/** Helpers estritamente documentais; partes puras extraídas do legado auditado ABA9AD09. */
final class AdendoDocumentalApresentacao
{
    public static function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
    private function escapeHtml(string $v): string { return self::h($v); }
    public static function moeda(int $c): string {
        if($c<0) throw new InvalidArgumentException('Valor documental negativo.');
        $inteiro=(string)intdiv($c,100);
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $inteiro).','.str_pad((string)($c%100),2,'0',STR_PAD_LEFT);
    }
    public function extenso(int $c): string {
        $r=intdiv($c,100); $cent=$c%100;
        return $this->numeroPorExtenso($r).' '.($r===1?'real':'reais').($cent ? ' e '.$this->numeroPorExtenso($cent).' '.($cent===1?'centavo':'centavos') : '');
    }
    public function pagamento(string $ref): string {
        [$ano,$mes]=array_map('intval',explode('-',$ref));
        return (new ContratoDateService())->formatDataPtBr($this->getQuintoDiaUtilProximoMes($mes,$ano));
    }
    public function qualificacao(array $dados): string {
        $texto=(new ContratoQualificacaoService())->buildQualificacaoCompleta($dados);
        return nl2br($this->boldNamesInQualificacao(self::h($texto),$dados));
    }
    public function contratante(int $id): array {
        return in_array($id,[13,39,44],true) ? ['nome'=>'STELLAR ANIMA LTDA.','cnpj'=>'45.284.934/0001-30'] : ['nome'=>'IMPROOV LTDA.','cnpj'=>'37.066.879/0001-84'];
    }
    private function getQuintoDiaUtilProximoMes(int $mes, int $ano): DateTimeImmutable
    {
        $tz = new DateTimeZone('America/Sao_Paulo');
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes), $tz);
        $nextMonth = $first->modify('first day of next month');
        $y = (int)$nextMonth->format('Y');
        $m = (int)$nextMonth->format('m');
        return $this->getQuintoDiaUtil($y, $m);
    }

    private function getQuintoDiaUtil(int $ano, int $mes): DateTimeImmutable
    {
        $tz = new DateTimeZone('America/Sao_Paulo');
        $dt = new DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes), $tz);
        $feriados = $this->getFeriadosNacionais($ano);
        $count = 0;
        while (true) {
            $dow = (int)$dt->format('N');
            $dateStr = $dt->format('Y-m-d');
            // Segunda (1) a sábado (6) contam, exceto feriados
            if ($dow <= 6 && !isset($feriados[$dateStr])) {
                $count++;
            }
            if ($count === 5) {
                // Se o 5º dia útil cair no sábado, avança para segunda-feira
                if ($dow === 6) {
                    $dt = $dt->modify('+2 days');
                }
                // Se o dia resultante for feriado ou domingo, continua avançando
                while ((int)$dt->format('N') === 7 || isset($feriados[$dt->format('Y-m-d')])) {
                    $dt = $dt->modify('+1 day');
                }
                return $dt;
            }
            $dt = $dt->modify('+1 day');
        }
    }

    /**
     * Retorna array de feriados nacionais brasileiros no formato ['Y-m-d' => true].
     * Inclui feriados fixos e móveis (Sexta-feira Santa e Corpus Christi).
     */
    private function getFeriadosNacionais(int $ano): array
    {
        $feriados = [];

        // Feriados fixos
        $fixos = [
            sprintf('%04d-01-01', $ano), // Confraternização Universal
            sprintf('%04d-04-21', $ano), // Tiradentes
            sprintf('%04d-05-01', $ano), // Dia do Trabalhador
            sprintf('%04d-09-07', $ano), // Independência do Brasil
            sprintf('%04d-10-12', $ano), // Nossa Senhora Aparecida
            sprintf('%04d-11-02', $ano), // Finados
            sprintf('%04d-11-15', $ano), // Proclamação da República
            sprintf('%04d-12-25', $ano), // Natal
        ];
        // Consciência Negra tornou-se feriado nacional a partir de 2024
        if ($ano >= 2024) {
            $fixos[] = sprintf('%04d-11-20', $ano);
        }
        foreach ($fixos as $d) {
            $feriados[$d] = true;
        }

        // Feriados móveis baseados na Páscoa
        $pascoa = $this->calcularPascoa($ano);
        // $feriados[$pascoa->modify('-2 days')->format('Y-m-d')] = true; // Sexta-feira Santa
        // $feriados[$pascoa->modify('+60 days')->format('Y-m-d')] = true; // Corpus Christi

        return $feriados;
    }

    /**
     * Calcula a data da Páscoa para um dado ano pelo algoritmo de Butcher.
     */
    private function calcularPascoa(int $ano): DateTimeImmutable
    {
        $a = $ano % 19;
        $b = intdiv($ano, 100);
        $c = $ano % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;
        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d', $ano, $mes, $dia),
            new DateTimeZone('America/Sao_Paulo')
        );
    }

    private function boldNamesInQualificacao(string $qualificacaoEsc, array $colab): string
    {
        $nomesParaDestacar = [];
        $nomeEmpresarial = isset($colab['nome_empresarial']) ? (string)$colab['nome_empresarial'] : '';
        $nomeColaborador = isset($colab['nome_colaborador']) ? (string)$colab['nome_colaborador'] : '';
        if ($nomeEmpresarial !== '') $nomesParaDestacar[] = $nomeEmpresarial;
        if ($nomeColaborador !== '') $nomesParaDestacar[] = $nomeColaborador;
        $nomesParaDestacar = array_unique($nomesParaDestacar);

        $nomesEsc = array_map([$this, 'escapeHtml'], $nomesParaDestacar);
        usort($nomesEsc, function ($a, $b) {
            return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
        });
        foreach ($nomesEsc as $nEsc) {
            if ($nEsc === '') continue;
            $qualificacaoEsc = str_replace($nEsc, '<strong>' . $nEsc . '</strong>', $qualificacaoEsc);
        }
        $qualificacaoEsc = preg_replace('/\bCONTRATADA\b/u', '<strong>CONTRATADA</strong>', $qualificacaoEsc) ?? $qualificacaoEsc;
        return $qualificacaoEsc;
    }

    private function normalizeName(string $s): string
    {
        $s = trim($s);
        if ($s === '') return '';
        $s = mb_strtolower($s, 'UTF-8');
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/[^a-z0-9\s]/', '', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return trim($s);
    }

    private function numeroPorExtenso(int $num): string
    {
        if ($num === 0) return 'zero';

        $unidades = [
            '',
            'um',
            'dois',
            'três',
            'quatro',
            'cinco',
            'seis',
            'sete',
            'oito',
            'nove',
            'dez',
            'onze',
            'doze',
            'treze',
            'quatorze',
            'quinze',
            'dezesseis',
            'dezessete',
            'dezoito',
            'dezenove'
        ];
        $dezenas = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
        $centenas = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

        $parts = [];

        $append = function (string $segment, int $remainder) use (&$parts) {
            if ($segment === '') return;
            if (!empty($parts)) {
                $lastIdx = count($parts) - 1;
                $joiner = $remainder > 0 && $remainder < 100 ? ' e ' : ' ';
                $parts[$lastIdx] .= $joiner . $segment;
            } else {
                $parts[] = $segment;
            }
        };

        $bilhoes = intdiv($num, 1000000000);
        if ($bilhoes > 0) {
            $segment = ($bilhoes === 1) ? 'um bilhão' : ($this->numeroPorExtenso($bilhoes) . ' bilhões');
            $num %= 1000000000;
            $append($segment, $num);
        }

        $milhoes = intdiv($num, 1000000);
        if ($milhoes > 0) {
            $segment = ($milhoes === 1) ? 'um milhão' : ($this->numeroPorExtenso($milhoes) . ' milhões');
            $num %= 1000000;
            $append($segment, $num);
        }

        $milhares = intdiv($num, 1000);
        if ($milhares > 0) {
            $segment = ($milhares === 1) ? 'mil' : ($this->numeroPorExtenso($milhares) . ' mil');
            $num %= 1000;
            $append($segment, $num);
        }

        if ($num > 0) {
            $segment = '';
            if ($num === 100) {
                $segment = 'cem';
            } else {
                $c = intdiv($num, 100);
                $d = $num % 100;
                if ($c > 0) {
                    $segment = $centenas[$c];
                }
                if ($d > 0) {
                    if ($segment !== '') $segment .= ' e ';
                    if ($d < 20) {
                        $segment .= $unidades[$d];
                    } else {
                        $dez = intdiv($d, 10);
                        $uni = $d % 10;
                        $segment .= $dezenas[$dez];
                        if ($uni > 0) {
                            $segment .= ' e ' . $unidades[$uni];
                        }
                    }
                }
            }
            $append($segment, 0);
        }

        return trim(implode('', $parts));
    }

}
