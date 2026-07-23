<?php

declare(strict_types=1);

/** Medicao leve e estruturada das requisicoes do modulo Fotografico. */
final class FotoPerf
{
    private int $startedAt;
    private int $lastMark;
    private array $timings = [];
    private ?mysqli $conn = null;
    private ?int $queriesAtStart = null;
    private int $requestBytes;
    private ?int $responseBytes = null;
    public string $requestId;
    public string $action = '';
    public int $planId = 0;

    public function __construct()
    {
        $this->startedAt = hrtime(true);
        $this->lastMark = $this->startedAt;
        $this->requestId = bin2hex(random_bytes(8));
        $this->requestBytes = max(0, (int) ($_SERVER['CONTENT_LENGTH'] ?? 0));
    }

    public function attach(mysqli $conn): void
    {
        $this->conn = $conn;
        $this->queriesAtStart = $this->queryCount();
    }

    public function mark(string $stage): void
    {
        $now = hrtime(true);
        $this->timings[$stage] = round(($now - $this->lastMark) / 1_000_000, 2);
        $this->lastMark = $now;
    }

    public function setResponseSize(int $bytes): void
    {
        $this->responseBytes = max(0, $bytes);
    }

    private function queryCount(): ?int
    {
        if (!$this->conn) return null;
        $result = @$this->conn->query("SHOW SESSION STATUS LIKE 'Questions'");
        $row = $result ? $result->fetch_assoc() : null;
        $result?->free();
        return isset($row['Value']) ? (int) $row['Value'] : null;
    }

    public function finish(): void
    {
        $this->mark('response');
        $queries = null;
        $end = $this->queryCount();
        if ($end !== null && $this->queriesAtStart !== null) {
            $queries = max(0, $end - $this->queriesAtStart);
        }
        $total = round((hrtime(true) - $this->startedAt) / 1_000_000, 2);
        $parts = [
            'request_id=' . $this->requestId,
            'action=' . $this->action,
            'plan=' . $this->planId,
            'queries=' . ($queries ?? 'n/a'),
            'request_bytes=' . $this->requestBytes,
            'response_bytes=' . ($this->responseBytes ?? 'n/a'),
        ];
        foreach ($this->timings as $stage => $ms) $parts[] = $stage . '=' . $ms . 'ms';
        $parts[] = 'total=' . $total . 'ms';
        error_log('[FOTO-PERF] ' . implode(' ', $parts));

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (in_array($host, ['improov', 'localhost:8066', 'localhost'], true) && !headers_sent()) {
            $serverTiming = ['foto-total;dur=' . $total, 'foto-queries;desc="' . ($queries ?? 'n/a') . '"'];
            foreach ($this->timings as $stage => $ms) {
                $metric = preg_replace('/[^a-z0-9_-]/i', '-', $stage) ?: 'stage';
                $serverTiming[] = 'foto-' . $metric . ';dur=' . $ms;
            }
            header('Server-Timing: ' . implode(', ', $serverTiming));
        }
    }
}
