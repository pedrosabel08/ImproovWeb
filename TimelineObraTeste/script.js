const state = {
  obraId: "",
  etapaId: "",
  tipoImagem: "",
  funcaoId: "",
  timeline: [],
  minDate: null,
  maxDate: null,
  dayWidth: 26,
};

const obraSelect = document.getElementById("obraSelect");
const etapaSelect = document.getElementById("etapaSelect");
const tipoImagemSelect = document.getElementById("tipoImagemSelect");
const funcaoSelect = document.getElementById("funcaoSelect");
const metaResumo = document.getElementById("metaResumo");
const emptyState = document.getElementById("emptyState");
const timelineContainer = document.getElementById("timelineContainer");
const timelineBody = document.getElementById("timelineBody");

const dateFmt = new Intl.DateTimeFormat("pt-BR");

obraSelect.addEventListener("change", () => {
  state.obraId = obraSelect.value;
  state.etapaId = "";
  state.tipoImagem = "";
  state.funcaoId = "";
  etapaSelect.value = "";
  tipoImagemSelect.value = "";
  funcaoSelect.value = "";
  carregarTimeline();
});

etapaSelect.addEventListener("change", () => {
  state.etapaId = etapaSelect.value;
  carregarTimeline(false);
});

tipoImagemSelect.addEventListener("change", () => {
  state.tipoImagem = tipoImagemSelect.value;
  carregarTimeline(false);
});

funcaoSelect.addEventListener("change", () => {
  state.funcaoId = funcaoSelect.value;
  carregarTimeline(false);
});

async function carregarTimeline(refreshFilters = true) {
  if (!state.obraId) {
    limparTimeline("Selecione uma obra para carregar a timeline.");
    return;
  }

  metaResumo.textContent = "Carregando timeline...";

  const params = new URLSearchParams();
  params.set("obra_id", state.obraId);
  if (state.etapaId) params.set("etapa_id", state.etapaId);
  if (state.tipoImagem) params.set("tipo_imagem", state.tipoImagem);
  if (state.funcaoId) params.set("funcao_id", state.funcaoId);

  try {
    const response = await fetch(`get_timeline_data.php?${params.toString()}`);
    const data = await response.json();

    if (!response.ok || !data.ok) {
      throw new Error(data.message || "Erro ao carregar timeline.");
    }

    if (refreshFilters) {
      popularSelectEtapa(data.filters?.etapas || []);
      popularSelectTipo(data.filters?.tipos_imagem || []);
      popularSelectFuncao(data.filters?.funcoes || []);
    }

    state.timeline = Array.isArray(data.timeline) ? data.timeline : [];
    state.minDate = data.meta?.inicio_geral
      ? new Date(data.meta.inicio_geral)
      : null;
    state.maxDate = data.meta?.fim_geral ? new Date(data.meta.fim_geral) : null;

    renderTimeline();
  } catch (error) {
    limparTimeline(error.message || "Falha ao carregar dados.");
  }
}

function popularSelectEtapa(etapas) {
  etapaSelect.innerHTML = '<option value="">Todas</option>';
  etapas.forEach((etapa) => {
    const option = document.createElement("option");
    option.value = String(etapa.id);
    option.textContent = etapa.nome;
    etapaSelect.appendChild(option);
  });
  etapaSelect.disabled = false;
  etapaSelect.value = state.etapaId;
}

function popularSelectTipo(tipos) {
  tipoImagemSelect.innerHTML = '<option value="">Todos</option>';
  tipos.forEach((tipo) => {
    const option = document.createElement("option");
    option.value = tipo;
    option.textContent = tipo;
    tipoImagemSelect.appendChild(option);
  });
  tipoImagemSelect.disabled = false;
  tipoImagemSelect.value = state.tipoImagem;
}

function popularSelectFuncao(funcoes) {
  funcaoSelect.innerHTML = '<option value="">Todas</option>';
  funcoes.forEach((funcao) => {
    const option = document.createElement("option");
    option.value = String(funcao.id);
    option.textContent = funcao.nome;
    funcaoSelect.appendChild(option);
  });
  funcaoSelect.disabled = false;
  funcaoSelect.value = state.funcaoId;
}

function limparTimeline(message) {
  emptyState.textContent = message;
  emptyState.classList.remove("hidden");
  timelineContainer.classList.add("hidden");
  timelineBody.innerHTML = "";
  metaResumo.textContent = message;
}

