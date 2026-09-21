(() => {
  const root = document.documentElement;
  const $ = (selector) => document.querySelector(selector);
  const escape = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[char]);
  const target = (cta = {}) => {
    const raw = String(cta.target || '').trim();
    if (!raw) return '../../inicio.php';
    return /^(https?:)?\/\//.test(raw) || raw.startsWith('/') ? raw : '../../' + raw.replace(/^\.\//, '');
  };
  const empty = (message) => `<div class="home-empty">${escape(message)}</div>`;
  let backgrounds = {};
  try { backgrounds = JSON.parse(document.body.dataset.backgrounds || '{}'); } catch (_) { backgrounds = {}; }

  function updateHeader() {
    const now = new Date();
    const hour = Number(new Intl.DateTimeFormat('en-US', { hour: 'numeric', hourCycle: 'h23', timeZone: 'America/Sao_Paulo' }).format(now));
    const greeting = hour < 12 ? 'Bom dia' : hour < 18 ? 'Boa tarde' : 'Boa noite';
    $('#homeSalutation').textContent = `${greeting}, ${document.body.dataset.userName || 'você'}`;
    $('#homeDate').textContent = new Intl.DateTimeFormat('pt-BR', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'America/Sao_Paulo' }).format(now).replace(/^./, (letter) => letter.toUpperCase());
  }

  function setBackground(theme) {
    const list = Array.isArray(backgrounds[theme]) ? backgrounds[theme] : [];
    const [year, month, day] = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Sao_Paulo' }).format(new Date()).split('-').map(Number);
    const index = Math.floor(Date.UTC(year, month - 1, day) / 86400000);
    $('.home-background').style.backgroundImage = list.length ? `url("${list[index % list.length]}")` : '';
  }

  function initTheme() {
    const theme = localStorage.getItem('flow-home-theme') || (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
    root.dataset.theme = theme; setBackground(theme);
    $('#themeToggle').innerHTML = `<i class="${theme === 'dark' ? 'ri-sun-line' : 'ri-moon-line'}"></i>`;
    $('#themeToggle').addEventListener('click', () => {
      const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
      root.dataset.theme = next; localStorage.setItem('flow-home-theme', next); setBackground(next);
      $('#themeToggle').innerHTML = `<i class="${next === 'dark' ? 'ri-sun-line' : 'ri-moon-line'}"></i>`;
    });
  }

  function renderAttention(items) {
    $('#attentionCount').textContent = items.length;
    const list = $('#attentionList'); list.classList.remove('home-skeleton-list');
    list.innerHTML = items.length ? items.slice(0, 3).map((item) => `<a class="attention-card severity-${escape(item.severity || 'info')}" href="${escape(target(item.cta))}"><span class="attention-icon"></span><span><h3>${escape(item.title || item.imagem?.nome || 'Ação necessária')}</h3><p>${escape(item.description || 'Revise esta situação.')}</p></span></a>`).join('') : empty('Tudo em ordem. Nenhuma pendência agora.');
  }

  function renderCurrent(task) {
    const node = $('#currentWork'); node.classList.remove('home-skeleton-current');
    if (!task) { node.innerHTML = empty('Nenhuma atividade operacional recomendada agora.'); return; }
    const title = task.imagem?.nome || task.funcao?.nome || 'Próxima ação';
    const context = [task.funcao?.nome, task.status].filter(Boolean).join(' · ');
    const preview = task.preview_url ? `<div class="current-work__preview"><img src="${escape(task.preview_url)}" alt=""><span class="current-work__project">${escape(task.obra?.nome || 'Projeto')}</span></div>` : `<div class="current-work__preview"><span class="current-work__project">${escape(task.obra?.nome || 'Projeto')}</span></div>`;
    node.innerHTML = `${preview}<div class="current-work__body"><div><h3>${escape(title)}</h3><p class="task-meta">${escape(context || task.obra?.nome || '')}</p><p class="task-status"><i class="ri-time-line"></i>Melhor próxima ação operacional</p></div><a class="primary-cta" href="${escape(target(task.cta))}">${escape(task.cta?.label || 'Continuar')} <i class="ri-arrow-right-line"></i></a></div>`;
  }

  function renderNext(tasks) {
    const node = $('#nextList');
    node.innerHTML = tasks.length ? tasks.slice(0, 3).map((task, index) => {
      const title = [task.obra?.nome, task.imagem?.nome || task.funcao?.nome].filter(Boolean).join(' · ');
      const detail = [task.funcao?.nome, task.deadline ? `prazo ${task.deadline.split('-').reverse().slice(0, 2).join('/')}` : 'sem prazo'].filter(Boolean).join(' · ');
      return `<a class="next-item" href="${escape(target(task.cta))}"><span class="next-time">${index === 0 ? 'Agora' : 'A seguir'}</span><span class="task-glyph"></span><span><h3>${escape(title || 'Tarefa')}</h3><p class="task-meta">${escape(detail)}</p></span><span class="mini-cta">${index === 0 ? 'Hoje' : 'Abrir'}</span></a>`;
    }).join('') : empty('Nenhuma próxima tarefa elegível.');
  }

  function renderPerformance(performance) {
    const node = $('#performancePanel'); node.classList.remove('home-skeleton-current');
    if (!performance?.available) { node.innerHTML = `<div class="performance-note">O resumo de conclusão ainda não está disponível para este período.</div>`; return; }
    const count = Number(performance.count || 0);
    const punctuality = performance.punctuality_percent == null ? '—' : `${Math.round(performance.punctuality_percent)}%`;
    const trend = performance.trend_percent == null ? 'Seu ritmo está sendo consolidado neste mês.' : `${performance.trend_percent >= 0 ? '↑' : '↓'} ${Math.abs(performance.trend_percent)}% em relação ao mês anterior`;
    const month = new Intl.DateTimeFormat('pt-BR', { month: 'long', timeZone: 'America/Sao_Paulo' }).format(new Date());
    node.innerHTML = `<div class="performance-metric"><strong>${count}</strong><span>tarefas concluídas</span></div><div class="performance-metric"><strong>${punctuality}</strong><span>no prazo</span></div><p class="performance-trend">${escape(trend)}</p><p class="performance-note">Resumo de ${escape(month)}. Mantenha o foco nas próximas entregas.</p>`;
  }

  function render(data) {
    const work = data.work || data.personal_work || {};
    renderAttention(data.attention || []); renderCurrent(work.current || null); renderNext(work.next || []); renderPerformance(data.performance || null);
    $('#homeContent').setAttribute('aria-busy', 'false');
  }

  async function load() {
    try {
      const response = await fetch('getHome.php', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await response.json(); if (!response.ok || !data.success) throw new Error(data.error || 'Não foi possível carregar a Home.'); render(data);
    } catch (error) {
      $('#attentionList').classList.remove('home-skeleton-list'); $('#attentionList').innerHTML = empty(error.message || 'Não foi possível carregar prioridades.');
      $('#currentWork').classList.remove('home-skeleton-current'); $('#currentWork').innerHTML = empty('Tente atualizar a página.'); $('#performancePanel').classList.remove('home-skeleton-current'); $('#performancePanel').innerHTML = empty('Resumo indisponível.'); $('#homeContent').setAttribute('aria-busy', 'false');
    }
  }
  initTheme(); updateHeader(); load();
})();
