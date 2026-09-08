<?php

declare(strict_types=1);

/**
 * Normaliza o conteudo textual da Biblioteca ALMA v1 em secoes menores.
 *
 * O script e idempotente: por padrao apenas simula; use --apply para gravar.
 * Os campos legados (diretriz_completa, complementar e fonte_oficial) nunca
 * sao apagados ou substituidos.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../conexaoMain.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$apply = in_array('--apply', $argv, true);

function alma_normalize_text(?string $text): string
{
    $text = str_replace(["\xC2\xA0", "\u{2028}"], ' ', (string) $text);
    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

function alma_slug(string $text): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    return trim((string) preg_replace('/[^a-z0-9]+/i', '_', strtolower($ascii)), '_');
}

/** @return list<array{label:string,value:string}> */
function alma_heading_segments(?string $text, array $labels): array
{
    $text = alma_normalize_text($text);
    if ($text === '' || !$labels) {
        return [];
    }
    usort($labels, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
    $alternation = implode('|', array_map(static fn (string $label): string => preg_quote($label, '/'), $labels));
    preg_match_all('/(?<![\p{L}\p{N}])(' . $alternation . ')(?=\s|:|$)/iu', $text, $matches, PREG_OFFSET_CAPTURE);
    $segments = [];
    $count = count($matches[0] ?? []);
    for ($index = 0; $index < $count; $index++) {
        $label = alma_normalize_text((string) $matches[1][$index][0]);
        $start = (int) $matches[0][$index][1] + strlen((string) $matches[0][$index][0]);
        $end = $index + 1 < $count ? (int) $matches[0][$index + 1][1] : strlen($text);
        $value = alma_normalize_text(trim(substr($text, $start, $end - $start), " :-"));
        if ($value !== '') {
            $segments[] = ['label' => $label, 'value' => $value];
        }
    }
    return $segments;
}

/** @return list<string> */
function alma_list_values(string $text): array
{
    $parts = preg_split('/\s*[✓✕●]\s*/u', $text) ?: [];
    $values = [];
    foreach ($parts as $part) {
        $part = alma_normalize_text($part);
        if ($part !== '') {
            $values[] = $part;
        }
    }
    return $values;
}

/** @return list<array{code:string,title:string,content:?string,entries:list<array{type:string,text:string}>}> */
function alma_directive_sections(?string $directive): array
{
    $text = alma_normalize_text($directive);
    if ($text === '') {
        return [];
    }
    preg_match_all(
        '/(Como [^?]+\?|O que define [^?]+\?|O que deve dominar [^?]+\?|O que deve ser percebido [^?]+\?|O que deve receber [^?]+?\?)/iu',
        $text,
        $matches,
        PREG_OFFSET_CAPTURE,
    );
    $sections = [];
    $questionMatches = $matches[1] ?? [];
    foreach (array_slice($questionMatches, 0, 2) as $index => $question) {
        $questionText = alma_normalize_text((string) $question[0]);
        $answerStart = (int) $question[1] + strlen((string) $question[0]);
        $nextQuestion = $questionMatches[$index + 1] ?? null;
        $answerEnd = $nextQuestion ? (int) $nextQuestion[1] : strlen($text);
        $answer = alma_normalize_text(substr($text, $answerStart, $answerEnd - $answerStart));
        if ($answer === '') {
            continue;
        }
        $lowerQuestion = mb_strtolower($questionText);
        if (str_starts_with($lowerQuestion, 'como ')) {
            $code = 'diretriz_sensacao';
            $title = 'Sensação desejada';
        } elseif (str_starts_with($lowerQuestion, 'o que define ')) {
            $code = 'diretriz_definicao';
            $title = 'Definição operacional';
        } else {
            $code = 'diretriz_percepcao';
            $title = 'Percepção dominante';
        }
        $sections[] = [
            'code' => $code,
            'title' => $title,
            'content' => $answer,
            'entries' => [],
        ];
    }
    return $sections;
}

/** @return list<array{code:string,title:string,content:?string,entries:list<array{type:string,text:string}>}> */
function alma_complementary_sections(string $dimensionCode, ?string $complementary): array
{
    $labelsByDimension = [
        'materialidade' => [
            'Superfícies e Pinturas', 'Elementos Naturais', 'Madeiras', 'Pedras', 'Metais', 'Tecidos', 'Vidros',
        ],
        'lifestyle' => [
            'Presença Humana', 'Quantidade de Pessoas', 'Tipos de Ação', 'Intensidade da Cena', 'Nível de Movimento',
        ],
        'composicao' => [
            'Número de Pontos de Interesse', 'Velocidade de Leitura', 'Relação Arquitetura × Lifestyle',
            'Recursos Compositivos Comuns', 'Aplicação na IMPROOV', 'Quando utilizar', 'Quando evitar', 'Protagonismo',
        ],
    ];
    $segments = alma_heading_segments($complementary, $labelsByDimension[$dimensionCode] ?? []);
    $sections = [];
    foreach ($segments as $segment) {
        $label = $segment['label'];
        $value = $segment['value'];
        $entries = [];
        $content = $value;
        if (preg_match('/\bPriorizar\b|\bEvitar\b/iu', $value)) {
            $content = null;
            preg_match_all('/\b(Priorizar|Evitar)\b/iu', $value, $matches, PREG_OFFSET_CAPTURE);
            $count = count($matches[0] ?? []);
            for ($index = 0; $index < $count; $index++) {
                $type = strtoupper((string) $matches[1][$index][0]);
                $start = (int) $matches[0][$index][1] + strlen((string) $matches[0][$index][0]);
                $end = $index + 1 < $count ? (int) $matches[0][$index + 1][1] : strlen($value);
                foreach (alma_list_values(substr($value, $start, $end - $start)) as $entry) {
                    $entries[] = ['type' => $type, 'text' => $entry];
                }
            }
        } elseif (preg_match('/[✓✕●]/u', $value)) {
            $content = null;
            $type = $label === 'Quando utilizar' ? 'UTILIZAR' : ($label === 'Quando evitar' ? 'EVITAR' : 'ITEM');
            foreach (alma_list_values($value) as $entry) {
                $entries[] = ['type' => $type, 'text' => $entry];
            }
        }
        $sections[] = [
            'code' => 'complementar_' . alma_slug($label),
            'title' => $label,
            'content' => $content,
            'entries' => $entries,
        ];
    }
    return $sections;
}

$conn = conectarBanco();
$conn->set_charset('utf8mb4');

$result = $conn->query(
    "SELECT i.id, d.codigo AS dimensao_codigo, i.diretriz_completa,
            i.diferenca_principal, c.conteudo AS complementar
       FROM alma_biblioteca_item i
       JOIN alma_biblioteca_dimensao d ON d.id = i.dimensao_id
       LEFT JOIN alma_biblioteca_item_secao c ON c.item_id = i.id AND c.codigo = 'complementar'
      ORDER BY i.id"
);

$stats = ['items' => 0, 'sections' => 0, 'entries' => 0, 'skipped' => 0];
$conn->begin_transaction();
try {
    while ($item = $result->fetch_assoc()) {
        $stats['items']++;
        $sectionOrder = 4;
        $cleanDifference = trim((string) preg_replace(
            '/\\s*Versão Resumida(?:\\s*\\(Exibida no Card\\))?\\s*/iu',
            ' ',
            (string) ($item['diferenca_principal'] ?? ''),
        ));
        if ($cleanDifference !== trim((string) ($item['diferenca_principal'] ?? ''))) {
            $itemId = (int) $item['id'];
            $stmt = $conn->prepare('UPDATE alma_biblioteca_item SET diferenca_principal = NULLIF(?, \'\') WHERE id = ?');
            $stmt->bind_param('si', $cleanDifference, $itemId);
            $stmt->execute();
            $stmt->close();
        }
        $sections = array_merge(
            alma_directive_sections($item['diretriz_completa'] ?? null),
            alma_complementary_sections((string) $item['dimensao_codigo'], $item['complementar'] ?? null),
        );
        foreach ($sections as $section) {
            $itemId = (int) $item['id'];
            $code = $section['code'];
            $stmt = $conn->prepare('SELECT id, conteudo FROM alma_biblioteca_item_secao WHERE item_id = ? AND codigo = ? LIMIT 1');
            $stmt->bind_param('is', $itemId, $code);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            if ($existing) {
                $sectionId = (int) $existing['id'];
                if ($section['content'] !== null && trim((string) ($existing['conteudo'] ?? '')) === '') {
                    $stmt = $conn->prepare('UPDATE alma_biblioteca_item_secao SET titulo = ?, conteudo = ? WHERE id = ?');
                    $stmt->bind_param('ssi', $section['title'], $section['content'], $sectionId);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                $order = $sectionOrder++;
                $stmt = $conn->prepare('INSERT INTO alma_biblioteca_item_secao (item_id, codigo, titulo, conteudo, ordem) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('isssi', $itemId, $code, $section['title'], $section['content'], $order);
                $stmt->execute();
                $sectionId = (int) $conn->insert_id;
                $stmt->close();
                $stats['sections']++;
            }
            if ($section['entries']) {
                $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM alma_biblioteca_secao_entrada WHERE secao_id = ?');
                $stmt->bind_param('i', $sectionId);
                $stmt->execute();
                $total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
                $stmt->close();
                if ($total === 0) {
                    foreach ($section['entries'] as $order => $entry) {
                        $entryOrder = $order + 1;
                        $stmt = $conn->prepare('INSERT INTO alma_biblioteca_secao_entrada (secao_id, tipo, texto, ordem) VALUES (?, ?, ?, ?)');
                        $stmt->bind_param('issi', $sectionId, $entry['type'], $entry['text'], $entryOrder);
                        $stmt->execute();
                        $stmt->close();
                        $stats['entries']++;
                    }
                }
            }
        }
        if ($sections) {
            $itemId = (int) $item['id'];
            $stmt = $conn->prepare("UPDATE alma_biblioteca_item_secao SET ordem = 90 WHERE item_id = ? AND codigo = 'complementar'");
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("UPDATE alma_biblioteca_item_secao SET ordem = 99 WHERE item_id = ? AND codigo = 'fonte_oficial'");
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $stmt->close();
        }
        if (!$sections) {
            $stats['skipped']++;
        }
    }
    if ($apply) {
        $conn->commit();
    } else {
        $conn->rollback();
    }
} catch (Throwable $error) {
    $conn->rollback();
    throw $error;
}
$conn->close();

$mode = $apply ? 'aplicado' : 'simulado (use --apply para gravar)';
printf(
    "Normalização %s: %d itens, %d seções novas, %d entradas novas, %d itens sem blocos detectáveis.\n",
    $mode,
    $stats['items'],
    $stats['sections'],
    $stats['entries'],
    $stats['skipped'],
);
