<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexaoMain.php';

function flow_connect_cli_options(array $argv): array
{
    $options = ['once' => false, 'daemon' => false, 'limit' => 20, 'verbose' => false, 'event_id' => null, 'cycle_id' => null, 'collaborator_id' => null];
    foreach ($argv as $arg) {
        if ($arg === '--once') $options['once'] = true;
        elseif ($arg === '--daemon') $options['daemon'] = true;
        elseif ($arg === '--verbose') $options['verbose'] = true;
        elseif (str_starts_with($arg, '--limit=')) $options['limit'] = max(1, min(500, (int) substr($arg, 8)));
        elseif (str_starts_with($arg, '--event-id=')) $options['event_id'] = max(1, (int) substr($arg, 11));
        elseif (str_starts_with($arg, '--cycle-id=')) $options['cycle_id'] = trim((string) substr($arg, 11)) ?: null;
        elseif (str_starts_with($arg, '--collaborator-id=')) $options['collaborator_id'] = max(1, (int) substr($arg, 18));
    }
    // Local/V1 nunca entra em loop infinito; --once é aceito por clareza operacional.
    if ($options['once'] && $options['daemon']) throw new InvalidArgumentException('Use apenas um entre --once e --daemon.');
    return $options;
}

function flow_connect_daemon_keep_running(): callable
{
    $running = true;
    if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        $stop = static function () use (&$running): void {
            $running = false;
        };
        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);
    }
    return static function () use (&$running): bool {
        return $running;
    };
}

function flow_connect_daemon_idle_wait(): void
{
    usleep(1000000);
}

function flow_connect_worker_id(string $name): string
{
    return substr($name . ':' . gethostname() . ':' . getmypid(), 0, 120);
}

function flow_connect_cli_log(string $message, bool $verbose = true): void
{
    if ($verbose) fwrite(STDOUT, '[' . gmdate('c') . '] ' . $message . PHP_EOL);
}
