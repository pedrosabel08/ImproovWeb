<?php
include '../conexao.php';
header('Content-Type: application/json');

$axes = [];

$sqlAxis = "SELECT id, nome, slug FROM flow_ref_axis WHERE ativo=1 ORDER BY ordem ASC, nome ASC";
$resAxis = $conn->query($sqlAxis);
if ($resAxis) {
    while ($a = $resAxis->fetch_assoc()) {
        $axes[] = [
            'id' => (int)$a['id'],
            'nome' => $a['nome'],
            'slug' => $a['slug'],
            'categories' => []
        ];
    }
}

// preload categories
$categoriesByAxis = [];
$resCat = $conn->query("SELECT id, axis_id, nome, slug FROM flow_ref_category WHERE ativo=1 ORDER BY ordem ASC, nome ASC");
if ($resCat) {
    while ($c = $resCat->fetch_assoc()) {
        $axisId = (int)$c['axis_id'];
        if (!isset($categoriesByAxis[$axisId])) $categoriesByAxis[$axisId] = [];
        $categoriesByAxis[$axisId][] = [
            'id' => (int)$c['id'],
            'nome' => $c['nome'],
            'slug' => $c['slug'],
            'subcategories' => []
        ];
    }
}

// preload subcategories
$subsByCategory = [];
$resSub = $conn->query("SELECT id, category_id, nome, slug, tipo_label, allowed_exts_json FROM flow_ref_subcategory WHERE ativo=1 ORDER BY ordem ASC, nome ASC");
if ($resSub) {
    while ($s = $resSub->fetch_assoc()) {
        $categoryId = (int)$s['category_id'];
        if (!isset($subsByCategory[$categoryId])) $subsByCategory[$categoryId] = [];

        $allowed = [];
        $decoded = json_decode($s['allowed_exts_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $ext) {
                $ext = strtolower(trim((string)$ext));
                if ($ext !== '') $allowed[] = $ext;
            }
        }

        $subsByCategory[$categoryId][] = [
            'id' => (int)$s['id'],
            'nome' => $s['nome'],
            'slug' => $s['slug'],
            'tipo_label' => $s['tipo_label'],
            'allowed_exts' => $allowed
        ];
    }
}

// stitch together
foreach ($axes as &$axis) {
    $axisId = (int)$axis['id'];
    $axisCats = $categoriesByAxis[$axisId] ?? [];

    foreach ($axisCats as &$cat) {
        $catId = (int)$cat['id'];
        $cat['subcategories'] = $subsByCategory[$catId] ?? [];
    }

    $axis['categories'] = $axisCats;
}

echo json_encode(['axes' => $axes]);
$conn->close();
