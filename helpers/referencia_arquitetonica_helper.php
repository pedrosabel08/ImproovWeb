<?php

/** Origem superior esquerda: v1 relativa à página; v2 relativa à área útil. */
function mp_name(mixed $value): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException('Informe um nome válido.');
    }
    $name = trim($value);
    if ($name === '' || mb_strlen($name) > 100) {
        throw new InvalidArgumentException('Informe um nome com até 100 caracteres.');
    }
    return $name;
}

function mp_cross(array $a, array $b, array $c): float
{
    return ($b[0] - $a[0]) * ($c[1] - $a[1]) - ($b[1] - $a[1]) * ($c[0] - $a[0]);
}

function mp_on_segment(array $a, array $b, array $p): bool
{
    return abs(mp_cross($a, $b, $p)) < 1e-10
        && $p[0] >= min($a[0], $b[0]) - 1e-10 && $p[0] <= max($a[0], $b[0]) + 1e-10
        && $p[1] >= min($a[1], $b[1]) - 1e-10 && $p[1] <= max($a[1], $b[1]) + 1e-10;
}

function mp_segments_intersect(array $a, array $b, array $c, array $d): bool
{
    $abC = mp_cross($a, $b, $c);
    $abD = mp_cross($a, $b, $d);
    $cdA = mp_cross($c, $d, $a);
    $cdB = mp_cross($c, $d, $b);
    return (($abC > 0 && $abD < 0 || $abC < 0 && $abD > 0)
        && ($cdA > 0 && $cdB < 0 || $cdA < 0 && $cdB > 0))
        || mp_on_segment($a, $b, $c) || mp_on_segment($a, $b, $d)
        || mp_on_segment($c, $d, $a) || mp_on_segment($c, $d, $b);
}

function mp_geometry(mixed $geometry): array
{
    if (!is_array($geometry) || !(
        (($geometry['version'] ?? null) === 1 && ($geometry['coordinate_space'] ?? '') === 'normalized')
        || (($geometry['version'] ?? null) === 2 && ($geometry['coordinate_space'] ?? '') === 'useful_area_normalized')
        || (($geometry['version'] ?? null) === 3 && ($geometry['coordinate_space'] ?? '') === 'page_normalized')
    )) {
        throw new InvalidArgumentException('A demarcação precisa usar coordenadas normalizadas.');
    }
    $points = $geometry['points'] ?? null;
    if (!is_array($points) || !array_is_list($points) || count($points) < 3 || count($points) > 200) {
        throw new InvalidArgumentException('Desenhe um polígono com 3 a 200 pontos.');
    }
    foreach ($points as &$point) {
        if (!is_array($point) || !array_is_list($point) || count($point) !== 2) {
            throw new InvalidArgumentException('Ponto inválido na demarcação.');
        }
        foreach ($point as &$coordinate) {
            if ((!is_float($coordinate) && !is_int($coordinate)) || !is_finite((float) $coordinate) || $coordinate < 0 || $coordinate > 1) {
                throw new InvalidArgumentException('A demarcação deve ficar dentro da planta.');
            }
            $coordinate = round((float) $coordinate, 8);
        }
        unset($coordinate);
    }
    unset($point);
    $n = count($points);
    $area = 0;
    for ($i = 0; $i < $n; $i++) {
        $next = ($i + 1) % $n;
        if (hypot($points[$i][0] - $points[$next][0], $points[$i][1] - $points[$next][1]) < 1e-8) {
            throw new InvalidArgumentException('Remova pontos repetidos da demarcação.');
        }
        // Collinear reversal makes adjacent edges overlap.
        $previous = ($i + $n - 1) % $n;
        if (abs(mp_cross($points[$previous], $points[$i], $points[$next])) < 1e-10
            && mp_on_segment($points[$previous], $points[$i], $points[$next])) {
            throw new InvalidArgumentException('O contorno não pode voltar sobre o mesmo segmento.');
        }
        $area += $points[$i][0] * $points[$next][1] - $points[$next][0] * $points[$i][1];
        for ($j = $i + 1; $j < $n; $j++) {
            if ($j === $next || ($i === 0 && $j === $n - 1)) {
                continue;
            }
            if (mp_segments_intersect($points[$i], $points[$next], $points[$j], $points[($j + 1) % $n])) {
                throw new InvalidArgumentException('O contorno do ambiente não pode cruzar a si mesmo.');
            }
        }
    }
    if (abs($area) < 1e-8) {
        throw new InvalidArgumentException('A demarcação precisa formar uma área.');
    }
    return ['version' => $geometry['version'], 'coordinate_space' => $geometry['coordinate_space'], 'points' => $points];
}

