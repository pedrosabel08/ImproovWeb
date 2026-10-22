<?php
/** Authenticated project navigation; never a source of authorization. */
function flow_obra_id_from_query(array $query): ?int
{
    foreach (['obra_id', 'idobra', 'id'] as $key) {
        if (!array_key_exists($key, $query)) continue;
        $value = $query[$key];
        if (!is_string($value) && !is_int($value)) return 0;
        if (!preg_match('/^[0-9]+$/D', (string)$value)) return 0;
        $value = ltrim((string)$value, '0');
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? 0 : $id;
    }
    return null;
}

function flow_obra_context_scripts(?int $obraId): string
{
    $seed = json_encode([
        'obraId' => $obraId !== null ? (string)$obraId : null,
        'usuarioId' => (int)($_SESSION['idusuario'] ?? 0),
        'colaboradorId' => (int)($_SESSION['idcolaborador'] ?? 0),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    $src = flow_metadata_base_path() . '/Dashboard/obra-context.js?v=' . filemtime(__DIR__ . '/obra-context.js');
    return '<script type="application/json" id="flow-obra-context">' . $seed . '</script>'
        . '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>';
}
