(() => {
  const byId = id => document.getElementById(id);
  const modal = byId('renderEfModal'), dialog = modal.querySelector('.render-ef-dialog'), trigger = byId('renderHighResolution');
  let renderId, request = null, payload, timer, destroyOrb, generation = 0, busy = false, clientKey, newPreparation = false;
  const stages = {
    SIMULADO: [4, 'Teste concluído'],
    SNAPSHOT: [0, 'Recuperando configuração do render aprovado'], DIMENSOES: [1, 'Confirme a resolução original'],
    FILA: [0, 'Aguardando preparação'], ARQUIVO: [0, 'Conferindo arquivo final'], INSPECAO: [0, 'Recuperando configuração original'],
    CONFIGURACAO: [1, 'Conferindo dimensões'], PASTA: [1, 'Conferindo destino'], REVISAO: [1, 'Pronto para enviar em alta'],
    CRIANDO_PASTA: [2, 'Preparando destino'], CONFIGURACOES: [2, 'Preparando render em alta'],
    SUBMETENDO: [2, 'Aguardando Deadline'], SUBMISSAO_INCERTA: [2, 'Reconciliando o mesmo envio'],
    VALIDANDO: [2, 'Validando job suspenso'], VINCULANDO: [3, 'Registrando no Flow'],
    LIBERANDO: [4, 'Liberando render'], CONCLUIDO: [4, 'Render enviado e vinculado'],
  };
  function orb(show) {
    destroyOrb?.(); destroyOrb = null;
    const canvas = byId('renderEfOrb'); canvas.hidden = !show; canvas.dataset.orbState = 'composing';
    canvas.setAttribute('aria-label', 'Preparando render em alta');
    if (show) destroyOrb = window.thinkingOrbs?.(canvas);
  }
  function message(title, text, error = false) {
    byId('renderEfStage').textContent = title; byId('renderEfMessage').textContent = text;
    modal.classList.toggle('has-error', error);
  }
  function rows(target, entries) {
    target.replaceChildren();
    entries.forEach(([label, value, origin]) => {
      if (value === null || value === undefined || value === '') return;
      const dt = document.createElement('dt'), dd = document.createElement('dd');
      dt.textContent = label; dd.textContent = String(value);
      if (origin) { const note = document.createElement('small'); note.className = 'render-ef-origin'; note.textContent = origin; dd.append(note); }
      target.append(dt, dd);
    });
  }
  function sourceChange() {
    const file = payload?.files?.find(f => String(f.id) === byId('renderEfFile').value);
    byId('renderEfFileInfo').textContent = file ? `${file.caminho} · ${file.status}` : 'Selecione explicitamente o .max final. A versão mais recente não é escolhida automaticamente.';
    if (payload) byId('renderEfSource').querySelector('button').disabled = !payload.schema_ready || !payload.snapshot_enabled;
  }
  function show(data) {
    payload = data; request = newPreparation ? null : data.request;
    const plan = request?.plan || {}, context = data.context || {}, origins = plan.parameter_sources || {};
    const pending = ['PENDENTE','SNAPSHOT_PREPARAR','SNAPSHOT_AGUARDAR','SNAPSHOT_ARQUIVO','INSPECIONAR','PROCESSANDO','EXECUTAR','EXECUTAR_V2','CONFIGURER','RECONCILIAR'].includes(request?.status);
    rows(byId('renderEfContext'), [['Obra', `${context.nomenclatura || ''} · ${context.nome_obra || ''}`], ['Imagem', context.imagem_nome], ['Aprovação', context.status], ['Etapa', context.etapa_atual]]);
    byId('renderEfSource').hidden = !!request;
    if (!request) {
      const select = byId('renderEfFile'), empty = document.createElement('option'); empty.value = ''; empty.textContent = 'Selecione o arquivo final aprovado';
      select.replaceChildren(empty);
      (data.files || []).forEach(file => { const item = document.createElement('option'); item.value = file.id; item.textContent = `${file.nome_arquivo} · ${file.criado_em} · ${file.status}`; select.append(item); });
    }
    sourceChange();
    byId('renderEfConfiguration').hidden = request?.status !== 'CONFIGURAR';
    const [step, title] = stages[request?.etapa] || [0, 'Preparar render em alta'];
    let liveTitle = title;
    if (data.runtime?.status === 'EM_ANDAMENTO') liveTitle = 'Renderizando';
    if (data.runtime?.status === 'EM_APROVACAO') liveTitle = 'Concluído · aguardando aprovação';
    if (data.runtime?.status === 'ERRO') liveTitle = 'Erro na renderização';
    const error = ['ERRO','ATENCAO'].includes(request?.status);
    message(!data.schema_ready ? 'Preparação indisponível' : error ? 'Preparação interrompida' : liveTitle,
      !data.schema_ready ? 'As migrations de preparação e snapshots estão pendentes. Nenhum envio está habilitado.' : request?.mensagem || (!data.snapshot_enabled ? 'A preparação por snapshots ainda não foi habilitada.' : 'Escolha e confirme o arquivo .max final. A configuração virá do render aprovado.'), error);
    orb(pending);
    byId('renderEfWorkerWarning').hidden = data.worker_online || !data.schema_ready;
    byId('renderEfBackground').hidden = !pending; byId('renderEfNotice').hidden = request?.status !== 'PRONTO';
    byId('renderEfConfirm').hidden = request?.status !== 'PRONTO' || !!plan.blockers?.length;
    byId('renderEfConfirm').disabled = !data.submission_enabled;
    byId('renderEfAbandon').hidden = !request || !!request.novo_job_id || !!request.submissao_iniciada_em || !['CONFIGURAR','PRONTO','ERRO'].includes(request.status);
    byId('renderEfNew').hidden = !request || !['ERRO','CANCELADO','CONCLUIDO'].includes(request.status) || (request.status === 'ERRO' && (!!request.novo_job_id || !!request.submissao_iniciada_em));
    byId('renderEfRetry').hidden = !error || (!request?.novo_job_id && !request?.submissao_iniciada_em);
    byId('renderEfOpen').hidden = !request?.render_ef_id;
    const blockers = byId('renderEfBlockers'); blockers.replaceChildren(); blockers.hidden = !plan.blockers?.length;
    (plan.blockers || []).forEach(value => { const li = document.createElement('li'); li.textContent = value; blockers.append(li); });
    const selected = data.files?.find(file => String(file.id) === String(request?.arquivo_log_id));
    rows(byId('renderEfDetails'), [
      ['Arquivo .max final', plan.scene || selected?.caminho], ['Snapshot do aprovado', plan.snapshot_id || data.snapshot?.id],
      ['Câmera registrada', plan.camera || (request ? 'Não informada pelo job · mantida na cena' : '')],
      ['Renderer registrado', plan.renderer || (request ? 'Não informado pelo job · mantido na cena' : '')],
      ['Resolução original', plan.old_width ? `${plan.old_width} × ${plan.old_height} px` : '', origins.resolution],
      ['Resolução em alta', plan.width ? `${plan.width} × ${plan.height} px` : ''],
      ['Pool / Group', plan.generic ? `${plan.generic.Pool || ''} / ${plan.generic.Group || ''}` : ''],
      ['Frames', plan.frames || plan.generic?.Frames], ['Destino exclusivo', plan.destination], ['Novo job', request?.novo_job_id],
    ]);
    byId('renderEfDetails').hidden = !request;
    const details = byId('renderEfDependencies'), body = details.querySelector('div'); body.replaceChildren(); details.hidden = !plan.outputs;
    Object.entries(plan.outputs || {}).forEach(([name, value]) => { const item = document.createElement('p'); item.textContent = `${name} · ${value}`; body.append(item); });
    const history = byId('renderEfHistory').querySelector('ol'); history.replaceChildren();
    (data.history || []).forEach(entry => { const li = document.createElement('li'), link = document.createElement('button'); link.type = 'button'; link.textContent = `#${entry.id} · ${entry.criado_em} · ${entry.status}`; link.addEventListener('click', () => { newPreparation = false; request = {id:entry.id}; run('status'); }); li.append(link); history.append(li); });
    modal.querySelectorAll('[data-ef-step]').forEach(item => { const index = Number(item.dataset.efStep); item.classList.toggle('is-done', index < step); item.classList.toggle('is-current', index === step); });
    if (pending || (request?.status === 'CONCLUIDO' && ['VINCULADA','EM_ANDAMENTO'].includes(data.runtime?.status))) timer = setTimeout(() => run('status'), pending ? 2000 : 15000);
  }
  async function api(action, post, extra) {
    const data = {action,render_id:renderId,...extra}; if (request?.id && action !== 'prepare') data.request_id = request.id;
    if (post) Object.assign(data,{csrf:dialog.dataset.csrf,plan_hash:request?.plan_hash || ''});
    const controller = new AbortController(), timeout = setTimeout(() => controller.abort(),20000);
    try {
      const response = await fetch(`render_ef.php${post ? '' : '?' + new URLSearchParams(data)}`,{method:post?'POST':'GET',credentials:'same-origin',cache:'no-store',signal:controller.signal,...(post?{headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)}:{})});
      const result = await response.json(); if (!response.ok || result.status !== 'sucesso') throw new Error(result.message || 'Não foi possível consultar a solicitação.'); return result;
    } finally { clearTimeout(timeout); }
  }
  async function run(action, post = false, extra = {}) {
    if (busy) return; busy = true; const current = generation; clearTimeout(timer); orb(true);
    modal.querySelectorAll('button[type=submit],footer button:not(#renderEfCancel)').forEach(button => {button.disabled = true;});
    try { const result = await api(action,post,extra); if (current !== generation || modal.hidden) return; if (action === 'prepare') newPreparation = false; show(result); }
    catch (error) { if (current !== generation || modal.hidden) return; orb(false); message('Não foi possível atualizar',error.name === 'AbortError' ? 'A consulta demorou. Reabra para consultar a mesma solicitação, sem repetir o envio.' : error.message,true); }
    finally { busy = false; if (current === generation) { modal.querySelectorAll('button[type=submit],footer button').forEach(button => {button.disabled = false;}); if (payload) {sourceChange(); byId('renderEfConfirm').disabled = !payload.submission_enabled;} } }
  }
  function close() {generation++; clearTimeout(timer); orb(false); modal.hidden = true; byId('myModal').inert = false; trigger.focus();}
  trigger.addEventListener('click', () => {if (trigger.disabled || busy) return; fecharMenuMaisAcoes(); renderId = byId('modal_idrender').textContent.trim(); request = null; payload = null; newPreparation = false; clientKey = crypto.randomUUID(); generation++; modal.hidden = false; byId('myModal').inert = true; byId('renderEfClose').focus(); run('status');});
  byId('renderEfFile').addEventListener('change',() => {sourceChange();byId('renderEfSource').querySelector('[name=file_confirmed]').checked = false;});
  byId('renderEfSource').addEventListener('submit',event => {event.preventDefault();run('prepare',true,{...Object.fromEntries(new FormData(event.target)),client_key:clientKey});});
  byId('renderEfConfiguration').addEventListener('submit',event => {event.preventDefault();run('configure',true,Object.fromEntries(new FormData(event.target)));});
  byId('renderEfConfirm').addEventListener('click', () => run('confirm',true)); byId('renderEfRetry').addEventListener('click', () => run('resume',true));
  byId('renderEfAbandon').addEventListener('click', () => run('cancel',true));
  byId('renderEfNew').addEventListener('click', () => {request = null; newPreparation = true; clientKey = crypto.randomUUID(); show(payload);});
  byId('renderEfOpen').addEventListener('click', () => {const id = request?.render_ef_id; close(); if (id) window.location.href = `?render_id=${encodeURIComponent(id)}`;});
  byId('renderEfCancel').addEventListener('click',close); byId('renderEfClose').addEventListener('click',close);
  modal.addEventListener('click',event => {if (event.target === modal) close();});
  modal.addEventListener('keydown',event => {
    if (event.key === 'Escape') {close();return;}
    if (event.key !== 'Tab') return;
    const nodes = [...dialog.querySelectorAll('button,input,select,summary,[tabindex="0"]')].filter(node => !node.disabled && node.getClientRects().length);
    if (!nodes.length) return;
    if (event.shiftKey && document.activeElement === nodes[0]) {event.preventDefault();nodes.at(-1).focus();}
    else if (!event.shiftKey && document.activeElement === nodes.at(-1)) {event.preventDefault();nodes[0].focus();}
  });
})();
