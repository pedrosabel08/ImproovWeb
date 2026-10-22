<?php
/** Public login presentation only: never include protected pages or database code. */
require_once __DIR__ . '/config/page_metadata.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Vary: Cookie');
header('X-Robots-Tag: noindex, nofollow');

// This is an allowlisted institutional key, never a URL or a return destination.
$previewKey = flow_metadata_public_key($_GET['preview'] ?? null);
$loginHtml = file_get_contents(__DIR__ . '/index.html');
echo flow_metadata_apply_to_html($loginHtml, $previewKey);
