<?php
// Partial rendered exclusively by the authenticated index.php document.
if (!isset($planejamentoPorImagem) || !$planejamentoPorImagem) {
    http_response_code(404);
    exit;
}
?>
<main class="planning-shell alteration-plan" id="alteration-plan" aria-labelledby="alteration-title">
  <header class="ap-header">
    <div class="ap-title-block"><span>Produção / Planejamento</span><h1 id="alteration-title">Planejar entrega</h1><p id="ap-project"></p></div>
    <section class="ap-kpis" aria-label="Resumo da entrega">
      <div><i class="fa-regular fa-calendar" aria-hidden="true"></i><span>Prazo do cliente<strong id="ap-due">—</strong></span></div>
      <div><i class="fa-regular fa-clock" aria-hidden="true"></i><span>Previsão atual<strong id="ap-forecast">—</strong></span></div>
      <div><i class="fa-solid fa-chart-line" aria-hidden="true"></i><span>Margem<strong id="ap-margin">—</strong></span></div>
      <div><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span>Imagens planejadas<strong id="ap-complete">—</strong></span></div>
    </section>
    <div class="ap-actions ap-header-actions">
      <button type="button" id="ap-save" class="ap-button" hidden>Salvar rascunho</button>
      <button type="button" id="ap-confirm" class="ap-button ap-primary" hidden><i class="fa-solid fa-check" aria-hidden="true"></i> Confirmar</button>
      <button type="button" id="ap-replan" class="ap-button ap-primary" hidden>Replanejar</button>
    </div>
  </header>

  <div id="ap-loading-home"><div id="ap-loading" class="ap-loading" role="status"><canvas data-thinking-orb data-orb-state="composing" data-orb-size="42" data-orb-theme="auto" aria-label="Carregando planejamento"></canvas><span>Carregando planejamento…</span></div></div>
  <button id="ap-reload" type="button" class="ap-button" hidden>Recarregar dados da entrega</button>

  <div id="ap-content" hidden>
    <div class="ap-layout">
      <section class="ap-board" aria-label="Planejamento da entrega">
        <div class="ap-calendar-toolbar">
          <div class="ap-calendar-nav">
            <button class="ap-button" id="ap-day-prev" type="button" aria-label="Período anterior"><i class="fa-solid fa-angle-left" aria-hidden="true"></i></button>
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <select id="ap-mobile-day" aria-label="Primeiro dia do período"></select>
            <button class="ap-button" id="ap-day-next" type="button" aria-label="Próximo período"><i class="fa-solid fa-angle-right" aria-hidden="true"></i></button>
            <button class="ap-button ap-today-button" id="ap-today" type="button">Hoje</button>
          </div>
          <div class="ap-toolbar-actions">
            <button class="ap-button" id="ap-add-person" type="button" title="Adicionar colaborador"><i class="fa-solid fa-user-plus" aria-hidden="true"></i><span>Colaborador</span></button>
            <div role="tablist" class="ap-tabs" aria-label="Visualização">
              <button type="button" role="tab" id="ap-tab-calendar" aria-controls="ap-calendar-view" aria-selected="true" aria-label="Calendário" title="Calendário"><i class="fa-solid fa-table-cells-large" aria-hidden="true"></i></button>
              <button type="button" role="tab" id="ap-tab-list" aria-controls="ap-list-view" aria-selected="false" tabindex="-1" aria-label="Lista de imagens" title="Lista de imagens"><i class="fa-solid fa-list" aria-hidden="true"></i></button>
            </div>
            <details class="ap-toolbar-filters">
              <summary class="ap-button" aria-label="Filtros da grade" title="Filtros da grade"><i class="fa-solid fa-sliders" aria-hidden="true"></i></summary>
              <div><label><input type="checkbox" id="ap-show-external" checked> Exibir outras entregas</label></div>
            </details>
            <button class="ap-button" type="button" id="ap-more"><span>Mais ações</span><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>
          </div>
        </div>
        <div id="ap-calendar-view" role="tabpanel" aria-labelledby="ap-tab-calendar">
          <div class="ap-calendar-workspace">
            <aside class="ap-image-rail" aria-labelledby="ap-rail-title">
              <div class="ap-rail-heading">
                <div><h2 id="ap-rail-title">Imagens da entrega</h2><span id="ap-rail-count"></span></div>
                <button class="ap-button" type="button" id="ap-rail-toggle" aria-expanded="true" aria-controls="ap-rail-controls ap-rail-items" aria-label="Recolher imagens" title="Recolher imagens"><i class="fa-solid fa-angles-left" aria-hidden="true"></i></button>
              </div>
              <div class="ap-rail-controls" id="ap-rail-controls">
                <div class="ap-rail-filters">
                  <label><span class="ap-sr-only">Pesquisar imagem</span><input id="ap-rail-search" type="search" placeholder="Pesquisar imagem…"></label>
                  <label><span class="ap-sr-only">Nível</span><select id="ap-rail-level"><option value="">Nível</option><option value="1">N1</option><option value="2">N2</option><option value="3">N3</option><option value="4">N4</option><option value="5">N5</option><option value="none">Sem classificação</option></select></label>
                </div>
                <div id="ap-rail-states" class="ap-rail-states" aria-label="Situação da imagem">
                  <button type="button" data-rail-state="" aria-pressed="true">Todas <span></span></button>
                  <button type="button" data-rail-state="pending" aria-pressed="false">Não planejadas <span></span></button>
                  <button type="button" data-rail-state="planned" aria-pressed="false">Planejadas <span></span></button>
                  <button type="button" data-rail-state="done" aria-pressed="false">Concluídas <span></span></button>
                </div>
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
          <div class="ap-list-head"><label><input type="checkbox" id="ap-select-all"> Selecionar visíveis</label><span id="ap-visible"></span></div>
          <div id="ap-rows" class="ap-list" aria-label="Imagens da entrega"></div>
          <p id="ap-empty" class="ap-empty" hidden>Nenhuma imagem corresponde aos filtros.</p>
        </div>
        <footer class="ap-board-footer"><span id="ap-status"></span><span id="ap-save-status" aria-live="polite"></span><span id="ap-demand"></span><span id="ap-partial" hidden></span><span id="ap-week-capacity"></span></footer>
        <div class="ap-bulk-bar" id="ap-bulk" popover="manual" aria-label="Ações nas imagens selecionadas">
          <strong id="ap-selection" aria-live="polite"></strong>
          <button class="ap-button ap-primary" type="button" id="ap-bulk-replan"><i class="fa-regular fa-calendar" aria-hidden="true"></i> Replanejar</button>
          <button class="ap-button" type="button" id="ap-bulk-assign"><i class="fa-regular fa-user" aria-hidden="true"></i> Alterar responsável</button>
          <button class="ap-button ap-danger-action" type="button" id="ap-clear"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Remover do planejamento</button>
          <button class="ap-button" type="button" id="ap-deselect">Desmarcar</button>
        </div>
      </section>
      <aside id="ap-editor" class="ap-editor" aria-labelledby="ap-editor-title" role="dialog" popover="manual">
        <header class="ap-section-head"><h2 id="ap-editor-title">Editar planejamento</h2><button type="button" id="ap-editor-close" class="ap-button" aria-label="Fechar edição">×</button></header>
        <form id="ap-editor-form">
          <div id="ap-editor-image" class="ap-editor-image"></div>
          <div class="ap-editor-fields">
            <label>Responsável<select id="ap-editor-person"></select></label>
            <label>Dia planejado<input type="date" id="ap-editor-day"></label>
          </div>
          <div class="ap-editor-meta">
            <div>Nível<strong id="ap-editor-level"></strong></div>
            <div>Pontos<strong id="ap-editor-points"></strong></div>
            <div>Situação<div id="ap-editor-status"></div></div>
          </div>
          <label class="ap-editor-pin"><input type="checkbox" id="ap-editor-pin"> Fixar no planejamento</label>
          <div class="ap-editor-actions">
            <button class="ap-button" type="button" id="ap-editor-remove">Remover do dia</button>
            <button class="ap-button ap-primary" type="submit" id="ap-editor-save">Aplicar alterações</button>
          </div>
          <button class="ap-button" type="button" id="ap-editor-transfer" hidden>Transferir execução</button>
        </form>
      </aside>
    </div>
  </div>
  <dialog id="ap-more-dialog" class="ap-dialog" aria-labelledby="ap-more-title">
    <div class="ap-section-head"><h2 id="ap-more-title">Mais ações</h2><button class="ap-button" type="button" id="ap-more-close">Fechar</button></div>
    <div class="ap-actions ap-secondary-actions">
      <a class="ap-button" href="../Dashboard/obra.php?obra_id=<?= $obraId ?>">Voltar à obra</a>
      <button class="ap-button" type="button" id="ap-history">Histórico</button>
      <a class="ap-button" id="ap-capacity-settings" href="../PlanejamentoCapacidade/#alteracao-config">Configurar capacidade</a>
      <button class="ap-button" type="button" id="ap-theme" aria-label="Alternar tema"><i class="fa-regular fa-sun" aria-hidden="true"></i> Tema</button>
      <button type="button" id="ap-cancel-replan" class="ap-button">Descartar alterações locais</button>
    </div>
    <p>Retorno <strong id="ap-received">—</strong> · <span id="ap-missing"></span> · <span id="ap-allocated"></span><span id="ap-original-forecast" hidden></span></p>
    <progress id="ap-progress" max="1" value="0" aria-label="Imagens com planejamento completo"></progress>
    <button class="ap-button" type="button" id="ap-realized">Planejado × executado</button>
    <details class="ap-extra-summary"><summary>Resumo por nível</summary><div id="ap-level-summary" class="ap-level-summary"></div></details>
    <details class="ap-suggestion"><summary>Sugestão automática</summary>
        <div class="ap-planner-footer"><div class="ap-suggest-controls"><button class="ap-button ap-primary" id="ap-suggest" type="button">Gerar sugestão</button><label>Ordem das imagens<select id="ap-suggest-order"><option value="MAIOR">Maior complexidade primeiro</option><option value="MENOR">Menor complexidade primeiro</option></select></label></div><details class="ap-type-rules"><summary>Distribuir por tipo de imagem</summary><p>Escolha um responsável para cada tipo que deseja manter junto. Os demais serão distribuídos automaticamente.</p><div id="ap-type-rules" class="ap-type-rules-fields"></div></details><details class="ap-type-rules"><summary>Priorizar tipos de imagem</summary><p>Defina a ordem usada ao distribuir as imagens. Dentro de cada tipo, vale a ordem de complexidade escolhida.</p><label class="ap-priority-toggle"><input type="checkbox" id="ap-type-priority-enabled"> Usar prioridade por tipo</label><ol id="ap-type-priority" class="ap-type-priority" aria-label="Ordem de prioridade dos tipos"></ol></details><strong id="ap-team-capacity"></strong><span id="ap-grid-summary"></span></div>
    </details>
    <details class="ap-distribution" aria-labelledby="ap-distribution-title"><summary id="ap-distribution-title">Distribuição atual</summary><p>Pontos, imagens e níveis por responsável.</p><div id="ap-distribution"></div><small>Capacidade diária considera compromissos confirmados. A conclusão das alterações é diferente da entrega ao cliente.</small></details>
  </dialog>
  <dialog id="ap-bulk-dialog" class="ap-dialog ap-compact-dialog" aria-labelledby="ap-bulk-title">
    <form id="ap-bulk-form">
      <header><h2 id="ap-bulk-title">Replanejar imagens</h2><p id="ap-bulk-points"></p></header>
      <div class="ap-dialog-fields" id="ap-bulk-fields">
        <label id="ap-bulk-date-field">Dia planejado<input type="date" id="ap-bulk-finish" aria-describedby="ap-bulk-date-help"><small id="ap-bulk-date-help">Em branco mantém o dia atual.</small></label>
        <label>Responsável<select id="ap-bulk-person"><option value="">Manter atual</option></select></label>
      </div>
      <p id="ap-bulk-preview" aria-live="polite"></p>
      <div class="ap-editor-actions"><button class="ap-button" type="button" id="ap-bulk-close">Cancelar</button><button class="ap-button ap-primary" type="submit" id="ap-apply">Aplicar alterações</button></div>
    </form>
  </dialog>
  <dialog id="ap-confirm-dialog" class="ap-dialog ap-compact-dialog" aria-labelledby="ap-confirm-title">
    <form id="ap-confirm-form">
      <header><h2 id="ap-confirm-title">Confirmar planejamento</h2><p id="ap-confirm-summary"></p></header>
      <div id="ap-replan-fields" class="ap-dialog-fields" hidden>
      <label>Motivo<select id="ap-reason"><option value="">Selecione o motivo</option><option value="AUMENTO_ESCOPO">Aumento de escopo</option><option value="ATRASO_OPERACIONAL">Atraso operacional</option><option value="REDISTRIBUICAO_EQUIPE">Redistribuição de equipe</option><option value="ANTECIPACAO">Antecipação</option><option value="ALTERACAO_PRAZO">Alteração de prazo</option><option value="MUDANCA_PRIORIDADE">Mudança de prioridade</option><option value="OUTRO">Outro</option></select></label>
      <label>Observação<input id="ap-note" maxlength="500" placeholder="Descreva o motivo, se necessário"></label>
      </div>
      <div class="ap-editor-actions"><button type="button" class="ap-button" id="ap-confirm-close">Cancelar</button><button type="submit" class="ap-button ap-primary">Confirmar planejamento</button></div>
    </form>
  </dialog>
  <dialog id="ap-picker" class="ap-dialog" aria-labelledby="ap-picker-title">
    <div class="ap-section-head"><h2 id="ap-picker-title">Adicionar imagens</h2><button class="ap-button" type="button" id="ap-picker-close">Fechar</button></div>
    <p id="ap-picker-destination"></p>
    <label>Pesquisar imagem<input id="ap-picker-search" type="search" placeholder="Nome da imagem"></label>
    <div id="ap-picker-items"></div>
    <button class="ap-button ap-primary" type="button" id="ap-picker-apply">Adicionar seleção</button>
  </dialog>
</main>
<dialog id="ap-transfer-dialog" class="ap-dialog" aria-labelledby="ap-transfer-title" aria-describedby="ap-transfer-summary">
  <form id="ap-transfer-form">
    <header><h2 id="ap-transfer-title">Transferir execução</h2><p id="ap-transfer-summary"></p></header>
    <label>Novo responsável<select id="ap-transfer-person" required></select></label>
    <label>Previsão de conclusão<input type="date" id="ap-transfer-date" required></label>
    <label>Motivo da transferência<textarea id="ap-transfer-reason" rows="3" maxlength="500" required></textarea></label>
    <p id="ap-transfer-error" role="alert" hidden></p>
    <div id="ap-transfer-loading"></div>
    <div class="ap-editor-actions">
      <button type="button" id="ap-transfer-cancel" class="ap-button">Cancelar</button>
      <button type="submit" id="ap-transfer-submit" class="ap-button ap-primary">Confirmar transferência</button>
    </div>
  </form>
</dialog>
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
