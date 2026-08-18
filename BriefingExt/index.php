<?php
$token = preg_replace('/[^a-f0-9]/i', '', (string) ($_GET['t'] ?? ''));
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Briefing — Flow</title>
  <link rel="stylesheet" href="style.css?v=<?= (int) filemtime(__DIR__ . '/style.css') ?>">
</head>

<body>
  <main class="shell">
    <header class="brand"><span>FLOW</span><strong>Briefing Online</strong></header>
    <div id="notice" role="status" aria-live="polite"></div>
    <section class="access-card" id="access">
      <p class="eyebrow">ACESSO SEGURO</p>
      <h1>Vamos começar seu briefing</h1>
      <p id="access-copy">Informe seu e-mail para continuar.</p>
      <form id="start-form"><label>E-mail<input id="email" type="email" autocomplete="email" required></label><button>Continuar</button></form>
      <form id="register-form" hidden><label>Nome<input id="name" autocomplete="name" maxlength="180" required></label><label>Cargo<input id="role" autocomplete="organization-title" maxlength="120"></label><label>Telefone<input id="phone" autocomplete="tel" maxlength="60"></label><button>Enviar código</button><button type="button" class="link" id="back-email">Voltar</button></form>
      <form id="verify-form" hidden><label>Código de 6 dígitos<input id="code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required></label><button>Confirmar acesso</button><button type="button" class="link" id="again">Enviar outro código</button></form>
    </section>
    <section id="briefing-app" hidden>
      <header class="briefing-head">
        <div>
          <p class="eyebrow">BRIEFING</p>
          <h1 id="title"></h1>
          <p id="subtitle"></p>
        </div>
        <div class="save-state" id="save-state">Carregando…</div>
      </header>
      <div class="progress-row">
        <div class="progress"><span id="progress-fill"></span></div><span id="progress-label"></span>
      </div>
      <div class="layout">
        <nav id="sections" aria-label="Seções"></nav>
        <section id="form" class="form-card"></section>
      </div>
      <footer class="footer"><span id="last-change"></span><button id="submit" class="primary">Concluir briefing</button></footer>
    </section>
  </main>
  <script>
    window.BRIEFING_ACCESS_TOKEN = <?= json_encode($token, JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="../assets/js/briefing-ws.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/briefing-ws.js') ?>" defer></script>
  <script src="app.js?v=<?= (int) filemtime(__DIR__ . '/app.js') ?>" defer></script>
</body>

</html>