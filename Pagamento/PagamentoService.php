<?php

/**
 * Núcleo inicial do domínio financeiro.
 *
 * Os endpoints legados continuam existindo nesta etapa. Este serviço concentra
 * primitivas idempotentes para que eles possam ser migrados gradualmente sem
 * alterar os valores financeiros já registrados.
 */
final class PagamentoService
{
    public const STATUS_PENDENTE_ENVIO = 'pendente_envio';
    public const STATUS_AGUARDANDO_RETORNO = 'aguardando_retorno';
    public const STATUS_VALIDADO = 'validado';
    public const STATUS_ADENDO_GERADO = 'adendo_gerado';
    public const STATUS_PAGO = 'pago';

    private mysqli $conn;
    private ?int $usuarioId;

    public function __construct(mysqli $conn, ?int $usuarioId = null)
    {
        $this->conn = $conn;
        $this->usuarioId = $usuarioId;
    }

    public static function competencia(int $mes, int $ano): string
    {
        if ($mes < 1 || $mes > 12 || $ano < 2000) {
            throw new InvalidArgumentException('Competência inválida.');
        }

        return sprintf('%04d-%02d', $ano, $mes);
    }

    public static function normalizarStatus(string $status): ?string
    {
        $status = strtolower(trim($status));
        $aliases = [
            'pendente' => self::STATUS_PENDENTE_ENVIO,
            'pendente_envio' => self::STATUS_PENDENTE_ENVIO,
            'enviado' => self::STATUS_AGUARDANDO_RETORNO,
            'confirmando' => self::STATUS_AGUARDANDO_RETORNO,
            'aguardando_retorno' => self::STATUS_AGUARDANDO_RETORNO,
            'validado' => self::STATUS_VALIDADO,
            'confirmado' => self::STATUS_VALIDADO,
            'adendo' => self::STATUS_ADENDO_GERADO,
            'adendo_gerado' => self::STATUS_ADENDO_GERADO,
            'pago' => self::STATUS_PAGO,
        ];

        return $aliases[$status] ?? null;
    }

    public function garantirPagamento(int $colaboradorId, int $mes, int $ano): int
    {
        $mesRef = self::competencia($mes, $ano);
        $stmt = $this->conn->prepare(
            'SELECT idpagamento FROM pagamentos WHERE colaborador_id = ? AND mes_ref = ? FOR UPDATE'
        );
        if (!$stmt) {
            throw new RuntimeException('Não foi possível consultar o pagamento.');
        }

        $stmt->bind_param('is', $colaboradorId, $mesRef);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Não foi possível consultar o pagamento.');
        }
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            return (int) $row['idpagamento'];
        }

        $status = self::STATUS_PENDENTE_ENVIO;
        $stmt = $this->conn->prepare(
            'INSERT INTO pagamentos (colaborador_id, mes_ref, status, criado_por) VALUES (?, ?, ?, ?)'
        );
        if (!$stmt) {
            throw new RuntimeException('Não foi possível criar o pagamento.');
        }

        $usuarioId = $this->usuarioId;
        $stmt->bind_param('issi', $colaboradorId, $mesRef, $status, $usuarioId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Não foi possível criar o pagamento.');
        }
        $id = (int) $stmt->insert_id;
        $stmt->close();

        $this->registrarEvento($id, 'created', 'Pagamento criado automaticamente.');
        return $id;
    }

    public function registrarEvento(int $pagamentoId, string $tipo, string $descricao): void
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO pagamento_eventos (pagamento_id, tipo, descricao, usuario_id) VALUES (?, ?, ?, ?)'
        );
        if (!$stmt) {
            throw new RuntimeException('Não foi possível registrar o evento do pagamento.');
        }

        $usuarioId = $this->usuarioId;
        $stmt->bind_param('issi', $pagamentoId, $tipo, $descricao, $usuarioId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Não foi possível registrar o evento do pagamento.');
        }
        $stmt->close();
    }

    public function itemExiste(int $pagamentoId, string $origem, int $origemId, ?string $observacao = null): bool
    {
        if ($observacao === null) {
            $stmt = $this->conn->prepare(
                'SELECT 1 FROM pagamento_itens WHERE pagamento_id = ? AND origem = ? AND origem_id = ? AND observacao IS NULL LIMIT 1'
            );
            if (!$stmt) {
                throw new RuntimeException('Não foi possível validar o item do pagamento.');
            }
            $stmt->bind_param('isi', $pagamentoId, $origem, $origemId);
        } else {
            $stmt = $this->conn->prepare(
                'SELECT 1 FROM pagamento_itens WHERE pagamento_id = ? AND origem = ? AND origem_id = ? AND observacao = ? LIMIT 1'
            );
            if (!$stmt) {
                throw new RuntimeException('Não foi possível validar o item do pagamento.');
            }
            $stmt->bind_param('isis', $pagamentoId, $origem, $origemId, $observacao);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Não foi possível validar o item do pagamento.');
        }

        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }
}
