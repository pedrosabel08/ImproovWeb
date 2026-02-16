<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
session_start();

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Não autenticado.';
    exit;
}

$idObra = isset($_GET['idobra']) ? intval($_GET['idobra']) : 0;
$category = isset($_GET['category']) ? strtolower(trim((string) $_GET['category'])) : 'todos';

if ($idObra <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Parâmetro idobra inválido.';
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../conexao.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function h($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatarDataPtBr($dateStr)
{
    if (!$dateStr) return '';
    try {
        $dt = new DateTime($dateStr);
        return $dt->format('d/m/Y');
    } catch (Exception $e) {
        return (string) $dateStr;
    }
}

function mapTipoToCategoria($tipo)
{
    $t = strtolower(trim((string) $tipo));
    if ($t === 'entrega') return 'entregas';
    if ($t === 'arquivo' || $t === 'arquivos' || $t === 'upload' || $t === 'anexo') return 'arquivos';
    return 'manuais';
}

// Buscar nomenclatura da obra
$obraNome = null;
$stmtObra = $conn->prepare('SELECT nomenclatura FROM obra WHERE idobra = ? LIMIT 1');
if ($stmtObra) {
    $stmtObra->bind_param('i', $idObra);
    $stmtObra->execute();
    $resObra = $stmtObra->get_result();
    if ($resObra && ($row = $resObra->fetch_assoc())) {
        $obraNome = $row['nomenclatura'] ?? null;
    }
    $stmtObra->close();
}

// Buscar acompanhamentos (mesma fonte do frontend: acompanhamento_email)
$acompanhamentos = [];
$sql = 'SELECT idacompanhamento_email as id, assunto, data, ordem, tipo FROM acompanhamento_email WHERE obra_id = ? ORDER BY data DESC, idacompanhamento_email DESC';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Erro ao preparar consulta.';
    exit;
}

$stmt->bind_param('i', $idObra);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['categoria'] = mapTipoToCategoria($row['tipo'] ?? '');
    $acompanhamentos[] = $row;
}
$stmt->close();

if ($category !== 'todos') {
    $acompanhamentos = array_values(array_filter($acompanhamentos, function ($a) use ($category) {
        return ($a['categoria'] ?? '') === $category;
    }));
}

$categoriaTitulo = 'Todos';
if ($category === 'manuais') $categoriaTitulo = 'Manuais';
if ($category === 'entregas') $categoriaTitulo = 'Entregas';
if ($category === 'arquivos') $categoriaTitulo = 'Arquivos';

$titulo = 'Histórico';
$subtitulo = ($obraNome ? $obraNome : ('Obra ' . $idObra));

$css = <<<CSS
@page { margin: 22mm 18mm; }
body { font-family: DejaVu Sans, sans-serif; color:#111; font-size: 12px; }
.page-header { margin-bottom: 14px; }
.page-header h1 { font-size: 20px; margin: 0 0 4px 0; }
.page-header .sub { color:#555; font-size: 12px; }
.badge { display:inline-block; padding: 3px 8px; border-radius: 10px; font-size: 11px; background:#0050ab; color:#fff; margin-left: 8px; }
.card { border: 1px solid #e2e2e2; border-radius: 10px; padding: 12px; }
.infos-obra-header { display:flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.infos-obra-header h2 { margin:0; font-size: 16px; }
.meta { color:#666; font-size: 11px; }
.list-acomp { display:block; }
.acomp-conteudo { border: 1px solid rgba(0,0,0,0.08); border-radius: 8px; padding: 10px; margin: 0 0 8px 0; }
.acomp-assunto { margin: 0 0 6px 0; font-size: 12px; }
.acomp-data { margin: 0; color:#444; font-size: 11px; }
.divider { height: 1px; background: #eee; margin: 10px 0; }
.empty { color:#666; font-style: italic; }
CSS;

$itemsHtml = '';
if (!$acompanhamentos || count($acompanhamentos) === 0) {
    $itemsHtml = '<div class="empty">Nenhum acompanhamento encontrado.</div>';
} else {
    foreach ($acompanhamentos as $a) {
        $assunto = $a['assunto'] ?? '';
        $dataFmt = formatarDataPtBr($a['data'] ?? '');
        $tipo = $a['tipo'] ?? '';
        $tagTipo = $tipo ? ('<span class="meta">(' . h($tipo) . ')</span>') : '';
        $itemsHtml .= '<div class="acomp-conteudo">'
            . '<p class="acomp-assunto"><strong>•</strong> ' . h($assunto) . ' ' . $tagTipo . '</p>'
            . '<p class="acomp-data"><strong>↳</strong> ' . h($dataFmt) . '</p>'
            . '</div>';
    }
}

$html = '<!doctype html><html><head><meta charset="utf-8"><style>' . $css . '</style></head><body>'
    . '<div class="page-header">'
    . '<h1>' . h($titulo) . '<span class="badge">' . h($categoriaTitulo) . '</span></h1>'
    . '<div class="sub">' . h($subtitulo) . '</div>'
    . '</div>'
    . '<div class="card">'
    . '<div class="infos-obra-header">'
    . '<h2>Registros</h2>'
    . '<div class="meta">Gerado em ' . h((new DateTime())->format('d/m/Y H:i')) . '</div>'
    . '</div>'
    . '<div class="list-acomp">' . $itemsHtml . '</div>'
    . '</div>'
    . '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->render();

$safeName = $obraNome ? preg_replace('/[^A-Za-z0-9._-]+/', '_', $obraNome) : ('obra_' . $idObra);
$filename = 'historico_' . $safeName . '.pdf';

// Stream para download
$dompdf->stream($filename, ['Attachment' => true]);
exit;