function mp_crop(mixed $value): ?array
{
    if ($value === null) return null;
    if (is_array($value) && isset($value['points'])) {
        $area = mp_geometry($value);
        if ($area['version'] !== 3) throw new InvalidArgumentException('Área útil deve ser relativa à página.');
        return $area;
    }
    if (!is_array($value)) throw new InvalidArgumentException('Área útil inválida.');
    $crop = [];
    foreach (['x', 'y', 'width', 'height'] as $key) {
        $number = $value[$key] ?? null;
        if ((!is_int($number) && !is_float($number)) || !is_finite((float) $number) || $number < 0 || $number > 1) {
            throw new InvalidArgumentException('A área útil deve usar coordenadas normalizadas da página.');
        }
        $crop[$key] = round((float) $number, 8);
    }
    if ($crop['width'] < .0001 || $crop['height'] < .0001
        || $crop['x'] + $crop['width'] > 1 + 1e-8 || $crop['y'] + $crop['height'] > 1 + 1e-8) {
        throw new InvalidArgumentException('A área útil deve formar um retângulo dentro da página.');
    }
    // Página inteira equivale a ausência de recorte.
    if ($crop == ['x' => 0, 'y' => 0, 'width' => 1, 'height' => 1]) return null;
    return ['version'=>3, 'coordinate_space'=>'page_normalized', 'points'=>[
        [$crop['x'],$crop['y']],[$crop['x']+$crop['width'],$crop['y']],
        [$crop['x']+$crop['width'],$crop['y']+$crop['height']],[$crop['x'],$crop['y']+$crop['height']],
    ]];
}

function mp_crop_bounds(?array $crop): array
{
    if (!$crop) return ['x'=>0.,'y'=>0.,'width'=>1.,'height'=>1.];
    if (!isset($crop['points'])) return $crop; // retângulo legado
    $xs=array_column($crop['points'],0); $ys=array_column($crop['points'],1);
    return ['x'=>min($xs),'y'=>min($ys),'width'=>max($xs)-min($xs),'height'=>max($ys)-min($ys)];
}

function mp_point_in_polygon(array $point, array $polygon): bool
{
    $inside=false; $n=count($polygon);
    for($i=0,$j=$n-1;$i<$n;$j=$i++) {
        $a=$polygon[$j];$b=$polygon[$i];
        if (mp_on_segment($a,$b,$point)) return true;
        if (($a[1]>$point[1])!==($b[1]>$point[1]) && $point[0]<($b[0]-$a[0])*($point[1]-$a[1])/($b[1]-$a[1])+$a[0]) $inside=!$inside;
    }
    return $inside;
}

