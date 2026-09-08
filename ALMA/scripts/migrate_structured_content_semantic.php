<?php

declare(strict_types=1);

/**
 * Migração semântica dos conteúdos legados da Biblioteca ALMA.
 *
 * Uso:
 *   php ALMA/scripts/migrate_structured_content_semantic.php --dry-run
 *   php ALMA/scripts/migrate_structured_content_semantic.php --apply
 *   php ALMA/scripts/migrate_structured_content_semantic.php --item=124 --dry-run
 *
 * O script exporta backup e relatório em ALMA/scripts/reports antes de gravar.
 * Não altera campos legados: grava apenas conteudo_estruturado e seu status.
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

function alma_semantic_clean(?string $value): string
{
    $value = str_replace(["\r\n", "\r", "\n"], ' ', (string) $value);
    return trim((string) preg_replace('/\s+/u', ' ', $value));
}

function alma_semantic_key(string $value): string
{
    $value = mb_strtolower(alma_semantic_clean($value));
    return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
}

/** @return list<string> */
function alma_semantic_list(string $value): array
{
    $parts = preg_split('/\s*[✓●✕]\s*/u', $value) ?: [];
    $items = [];
    foreach ($parts as $part) {
        $part = alma_semantic_clean($part);
        if ($part !== '') {
            $items[] = $part;
        }
    }
    return $items;
}

