<?php

declare(strict_types=1);

return static function (array $payload): array {
    return ['text' => trim((string) ($payload['message'] ?? 'Notificação operacional.')), 'blocks' => null];
};
