<?php

declare(strict_types=1);

/**
 * Migra diretrizes legadas para o documento semântico ALMA.
 *
 * Uso seguro (padrão): php migrate_structured_content.php --dry-run
 * Gravação explícita:    php migrate_structured_content.php --apply
 * Filtro opcional:       php migrate_structured_content.php --dry-run --item=123
 *
 * Não sobrescreve JSON existente, não apaga LONGTEXT e classifica casos
 * ambíguos como REVISAR para revisão editorial humana.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../alma_helpers.php';
require_once __DIR__ . '/../../conexaoMain.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$apply = in_array('--apply', $argv, true);
$itemId = 0;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--item=')) {
        $itemId = max(0, (int) substr($argument, 7));
    }
}

function alma_migration_clean(string $value): string
{
    return trim((string) preg_replace('/[ \t]+/u', ' ', str_replace("\r\n", "\n", $value)));
}

/** @return array{document:?array,status:string,reason:string} */
function alma_migration_document(string $legacy): array
{
    $legacy = alma_migration_clean($legacy);
    if ($legacy === '') {
        return ['document' => null, 'status' => 'PENDENTE', 'reason' => 'sem conteúdo legado'];
    }
    $headings = [
        ['pattern' => '/Princípio Fundamental(?=\s|$)/iu', 'type' => 'principle'],
        ['pattern' => '/O que reforça[^?]{0,140}\?/iu', 'type' => 'positive_list'],
        ['pattern' => '/O que enfraquece[^?]{0,140}\?/iu', 'type' => 'negative_list'],
        ['pattern' => '/Quais elementos[^?]{0,160}\?/iu', 'type' => 'positive_list'],
        ['pattern' => '/Como [^?]{3,160}\?/iu', 'type' => 'text'],
        ['pattern' => '/O que deve dominar[^?]{0,160}\?/iu', 'type' => 'text'],
        ['pattern' => '/O que o observador percebe primeiro\?/iu', 'type' => 'text'],
    ];
    $matches = [];
    foreach ($headings as $heading) {
        preg_match_all($heading['pattern'], $legacy, $found, PREG_OFFSET_CAPTURE);
        foreach ($found[0] ?? [] as $entry) {
            $matches[] = ['offset' => (int) $entry[1], 'title' => trim($entry[0]), 'type' => $heading['type']];
        }
    }
    $byOffset = [];
    foreach ($matches as $match) {
        $byOffset[$match['offset']] ??= $match;
    }
    $matches = array_values($byOffset);
    usort($matches, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);
    if (!$matches || $matches[0]['offset'] > 0) {
        return ['document' => null, 'status' => 'REVISAR', 'reason' => 'cabeçalhos legados insuficientes ou prefixo não identificado'];
    }
    $blocks = [];
    foreach ($matches as $index => $match) {
        $next = $matches[$index + 1]['offset'] ?? strlen($legacy);
        $body = trim(substr($legacy, $match['offset'] + strlen($match['title']), $next - $match['offset'] - strlen($match['title'])));
        if ($match['type'] === 'text' || $match['type'] === 'principle') {
            if ($body === '') {
                return ['document' => null, 'status' => 'REVISAR', 'reason' => 'bloco textual vazio'];
            }
            $blocks[] = ['type' => $match['type'], 'title' => $match['title'], 'content' => alma_migration_clean($body)];
            continue;
        }
        $list = array_values(array_filter(array_map('alma_migration_clean', preg_split('/\s*[✓●✕]\s*/u', $body) ?: [])));
        if (!$list) {
            return ['document' => null, 'status' => 'REVISAR', 'reason' => 'lista sem marcadores confiáveis'];
        }
        $blocks[] = ['type' => $match['type'], 'title' => $match['title'], 'items' => $list];
    }
    try {
        $document = alma_normalize_structured_content(['version' => 1, 'blocks' => $blocks]);
    } catch (Throwable $error) {
        return ['document' => null, 'status' => 'REVISAR', 'reason' => $error->getMessage()];
    }
    return ['document' => $document, 'status' => 'AUTOMATICO', 'reason' => 'padrão de cabeçalhos e listas reconhecido'];
}

$conn = conectarBanco();
$column = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alma_biblioteca_item' AND COLUMN_NAME = 'conteudo_estruturado'")->fetch_row();
if (!$column) {
    throw new RuntimeException('Aplique primeiro sql/2026-09-08_alma_conteudo_estruturado.sql.');
}
$sql = 'SELECT id, titulo, diretriz_completa, conteudo_estruturado FROM alma_biblioteca_item WHERE conteudo_estruturado IS NULL';
if ($itemId) {
    $sql .= ' AND id = ' . $itemId;
}
$rows = $conn->query($sql);
$report = ['candidates' => 0, 'automatic' => [], 'review' => []];
$conn->begin_transaction();
try {
    while ($row = $rows->fetch_assoc()) {
        $report['candidates']++;
        $result = alma_migration_document((string) ($row['diretriz_completa'] ?? ''));
        $target = $result['status'] === 'AUTOMATICO' ? 'automatic' : 'review';
        $report[$target][] = ['id' => (int) $row['id'], 'titulo' => $row['titulo'], 'motivo' => $result['reason']];
        if ($apply && $result['document']) {
            $json = json_encode($result['document'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $status = $result['status'];
            $id = (int) $row['id'];
            $stmt = $conn->prepare('UPDATE alma_biblioteca_item SET conteudo_estruturado=?, conteudo_estruturado_revisao_status=? WHERE id=? AND conteudo_estruturado IS NULL');
            $stmt->bind_param('ssi', $json, $status, $id);
            $stmt->execute();
            $stmt->close();
        } elseif ($apply) {
            $status = 'REVISAR';
            $id = (int) $row['id'];
            $stmt = $conn->prepare('UPDATE alma_biblioteca_item SET conteudo_estruturado_revisao_status=? WHERE id=? AND conteudo_estruturado IS NULL');
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();
            $stmt->close();
        }
    }
    $apply ? $conn->commit() : $conn->rollback();
} catch (Throwable $error) {
    $conn->rollback();
    throw $error;
} finally {
    $conn->close();
}
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
