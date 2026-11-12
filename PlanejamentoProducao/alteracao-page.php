<?php
// Partial rendered exclusively by the authenticated index.php document.
if (!isset($planejamentoPorImagem) || !$planejamentoPorImagem) {
    http_response_code(404);
    exit;
}
?>
<main class="planning-shell alteration-plan" id="alteration-plan" aria-labelledby="alteration-title">
  <header class="ap-header">
    <div><h1 id="alteration-title">Planejar entrega</h1><p id="ap-project"></p></div>
    <nav class="ap-actions" aria-label="Ações da entrega">
      <a class="ap-button" href="../Dashboard/obra.php?obra_id=<?= $obraId ?>">Voltar à obra</a>
      <button class="ap-button" type="button" id="ap-history">Histórico</button>
      <button class="ap-button" type="button" id="ap-theme" aria-label="Alternar tema"><i class="fa-regular fa-sun" aria-hidden="true"></i> Tema</button>
      <a class="ap-button ap-primary" href="../PlanejamentoCapacidade/index.php#alteracao-config">Configurar capacidade</a>
    </nav>
  </header>
  <div id="ap-loading-home"><div id="ap-loading" class="ap-loading" role="status"><canvas data-thinking-orb data-orb-state="composing" data-orb-size="42" data-orb-theme="auto" aria-label="Carregando planejamento"></canvas><span>Carregando planejamento…</span></div></div>
  <button id="ap-reload" type="button" class="ap-button" hidden>Recarregar dados da entrega</button>

  <div id="ap-content" hidden>
    <section class="ap-kpis" aria-label="Indicadores da entrega">
      <div><i class="fa-regular fa-calendar" aria-hidden="true"></i><span>Prazo do cliente<strong id="ap-due">—</strong></span></div>
      <div><i class="fa-regular fa-clock" aria-hidden="true"></i><span>Previsão<strong id="ap-forecast">—</strong></span></div>
      <div><i class="fa-solid fa-calendar-check" aria-hidden="true"></i><span>Margem<strong id="ap-margin">—</strong></span></div>
      <div><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span>Imagens planejadas<strong id="ap-complete">0 / 0</strong></span></div>
      <div><i class="fa-solid fa-bullseye" aria-hidden="true"></i><span>Carga total<strong id="ap-demand">—</strong></span></div>
    </section>
    <section id="ap-replan-fields" class="ap-replan" aria-label="Motivo do replanejamento" hidden>
      <label>Motivo<select id="ap-reason"><option value="">Selecione o motivo</option><option value="AUMENTO_ESCOPO">Aumento de escopo</option><option value="ATRASO_OPERACIONAL">Atraso operacional</option><option value="REDISTRIBUICAO_EQUIPE">Redistribuição de equipe</option><option value="ANTECIPACAO">Antecipação</option><option value="ALTERACAO_PRAZO">Alteração de prazo</option><option value="MUDANCA_PRIORIDADE">Mudança de prioridade</option><option value="OUTRO">Outro</option></select></label>
      <label>Observação<input id="ap-note" maxlength="500" placeholder="Descreva o motivo, se necessário"></label>
      <button type="button" id="ap-cancel-replan" class="ap-button">Descartar alterações locais</button>
    </section>
    <div class="ap-layout">
      <section class="ap-board" aria-label="Planejamento da entrega">
        <div class="ap-board-head">
          <div class="ap-tabs" role="tablist" aria-label="Visualização da entrega">
            <button type="button" role="tab" id="ap-tab-calendar" aria-controls="ap-calendar-view" aria-selected="true">Planejamento</button>
            <button type="button" role="tab" id="ap-tab-list" aria-controls="ap-list-view" aria-selected="false" tabindex="-1">Lista de imagens</button>
          </div>
          <div class="ap-actions">
            <button class="ap-button" type="button" id="ap-more">Mais ações</button>
            <button type="button" id="ap-replan" class="ap-button ap-primary" hidden>Replanejar</button>
            <button type="button" id="ap-save" class="ap-button">Salvar rascunho</button>
            <button type="button" id="ap-confirm" class="ap-button ap-primary">Confirmar planejamento</button>
          </div>
        </div>
        <div id="ap-calendar-view" role="tabpanel" aria-labelledby="ap-tab-calendar">
          <div class="ap-calendar-toolbar">
            <div class="ap-calendar-nav">
              <button class="ap-button" id="ap-day-prev" type="button" aria-label="Dias anteriores">‹</button>
              <select id="ap-mobile-day" aria-label="Primeiro dia do período"></select>
              <button class="ap-button" id="ap-day-next" type="button" aria-label="Próximos dias">›</button>
              <button class="ap-button ap-today-button" id="ap-today" type="button">Hoje</button>
            </div>
            <button class="ap-button" id="ap-add-person" type="button">Adicionar colaborador</button>
          </div>
          <div class="ap-calendar-workspace">
            <aside class="ap-image-rail" aria-labelledby="ap-rail-title">
              <div class="ap-rail-controls">
                <div class="ap-section-head"><h2 id="ap-rail-title">Imagens da entrega</h2><span id="ap-rail-count"></span></div>
                <label>Pesquisar imagem<input id="ap-rail-search" type="search" placeholder="Nome da imagem"></label>
                <label>Nível<select id="ap-rail-level"><option value="">Todos</option><option value="1">N1</option><option value="2">N2</option><option value="3">N3</option><option value="4">N4</option><option value="5">N5</option><option value="none">Sem classificação</option></select></label>
                <label class="ap-rail-check"><input id="ap-rail-pending" type="checkbox"> Somente não planejadas</label>
              </div>
              <div id="ap-rail-items" class="ap-rail-items" aria-label="Imagens disponíveis para planejamento"></div>
            </aside>
            <div class="ap-grid-scroll" id="ap-grid-scroll"><div id="ap-grid" class="ap-grid" aria-label="Agenda por colaborador e dia"></div></div>
          </div>
        </div>
        <div id="ap-list-view" role="tabpanel" aria-labelledby="ap-tab-list" hidden>
          <div class="ap-filters" role="search" aria-label="Filtrar imagens">
            <label class="ap-search">Pesquisar imagem<input id="ap-search" type="search" placeholder="Nome da imagem"></label>
            <label>Nível<select id="ap-level"><option value="">Todos</option><option value="1">N1</option><option value="2">N2</option><option value="3">N3</option><option value="4">N4</option><option value="5">N5</option><option value="none">Sem classificação</option></select></label>
            <label>Responsável<select id="ap-person"><option value="">Todos</option></select></label>
            <label>Situação<select id="ap-pending"><option value="">Todas</option><option value="planejada">Planejadas</option><option value="pendente">Pendentes</option><option value="bloqueada">Bloqueadas</option><option value="responsavel">Sem responsável</option><option value="data">Sem dia planejado</option></select></label>
          </div>
          <fieldset class="ap-bulk" id="ap-bulk"><legend>Ações nas imagens selecionadas</legend><strong id="ap-selection">0 selecionadas</strong>
          <label>Responsável<select id="ap-bulk-person"><option value="">Manter responsável</option></select></label>
          <label hidden>Início opcional<input type="date" id="ap-bulk-start"></label>
          <label>Dia planejado<input type="date" id="ap-bulk-finish"></label>
          <button class="ap-button" type="button" id="ap-apply">Aplicar</button><button class="ap-button" type="button" id="ap-clear">Limpar decisões</button>
        </fieldset>
          <div class="ap-list-head"><label><input type="checkbox" id="ap-select-all"> Selecionar visíveis</label><span id="ap-visible"></span></div>
          <div id="ap-rows" class="ap-list" aria-label="Imagens da entrega"></div>
          <p id="ap-empty" class="ap-empty" hidden>Nenhuma imagem corresponde aos filtros.</p>
        </div>
        <footer class="ap-board-footer"><span id="ap-status"></span><span id="ap-save-status" aria-live="polite"></span><span id="ap-partial" hidden></span></footer>
      </section>
      <aside id="ap-editor" class="ap-editor" aria-labelledby="ap-editor-title" hidden>
        <header class="ap-section-head"><h2 id="ap-editor-title">Editar planejamento</h2><button type="button" id="ap-editor-close" class="ap-button" aria-label="Fechar painel">×</button></header>
        <form id="ap-editor-form">
          <div id="ap-editor-image" class="ap-editor-image"></div>
          <label>Responsável<select id="ap-editor-person"></select></label>
          <div class="ap-editor-meta"><div>Nível<strong id="ap-editor-level"></strong></div><div>Pontos<strong id="ap-editor-points"></strong></div></div>
          <label>Dia planejado<input type="date" id="ap-editor-day"></label>
          <div>Situação<div id="ap-editor-status"></div></div>
          <label class="ap-editor-pin"><input type="checkbox" id="ap-editor-pin"> Fixar no planejamento</label>
          <div class="ap-editor-actions">
            <button class="ap-button" type="button" id="ap-editor-remove">Remover do dia</button>
            <button class="ap-button ap-primary" type="submit" id="ap-editor-save">Aplicar alterações</button>
          </div>
        </form>
      </aside>
    </div>
  </div>
  <dialog id="ap-more-dialog" class="ap-dialog" aria-labelledby="ap-more-title">
    <div class="ap-section-head"><h2 id="ap-more-title">Mais ações</h2><button class="ap-button" type="button" id="ap-more-close">Fechar</button></div>
    <p>Retorno <strong id="ap-received">—</strong> · <span id="ap-missing"></span> · <span id="ap-allocated"></span></p>
    <progress id="ap-progress" max="1" value="0" aria-label="Imagens com planejamento completo"></progress>
    <button class="ap-button" type="button" id="ap-realized">Planejado × executado</button>
    <details class="ap-extra-summary"><summary>Resumo por nível</summary><div id="ap-level-summary" class="ap-level-summary"></div></details>
    <details class="ap-suggestion"><summary>Sugestão automática</summary>
        <div class="ap-planner-footer"><div class="ap-suggest-controls"><button class="ap-button ap-primary" id="ap-suggest" type="button">Gerar sugestão</button><label>Ordem das imagens<select id="ap-suggest-order"><option value="MAIOR">Maior complexidade primeiro</option><option value="MENOR">Menor complexidade primeiro</option></select></label></div><details class="ap-type-rules"><summary>Distribuir por tipo de imagem</summary><p>Escolha um responsável para cada tipo que deseja manter junto. Os demais serão distribuídos automaticamente.</p><div id="ap-type-rules" class="ap-type-rules-fields"></div></details><details class="ap-type-rules"><summary>Priorizar tipos de imagem</summary><p>Defina a ordem usada ao distribuir as imagens. Dentro de cada tipo, vale a ordem de complexidade escolhida.</p><label class="ap-priority-toggle"><input type="checkbox" id="ap-type-priority-enabled"> Usar prioridade por tipo</label><ol id="ap-type-priority" class="ap-type-priority" aria-label="Ordem de prioridade dos tipos"></ol></details><strong id="ap-team-capacity"></strong><span id="ap-grid-summary"></span></div>
    </details>
    <details class="ap-distribution" aria-labelledby="ap-distribution-title"><summary id="ap-distribution-title">Distribuição atual</summary><p>Pontos, imagens e níveis por responsável.</p><div id="ap-distribution"></div><small>Capacidade diária considera compromissos confirmados. A conclusão das alterações é diferente da entrega ao cliente.</small></details>
  </dialog>
  <dialog id="ap-picker" class="ap-dialog" aria-labelledby="ap-picker-title">
    <div class="ap-section-head"><h2 id="ap-picker-title">Adicionar imagens</h2><button class="ap-button" type="button" id="ap-picker-close">Fechar</button></div>
    <p id="ap-picker-destination"></p>
    <label>Pesquisar imagem<input id="ap-picker-search" type="search" placeholder="Nome da imagem"></label>
    <div id="ap-picker-items"></div>
    <button class="ap-button ap-primary" type="button" id="ap-picker-apply">Adicionar seleção</button>
  </dialog>
