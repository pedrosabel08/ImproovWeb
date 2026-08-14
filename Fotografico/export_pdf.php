<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/fotografico_service.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function foto_pdf_fail(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function foto_pdf_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function foto_pdf_height(array $point): string
{
    if (($point['altura_m'] ?? null) !== null && (float) $point['altura_m'] > 0) {
        return number_format((float) $point['altura_m'], 2, ',', '.') . ' m';
    }
    return trim((string) ($point['altura'] ?? '')) ?: 'Não definida';
}

/**
 * O Dompdf não posiciona de forma confiável elementos absolutos sobre uma
 * imagem com altura automática. Por isso, a planta e seus marcadores são
 * compostos em um único SVG antes da renderização do PDF.
 */
function foto_pdf_map_with_markers(string $path, string $mime, array $points): ?string
{
    $size = @getimagesize($path);
    $image = @file_get_contents($path);
    if (!$size || !$image || (int) $size[0] <= 0 || (int) $size[1] <= 0) {
        return null;
    }

    $width = (int) $size[0];
    $height = (int) $size[1];
    $radius = max(12, min(28, (int) round(min($width, $height) * 0.025)));
    $fontSize = max(8, min(13, (int) round($radius * 0.72)));
    $markers = '';
    foreach ($points as $point) {
        $x = $width * max(0, min(100, (float) ($point['x_percentual'] ?? 0))) / 100;
        $y = $height * max(0, min(100, (float) ($point['y_percentual'] ?? 0))) / 100;
        $code = trim((string) ($point['codigo'] ?? '')) ?: 'Ponto';
        $markerWidth = max($radius * 2, (int) ceil(strlen($code) * $fontSize * 0.72) + 12);
        $markers .= '<g><rect x="' . number_format($x - ($markerWidth / 2), 2, '.', '') . '" y="' . number_format($y - $radius, 2, '.', '') . '" width="' . $markerWidth . '" height="' . ($radius * 2) . '" rx="' . $radius . '" fill="#215da8" stroke="#ffffff" stroke-width="3"/><text x="' . number_format($x, 2, '.', '') . '" y="' . number_format($y + ($fontSize * 0.34), 2, '.', '') . '" text-anchor="middle" fill="#ffffff" font-family="DejaVu Sans, sans-serif" font-size="' . $fontSize . '" font-weight="bold">' . foto_pdf_escape($code) . '</text></g>';
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '"><image x="0" y="0" width="' . $width . '" height="' . $height . '" xlink:href="data:' . foto_pdf_escape($mime) . ';base64,' . base64_encode($image) . '"/>' . $markers . '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    foto_pdf_fail(401, 'Sessão expirada.');
}
if (!fotografico_schema_ready($conn)) {
    foto_pdf_fail(503, 'A migration do módulo Fotográfico ainda não foi aplicada.');
}

$planId = (int) ($_GET['plano_id'] ?? 0);
if ($planId <= 0) {
    foto_pdf_fail(400, 'Plano fotográfico inválido.');
}

$stmt = $conn->prepare(
    "SELECT p.*, o.nome_obra, o.nome_completo, o.nomenclatura, c.nome_cliente AS cliente, o.local,
            rp.nome_colaborador AS responsavel_plano_nome,
            re.nome_colaborador AS responsavel_execucao_nome
       FROM fotografico_plano p
       JOIN obra o ON o.idobra = p.obra_id
  LEFT JOIN colaborador rp ON rp.idcolaborador = p.responsavel_plano_id
  LEFT JOIN colaborador re ON re.idcolaborador = p.responsavel_execucao_id
  LEFT JOIN cliente c ON c.idcliente = o.cliente
      WHERE p.id = ? LIMIT 1"
);
$stmt->bind_param('i', $planId);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$plan) {
    foto_pdf_fail(404, 'Plano fotográfico não encontrado.');
}
if (!improov_usuario_pode_acessar_obra($conn, (int) $plan['obra_id'])) {
    foto_pdf_fail(403, 'Sem acesso a esta obra.');
}

$stmt = $conn->prepare(
    "SELECT v.*, a.caminho AS mapa_caminho, a.mime AS mapa_mime
       FROM fotografico_plano_versao v
  LEFT JOIN fotografico_anexo a ON a.id = v.mapa_anexo_id AND a.arquivado_em IS NULL
      WHERE v.plano_id = ?
      ORDER BY CASE v.status WHEN 'RASCUNHO' THEN 0 WHEN 'PUBLICADA' THEN 1 ELSE 2 END, v.numero DESC
      LIMIT 1"
);
$stmt->bind_param('i', $planId);
$stmt->execute();
$version = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$version) {
    foto_pdf_fail(422, 'O plano ainda não possui versão para exportação.');
}

