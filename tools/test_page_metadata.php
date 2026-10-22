<?php
// Run: php tools/test_page_metadata.php (no server, database or credentials required).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/config/page_metadata.php';

$checks = 0;
$failures = [];
$check = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$ok) {
        $failures[] = $message;
    }
};
$root = dirname(__DIR__);
$catalog = flow_metadata_catalog();
$_SERVER['HTTP_HOST'] = 'improov';
$_SERVER['REQUEST_URI'] = '/ImproovWeb/';
$originalBase = getenv('FLOW_PUBLIC_BASE_URL');
putenv('FLOW_PUBLIC_BASE_URL');

$required = ['og:title', 'og:description', 'og:image', 'og:url', 'og:type', 'og:site_name', 'twitter:card', 'twitter:title', 'twitter:description', 'twitter:image'];
foreach ($catalog as $key => $meta) {
    foreach ($meta['files'] as $file) {
        $check(is_file($root . '/' . $file), "Entry point missing: $file");
        $check(flow_metadata_key_for_script($root . '/' . $file) === $key, "Mapping mismatch: $file");
        $_SERVER['SCRIPT_FILENAME'] = $root . '/' . $file;
        $_SERVER['REQUEST_URI'] = '/ImproovWeb/' . $file;
        $expected = flow_metadata_html();
        $_GET = ['obra_nome' => 'PRIVATE_SENTINEL', 'imagem_id' => '987654321', 'token' => 'SECRET_SENTINEL'];
        $_SERVER['REQUEST_URI'] .= '?obra_nome=PRIVATE_SENTINEL&token=SECRET_SENTINEL';
        $check($expected === flow_metadata_html(), "Metadata depends on query: $file");
        $check(!str_contains($expected, 'SENTINEL'), "Private query reflected: $file");
        $check(!str_contains(flow_metadata_login_url(), 'SENTINEL'), "Login reflects query: $file");
    }
    $html = flow_metadata_html($key);
    $check(substr_count($html, '<title>') === 1, "Duplicate title: $key");
    $check(substr_count($html, 'rel="icon"') === 1, "Duplicate icon: $key");
    foreach ($required as $property) {
        $check(substr_count($html, '="' . $property . '"') === 1, "Missing/duplicate $property: $key");
    }
    $values = flow_metadata_values($key);
    $check($values['title'] === $meta['title'], "Title mismatch: $key");
    $check(!str_contains($values['url'], '?') && !str_contains($values['url'], '#'), "Unsafe canonical: $key");
}

$_SERVER['SCRIPT_FILENAME'] = $root . '/future-page.php';
$check(flow_metadata_values()['title'] === 'Flow | Improov', 'Unconfigured page has no fallback');
foreach ([null, [], '<script>alert(1)</script>', '../FlowReview/index.php', 'https://evil.invalid/', 'flow-review&token=SECRET'] as $key) {
    $check(flow_metadata_public_key($key) === 'flow', 'Invalid preview key accepted');
}
$check(flow_metadata_public_key('flow-review') === 'flow-review', 'Valid preview key rejected');
foreach (['evil.invalid', 'improov.evil.invalid', 'localhost:8066@evil.invalid'] as $host) {
    $_SERVER['HTTP_HOST'] = $host;
    $check(flow_metadata_base_url() === 'https://improov.com.br/flow/ImproovWeb', 'Untrusted Host reflected');
}
$_SERVER['HTTP_HOST'] = 'localhost:8066';
$check(flow_metadata_base_url() === 'http://localhost:8066/ImproovWeb', 'Local HTTP origin mismatch');
$_SERVER['HTTP_HOST'] = 'improov';
$_SERVER['REQUEST_URI'] = '/flow/ImproovWeb/FlowReview/?token=SECRET';
$check(flow_metadata_base_url() === 'https://improov/flow/ImproovWeb', 'Deployment base path mismatch');
foreach (['javascript:alert(1)', 'https://user@example.com/flow/ImproovWeb', 'https://example.com/?token=SECRET', "https://example.com/\r\nInjected"] as $base) {
    putenv('FLOW_PUBLIC_BASE_URL=' . $base);
    $check(!str_contains(flow_metadata_base_url(), 'example.com'), 'Invalid deployment origin accepted');
}
putenv('FLOW_PUBLIC_BASE_URL=https://example.com/flow/ImproovWeb');
$check(flow_metadata_base_url() === 'https://example.com/flow/ImproovWeb', 'Trusted deployment configuration ignored');
putenv('FLOW_PUBLIC_BASE_URL');
$_SERVER['REQUEST_URI'] = '/ImproovWeb/';
$fixture = '<html><head><title>Old</title><link rel="icon" href="old"><link rel="shortcut icon" href="duplicate"><meta property="og:title" content="Old"><meta name="twitter:card" content="summary"><meta name="description" content="Old"></head><body><form action="login.php"></form></body></html>';
$result = flow_metadata_apply_to_html($fixture, 'flow-review');
$check(substr_count($result, '<title>') === 1, 'Template duplicates title');
$check(substr_count($result, 'rel="icon"') === 1, 'Template duplicates favicon');
$check(substr_count($result, 'property="og:title"') === 1 && substr_count($result, 'name="twitter:card"') === 1 && substr_count($result, 'name="description"') === 1, 'Template duplicates existing metadata');
$check(str_contains($result, '<body><form action="login.php"></form></body>'), 'Template body changed');

// Keep the registry accountable to the current active sidebar, not a copied route list.
$sidebar = file_get_contents($root . '/sidebar.php');
$active = substr($sidebar, strpos($sidebar, '<aside class="sidebar"'));
preg_match_all('~https://improov\.com\.br/flow/ImproovWeb/([^"?#]+)~', $active, $absolute);
preg_match_all('~improov_sidebar_url\(\s*[\'"]([^\'"?]+)~', $active, $relative);
$routes = [];
foreach (array_merge($absolute[1], $relative[1]) as $route) {
    if (!str_starts_with($route, 'gif/')) {
        $routes[rtrim($route, '/')] = true;
    }
}
$canonical = array_map(static fn ($meta) => rtrim($meta['route'], '/'), $catalog);
foreach (array_keys($routes) as $route) {
    $check(in_array($route, $canonical, true), "Sidebar route absent from metadata catalog: $route");
}
$shell = file_get_contents($root . '/assets/js/flow-shell.js');
$check(str_contains($shell, '"Dashboard/obra"') && in_array('Dashboard/obra', $canonical, true), 'Dynamic project destination missing');
$check(!str_contains($sidebar, '<title>Sidebar</title>'), 'Sidebar duplicates page title');
foreach (['assets/og/flow-default.png', 'assets/icons/flow-favicon.png'] as $asset) {
    $size = getimagesize($root . '/' . $asset);
    $check($size !== false && $size['mime'] === 'image/png', "Invalid PNG asset: $asset");
}
$check(hash_file('sha256', $root . '/assets/og/flow-default.png') === hash_file('sha256', $root . '/assets/ImproovFlow - logo.png'), 'Institutional logo was changed');
if ($originalBase !== false) {
    putenv('FLOW_PUBLIC_BASE_URL=' . $originalBase);
}
echo json_encode(['checks' => $checks, 'modules' => count($catalog), 'sidebar_static_routes' => count($routes), 'failures' => $failures], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failures === [] ? 0 : 1);
