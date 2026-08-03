<?php

declare(strict_types=1);

namespace FlowConnect\Application;

use mysqli;

/**
 * Reads the current business state immediately before a temporal notification.
 * Unknown is deliberately non-communicable: stale data must never generate a
 * cobrança merely because an old cycle happened to be active.
 */
final class OperationalStateProvider
{
    public function inspect(mysqli $conn, array $cycle): array
    {
        return match ((string) $cycle['module_key']) {
            'projeto', 'imagem' => $this->checklist($conn, $cycle),
            'flow_block' => $this->flowBlock($conn, $cycle),
            'render', 'flow_review' => $this->reviewTask($conn, $cycle),
            'links' => $this->links($conn, $cycle),
            'pre_alteracao' => $this->preAlteracao($conn, $cycle),
            'fotografico' => $this->fotografico($conn, $cycle),
            'cobranca_cliente' => $this->cobrancaCliente($conn, $cycle),
            default => ['state' => 'UNKNOWN'],
        };
    }

    private function checklist(mysqli $conn, array $cycle): array
    {
        $id = (int) $cycle['entity_id'];
        $stmt = $conn->prepare('SELECT status,responsavel_id,due_at FROM checklist_operacional WHERE id=? LIMIT 1');
        if (!$stmt) return ['state' => 'UNKNOWN'];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return ['state' => 'CANCELLED'];
        $status = strtolower((string) $row['status']);
        $state = in_array($status, ['concluido', 'concluida', 'resolvido', 'resolvida'], true) ? 'RESOLVED'
            : (in_array($status, ['cancelado', 'cancelada'], true) ? 'CANCELLED' : 'ACTIVE');
        return ['state' => $state, 'responsavel_id' => (int) ($row['responsavel_id'] ?? 0), 'due_at' => $row['due_at'] ?? null];
    }

    private function flowBlock(mysqli $conn, array $cycle): array
    {
        $id = (int) $cycle['entity_id'];
        $stmt = $conn->prepare('SELECT status,responsavel_colaborador_id,proxima_cobranca_em FROM flow_issue WHERE id=? LIMIT 1');
        if (!$stmt) return ['state' => 'UNKNOWN'];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return ['state' => 'CANCELLED'];
        $status = strtoupper((string) $row['status']);
        $state = $status === 'PAUSADA' ? 'PAUSED' : ($status === 'RESOLVIDA' ? 'RESOLVED' : ($status === 'CANCELADA' ? 'CANCELLED' : 'ACTIVE'));
        return ['state' => $state, 'responsavel_id' => (int) ($row['responsavel_colaborador_id'] ?? 0), 'due_at' => $row['proxima_cobranca_em'] ?? null];
    }

    private function reviewTask(mysqli $conn, array $cycle): array
    {
        $context = json_decode((string) ($cycle['context_json'] ?? '{}'), true) ?: [];
        if (($context['shadow_fixture'] ?? false) === true) {
            return ['state' => 'ACTIVE', 'responsavel_id' => (int) ($context['responsavel_id'] ?? $cycle['responsavel_id'] ?? 0)];
        }
        $id = (int) $cycle['entity_id'];
        $stmt = $conn->prepare('SELECT status,colaborador_id FROM funcao_imagem WHERE idfuncao_imagem=? LIMIT 1');
        if (!$stmt) return ['state' => 'UNKNOWN'];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return ['state' => 'CANCELLED'];
        return ['state' => (string) $row['status'] === 'Em aprovação' ? 'ACTIVE' : 'RESOLVED', 'responsavel_id' => (int) ($row['colaborador_id'] ?? 0)];
    }

    private function links(mysqli $conn, array $cycle): array
    {
        $id = (int) $cycle['entity_id'];
        $stmt = $conn->prepare('SELECT status,responsavel_id FROM pendencias_links_obra WHERE id=? LIMIT 1');
        if (!$stmt) return ['state' => 'UNKNOWN'];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return ['state' => 'CANCELLED'];
        $status = strtolower((string) ($row['status'] ?? ''));
        return ['state' => in_array($status, ['resolvida', 'concluida'], true) ? 'RESOLVED' : (str_contains($status, 'cancel') ? 'CANCELLED' : 'ACTIVE'), 'responsavel_id' => (int) ($row['responsavel_id'] ?? 0)];
    }

    private function preAlteracao(mysqli $conn, array $cycle): array
    {
        $id = (int) $cycle['entity_id'];
        $stmt = $conn->prepare('SELECT status,responsavel_id,created_by,prazo FROM pre_alt_lote WHERE id=? LIMIT 1');
        if (!$stmt) return ['state' => 'UNKNOWN'];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return ['state' => 'CANCELLED'];
        $status = strtoupper((string) $row['status']);
        return ['state' => $status === 'EM_TRIAGEM' ? 'ACTIVE' : ($status === 'CANCELADO' ? 'CANCELLED' : 'RESOLVED'), 'responsavel_id' => (int) ($row['responsavel_id'] ?? $row['created_by'] ?? 0), 'due_at' => $row['prazo'] ?? null];
    }

    private function fotografico(mysqli $conn, array $cycle): array
    {
        $id = (int) $cycle['entity_id'];
        $stmt = $conn->prepare('SELECT status,responsavel_id,responsavel_cobranca_id,proxima_cobranca_em FROM fotografico_pendencia WHERE id=? LIMIT 1');
        if (!$stmt) return ['state' => 'UNKNOWN'];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return ['state' => 'CANCELLED'];
        $status = strtoupper((string) $row['status']);
        return ['state' => $status === 'ABERTA' ? 'ACTIVE' : ($status === 'IGNORADA' ? 'CANCELLED' : 'RESOLVED'), 'responsavel_id' => (int) ($row['responsavel_id'] ?? 0), 'responsavel_cobranca_id' => (int) ($row['responsavel_cobranca_id'] ?? 0), 'due_at' => $row['proxima_cobranca_em'] ?? null];
    }

    private function cobrancaCliente(mysqli $conn, array $cycle): array
    {
        $id = (int) $cycle['entity_id'];
        $stmt = $conn->prepare('SELECT status,due_at,snooze_until FROM cobranca_review WHERE id=? LIMIT 1');
        if (!$stmt) return ['state' => 'UNKNOWN'];
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return ['state' => 'CANCELLED'];
        $status = strtoupper((string) $row['status']);
        return ['state' => $status === 'SNOOZED' ? 'PAUSED' : (in_array($status, ['PENDING', 'OVERDUE', 'NOTIFIED'], true) ? 'ACTIVE' : ($status === 'IGNORED' ? 'CANCELLED' : 'RESOLVED')), 'due_at' => $row['snooze_until'] ?: ($row['due_at'] ?? null)];
    }
}