/** @return list<array{offset:int,title:string,type:string}> */
function alma_semantic_headings(string $legacy): array
{
    $patterns = [
        ['pattern' => '/Princípio Fundamental(?=\s|$)/iu', 'type' => 'principle'],
        ['pattern' => '/Quais elementos de cena normalmente ajudam a comunicar[^?]{0,180}\?/iu', 'type' => 'positive_list'],
        ['pattern' => '/Quais elementos reforçam[^?]{0,180}\?/iu', 'type' => 'positive_list'],
        ['pattern' => '/O que reforça[^?]{0,180}\?/iu', 'type' => 'positive_list'],
        ['pattern' => '/Quais elementos enfraquecem[^?]{0,180}\?/iu', 'type' => 'negative_list'],
        ['pattern' => '/O que enfraquece[^?]{0,180}\?/iu', 'type' => 'negative_list'],
        ['pattern' => '/O que define (?:esse estilo|essa materialidade)\?/iu', 'type' => 'text'],
        ['pattern' => '/Que tipo de experiência humana estamos retratando\?/iu', 'type' => 'text'],
        ['pattern' => '/O que deve dominar[^?]{0,180}\?/iu', 'type' => 'text'],
        ['pattern' => '/Como essa luz se comporta\?/iu', 'type' => 'text'],
        ['pattern' => '/Como queremos que[^?]{0,180}\?/iu', 'type' => 'text'],
        ['pattern' => '/O que o observador percebe primeiro\?/iu', 'type' => 'text'],
    ];
    $matches = [];
    foreach ($patterns as $priority => $rule) {
        preg_match_all($rule['pattern'], $legacy, $found, PREG_OFFSET_CAPTURE);
        foreach ($found[0] ?? [] as $entry) {
            $matches[] = [
                'offset' => (int) $entry[1],
                'title' => alma_semantic_clean($entry[0]),
                'type' => $rule['type'],
                'priority' => $priority,
            ];
        }
    }
    usort($matches, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset'] ?: $a['priority'] <=> $b['priority']);
    $unique = [];
    foreach ($matches as $match) {
        $unique[$match['offset']] ??= $match;
    }
    return array_values($unique);
}

/** @return list<array<string,mixed>> */
function alma_semantic_parse_directive(string $legacy, array &$warnings): array
{
    $legacy = alma_semantic_clean($legacy);
    if ($legacy === '') {
        return [];
    }
    $headings = alma_semantic_headings($legacy);
    if (!$headings) {
        $warnings[] = 'Diretriz sem cabeçalhos reconhecíveis; preservada como bloco textual único.';
        return [['type' => 'text', 'title' => 'Diretriz completa', 'content' => $legacy]];
    }
    $blocks = [];
    if ($headings[0]['offset'] > 0) {
        $prefix = alma_semantic_clean(substr($legacy, 0, $headings[0]['offset']));
        if ($prefix !== '') {
            $warnings[] = 'Prefixo anterior ao primeiro cabeçalho preservado como texto.';
            $blocks[] = ['type' => 'text', 'title' => 'Diretriz completa', 'content' => $prefix];
        }
    }
    foreach ($headings as $index => $heading) {
        $nextOffset = $headings[$index + 1]['offset'] ?? strlen($legacy);
        $content = alma_semantic_clean(substr(
            $legacy,
            $heading['offset'] + strlen($heading['title']),
            $nextOffset - $heading['offset'] - strlen($heading['title'])
        ));
        if ($content === '') {
            $warnings[] = sprintf('Cabeçalho "%s" não possuía conteúdo.', $heading['title']);
            continue;
        }
        if (in_array($heading['type'], ['positive_list', 'negative_list'], true)) {
            $items = alma_semantic_list($content);
            if ($items) {
                $blocks[] = ['type' => $heading['type'], 'title' => $heading['title'], 'items' => $items];
            } else {
                $warnings[] = sprintf('Lista "%s" sem marcadores preservada como texto.', $heading['title']);
                $blocks[] = ['type' => 'text', 'title' => $heading['title'], 'content' => $content];
            }
            continue;
        }
        $blocks[] = ['type' => $heading['type'], 'title' => $heading['title'], 'content' => $content];
    }
    return $blocks;
}

/** @param list<array<string,mixed>> $blocks */
function alma_semantic_has_text(array $blocks, string $title, string $content, ?string $type = null): bool
{
    $target = alma_semantic_key($content);
    $targetTitle = alma_semantic_key($title);
    if ($target === '') {
        return true;
    }
    foreach ($blocks as $block) {
        if (($type === null || ($block['type'] ?? null) === $type)
            && alma_semantic_key((string) ($block['title'] ?? '')) === $targetTitle
            && isset($block['content'])
            && alma_semantic_key((string) $block['content']) === $target) {
            return true;
        }
    }
    return false;
}

/** @param list<array<string,mixed>> $blocks @param list<string> $items */
function alma_semantic_has_list(array $blocks, string $type, array $items): bool
{
    $target = array_map('alma_semantic_key', $items);
    foreach ($blocks as $block) {
        if (($block['type'] ?? null) === $type && isset($block['items']) && is_array($block['items'])
            && array_map('alma_semantic_key', $block['items']) === $target) {
            return true;
        }
    }
    return false;
}

/** @param list<array<string,mixed>> $blocks */
function alma_semantic_add_text(array &$blocks, string $title, ?string $content, string $type = 'text'): void
{
    $content = alma_semantic_clean($content);
    if ($content !== '' && !alma_semantic_has_text($blocks, $title, $content, $type)) {
        $blocks[] = ['type' => $type, 'title' => $title, 'content' => $content];
    }
}

/** @param list<array<string,mixed>> $blocks @param list<string> $items */
function alma_semantic_add_list(array &$blocks, string $type, string $title, array $items): void
{
    $items = array_values(array_filter(array_map('alma_semantic_clean', $items)));
    if ($items && !alma_semantic_has_list($blocks, $type, $items)) {
        $blocks[] = ['type' => $type, 'title' => $title, 'items' => $items];
    }
}

/** @return array<int,list<array{codigo:string,titulo:string,conteudo:?string,entradas:list<array{tipo:string,texto:string}>}>> */
function alma_semantic_sections(mysqli $conn): array
{
    $sql = 'SELECT s.item_id, s.id AS secao_id, s.codigo, s.titulo, s.conteudo, s.ordem, e.tipo, e.texto, e.ordem AS entrada_ordem
        FROM alma_biblioteca_item_secao s
        LEFT JOIN alma_biblioteca_secao_entrada e ON e.secao_id=s.id
        ORDER BY s.item_id, s.ordem, s.id, e.ordem, e.id';
    $rows = $conn->query($sql);
    $sections = [];
    while ($row = $rows->fetch_assoc()) {
        $itemId = (int) $row['item_id'];
        $sectionId = (int) $row['secao_id'];
        if (!isset($sections[$itemId][$sectionId])) {
            $sections[$itemId][$sectionId] = [
                'codigo' => (string) $row['codigo'],
                'titulo' => (string) $row['titulo'],
                'conteudo' => $row['conteudo'],
                'entradas' => [],
            ];
        }
        if ($row['tipo'] !== null && alma_semantic_clean($row['texto']) !== '') {
            $sections[$itemId][$sectionId]['entradas'][] = ['tipo' => (string) $row['tipo'], 'texto' => alma_semantic_clean($row['texto'])];
        }
    }
    foreach ($sections as $itemId => $group) {
        $sections[$itemId] = array_values($group);
    }
    return $sections;
}

/** @param list<array<string,mixed>> $blocks @param list<array<string,mixed>> $sections */
function alma_semantic_add_sections(array &$blocks, array $sections, string $pillar): void
{
    foreach ($sections as $section) {
        $code = (string) $section['codigo'];
        if (in_array($code, ['fonte_oficial', 'complementar'], true) || str_starts_with($code, 'diretriz_')) {
            continue; // fontes agregadas e recortes já presentes na diretriz
        }
        $title = alma_semantic_clean((string) $section['titulo']);
        $content = alma_semantic_clean($section['conteudo'] ?? '');
        $positive = [];
        $negative = [];
        $other = [];
        foreach ($section['entradas'] ?? [] as $entry) {
            $kind = strtoupper((string) $entry['tipo']);
            if ($kind === 'PRIORIZAR') {
                $positive[] = $entry['texto'];
            } elseif ($kind === 'EVITAR') {
                $negative[] = $entry['texto'];
            } else {
                $other[] = $entry['texto'];
            }
        }
        if ($pillar === 'Materialidade' && str_starts_with($code, 'complementar_') && ($positive || $negative)) {
            $duplicate = false;
            foreach ($blocks as $block) {
                if (($block['type'] ?? null) !== 'material_guideline' || alma_semantic_key((string) $block['title']) !== alma_semantic_key($title)) {
                    continue;
                }
                if (array_map('alma_semantic_key', $block['positive']) === array_map('alma_semantic_key', $positive)
                    && array_map('alma_semantic_key', $block['negative']) === array_map('alma_semantic_key', $negative)) {
                    $duplicate = true;
                    break;
                }
            }
            if (!$duplicate) {
                $blocks[] = ['type' => 'material_guideline', 'title' => $title, 'positive' => $positive, 'negative' => $negative];
            }
            alma_semantic_add_text($blocks, $title, $content);
            alma_semantic_add_list($blocks, 'positive_list', $title, $other);
            continue;
        }
        alma_semantic_add_text($blocks, $title, $content);
        alma_semantic_add_list($blocks, 'positive_list', $title, $positive);
        alma_semantic_add_list($blocks, 'negative_list', $title, $negative);
        alma_semantic_add_list($blocks, str_contains(mb_strtolower($code), 'evitar') ? 'negative_list' : 'positive_list', $title, $other);
    }
}

/** @return array{document:?array,warnings:list<string>,has_legacy:bool} */
function alma_semantic_document(array $item, array $sections): array
{
    $warnings = [];
    $hasLegacy = false;
    foreach (['resumo', 'diferenca_principal', 'descricao', 'principio_fundamental', 'diretriz_completa'] as $field) {
        $hasLegacy = $hasLegacy || alma_semantic_clean($item[$field] ?? null) !== '';
    }
    foreach ($sections as $section) {
        $hasLegacy = $hasLegacy || alma_semantic_clean($section['conteudo'] ?? null) !== '' || !empty($section['entradas']);
    }
    if (!$hasLegacy) {
        return ['document' => null, 'warnings' => ['Item sem conteúdo legado para estruturar.'], 'has_legacy' => false];
    }
    $blocks = alma_semantic_parse_directive((string) ($item['diretriz_completa'] ?? ''), $warnings);
    alma_semantic_add_text($blocks, 'Resumo', $item['resumo'] ?? null);
    alma_semantic_add_text($blocks, 'Diferença principal', $item['diferenca_principal'] ?? null);
    alma_semantic_add_text($blocks, 'Descrição', $item['descricao'] ?? null);
    alma_semantic_add_sections($blocks, $sections, (string) ($item['pilar_nome'] ?? ''));
    alma_semantic_add_text($blocks, 'Princípio Fundamental', $item['principio_fundamental'] ?? null, 'principle');
    try {
        return [
            'document' => alma_normalize_structured_content(['version' => 1, 'blocks' => $blocks]),
            'warnings' => $warnings,
            'has_legacy' => true,
        ];
    } catch (Throwable $error) {
        $warnings[] = 'Falha de validação: ' . $error->getMessage();
        return ['document' => null, 'warnings' => $warnings, 'has_legacy' => true];
    }
}

function alma_semantic_equal(?array $left, ?array $right): bool
{
    return json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        === json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function alma_semantic_write_json(string $path, array $payload): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível criar o diretório de relatórios.');
    }
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

$conn = conectarBanco();
$column = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alma_biblioteca_item' AND COLUMN_NAME = 'conteudo_estruturado'")->fetch_row();
if (!$column) {
    throw new RuntimeException('Aplique primeiro sql/2026-09-08_alma_conteudo_estruturado.sql.');
}
$sql = 'SELECT i.*, d.codigo AS dimensao_codigo, d.nome AS dimensao_nome, d.pilar_nome, v.id AS versao_id, v.codigo AS versao_codigo, v.nome AS versao_nome, v.estado AS versao_estado
    FROM alma_biblioteca_item i
    JOIN alma_biblioteca_dimensao d ON d.id=i.dimensao_id
    JOIN alma_biblioteca_versao v ON v.id=d.versao_id';
if ($itemId) {
    $sql .= ' WHERE i.id=' . $itemId;
}
$sql .= ' ORDER BY v.id, d.ordem_jornada, d.ordem_no_pilar, i.ordem, i.id';
$itemsResult = $conn->query($sql);
$items = [];
while ($item = $itemsResult->fetch_assoc()) {
    $items[] = $item;
}
$sectionsByItem = alma_semantic_sections($conn);

$stamp = date('Y-m-d_His');
$reportsDir = __DIR__ . '/reports';
$backupPath = $reportsDir . '/structured-content-backup-' . $stamp . '.json';
alma_semantic_write_json($backupPath, ['generated_at' => date(DATE_ATOM), 'items' => $items, 'sections_by_item' => $sectionsByItem]);

$report = [
    'generated_at' => date(DATE_ATOM),
    'mode' => $apply ? 'apply' : 'dry-run',
    'backup' => basename($backupPath),
    'summary' => ['items' => 0, 'valid_before' => 0, 'proposed' => 0, 'changed' => 0, 'no_legacy_content' => 0, 'review' => 0],
    'items' => [],
];
$updates = [];
foreach ($items as $item) {
    $before = alma_decode_structured_content($item['conteudo_estruturado'] ?? null);
    $proposal = alma_semantic_document($item, $sectionsByItem[(int) $item['id']] ?? []);
    $document = $proposal['document'];
    $newStatus = (string) $item['conteudo_estruturado_revisao_status'];
    $action = 'SKIP';
    if ($before) {
        $report['summary']['valid_before']++;
    }
    if (!$proposal['has_legacy']) {
        $report['summary']['no_legacy_content']++;
        $newStatus = 'PENDENTE';
    } elseif (!$document) {
        $report['summary']['review']++;
        $newStatus = 'REVISAR';
    } else {
        $report['summary']['proposed']++;
        $newStatus = 'MANUAL';
        if (!alma_semantic_equal($before, $document) || $newStatus !== ($item['conteudo_estruturado_revisao_status'] ?? '')) {
            $action = 'PERSIST';
            $report['summary']['changed']++;
            $updates[] = ['id' => (int) $item['id'], 'document' => $document, 'status' => $newStatus];
        } else {
            $action = 'VALIDATED';
        }
    }
    $report['summary']['items']++;
    $report['items'][] = [
        'id' => (int) $item['id'],
        'version' => ['id' => (int) $item['versao_id'], 'codigo' => $item['versao_codigo'], 'nome' => $item['versao_nome'], 'state' => $item['versao_estado']],
        'pillar' => $item['pilar_nome'],
        'dimension' => $item['dimensao_nome'],
        'name' => $item['titulo'],
        'active' => (bool) $item['ativo'],
        'previous_status' => $item['conteudo_estruturado_revisao_status'],
        'new_status' => $newStatus,
        'has_legacy_content' => $proposal['has_legacy'],
        'previous_document' => $before,
        'proposed_document' => $document,
        'warnings' => $proposal['warnings'],
        'action' => $action,
    ];
}

if ($apply) {
    $conn->begin_transaction();
    try {
        foreach ($updates as $update) {
            $json = json_encode($update['document'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $statement = $conn->prepare('UPDATE alma_biblioteca_item SET conteudo_estruturado=?, conteudo_estruturado_revisao_status=? WHERE id=?');
            $statement->bind_param('ssi', $json, $update['status'], $update['id']);
            $statement->execute();
            $statement->close();
        }
        foreach ($report['items'] as $row) {
            if (!$row['has_legacy_content']) {
                $status = 'PENDENTE';
                $id = (int) $row['id'];
                $statement = $conn->prepare('UPDATE alma_biblioteca_item SET conteudo_estruturado_revisao_status=? WHERE id=? AND conteudo_estruturado IS NULL');
                $statement->bind_param('si', $status, $id);
                $statement->execute();
                $statement->close();
            }
        }
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

$reportPath = $reportsDir . '/structured-content-review-' . $stamp . ($apply ? '-applied' : '-preview') . '.json';
alma_semantic_write_json($reportPath, $report);
$conn->close();
echo json_encode(['report' => $reportPath, 'backup' => $backupPath, 'summary' => $report['summary']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
