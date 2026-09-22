<?php

/**
 * Atalhos navegáveis da Home a partir do histórico já registrado pela shell.
 * Rotas técnicas e a própria Home não entram na seleção.
 */

function home_navegacao_normalizar_path(string $url): string
{
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
    $path = '/' . trim(str_replace('\\', '/', $path), '/');
    $marker = stripos($path, '/ImproovWeb/');
    if ($marker !== false) {
        $path = substr($path, $marker + strlen('/ImproovWeb'));
    }
    return strtolower(rtrim($path, '/')) ?: '/';
}

function home_navegacao_tela(string $url, string $fallbackTitle = ''): ?array
{
    $path = home_navegacao_normalizar_path($url);
    $screens = [
        '/inicio.php' => ['key' => 'flow', 'label' => 'Flow', 'icon' => 'ri-layout-grid-line', 'target' => 'inicio.php'],
        '/projetos' => ['key' => 'projects', 'label' => 'Projetos', 'icon' => 'ri-folder-3-line', 'target' => 'Projetos/'],
        '/flowreview' => ['key' => 'flow_review', 'label' => 'Flow Review', 'icon' => 'ri-checkbox-circle-line', 'target' => 'FlowReview/'],
        '/alma' => ['key' => 'alma', 'label' => 'ALMA', 'icon' => 'ri-box-3-line', 'target' => 'ALMA/'],
        '/render' => ['key' => 'render', 'label' => 'Render', 'icon' => 'ri-image-line', 'target' => 'Render/'],
        '/planejamentocapacidade' => ['key' => 'capacity', 'label' => 'Capacidade', 'icon' => 'ri-line-chart-line', 'target' => 'PlanejamentoCapacidade/'],
        '/planejamentoproducao' => ['key' => 'planning', 'label' => 'Planejamento', 'icon' => 'ri-calendar-schedule-line', 'target' => 'PlanejamentoProducao/'],
        '/entregas' => ['key' => 'deliveries', 'label' => 'Entregas', 'icon' => 'ri-truck-line', 'target' => 'Entregas/'],
        '/calendario' => ['key' => 'calendar', 'label' => 'Calendário', 'icon' => 'ri-calendar-line', 'target' => 'Calendario/'],
        '/flowdrive' => ['key' => 'drive', 'label' => 'Arquivos', 'icon' => 'ri-folder-open-line', 'target' => 'FlowDrive/'],
        '/briefing' => ['key' => 'briefing', 'label' => 'Briefing', 'icon' => 'ri-file-list-3-line', 'target' => 'Briefing/'],
        '/gestao' => ['key' => 'management', 'label' => 'Gestão', 'icon' => 'ri-pie-chart-line', 'target' => 'Gestao/'],
    ];
    foreach ($screens as $prefix => $screen) {
        if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
            return $screen;
        }
    }
    return null;
}

function home_navegacao_atalhos(mysqli $conn, int $userId, int $limit = 5): array
{
    $fixed = ['key' => 'flow_review', 'label' => 'Flow Review', 'icon' => 'ri-checkbox-circle-line', 'target' => 'FlowReview/', 'fixed' => true];
    if ($userId <= 0 || $limit <= 0) {
        return [$fixed];
    }

    try {
        $stmt = $conn->prepare(
            'SELECT tela, url, created_at
               FROM logs_usuarios_historico
              WHERE usuario_id = ?
              ORDER BY created_at DESC
              LIMIT 80'
        );
        if (!$stmt) {
            throw new RuntimeException($conn->error);
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (Throwable $error) {
        return [$fixed];
    }

    $items = [$fixed];
    $seen = ['flow_review' => true];
    foreach ($rows as $row) {
        $screen = home_navegacao_tela((string) ($row['url'] ?? ''), (string) ($row['tela'] ?? ''));
        if ($screen === null || isset($seen[$screen['key']])) {
            continue;
        }
        $seen[$screen['key']] = true;
        $items[] = $screen + ['fixed' => false];
        if (count($items) >= $limit + 1) {
            break;
        }
    }
    return $items;
}
