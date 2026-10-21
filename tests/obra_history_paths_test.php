<?php
// Testa a resolução de mídia sem conectar a banco ou SFTP.
function improov_app_base_path(): string { return '/ImproovWeb'; }
require_once __DIR__ . '/../Obras/historico_data.php';
function check($actual, $expected): void {
    if ($actual !== $expected) throw new RuntimeException('Esperado ' . var_export($expected, true) . ', recebido ' . var_export($actual, true));
}
check(historicoPublicUrl('https://example.com/a.jpg'), 'https://example.com/a.jpg');
check(historicoPublicUrl('/home/site/public_html/flow/ImproovWeb/uploads/a.jpg'), '/flow/ImproovWeb/uploads/a.jpg');
check(historicoPublicUrl('/mnt/clientes/2026/OBRA/05.Exchange/01.Input/ref/a.jpg'), '/ImproovWeb/uploads/ref/a.jpg');
check(historicoPublicUrl('uploads/previa.jpg'), '/ImproovWeb/uploads/previa.jpg');
check(historicoPublicUrl('previa.jpg'), '/ImproovWeb/uploads/previa.jpg');
check(historicoPublicUrl('assets/perfil/pedro.jpg'), '/ImproovWeb/assets/perfil/pedro.jpg');
check(historicoPublicUrl('../segredo.jpg'), null);
check(historicoPublicUrl('javascript:alert(1)'), null);
check(historicoPublicUrl(null), null);
$preview = historicoPreview('Vista da fachada', 'uploads/fachada.JPG');
check($preview['nome_original'], 'Vista da fachada');
check($preview['url'], '/ImproovWeb/uploads/fachada.JPG');
check(strpos($preview['thumbnail_url'], '../thumb.php?w=208') === 0, true);
check(historicoPreview('Documento', 'uploads/projeto.pdf'), null);
echo "Histórico: URLs de imagem, foto e miniaturas passaram.\n";
