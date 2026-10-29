(() => {
  const panel = document.getElementById('alteracao-config');
  if (!panel) return;
  let data;
  const escape = (v) => String(v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const status = document.getElementById('alteracao-config-status');
  const agenda = document.getElementById('alteracao-agenda');
  async function loadAgenda() {
    const output = document.getElementById('alteracao-agenda-content');
    output.textContent = 'Buscando agenda…';
    try {
      const r = await fetch('alteracao_agenda.php', { headers: { Accept: 'application/json' }, cache: 'no-store' }), result = await r.json();
      if (!r.ok || !result.success) throw new Error(result.message);
      output.innerHTML = '<div style="overflow:auto"><table><thead><tr><th>Colaborador / dia</th>' + result.dias.map((d) => '<th>' + d.split('-').reverse().slice(0, 2).join('/') + '</th>').join('') + '</tr></thead><tbody>' + result.colaboradores.map((p) => '<tr><th>' + escape(p.nome) + '<br><small>' + (p.tipo_atuacao === 'PRINCIPAL' ? 'Principal' : 'Secundária') + '</small></th>' + result.dias.map((d) => { const a = result.agenda[p.id][d]; return `<td style="min-width:130px;padding:10px"><strong>${Number(a.comprometidos)} pts comprometidos</strong><br><small>${a.disponivel == null ? Number(a.nominal) + ' pts nominais · disponibilidade não estimada' : Number(a.disponivel) + ' pts disponíveis'}${a.ocupacao_r00 ? '<br>R00: ' + Math.round(a.ocupacao_r00 * 100) + '%' : ''}</small>${a.avisos.map((s) => '<p>' + escape(s) + '</p>').join('')}</td>`; }).join('') + '</tr>').join('') + '</tbody></table></div>';
    } catch (e) { output.textContent = e.message || 'Não foi possível carregar a agenda.'; }
  }
  agenda.addEventListener('toggle', () => { if (agenda.open) loadAgenda(); });
  document.getElementById('alteracao-agenda-reload').addEventListener('click', loadAgenda);
  const save = document.getElementById('alteracao-config-save');
  async function load() {
    try {
      const r = await fetch('alteracao_config.php', { headers: { Accept: 'application/json' }, cache: 'no-store' });
      data = await r.json();
      if (!r.ok || !data.success) throw new Error(data.message);
      const c = data.configuracao;
      document.getElementById('alteracao-config-default').value = c.capacidade_padrao;
      document.getElementById('alteracao-config-levels').innerHTML = [1, 2, 3, 4, 5].map((n) => `<label>N${n} · pontos<input type="number" min="0.01" max="10000" step="0.01" required data-config-level="${n}" value="${Number(c.niveis[n])}"></label>`).join('');
      document.getElementById('alteracao-config-people').innerHTML = data.colaboradores.map((p) => `<label>${escape(p.nome)} · Alteração ${p.tipo_atuacao === 'PRINCIPAL' ? 'principal' : 'secundária'}<input type="number" min="0" max="10000" step="0.01" data-config-person="${Number(p.id)}" value="${c.colaboradores[p.id] ?? ''}" placeholder="Padrão: ${Number(c.capacidade_padrao)}"></label>`).join('');
      save.disabled = false; status.textContent = '';
    } catch (e) { status.textContent = e.message || 'Não foi possível carregar a configuração.'; }
  }
  panel.addEventListener('toggle', () => { if (panel.open && !data) load(); });
  if (location.hash === '#alteracao-config') panel.open = true;
  document.getElementById('alteracao-config-reload').addEventListener('click', load);
  document.getElementById('alteracao-config-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!data) return;
    const configuracao = { capacidade_padrao: document.getElementById('alteracao-config-default').value, niveis: {}, colaboradores: {} };
    panel.querySelectorAll('[data-config-level]').forEach((input) => { configuracao.niveis[input.dataset.configLevel] = input.value; });
    panel.querySelectorAll('[data-config-person]').forEach((input) => { if (input.value !== '') configuracao.colaboradores[input.dataset.configPerson] = input.value; });
    save.disabled = true;
    try {
      const r = await fetch('alteracao_config.php', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ configuracao, fingerprint: data.fingerprint, csrf_token: data.csrf_token }) });
      const result = await r.json();
      if (!r.ok || !result.success) throw new Error(result.message);
      data = result; status.textContent = 'Configuração salva. Versões confirmadas preservam seus pontos históricos.';
    } catch (e) { status.textContent = e.message || 'Não foi possível salvar.'; }
    finally { save.disabled = false; }
  });
})();
