document.addEventListener('DOMContentLoaded', () => {
  // menu toggle (reuse behavior from other modules)
  const btn = document.getElementById('menuButton');
  const menu = document.getElementById('menu');
  if (btn && menu) {
    btn.addEventListener('click', () => menu.classList.toggle('hidden'));
    window.addEventListener('click', (e) => {
      if (!btn.contains(e.target) && !menu.contains(e.target)) menu.classList.add('hidden');
    });
  }

  // metas ilusórias
  const metas = {
    imagensEntreguesMes: 100,
    funcoes: {
      cadernos_filtro_assets: 80,
      modelagem: 60,
      composicao: 60,
      finalizacao: 120,
      pos_producao: 120
    }
  };

  // dados mockados (MVP)
  const now = new Date();
  const mesAtualLabel = now.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });

  // produção do mês (somente ENTREGUES contam)
  const entreguesMes = 72; // mock
  const finalizadasMes = 138; // mock (tarefas finalizadas por função, soma total)

  // próximas entregas (entrega_itens)
  const proximasEntregas = [
    { entrega: 'OBR-245', item: 'IMG-1021', data_prevista: proxDias(2), status: 'em_andamento' },
    { entrega: 'OBR-245', item: 'IMG-1022', data_prevista: proxDias(5), status: 'revisao' },
    { entrega: 'OBR-312', item: 'IMG-0988', data_prevista: proxDias(8), status: 'aguardando' },
    { entrega: 'OBR-367', item: 'IMG-1102', data_prevista: proxDias(13), status: 'em_andamento' },
    { entrega: 'OBR-401', item: 'IMG-1200', data_prevista: proxDias(1), status: 'atrasado' }
  ];

  // status geral (itens)
  const statusGeral = {
    entregue: 72,
    finalizado: 66, // finalizado mas ainda não entregue
    em_andamento: 90,
    revisao: 24,
    aguardando: 14
  };

  // progresso por função
  const funcoes = [
    { nome: 'Cadernos + Filtro de Assets', key: 'cadernos_filtro_assets', entregues: 35, finalizadas: 50 },
    { nome: 'Modelagem', key: 'modelagem', entregues: 28, finalizadas: 40 },
    { nome: 'Composição', key: 'composicao', entregues: 25, finalizadas: 38 },
    { nome: 'Finalização', key: 'finalizacao', entregues: 40, finalizadas: 60 },
    { nome: 'Pós-Produção', key: 'pos_producao', entregues: 30, finalizadas: 52 }
  ];

  // histórico últimos meses (entregues)
  const meses = ultimosMeses(6);
  const historicoEntregues = [58, 64, 70, 61, 85, entreguesMes];

  // KPIs
  setText('entregues-mes', entreguesMes);
  setText('meta-mes', metas.imagensEntreguesMes);
  const perc = Math.round((entreguesMes / metas.imagensEntreguesMes) * 100);
  setText('percentual-entregues', `${Math.min(perc, 100)}%`);
  setWidth('progress-entregues', `${Math.min(perc, 100)}%`);
  setText('finalizadas-mes', finalizadasMes);
  setText('prox-entregas', proximasEntregas.length);
  setText('meta-mensal', metas.imagensEntreguesMes);

  // Tabela próximas entregas
  const tbody = document.getElementById('tbody-entregas');
  proximasEntregas
    .sort((a, b) => new Date(a.data_prevista) - new Date(b.data_prevista))
    .forEach(row => {
      const tr = document.createElement('tr');
      const diff = diasAte(row.data_prevista);
      const chip = renderChip(row.status, diff);
      tr.innerHTML = `
        <td>${row.entrega}</td>
        <td>${row.item}</td>
        <td>${fmt(row.data_prevista)}</td>
        <td>${chip}</td>
        <td>${diff}</td>
      `;
      tbody.appendChild(tr);
    });

  // Chart: mês vs meta
  new Chart(document.getElementById('chartMes'), {
    type: 'bar',
    data: {
      labels: [mesAtualLabel],
      datasets: [
        { label: 'Entregues', data: [entreguesMes], backgroundColor: '#0ea5e9' },
        { label: 'Meta', data: [metas.imagensEntreguesMes], backgroundColor: '#f97316' },
        { label: 'Finalizadas', data: [finalizadasMes], backgroundColor: '#22c55e' }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'bottom' } },
      scales: { y: { beginAtZero: true } }
    }
  });

  // Chart: Status geral
  new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',
    data: {
      labels: ['Entregue', 'Finalizado', 'Em andamento', 'Revisão', 'Aguardando'],
      datasets: [{
        data: [statusGeral.entregue, statusGeral.finalizado, statusGeral.em_andamento, statusGeral.revisao, statusGeral.aguardando],
        backgroundColor: ['#0ea5e9', '#22c55e', '#f59e0b', '#a78bfa', '#94a3b8']
      }]
    },
    options: { plugins: { legend: { position: 'bottom' } } }
  });

  // Chart: Progresso por função
  new Chart(document.getElementById('chartFuncoes'), {
    type: 'bar',
    data: {
      labels: funcoes.map(f => f.nome),
      datasets: [
        { label: 'Entregues', data: funcoes.map(f => f.entregues), backgroundColor: '#0ea5e9' },
        { label: 'Finalizadas', data: funcoes.map(f => f.finalizadas), backgroundColor: '#22c55e' },
        { label: 'Meta', data: funcoes.map(f => metas.funcoes[f.key]), backgroundColor: '#f97316' }
      ]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
  });

  // Chart: Histórico
  new Chart(document.getElementById('chartHistorico'), {
    type: 'line',
    data: {
      labels: meses,
      datasets: [{
        label: 'Entregues',
        data: historicoEntregues,
        tension: .35,
        borderColor: '#0ea5e9',
        backgroundColor: 'rgba(14,165,233,.15)',
        fill: true,
        pointRadius: 3
      }]
    },
    options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
  });

  // helpers
  function setText(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }
  function setWidth(id, v) { const el = document.getElementById(id); if (el) el.style.width = v; }
  function fmt(d) { return new Date(d).toLocaleDateString('pt-BR'); }
  function proxDias(n) { const d = new Date(); d.setDate(d.getDate() + n); return d; }
  function diasAte(d) { const dt = new Date(d); const today = new Date(); dt.setHours(0,0,0,0); today.setHours(0,0,0,0); return Math.round((dt - today) / (1000*60*60*24)); }
  function ultimosMeses(n) {
    const arr = []; const d = new Date();
    for (let i = n - 1; i >= 0; i--) {
      const x = new Date(d.getFullYear(), d.getMonth() - i, 1);
      arr.push(x.toLocaleDateString('pt-BR', { month: 'short' }));
    }
    return arr;
  }
  function renderChip(status, dias) {
    let cls = 'ok'; let text = 'OK';
    if (status === 'atrasado' || dias < 0) { cls = 'late'; text = 'Atrasado'; }
    else if (dias <= 3) { cls = 'warn'; text = 'Em breve'; }
    else if (status === 'revisao') { cls = 'warn'; text = 'Revisão'; }
    else if (status === 'aguardando') { cls = 'warn'; text = 'Aguardando'; }
    return `<span class="chip ${cls}">${text}</span>`;
  }
});