function mp_geometry_inside_area(array $geometry, ?array $area): void
{
    if (!$area) return;
    $area=mp_crop($area); if (!$area) return;
    $polygon=$area['points']; $bounds=mp_crop_bounds($area);
    $points=array_map(fn($p)=>[$bounds['x']+$p[0]*$bounds['width'],$bounds['y']+$p[1]*$bounds['height']],$geometry['points']);
    foreach($points as $index=>$a) {
        $b=$points[($index+1)%count($points)];
        if (!mp_point_in_polygon($a,$polygon)) throw new InvalidArgumentException('A área útil precisa conter os ambientes. Ajuste o contorno.');
        // Divide também nos vértices tocados: só testar os pontos não basta em áreas côncavas.
        $ts=[0.,1.]; $dx=$b[0]-$a[0]; $dy=$b[1]-$a[1];
        for($i=0;$i<count($polygon);$i++) {
            $p=$polygon[$i];$q=$polygon[($i+1)%count($polygon)];
            $ex=$q[0]-$p[0];$ey=$q[1]-$p[1];$den=$dx*$ey-$dy*$ex;
            if (abs($den)>1e-12) {
                $t=(($p[0]-$a[0])*$ey-($p[1]-$a[1])*$ex)/$den;
                $u=(($p[0]-$a[0])*$dy-($p[1]-$a[1])*$dx)/$den;
                if ($t>0 && $t<1 && $u>=-1e-10 && $u<=1+1e-10) $ts[]=$t;
            } elseif (mp_on_segment($a,$b,$p)) {
                $ts[]=abs($dx)>abs($dy)?($p[0]-$a[0])/$dx:($p[1]-$a[1])/$dy;
            }
        }
        sort($ts);
        for($i=1;$i<count($ts);$i++) {
            $t=($ts[$i-1]+$ts[$i])/2;
            if (!mp_point_in_polygon([$a[0]+$dx*$t,$a[1]+$dy*$t],$polygon)) throw new InvalidArgumentException('O contorno do ambiente deve ficar dentro da área útil.');
        }
    }
}

/** Preserva o ponto físico na página, inclusive para demarcações legadas v1. */
function mp_geometry_rebase(array $geometry, ?array $oldCrop, ?array $newCrop): array
{
    $geometry = mp_geometry($geometry);
    $old = mp_crop_bounds($geometry['version'] !== 2 ? null : $oldCrop);
    $new = mp_crop_bounds($newCrop);
    $points = [];
    foreach ($geometry['points'] as [$x, $y]) {
        $px = ($old['x'] + $x * $old['width'] - $new['x']) / $new['width'];
        $py = ($old['y'] + $y * $old['height'] - $new['y']) / $new['height'];
        if ($px < -1e-7 || $px > 1 + 1e-7 || $py < -1e-7 || $py > 1 + 1e-7) {
            throw new InvalidArgumentException('A área útil precisa conter todos os ambientes existentes. Amplie o recorte.');
        }
        $points[] = [max(0, min(1, $px)), max(0, min(1, $py))];
    }
    $result=mp_geometry(['version' => 2, 'coordinate_space' => 'useful_area_normalized', 'points' => $points]);
    mp_geometry_inside_area($result,$newCrop);
    return $result;
}

function mp_layers(mixed $value): array
{
    if (!is_array($value) || ($value['version'] ?? null) !== 1 || !is_array($value['groups'] ?? null)
        || !array_is_list($value['groups']) || count($value['groups']) > 2000) {
        throw new InvalidArgumentException('Configuração de camadas inválida.');
    }
    $groups = [];
    $seen = [];
    foreach ($value['groups'] as $group) {
        if (!is_array($group) || !is_string($group['id'] ?? null) || $group['id'] === '' || strlen($group['id']) > 100
            || !is_string($group['name'] ?? null) || mb_strlen($group['name']) > 1000
            || !is_bool($group['visible'] ?? null) || isset($seen[$group['id']])) {
            throw new InvalidArgumentException('Camada inválida ou duplicada.');
        }
        $seen[$group['id']] = true;
        $groups[] = ['id' => $group['id'], 'name' => $group['name'], 'visible' => $group['visible']];
    }
    return ['version' => 1, 'groups' => $groups];
}

function mp_reference_config(array $reference): array
{
    $reference['area_util'] = $reference['area_util_json'] === null ? null : mp_crop(json_decode($reference['area_util_json'], true, 64, JSON_THROW_ON_ERROR));
    $reference['layers'] = $reference['layers_json'] === null ? null : mp_layers(json_decode($reference['layers_json'], true, 64, JSON_THROW_ON_ERROR));
    $reference['has_layers'] = $reference['has_layers'] === null ? null : (bool) $reference['has_layers'];
    $reference['versao_config'] = (int) $reference['versao_config'];
    unset($reference['area_util_json'], $reference['layers_json'], $reference['legado_json']);
    return $reference;
}