function renderTimeline() {
  if (!state.timeline.length || !state.minDate || !state.maxDate) {
    limparTimeline("Sem dados para os filtros selecionados.");
    return;
  }

  const grupos = agruparPorTipoEtapa(state.timeline);
  const gruposPorStatus = agruparPorStatus(grupos);
  const totalItens = grupos.length;
  const diasTotais = daysBetween(state.minDate, state.maxDate) + 1;
  const larguraPx = Math.max(900, diasTotais * state.dayWidth);

  timelineBody.innerHTML = "";

  gruposPorStatus.forEach((grupoStatus) => {
    const bloco = document.createElement("section");
    bloco.className = "status-group";

    const titulo = document.createElement("h3");
    titulo.className = "status-group-title";
    titulo.textContent = grupoStatus.status_nome || "Sem status";
    bloco.appendChild(titulo);

    grupoStatus.tipos.forEach((grupo) => {
      const row = document.createElement("div");
      row.className = "timeline-row";

      const label = document.createElement("div");
      label.className = "row-label";
      label.innerHTML = `
            <strong>${escapeHtml(grupo.tipo_imagem)}</strong>
            <span>${grupo.funcoes.length} função(ões)</span>
          `;

      const laneScroll = document.createElement("div");
      laneScroll.className = "lane-scroll";

      const lane = document.createElement("div");
      lane.className = "lane";
      lane.style.width = `${larguraPx}px`;

      const layout = distribuirFuncoesEmNiveis(grupo.funcoes);
      const barHeight = 52;
      const gapY = 8;
      const padY = 8;
      const totalBarsHeight =
        layout.totalNiveis * barHeight +
        Math.max(0, layout.totalNiveis - 1) * gapY;
      const laneHeight = Math.max(68, totalBarsHeight + padY * 2);
      const offsetY = Math.round((laneHeight - totalBarsHeight) / 2);

      lane.style.height = `${laneHeight}px`;
      label.style.minHeight = `${laneHeight}px`;

      layout.itens.forEach((item) => {
        const inicio = new Date(item.inicio_no_status);
        const fim = new Date(item.fim_no_status);
        const left = daysBetween(state.minDate, inicio) * state.dayWidth;
        const width = Math.max(
          120,
          (daysBetween(inicio, fim) + 1) * state.dayWidth,
        );
        const cores = corDaFuncao(item.funcao_id, item.nome_funcao);

        const bar = document.createElement("div");
        bar.className = "timeline-bar";
        bar.style.left = `${left}px`;
        bar.style.width = `${width}px`;
        bar.style.top = `${offsetY + item._nivel * (barHeight + gapY)}px`;
        bar.style.background = `linear-gradient(135deg, ${cores.inicio} 0%, ${cores.fim} 100%)`;
        bar.style.borderColor = cores.borda;
        bar.title = `${item.tipo_imagem} | ${item.nome_funcao} | ${item.total_imagens} imagem(ns) | ${dateFmt.format(inicio)} até ${dateFmt.format(fim)}`;
        bar.innerHTML = `
            <span>${escapeHtml(item.nome_funcao)}</span>
            <small>${formatarIntervalo(inicio, fim)}</small>
          `;

        lane.appendChild(bar);
      });

      laneScroll.appendChild(lane);
      row.appendChild(label);
      row.appendChild(laneScroll);
      bloco.appendChild(row);

      habilitarArrasteHorizontal(laneScroll);
    });

    timelineBody.appendChild(bloco);
  });

  const inicioFmt = dateFmt.format(state.minDate);
  const fimFmt = dateFmt.format(state.maxDate);
  metaResumo.textContent = `${totalItens} bloco(s) gerais | período: ${inicioFmt} até ${fimFmt}`;

  emptyState.classList.add("hidden");
  timelineContainer.classList.remove("hidden");
}

function agruparPorStatus(grupos) {
  const mapa = new Map();

  grupos.forEach((grupo) => {
    const chave = String(grupo.status_id);
    if (!mapa.has(chave)) {
      mapa.set(chave, {
        status_id: grupo.status_id,
        status_nome: grupo.status_nome,
        tipos: [],
      });
    }

    mapa.get(chave).tipos.push(grupo);
  });

  return Array.from(mapa.values()).sort((a, b) => a.status_id - b.status_id);
}

