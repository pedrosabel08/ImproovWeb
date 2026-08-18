(() => {
  const state = { token: window.BRIEFING_ACCESS_TOKEN || '', csrf: '', briefing: null, section: 0, timers: new Map(), operations: new Map() };
  const $ = id => document.getElementById(id);
  const setNotice = message => { $('notice').textContent = message || ''; };
  const uuid = () => crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
  const api = async (data, csrf = false) => {
    const response = await fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(csrf ? { 'X-Briefing-CSRF': state.csrf } : {}) }, body: JSON.stringify(data) });
    const json = await response.json().catch(() => ({}));
    if (!response.ok || !json.ok) { const error = new Error(json.message || 'Não foi possível concluir a operação.'); error.data = json; throw error; }
    return json;
  };
  const setSave = (text, kind = '') => { const element = $('save-state'); element.textContent = text; element.className = `save-state ${kind}`; };
  const requestCode = async action => {
    const result = await api({ action, token: state.token, email: $('email').value, name: $('name')?.value, role: $('role')?.value, phone: $('phone')?.value });
    if (result.next === 'register') { $('start-form').hidden = true; $('register-form').hidden = false; $('access-copy').textContent = 'Complete seus dados para receber o código de acesso.'; return; }
    $('start-form').hidden = true; $('register-form').hidden = true; $('verify-form').hidden = false; $('access-copy').textContent = 'Enviamos um código para seu e-mail.';
  };
  $('start-form').onsubmit = async event => { event.preventDefault(); try { await requestCode('access.start'); setNotice(''); } catch (error) { setNotice(error.message); } };
  $('register-form').onsubmit = async event => { event.preventDefault(); try { await requestCode('access.register'); setNotice(''); } catch (error) { setNotice(error.message); } };
  $('back-email').onclick = () => { $('register-form').hidden = true; $('start-form').hidden = false; };
  $('again').onclick = () => $('start-form').requestSubmit();
  $('verify-form').onsubmit = async event => {
    event.preventDefault();
    try { const result = await api({ action: 'access.verify', token: state.token, email: $('email').value, code: $('code').value }); state.csrf = result.csrf; $('access').hidden = true; $('briefing-app').hidden = false; await bootstrap(); }
    catch (error) { setNotice(error.message); }
  };
  async function bootstrap() {
    try {
      setSave('Sincronizando…');
      const result = await api({ action: 'briefing.bootstrap', token: state.token });
      state.csrf = result.csrf || state.csrf; state.briefing = result.briefing; render(); setSave('Salvo', 'saved');
      window.FlowBriefingWS?.connect?.(() => api({ action: 'ws.ticket', token: state.token }, true).then(item => item.ticket));
    } catch (error) { setNotice(error.message); setSave('Sem conexão', 'error'); }
  }
  function valueOf(question, element) {
    if (question.tipo === 'MULTI_SELECT') return [...element.querySelectorAll('input:checked')].map(input => input.value);
    if (question.tipo === 'SINGLE_SELECT') return element.querySelector('input:checked')?.value || '';
    if (question.tipo === 'YES_NO') return element.value === 'sim' ? true : element.value === 'nao' ? false : '';
    return element.value;
  }
  function fieldFor(question) {
    const answer = question.answer || {}; const value = answer.value; let field;
    if (question.tipo === 'LONG_TEXT') { field = document.createElement('textarea'); field.value = value || ''; }
    else if (question.tipo === 'YES_NO') { field = document.createElement('select'); field.append(new Option('Selecione', ''), new Option('Sim', 'sim'), new Option('Não', 'nao')); field.value = value === true ? 'sim' : value === false ? 'nao' : ''; }
    else if (['SINGLE_SELECT', 'MULTI_SELECT'].includes(question.tipo)) {
      field = document.createElement('div'); field.className = 'choices';
      question.options.forEach(option => { const label = document.createElement('label'); const input = document.createElement('input'); input.type = question.tipo === 'MULTI_SELECT' ? 'checkbox' : 'radio'; input.name = `q-${question.id}`; input.value = option.value; input.checked = Array.isArray(value) ? value.includes(option.value) : value === option.value; input.disabled = !question.editable; input.onchange = () => queue(question, valueOf(question, field)); label.append(input, document.createTextNode(option.label)); field.append(label); });
      return field;
    } else { field = document.createElement('input'); field.type = question.tipo === 'NUMBER' ? 'number' : question.tipo === 'DATE' ? 'date' : question.tipo === 'LINK' ? 'url' : 'text'; field.value = value || ''; }
    field.disabled = !question.editable; field.oninput = () => queue(question, valueOf(question, field)); field.onblur = () => flush(question.id); return field;
  }
  function render() {
    const briefing = state.briefing;
    $('title').textContent = briefing.titulo; $('subtitle').textContent = `${briefing.nome_obra || ''} · ${briefing.temporal_status.replaceAll('_', ' ')}`;
    $('progress-fill').style.width = `${briefing.progress.percent}%`; $('progress-label').textContent = `${briefing.progress.answered} de ${briefing.progress.total} respondidas`;
    $('sections').replaceChildren(...briefing.sections.map((section, index) => { const button = document.createElement('button'); button.textContent = section.titulo; button.className = index === state.section ? 'active' : ''; button.onclick = () => { state.section = index; render(); }; return button; }));
    const section = briefing.sections[state.section]; const form = $('form'); form.replaceChildren(); const heading = document.createElement('h2'); heading.textContent = section.titulo; form.append(heading);
    section.questions.forEach(question => { const card = document.createElement('div'); card.className = 'question'; const label = document.createElement('label'); label.textContent = question.pergunta; if (question.obrigatoria) label.append(Object.assign(document.createElement('span'), { className: 'required', textContent: ' *' })); card.append(label); if (question.ajuda) card.append(Object.assign(document.createElement('p'), { className: 'help', textContent: question.ajuda })); card.append(fieldFor(question)); if (question.permite_nao_aplica) { const notApplicable = document.createElement('label'); notApplicable.className = 'na'; const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.checked = !!question.answer?.not_applicable; checkbox.disabled = !question.editable; checkbox.onchange = () => queue(question, null, checkbox.checked); notApplicable.append(checkbox, document.createTextNode(' Não se aplica')); card.append(notApplicable); } if (!question.editable) card.append(Object.assign(document.createElement('p'), { className: 'answer-meta', textContent: 'Resposta disponível somente para leitura.' })); form.append(card); });
    const editable = briefing.sections.flatMap(sectionItem => sectionItem.questions).some(question => question.editable); $('submit').disabled = !editable || !['AGUARDANDO_CLIENTE', 'EM_PREENCHIMENTO', 'AJUSTES_SOLICITADOS'].includes(briefing.status);
  }
  async function queue(question, value, notApplicable = false) {
    if (!question.editable) return;
    const item = { id: uuid(), question, value, notApplicable, expected: Number(question.answer?.version || 0) }; state.operations.set(question.id, item); clearTimeout(state.timers.get(question.id)); state.timers.set(question.id, setTimeout(() => send(item), 650)); setSave('Salvando…');
  }
  async function send(item) {
    if (state.operations.get(item.question.id)?.id !== item.id) return;
    try { const result = await api({ action: 'answer.save', token: state.token, question_id: item.question.id, value: item.value, not_applicable: item.notApplicable, expected_version: item.expected, operation_uuid: item.id }, true); item.question.answer = { ...item.question.answer, ...result.answer }; state.briefing.progress = result.progress; state.operations.delete(item.question.id); render(); setSave('Salvo', 'saved'); }
    catch (error) { setSave('Alteração pendente', 'error'); setNotice(error.message); if (error.data?.conflict) await bootstrap(); }
  }
  async function flush(questionId) { const item = state.operations.get(questionId); if (item) await send(item); }
  $('submit').onclick = async () => { try { for (const question of state.briefing.sections.flatMap(section => section.questions)) await flush(question.id); if (!confirm('Concluir e enviar este briefing para conferência?')) return; const result = await api({ action: 'briefing.submit', token: state.token }, true); state.briefing.status = result.status; render(); setSave('Enviado', 'saved'); setNotice(result.status === 'APROVADO' ? 'Briefing concluído e aprovado.' : 'Briefing enviado para conferência.'); } catch (error) { setNotice(error.message); } };
  window.addEventListener('briefing:realtime', event => { if (event.detail?.briefing_id === state.briefing?.id && !state.operations.size) bootstrap(); });
  async function inspectAccess() {
    if (!state.token) { setNotice('Link de acesso inválido.'); return; }
    try {
      const result = await api({ action: 'access.inspect', token: state.token });
      if (result.authenticated) { $('access').hidden = true; $('briefing-app').hidden = false; await bootstrap(); }
    } catch (error) { setNotice(error.message); $('start-form').querySelector('button').disabled = true; }
  }
  inspectAccess();
})();
