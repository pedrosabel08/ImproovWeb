<?php

declare(strict_types=1);

namespace FlowConnect\Application\PendingSummary;

use FlowConnect\Contracts\PendingSummaryProviderInterface;
use mysqli;
use RuntimeException;

abstract class AbstractPendingSummaryProvider implements PendingSummaryProviderInterface
{
    public function __construct(
        protected array $config,
        protected string $moduleKey,
        protected string $moduleName,
        protected string $originPath = 'PaginaPrincipal/'
    ) {}

    protected function tableExists(mysqli $conn, string $table): bool
    {
        $stmt = $conn->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
        if (!$stmt) return false;
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows === 1;
        $stmt->close();
        return $exists;
    }

    protected function columnExists(mysqli $conn, string $table, string $column): bool
    {
        $stmt = $conn->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1');
        if (!$stmt) return false;
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows === 1;
        $stmt->close();
        return $exists;
    }

    /** @return list<array> */
    protected function query(mysqli $conn, string $sql): array
    {
        $result = $conn->query($sql);
        if (!$result) throw new RuntimeException('pending_summary_query_failed:' . $this->moduleKey);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /** @param list<array> $rows @return list<array> */
    protected function summarize(array $rows): array
    {
        $byCollaborator = [];
        $limit = (int) ($this->config['operational']['pending_summary']['preview_limit_per_module'] ?? 3);
        foreach ($rows as $row) {
            $recipients = $row['recipient_ids'] ?? [$row['collaborator_id'] ?? 0];
            foreach ((array) $recipients as $recipient) {
                $collaboratorId = (int) $recipient;
                if ($collaboratorId <= 0) continue;
                $entityId = (string) ($row['entity_id'] ?? '');
                if ($entityId === '') continue;
                $bucket =& $byCollaborator[$collaboratorId];
                if (!isset($bucket)) {
                    $bucket = ['seen' => [], 'items' => []];
                }
                if (isset($bucket['seen'][$entityId])) continue;
                $bucket['seen'][$entityId] = true;
                $bucket['items'][] = $row;
                unset($bucket);
            }
        }
        $summaries = [];
        foreach ($byCollaborator as $collaboratorId => $bucket) {
            $items = $bucket['items'];
            usort($items, static fn(array $a, array $b): int => strcmp((string) ($a['due_at'] ?? $a['created_at'] ?? '9999'), (string) ($b['due_at'] ?? $b['created_at'] ?? '9999')));
            $priorities = array_column($items, 'priority');
            $priority = in_array('CRITICAL', $priorities, true) ? 'CRITICAL' : (in_array('ATTENTION', $priorities, true) ? 'ATTENTION' : 'NORMAL');
            $created = array_values(array_filter(array_column($items, 'created_at')));
            $due = array_values(array_filter(array_column($items, 'due_at')));
            $preview = array_map(static fn(array $item): array => [
                'entity_type' => (string) $item['entity_type'], 'entity_id' => (string) $item['entity_id'],
                'titulo' => (string) ($item['title'] ?? ''), 'obra' => (string) ($item['obra'] ?? ''),
                'imagem' => (string) ($item['imagem'] ?? ''), 'funcao' => (string) ($item['funcao'] ?? ''),
                'created_at' => $item['created_at'] ?? null, 'due_at' => $item['due_at'] ?? null, 'link' => (string) ($item['link'] ?? ''),
            ], array_slice($items, 0, $limit));
            $summaries[] = [
                'module_key' => $this->moduleKey, 'module_name' => $this->moduleName, 'collaborator_id' => (int) $collaboratorId,
                'total' => count($items), 'oldest_created_at' => $created === [] ? null : min($created),
                'oldest_due_at' => $due === [] ? null : min($due), 'highest_priority' => $priority,
                'preview_items' => $preview, 'origin_url' => $this->originUrl(),
            ];
        }
        return $summaries;
    }

    protected function originUrl(): string
    {
        $base = rtrim((string) ($this->config['operational']['pending_summary']['origin_url'] ?? ''), '/');
        return $this->originPath === '' ? $base : $base . '/' . ltrim($this->originPath, '/');
    }

    protected function priority(?string $dueAt): string
    {
        if (!$dueAt) return 'NORMAL';
        $timestamp = strtotime($dueAt);
        if ($timestamp !== false && $timestamp < time()) return 'CRITICAL';
        if ($timestamp !== false && $timestamp < time() + 86400) return 'ATTENTION';
        return 'NORMAL';
    }
}