</main>
<dialog id="ap-history-dialog" class="ap-dialog" aria-labelledby="ap-history-title"><div class="ap-section-head"><h2 id="ap-history-title">Histórico do planejamento</h2><button class="ap-button" type="button" id="ap-close-history">Fechar</button></div><div id="ap-history-list"></div><div id="ap-history-snapshot"></div></dialog>
<dialog id="ap-people-dialog" class="ap-dialog" aria-labelledby="ap-people-title"><div class="ap-section-head"><h2 id="ap-people-title">Colaboradores de Alteração</h2><button type="button" id="ap-close-people" class="ap-button">Fechar</button></div><p>Disponibilidade no dia selecionado. Os compromissos são calculados por dia.</p><div id="ap-people-list"></div></dialog>
<dialog id="ap-classification-dialog" class="ap-dialog" aria-labelledby="ap-classification-title" aria-describedby="ap-classification-description">
  <form id="ap-classification-form" novalidate>
    <header class="ap-classification-header">
      <h2 id="ap-classification-title">Classificar imagens</h2>
      <p id="ap-classification-description"></p>
    </header>
    <div id="ap-classification-workspace">
      <div class="ap-classification-tools">
        <div class="ap-classification-filters">
          <label>Buscar imagem<input type="search" id="ap-classification-search" placeholder="Nome da imagem"></label>
          <label>Tipo de imagem<select id="ap-classification-type"><option value="">Todos os tipos</option></select></label>
          <label>Nível<select id="ap-classification-filter"></select></label>
        </div>
        <div class="ap-classification-batch">
          <label class="ap-classification-check"><input type="checkbox" id="ap-classification-select-all"> Selecionar visíveis</label>
          <span id="ap-classification-selected" role="status"></span>
          <div class="ap-classification-apply">
            <select id="ap-classification-batch-level" aria-label="Nível para as imagens selecionadas"></select>
            <button type="button" id="ap-classification-apply" class="ap-button" disabled>Aplicar</button>
          </div>
        </div>
      </div>
      <div class="ap-classification-list">
        <p id="ap-classification-visible" role="status"></p>
        <div id="ap-classification-items"></div>
        <div id="ap-classification-empty" hidden>
          <p>Nenhuma imagem corresponde aos filtros.</p>
          <button type="button" id="ap-classification-reset" class="ap-button">Limpar filtros</button>
        </div>
      </div>
    </div>
    <div class="ap-classification-actions">
      <p id="ap-classification-error" role="alert" hidden></p>
      <p id="ap-classification-progress" role="status"></p>
      <div class="ap-classification-buttons">
        <button type="button" id="ap-classification-close" class="ap-button">Agora não</button>
        <button type="submit" id="ap-classification-submit" class="ap-button ap-primary">Salvar classificação</button>
      </div>
    </div>
  </form>
</dialog>
<script src="<?= asset_url('../assets/js/thinking-orbs.js') ?>"></script>
<script src="alteracao.js?v=<?= filemtime(__DIR__ . '/alteracao.js') ?>" defer></script>
