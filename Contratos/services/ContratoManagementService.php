<?php

require_once __DIR__ . '/ContratoDateService.php';

/**
 * Fonte única dos dados da central de contratos.
 *
 * A competência é sempre aplicada no JOIN com contratos. Assim, a ausência de
 * contrato no mês consultado não é confundida com o último contrato existente.
 */
class ContratoManagementService
{
    private mysqli $conn;
    private ContratoDateService $dateService;

    private const STATUSES = ['nao_gerado', 'gerado', 'enviado', 'visualizado', 'assinado', 'recusado', 'expirado'];

    public function __construct(mysqli $conn, ?ContratoDateService $dateService = null)
    {
        $this->conn = $conn;
        $this->dateService = $dateService ?: new ContratoDateService();
    }

    public function getCompetenciaAtual(): string
    {
        return $this->dateService->buildCompetencia();
    }

    public function isCompetenciaValida(?string $competencia): bool
    {
        return is_string($competencia) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $competencia) === 1;
    }

    public function isCompetenciaAtual(string $competencia): bool
    {
        return hash_equals($this->getCompetenciaAtual(), $competencia);
    }

    public function getCompetenciasDisponiveis(): array
    {
        $competencias = [$this->getCompetenciaAtual() => true];
        $sql = "SELECT DISTINCT competencia FROM contratos WHERE competencia IS NOT NULL AND competencia <> '' ORDER BY competencia DESC";
        $result = $this->conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $competencia = (string) ($row['competencia'] ?? '');
                if ($this->isCompetenciaValida($competencia)) {
                    $competencias[$competencia] = true;
                }
            }
        }

        $items = array_keys($competencias);
        rsort($items, SORT_STRING);
        return $items;
    }

    public function getDashboard(string $competencia): array
    {
        if (!$this->isCompetenciaValida($competencia)) {
            throw new InvalidArgumentException('Competência inválida.');
        }

        $sql = "SELECT
                    c.idcolaborador,
                    c.nome_colaborador,
                    ct.id AS contrato_id,
                    ct.competencia,
                    ct.status,
                    ct.zapsign_doc_token,
                    ct.sign_url,
                    ct.data_envio,
                    ct.assinado_em,
                    ct.arquivo_nome,
                    ct.arquivo_path,
                    ct.created_at,
                    ct.updated_at
                FROM colaborador c
                LEFT JOIN contratos ct
                    ON ct.colaborador_id = c.idcolaborador
                    AND ct.competencia = ?
                WHERE c.ativo = 1
                    AND (c.cargo_id IS NULL OR c.cargo_id NOT IN (9, 11, 12, 13))
                ORDER BY c.nome_colaborador";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Falha ao preparar a consulta de contratos: ' . $this->conn->error);
        }
        $stmt->bind_param('s', $competencia);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $this->mapItem($row, $competencia);
        }
        $stmt->close();

        return [
            'competencia' => $competencia,
            'competencia_atual' => $this->getCompetenciaAtual(),
            'items' => $items,
            'resumo' => $this->buildResumo($items),
        ];
    }

    public function getHistorico(int $contratoId): ?array
    {
        $sql = "SELECT ct.*, c.nome_colaborador
                FROM contratos ct
                JOIN colaborador c ON c.idcolaborador = ct.colaborador_id
                WHERE ct.id = ?
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Falha ao preparar o histórico: ' . $this->conn->error);
        }
        $stmt->bind_param('i', $contratoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $contrato = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$contrato) {
            return null;
        }

        $logs = [];
        $stmt = $this->conn->prepare(
            'SELECT id, status, acao, origem, detalhe, ocorrido_em
             FROM log_contratos
             WHERE contrato_id = ?
             ORDER BY ocorrido_em ASC, id ASC'
        );
        if ($stmt) {
            $stmt->bind_param('i', $contratoId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
            $stmt->close();
        }

        return [
            'contrato' => $this->mapItem($contrato, (string) $contrato['competencia']),
            'historico' => $logs,
        ];
    }

    public function getArquivosParaDownload(array $colaboradorIds, string $competencia): array
    {
        if (!$this->isCompetenciaValida($competencia)) {
            throw new InvalidArgumentException('Competência inválida.');
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $colaboradorIds))));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT colaborador_id, arquivo_nome, arquivo_path
                FROM contratos
                WHERE competencia = ? AND colaborador_id IN ({$placeholders}) AND arquivo_nome IS NOT NULL";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Falha ao preparar os arquivos: ' . $this->conn->error);
        }
        $types = 's' . str_repeat('i', count($ids));
        $params = array_merge([$competencia], $ids);
        $refs = [$types];
        foreach ($params as $index => $value) {
            $refs[] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
        $stmt->execute();
        $result = $stmt->get_result();
        $files = [];
        while ($row = $result->fetch_assoc()) {
            $path = (string) ($row['arquivo_path'] ?? '');
            if ($path !== '' && is_file($path)) {
                $files[] = $row;
            }
        }
        $stmt->close();
        return $files;
    }

    private function mapItem(array $row, string $competencia): array
    {
        $status = (string) ($row['status'] ?? 'nao_gerado');
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'nao_gerado';
        }
        $exists = !empty($row['contrato_id']) || !empty($row['id']);
        if (!$exists) {
            $status = 'nao_gerado';
        }

        $arquivoNome = $row['arquivo_nome'] ?? null;
        $arquivoPath = $row['arquivo_path'] ?? null;
        $isCurrent = $this->isCompetenciaAtual($competencia);
        $downloadUrl = $exists ? $this->buildDownloadUrl($arquivoNome, $arquivoPath) : null;

        return [
            'colaborador_id' => (int) ($row['idcolaborador'] ?? $row['colaborador_id']),
            'colaborador_nome' => (string) $row['nome_colaborador'],
            'contrato_id' => $exists ? (int) ($row['contrato_id'] ?? $row['id']) : null,
            'competencia' => $competencia,
            'status' => $status,
            'arquivo_nome' => $arquivoNome,
            'download_url' => $downloadUrl,
            'sign_url' => $row['sign_url'] ?? null,
            'data_geracao' => $row['created_at'] ?? null,
            'data_envio' => $row['data_envio'] ?? null,
            'assinado_em' => $row['assinado_em'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'ultima_atualizacao' => $this->getUltimaAtualizacao($row),
            'is_competencia_atual' => $isCurrent,
            'can_generate' => $isCurrent && !$exists,
            'can_regenerate' => $isCurrent && $exists && $status === 'gerado',
            'can_download' => $downloadUrl !== null,
            'can_history' => $exists,
        ];
    }

    private function buildResumo(array $items): array
    {
        $resumo = [
            'total' => count($items),
            'assinado' => 0,
            'pendente_assinatura' => 0,
            'expirado' => 0,
            'recusado' => 0,
            'nao_gerado' => 0,
        ];
        foreach ($items as $item) {
            switch ($item['status']) {
                case 'assinado':
                    $resumo['assinado']++;
                    break;
                case 'expirado':
                    $resumo['expirado']++;
                    break;
                case 'recusado':
                    $resumo['recusado']++;
                    break;
                case 'nao_gerado':
                    $resumo['nao_gerado']++;
                    break;
                default:
                    $resumo['pendente_assinatura']++;
            }
        }
        return $resumo;
    }

    private function getUltimaAtualizacao(array $row): ?string
    {
        return $row['assinado_em'] ?? $row['updated_at'] ?? $row['data_envio'] ?? $row['created_at'] ?? null;
    }

    private function buildDownloadUrl(?string $arquivoNome, ?string $arquivoPath): ?string
    {
        $baseDir = realpath(__DIR__ . '/../gerados');
        if ($arquivoPath) {
            $realPath = realpath($arquivoPath);
            if ($baseDir && $realPath && str_starts_with($realPath, $baseDir . DIRECTORY_SEPARATOR)) {
                $relative = ltrim(substr($realPath, strlen($baseDir)), DIRECTORY_SEPARATOR);
                return './download.php?arquivo=' . rawurlencode($relative);
            }
        }
        return $arquivoNome ? './download.php?arquivo=' . rawurlencode((string) $arquivoNome) : null;
    }
}
