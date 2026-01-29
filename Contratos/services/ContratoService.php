<?php

class ContratoService
{
    private mysqli $conn;
    private ContratoDataService $dataService;
    private ContratoDateService $dateService;
    private ContratoQualificacaoService $qualificacaoService;
    private Clausula17Service $clausula17Service;
    private ZapSignClient $zapSign;
    private string $zapsignTemplateId;
    private bool $zapsignSandbox;

    public function __construct(
        mysqli $conn,
        ContratoDataService $dataService,
        ContratoDateService $dateService,
        ContratoQualificacaoService $qualificacaoService,
        Clausula17Service $clausula17Service,
        ZapSignClient $zapSign,
        string $zapsignTemplateId,
        bool $zapsignSandbox
    ) {
        $this->conn = $conn;
        $this->dataService = $dataService;
        $this->dateService = $dateService;
        $this->qualificacaoService = $qualificacaoService;
        $this->clausula17Service = $clausula17Service;
        $this->zapSign = $zapSign;
        $this->zapsignTemplateId = $zapsignTemplateId;
        $this->zapsignSandbox = $zapsignSandbox;
    }

    public function gerarContrato(int $colaboradorId, ?string $competencia = null): array
    {
        $competencia = $competencia ?: $this->dateService->buildCompetencia();

        $existente = $this->getContratoByCompetencia($colaboradorId, $competencia);
        if ($existente && in_array($existente['status'], ['enviado', 'assinado', 'recusado', 'expirado'], true)) {
            throw new RuntimeException('Contrato já enviado ou finalizado para esta competência.');
        }

        $colab = $this->dataService->getColaboradorContratoData($colaboradorId);
        $funcoes = $this->dataService->getColaboradorFuncoes($colaboradorId);

        $qualificacao = $this->qualificacaoService->buildQualificacaoCompleta($colab);
        $clausula = $this->clausula17Service->buildClausula17($funcoes, $colaboradorId);
        $datas = $this->dateService->getInicioFimPrazo();

        $payload = [
            'CONTRATADA_QUALIFICACAO_COMPLETA' => $qualificacao,
            'DATA_INICIO_CONTRATO' => $datas['inicio']->format('Y-m-d'),
            'DATA_FIM_CONTRATO' => $datas['fim']->format('Y-m-d'),
            'PRAZO_DIAS' => (string)$datas['prazo_dias'],
            'CLAUSULA_17_COMPLETA' => $clausula['texto'],
            'DATA_ENVIO_CONTRATO' => $this->dateService->formatDataPtBr(new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'))),
            'NOME_CONTRATADO' => $colab['nome_colaborador'] ?? '',
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $signerName = (string)($colab['nome_colaborador'] ?? '');
        $signerEmail = (string)($colab['email'] ?? '');
        if (trim($signerEmail) === '') {
            throw new RuntimeException('Colaborador sem e-mail cadastrado (necessário para assinatura).');
        }

        $docResp = $this->zapSign->createDocumentFromTemplate(
            $this->zapsignTemplateId,
            $signerName,
            $signerEmail,
            $payload,
            $this->zapsignSandbox
        );

        $docToken = $docResp['token'] ?? $docResp['doc_token'] ?? null;
        if (!$docToken) {
            throw new RuntimeException('Token do documento não retornado pela ZapSign.');
        }

        $now = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
        $this->salvarContrato(
            $existente ? (int)$existente['id'] : null,
            $colaboradorId,
            $competencia,
            'enviado',
            $docToken,
            $now,
            $datas['inicio']->format('Y-m-d'),
            $datas['fim']->format('Y-m-d'),
            $payloadJson
        );

        return [
            'success' => true,
            'token' => $docToken,
            'competencia' => $competencia,
        ];
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

    private function salvarContrato(?int $id, int $colaboradorId, string $competencia, string $status, string $docToken, string $dataEnvio, string $dataInicio, string $dataFim, string $payload): void
    {
        if ($id) {
            $sql = "UPDATE contratos SET status = ?, zapsign_doc_token = ?, data_envio = ?, data_inicio = ?, data_fim = ?, payload_enviado = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Falha ao atualizar contrato: ' . $this->conn->error);
            }
            $stmt->bind_param('ssssssi', $status, $docToken, $dataEnvio, $dataInicio, $dataFim, $payload, $id);
        } else {
            $sql = "INSERT INTO contratos (colaborador_id, competencia, status, zapsign_doc_token, data_envio, data_inicio, data_fim, payload_enviado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Falha ao inserir contrato: ' . $this->conn->error);
            }
            $stmt->bind_param('isssssss', $colaboradorId, $competencia, $status, $docToken, $dataEnvio, $dataInicio, $dataFim, $payload);
        }
        $stmt->execute();
        $stmt->close();
    }
}
