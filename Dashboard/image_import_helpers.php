<?php

require_once __DIR__ . '/planned_function_helpers.php';

function dashboard_remove_accents(string $value): string
{
    if ($value === '') {
        return $value;
    }

    if (function_exists('transliterator_transliterate')) {
        $translated = @transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $value);
        if (is_string($translated) && $translated !== '') {
            return $translated;
        }
    }

    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($value, Normalizer::FORM_D);
        if (is_string($normalized) && $normalized !== '') {
            $normalized = preg_replace('/\p{Mn}+/u', '', $normalized);
            $recomposed = Normalizer::normalize($normalized, Normalizer::FORM_C);
            if (is_string($recomposed) && $recomposed !== '') {
                return $recomposed;
            }
            return $normalized;
        }
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false && $converted !== '') {
            return $converted;
        }
    }

    $map = [
        'Á' => 'A',
        'À' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'A',
        'á' => 'a',
        'à' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'a',
        'É' => 'E',
        'È' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'é' => 'e',
        'è' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'Í' => 'I',
        'Ì' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'í' => 'i',
        'ì' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'Ó' => 'O',
        'Ò' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'O',
        'ó' => 'o',
        'ò' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        'Ú' => 'U',
        'Ù' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'ú' => 'u',
        'ù' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'Ç' => 'C',
        'ç' => 'c',
    ];

    return strtr($value, $map);
}

