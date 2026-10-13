<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../helpers/referencia_arquitetonica_helper.php';
require_once __DIR__ . '/../helpers/pdf_reference_helper.php';
require_once __DIR__ . '/../vendor/autoload.php';
function check(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException($label);
}
function invalid(callable $operation, string $label): void {
    try { $operation(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Deveria recusar: ' . $label);
}
function geometry(array $points): array { return ['version' => 1, 'coordinate_space' => 'normalized', 'points' => $points]; }
$concave = geometry([[0,0],[1,0],[1,1],[.5,.5],[0,1]]);
check(mp_geometry($concave)['points'] == $concave['points'], 'Polígono côncavo e limites 0/1');
invalid(fn() => mp_geometry(geometry([[0,0],[1,1],[0,1],[1,0]])), 'autocruzamento');
invalid(fn() => mp_geometry(geometry([[0,0],[.5,.5],[1,1]])), 'área nula');
invalid(fn() => mp_geometry(geometry([[0,0],[1.1,0],[0,1]])), 'fora da página');
invalid(fn() => mp_geometry(geometry([[0,0],['1',0],[0,1]])), 'string numérica');
invalid(fn() => mp_geometry(geometry([[0,0],[INF,0],[0,1]])), 'infinito');
invalid(fn() => mp_geometry(geometry([[0,0],[1,0],[1,0],[0,1]])), 'vértice repetido');
invalid(fn() => mp_geometry(geometry([[0,0],[1,0],[.5,0],[1,1],[0,1]])), 'segmentos adjacentes sobrepostos');
invalid(fn() => mp_geometry(['points' => [[0,0],[1,0],[0,1]]]), 'convenção ausente');
invalid(fn() => mp_name(str_repeat('á', 101)), 'nome longo');
check(mp_image_ids([1,2,1]) === [1,2], 'vínculos sem duplicidade');
invalid(fn() => mp_image_ids(['1']), 'ID inválido');
$files = [];
try {
    foreach ([1,2] as $pages) {
        $file = tempnam(sys_get_temp_dir(), 'mp_pdf_'); $files[] = $file;
        $pdf = new FPDF();
        for ($i=0;$i<$pages;$i++) { $pdf->AddPage(); $pdf->SetFont('Arial','',12); $pdf->Text(20,20,'Planta de teste'); }
        $pdf->Output('F', $file);
        if ($pages === 1) {
            $meta = mp_pdf_metadata($file); check($meta['pages'] === 1 && $meta['width'] > 0 && $meta['height'] > 0, 'PDF uma página');
        } else check(mp_pdf_metadata($file)['pages'] === 2, 'PDF multipágina aceito na Fase 2');
    }
    $text = tempnam(sys_get_temp_dir(), 'mp_fake_'); $files[] = $text; file_put_contents($text, 'Não é PDF');
    invalid(fn() => mp_pdf_metadata($text), 'arquivo falso');
    echo "OK: geometria, nomes, vínculos e validação real de PDF.\n";
} finally { foreach ($files as $file) if (is_file($file)) unlink($file); }
