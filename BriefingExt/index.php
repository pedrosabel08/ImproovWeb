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
        <div class="head-context"><span class="head-flow">FLOW</span><span>Briefing Online</span></div>
        <div class="save-state" id="save-state" role="status" aria-live="polite">Sincronizando…</div>
        <div class="participant-chip" id="participant-chip" aria-label="Participante atual"></div>
      </header>
      <div class="mobile-project-bar">
        <span id="mobile-section-count"></span>
        <strong id="mobile-section-name"></strong>
        <button type="button" id="toggle-sections" aria-expanded="false">Ver todas as seções</button>
      </div>
      <div class="layout">
        <aside class="project-sidebar">
          <section class="project-card" aria-label="Contexto do projeto">
            <span class="project-monogram" id="project-monogram" aria-hidden="true"></span>
            <div><h1 id="title"></h1><p id="subtitle"></p></div>
          </section>
          <nav id="sections" aria-label="Etapas do briefing"></nav>
        </aside>
        <section class="briefing-content">
          <div class="welcome">
            <p class="eyebrow">SEU PROJETO</p>
            <h2 id="welcome-title"></h2>
            <p id="welcome-copy"></p>
            <div class="reassurances" aria-label="Como funciona">
              <span><b aria-hidden="true">⌁</b> Respostas salvas com segurança</span>
              <span><b aria-hidden="true">◷</b> Continue quando quiser</span>
            </div>
            <div class="progress-row" aria-label="Progresso do briefing">
              <div class="progress"><span id="progress-fill"></span></div><span id="progress-label"></span>
            </div>
          </div>
          <section id="form" class="form-card" aria-live="polite"></section>
          <footer class="section-footer">
            <p>Obrigado por compartilhar. Suas respostas ajudam nossa equipe a entender melhor o seu projeto.</p>
            <div class="step-actions"><button type="button" id="previous-section" class="secondary">← Voltar</button><button type="button" id="next-section" class="primary">Continuar →</button></div>
          </footer>
        </section>
        <aside class="insights-sidebar">
          <section class="insight-card overall-progress">
            <p class="card-label">PROGRESSO GERAL</p>
            <div class="progress-ring" id="progress-ring"><strong id="progress-percent">0%</strong></div>
            <h2 id="progress-message">Vamos começar</h2>
            <p id="progress-detail"></p>
          </section>
          <section class="insight-card">
            <h2>Resumo do briefing</h2>
            <dl><div><dt>Perguntas respondidas</dt><dd id="summary-answers"></dd></div><div><dt>Seções concluídas</dt><dd id="summary-sections"></dd></div></dl>
          </section>
          <section class="insight-card request-summary" id="request-summary" hidden>
            <p class="card-label">COMPLEMENTO SOLICITADO</p>
            <strong id="request-title"></strong><p id="request-copy"></p>
          </section>
          <section class="insight-card tips-card">
            <h2>Dicas</h2><ul><li>Quanto mais contexto, melhor.</li><li>Você pode voltar a qualquer momento.</li></ul>
          </section>
        </aside>
      </div>
      <footer class="footer"><span id="last-change"></span><button id="submit" class="primary">Enviar briefing</button></footer>
    </section>
  </main>
  <script>
    window.BRIEFING_ACCESS_TOKEN = <?= json_encode($token, JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="../assets/js/briefing-ws.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/briefing-ws.js') ?>" defer></script>
  <script src="app.js?v=<?= (int) filemtime(__DIR__ . '/app.js') ?>" defer></script>
</body>

</html>