function dashboard_sanitize_text(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = dashboard_remove_accents($value);
    $value = str_replace('/', '-', $value);
    $value = preg_replace('/[^A-Za-z0-9 \._\-]/', '', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    $value = preg_replace('/_+/', '_', $value);
    $value = preg_replace('/\s*_\s*/', '_', $value);

    return trim((string) $value);
}

function dashboard_normalize_for_search(string $value): string
{
    return dashboard_remove_accents(mb_strtolower($value, 'UTF-8'));
}

function dashboard_detect_tipo_imagem(string $imageName): string
{
    if (trim($imageName) === '') {
        return '';
    }

    $normalized = dashboard_normalize_for_search($imageName);

    if (strpos($normalized, 'planta humanizada') !== false) {
        return 'Planta Humanizada';
    }
    if (strpos($normalized, 'piscina aquecida') !== false) {
        return 'Imagem Interna';
    }

    foreach (['fotomontagem', 'fachada', 'embasamento', 'foto insercao'] as $keyword) {
        if (strpos($normalized, $keyword) !== false) {
            return 'Fachada';
        }
    }

    foreach (['living', 'suite', 'suíte', 'teraco', 'terraço', 'duplex', 'quarto', 'sacada', 'varanda', 'apartamentos'] as $keyword) {
        $keywordNormalized = dashboard_normalize_for_search($keyword);
        if (strpos($normalized, $keywordNormalized) !== false) {
            return 'Unidade';
        }
    }

    foreach (['academia', 'hall de entrada', 'salao de jogos', 'salon de jogos', 'salao de festas', 'salon de festas', 'saloes de festas', 'festas', 'jogos', 'coworking', 'lavanderia', 'gourmet', 'interno', 'grill', 'garagem', 'brinquedoteca', 'bistro', 'cinema', 'sauna', 'sala de massagem', 'espaco kids', 'pizza', 'grab and go', 'bwc', 'home market', 'lobby', 'espaço pet', 'fitness', 'espaco pet', 'pub', 'sports bar', 'spa', 'boate', 'salao de beleza', 'beach point'] as $keyword) {
        if (strpos($normalized, $keyword) !== false) {
            return 'Imagem Interna';
        }
    }

    foreach (['piscina', 'playground', 'externo', 'quadra', 'lazer', 'fire place', 'carwash', 'ofuros'] as $keyword) {
        if (strpos($normalized, $keyword) !== false) {
            return 'Imagem Externa';
        }
    }

    return '';
}

function dashboard_format_image_name(string $rawName, string $nomenclatura): string
{
    $rawName = trim($rawName);
    if (preg_match('/^(.*)\(([^)]*)\)\s*$/u', $rawName, $matches)) {
        $prefix = trim($matches[1]);
        $inside = trim($matches[2]);
        $rawName = $inside !== '' ? ($prefix !== '' ? ($prefix . ' - ' . $inside) : $inside) : $prefix;
    }

    $nomenclatura = dashboard_sanitize_text($nomenclatura);
    if ($nomenclatura === '') {
        return dashboard_sanitize_text($rawName);
    }

    if (preg_match('/^(\d+\.)\s*(.*)$/', $rawName, $matches)) {
        $prefix = $matches[1];
        $rest = dashboard_sanitize_text($matches[2]);
        return $rest !== '' ? $prefix . $nomenclatura . ' ' . $rest : $prefix . $nomenclatura;
    }

    $rest = dashboard_sanitize_text($rawName);
    return $rest !== '' ? $nomenclatura . ' ' . $rest : $nomenclatura;
}

function dashboard_prepare_image_entries(array $rawEntries, string $nomenclatura): array
{
    $entries = [];
    $duplicates = [];
    $errors = [];
    $seen = [];

    foreach (array_values($rawEntries) as $index => $rawEntry) {
        $lineNumber = $index + 1;
        $rawName = trim((string) $rawEntry);
        if ($rawName === '') {
            $errors[] = ['linha' => $lineNumber, 'erro' => 'Nome de imagem vazio'];
            continue;
        }

        $formattedName = dashboard_format_image_name($rawName, $nomenclatura);
        if ($formattedName === '') {
            $errors[] = ['linha' => $lineNumber, 'erro' => 'Nome de imagem inválido'];
            continue;
        }

        $key = dashboard_normalize_for_search($formattedName);
        if (isset($seen[$key])) {
            $duplicates[] = ['linha' => $lineNumber, 'nome' => $formattedName];
            continue;
        }

        $seen[$key] = true;
        $type = dashboard_detect_tipo_imagem($formattedName);
        $entries[] = [
            'imagem_nome' => $formattedName,
            'tipo_imagem' => $type !== '' ? $type : 'Desconhecido',
        ];
    }

    return [
        'entries' => $entries,
        'duplicates' => $duplicates,
        'errors' => $errors,
    ];
}

function dashboard_insert_image_entries(mysqli $conn, int $clienteId, int $obraId, array $entries): array
{
    $sql = "INSERT INTO imagens_cliente_obra (cliente_id, obra_id, imagem_nome, recebimento_arquivos, data_inicio, prazo, tipo_imagem, antecipada, animacao, clima, dias_trabalhados)
            VALUES (?, ?, ?, NULL, NULL, NULL, ?, 0, 0, '', 0)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar insert de imagens: ' . $conn->error);
    }

    $inserted = 0;
    $plannedInserted = 0;
    $errors = [];
    foreach ($entries as $index => $entry) {
        $imageName = (string) ($entry['imagem_nome'] ?? '');
        $imageType = (string) ($entry['tipo_imagem'] ?? 'Desconhecido');
        $stmt->bind_param('iiss', $clienteId, $obraId, $imageName, $imageType);
        if (!$stmt->execute()) {
            $errors[] = ['linha' => $index + 1, 'erro' => $stmt->error, 'nome' => $imageName];
            continue;
        }

        $imageId = (int) $conn->insert_id;
        $planning = dashboard_insert_planned_functions_for_image($conn, $imageId, $imageType);
        if (!$planning['success']) {
            $errors[] = [
                'linha' => $index + 1,
                'erro' => 'Falha ao gerar planejamento: ' . (string) ($planning['message'] ?? $planning['reason'] ?? 'erro desconhecido'),
                'nome' => $imageName,
            ];
        } else {
            $plannedInserted += (int) ($planning['inserted'] ?? 0);
        }

        $inserted++;
    }

    $stmt->close();

    return ['inserted' => $inserted, 'planned_inserted' => $plannedInserted, 'errors' => $errors];
}
