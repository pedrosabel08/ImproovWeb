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
  <title>Briefings — Flow</title>
  <link rel="stylesheet" href="../css/styleSidebar.css">
  <link rel="stylesheet" href="style-online.css">
</head>

<body>
  <?php include __DIR__ . '/../sidebar.php'; ?>
  <main class="briefing-main">
    <header class="briefing-header">
      <div>
        <h1>Briefings</h1>
        <p class="muted">Gerencie e acompanhe os briefings dos projetos.</p>
      </div>
      <div class="header-actions">
        <button class="button secondary" id="open-templates">Templates</button>
        <button class="button" id="new-briefing">+ Novo briefing</button>
      </div>
    </header>

    <div class="notice" id="notice" role="status" aria-live="polite"></div>
    <nav class="module-tabs" aria-label="Área do Briefing">
      <button type="button" class="module-tab is-active" id="view-briefings" aria-current="page">Briefings</button>
      <button type="button" class="module-tab" id="view-templates">Templates</button>
    </nav>

    <section class="workspace-view" id="briefings-view">
      <div class="quick-filters" id="quick-filters" aria-label="Filtros rápidos por status"></div>
      <div class="filter-toolbar">
        <label class="search-control"><span aria-hidden="true">⌕</span><input id="briefing-search" type="search" placeholder="Buscar por projeto, título ou responsável..."></label>
        <select id="filter-status" aria-label="Filtrar por status">
          <option value="">Status</option>
        </select>
        <select id="filter-reviewer" aria-label="Filtrar por responsável">
          <option value="">Responsável</option>
        </select>
        <select id="filter-due" aria-label="Filtrar por prazo">
          <option value="">Prazo</option>
          <option value="upcoming">Próximos 7 dias</option>
          <option value="late">Atrasados</option>
          <option value="none">Sem prazo</option>
        </select>
        <select id="filter-sort" aria-label="Ordenar briefings">
          <option value="activity">Atividade recente</option>
          <option value="due">Prazo</option>
          <option value="progress">Progresso</option>
          <option value="title">Projeto</option>
          <option value="status">Status</option>
        </select>
        <button class="icon-button" type="button" id="clear-filters" aria-label="Limpar filtros" title="Limpar filtros">↺</button>
      </div>

      <section class="workspace-grid">
        <div class="list-surface">
          <div class="list-heading"><span>Projeto</span><span>Progresso</span><span>Status</span><span>Prazo</span><span>Última atividade</span><span class="visually-hidden">Abrir</span></div>
          <div id="briefing-list" class="briefing-list" aria-live="polite"></div>
          <footer class="pagination" id="pagination"></footer>
        </div>
        <aside class="detail-panel" id="detail" aria-live="polite">
          <div class="detail-empty"><strong>Selecione um briefing</strong>
            <p>Consulte o progresso, as respostas, participantes e histórico sem sair da listagem.</p>
          </div>
        </aside>
      </section>
    </section>

    <section class="workspace-view" id="templates-view" hidden>
      <div class="templates-header"><label class="search-control"><span aria-hidden="true">⌕</span><input id="template-search" type="search" placeholder="Buscar template..."></label><button class="button" type="button" id="new-template">+ Novo template</button></div>
      <section class="workspace-grid templates-grid">
        <div class="list-surface">
          <div class="list-heading template-heading"><span>Nome</span><span>Perguntas</span><span>Última alteração</span><span class="visually-hidden">Abrir</span></div>
          <div id="template-list" class="briefing-list"></div>
        </div>
        <aside class="detail-panel" id="template-detail">
          <div class="detail-empty"><strong>Selecione um template</strong>
            <p>Visualize sua estrutura, edite-o ou crie um briefing a partir dele.</p>
          </div>
        </aside>
      </section>
    </section>
  </main>

  <dialog id="template-dialog" class="dialog wide">
    <form method="dialog" id="template-form">
      <header>
        <div>
          <p class="eyebrow">CATÁLOGO</p>
          <h2 id="template-dialog-title">Template de briefing</h2>
        </div><button type="submit" class="icon" value="cancel" formnovalidate aria-label="Fechar">×</button>
      </header>
      <label>Nome do template<input id="template-name" required maxlength="180"></label><label class="check"><input type="checkbox" id="template-review" checked> Exige conferência interna</label><label>Revisor padrão<select id="template-reviewer">
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
      </header>
      <label>Template<select id="briefing-template" required></select></label><label>Obra<select id="briefing-obra" required></select></label><label>Título<input id="briefing-title" required maxlength="180"></label><label>Prazo<input id="briefing-due" type="datetime-local"></label><label>Responsável pela conferência<select id="briefing-reviewer">
          <option value="">Qualquer pessoa interna</option>
        </select></label><label class="check"><input type="checkbox" id="briefing-requires-review" checked> Exige conferência interna</label>
      <footer><button class="button" value="default" id="save-briefing">Criar briefing</button></footer>
    </form>
  </dialog>
  <dialog id="complement-dialog" class="dialog">
    <form method="dialog" id="complement-form">
      <header>
        <div>
          <p class="eyebrow">CONFERÊNCIA</p>
          <h2>Solicitar complemento</h2>
        </div><button type="submit" class="icon" value="cancel" formnovalidate aria-label="Fechar">×</button>
      </header>
      <label>Pergunta<select id="complement-question" required></select></label><label>O que precisa ser complementado?<textarea id="complement-message" required maxlength="5000" rows="5" placeholder="Explique para o cliente o que precisa ser revisto."></textarea></label>
      <footer><button class="button" value="default" id="save-complement">Solicitar complemento</button></footer>
    </form>
  </dialog>
  <script src="../assets/js/briefing-ws.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/briefing-ws.js') ?>" defer></script>
  <script src="app.js?v=<?= (int) filemtime(__DIR__ . '/app.js') ?>" defer></script>
</body>

</html>