function distribuirFuncoesEmNiveis(funcoes) {
  const ordenadas = [...funcoes].sort((a, b) => {
    const inicioDiff =
      new Date(a.inicio_no_status) - new Date(b.inicio_no_status);
    if (inicioDiff !== 0) return inicioDiff;

    const fimDiff = new Date(a.fim_no_status) - new Date(b.fim_no_status);
    if (fimDiff !== 0) return fimDiff;

    return String(a.nome_funcao).localeCompare(String(b.nome_funcao), "pt-BR");
  });

  const ultimoFimPorNivel = [];

  const itens = ordenadas.map((item) => {
    const inicioDia = daysBetween(
      state.minDate,
      new Date(item.inicio_no_status),
    );
    const fimDia = daysBetween(state.minDate, new Date(item.fim_no_status));

    let nivel = 0;
    while (
      nivel < ultimoFimPorNivel.length &&
      inicioDia <= ultimoFimPorNivel[nivel]
    ) {
      nivel += 1;
    }

    ultimoFimPorNivel[nivel] = fimDia;

    return {
      ...item,
      _nivel: nivel,
    };
  });

  return {
    itens,
    totalNiveis: Math.max(1, ultimoFimPorNivel.length),
  };
}

function agruparPorTipoEtapa(lista) {
  const mapa = new Map();

  lista.forEach((item) => {
    const chave = `${item.status_id}||${item.tipo_imagem}`;
    if (!mapa.has(chave)) {
      mapa.set(chave, {
        status_id: item.status_id,
        status_nome: item.status_nome,
        tipo_imagem: item.tipo_imagem,
        funcoes: [],
      });
    }

    mapa.get(chave).funcoes.push(item);
  });

  const grupos = Array.from(mapa.values());
  grupos.forEach((grupo) => {
    grupo.funcoes.sort((a, b) => {
      const diffInicio =
        new Date(a.inicio_no_status) - new Date(b.inicio_no_status);
      if (diffInicio !== 0) return diffInicio;
      return String(a.nome_funcao).localeCompare(
        String(b.nome_funcao),
        "pt-BR",
      );
    });
  });

  grupos.sort((a, b) => {
    if (a.status_id !== b.status_id) return a.status_id - b.status_id;
    return String(a.tipo_imagem).localeCompare(String(b.tipo_imagem), "pt-BR");
  });

  return grupos;
}

function corDaFuncao(funcaoId, nomeFuncao) {
  const base =
    Number.isFinite(Number(funcaoId)) && Number(funcaoId) > 0
      ? Number(funcaoId)
      : hashString(nomeFuncao || "funcao");
  const hue = (base * 47) % 360;

  return {
    inicio: `hsl(${hue}, 78%, 53%)`,
    fim: `hsl(${(hue + 18) % 360}, 82%, 43%)`,
    borda: `hsl(${hue}, 72%, 35%)`,
  };
}

function hashString(texto) {
  let hash = 0;
  const str = String(texto || "");
  for (let i = 0; i < str.length; i += 1) {
    hash = (hash << 5) - hash + str.charCodeAt(i);
    hash |= 0;
  }
  return Math.abs(hash || 1);
}

function habilitarArrasteHorizontal(scrollEl) {
  if (scrollEl.dataset.dragBound === "1") {
    return;
  }
  scrollEl.dataset.dragBound = "1";

  let isDragging = false;
  let startX = 0;
  let startLeft = 0;

  scrollEl.addEventListener("mousedown", (event) => {
    isDragging = true;
    startX = event.pageX;
    startLeft = scrollEl.scrollLeft;
    scrollEl.classList.add("dragging");
  });

  window.addEventListener("mouseup", () => {
    if (!isDragging) return;
    isDragging = false;
    scrollEl.classList.remove("dragging");
  });

  scrollEl.addEventListener("mousemove", (event) => {
    if (!isDragging) return;
    const deslocamento = event.pageX - startX;
    scrollEl.scrollLeft = startLeft - deslocamento;
  });
}

function daysBetween(a, b) {
  const start = new Date(a);
  const end = new Date(b);
  start.setHours(0, 0, 0, 0);
  end.setHours(0, 0, 0, 0);
  return Math.round((end - start) / 86400000);
}

function formatarIntervalo(inicio, fim) {
  return `${dateFmt.format(inicio)} → ${dateFmt.format(fim)}`;
}

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text == null ? "" : String(text);
  return div.innerHTML;
}
