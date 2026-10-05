/* Managerial dashboard. All monetary values and classifications come from PHP. */
document.addEventListener('DOMContentLoaded', () => {
  const container = document.querySelector('.container');
  const header = document.querySelector('.payment-header');
  const filters = document.querySelector('.competencia-bar');
  if (!container || !header || !filters) return;
  const mes = document.getElementById('mes');
  const ano = document.getElementById('ano');
  const colaborador = document.getElementById('colaborador');
  const colabFilter = colaborador.closest('.filter-group');
  const individualCount = filters.querySelector('.competencia-count');
  const money = cents => (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const pct = (part, total) => total > 0 ? Math.max(0, Math.min(100, part / total * 100)) : 0;
  const percentage = value => `${Number(value).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
  const empty = message => `<p class="overview-empty">${escape(message)}</p>`;
  const icon = name => `<i class="fa-solid ${name}" aria-hidden="true"></i>`;
  const number = (value, kind = 'count', suffix = '') => {
    const label = kind === 'money' ? money(value) : kind === 'percent' ? percentage(value) : Number(value).toLocaleString('pt-BR');
    return `<span class="payment-number" data-payment-number="${Number(value)}" data-number-kind="${kind}" data-number-suffix="${escape(suffix)}" role="img" aria-label="${escape(label + suffix)}">${escape(label + suffix)}</span>`;
  };
  let payload = null;
  let controller;

  const individual = document.createElement('section');
  individual.id = 'payment-individual';
  individual.className = 'payment-individual';
  individual.setAttribute('aria-label', 'Pagamento por colaborador');
  container.append(individual);
  [document.querySelector('.financial-summary'), document.querySelector('.table-scroll-area')].forEach(el => individual.append(el));
  const detailNotice = document.createElement('div');
  detailNotice.className = 'overview-notice';
  detailNotice.hidden = true;
  individual.insertBefore(detailNotice, individual.lastElementChild);
  window.addEventListener('pagamento:detalhe', event => {
    const r = event.detail;
    detailNotice.hidden = !r?.divergencias_financeiras;
    detailNotice.textContent = r?.divergencias_financeiras ? `${r.divergencias_financeiras} item(ns) com inconsistência no livro financeiro. Confira os lançamentos antes de pagar. Os valores exibidos preservam os registros existentes.` : '';
  });

  const actions = document.createElement('div');
  actions.className = 'payment-header-actions';
  actions.innerHTML = '<div class="payment-view-toggle" role="group" aria-label="Visão de pagamentos"><button type="button" data-view="geral" aria-controls="payment-overview">Visão geral</button><button type="button" data-view="colaborador" aria-controls="payment-individual">Por colaborador</button></div>';
  const statusButton = document.getElementById('btn-ver-status-geral');
  header.append(actions);
  actions.append(statusButton);
  const subtitle = document.createElement('p');
  subtitle.className = 'payment-subtitle';
  subtitle.textContent = 'Custos de produção, pagamentos e adendos';
  header.querySelector('.payment-heading > div').append(subtitle);
  const overviewCount = document.createElement('span');
  overviewCount.className = 'competencia-count';
  overviewCount.innerHTML = `${icon('fa-layer-group')} <span id="overview-item-count">—</span> itens na competência`;
  filters.append(overviewCount);

  const overview = document.createElement('section');
  overview.id = 'payment-overview';
  overview.className = 'payment-overview';
  overview.setAttribute('aria-label', 'Visão geral de pagamentos');
  overview.innerHTML = `
    <div id="overview-feedback" role="status" aria-live="polite"></div>
    <div class="overview-kpis" id="overview-kpis"></div>
    <div class="overview-grid">
      <article class="overview-card"><header>${icon('fa-chart-simple')}<div><h2>Pagamento do mês</h2><p>Distribuição financeira dos itens da competência</p></div></header><div id="overview-month"></div></article>
      <article class="overview-card"><header>${icon('fa-chart-pie')}<div><h2>Status dos pagamentos</h2><p>Quantidade de itens por situação financeira</p></div></header><div id="overview-status"></div></article>
      <article class="overview-card"><header>${icon('fa-file-contract')}<div><h2>Status dos adendos</h2><p>Situação documental nesta competência</p></div></header><div id="overview-amendments"></div></article>
      <article class="overview-card overview-functions"><header>${icon('fa-cube')}<div><h2>Custo por função</h2><p>Valores salvos nos itens, em ordem de custo</p></div></header><div id="overview-functions"></div></article>
      <article class="overview-card"><header>${icon('fa-users')}<div><h2>Top colaboradores do mês</h2><p>Selecione uma pessoa para consultar os detalhes</p></div></header><div id="overview-top"></div></article>
    </div>
    <section class="overview-card overview-operational" aria-labelledby="operational-title">
      <header>${icon('fa-user-group')}<div><h2 id="operational-title">Resumo operacional</h2><p>Consolidado por colaborador nesta competência</p></div><span id="overview-results" class="overview-results"></span></header>
      <div class="overview-local-filters">
        <label class="overview-search">${icon('fa-magnifying-glass')}<span class="sr-only">Buscar colaborador</span><input type="search" id="overview-search" placeholder="Buscar colaborador…"></label>
        <label><span class="sr-only">Filtrar por função</span><select id="overview-role"><option value="">Todas as funções</option></select></label>
        <label><span class="sr-only">Filtrar por obra</span><select id="overview-work"><option value="">Todas as obras</option></select></label>
        <label class="toggle-filter"><input type="checkbox" id="overview-pending"><span></span> Somente pendentes</label>
        <button type="button" class="btn btn-secondary" id="overview-clear">${icon('fa-filter-circle-xmark')} Limpar filtros</button>
      </div>
      <div class="overview-table-wrap"><table class="data-table overview-table"><thead><tr><th>Colaborador</th><th class="col-right">Itens</th><th class="col-right">Total</th><th class="col-right">Pago</th><th class="col-right">Pendente</th><th class="col-right">Adendos</th><th>Situação financeira</th></tr></thead><tbody id="overview-rows"></tbody></table></div>
      <p class="overview-table-note">Os filtros selecionam colaboradores. Cada linha mantém os valores integrais da competência; os indicadores gerais não mudam.</p>
    </section>
    <p class="overview-footnote">Pago inclui a liquidação dos itens selecionados, mesmo em outro mês. Adendos documentam valores e não são somados ao custo.</p>`;
  container.append(overview);
  const search = document.getElementById('overview-search');
  const role = document.getElementById('overview-role');
  const work = document.getElementById('overview-work');
  const pending = document.getElementById('overview-pending');
  const setHtml = (id, html) => { document.getElementById(id).innerHTML = html; };

  function persist() {
    const url = new URL(location.href);
    url.searchParams.set('view', document.body.dataset.paymentView);
    url.searchParams.set('mes', mes.value);
    url.searchParams.set('ano', ano.value);
    if (colaborador.value) url.searchParams.set('colaborador_id', colaborador.value);
    else url.searchParams.delete('colaborador_id');
    history.replaceState(null, '', url);
  }

  function setView(view) {
    document.body.dataset.paymentView = view;
    overview.hidden = view !== 'geral';
    individual.hidden = view !== 'colaborador';
    colabFilter.hidden = view !== 'colaborador';
    individualCount.hidden = view !== 'colaborador';
    overviewCount.hidden = view !== 'geral';
    actions.querySelectorAll('[data-view]').forEach(button => {
      button.classList.toggle('is-active', button.dataset.view === view);
      button.setAttribute('aria-pressed', String(button.dataset.view === view));
    });
    persist();
    if (view === 'geral') { overview.scrollTop = 0; load(); }
    else { controller?.abort(); window.pagamentoMotion?.stop(overview); window.carregarDadosColab?.(); }
  }

  function drillDown(id) {
    if (!Array.from(colaborador.options).some(option => option.value === String(id))) {
      const person = payload?.colaboradores.find(c => String(c.colaborador_id) === String(id));
      if (person) colaborador.add(new Option(person.nome, String(id)));
    }
    colaborador.value = String(id);
    setView('colaborador');
    colaborador.focus();
  }

  function kpi(label, value, detail, tone, symbol, kind = 'money', suffix = '') {
    return `<article class="overview-kpi ${tone}"><span class="overview-kpi-icon">${icon(symbol)}</span><div><h2>${escape(label)}</h2><strong>${number(value, kind, suffix)}</strong><p>${escape(detail)}</p></div></article>`;
  }

  function metricBar(label, count, total, tone) {
    const percent = pct(count, total);
    return `<div class="overview-status-row ${tone}"><span class="overview-status-label"><b class="overview-dot" aria-hidden="true"></b>${escape(label)}</span><strong>${number(count)}</strong><span class="overview-track" aria-hidden="true"><span style="width:${percent}%"></span></span><small>${number(percent, 'percent')}</small></div>`;
  }

  function render(data) {
    const r = data.resumo;
    const docs = data.adendos;
    document.getElementById('overview-item-count').textContent = r.itens;
    setHtml('overview-kpis', [
      kpi('Custo total da produção', r.total, `${r.itens} itens`, 'cost', 'fa-layer-group'),
      kpi('Pago', r.pago, `${r.itens_pagos} itens quitados${r.percentual_pago === null ? '' : ` · ${percentage(r.percentual_pago)} do valor`}`, 'paid', 'fa-circle-check'),
      kpi('Pendente', r.pendente, `${r.itens_pendentes} itens${r.percentual_pendente === null ? '' : ` · ${percentage(r.percentual_pendente)} do valor`}`, 'pending', 'fa-clock'),
      kpi('Divergências', r.divergencias, r.divergencias ? 'Itens que precisam de conferência' : 'Nenhuma divergência identificada', 'danger', 'fa-triangle-exclamation', 'count', ' ocorrências'),
      kpi('Adendos', docs.total, `${docs.nao_assinados} não assinados`, 'documents', 'fa-file-lines', 'count', ' registros'),
    ].join(''));
    setHtml('overview-feedback', !r.itens ? empty('Nenhum item de pagamento encontrado nesta competência.') : (r.divergencias_financeiras ? `<div class="overview-notice">${icon('fa-triangle-exclamation')} ${r.divergencias_financeiras} item(ns) com inconsistência no livro financeiro. ${r.excesso > 0 ? `Há ${escape(money(r.excesso))} pagos acima dos valores salvos. ` : ''}Confira os colaboradores com divergência.</div>` : ''));
    let distribution = empty(r.itens ? 'A distribuição financeira não está disponível para estes valores. Confira as divergências.' : 'Nenhum pagamento encontrado nesta competência.');
    if (r.grafico_financeiro_disponivel) {
      distribution = `<div class="overview-segment" role="img" aria-label="${percentage(r.percentual_pago)} pago e ${percentage(r.percentual_pendente)} pendente em valores"><span class="paid" style="width:${pct(r.pago, r.total)}%">${r.percentual_pago >= 12 ? percentage(r.percentual_pago) : ''}</span><span class="pending" style="width:${pct(r.pendente, r.total)}%">${r.percentual_pendente >= 12 ? percentage(r.percentual_pendente) : ''}</span></div>`;
    }
    setHtml('overview-month', `${distribution}<div class="overview-month-legend"><div class="paid"><span>${icon('fa-circle')} Pago</span><strong>${number(r.pago, 'money')}</strong><small>${r.itens_pagos} itens quitados</small></div><div class="pending"><span>${icon('fa-circle')} Pendente</span><strong>${number(r.pendente, 'money')}</strong><small>${r.itens_pendentes} itens</small></div></div>`);
    setHtml('overview-status', r.itens ? `${metricBar('Pagos', r.itens_pagos, r.itens, 'paid')}${metricBar('Pendentes', r.itens_pendentes, r.itens, 'pending')}${metricBar('Com divergência', r.divergencias, r.itens, 'danger')}<p class="overview-small-note">Divergências podem ocorrer em itens pagos ou pendentes.</p>` : empty('Nenhum item nesta competência.'));
    const states = Object.entries(docs.status).filter(([, count]) => count > 0);
    setHtml('overview-amendments', docs.total ? states.map(([state, count]) => metricBar(adendoStatusInfo(state).label, count, docs.total, state === 'assinado' ? 'paid' : ['recusado', 'expirado'].includes(state) ? 'danger' : state === 'nao_gerado' ? 'pending' : 'documents')).join('') : empty('Nenhum adendo registrado nesta competência.'));
    setHtml('overview-functions', data.funcoes.length ? `<div class="overview-rank-list">${data.funcoes.map(item => `<div class="overview-function-row"><span title="${escape(item.nome)}">${escape(item.nome)}</span><span class="overview-track" aria-hidden="true"><span style="width:${pct(item.total, r.total)}%"></span></span><strong>${number(item.total, 'money')}</strong><small>${r.total > 0 && item.total >= 0 ? number(item.total / r.total * 100, 'percent') : '—'}</small></div>`).join('')}</div>` : empty('Nenhum custo por função nesta competência.'));
    const top = data.colaboradores.filter(c => c.itens > 0).slice(0, 5);
    setHtml('overview-top', top.length ? `<div class="overview-rank-list">${top.map((c, index) => `<button type="button" class="overview-top-row" data-colaborador="${c.colaborador_id}"><span class="overview-rank">${index + 1}</span><span>${escape(c.nome)}</span><span class="overview-track" aria-hidden="true"><span style="width:${pct(c.total, top[0].total)}%"></span></span><strong>${number(c.total, 'money')}</strong></button>`).join('')}</div>` : empty('Nenhum colaborador com itens nesta competência.'));
    const oldRole = role.value;
    const oldWork = work.value;
    role.innerHTML = '<option value="">Todas as funções</option>' + data.funcoes.map(f => `<option value="${escape(f.nome)}">${escape(f.nome)}</option>`).join('');
    work.innerHTML = '<option value="">Todas as obras</option>' + data.obras.map(o => `<option value="${o.id}">${escape(o.nome || `Obra #${o.id}`)}</option>`).join('');
    role.value = oldRole; work.value = oldWork;
    renderRows();
    window.pagamentoMotion?.animate(overview);
  }

  function renderRows() {
    if (!payload) return;
    const query = search.value.trim().toLocaleLowerCase('pt-BR');
    const rows = payload.colaboradores.filter(c => c.nome.toLocaleLowerCase('pt-BR').includes(query) && (!role.value || c.funcoes.includes(role.value)) && (!work.value || c.obras.includes(Number(work.value))) && (!pending.checked || c.itens_pendentes > 0));
    document.getElementById('overview-results').textContent = `${rows.length} colaboradores`;
    setHtml('overview-rows', rows.length ? rows.map(c => {
      const initials = c.nome.trim().split(/\s+/).map(s => s[0]).slice(0, 2).join('').toUpperCase();
      const tone = c.divergencias ? 'danger' : c.itens_pendentes ? 'pending' : c.itens ? 'paid' : 'neutral';
      return `<tr><td><button type="button" class="overview-person" data-colaborador="${c.colaborador_id}"><span class="overview-avatar" aria-hidden="true">${escape(initials)}</span><span>${escape(c.nome)}</span>${icon('fa-chevron-right')}</button></td><td class="col-right">${c.itens}</td><td class="col-right">${money(c.total)}</td><td class="col-right">${money(c.pago)}</td><td class="col-right">${money(c.pendente)}</td><td class="col-right">${c.adendos}</td><td><span class="overview-status-pill ${tone}">${icon(c.divergencias ? 'fa-triangle-exclamation' : c.itens_pendentes ? 'fa-clock' : c.itens ? 'fa-circle-check' : 'fa-minus')}${escape(c.situacao)}</span></td></tr>`;
    }).join('') : `<tr><td colspan="7" class="overview-empty">${payload.colaboradores.length ? (pending.checked && !search.value && !role.value && !work.value ? 'Nenhum item pendente nesta competência.' : 'Nenhum colaborador corresponde aos filtros.') : 'Nenhum colaborador com registros nesta competência.'}</td></tr>`);
  }

  function skeleton() {
    setHtml('overview-feedback', '<span class="sr-only">Carregando visão geral…</span>');
    setHtml('overview-kpis', Array.from({ length: 5 }, () => '<div class="overview-kpi overview-skeleton"><span></span><span></span><span></span></div>').join(''));
    ['month', 'status', 'amendments', 'functions', 'top'].forEach(id => setHtml(`overview-${id}`, '<div class="overview-skeleton overview-card-skeleton"><span></span><span></span><span></span></div>'));
    setHtml('overview-rows', '<tr><td colspan="7"><div class="overview-skeleton"><span></span><span></span></div></td></tr>');
    document.getElementById('overview-item-count').textContent = '—';
    document.getElementById('overview-results').textContent = '';
  }

  async function load() {
    controller?.abort();
    window.pagamentoMotion?.stop(overview);
    const request = new AbortController();
    controller = request;
    payload = null;
    overview.setAttribute('aria-busy', 'true');
    skeleton();
    const params = new URLSearchParams({ mes: mes.value, ano: ano.value });
    try {
      const response = await fetch(`getVisaoGeral.php?${params}`, { signal: request.signal });
      const data = await response.json();
      if (request.signal.aborted) return;
      if (!data.success) throw new Error(data.error || 'Não foi possível carregar a visão geral.');
      payload = data;
      render(data);
    } catch (error) {
      if (request.signal.aborted) return;
      setHtml('overview-kpis', '');
      ['month', 'status', 'amendments', 'functions', 'top'].forEach(id => setHtml(`overview-${id}`, empty('Dados indisponíveis.')));
      setHtml('overview-rows', '<tr><td colspan="7" class="overview-empty">Não foi possível consultar os colaboradores.</td></tr>');
      setHtml('overview-feedback', `<div class="overview-notice" role="alert">${escape(error.message)} <button class="btn btn-secondary" type="button" id="overview-retry">Tentar novamente</button></div>`);
      document.getElementById('overview-retry').addEventListener('click', load);
    } finally {
      if (controller === request) overview.setAttribute('aria-busy', 'false');
    }
  }

  actions.querySelectorAll('[data-view]').forEach(button => button.addEventListener('click', () => setView(button.dataset.view)));
  overview.addEventListener('click', event => {
    const button = event.target.closest('[data-colaborador]');
    if (button) drillDown(button.dataset.colaborador);
  });
  search.addEventListener('input', renderRows);
  [role, work, pending].forEach(el => el.addEventListener('change', renderRows));
  document.getElementById('overview-clear').addEventListener('click', () => {
    search.value = ''; role.value = ''; work.value = ''; pending.checked = false; renderRows();
  });
  [mes, ano].forEach(el => el.addEventListener('change', () => { persist(); if (document.body.dataset.paymentView === 'geral') load(); }));
  colaborador.addEventListener('change', persist);
  const params = new URLSearchParams(location.search);
  if (/^(?:[1-9]|1[0-2])$/.test(params.get('mes') || '')) mes.value = params.get('mes');
  const year = params.get('ano');
  if (/^20\d{2}$/.test(year || '')) {
    if (!Array.from(ano.options).some(option => option.value === year)) ano.add(new Option(year, year));
    ano.value = year;
  }
  const colabId = params.get('colaborador_id');
  if (colabId && Array.from(colaborador.options).some(option => option.value === colabId)) colaborador.value = colabId;
  setView(params.get('view') === 'colaborador' ? 'colaborador' : 'geral');
});
