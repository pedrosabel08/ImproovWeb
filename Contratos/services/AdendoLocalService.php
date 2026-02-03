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

        $nomeArquivoBase = 'ADENDO_CONTRATUAL_' . ($colab['nome_colaborador'] ?? 'COLABORADOR') . '_' . $competenciaInfo['mes_nome'] . '_' . $competenciaInfo['ano'];
        $nomeArquivo = $this->sanitizeFileName($nomeArquivoBase) . '.pdf';

        $qualificacao = $this->qualificacaoService->buildQualificacaoCompleta($colab);
        $qualificacaoEsc = $this->escapeHtml($qualificacao);
        $qualificacaoEsc = $this->boldNamesInQualificacao($qualificacaoEsc, $colab);

        [$contratanteNome, $contratanteCnpj] = $this->getContratanteInfo($colaboradorId, $colab);

        $itens = !empty($itensInput) ? $this->normalizeItensInput($itensInput) : $this->getAdendoItens($colaboradorId, $mes, $ano);
        if (!empty($funcoesFiltro)) {
            $itens = $this->filterItensByFuncoes($itens, $funcoesFiltro);
        }
        $nomeColaborador = (string)($colab['nome_colaborador'] ?? '');
        $showValor = !$this->isColaboradorSemValor($nomeColaborador);

        $rows = $this->buildRows($itens, $showValor);
        $extras = $this->normalizeExtras($extrasInput);

        if ($colaboradorId === 1) {
            $extras[] = [
                'categoria' => 'Acompanhamento',
                'valor' => 3000.00,
            ];
            $tabelaHtml = $this->buildExtrasTabelaHtml($extras);
            $totalValor = $this->sumExtras($extras);
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

        $contratadoNome = (string)($colab['nome_empresarial'] ?: $colab['nome_colaborador'] ?: '');
        $contratadoCnpj = $this->escapeHtml((string)($colab['cnpj'] ?? ''));

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
            'contratado_nome' => $this->escapeHtml($contratadoNome),
            'contratado_cnpj' => $contratadoCnpj,
        ];

        $pdf = $this->pdfService->gerarPdf($nomeArquivo, $placeholders);

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
        if ($colaboradorId === 13 || $nome === 'andre luis tavares') {
            $contratanteNome = 'STELLAR ANIMA LTDA.';
            $contratanteCnpj = '45.284.934/0001-30';
        }
        return [$contratanteNome, $contratanteCnpj];
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
            $incluirLinha = $dataPagamento === null || $dataPagamento === '0000-00-00' || $pagoParcial;
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
                'valor' => $showValor ? $this->formatCurrency($valor) : null,
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

        if (abs($valor - 125) < 0.01) {
            return 'Variação de proporção';
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
        $count = 0;
        while (true) {
            $dow = (int)$dt->format('N');
            if ($dow < 6) {
                $count++;
            }
            if ($count === 5) {
                return $dt;
            }
            $dt = $dt->modify('+1 day');
        }
    }

    private function getAdendoItens(int $colaboradorId, int $mesNumero, int $ano): array
    {
        $sql = '';
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
                        ) OR ico.status_id = 1
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
    WHERE 
        fi.colaborador_id = ?
        AND (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
        AND YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?
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
                        ) OR ico.status_id = 1
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
    WHERE 
        fi.colaborador_id = ?
        AND (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
        AND YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?
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
                        ) OR ico.status_id = 1
                        THEN CONCAT('Finalização Parcial - ', c.nome_colaborador)
                        ELSE CONCAT('Finalização Completa - ', c.nome_colaborador)
                    END 
            ELSE f.nome_funcao 
        END AS nome_funcao,
        fi.status,
        fi.prazo,
        fi.pagamento,
        fi.valor,
        fi.data_pagamento,
        c.nome_colaborador AS colaborador_ref,
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
    JOIN 
        colaborador c ON c.idcolaborador = fi.colaborador_id
    WHERE 
        fi.colaborador_id IN (23, 40)
        AND fi.funcao_id = 4
        AND (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
        AND YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?";
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
                    ) OR ico.status_id = 1
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
WHERE 
    fi.colaborador_id = ?
    AND (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
    AND YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?
UNION ALL
SELECT 
    an.colaborador_id,
    'animacao' AS origem,
    an.idanimacao AS identificador,
    an.imagem_id,
    ico.imagem_nome,
    NULL AS funcao_id,
    'Animação' AS nome_funcao,
    an.status_anima as status,
    an.data_anima as prazo,
    an.pagamento,
    an.valor,
    an.data_pagamento,
    NULL AS pago_parcial_count,
    NULL AS pago_completa_count,
    an.obra_id  
FROM 
    animacao an
JOIN 
    imagem_animacao ico ON an.imagem_id = ico.idimagem_animacao
WHERE 
    an.colaborador_id = ? AND YEAR(an.data_anima) = ? AND MONTH(an.data_anima) = ?
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
                        ) OR ico.status_id = 1
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
    WHERE 
        fi.colaborador_id = ?
        AND (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
        AND YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?
    ORDER BY ico.obra_id, ico.idimagens_cliente_obra, fi.funcao_id";
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Erro ao preparar consulta de adendo: ' . $this->conn->error);
        }

        if ($colaboradorId === 1) {
            $stmt->bind_param('iiiiii', $colaboradorId, $ano, $mesNumero, $colaboradorId, $ano, $mesNumero);
        } elseif ($colaboradorId === 8) {
            $stmt->bind_param('iiiii', $colaboradorId, $ano, $mesNumero, $ano, $mesNumero);
        } elseif (in_array($colaboradorId, [13, 20, 23, 37], true)) {
            $stmt->bind_param('iiiiii', $colaboradorId, $ano, $mesNumero, $colaboradorId, $ano, $mesNumero);
        } else {
            $stmt->bind_param('iii', $colaboradorId, $ano, $mesNumero);
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
            '', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove',
            'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis',
            'dezessete', 'dezoito', 'dezenove'
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
