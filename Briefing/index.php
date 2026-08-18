<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
if (($_SESSION['logado'] ?? false) !== true) {
  header('Location: ../index.html');
  exit;
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Briefing Online — Flow</title>
  <link rel="stylesheet" href="../css/styleSidebar.css">
  <link rel="stylesheet" href="style-online.css">
</head>

<body>
  <?php include __DIR__ . '/../sidebar.php'; ?>
  <main class="briefing-main">
    <header class="briefing-header">
      <div>
        <p class="eyebrow">FLOW</p>
        <h1>Briefing Online</h1>
        <p class="muted">Crie, acompanhe e valide briefings sem perder o histórico.</p>
      </div>
      <div class="header-actions"><button class="button secondary" id="new-template">Novo template</button><button class="button" id="new-briefing">Novo briefing</button></div>
    </header>
    <div class="notice" id="notice" role="status" aria-live="polite"></div>
    <section class="kpis" id="kpis"></section>
    <section class="briefing-grid">
      <div class="panel">
        <div class="panel-head">
          <h2>Briefings</h2><button class="text-button" id="refresh">Atualizar</button>
        </div>
        <div id="briefing-list" class="card-list"></div>
      </div>
      <aside class="panel detail" id="detail">
        <p class="muted">Selecione um briefing para acompanhar progresso, prazo, participantes e histórico.</p>
      </aside>
    </section>
  </main>
  <dialog id="template-dialog" class="dialog wide">
    <form method="dialog" id="template-form">
      <header>
        <div>
          <p class="eyebrow">CATÁLOGO</p>
          <h2>Template de briefing</h2>
        </div><button type="submit" class="icon" value="cancel" formnovalidate aria-label="Fechar">×</button>
      </header><label>Nome do template<input id="template-name" required maxlength="180"></label><label class="check"><input type="checkbox" id="template-review" checked> Exige conferência interna</label><label>Revisor padrão<select id="template-reviewer">
          <option value="">Definir por briefing</option>
        </select></label>
      <div id="template-sections"></div><button type="button" class="button secondary" id="add-section">Adicionar seção</button>
      <footer><button class="button" value="default" id="save-template">Salvar template</button></footer>
    </form>
  </dialog>
  <dialog id="briefing-dialog" class="dialog">
    <form method="dialog" id="briefing-form">
      <header>
        <h2>Novo briefing</h2><button type="submit" class="icon" value="cancel" formnovalidate aria-label="Fechar">×</button>
      </header><label>Template<select id="briefing-template" required></select></label><label>Obra<select id="briefing-obra" required></select></label><label>Título<input id="briefing-title" required maxlength="180"></label><label>Prazo<input id="briefing-due" type="datetime-local"></label><label>Responsável pela conferência<select id="briefing-reviewer">
          <option value="">Qualquer pessoa interna</option>
        </select></label><label class="check"><input type="checkbox" id="briefing-requires-review" checked> Exige conferência interna</label>
      <footer><button class="button" value="default" id="save-briefing">Criar briefing</button></footer>
    </form>
  </dialog>
</body>
<script src="../assets/js/briefing-ws.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/briefing-ws.js') ?>" defer></script>
<script src="app.js?v=<?= (int) filemtime(__DIR__ . '/app.js') ?>" defer></script>

</html>
