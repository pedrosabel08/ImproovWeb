<?php
// Partial rendered exclusively by the authenticated index.php document.
if (!isset($planejamentoPorImagem) || !$planejamentoPorImagem) {
    http_response_code(404);
    exit;
}
?>
<main class="planning-shell alteration-plan" id="alteration-plan" aria-labelledby="alteration-title">
  <header class="ap-header">
    <div><h1 id="alteration-title">Planejar entrega</h1><p id="ap-project">Planejamento de alterações</p></div>
    <nav class="ap-actions" aria-label="Ações do planejamento">
      <a class="ap-button" href="../Dashboard/obra.php?obra_id=<?= $obraId ?>">Voltar à obra</a>
      <button class="ap-button" type="button" id="ap-history">Histórico</button>
      <button class="ap-button" type="button" id="ap-realized">Planejado × executado</button>
      <button class="ap-button" type="button" id="ap-theme" aria-label="Alternar tema">Tema</button>
      <a class="ap-button" href="../PlanejamentoCapacidade/index.php#alteracao-config">Configurar capacidade</a>
    </nav>
  </header>

  <div id="ap-loading-home"><div id="ap-loading" class="ap-loading" role="status"><canvas data-thinking-orb data-orb-state="composing" data-orb-size="42" data-orb-theme="auto" aria-label="Carregando planejamento"></canvas><span>Carregando planejamento…</span></div></div>
  <div id="ap-message" class="ap-message" role="status" hidden></div>
  <button id="ap-reload" type="button" class="ap-button" hidden>Recarregar dados da entrega</button>

  <div id="ap-content" hidden>
    <section class="ap-overview" aria-label="Resumo da entrega">
      <dl><div><dt>Retorno recebido</dt><dd id="ap-received">—</dd></div><div><dt>Prazo cliente</dt><dd id="ap-due">—</dd></div><div><dt>Demanda da entrega</dt><dd id="ap-demand">—</dd></div></dl>
      <div class="ap-progress"><strong id="ap-complete">0/0 completos</strong><span id="ap-status">Planejamento pendente</span><progress id="ap-progress" max="1" value="0" aria-label="Imagens com planejamento completo"></progress><small id="ap-missing"></small></div>
      <div class="ap-save"><span id="ap-save-status" aria-live="polite"></span><div class="ap-actions"><button type="button" id="ap-replan" class="ap-button" hidden>Replanejar</button><button type="button" id="ap-save" class="ap-button">Salvar rascunho</button><button type="button" id="ap-confirm" class="ap-button ap-primary">Confirmar planejamento</button></div></div>
    </section>
    <section class="ap-point-overview" aria-label="Demanda e conclusão"><div id="ap-level-summary" class="ap-level-summary"></div><div class="ap-forecast"><span>Previsão de conclusão das alterações</span><strong id="ap-forecast">—</strong><span id="ap-margin">Planeje as imagens para calcular a margem</span><strong id="ap-allocated">0 pontos alocados</strong></div></section>

    <section id="ap-replan-fields" class="ap-replan" aria-label="Motivo do replanejamento" hidden>
      <label>Motivo<select id="ap-reason"><option value="">Selecione o motivo</option><option value="AUMENTO_ESCOPO">Aumento de escopo</option><option value="ATRASO_OPERACIONAL">Atraso operacional</option><option value="REDISTRIBUICAO_EQUIPE">Redistribuição de equipe</option><option value="ANTECIPACAO">Antecipação</option><option value="ALTERACAO_PRAZO">Alteração de prazo</option><option value="MUDANCA_PRIORIDADE">Mudança de prioridade</option><option value="OUTRO">Outro</option></select></label>
      <label>Observação<input id="ap-note" maxlength="500" placeholder="Descreva o motivo, se necessário"></label>
      <button type="button" id="ap-cancel-replan" class="ap-button">Descartar alterações locais</button>
    </section>

    <div class="ap-daily-workspace">
      <details class="ap-backlog" open><summary>Imagens a planejar <span id="ap-backlog-count"></span></summary>
        <label class="ap-backlog-search">Pesquisar imagem<input type="search" id="ap-backlog-search" placeholder="Nome da imagem"></label>
        <div id="ap-backlog-levels" class="ap-level-tabs" role="group" aria-label="Filtrar backlog por nível"></div>
        <label class="ap-backlog-toggle"><input id="ap-backlog-pending" type="checkbox" checked> Somente não planejadas</label>
        <div id="ap-backlog" class="ap-backlog-list" aria-label="Imagens disponíveis para planejamento"></div>
        <p class="ap-help">Selecione imagens e use “Adicionar seleção” em um dia. Também é possível arrastar os cards.</p>
      </details>
      <section class="ap-planner" aria-labelledby="ap-planner-title">
        <div class="ap-section-head"><h2 id="ap-planner-title">Planejamento por dia e colaborador</h2><div class="ap-actions"><button class="ap-button" id="ap-add-person" type="button">Adicionar colaborador</button><button class="ap-button" id="ap-more-days" type="button">Adicionar dia +</button></div></div>
        <div class="ap-calendar-nav"><button class="ap-button" id="ap-day-prev" type="button" aria-label="Dias anteriores">‹</button><label>Dia selecionado<select id="ap-mobile-day"></select></label><button class="ap-button" id="ap-day-next" type="button" aria-label="Próximos dias">›</button><span id="ap-period-label"></span></div>
        <div class="ap-grid-scroll" id="ap-grid-scroll"><div id="ap-grid" class="ap-grid" aria-label="Agenda por colaborador e dia"></div></div>
        <div class="ap-planner-footer"><div class="ap-suggest-controls"><button class="ap-button ap-primary" id="ap-suggest" type="button">Gerar sugestão</button><label>Ordem das imagens<select id="ap-suggest-order"><option value="MAIOR">Maior complexidade primeiro</option><option value="MENOR">Menor complexidade primeiro</option></select></label></div><details class="ap-type-rules"><summary>Distribuir por tipo de imagem</summary><p>Escolha um responsável para cada tipo que deseja manter junto. Os demais serão distribuídos automaticamente.</p><div id="ap-type-rules" class="ap-type-rules-fields"></div></details><details class="ap-type-rules"><summary>Priorizar tipos de imagem</summary><p>Defina a ordem usada ao distribuir as imagens. Dentro de cada tipo, vale a ordem de complexidade escolhida.</p><label class="ap-priority-toggle"><input type="checkbox" id="ap-type-priority-enabled"> Usar prioridade por tipo</label><ol id="ap-type-priority" class="ap-type-priority" aria-label="Ordem de prioridade dos tipos"></ol></details><div id="ap-suggestion-loading"></div><strong id="ap-team-capacity"></strong><span id="ap-grid-summary"></span></div>
      </section>
    </div>
    <div class="ap-workspace">
      <section class="ap-images" aria-labelledby="ap-images-title">
        <div class="ap-section-head"><h2 id="ap-images-title">Detalhes das imagens</h2><span id="ap-visible"></span></div>
        <div class="ap-filters" role="search" aria-label="Filtrar imagens">
          <label class="ap-search">Pesquisar imagem<input id="ap-search" type="search" placeholder="Nome da imagem"></label>
          <label>Pendências<select id="ap-pending"><option value="">Todas</option><option value="responsavel">Sem responsável</option><option value="data">Sem dia planejado</option></select></label>
          <label>Nível<select id="ap-level"><option value="">Todos</option><option value="1">N1</option><option value="2">N2</option><option value="3">N3</option><option value="4">N4</option><option value="5">N5</option><option value="none">Sem classificação</option></select></label>
          <label>Colaborador<select id="ap-person"><option value="">Todos</option></select></label>
        </div>
        <fieldset class="ap-bulk" id="ap-bulk"><legend>Ações nas imagens selecionadas</legend><strong id="ap-selection">0 selecionadas</strong>
          <label>Responsável<select id="ap-bulk-person"><option value="">Manter responsável</option></select></label>
          <label hidden>Início opcional<input type="date" id="ap-bulk-start"></label>
          <label>Dia planejado<input type="date" id="ap-bulk-finish"></label>
          <button class="ap-button" type="button" id="ap-apply">Aplicar</button><button class="ap-button" type="button" id="ap-clear">Limpar decisões</button>
        </fieldset>
        <div class="ap-table-wrap"><table class="ap-table"><thead><tr><th><input type="checkbox" id="ap-select-all" aria-label="Selecionar todas as imagens filtradas"></th><th>Imagem</th><th>Nível</th><th>Responsável</th><th>Pontos</th><th>Dia planejado</th><th>Situação / Fixar</th></tr></thead><tbody id="ap-rows"></tbody></table></div>
        <p id="ap-empty" class="ap-empty" hidden>Nenhuma imagem corresponde aos filtros.</p>
        <p class="ap-help">O rascunho guarda suas decisões. A confirmação aplica os responsáveis e libera a execução desta entrega.</p>
      </section>
      <aside class="ap-distribution" aria-labelledby="ap-distribution-title"><h2 id="ap-distribution-title">Distribuição atual</h2><p>Pontos, imagens e níveis por responsável.</p><div id="ap-distribution"></div><small>Capacidade diária considera compromissos confirmados. A conclusão das alterações é diferente da entrega ao cliente.</small></aside>
    </div>
  </div>
</main>
<dialog id="ap-history-dialog" class="ap-dialog" aria-labelledby="ap-history-title"><div class="ap-section-head"><h2 id="ap-history-title">Histórico do planejamento</h2><button class="ap-button" type="button" id="ap-close-history">Fechar</button></div><div id="ap-history-list"></div><div id="ap-history-snapshot"></div></dialog>
<dialog id="ap-people-dialog" class="ap-dialog" aria-labelledby="ap-people-title"><div class="ap-section-head"><h2 id="ap-people-title">Colaboradores de Alteração</h2><button type="button" id="ap-close-people" class="ap-button">Fechar</button></div><p>Disponibilidade no dia selecionado. Os compromissos são calculados por dia.</p><div id="ap-people-list"></div></dialog>
<script src="<?= asset_url('../assets/js/thinking-orbs.js') ?>"></script>
<script src="alteracao.js?v=<?= filemtime(__DIR__ . '/alteracao.js') ?>" defer></script>