$stmt = $conn->prepare(
    "SELECT po.*, COALESCE(po.altura_identificacao_snapshot, h.identificacao) AS altura_identificacao,
            COALESCE(po.altura_m_snapshot, h.altura_m) AS altura_m, h.altura,
            GROUP_CONCAT(DISTINCT pe.nome ORDER BY pe.ordem SEPARATOR ', ') AS periodos,
            MIN(c.prioridade) AS prioridade
       FROM fotografico_posicao po
  LEFT JOIN fotografico_alturas h ON h.id = po.altura_id
  LEFT JOIN fotografico_captura c ON c.posicao_id = po.id
  LEFT JOIN fotografico_periodo pe ON pe.id = c.periodo_id
      WHERE po.versao_id = ?
      GROUP BY po.id
      ORDER BY po.ordem, po.id"
);
$versionId = (int) $version['id'];
$stmt->bind_param('i', $versionId);
$stmt->execute();
$points = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $points[] = $row;
}
$stmt->close();

$photoTypes = ['360' => '360º', 'PANORAMICA' => 'Panorâmica', 'CLIQUE_UNICO' => 'Clique único'];
$mapHtml = '';
$mapPath = trim((string) ($version['mapa_caminho'] ?? ''));
if ($mapPath !== '') {
    $root = realpath(__DIR__ . '/..');
    $candidate = realpath($root . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $mapPath), '/\\'));
    if ($root && $candidate && str_starts_with($candidate, $root . DIRECTORY_SEPARATOR) && is_file($candidate) && filesize($candidate) <= 3 * 1024 * 1024) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($candidate) ?: 'image/png';
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $mapSource = foto_pdf_map_with_markers($candidate, $mime, $points) ?: 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($candidate));
            $mapHtml = '<section class="map"><h2>Mapa de posições</h2><img style="display:block;width:100%;max-height:140mm;border:1px solid #cbd5e1" src="' . $mapSource . '" alt="Mapa de posições"><p class="map-legend">Marcadores: ' . count($points) . ' ponto(s) fotográfico(s)</p></section>';
        }
    }
}

$pointHtml = '';
foreach ($points as $point) {
    $type = $photoTypes[(string) ($point['tipo_foto'] ?? '')] ?? 'Não definido';
    $rows = [
        'Tipo de foto' => $type,
        'Altura' => ($point['altura_identificacao'] ? $point['altura_identificacao'] . ' — ' : '') . foto_pdf_height($point),
        'Período' => $point['periodos'] ?: 'Não definido',
        'Prioridade' => $point['prioridade'] ? 'P' . $point['prioridade'] : 'Não definida',
    ];
    $meta = '';
    foreach ($rows as $label => $content) {
        $meta .= '<div><dt>' . foto_pdf_escape($label) . '</dt><dd>' . foto_pdf_escape((string) $content) . '</dd></div>';
    }
    $pointHtml .= '<article class="point"><header><span class="code">' . foto_pdf_escape($point['codigo']) . '</span><div><h2>' . foto_pdf_escape($point['observacao'] ?: 'Ponto fotográfico') . '</h2><p>Informações de captura e orientação operacional</p></div></header><dl>' . $meta . '</dl></article>';
}

