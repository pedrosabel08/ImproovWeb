<?php
// Fixtures sintéticas para QA do visualizador, sem documentos de clientes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$directory = __DIR__ . '/../tmp/mapa-pavimento-qa';
if (!is_dir($directory)) mkdir($directory, 0755, true);
foreach (['layers', 'sem-layers', 'rotacionado', 'multipaginas'] as $variant) {
    $layered = $variant !== 'sem-layers';
    $stream = "q\n/OC /Architecture BDC\n0.8 0.1 0.1 RG 3 w 80 80 420 240 re S 280 80 m 280 320 l S\nBT /F1 16 Tf 0 0 0 rg 100 290 Td (Planta principal) Tj ET\nEMC\n/OC /Dimensions BDC\n0.1 0.2 0.9 RG 2 w 60 60 460 280 re S\nBT /F1 14 Tf 0 0 0 rg 30 365 Td (Cotas e legenda) Tj ET\nEMC\n/OC /Furniture BDC\n0.1 0.7 0.2 rg 180 150 60 70 re f\nEMC\nQ\n";
    if (!$layered) $stream = preg_replace('~/OC /\w+ BDC\n|EMC\n~', '', $stream);
    if ($variant === 'multipaginas') $stream = str_replace('280 320 l S', '280 320 l S 200 120 m 200 280 l S', $stream);
    $oc = $layered ? ' /OCProperties << /OCGs [6 0 R 7 0 R 8 0 R] /D << /Order [6 0 R 7 0 R 8 0 R] /BaseState /ON /OFF [8 0 R] >> >>' : '';
    $rotation = $variant === 'rotacionado' ? ' /Rotate 90 /CropBox [20 20 580 380]' : '';
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R' . $oc . ' >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 600 400]' . $rotation . ' /Resources << /Font << /F1 4 0 R >> /Properties << /Architecture 6 0 R /Dimensions 7 0 R /Furniture 8 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . 'endstream',
        '<< /Type /OCG /Name (Arquitetura) >>',
        '<< /Type /OCG /Name (Cotas) >>',
        '<< /Type /OCG /Name <FEFF004D006F00620069006C006900E100720069006F> >>',
    ];
    if ($variant === 'multipaginas') {
        $objects[1] = '<< /Type /Pages /Kids [3 0 R 9 0 R 11 0 R] /Count 3 >>';
        foreach ([2,3] as $pageNumber) {
            $contentId = count($objects) + 2;
            $objects[] = str_replace('/Contents 5 0 R', '/Contents '.$contentId.' 0 R', $objects[2]);
            $pageStream = str_replace('Planta principal', 'Pavimento '.$pageNumber, $stream);
            $pageStream .= "q 0.9 0.7 0.1 rg ".($pageNumber * 60)." 100 50 100 re f Q\n";
            $objects[] = '<< /Length '.strlen($pageStream).">>\nstream\n".$pageStream.'endstream';
        }
    }
    $pdf = "%PDF-1.5\n"; $offsets = [0];
    foreach ($objects as $i => $object) {
        $offsets[] = strlen($pdf); $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $objectCount = count($objects) + 1;
    $xref = strlen($pdf); $pdf .= "xref\n0 $objectCount\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset);
    $pdf .= "trailer\n<< /Size $objectCount /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    file_put_contents($directory . '/preparacao-' . $variant . '.pdf', $pdf);
}
echo "Fixtures PDF de camadas, sem camadas e rotação geradas em tmp/mapa-pavimento-qa.\n";
