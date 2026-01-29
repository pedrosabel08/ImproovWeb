<?php

class ContratoLocalService
{
    private mysqli $conn;
    private ContratoDataService $dataService;
    private ContratoDateService $dateService;
    private ContratoQualificacaoService $qualificacaoService;
    private Clausula1Service $clausula1Service;
    private Clausula17Service $clausula17Service;
    private ContratoPdfService $pdfService;

    public function __construct(
        mysqli $conn,
        ContratoDataService $dataService,
        ContratoDateService $dateService,
        ContratoQualificacaoService $qualificacaoService,
        Clausula1Service $clausula1Service,
        Clausula17Service $clausula17Service,
        ContratoPdfService $pdfService
    ) {
        $this->conn = $conn;
        $this->dataService = $dataService;
        $this->dateService = $dateService;
        $this->qualificacaoService = $qualificacaoService;
        $this->clausula1Service = $clausula1Service;
        $this->clausula17Service = $clausula17Service;
        $this->pdfService = $pdfService;
    }

    public function gerarContrato(int $colaboradorId, ?string $competencia = null): array
    {
        $competencia = $competencia ?: $this->dateService->buildCompetencia();

        $existente = $this->getContratoByCompetencia($colaboradorId, $competencia);
        if ($existente && in_array($existente['status'], ['assinado', 'recusado', 'expirado'], true)) {
            throw new RuntimeException('Contrato já finalizado para esta competência.');
        }

        $colab = $this->dataService->getColaboradorContratoData($colaboradorId);
        $funcoes = $this->dataService->getColaboradorFuncoes($colaboradorId);

        $qualificacao = $this->qualificacaoService->buildQualificacaoCompleta($colab);
        $clausula1 = $this->clausula1Service->buildClausula1($colaboradorId, $funcoes);
        $clausula = $this->clausula17Service->buildClausula17($funcoes, $colaboradorId);
        $datas = $this->dateService->getInicioFimPrazo();
        $competenciaInfo = $this->dateService->getCompetenciaInfo($competencia);

        $nomeArquivoBase = 'CONTRATO_' . ($colab['nome_colaborador'] ?? 'COLABORADOR') . '_' . $competenciaInfo['mes_nome'] . '_' . $competenciaInfo['ano'];
        $nomeArquivo = $this->sanitizeFileName($nomeArquivoBase) . '.pdf';

        $listaImagens = $this->buildListaImagensHtml($funcoes);

        $qualificacaoEsc = $this->escapeHtml($qualificacao);
        $nomeColaborador = (string)($colab['nome_empresarial'] ?: $colab['nome_colaborador'] ?: '');
        if ($nomeColaborador !== '') {
            $nomeColaboradorEsc = $this->escapeHtml($nomeColaborador);
            $qualificacaoEsc = str_replace(
                $nomeColaboradorEsc,
                '<strong>' . $nomeColaboradorEsc . '</strong>',
                $qualificacaoEsc
            );
        }
        $qualificacaoEsc = preg_replace('/\bCONTRATADA\b/u', '<strong>CONTRATADA</strong>', $qualificacaoEsc) ?? $qualificacaoEsc;

        // CONTRATANTE (default) + exceção colaborador_id=13
        $contratanteNome = 'IMPROOV LTDA.';
        $contratanteCnpj = '37.066.879/0001-84';
        if ($colaboradorId === 13) {
            $contratanteNome = 'STELLAR ANIMA LTDA.';
            $contratanteCnpj = '45.284.934/0001-30';
        }

        $placeholders = [
            'contratante_nome' => $this->escapeHtml($contratanteNome),
            'contratante_cnpj' => $this->escapeHtml($contratanteCnpj),
            'dados_colaborador' => nl2br($qualificacaoEsc),
            'clausula_primeira' => $clausula1,
            'dias_vigencia' => (string)$datas['prazo_dias'] . ' dias',
            'inicio_vigencia' => $datas['inicio']->format('d/m/Y'),
            'termino_vigencia' => $datas['fim']->format('d/m/Y'),
            'clausula_dezessete' => nl2br($clausula['texto']),
            'dia_atual' => $this->dateService->formatDataPtBr(new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'))),
            'colaborador_contratado' => $this->escapeHtml((string)($colab['nome_empresarial'] ?: $colab['nome_colaborador'] ?: '')),
            'cnpj_contratado' => $this->escapeHtml((string)($colab['cnpj'] ?? '')),
            'lista_imagens' => $listaImagens,
        ];

        $pdf = $this->pdfService->gerarPdf(
            $nomeArquivo,
            $placeholders
        );

        $payload = [
            'CONTRATADA_QUALIFICACAO_COMPLETA' => $qualificacao,
            'DATA_INICIO_CONTRATO' => $datas['inicio']->format('Y-m-d'),
            'DATA_FIM_CONTRATO' => $datas['fim']->format('Y-m-d'),
            'PRAZO_DIAS' => (string)$datas['prazo_dias'],
            'CLAUSULA_17_COMPLETA' => $clausula['texto'],
            'DATA_GERACAO_CONTRATO' => $this->dateService->formatDataPtBr(new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'))),
            'NOME_CONTRATADO' => $colab['nome_colaborador'] ?? '',
            'ARQUIVO_NOME' => $nomeArquivo,
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $now = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');

        $this->salvarContrato(
            $existente ? (int)$existente['id'] : null,
            $colaboradorId,
            $competencia,
            'gerado',
            $now,
            $datas['inicio']->format('Y-m-d'),
            $datas['fim']->format('Y-m-d'),
            $payloadJson,
            $nomeArquivo,
            $pdf['file_path']
        );

        return [
            'success' => true,
            'competencia' => $competencia,
            'arquivo_nome' => $nomeArquivo,
        ];
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
        return $name === '' ? 'CONTRATO' : $name;
    }

    private function buildListaImagensHtml(array $funcoes): string
    {
        if (!$funcoes) {
            return '<li>-</li>';
        }

        $items = [];
        foreach ($funcoes as $f) {
            $nome = isset($f['nome_funcao']) ? (string)$f['nome_funcao'] : '';
            if ($nome === '') continue;
            $items[] = '<li>' . $this->escapeHtml($nome) . '</li>';
        }
        return $items ? implode("\n", $items) : '<li>-</li>';
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function getContratoByCompetencia(int $colaboradorId, string $competencia): ?array
    {
        $sql = "SELECT * FROM contratos WHERE colaborador_id = ? AND competencia = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Falha ao preparar contrato: ' . $this->conn->error);
        }
        $stmt->bind_param('is', $colaboradorId, $competencia);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    private function salvarContrato(
        ?int $id,
        int $colaboradorId,
        string $competencia,
        string $status,
        string $dataEnvio,
        string $dataInicio,
        string $dataFim,
        string $payload,
        string $arquivoNome,
        string $arquivoPath
    ): void {
        if ($id) {
            $sql = "UPDATE contratos SET status = ?, data_envio = ?, data_inicio = ?, data_fim = ?, payload_enviado = ?, arquivo_nome = ?, arquivo_path = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Falha ao atualizar contrato: ' . $this->conn->error);
            }
            $stmt->bind_param('sssssssi', $status, $dataEnvio, $dataInicio, $dataFim, $payload, $arquivoNome, $arquivoPath, $id);
        } else {
            $sql = "INSERT INTO contratos (colaborador_id, competencia, status, data_envio, data_inicio, data_fim, payload_enviado, arquivo_nome, arquivo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Falha ao inserir contrato: ' . $this->conn->error);
            }
            $stmt->bind_param('issssssss', $colaboradorId, $competencia, $status, $dataEnvio, $dataInicio, $dataFim, $payload, $arquivoNome, $arquivoPath);
        }
        $stmt->execute();
        $stmt->close();
    }
}