$date = $plan['data_planejada'] ? date('d/m/Y', strtotime((string) $plan['data_planejada'])) : 'Não definida';
$title = 'Plano Fotográfico — ' . ($plan['nomenclatura'] ?: $plan['nome_obra']);
$html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>
@page { margin: 17mm 15mm 18mm; }
*{box-sizing:border-box} body{font-family:DejaVu Sans,sans-serif;color:#172033;font-size:9.5pt;line-height:1.45} h1,h2,p{margin:0}.header{border-bottom:3px solid #215da8;padding-bottom:12px;margin-bottom:16px}.eyebrow{font-size:8pt;color:#4d6684;text-transform:uppercase;letter-spacing:.08em}.header h1{font-size:20pt;margin:3px 0 5px;color:#14243b}.header p{color:#53657a}.overview{width:100%;border-collapse:separate;border-spacing:8px;margin:0 -8px 14px}.overview td{width:50%;vertical-align:top;border:1px solid #d7e0eb;border-radius:5px;padding:8px 10px;background:#f8fafc}.overview strong{display:block;font-size:7.5pt;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin-bottom:2px}.overview span{color:#1e293b}.map{margin:0 0 16px;page-break-inside:avoid}.map h2{font-size:12pt;margin-bottom:7px}.map-canvas{position:relative;width:100%;line-height:0}.map-canvas img{display:block;width:100%;max-height:140mm;border:1px solid #cbd5e1}.map-marker{position:absolute;display:block;min-width:18px;padding:2px 4px;transform:translate(-50%,-50%);background:#215da8;border:1px solid #fff;border-radius:9px;color:#fff;font-family:DejaVu Sans,sans-serif;font-size:7pt;font-weight:bold;line-height:1.15;text-align:center;white-space:nowrap}.map-legend{font-size:7.5pt;color:#64748b;margin-top:4px}.section{font-size:12pt;color:#14243b;border-bottom:1px solid #cbd5e1;padding-bottom:6px;margin:15px 0 9px}.point{border:1px solid #cbd5e1;border-left:4px solid #215da8;border-radius:5px;padding:10px 11px;margin:0 0 10px;page-break-inside:avoid;background:#fff}.point header{display:table;width:100%;margin-bottom:8px}.point header>div,.code{display:table-cell;vertical-align:middle}.code{width:45px;height:36px;color:#fff;background:#215da8;border-radius:4px;text-align:center;font-weight:bold;font-size:10pt}.point header>div{padding-left:9px}.point h2{font-size:11pt;color:#172033}.point header p{font-size:8pt;color:#64748b;margin-top:2px}.point dl{display:table;width:100%;border-collapse:collapse;margin:0}.point dl div{display:table-row}.point dt,.point dd{display:table-cell;border-top:1px solid #e7edf4;padding:4px 0;vertical-align:top}.point dt{width:27%;font-size:8pt;font-weight:bold;color:#64748b}.point dd{color:#253348}.image-list{margin:0;padding:0;list-style:none}.image-list li{padding:3px 0 3px 10px;border-left:2px solid #93b4dc}.image-list li+li{border-top:1px solid #eef2f7}.image-list strong,.image-list span{display:block}.image-list strong{font-size:8.5pt}.image-list span,.no-image{font-size:8pt;color:#64748b}.empty{padding:18px;border:1px dashed #94a3b8;color:#64748b;text-align:center}.footer{position:fixed;bottom:-10mm;left:0;right:0;font-size:7.5pt;color:#64748b;text-align:center}.footer .page:after{content:counter(page)}
</style></head><body><header class="header"><div class="eyebrow">Documento operacional · Versão ' . foto_pdf_escape((string) $version['numero']) . '</div><h1>' . foto_pdf_escape($title) . '</h1><p>Gerado em ' . date('d/m/Y H:i') . ' · Plano #PF-' . str_pad((string) $planId, 4, '0', STR_PAD_LEFT) . '</p></header><table class="overview"><tr><td><strong>Projeto / obra</strong><span>' . foto_pdf_escape($plan['nome_completo'] ?: $plan['nome_obra']) . '</span></td><td><strong>Cliente</strong><span>' . foto_pdf_escape($plan['cliente'] ?: 'Não definido') . '</span></td></tr><tr><td><strong>Endereço</strong><span>' . foto_pdf_escape($plan['local'] ?: 'Não definido') . '</span></td><td><strong>Data planejada</strong><span>' . foto_pdf_escape($date) . '</span></td></tr><tr><td><strong>Responsável pelo plano</strong><span>' . foto_pdf_escape($plan['responsavel_plano_nome'] ?: 'Não definido') . '</span></td><td><strong>Responsável pela execução</strong><span>' . foto_pdf_escape($plan['responsavel_execucao_nome'] ?: 'Não definido') . '</span></td></tr></table>' . $mapHtml . '<h2 class="section">Pontos fotográficos (' . count($points) . ')</h2>' . ($pointHtml ?: '<div class="empty">Nenhum ponto fotográfico cadastrado nesta versão.</div>') . '<div class="footer">Plano fotográfico · ' . foto_pdf_escape($plan['nomenclatura'] ?: $plan['nome_obra']) . ' · Página <span class="page"></span></div></body></html>';

$cacheDir = __DIR__ . '/../Contratos/cache/dompdf';
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('chroot', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
$options->set('defaultFont', 'DejaVu Sans');
$options->set('fontDir', $cacheDir);
$options->set('fontCache', $cacheDir);
$options->set('tempDir', $cacheDir);
$pdf = new Dompdf($options);
$pdf->setPaper('A4', 'portrait');
$pdf->loadHtml($html, 'UTF-8');
$pdf->render();
$pdf->getCanvas()->page_text(515, 815, 'Página {PAGE_NUM} de {PAGE_COUNT}', 'DejaVu Sans', 7, [0.4, 0.45, 0.52]);
$filename = 'plano-fotografico-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($plan['nomenclatura'] ?: $planId)) . '.pdf';
$pdf->stream($filename, ['Attachment' => true]);
