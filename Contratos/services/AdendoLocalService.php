<?php

class AdendoLocalService
{
    private mysqli $conn;
    private ContratoDataService $dataService;
    private ContratoDateService $dateService;
    private ContratoQualificacaoService $qualificacaoService;
    private ContratoPdfService $pdfService;

    public function __construct(
        mysqli $conn,
        ContratoDataService $dataService,
        ContratoDateService $dateService,
        ContratoQualificacaoService $qualificacaoService,
        ContratoPdfService $pdfService
    ) {
        $this->conn = $conn;
        $this->dataService = $dataService;
        $this->dateService = $dateService;
        $this->qualificacaoService = $qualificacaoService;
        $this->pdfService = $pdfService;
    }

    public function gerarAdendo(int $colaboradorId, int $mes, int $ano, float $valorFixo, array $funcoesFiltro = [], array $extrasInput = [], array $itensInput = []): array
    {
        $colab = $this->dataService->getColaboradorContratoData($colaboradorId);
        $competencia = sprintf('%04d-%02d', $ano, $mes);
        $competenciaInfo = $this->dateService->getCompetenciaInfo($competencia);

        $existente = $this->getAdendoByCompetencia($colaboradorId, $competencia);
        if ($existente && in_array($existente['status'], ['assinado', 'recusado', 'expirado'], true)) {
            throw new RuntimeException('Adendo já finalizado para esta competência.');
        }

        $nomeArquivoBase = 'ADENDO_CONTRATUAL_' . ($colab['nome_colaborador'] ?? 'COLABORADOR') . '_' . $competenciaInfo['mes_nome'] . '_' . $competenciaInfo['ano'];
        $nomeArquivo = $this->sanitizeFileName($nomeArquivoBase) . '.pdf';

        $qualificacao = $this->qualificacaoService->buildQualificacaoCompleta($colab);
        $qualificacaoEsc = $this->escapeHtml($qualificacao);
        $qualificacaoEsc = $this->boldNamesInQualificacao($qualificacaoEsc, $colab);

        [$contratanteNome, $contratanteCnpj] = $this->getContratanteInfo($colaboradorId, $colab);

        if (!empty($itensInput)) {
            // Itens já filtrados pelo frontend — não reaplicar filtro de funções
            $itens = $this->normalizeItensInput($itensInput);
        } else {
            $itens = $this->getAdendoItens($colaboradorId, $mes, $ano);
            if (!empty($funcoesFiltro)) {
                $itens = $this->filterItensByFuncoes($itens, $funcoesFiltro);
            }
        }
        $showValor = true;

        $rows = $this->buildRows($itens, $showValor);
        $extras = $this->normalizeExtras($extrasInput);

        if ($colaboradorId === 1) {
            // Colaborador 1: variável (funcao_imagem) + fixo Acompanhamento R$4000
            $extras = [['categoria' => 'Acompanhamento', 'valor' => 4000.00]];
            if (!empty($rows)) {
                $tabelaHtml = $this->buildTabelaHtml($rows, $showValor);
                $tabelaHtml .= '<br>' . $this->buildExtrasTabelaHtml($extras);
                $totalValor = $this->sumRows($rows) + $this->sumExtras($extras);
            } else {
                $tabelaHtml = $this->buildExtrasTabelaHtml($extras);
                $totalValor = $this->sumExtras($extras);
            }
        } else {
            $tabelaHtml = $this->buildTabelaHtml($rows, $showValor);
            $totalValor = $this->sumRows($rows);
            if (!empty($extras)) {
                $tabelaHtml .= '<br>' . $this->buildExtrasTabelaHtml($extras);
                $totalValor += $this->sumExtras($extras);
            }
        }
        $totalComFixo = $totalValor + $valorFixo;
        $totalFormatado = $this->formatCurrency($totalComFixo);
        $valorExtenso = $this->valorPorExtenso($totalComFixo);

        $dataPagamento = $this->getQuintoDiaUtilProximoMes($mes, $ano);
        $dataPagamentoExtenso = $this->dateService->formatDataPtBr($dataPagamento);

        $dataAtual = $this->dateService->formatDataPtBr(new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')));

        $contratadoNome = (string)($colab['nome_colaborador']);
        $contratadoCnpj = $this->escapeHtml((string)($colab['cnpj'] ?? ''));
        $contratadoCpf = $this->escapeHtml((string)($colab['cpf'] ?? ''));
        $contratadoNomeEmpresarial = $this->escapeHtml((string)($colab['nome_empresarial'] ?? ''));

        $placeholders = [
            'titulo_adendo' => 'ADENDO CONTRATUAL - ' . $competenciaInfo['mes_nome'] . ' ' . $competenciaInfo['ano'],
            'contratante_nome' => $this->escapeHtml($contratanteNome),
            'contratante_cnpj' => $this->escapeHtml($contratanteCnpj),
            'dados_colaborador' => nl2br($qualificacaoEsc),
            'competencia_mes_nome' => $competenciaInfo['mes_nome'],
            'competencia_ano' => $competenciaInfo['ano'],
            'tabela_servicos' => $tabelaHtml,
            'valor_total' => $totalFormatado,
            'valor_total_extenso' => $this->escapeHtml($valorExtenso),
            'data_pagamento' => $this->escapeHtml($dataPagamentoExtenso),
            'data_atual' => $this->escapeHtml($dataAtual),
            'contratado_nome' => $this->escapeHtml($contratadoNome),
            'contratado_cnpj' => $contratadoCnpj,
            'contratado_cpf' => $contratadoCpf,
            'contratado_nome_empresarial' => $contratadoNomeEmpresarial,
        ];

        $pdf = $this->pdfService->gerarPdf($nomeArquivo, $placeholders);

        $payload = [
            'ARQUIVO_NOME' => $nomeArquivo,
            'COMPETENCIA' => $competencia,
            'VALOR_FIXO' => $valorFixo,
            'VALOR_TOTAL' => $totalComFixo,
        ];
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $now = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');

        $this->salvarAdendo(
            $existente ? (int)$existente['id'] : null,
            $colaboradorId,
            $competencia,
            'gerado',
            $now,
            $payloadJson,
            $nomeArquivo,
            $pdf['file_path']
        );

        return [
            'success' => true,
            'competencia' => $competencia,
            'arquivo_nome' => $nomeArquivo,
            'arquivo_path' => $pdf['file_path'],
        ];
    }

    private function getContratanteInfo(int $colaboradorId, array $colab): array
    {
        $contratanteNome = 'IMPROOV LTDA.';
        $contratanteCnpj = '37.066.879/0001-84';
        $nome = $this->normalizeName((string)($colab['nome_colaborador'] ?? ''));
        if (in_array($colaboradorId, [13, 39, 44], true)) {
            $contratanteNome = 'STELLAR ANIMA LTDA.';
            $contratanteCnpj = '45.284.934/0001-30';
        }
        return [$contratanteNome, $contratanteCnpj];
    }

    private function getAdendoByCompetencia(int $colaboradorId, string $competencia): ?array
    {
        $sql = "SELECT * FROM adendos WHERE colaborador_id = ? AND competencia = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Falha ao preparar adendo: ' . $this->conn->error);
        }
        $stmt->bind_param('is', $colaboradorId, $competencia);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    private function salvarAdendo(
        ?int $id,
        int $colaboradorId,
        string $competencia,
        string $status,
        string $dataEnvio,
        string $payload,
        string $arquivoNome,
        string $arquivoPath
    ): void {
        if ($id) {
            $sql = "UPDATE adendos SET status = ?, data_envio = ?, payload_enviado = ?, arquivo_nome = ?, arquivo_path = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Falha ao atualizar adendo: ' . $this->conn->error);
            }
            $stmt->bind_param('sssssi', $status, $dataEnvio, $payload, $arquivoNome, $arquivoPath, $id);
        } else {
            $sql = "INSERT INTO adendos (colaborador_id, competencia, status, data_envio, payload_enviado, arquivo_nome, arquivo_path) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Falha ao inserir adendo: ' . $this->conn->error);
            }
            $stmt->bind_param('issssss', $colaboradorId, $competencia, $status, $dataEnvio, $payload, $arquivoNome, $arquivoPath);
        }
        $stmt->execute();
        $stmt->close();
    }

    private function isColaboradorSemValor(string $nome): bool
    {
        $norm = $this->normalizeName($nome);
        $semValor = [
            'anderson roberto de souza',
            'pedro henrique munhoz da silva',
        ];
        return in_array($norm, $semValor, true);
    }

    private function buildRows(array $itens, bool $showValor): array
    {
        $rows = [];
        $rowNumber = 1;
        foreach ($itens as $item) {
            $dataPagamento = $this->normalizeDateValue($item['data_pagamento'] ?? null);
            $pagoParcial = $this->isPagoParcial($item);
            $pagoCompleta = (int)($item['pago_completa_count'] ?? 0);
            // Qualquer "Finalização Parcial" não entra no adendo (mesmo lógica do getColaborador.php)
            if (stripos((string)($item['nome_funcao'] ?? ''), 'parcial') !== false) continue;
            // Incluir apenas linhas não pagas; para comissão do gestor, pago_completa_count=1 indica pago
            $incluirLinha = ($dataPagamento === null || $dataPagamento === '0000-00-00' || $pagoParcial)
                && $pagoCompleta === 0;
            if (!$incluirLinha) continue;

            $imagem = (string)($item['imagem_nome'] ?? '');
            $funcao = (string)($item['nome_funcao'] ?? '');
            $colabRef = trim((string)($item['colaborador_ref'] ?? ''));
            if ($colabRef !== '' && preg_match('/finaliza[cç][aã]o\s+completa/i', $funcao) && !preg_match('/[\-–—]/u', $funcao)) {
                $funcao = 'Finalização Completa - ' . $colabRef;
            }
            $valor = (float)($item['valor'] ?? 0);
            $fromPayload = !empty($item['from_payload']);

            if ($pagoParcial) {
                // Para funções com colaborador (ex.: "Finalização Completa - Vitor"), manter o rótulo original
                // limpar rótulos como "(Pago parcial)" e normalizar
                $funcaoClean = preg_replace('/\s*\((?=.*pago\s*parcial)[^)]*\)\s*/i', '', $funcao);
                $funcaoClean = trim($funcaoClean);
                // Se já vier com traço seguido de nome, manter assim. Use original raw value as fallback.
                $hasDashClean = preg_match('/[\-–—]\s*\S+/u', $funcaoClean) === 1;
                $hasDashOriginal = preg_match('/[\-–—]\s*\S+/u', (string)($item['nome_funcao'] ?? '')) === 1;
                if ($hasDashClean || $hasDashOriginal) {
                    $funcao = $funcaoClean;
                } else {
                    // Caso contrário usamos o rótulo padrão para pagamento final
                    $funcao = 'Finalização completa com pagamento final';
                }
            }

            $funcao = $this->applyAnimacaoRename($funcao, $valor);

            $row = [
                'no' => $rowNumber,
                'imagem' => $imagem,
                'funcao' => $funcao,
                'valor' => ($valor === null || $valor === '' || (float)$valor === 0.0) ? '-' : $this->formatCurrency($valor),
                'valor_num' => $valor,
            ];

            $rows[] = $row;
            $rowNumber++;
        }

        return $rows;
    }

    private function isPagoParcial(array $item): bool
    {
        $parcial = isset($item['pago_parcial_count']) ? (int)$item['pago_parcial_count'] : 0;
        $completa = isset($item['pago_completa_count']) ? (int)$item['pago_completa_count'] : 0;
        return $parcial > 0 && $completa === 0;
    }

    private function normalizeDateValue($value): ?string
    {
        if ($value === null) return null;
        $v = trim((string)$value);
        if ($v === '' || $v === '-' || $v === '—') return null;
        $lower = mb_strtolower($v, 'UTF-8');
        if ($lower === 'null' || $lower === 'undefined') return null;
        return $v;
    }

    private function applyAnimacaoRename(string $funcao, float $valor): string
    {
        $norm = $this->normalizeName($funcao);
        $isAnimacao = strpos($norm, 'animacao') !== false || strpos($norm, 'anima') !== false;
        if (!$isAnimacao) return $funcao;

        if (abs($valor - 100) < 0.01) {
            return 'Pós-Produção';
        }
        if (abs($valor - 175) < 0.01) {
            return 'Pós-Produção';
        }
        return $funcao;
    }

    private function buildTabelaHtml(array $rows, bool $showValor): string
    {
        $cols = $showValor ? 4 : 3;
        if (!$rows) {
            return '<table class="tabela"><thead><tr><th>No.</th><th>Nome da Imagem</th><th>Função</th>'
                . ($showValor ? '<th>Valor (R$)</th>' : '')
                . '</tr></thead><tbody><tr><td colspan="' . $cols . '">Sem itens para este período.</td></tr></tbody></table>';
        }

        $html = '<table class="tabela">';
        $html .= '<thead><tr><th>No.</th><th>Nome da Imagem</th><th>Função</th>' . ($showValor ? '<th>Valor (R$)</th>' : '') . '</tr></thead>';
        $html .= '<tbody>';
        foreach ($rows as $r) {
            $html .= '<tr>';
            $html .= '<td class="cell-center">' . $this->escapeHtml((string)$r['no']) . '</td>';
            $html .= '<td>' . $this->escapeHtml((string)$r['imagem']) . '</td>';
            $html .= '<td>' . $this->escapeHtml((string)$r['funcao']) . '</td>';
            if ($showValor) {
                $html .= '<td class="cell-right">' . $this->escapeHtml((string)$r['valor']) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    private function sumRows(array $rows): float
    {
        $total = 0.0;
        foreach ($rows as $r) {
            $total += isset($r['valor_num']) ? (float)$r['valor_num'] : 0.0;
        }
        return $total;
    }

    private function sumExtras(array $extras): float
    {
        $total = 0.0;
        foreach ($extras as $extra) {
            $total += isset($extra['valor']) ? (float)$extra['valor'] : 0.0;
        }
        return $total;
    }

    private function buildExtrasTabelaHtml(array $extras): string
    {
        if (!$extras) {
            return '<table class="tabela"><thead><tr><th>Categoria</th><th>Valor (R$)</th></tr></thead><tbody><tr><td colspan="2">Sem extras.</td></tr></tbody></table>';
        }

        $html = '<table class="tabela">';
        $html .= '<thead><tr><th>Categoria</th><th>Valor (R$)</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($extras as $extra) {
            $categoria = $this->escapeHtml((string)($extra['categoria'] ?? ''));
            $valor = $this->escapeHtml($this->formatCurrency((float)($extra['valor'] ?? 0)));
            $html .= '<tr>';
            $html .= '<td>' . $categoria . '</td>';
            $html .= '<td class="cell-right">' . $valor . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    private function normalizeExtras(array $extrasInput): array
    {
        $out = [];
        foreach ($extrasInput as $extra) {
            if (!is_array($extra)) continue;
            $categoria = trim((string)($extra['categoria'] ?? ''));
            $valorRaw = (string)($extra['valor'] ?? '0');
            $valor = (float)str_replace(',', '.', $valorRaw);
            if ($categoria === '' || !is_numeric($valorRaw)) continue;
            $out[] = [
                'categoria' => $categoria,
                'valor' => $valor,
            ];
        }
        return $out;
    }

    private function normalizeItensInput(array $itensInput): array
    {
        $out = [];
        foreach ($itensInput as $item) {
            if (!is_array($item)) continue;
            $imagem = trim((string)($item['imagem_nome'] ?? ''));
            $funcao = trim((string)($item['nome_funcao'] ?? ''));
            $valorRaw = (string)($item['valor'] ?? '0');
            $valor = (float)str_replace(',', '.', $valorRaw);
            $dataPagamento = $item['data_pagamento'] ?? null;
            $pagoParcial = isset($item['pago_parcial_count']) ? (int)$item['pago_parcial_count'] : 0;
            $pagoCompleta = isset($item['pago_completa_count']) ? (int)$item['pago_completa_count'] : 0;
            $out[] = [
                'imagem_nome' => $imagem,
                'nome_funcao' => $funcao,
                'valor' => $valor,
                'data_pagamento' => $dataPagamento,
                'pago_parcial_count' => $pagoParcial,
                'pago_completa_count' => $pagoCompleta,
                'colaborador_ref' => null,
                'from_payload' => true,
            ];
        }
        return $out;
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

    private function getAdendoItens(int $colaboradorId, int $mesNumero, int $ano): array
    {
        $sql = '';
        $fimMesDia = cal_days_in_month(CAL_GREGORIAN, $mesNumero, $ano);
        $fimMesDataTime = sprintf('%04d-%02d-%02d 23:59:59', $ano, $mesNumero, $fimMesDia);
        if ($colaboradorId === 1) {
            $sql = "SELECT 
        fi.colaborador_id,
        'funcao_imagem' AS origem,
        fi.idfuncao_imagem AS identificador,
        fi.imagem_id,
        ico.imagem_nome,
        fi.funcao_id,
        CASE 
            WHEN fi.funcao_id = 4 THEN 
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM funcao_imagem fi_sub
                            JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                            WHERE fi_sub.imagem_id = fi.imagem_id 
                            AND f_sub.nome_funcao = 'Pré-Finalização'
                        ) OR hi_snap.status_id = 1
                        THEN 'Finalização Parcial'
                        ELSE 'Finalização Completa'
                    END 
            ELSE f.nome_funcao 
        END AS nome_funcao,
        fi.status,
        fi.prazo,
        fi.pagamento,
                fi.valor,
                fi.data_pagamento,
                (
                        SELECT c_ref.nome_colaborador
                        FROM funcao_imagem fi_ref
                        JOIN colaborador c_ref ON c_ref.idcolaborador = fi_ref.colaborador_id
                        WHERE fi_ref.imagem_id = fi.imagem_id
                            AND fi_ref.funcao_id = 4
                            AND fi_ref.colaborador_id IN (23, 40)
                        LIMIT 1
                ) AS colaborador_ref,
        CASE WHEN fi.funcao_id = 4 THEN (
            SELECT COUNT(1)
            FROM pagamento_itens pi
            JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
            WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Finalização Parcial'
        ) ELSE 0 END AS pago_parcial_count,
        CASE WHEN fi.funcao_id = 4 THEN (
            SELECT COUNT(1)
            FROM pagamento_itens pi
            JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
            WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Pago Completa'
        ) ELSE 0 END AS pago_completa_count
    FROM 
        funcao_imagem fi
    JOIN 
        imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    JOIN 
        obra o ON ico.obra_id = o.idobra
    JOIN 
        funcao f ON fi.funcao_id = f.idfuncao
    LEFT JOIN (
        SELECT h1.imagem_id, h1.status_id
        FROM historico_imagens h1
        INNER JOIN (
            SELECT imagem_id, MAX(data_movimento) AS max_data
            FROM historico_imagens
            WHERE data_movimento <= ?
            GROUP BY imagem_id
        ) hm ON hm.imagem_id = h1.imagem_id AND hm.max_data = h1.data_movimento
    ) hi_snap ON hi_snap.imagem_id = ico.idimagens_cliente_obra
    WHERE 
        fi.colaborador_id = ?
        AND (
            (
                (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
                AND (YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?)
            )
            OR EXISTS (
                SELECT 1 FROM log_alteracoes la
                WHERE la.funcao_imagem_id = fi.idfuncao_imagem
                  AND MONTH(la.data) = ? AND YEAR(la.data) = ?
                  AND LOWER(TRIM(la.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
            )
        )
    UNION ALL
    SELECT 
        ac.colaborador_id,
        'acompanhamento' AS origem,
        ac.idacompanhamento AS identificador,
        ico.idimagens_cliente_obra AS imagem_id,
        ico.imagem_nome AS imagem_nome,
        NULL AS funcao_id,
        'Acompanhamento' AS nome_funcao,
        NULL AS status,
        NULL AS prazo,
        ac.pagamento,
        ac.valor,
        ac.data_pagamento,
        NULL AS colaborador_ref,
        NULL AS pago_parcial_count,
        NULL AS pago_completa_count
    FROM 
        acompanhamento ac
    JOIN 
        obra o ON o.idobra = ac.obra_id
    JOIN 
        imagens_cliente_obra ico ON ico.idimagens_cliente_obra = ac.imagem_id
    WHERE 
        ac.colaborador_id = ? AND YEAR(ac.data) = ? AND MONTH(ac.data) = ?";
        } elseif ($colaboradorId === 8) {
            $sql = "SELECT 
        fi.colaborador_id,
        'funcao_imagem' AS origem,
        fi.idfuncao_imagem AS identificador,
        fi.imagem_id,
        ico.imagem_nome,
        fi.funcao_id,
        CASE 
            WHEN fi.funcao_id = 4 THEN 
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM funcao_imagem fi_sub
                            JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                            WHERE fi_sub.imagem_id = fi.imagem_id 
                            AND f_sub.nome_funcao = 'Pré-Finalização'
                        ) OR hi_snap.status_id = 1
                        THEN 'Finalização Parcial'
                        ELSE 'Finalização Completa'
                    END 
            ELSE f.nome_funcao 
        END AS nome_funcao,
        fi.status,
        fi.prazo,
        fi.pagamento,
        fi.valor,
        fi.data_pagamento,
        NULL AS colaborador_ref,
        CASE WHEN fi.funcao_id = 4 THEN (
            SELECT COUNT(1)
            FROM pagamento_itens pi
            JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
            WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Finalização Parcial'
        ) ELSE 0 END AS pago_parcial_count,
        CASE WHEN fi.funcao_id = 4 THEN (
            SELECT COUNT(1)
            FROM pagamento_itens pi
            JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
            WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Pago Completa'
        ) ELSE 0 END AS pago_completa_count
    FROM 
        funcao_imagem fi
    JOIN 
        imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    JOIN 
        obra o ON ico.obra_id = o.idobra
    JOIN 
        funcao f ON fi.funcao_id = f.idfuncao
    LEFT JOIN (
        SELECT h1.imagem_id, h1.status_id
        FROM historico_imagens h1
        INNER JOIN (
            SELECT imagem_id, MAX(data_movimento) AS max_data
            FROM historico_imagens
            WHERE data_movimento <= ?
            GROUP BY imagem_id
        ) hm ON hm.imagem_id = h1.imagem_id AND hm.max_data = h1.data_movimento
    ) hi_snap ON hi_snap.imagem_id = ico.idimagens_cliente_obra
    WHERE 
        fi.colaborador_id = ?
        AND (
            (
                (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
                AND (YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?)
            )
            OR EXISTS (
                SELECT 1 FROM log_alteracoes la
                WHERE la.funcao_imagem_id = fi.idfuncao_imagem
                  AND MONTH(la.data) = ? AND YEAR(la.data) = ?
                  AND LOWER(TRIM(la.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
            )
        )
    UNION ALL
    SELECT 
        fi.colaborador_id,
        'funcao_imagem' AS origem,
        fi.idfuncao_imagem AS identificador,
        fi.imagem_id,
        ico.imagem_nome,
        fi.funcao_id,
        CASE 
            WHEN fi.funcao_id = 4 THEN 
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM funcao_imagem fi_sub
                            JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                            WHERE fi_sub.imagem_id = fi.imagem_id 
                            AND f_sub.nome_funcao = 'Pré-Finalização'
                        ) OR hi_snap.status_id = 1
                        THEN CONCAT('Finalização Parcial - ', c.nome_colaborador)
                        ELSE CONCAT('Finalização Completa - ', c.nome_colaborador)
                    END 
            ELSE f.nome_funcao 
        END AS nome_funcao,
        fi.status,
        fi.prazo,
        CASE WHEN EXISTS (
            SELECT 1 FROM pagamento_itens pi
            WHERE pi.origem = 'funcao_imagem' AND pi.origem_id = fi.idfuncao_imagem
              AND pi.observacao = 'Comissão Gestor'
        ) THEN 1 ELSE 0 END AS pagamento,
        fi.valor,
        NULL AS data_pagamento,
        c.nome_colaborador AS colaborador_ref,
        0 AS pago_parcial_count,
        CASE WHEN EXISTS (
            SELECT 1 FROM pagamento_itens pi
            WHERE pi.origem = 'funcao_imagem' AND pi.origem_id = fi.idfuncao_imagem
              AND pi.observacao = 'Comissão Gestor'
        ) THEN 1 ELSE 0 END AS pago_completa_count
    FROM 
        funcao_imagem fi
    JOIN 
        imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    JOIN 
        obra o ON ico.obra_id = o.idobra
    JOIN 
        funcao f ON fi.funcao_id = f.idfuncao
    JOIN 
        colaborador c ON c.idcolaborador = fi.colaborador_id
    LEFT JOIN (
        SELECT h1.imagem_id, h1.status_id
        FROM historico_imagens h1
        INNER JOIN (
            SELECT imagem_id, MAX(data_movimento) AS max_data
            FROM historico_imagens
            WHERE data_movimento <= ?
            GROUP BY imagem_id
        ) hm ON hm.imagem_id = h1.imagem_id AND hm.max_data = h1.data_movimento
    ) hi_snap ON hi_snap.imagem_id = ico.idimagens_cliente_obra
    WHERE 
        fi.colaborador_id IN (23, 40)
        AND fi.funcao_id = 4
        AND NOT (
            EXISTS (
                SELECT 1 FROM funcao_imagem fi_sub
                JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                WHERE fi_sub.imagem_id = fi.imagem_id
                  AND f_sub.nome_funcao = 'Pré-Finalização'
            )
            OR hi_snap.status_id = 1
        )
        AND (
            (
                (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
                AND (YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?)
            )
            OR EXISTS (
                SELECT 1 FROM log_alteracoes la
                WHERE la.funcao_imagem_id = fi.idfuncao_imagem
                  AND MONTH(la.data) = ? AND YEAR(la.data) = ?
                  AND LOWER(TRIM(la.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
            )
        )";
        } elseif (in_array($colaboradorId, [13, 20, 23, 37], true)) {
            $sql = "SELECT 
    fi.colaborador_id,
    'funcao_imagem' AS origem,
    fi.idfuncao_imagem AS identificador,
    fi.imagem_id,
    ico.imagem_nome,
    fi.funcao_id,
    CASE 
        WHEN fi.funcao_id = 4 THEN 
                CASE 
                    WHEN EXISTS (
                        SELECT 1 
                        FROM funcao_imagem fi_sub
                        JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                        WHERE fi_sub.imagem_id = fi.imagem_id 
                        AND f_sub.nome_funcao = 'Pré-Finalização'
                    ) OR hi_snap.status_id = 1
                    THEN 'Finalização Parcial'
                    ELSE 'Finalização Completa'
                END 
        ELSE f.nome_funcao 
    END AS nome_funcao,
    fi.status,
    fi.prazo,
    fi.pagamento,
    fi.valor,
    fi.data_pagamento,
    CASE WHEN fi.funcao_id = 4 THEN (
        SELECT COUNT(1)
        FROM pagamento_itens pi
        JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
        WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Finalização Parcial'
    ) ELSE 0 END AS pago_parcial_count,
    CASE WHEN fi.funcao_id = 4 THEN (
        SELECT COUNT(1)
        FROM pagamento_itens pi
        JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
        WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Pago Completa'
    ) ELSE 0 END AS pago_completa_count,
    o.idobra AS obra_id   
FROM 
    funcao_imagem fi
JOIN 
    imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
JOIN 
    obra o ON ico.obra_id = o.idobra
JOIN 
    funcao f ON fi.funcao_id = f.idfuncao
LEFT JOIN (
    SELECT h1.imagem_id, h1.status_id
    FROM historico_imagens h1
    INNER JOIN (
        SELECT imagem_id, MAX(data_movimento) AS max_data
        FROM historico_imagens
        WHERE data_movimento <= ?
        GROUP BY imagem_id
    ) hm ON hm.imagem_id = h1.imagem_id AND hm.max_data = h1.data_movimento
) hi_snap ON hi_snap.imagem_id = ico.idimagens_cliente_obra
WHERE 
    fi.colaborador_id = ?
    AND (
        (
            (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
            AND (YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?)
        )
        OR EXISTS (
            SELECT 1 FROM log_alteracoes la
            WHERE la.funcao_imagem_id = fi.idfuncao_imagem
              AND MONTH(la.data) = ? AND YEAR(la.data) = ?
              AND LOWER(TRIM(la.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
        )
    )
UNION ALL
SELECT 
    fa.colaborador_id,
    'funcao_animacao' AS origem,
    fa.id AS identificador,
    an.imagem_id,
    ico.imagem_nome,
    fa.funcao_id,
    f.nome_funcao AS nome_funcao,
    fa.status,
    an.data_anima as prazo,
    fa.pagamento,
    fa.valor,
    fa.data_pagamento,
    NULL AS pago_parcial_count,
    NULL AS pago_completa_count,
    an.obra_id  
FROM 
    funcao_animacao fa
JOIN
    animacao an ON fa.animacao_id = an.idanimacao
JOIN
    funcao f ON fa.funcao_id = f.idfuncao
LEFT JOIN 
    imagens_cliente_obra ico ON an.imagem_id = ico.idimagens_cliente_obra
WHERE 
    fa.colaborador_id = ? AND YEAR(an.data_anima) = ? AND MONTH(an.data_anima) = ?
ORDER BY obra_id, imagem_nome";
        } else {
            $sql = "SELECT 
        fi.colaborador_id,
        'funcao_imagem' AS origem,
        fi.idfuncao_imagem AS identificador,
        fi.imagem_id,
        ico.imagem_nome,
        fi.funcao_id,
        CASE 
            WHEN fi.funcao_id = 4 THEN 
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM funcao_imagem fi_sub
                            JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                            WHERE fi_sub.imagem_id = fi.imagem_id 
                            AND f_sub.nome_funcao = 'Pré-Finalização'
                        ) OR hi_snap.status_id = 1
                        THEN 'Finalização Parcial'
                        ELSE 'Finalização Completa'
                    END 
            ELSE f.nome_funcao 
        END AS nome_funcao,
        fi.status,
        fi.prazo,
        fi.pagamento,
        fi.valor,
        fi.data_pagamento,
        CASE WHEN fi.funcao_id = 4 THEN (
            SELECT COUNT(1)
            FROM pagamento_itens pi
            JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
            WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Finalização Parcial'
        ) ELSE 0 END AS pago_parcial_count,
        CASE WHEN fi.funcao_id = 4 THEN (
            SELECT COUNT(1)
            FROM pagamento_itens pi
            JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
            WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Pago Completa'
        ) ELSE 0 END AS pago_completa_count
    FROM 
        funcao_imagem fi
    JOIN 
        imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    JOIN 
        obra o ON ico.obra_id = o.idobra
    JOIN 
        funcao f ON fi.funcao_id = f.idfuncao
    LEFT JOIN (
        SELECT h1.imagem_id, h1.status_id
        FROM historico_imagens h1
        INNER JOIN (
            SELECT imagem_id, MAX(data_movimento) AS max_data
            FROM historico_imagens
            WHERE data_movimento <= ?
            GROUP BY imagem_id
        ) hm ON hm.imagem_id = h1.imagem_id AND hm.max_data = h1.data_movimento
    ) hi_snap ON hi_snap.imagem_id = ico.idimagens_cliente_obra
    WHERE 
        fi.colaborador_id = ?
        AND (
            (
                (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
                AND (YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?)
            )
            OR EXISTS (
                SELECT 1 FROM log_alteracoes la
                WHERE la.funcao_imagem_id = fi.idfuncao_imagem
                  AND MONTH(la.data) = ? AND YEAR(la.data) = ?
                  AND LOWER(TRIM(la.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
            )
        )
    ORDER BY ico.obra_id, ico.idimagens_cliente_obra, fi.funcao_id";
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Erro ao preparar consulta de adendo: ' . $this->conn->error);
        }

        if ($colaboradorId === 1) {
            // funcao_imagem: fimMesDataTime(hi_snap), colaboradorId, ano, mes, mes(log), ano(log)
            // acompanhamento: colaboradorId, ano, mes
            $stmt->bind_param('siiiiiiii', $fimMesDataTime, $colaboradorId, $ano, $mesNumero, $mesNumero, $ano, $colaboradorId, $ano, $mesNumero);
        } elseif ($colaboradorId === 8) {
            // 1st UNION (colabId): fimMesDataTime(hi_snap), colaboradorId, ano, mes, mes(log), ano(log) = 6 params
            // 2nd UNION (23,40):   fimMesDataTime(hi_snap), ano, mes, mes(log), ano(log) = 5 params
            $stmt->bind_param('siiiiisiiii', $fimMesDataTime, $colaboradorId, $ano, $mesNumero, $mesNumero, $ano, $fimMesDataTime, $ano, $mesNumero, $mesNumero, $ano);
        } elseif (in_array($colaboradorId, [13, 20, 23, 37], true)) {
            // funcao_imagem: fimMesDataTime(hi_snap), colaboradorId, ano, mes, mes(log), ano(log)
            // animacao: colaboradorId, ano, mes
            $stmt->bind_param('siiiiiiiii', $fimMesDataTime, $colaboradorId, $ano, $mesNumero, $mesNumero, $ano, $colaboradorId, $ano, $mesNumero);
        } else {
            // funcao_imagem: fimMesDataTime(hi_snap), colaboradorId, ano, mes, mes(log), ano(log)
            $stmt->bind_param('siiiiii', $fimMesDataTime, $colaboradorId, $ano, $mesNumero, $mesNumero, $ano);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $funcoes = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $funcoes[] = $row;
            }
        }
        $stmt->close();
        return $funcoes;
    }

    private function filterItensByFuncoes(array $itens, array $funcoesFiltro): array
    {
        $normFiltro = [];
        foreach ($funcoesFiltro as $f) {
            $nf = $this->normalizeFuncao((string)$f);
            if ($nf !== '') $normFiltro[$nf] = true;
        }
        if (!$normFiltro) return $itens;

        $out = [];
        foreach ($itens as $item) {
            $nome = (string)($item['nome_funcao'] ?? '');
            $norm = $this->normalizeFuncao($nome);
            if (isset($normFiltro[$norm])) {
                $out[] = $item;
            }
        }
        return $out;
    }

    private function normalizeFuncao(string $funcao): string
    {
        $funcao = preg_replace('/\s*-\s*.*/', '', $funcao);
        $funcao = preg_replace('/Pago\s*Parcial/i', '', $funcao);
        $funcao = preg_replace('/Pago\s*Completa/i', '', $funcao);
        $funcao = trim($funcao);
        $funcao = mb_strtolower($funcao, 'UTF-8');
        $funcao = iconv('UTF-8', 'ASCII//TRANSLIT', $funcao);
        $funcao = preg_replace('/[^a-z0-9\s]/', '', $funcao);
        $funcao = preg_replace('/\s+/', ' ', $funcao);
        return trim($funcao);
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

    private function formatCurrency(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private function sanitizeFileName(string $name): string
    {
        $name = trim($name);
        if ($name !== '' && function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $name);
            if (is_string($converted)) {
                $name = $converted;
            }
        }
        $name = preg_replace('/\s+/', '_', $name) ?? '';
        $name = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '-', $name);
        $name = preg_replace('/-+/', '-', $name) ?? '';
        $name = trim($name, "- ");
        return $name === '' ? 'ADENDO' : $name;
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function valorPorExtenso(float $valor): string
    {
        $valor = round($valor, 2);
        $inteiro = (int)floor($valor);
        $centavos = (int)round(($valor - $inteiro) * 100);
        if ($centavos === 100) {
            $inteiro += 1;
            $centavos = 0;
        }

        $texto = $this->numeroPorExtenso($inteiro) . ' ' . ($inteiro === 1 ? 'real' : 'reais');
        if ($centavos > 0) {
            $texto .= ' e ' . $this->numeroPorExtenso($centavos) . ' ' . ($centavos === 1 ? 'centavo' : 'centavos');
        }
        return $texto;
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
