<?php
declare(strict_types=1);
require_once __DIR__.'/../config/session_bootstrap.php';
if (empty($_SESSION['portal_entry_csrf'])) {
    $_SESSION['portal_entry_csrf'] = bin2hex(random_bytes(32));
}
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer"><meta name="portal-csrf" content="<?= htmlspecialchars($_SESSION['portal_entry_csrf'], ENT_QUOTES, 'UTF-8') ?>"><title>Seu projeto — Improov</title><link rel="stylesheet" href="portal.css"><script src="app.js" defer></script></head>
<body><header class="brand"><a href="#inicio" aria-label="Improov — início">improov<span>®</span></a><span>SEU PROJETO, COM A GENTE.</span></header><div class="shell"><p class="eyebrow" id="project-context">ÁREA DO CLIENTE</p><nav id="nav" aria-label="Seu projeto" hidden><button data-view="inicio">Seu momento</button><button data-view="equipe">Equipe</button><button data-view="materiais">Materiais</button><button data-view="perfil">Sua participação</button><button id="logout">Sair</button></nav><div id="notice" role="status" aria-live="polite" hidden></div><main id="main" aria-busy="true"><p>Estamos preparando seu espaço…</p></main><footer><strong>Estamos por perto.</strong><p>Precisa de ajuda? Fale com seu contato na Improov pelo canal habitual do projeto.</p></footer></div></body></html>
