<?php
declare(strict_types=1);
require_once __DIR__.'/../config/session_bootstrap.php';
if (($_SESSION['logado'] ?? false) !== true) {
    header('Location: ../index.html');
    exit;
}
if (empty($_SESSION['portal_internal_csrf'])) {
    $_SESSION['portal_internal_csrf'] = bin2hex(random_bytes(32));
}
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="portal-csrf" content="<?= htmlspecialchars($_SESSION['portal_internal_csrf'], ENT_QUOTES, 'UTF-8') ?>"><title>Portal do Cliente — Flow</title><link rel="stylesheet" href="../css/styleSidebar.css"><link rel="stylesheet" href="portal.css"><script src="internal.js" defer></script></head><body>
<?php include __DIR__.'/../sidebar.php'; ?>
<div class="internal-shell"><div class="topbar"><div><p class="eyebrow">FLOW · RELACIONAMENTO COM O CLIENTE</p><h1>Portal do Cliente</h1></div><a href="../inicio.php">Voltar ao Flow</a></div><label class="field">Projeto<select id="project"><option value="">Selecione um projeto</option></select></label><div id="notice" role="status" aria-live="polite" hidden></div><div id="invitation" class="share-box" hidden></div><main id="main" aria-busy="true"><p>Carregando projetos…</p></main></div><script src="../script/sidebar.js"></script></body></html>