function mp_save_reference_config(mysqli $conn, int $obraId, array $input): void
{
    if (!array_key_exists('area_util', $input)) throw new InvalidArgumentException('Informe a configuração completa da referência.');
    $crop = mp_crop($input['area_util'] ?? null);
    $layers = mp_layers($input['layers'] ?? null);
    $subtypeId = (int) ($input['subtipo_id'] ?? 0);
    $referenceId = (int) ($input['referencia_id'] ?? 0);
    $conn->begin_transaction();
    try {
        $reference = mp_map_context($conn, $referenceId, $obraId, $subtypeId, true);
        if (!$reference) throw new RuntimeException('Referência não encontrada neste pavimento.', 404);
        $reference = mp_reference_config($reference);
        if ($reference['versao_config'] !== (int) ($input['versao_config'] ?? 0)) {
            throw new RuntimeException('A referência foi alterada. Atualize a página antes de salvar.', 409);
        }
        if ($reference['layers'] !== null) {
            $catalog = fn(array $config) => array_column($config['groups'], 'name', 'id');
            if ($catalog($reference['layers']) != $catalog($layers)) {
                throw new InvalidArgumentException('As camadas devem corresponder ao PDF original. Atualize a página.');
            }
        }
        $demarcations = mp_rows($conn, 'SELECT d.id, d.ambiente_id, d.geometria_json FROM ambiente_demarcacoes d JOIN ambientes a ON a.id = d.ambiente_id WHERE d.referencia_id = ? AND a.referencia_id = ? FOR UPDATE', 'ii', [$referenceId, $referenceId]);
        foreach ($demarcations as $demarcation) {
            $old = json_decode($demarcation['geometria_json'], true, 64, JSON_THROW_ON_ERROR);
            $new = mp_geometry_rebase($old, $reference['area_util'], $crop);
            if ($new != $old) {
                mp_query($conn, 'UPDATE ambiente_demarcacoes SET geometria_json = ? WHERE id = ?', 'si', [json_encode($new, JSON_THROW_ON_ERROR), $demarcation['id']])->close();
                mp_query($conn, 'UPDATE ambientes SET versao_edicao = versao_edicao + 1 WHERE id = ?', 'i', [$demarcation['ambiente_id']])->close();
            }
        }
        mp_query($conn, 'UPDATE referencias_arquitetonicas SET area_util_json = ?, layers_json = ?, has_layers = ?, versao_config = versao_config + 1, atualizado_em = CURRENT_TIMESTAMP WHERE id = ?', 'ssii', [
            $crop === null ? null : json_encode($crop, JSON_THROW_ON_ERROR),
            json_encode($layers, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), (int) (count($layers['groups']) > 0), $referenceId,
        ])->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function mp_query(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function mp_row(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $stmt = mp_query($conn, $sql, $types, $params);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

function mp_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mp_query($conn, $sql, $types, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function mp_require_obra(mysqli $conn, int $obraId): void
{
    static $allowed = null;
    if ($allowed === null) {
        $allowed = array_map('intval', array_column(obterObras($conn), 'idobra'));
    }
    if ($obraId <= 0 || !in_array($obraId, $allowed, true)) {
        throw new RuntimeException('Obra indisponível para este usuário.', 403);
    }
}

function mp_subtype(mysqli $conn, int $id): array
{
    $row=mp_row($conn,'SELECT id,nome FROM subtipo_imagem WHERE id=?','i',[$id]);
    if (!$row) throw new RuntimeException('Subtipo não encontrado.',404);
    return $row;
}
function mp_map_context(mysqli $conn, int $id, int $obra, int $subtype, bool $lock=false): array
{
    if ($subtype<=0) throw new InvalidArgumentException('Selecione o subtipo antes de configurar.');
    $row=mp_row($conn,'SELECT * FROM referencias_arquitetonicas WHERE id=? AND obra_id=? AND subtipo_id=?'.($lock?' FOR UPDATE':''),'iii',[$id,$obra,$subtype]);
    if (!$row) throw new RuntimeException('Mapa não encontrado neste subtipo da obra.',404);
    if (!$row['documento_id']) throw new RuntimeException('Vincule uma página de PDF antes de configurar este mapa.',409);
    return $row;
}

function mp_image_ids(mixed $values): array
{
    if (!is_array($values) || !array_is_list($values) || count($values) > 1000) {
        throw new InvalidArgumentException('Seleção de imagens inválida.');
    }
    foreach ($values as $value) {
        if (!is_int($value) || $value <= 0) {
            throw new InvalidArgumentException('Imagem inválida.');
        }
    }
    return array_values(array_unique($values));
}

function mp_save_environment(mysqli $conn, int $obraId, array $input, int $author): int
{
    $name = mp_name($input['nome'] ?? null);
    $geometry = mp_geometry($input['geometria'] ?? null);
    $imageIds = mp_image_ids($input['imagem_ids'] ?? []);
    $subtypeId = (int) ($input['subtipo_id'] ?? 0);
    $referenceId = (int) ($input['referencia_id'] ?? 0);
    $id = (int) ($input['id'] ?? 0);
    $conn->begin_transaction();
    try {
        $ref = mp_map_context($conn, $referenceId, $obraId, $subtypeId, true);
        if (!$ref) {
            throw new RuntimeException('Referência não encontrada neste pavimento.', 404);
        }
        if ((int) $ref['versao_config'] !== (int) ($input['versao_referencia'] ?? 0)) {
            throw new RuntimeException('A referência foi alterada. Atualize a página antes de demarcar.', 409);
        }
        $crop = $ref['area_util_json'] === null ? null : json_decode($ref['area_util_json'], true, 64, JSON_THROW_ON_ERROR);
        $geometry = json_encode(mp_geometry_rebase($geometry, $crop, $crop), JSON_THROW_ON_ERROR);
        if ($imageIds) {
            $marks = implode(',', array_fill(0, count($imageIds), '?'));
            $found = mp_rows($conn, "SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE obra_id = ? AND idimagens_cliente_obra IN ($marks)", 'i' . str_repeat('i', count($imageIds)), array_merge([$obraId], $imageIds));
            if (count($found) !== count($imageIds)) {
                throw new InvalidArgumentException('Selecione somente imagens desta obra.');
            }
        }
        if ($id > 0) {
            $current = mp_row($conn, 'SELECT versao_edicao FROM ambientes WHERE id = ? AND referencia_id = ? FOR UPDATE', 'ii', [$id, $referenceId]);
            if (!$current) {
                throw new RuntimeException('Ambiente não encontrado neste pavimento.', 404);
            }
            if ((int) $current['versao_edicao'] !== (int) ($input['versao_edicao'] ?? 0)) {
                throw new RuntimeException('Este ambiente foi alterado por outra pessoa. Atualize a página antes de salvar.', 409);
            }
            mp_query($conn, 'UPDATE ambientes SET nome = ?, versao_edicao = versao_edicao + 1 WHERE id = ?', 'si', [$name, $id])->close();
            mp_query($conn, 'UPDATE ambiente_demarcacoes SET geometria_json = ? WHERE ambiente_id = ? AND referencia_id = ?', 'sii', [$geometry, $id, $referenceId])->close();
        } else {
            mp_query($conn, 'INSERT INTO ambientes (referencia_id, nome, criado_por) VALUES (?, ?, ?)', 'isi', [$referenceId, $name, $author])->close();
            $id = (int) $conn->insert_id;
            mp_query($conn, 'INSERT INTO ambiente_demarcacoes (ambiente_id, referencia_id, geometria_json) VALUES (?, ?, ?)', 'iis', [$id, $referenceId, $geometry])->close();
        }
        mp_query($conn, 'DELETE FROM ambiente_imagens WHERE ambiente_id = ?', 'i', [$id])->close();
        foreach ($imageIds as $imageId) {
            mp_query($conn, 'INSERT INTO ambiente_imagens (ambiente_id, imagem_id) VALUES (?, ?)', 'ii', [$id, $imageId])->close();
        }
        $conn->commit();
        return $id;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
