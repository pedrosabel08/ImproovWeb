let idImagemSelecionada = null;
let sortableInstances = [];
const selectedCards = new Set();
let _filterDebounceTimer = null;
let cardsCompactos = false;
let altConferenciaData = null;
let altCurrentImageUrl = "";
let altZoom = 1;
let altSidePanelCollapsed = false;
let altSidePanelActive = "files";
var idfuncao_imagem = null;
var titulo = null;
var subtitulo = null;
var obra = null;
var idimagem = null;
var nome_status = null;
var cardSelecionado = null;
let arquivosFinais = [];
let imagensSelecionadas = [];
let altApprovalColaboradorId = window.ALTERACAO_LOGGED_COLAB_ID || null;

function isCompactConferenceLayout() {
  return window.matchMedia("(max-width: 1180px)").matches;
}

const STATUS_COLUMNS = [
  { label: "Não iniciado", key: "nao-iniciado" },
  { label: "HOLD", key: "hold" },
  { label: "Em andamento", key: "em-andamento" },
  { label: "Em aprovação", key: "em-aprovacao" },
  { label: "Ajuste", key: "ajuste" },
  { label: "Aprovado com ajustes", key: "aprovado-ajustes" },
  // { label: "Finalizado", key: "finalizado" },
];

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function formatDate(value) {
  if (!value) return "-";
  const raw = String(value).slice(0, 10);
  const parts = raw.split("-");
  if (parts.length !== 3) return escapeHtml(value);
  return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function formatDateTime(value) {
  if (!value) return "-";
  const [date, time = ""] = String(value).split(" ");
  const formattedDate = formatDate(date);
  return time ? `${formattedDate} ${time.slice(0, 5)}` : formattedDate;
}

function getInitials(name) {
  return String(name || "?")
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join("")
    .toUpperCase();
}

function sftpToPublicUrl(rawPath) {
  if (!rawPath) return "";
  const p = String(rawPath).replace(/\\/g, "/");
  const full = p.match(
    /\/mnt\/clientes\/\d+\/([^/]+)\/05\.Exchange\/01\.Input\/(.*)/i,
  );
  if (full && full[1] && full[2]) {
    return `https://improov.com.br/flow/ImproovWeb/uploads/angulo_definido/${full[1]}/${full[2]}`;
  }
  const angulo = p.match(/\/Angulo_definido\/(.*)/i);
  if (angulo && angulo[1]) {
    return `https://improov.com.br/flow/ImproovWeb/uploads/angulo_definido/${angulo[1]}`;
  }
  const idx = p.indexOf("/05.Exchange/01.Input/");
  if (idx >= 0) {
    return `https://improov.com.br/flow/ImproovWeb/uploads/${p.substring(idx + "/05.Exchange/01.Input/".length)}`;
  }
  if (/^https?:\/\//i.test(p)) return p;
  if (/\.(jpg|jpeg|png|webp|gif)$/i.test(p)) {
    const path = window.location.pathname;
    const marker = "/ImproovWeb";
    const markerIndex = path.indexOf(marker);
    const base =
      markerIndex >= 0
        ? `${window.location.origin}${path.slice(0, markerIndex + marker.length)}`
        : window.location.origin;
    return `${base}/${p.replace(/^\/+/, "")}`;
  }
  return "";
}

function improovBaseUrl() {
  const path = window.location.pathname;
  const marker = "/ImproovWeb";
  const markerIndex = path.indexOf(marker);
  return markerIndex >= 0
    ? `${window.location.origin}${path.slice(0, markerIndex + marker.length)}`
    : window.location.origin;
}

function thumbUrl(rawPath, width = 1200, quality = 82) {
  if (!rawPath) return "";
  return `${improovBaseUrl()}/thumb.php?path=${encodeURIComponent(String(rawPath))}&w=${width}&q=${quality}`;
}

function setText(id, value) {
  const node = document.getElementById(id);
  if (node) node.textContent = value || "-";
}

function setCount(id, value) {
  const node = document.getElementById(id);
  if (node) node.textContent = value ? String(value) : "";
}

function buildConferenceModalShell() {
  const modal = document.getElementById("myModal");
  if (!modal || modal.dataset.conferenceReady === "1") return;

  const colaboradorOptions =
    document.getElementById("opcao_alteracao")?.innerHTML ||
    '<option value="">Ninguem</option>';
  const statusOptions =
    document.getElementById("status_alteracao")?.innerHTML ||
    '<option value="Nao iniciado">Nao iniciado</option><option value="Em andamento">Em andamento</option><option value="Em aprovacao">Em aprovacao</option><option value="Finalizado">Finalizado</option>';

  modal.classList.add("alt-conference-modal");
  modal.setAttribute("aria-hidden", "true");
  modal.innerHTML = `
    <div class="modal-content alt-conference-shell" role="dialog" aria-modal="true" aria-labelledby="campoNomeImagem">
      <input type="hidden" id="imagem_id">
      <header class="alt-conf-header">
        <div class="alt-conf-identity">
          <div class="alt-conf-thumb" id="altConfThumb"><i class="fa-regular fa-image"></i></div>
          <div class="alt-conf-title-block">
            <h2 class="modal-title alt-conf-title" id="campoNomeImagem">-</h2>
            <p class="alt-conf-description" id="altConfDescricao">Tela de confer&ecirc;ncia da altera&ccedil;&atilde;o</p>
            <div class="alt-conf-tags"><span id="altConfObra">-</span><span id="altConfSubtipo">-</span></div>
          </div>
        </div>
        <div class="alt-conf-meta-grid">
          <div class="alt-conf-meta"><span>Etapa atual</span><strong id="altConfEtapa" class="alt-pill">-</strong></div>
          <div class="alt-conf-meta"><span>Tipo de imagem</span><strong id="altConfTipo">-</strong></div>
          <div class="alt-conf-meta"><span>Status</span><strong id="altConfStatus" class="alt-pill">-</strong></div>
          <div class="alt-conf-meta"><span>Complexidade</span><strong id="altConfComplexidade">-</strong></div>
          <div class="alt-conf-meta"><span>Prazo</span><strong id="altConfPrazo">-</strong></div>
          <div class="alt-conf-meta"><span>Respons&aacute;vel</span><strong id="altConfResponsavel">-</strong></div>
          <div class="alt-conf-meta"><span>Iniciada em</span><strong id="altConfInicio">-</strong></div>
          <div class="alt-conf-meta"><span>&Uacute;ltima atualiza&ccedil;&atilde;o</span><strong id="altConfAtualizacao">-</strong></div>
        </div>
        <div class="alt-conf-header-actions">
          <a class="alt-conf-btn alt-conf-btn-review is-disabled" id="altConfReviewTop" href="#" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir no Review Studio</a>
          <button type="button" class="alt-conf-btn" id="altConfMoreActions" aria-expanded="false" aria-controls="altConfMorePanel">Mais a&ccedil;&otilde;es <i class="fa-solid fa-chevron-down"></i></button>
          <button class="modal-close" id="closeModal" title="Fechar" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
        </div>
      </header>
      <div class="alt-conf-more-panel" id="altConfMorePanel" hidden>
        <div class="alt-conf-more-head">
          <strong>Op&ccedil;&otilde;es da altera&ccedil;&atilde;o</strong>
          <button type="button" id="altConfMoreClose" aria-label="Fechar op&ccedil;&otilde;es"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="alt-conf-more-form">
          <div class="modal-field-group"><label class="filter-label">Colaborador</label><select class="filter-select modal-select" id="opcao_alteracao">${colaboradorOptions}</select></div>
          <div class="modal-field-group"><label class="filter-label">Status da Altera&ccedil;&atilde;o</label><select class="filter-select modal-select" id="status_alteracao">${statusOptions}</select></div>
          <div class="modal-field-group alt-conf-date-field"><label class="filter-label">Prazo</label><input type="date" class="filter-select modal-select" id="prazo_alteracao"></div>
          <div class="modal-field-group alt-conf-path-field"><label class="filter-label">Observação</label><input type="text" class="filter-input modal-input" id="obs_alteracao" placeholder="Caminho arquivo"></div>
        </div>
        <button type="button" class="alt-conf-more-link btn-action btn-primario" id="salvar_funcoes"><i class="fa-solid fa-floppy-disk"></i> Salvar altera&ccedil;&atilde;o</button>
      </div>
      <div class="alt-conf-body">
        <aside class="alt-conf-left">
          <nav class="alt-conf-nav" aria-label="Conferencia">
            <strong>1. CONFER&Ecirc;NCIA</strong>
            <a href="#altSecResumo" class="active"><i class="fa-regular fa-clipboard"></i> Resumo da pr&eacute;-altera&ccedil;&atilde;o <span id="altNavResumoCount"></span></a>
            <a href="#altSecComentarios"><i class="fa-regular fa-comments"></i> Coment&aacute;rios da imagem <span id="altNavComentariosCount">0</span></a>
            <a href="#altSecArquivos"><i class="fa-regular fa-folder-open"></i> Arquivos enviados <span id="altNavArquivosCount">0</span></a>
            <a href="#altSecReferencias"><i class="fa-regular fa-images"></i> Refer&ecirc;ncias por escopo <span id="altNavReferenciasCount">0</span></a>
            <a href="#altSecHistorico"><i class="fa-regular fa-clock"></i> Hist&oacute;rico da altera&ccedil;&atilde;o <span id="altNavHistoricoCount">0</span></a>
          </nav>
          <section class="alt-conf-card" id="altSecResumo">
            <div class="alt-conf-card-title"><h3>Resumo da Pr&eacute;-Altera&ccedil;&atilde;o</h3><button type="button" id="altConfEditarResumo">Editar</button></div>
            <div id="altConfResumo" class="alt-conf-summary"></div>
          </section>
        </aside>
        <main class="alt-conf-main">
          <section class="alt-conf-image-card">
            <div class="alt-conf-section-head">
              <h3>Imagem atual</h3>
              <div class="alt-conf-image-tools">
                <button type="button" id="altConfZoomOut" title="Reduzir zoom"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                <button type="button" id="altConfZoomValue">100%</button>
                <button type="button" id="altConfZoomIn" title="Aumentar zoom"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                <button type="button" id="altConfOpenImage" title="Abrir em tamanho maior"><i class="fa-solid fa-expand"></i></button>
              </div>
            </div>
            <div class="alt-conf-image-stage" id="altConfImageStage"><div class="alt-conf-image-empty"><i class="fa-regular fa-image"></i><span>Sem imagem dispon&iacute;vel</span></div></div>
            <div class="alt-conf-current-version">
              <div><span class="alt-conf-live-dot"></span><strong id="altConfVersionTitle">&Uacute;ltima vers&atilde;o dispon&iacute;vel</strong><small id="altConfVersionMeta">-</small></div>
              <a class="alt-conf-btn alt-conf-btn-review is-disabled" id="altConfReviewImage" href="#" target="_blank" rel="noopener noreferrer">Abrir no Review Studio <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
            </div>
          </section>
        </main>
        <button type="button" class="alt-conf-side-toggle" id="altConfSideToggle" aria-expanded="true" aria-controls="altConfRightPanel" title="Mostrar ou ocultar painel">
          <i class="fa-solid fa-chevron-right"></i>
          <span>Painel</span>
        </button>
        <aside class="alt-conf-right" id="altConfRightPanel" data-active-panel="files">
          <div class="alt-conf-side-head">
            <strong>Informa&ccedil;&otilde;es de apoio</strong>
          </div>
          <div class="alt-conf-side-tabs" role="tablist" aria-label="Painel de apoio">
            <button type="button" data-panel-target="summary"><i class="fa-regular fa-clipboard"></i> Resumo</button>
            <button type="button" class="active" data-panel-target="files"><i class="fa-regular fa-folder-open"></i> Arquivos</button>
            <button type="button" data-panel-target="refs"><i class="fa-regular fa-images"></i> Refer&ecirc;ncias</button>
            <button type="button" data-panel-target="steps"><i class="fa-regular fa-circle-check"></i> Passos</button>
          </div>
          <section class="alt-conf-card alt-conf-side-section" data-panel="summary" id="altSecResumoApoio"><div class="alt-conf-card-title"><h3>Resumo da pr&eacute;-altera&ccedil;&atilde;o</h3></div><div id="altConfResumoApoio" class="alt-conf-summary"></div></section>
          <section class="alt-conf-card alt-conf-side-section active" data-panel="files" id="altSecArquivos"><div class="alt-conf-card-title"><h3>Arquivos enviados na revis&atilde;o</h3><button type="button" id="altConfAllFiles">Ver todos</button></div><div class="alt-conf-tabs" id="altConfFileTabs"></div><div id="altConfArquivos" class="alt-conf-file-list"></div></section>
          <section class="alt-conf-card alt-conf-side-section" data-panel="refs" id="altSecReferencias"><div class="alt-conf-card-title"><h3>Refer&ecirc;ncias por escopo</h3><button type="button" id="altConfAllRefs">Ver todas</button></div><div class="alt-conf-tabs" id="altConfRefTabs"></div><div id="altConfReferencias" class="alt-conf-ref-list"></div></section>
          <section class="alt-conf-card alt-conf-side-section alt-conf-steps-card" data-panel="steps"><div class="alt-conf-card-title"><h3>Pr&oacute;ximos passos</h3></div><div id="altConfProximos" class="alt-conf-steps"></div></section>
        </aside>
      </div>
      <footer class="modal-footer alt-conf-footer">
        <button type="button" class="btn-action btn-secundario" id="closeModalBtn">Voltar para a lista</button>
        <button type="button" class="btn-action btn-secundario" id="altConfStart"><i class="fa-solid fa-play"></i> iniciar</button>
        <button type="button" class="btn-action btn-secundario" id="altConfQuestionBtn"><i class="fa-regular fa-comment-dots"></i> Registrar d&uacute;vida / bloqueio</button>
        <button type="button" class="btn-action btn-primario" id="altConfSendApproval">Enviar para aprova&ccedil;&atilde;o interna</button>
      </footer>
      <div class="alt-approval-upload-modal" id="altApprovalUploadModal" aria-hidden="true">
        <div class="alt-approval-upload-card" role="dialog" aria-modal="true" aria-labelledby="altApprovalUploadTitle">
          <div class="alt-approval-upload-head">
            <div>
              <span>Enviar para aprova&ccedil;&atilde;o</span>
              <h3 id="altApprovalUploadTitle">Pr&eacute;via e arquivo</h3>
            </div>
            <button type="button" id="altApprovalUploadClose" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
          </div>
          <div class="alt-approval-upload-body">
            <section class="alt-upload-section" id="etapaPrevia">
              <div class="alt-upload-section-title">
                <i class="fa-regular fa-images"></i>
                <strong>Pr&eacute;via</strong>
              </div>
              <div class="drop-area alt-drop-area" id="drop-area-previa">
                <input type="file" id="fileElemPrevia" accept="image/*" multiple hidden>
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <span>Solte as pr&eacute;vias aqui ou clique para selecionar</span>
              </div>
              <ul class="file-list alt-file-list" id="fileListPrevia"></ul>
              <button type="button" class="btn-action btn-primario alt-upload-send" id="altSendPrevia"><i class="fa-solid fa-paper-plane"></i> Enviar pr&eacute;via</button>
            </section>
            <section class="alt-upload-section" id="etapaFinal">
              <div class="alt-upload-section-title">
                <i class="fa-regular fa-file-lines"></i>
                <strong>Arquivo</strong>
              </div>
              <div class="drop-area alt-drop-area" id="drop-area-final">
                <input type="file" id="fileElemFinal" hidden>
                <i class="fa-solid fa-file-arrow-up"></i>
                <span>Solte o arquivo aqui ou clique para selecionar</span>
              </div>
              <ul class="file-list alt-file-list" id="fileListFinal"></ul>
              <button type="button" class="btn-action btn-primario alt-upload-send" id="altSendArquivo"><i class="fa-solid fa-paper-plane"></i> Enviar arquivo</button>
            </section>
          </div>
        </div>
      </div>
    </div>`;

  modal.dataset.conferenceReady = "1";
}

function normalizarStatus(status) {
  return (status || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

function getStatusKey(status) {
  const n = normalizarStatus(status);
  if (n === "nao iniciado") return "nao-iniciado";
  if (n === "hold") return "hold";
  if (n === "em andamento") return "em-andamento";
  if (n === "em aprovacao") return "em-aprovacao";
  if (n === "ajuste") return "ajuste";
  if (n === "aprovado com ajustes") return "aprovado-ajustes";
  if (n === "finalizado") return "finalizado";
  return "nao-iniciado";
}

function getEFKanbanStatusClass(status) {
  const n = normalizarStatus(status);
  if (n === "finalizado") return "ef-ks-done";
  if (n === "em andamento") return "ef-ks-wip";
  if (n === "em aprovacao") return "ef-ks-review";
  return "ef-ks-todo";
}

const STATUS_IMAGEM_CLASS_MAP = {
  P00: "si-p00",
  R00: "si-r00",
  R01: "si-r01",
  R02: "si-r02",
  R03: "si-r03",
  R04: "si-r04",
  R05: "si-r05",
  EF: "si-ef",
  HOLD: "si-hold",
  TEA: "si-tea",
  REN: "si-ren",
  APR: "si-apr",
  APP: "si-app",
  RVW: "si-rvw",
  OK: "si-ok",
  TO_DO: "si-to-do",
  FIN: "si-fin",
  DRV: "si-drv",
  RVW_DONE: "si-rvw-done",
  PRE_ALT: "si-pre-alt",
  READY_FOR_PLANNING: "si-ready-for-planning",
};

const STATUS_IMAGEM_CLASSES = Object.values(STATUS_IMAGEM_CLASS_MAP);

function getStatusImagemClass(status) {
  const key = String(status || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .trim()
    .toUpperCase()
    .replace(/[\s-]+/g, "_");
  return STATUS_IMAGEM_CLASS_MAP[key] || "";
}

function applyStatusImagemClass(element, status) {
  if (!element) return;
  element.classList.remove(...STATUS_IMAGEM_CLASSES);
  const cls = getStatusImagemClass(status);
  if (cls) {
    element.classList.add(cls);
  }
}

function getFilters() {
  return {
    status: document.getElementById("filtro-status").value,
    status_imagem: document.getElementById("filtro-status-imagem").value,
    obra_id: document.getElementById("filtro-obra").value,
    colaborador_id: document.getElementById("filtro-colaborador").value,
    busca: document.getElementById("filtro-busca").value.trim(),
  };
}

function montarQueryString(filtros) {
  const params = new URLSearchParams();
  Object.entries(filtros).forEach(([key, value]) => {
    if (value !== "" && value !== null && value !== undefined) {
      params.append(key, value);
    }
  });
  return params.toString();
}

function limparSelecao() {
  selectedCards.clear();
  document
    .querySelectorAll(".imagem-card.selected")
    .forEach((card) => card.classList.remove("selected"));
}

function preencherFiltros(filtros) {
  const selectObra = document.getElementById("filtro-obra");
  const selectColab = document.getElementById("filtro-colaborador");
  const selectStatusImagem = document.getElementById("filtro-status-imagem");

  const obraAtual = selectObra.value;
  const colabAtual = selectColab.value;
  const statusImagemAtual = selectStatusImagem.value;

  selectObra.innerHTML = '<option value="">Todas</option>';
  (filtros?.obras || []).forEach((obra) => {
    const option = document.createElement("option");
    option.value = obra.id;
    option.textContent = obra.nome;
    selectObra.appendChild(option);
  });
  if (obraAtual) selectObra.value = obraAtual;

  selectColab.innerHTML = '<option value="">Todos</option>';
  (filtros?.colaboradores || []).forEach((colab) => {
    const option = document.createElement("option");
    option.value = colab.id;
    option.textContent = colab.nome;
    selectColab.appendChild(option);
  });
  if (colabAtual) selectColab.value = colabAtual;

  selectStatusImagem.innerHTML = '<option value="">Todos</option>';
  (filtros?.status_imagens || []).forEach((status) => {
    const option = document.createElement("option");
    option.value = status.nome;
    option.textContent = status.nome;
    selectStatusImagem.appendChild(option);
  });
  if (statusImagemAtual) selectStatusImagem.value = statusImagemAtual;
}

function agruparPorObra(items) {
  return items.reduce((acc, item) => {
    const key = `${item.obra_id}`;
    if (!acc[key]) acc[key] = { obra_nome: item.obra_nome, items: [] };
    acc[key].items.push(item);
    return acc;
  }, {});
}

function applyStatusImagem(cell, status) {
  applyStatusImagemClass(cell, status);
}

function criarCard(item) {
  const card = document.createElement("div");
  card.className = "imagem-card";
  card.dataset.imagemId = item.imagem_id;
  card.dataset.funcaoId = String(item.funcao_id);

  if (!item.colaborador_id) card.classList.add("sem-colaborador");
  if (item.is_ef) card.classList.add("ef-card");
  if (cardsCompactos) card.classList.add("card-compact");

  const efLabelHtml = item.is_ef
    ? `<div class="ef-label"><i class="fa-solid fa-bolt"></i> Render em Alta</div>`
    : "";
  const nivel = Number(item.nivel_complexidade || 0);
  const nivelHtml =
    nivel >= 1 && nivel <= 5
      ? `<span class="nivel-chip nivel-n${nivel}">N${nivel}</span>`
      : "";

  card.innerHTML = `
    ${efLabelHtml}
    <div class="card-badges-row">
      <span class="badge">${item.status_nome}</span>
      ${nivelHtml}
    </div>
    <div class="card-title">${item.imagem_nome}</div>
    <div class="card-sub"><i class="fa-solid fa-building"></i> ${item.obra_nome}</div>
    <div class="card-footer">
      <div class="card-meta"><i class="fa-solid fa-user"></i> ${item.colaborador_nome || "—"}</div>
      <div class="card-meta"><i class="fa-solid fa-calendar"></i> ${item.prazo || "—"}</div>
    </div>
  `;

  applyStatusImagem(card.querySelector(".badge"), item.status_nome);

  card.addEventListener("click", (event) => {
    const funcaoId = String(item.funcao_id);
    if (event.ctrlKey || event.metaKey) {
      event.preventDefault();
      if (selectedCards.has(funcaoId)) {
        selectedCards.delete(funcaoId);
        card.classList.remove("selected");
      } else {
        selectedCards.add(funcaoId);
        card.classList.add("selected");
      }
      return;
    }
    limparSelecao();
    abrirModal(item.imagem_id);
  });

  return card;
}

function renderEFPanel(items) {
  const efItems = items.filter((i) => i.is_ef);
  const body = document.getElementById("ef-panel-body");
  const countEl = document.getElementById("ef-panel-count");

  if (countEl) countEl.textContent = String(efItems.length);
  if (!body) return;

  body.innerHTML = "";

  if (efItems.length === 0) {
    body.innerHTML = `
      <div class="ef-panel-empty">
        <i class="fa-solid fa-check"></i>
        <span>Nenhum EF</span>
      </div>`;
    return;
  }

  efItems.forEach((item) => {
    const div = document.createElement("div");
    div.className = "ef-item";
    div.dataset.imagemId = item.imagem_id;

    const ksCls = getEFKanbanStatusClass(item.status_funcao);
    const dateHtml = item.prazo
      ? `<div class="ef-item-date"><i class="fa-solid fa-calendar"></i> ${item.prazo}</div>`
      : "";

    div.innerHTML = `
      <div class="ef-item-top">
        <span class="badge si-ef" style="position:static;display:inline-block;">EF</span>
        <span class="ef-item-kanban-status ${ksCls}">${item.status_funcao}</span>
      </div>
      <div class="ef-item-title">${item.imagem_nome}</div>
      <div class="ef-item-meta"><i class="fa-solid fa-building"></i> ${item.obra_nome}</div>
      <div class="ef-item-footer">
        ${dateHtml}
        <div class="card-meta"><i class="fa-solid fa-user"></i> ${item.colaborador_nome || "—"}</div>
      </div>
    `;

    div.addEventListener("click", () => abrirModal(item.imagem_id));
    body.appendChild(div);
  });
}

function renderKanban(items) {
  STATUS_COLUMNS.forEach(({ key }) => {
    const container = document.getElementById(`kanban-${key}`);
    if (container) container.innerHTML = "";
  });

  // Update results count
  const resultsCountEl = document.getElementById("resultsCount");
  if (resultsCountEl) resultsCountEl.textContent = String(items.length);

  // Render EF panel
  renderEFPanel(items);

  const obraFiltrada = document.getElementById("filtro-obra").value !== "";

  STATUS_COLUMNS.forEach(({ label, key }) => {
    const container = document.getElementById(`kanban-${key}`);
    if (!container) return;

    const itensColuna = items.filter(
      (item) => getStatusKey(item.status_funcao) === key,
    );

    const countElement = document.getElementById(`count-${key}`);
    if (countElement) countElement.textContent = String(itensColuna.length);

    const efCountEl = document.getElementById(`ef-count-${key}`);
    if (efCountEl) {
      const efQty = itensColuna.filter((i) => i.is_ef).length;
      const efSpan = efCountEl.querySelector("span");
      if (efSpan) efSpan.textContent = String(efQty);
      efCountEl.style.display = efQty > 0 ? "flex" : "none";
    }

    if (itensColuna.length === 0) {
      const list = document.createElement("div");
      list.className = "cards-list";
      const empty = document.createElement("div");
      empty.className = "empty-state";
      empty.innerHTML =
        '<i class="fa-solid fa-inbox"></i><span>Nenhum item</span>';
      list.appendChild(empty);
      container.appendChild(list);
      return;
    }

    if (obraFiltrada) {
      const grupos = agruparPorObra(itensColuna);
      let cardIndex = 0;
      Object.values(grupos).forEach((grupo) => {
        const groupContainer = document.createElement("div");
        groupContainer.className = "obra-group";
        groupContainer.innerHTML = `<div class="obra-title">${grupo.obra_nome}</div>`;
        const list = document.createElement("div");
        list.className = "cards-list";
        grupo.items.forEach((item) => {
          const card = criarCard(item);
          card.style.animationDelay = `${cardIndex * 40}ms`;
          list.appendChild(card);
          cardIndex++;
        });
        groupContainer.appendChild(list);
        container.appendChild(groupContainer);
      });
      return;
    }

    const list = document.createElement("div");
    list.className = "cards-list";
    itensColuna.forEach((item, i) => {
      const card = criarCard(item);
      card.style.animationDelay = `${i * 40}ms`;
      list.appendChild(card);
    });
    container.appendChild(list);
  });

  inicializarDragAndDrop();
}

function inicializarDragAndDrop() {
  sortableInstances.forEach((instance) => instance.destroy());
  sortableInstances = [];

  document.querySelectorAll(".cards-list").forEach((list) => {
    const instance = new Sortable(list, {
      group: "alteracao-kanban",
      animation: 120,
      ghostClass: "drag-ghost",
      onStart: (evt) => {
        const funcaoId = evt.item?.dataset?.funcaoId;
        if (funcaoId && !selectedCards.has(funcaoId)) {
          limparSelecao();
          selectedCards.add(funcaoId);
          evt.item.classList.add("selected");
        }
      },
      onEnd: (evt) => {
        const coluna = evt.to.closest(".kanban-column");
        const statusDestino = coluna?.dataset?.status;
        if (!statusDestino) {
          recarregarAlteracao();
          return;
        }

        const funcaoId = evt.item?.dataset?.funcaoId;
        const ids =
          selectedCards.size > 0 && funcaoId && selectedCards.has(funcaoId)
            ? Array.from(selectedCards)
            : funcaoId
              ? [funcaoId]
              : [];

        if (ids.length === 0) {
          recarregarAlteracao();
          return;
        }
        atualizarStatusLote(ids, statusDestino);
      },
    });
    sortableInstances.push(instance);
  });
}

function atualizarStatusLote(ids, statusDestino) {
  const atribuirLogado = normalizarStatus(statusDestino) === "em andamento";

  fetch("updateStatusLote.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      funcao_ids: ids,
      status: statusDestino,
      atribuir_logado: atribuirLogado,
    }),
  })
    .then((r) => r.json())
    .then((data) => {
      if (!data.success) {
        Toastify({
          text: data.message || "Erro ao atualizar status.",
          duration: 3200,
          gravity: "top",
          position: "right",
          style: {
            background: "#ef4444",
            borderRadius: "8px",
            fontFamily: '"Inter",sans-serif',
            fontSize: "13px",
          },
        }).showToast();
        recarregarAlteracao();
        return;
      }
      Toastify({
        text: "Status atualizado!",
        duration: 2500,
        gravity: "top",
        position: "right",
        style: {
          background: "#10b981",
          borderRadius: "8px",
          fontFamily: '"Inter",sans-serif',
          fontSize: "13px",
        },
      }).showToast();
      recarregarAlteracao();
    })
    .catch(() => {
      Toastify({
        text: "Erro ao atualizar status.",
        duration: 3200,
        gravity: "top",
        position: "right",
        style: {
          background: "#ef4444",
          borderRadius: "8px",
          fontFamily: '"Inter",sans-serif',
          fontSize: "13px",
        },
      }).showToast();
      recarregarAlteracao();
    });
}

function recarregarAlteracao() {
  limparSelecao();
  const filtros = getFilters();
  const query = montarQueryString(filtros);

  STATUS_COLUMNS.forEach(({ key }) => {
    const container = document.getElementById(`kanban-${key}`);
    if (container) {
      container.innerHTML =
        '<div class="cards-list">' +
        '<div class="skeleton-card"></div>'.repeat(3) +
        "</div>";
    }
  });

  fetch(`getAlteracao.php${query ? `?${query}` : ""}`)
    .then((r) => r.json())
    .then((data) => {
      if (!data.success)
        throw new Error(data.message || "Erro ao carregar dados.");
      preencherFiltros(data.filtros);
      renderKanban(data.items || []);
    })
    .catch((error) => console.error("Erro ao carregar Kanban:", error));
}

// ─── Modal ───
function complexityLabel(value) {
  const n = Number(value || 0);
  if (n <= 0) return "-";
  if (n <= 2) return "Baixa";
  if (n === 3) return "Media";
  if (n === 4) return "Alta";
  return "Critica";
}

function normalizeLabel(text) {
  return String(text || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

function complexityClass(value) {
  return normalizeLabel(complexityLabel(value));
}

function applyComplexityClass(element, value) {
  if (!element) return;
  element.classList.remove("baixa", "media", "alta", "critica");
  const cls = complexityClass(value);
  if (cls && cls !== "-") {
    element.classList.add(cls);
  }
}

function fileIcon(type) {
  const t = String(type || "").toLowerCase();
  if (t === "pdf") return "fa-file-pdf";
  if (["jpg", "jpeg", "png", "webp"].includes(t)) return "fa-file-image";
  if (["dwg", "rvt", "skp"].includes(t)) return "fa-cube";
  return "fa-file";
}

function renderReviewLinks(url) {
  ["altConfReviewTop", "altConfReviewImage"].forEach((id) => {
    const link = document.getElementById(id);
    if (!link) return;
    if (url) {
      link.href = url;
      link.classList.remove("is-disabled");
      link.removeAttribute("aria-disabled");
    } else {
      link.href = "#";
      link.classList.add("is-disabled");
      link.setAttribute("aria-disabled", "true");
    }
  });
}

function renderMainImage(data) {
  const stage = document.getElementById("altConfImageStage");
  const thumb = document.getElementById("altConfThumb");
  const path = data?.latest_version?.public_path || "";
  altCurrentImageUrl = thumbUrl(path, 2200, 90);
  altZoom = 1;
  const zoomValue = document.getElementById("altConfZoomValue");
  if (zoomValue) zoomValue.textContent = "100%";

  if (!stage) return;
  if (!path) {
    stage.innerHTML = `<div class="alt-conf-image-empty"><i class="fa-regular fa-image"></i><span>Sem imagem disponivel</span></div>`;
    if (thumb) thumb.innerHTML = '<i class="fa-regular fa-image"></i>';
    return;
  }

  const stageUrl = thumbUrl(path, 1600, 84);
  const thumbSrc = thumbUrl(path, 360, 70);
  stage.innerHTML = `<img id="altConfMainImage" src="${stageUrl}" alt="Imagem atual">`;
  if (thumb) thumb.innerHTML = `<img src="${thumbSrc}" alt="">`;
}

function updateZoom(delta) {
  const image = document.getElementById("altConfMainImage");
  if (!image) return;
  altZoom = Math.min(2, Math.max(0.6, Number((altZoom + delta).toFixed(1))));
  image.style.transform = `scale(${altZoom})`;
  document.getElementById("altConfZoomValue").textContent =
    `${Math.round(altZoom * 100)}%`;
}

function renderSummary(summary, containerId = "altConfResumo") {
  const container = document.getElementById(containerId);
  if (!container) return;
  const hasRealChange =
    summary.has_real_change === null
      ? "-"
      : summary.has_real_change
        ? "Sim"
        : "Nao";
  const returnRequired =
    summary.return_required === null
      ? "-"
      : summary.return_required
        ? "Sim"
        : "Nao";
  const planning = summary.planning || null;
  const planningHtml = planning
    ? `
    <a class="alt-conf-summary-row alt-conf-planning-link" href="${escapeHtml(planning.url || "#")}" target="_blank" rel="noopener noreferrer">
      <span><i class="fa-solid fa-diagram-project"></i> Planejamento visual</span>
      <strong>${escapeHtml(planning.status || "RASCUNHO")}</strong>
    </a>`
    : "";

  container.innerHTML = `
    <div class="alt-conf-summary-row">
      <span><i class="fa-solid fa-circle-check"></i> Há alteração real</span>
      <strong>${escapeHtml(hasRealChange)}</strong>
    </div>

    <div class="alt-conf-summary-row ${escapeHtml(complexityClass(summary.complexity))}">
      <span><i class="fa-solid fa-layer-group"></i> Complexidade</span>
      <strong class="complexity-badge ${escapeHtml(complexityClass(summary.complexity))}">
        ${escapeHtml(complexityLabel(summary.complexity))}
      </strong>
    </div>

    <div class="alt-conf-summary-block">
      <span><i class="fa-solid fa-clipboard-list"></i> Observação da triagem</span>
      <p>${escapeHtml(summary.triage_note || "Sem observação registrada.")}</p>
    </div>

    <div class="alt-conf-summary-block">
      <span><i class="fa-solid fa-tags"></i> Tipo de alteração</span>
      <p>${escapeHtml(summary.type || "Sem tipo de alteração registrado.")}</p>
    </div>

    <div class="alt-conf-summary-row">
      <span><i class="fa-solid fa-user-check"></i> Responsável pela triagem</span>
      <strong>${escapeHtml(summary.responsible || "-")}</strong>
    </div>
    ${planningHtml}
  `;
}

function renderFiles(containerId, tabsId, grouped, labels) {
  const tabs = document.getElementById(tabsId);
  const container = document.getElementById(containerId);
  if (!tabs || !container) return;

  const keys = Object.keys(labels);
  let active = keys.find((key) => (grouped[key] || []).length > 0) || keys[0];

  function draw(key) {
    active = key;
    tabs.querySelectorAll("button").forEach((btn) => {
      btn.classList.toggle("active", btn.dataset.key === active);
    });
    const list = grouped[active] || [];
    if (list.length === 0) {
      container.innerHTML = `<div class="alt-conf-empty">Nenhum item encontrado.</div>`;
      return;
    }
    container.innerHTML = list
      .slice(0, 5)
      .map((file) => {
        const type = escapeHtml(file.type || "ARQ");
        const date = formatDate(file.date);
        const name = escapeHtml(file.name);
        const desc = escapeHtml(file.description || file.category || "");
        const copyPath = normalizeFileCopyPath(file.path || "");
        return `
          <div class="alt-conf-file-row" title="${escapeHtml(copyPath || file.path || "")}" data-copy-path="${escapeHtml(copyPath)}">
            <div class="alt-conf-file-icon"><i class="fa-solid ${fileIcon(type)}"></i></div>
            <div class="alt-conf-file-info">
              <strong>${name}</strong>
              <span>${type}${desc ? " - " + desc : ""}</span>
            </div>
            <small>${date}</small>
          </div>`;
      })
      .join("");
    bindFileCopyRows(container);
  }

  tabs.innerHTML = keys
    .map(
      (key) =>
        `<button type="button" class="${key === active ? "active" : ""}" data-key="${key}">${labels[key]} <span>${(grouped[key] || []).length}</span></button>`,
    )
    .join("");
  tabs.querySelectorAll("button").forEach((btn) => {
    btn.addEventListener("click", () => draw(btn.dataset.key));
  });
  draw(active);
}

function flattenFileGroups(grouped, labels) {
  return Object.entries(labels).flatMap(([key, label]) =>
    (grouped?.[key] || []).map((file) => ({
      ...file,
      group_key: key,
      group_label: label,
    })),
  );
}

function groupFilesByCategory(files) {
  return files.reduce((acc, file) => {
    const category = file.category || "Sem categoria";
    const type = file.type || "Outros";
    if (!acc[category]) acc[category] = {};
    if (!acc[category][type]) acc[category][type] = [];
    acc[category][type].push(file);
    return acc;
  }, {});
}

function normalizeFileCopyPath(rawPath) {
  let path = String(rawPath || "").trim();
  if (!path) return "";

  path = path.replace(/^\/mnt\/clientes\/?/i, "Z:/");
  path = path.replace(/^Z:\/*/i, "Z:/");
  path = path.replace(/[\\/]+/g, "/");
  path = path.replace(/^Z:\/?/i, "Z:/");
  path = path.replace(/\/+$/, "");

  const lastSlash = path.lastIndexOf("/");
  if (lastSlash > "Z:/".length) {
    path = path.slice(0, lastSlash);
  }

  return path;
}

function showAltToast(text, type = "success") {
  if (typeof Toastify !== "function") return;
  Toastify({
    text,
    duration: 2500,
    gravity: "top",
    position: "right",
    style: {
      background: type === "error" ? "#ef4444" : "#10b981",
      borderRadius: "8px",
      fontFamily: '"Inter",sans-serif',
      fontSize: "13px",
    },
  }).showToast();
}

function getImproovBaseUrl() {
  const path = window.location.pathname;
  const marker = "/ImproovWeb";
  const markerIndex = path.indexOf(marker);
  if (markerIndex >= 0) {
    return `${window.location.origin}${path.slice(0, markerIndex + marker.length)}`;
  }
  return window.location.origin;
}

function getApprovalStatusValue() {
  const status = document.getElementById("status_alteracao");
  const fallback = "Em aprova\u00e7\u00e3o";
  if (!status) return fallback;
  const target = Array.from(status.options).find(
    (opt) => normalizarStatus(opt.value) === "em aprovacao",
  );
  return target ? target.value : fallback;
}

function setApprovalUploadContext(data = altConferenciaData) {
  const image = data?.image || {};
  idfuncao_imagem = image.alteracao_funcao_id || idfuncao_imagem || null;
  idimagem = image.imagem_id || idImagemSelecionada || idimagem || null;
  titulo = image.imagem_nome || titulo || "";
  subtitulo = "Altera\u00e7\u00e3o";
  obra = image.nomenclatura || image.nome_obra || obra || "";
  nome_status = image.alteracao_status || nome_status || "";
  altApprovalColaboradorId =
    window.ALTERACAO_LOGGED_COLAB_ID ||
    image.colaborador_id ||
    altApprovalColaboradorId ||
    "";
  cardSelecionado = null;
}

async function refreshApprovalContext() {
  if (!idimagem && !idImagemSelecionada) return;
  const response = await fetch(
    `getConferenciaAlteracao.php?imagem_id=${encodeURIComponent(idimagem || idImagemSelecionada)}`,
  );
  const data = await response.json();
  if (!data.success) {
    throw new Error(
      data.message || "Erro ao atualizar dados da altera\u00e7\u00e3o.",
    );
  }
  renderConference(data);
  setApprovalUploadContext(data);
}

async function salvarStatusAprovacaoInterna() {
  setApprovalUploadContext();
  if (!idimagem) {
    throw new Error("Nenhuma imagem selecionada.");
  }

  const statusValue = getApprovalStatusValue();
  const status = document.getElementById("status_alteracao");
  if (status) status.value = statusValue;

  const formData = new FormData();
  formData.append("imagem_id", idimagem);
  formData.append("funcao_id", "6");
  formData.append(
    "colaborador_id",
    document.getElementById("opcao_alteracao")?.value ||
      altApprovalColaboradorId ||
      "",
  );
  formData.append("status", statusValue);
  formData.append(
    "prazo",
    document.getElementById("prazo_alteracao")?.value || "",
  );
  formData.append(
    "observacao",
    document.getElementById("obs_alteracao")?.value || "",
  );

  const response = await fetch("../insereFuncao.php", {
    method: "POST",
    body: formData,
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.error) {
    throw new Error(
      data.error || "Erro ao salvar status de aprova\u00e7\u00e3o.",
    );
  }

  await refreshApprovalContext();
  if (!idfuncao_imagem) {
    throw new Error(
      "N\u00e3o foi poss\u00edvel localizar a fun\u00e7\u00e3o de altera\u00e7\u00e3o.",
    );
  }
}

function configurarDropzone(dropId, inputId, listaId, destino) {
  const dropArea = document.getElementById(dropId);
  const fileInput = document.getElementById(inputId);
  if (!dropArea || !fileInput) return;

  if (dropArea._altDropHandlers) {
    dropArea.removeEventListener("click", dropArea._altDropHandlers.click);
    dropArea.removeEventListener(
      "dragover",
      dropArea._altDropHandlers.dragover,
    );
    dropArea.removeEventListener(
      "dragleave",
      dropArea._altDropHandlers.dragleave,
    );
    dropArea.removeEventListener("drop", dropArea._altDropHandlers.drop);
    fileInput.removeEventListener("change", dropArea._altDropHandlers.change);
  }

  const addFiles = (files) => {
    Array.from(files || []).forEach((file) => destino.push(file));
    renderizarLista(destino, listaId);
  };
  const handlers = {
    click: () => fileInput.click(),
    dragover: (event) => {
      event.preventDefault();
      dropArea.classList.add("highlight");
    },
    dragleave: () => dropArea.classList.remove("highlight"),
    drop: (event) => {
      event.preventDefault();
      dropArea.classList.remove("highlight");
      addFiles(event.dataTransfer?.files);
    },
    change: () => {
      addFiles(fileInput.files);
      fileInput.value = "";
    },
  };

  dropArea.addEventListener("click", handlers.click);
  dropArea.addEventListener("dragover", handlers.dragover);
  dropArea.addEventListener("dragleave", handlers.dragleave);
  dropArea.addEventListener("drop", handlers.drop);
  fileInput.addEventListener("change", handlers.change);
  dropArea._altDropHandlers = handlers;
}

function renderizarLista(array, listaId) {
  const lista = document.getElementById(listaId);
  if (!lista) return;
  lista.innerHTML = "";
  array.forEach((file, i) => {
    let tamanho = file.size;
    let tamanhoStr = "";
    if (tamanho < 1024) {
      tamanhoStr = `${tamanho} B`;
    } else if (tamanho < 1024 * 1024) {
      tamanhoStr = `${(tamanho / 1024).toFixed(1)} KB`;
    } else if (tamanho < 1024 * 1024 * 1024) {
      tamanhoStr = `${(tamanho / (1024 * 1024)).toFixed(2)} MB`;
    } else {
      tamanhoStr = `${(tamanho / (1024 * 1024 * 1024)).toFixed(2)} GB`;
    }

    const li = document.createElement("li");
    const info = document.createElement("div");
    info.className = "file-info";

    const name = document.createElement("span");
    name.textContent = `${file.name} (${tamanhoStr})`;

    const remove = document.createElement("button");
    remove.type = "button";
    remove.setAttribute("aria-label", "Remover arquivo");
    remove.innerHTML = '<i class="fa-solid fa-xmark"></i>';
    remove.addEventListener("click", () => removerArquivo(i, listaId));

    info.append(name, remove);
    li.appendChild(info);
    lista.appendChild(li);
  });
}

function removerArquivo(index, listaId) {
  if (listaId === "fileListPrevia") {
    imagensSelecionadas.splice(index, 1);
    renderizarLista(imagensSelecionadas, listaId);
  } else {
    arquivosFinais.splice(index, 1);
    renderizarLista(arquivosFinais, listaId);
  }
}

function abrirModalEnvioAprovacao() {
  setApprovalUploadContext();
  imagensSelecionadas = [];
  arquivosFinais = [];
  renderizarLista(imagensSelecionadas, "fileListPrevia");
  renderizarLista(arquivosFinais, "fileListFinal");
  configurarDropzone(
    "drop-area-previa",
    "fileElemPrevia",
    "fileListPrevia",
    imagensSelecionadas,
  );
  configurarDropzone(
    "drop-area-final",
    "fileElemFinal",
    "fileListFinal",
    arquivosFinais,
  );

  const title = document.getElementById("altApprovalUploadTitle");
  if (title) title.textContent = titulo || "Pr\u00e9via e arquivo";

  const modal = document.getElementById("altApprovalUploadModal");
  if (modal) {
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
  }
}

function fecharModalEnvioAprovacao() {
  const modal = document.getElementById("altApprovalUploadModal");
  if (modal) {
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
  }
}

async function enviarImagens() {
  if (imagensSelecionadas.length === 0) {
    showAltToast(
      "Selecione pelo menos uma imagem para enviar a pr\u00e9via.",
      "error",
    );
    return;
  }

  const _doEnviar = async () => {
    try {
      await salvarStatusAprovacaoInterna();
    } catch (error) {
      showAltToast(error.message || "Erro ao preparar envio.", "error");
      return;
    }

    const formData = new FormData();
    imagensSelecionadas.forEach((file) => formData.append("imagens[]", file));
    formData.append("dataIdFuncoes", idfuncao_imagem);
    formData.append("idimagem", idimagem);
    formData.append("nome_funcao", subtitulo);
    formData.append("nome_imagem", titulo);

    const numeroImagem = String(titulo || "").match(/^\d+/)?.[0] || "";
    formData.append("numeroImagem", numeroImagem);
    formData.append("nomenclatura", obra);

    const descricaoMatch = String(titulo || "").match(
      /^\d+\.\s*[A-Z0-9_]+\s+([^\s]+)/i,
    );
    const primeiraPalavra = descricaoMatch ? descricaoMatch[1] : "";
    formData.append("primeiraPalavra", primeiraPalavra);

    const badgeId = "prev_" + Date.now();
    const _totalBytes = imagensSelecionadas.reduce((acc, f) => acc + f.size, 0);
    const _displayName =
      imagensSelecionadas.length === 1
        ? imagensSelecionadas[0].name
        : imagensSelecionadas.length + " imagens";

    const xhr = new XMLHttpRequest();
    let uploadCancelado = false;

    if (window.UploadBadge)
      window.UploadBadge.add(badgeId, _displayName, _totalBytes, xhr);

    xhr.open("POST", `${getImproovBaseUrl()}/uploadArquivos.php`);

    xhr.upload.addEventListener("progress", (e) => {
      if (e.lengthComputable && window.UploadBadge) {
        const percent = (e.loaded / e.total) * 100;
        window.UploadBadge.phase1Progress(badgeId, percent, e.loaded, e.total);
      }
    });

    xhr.onabort = () => {
      uploadCancelado = true;
    };

    xhr.onreadystatechange = () => {
      if (xhr.readyState === 4 && !uploadCancelado) {
        try {
          const res = JSON.parse(xhr.responseText);

          if (res.error) {
            if (window.UploadBadge)
              window.UploadBadge.error(badgeId, "Erro: " + res.error);
            showAltToast("Erro: " + res.error, "error");
          } else {
            if (window.UploadBadge) window.UploadBadge.complete(badgeId);
            showAltToast("Pr\u00e9via enviada com sucesso!");
            recarregarAlteracao();
          }
        } catch (err) {
          if (window.UploadBadge)
            window.UploadBadge.error(badgeId, "Erro ao processar resposta");
          showAltToast("Erro ao processar resposta do servidor", "error");
          console.error(err);
        }
      }
    };

    xhr.onerror = () => {
      if (!uploadCancelado) {
        if (window.UploadBadge)
          window.UploadBadge.error(badgeId, "Erro ao enviar pr\u00e9via");
        showAltToast("Erro ao enviar pr\u00e9via", "error");
      }
    };

    xhr.send(formData);
  };

  if (idfuncao_imagem && normalizarStatus(nome_status) === "ajuste") {
    fetch(
      `${getImproovBaseUrl()}/FlowReview/verificar_comentarios_pendentes.php?funcao_imagem_id=${encodeURIComponent(idfuncao_imagem)}`,
    )
      .then((r) => r.json())
      .then((data) => {
        if (data && data.tem_pendentes && window.Swal) {
          Swal.fire({
            icon: "warning",
            title: "Coment\u00e1rios pendentes",
            html: `Existem <strong>${data.pendentes}</strong> coment\u00e1rio(s) n\u00e3o conclu\u00eddo(s).<br>
                   Acesse o <strong>Flow Review</strong> e marque todos os ajustes como conclu\u00eddos antes de enviar uma nova vers\u00e3o.`,
            showCancelButton: true,
            confirmButtonText: "Ir para o Flow Review",
            cancelButtonText: "Entendido",
            confirmButtonColor: "#2563eb",
            cancelButtonColor: "#f59e0b",
          }).then((result) => {
            if (!result.isConfirmed) return;
            const base = `${getImproovBaseUrl()}/FlowReview/index.php`;
            const url = obra
              ? `${base}?obra_nome=${encodeURIComponent(obra)}`
              : base;
            window.open(url, "_blank");
          });
        } else {
          _doEnviar();
        }
      })
      .catch(() => _doEnviar());
  } else {
    _doEnviar();
  }
}

async function enviarArquivo() {
  if (arquivosFinais.length === 0) {
    showAltToast(
      "Selecione pelo menos um arquivo para enviar o final.",
      "error",
    );
    return;
  }

  try {
    await salvarStatusAprovacaoInterna();
  } catch (error) {
    showAltToast(error.message || "Erro ao preparar envio.", "error");
    return;
  }

  const file = arquivosFinais[0];
  const formData = new FormData();
  formData.append("arquivo_final", file);
  formData.append("tipo_tarefa", "imagem");
  formData.append("dataIdFuncoes", JSON.stringify([idfuncao_imagem]));
  formData.append("idimagem", idimagem);
  formData.append("nome_funcao", subtitulo);
  const campoNomeImagem = titulo;
  formData.append("nome_imagem", campoNomeImagem);
  formData.append("nome_imagem_original", campoNomeImagem);

  const numeroImagem = String(campoNomeImagem || "").match(/^\d+/)?.[0] || "";
  formData.append("numeroImagem", numeroImagem);
  const nomenclatura = obra;
  formData.append("nomenclatura", nomenclatura);
  const descricaoMatch = String(campoNomeImagem || "").match(
    /^\d+\.\s*[A-Z0-9_]+\s+([^\s]+)/i,
  );
  const primeiraPalavra = descricaoMatch ? descricaoMatch[1] : "";
  formData.append("primeiraPalavra", primeiraPalavra);
  formData.append("idcolaborador", altApprovalColaboradorId);

  const clientId =
    "upl_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9);
  try {
    localStorage.setItem("improov_client_id", clientId);
  } catch (e) {}
  if (window.improovUploadWS) window.improovUploadWS.subscribe(clientId);
  formData.append("client_id", clientId);

  const xhr = new XMLHttpRequest();
  let uploadCancelado = false;

  if (window.UploadBadge)
    window.UploadBadge.add(clientId, file.name, file.size, xhr);

  const _enqueueUrl = window.IMPROOV_APP_BASE
    ? window.location.origin + window.IMPROOV_APP_BASE + "/upload_enqueue.php"
    : `${getImproovBaseUrl()}/upload_enqueue.php`;
  xhr.open("POST", _enqueueUrl);

  xhr.upload.addEventListener("progress", (e) => {
    if (e.lengthComputable && window.UploadBadge) {
      const percent = (e.loaded / e.total) * 100;
      window.UploadBadge.phase1Progress(clientId, percent, e.loaded, e.total);
    }
  });

  xhr.onabort = () => {
    uploadCancelado = true;
  };

  xhr.onload = () => {
    if (uploadCancelado) return;
    let res = null;
    try {
      res = JSON.parse(xhr.responseText || "null");
    } catch (err) {
      console.error("Resposta nao-JSON do servidor:", xhr.responseText);
    }

    if (xhr.status >= 200 && xhr.status < 300 && !(res && res.error)) {
      if (window.UploadBadge)
        window.UploadBadge.phase2Progress(
          clientId,
          0,
          "Na fila - aguardando transferencia...",
        );
      fecharModalEnvioAprovacao();
      recarregarAlteracao();
    } else {
      const serverMsg =
        res?.error || xhr.responseText || `Status ${xhr.status}`;
      if (window.UploadBadge)
        window.UploadBadge.error(clientId, "Erro no servidor");
      showAltToast("Erro no servidor: " + serverMsg, "error");
    }
  };

  xhr.onerror = () => {
    if (!uploadCancelado) {
      if (window.UploadBadge)
        window.UploadBadge.error(clientId, "Erro ao enfileirar arquivo");
      showAltToast("Erro ao enfileirar arquivo", "error");
    }
  };

  xhr.send(formData);
}

async function copyTextToClipboard(text) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    try {
      await navigator.clipboard.writeText(text);
      return;
    } catch (err) {
      // Some embedded browsers expose the Clipboard API but block it at runtime.
    }
  }

  const tempInput = document.createElement("textarea");
  tempInput.value = text;
  tempInput.setAttribute("readonly", "readonly");
  tempInput.style.position = "fixed";
  tempInput.style.left = "-9999px";
  tempInput.style.top = "0";
  tempInput.style.opacity = "0";
  document.body.appendChild(tempInput);
  tempInput.focus();
  tempInput.select();
  tempInput.setSelectionRange(0, tempInput.value.length);
  const copied = document.execCommand("copy");
  document.body.removeChild(tempInput);
  if (!copied) throw new Error("copy_failed");
}

function bindFileCopyRows(root) {
  root.querySelectorAll("[data-copy-path]").forEach((row) => {
    if (row.dataset.copyBound === "1") return;
    row.dataset.copyBound = "1";
    row.addEventListener("click", async () => {
      const path = row.dataset.copyPath || "";
      if (!path) return;

      try {
        await copyTextToClipboard(path);
        showAltToast("Caminho da pasta copiado.");
      } catch (err) {
        showAltToast("Nao foi possivel copiar o caminho.", "error");
      }
    });
  });
}

function openAltFilesModal(title, files) {
  const existing = document.getElementById("alt-files-modal");
  if (existing) existing.remove();

  const modal = document.createElement("div");
  modal.id = "alt-files-modal";
  modal.className = "alt-files-modal is-open";
  modal.innerHTML = `
    <div class="alt-files-modal-content" role="dialog" aria-modal="true">
      <header class="alt-files-modal-header">
        <h3>${escapeHtml(title)}</h3>
        <button type="button" class="alt-files-modal-close" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
      </header>
      <div class="alt-files-modal-body"></div>
    </div>
  `;
  document.body.appendChild(modal);

  const close = () => modal.remove();
  modal
    .querySelector(".alt-files-modal-close")
    ?.addEventListener("click", close);
  modal.addEventListener("click", (event) => {
    if (event.target === modal) close();
  });

  const body = modal.querySelector(".alt-files-modal-body");
  if (!files.length) {
    body.innerHTML = `<div class="alt-conf-empty">Sem arquivos disponiveis.</div>`;
    return;
  }

  const grouped = groupFilesByCategory(files);
  body.innerHTML = Object.entries(grouped)
    .map(
      ([category, byType]) => `
      <section class="alt-files-category">
        <div class="alt-files-category-head">
          <strong>${escapeHtml(category)}</strong>
          <span>${Object.values(byType).reduce((sum, arr) => sum + arr.length, 0)} arquivo(s)</span>
        </div>
        ${Object.entries(byType)
          .map(
            ([type, rows]) => `
            <div class="alt-files-type">
              <h4>${escapeHtml(type)}</h4>
              ${rows
                .map((file) => {
                  const copyPath = normalizeFileCopyPath(file.path || "");
                  return `
                  <article class="alt-files-row" title="${escapeHtml(copyPath || file.path || "")}" data-copy-path="${escapeHtml(copyPath)}">
                    <div class="alt-conf-file-icon"><i class="fa-solid ${fileIcon(type)}"></i></div>
                    <div>
                      <strong>${escapeHtml(file.name || "Arquivo")}</strong>
                      <span>${escapeHtml(file.group_label || file.scope || "")}${file.suffix ? " - " + escapeHtml(file.suffix) : ""}</span>
                      ${file.description ? `<small>${escapeHtml(file.description)}</small>` : ""}
                    </div>
                    <time>${escapeHtml(formatDate(file.date) || "")}</time>
                  </article>`;
                })
                .join("")}
            </div>`,
          )
          .join("")}
      </section>`,
    )
    .join("");
  bindFileCopyRows(body);
}

function renderHistory(data) {
  const container = document.getElementById("altConfHistorico");
  if (!container) return;
  const logs = data?.history?.logs || [];
  const approvals = data?.history?.approvals || [];
  const combined = [
    ...logs.map((log) => ({
      date: log.data,
      title: log.status_novo || "Atualizacao",
      subtitle: log.status_anterior
        ? `${log.status_anterior} -> ${log.status_novo}`
        : "Log da alteracao",
      actor: log.responsavel || "-",
    })),
    ...approvals.map((item) => ({
      date: item.data_aprovacao,
      title: item.status_novo || "Aprovacao interna",
      subtitle: item.observacoes || "Historico de aprovacao interna",
      actor: item.responsavel_nome || item.colaborador_nome || "-",
    })),
  ].sort((a, b) => String(b.date || "").localeCompare(String(a.date || "")));

  if (combined.length === 0) {
    container.innerHTML = `<div class="alt-conf-empty">Sem historico registrado.</div>`;
    return;
  }

  container.innerHTML = combined
    .slice(0, 6)
    .map(
      (item) => `
        <div class="alt-conf-history-item">
          <div class="alt-conf-history-dot">${escapeHtml(String(item.title || "R").slice(0, 3))}</div>
          <div>
            <strong>${escapeHtml(item.title)}</strong>
            <span>${escapeHtml(item.subtitle)}</span>
            <small>${escapeHtml(item.actor)} - ${formatDateTime(item.date)}</small>
          </div>
        </div>`,
    )
    .join("");
}

function renderCommunication(data) {
  const container = document.getElementById("altConfComunicacao");
  if (!container) return;
  const comments = data?.comments || [];
  const history = data?.pre_alteracao_history || [];
  const rows = [
    ...comments.map((comment) => ({
      date: comment.data,
      actor: comment.responsavel || "Cliente",
      tag:
        comment.concluido === "1" || comment.concluido === 1
          ? "Resolvido"
          : "Pendente",
      text: comment.texto || "Comentario sem texto.",
    })),
    ...history.map((item) => ({
      date: item.created_at,
      actor: item.nome_colaborador || "Triagem",
      tag: item.tipo_evento || "Pre-alteracao",
      text: item.observacao || item.valor_novo || "Atualizacao registrada.",
    })),
  ].sort((a, b) => String(b.date || "").localeCompare(String(a.date || "")));

  if (rows.length === 0) {
    container.innerHTML = `<div class="alt-conf-empty">Sem comunicacoes ou bloqueios.</div>`;
    return;
  }

  container.innerHTML = rows
    .slice(0, 5)
    .map(
      (row) => `
        <div class="alt-conf-message">
          <div class="alt-conf-avatar">${escapeHtml(getInitials(row.actor))}</div>
          <div>
            <strong>${escapeHtml(row.actor)} <span>${escapeHtml(row.tag)}</span></strong>
            <p>${escapeHtml(row.text)}</p>
            <small>${formatDateTime(row.date)}</small>
          </div>
        </div>`,
    )
    .join("");
}

function renderNextSteps(data) {
  const container = document.getElementById("altConfProximos");
  if (!container) return;
  const status = normalizarStatus(data?.image?.alteracao_status || "");
  const steps = [
    ["Executar alteracao", ["nao iniciado", "em andamento"].includes(status)],
    ["Aprovacao arquitetura", status === "em aprovacao"],
    ["Aprovacao direcao", false],
    ["Render", false],
    ["Proxima etapa do fluxo", false],
  ];
  container.innerHTML = steps
    .map(
      ([label, active]) => `
        <div class="alt-conf-step ${active ? "active" : ""}">
          <span></span><strong>${escapeHtml(label)}</strong>
        </div>`,
    )
    .join("");
}

function syncSidePanelState() {
  const modal = document.getElementById("myModal");
  const shell = document.querySelector(".alt-conference-shell");
  const panel = document.getElementById("altConfRightPanel");
  const toggle = document.getElementById("altConfSideToggle");
  if (!panel) return;

  panel.dataset.activePanel = altSidePanelActive;
  panel.querySelectorAll("[data-panel-target]").forEach((btn) => {
    btn.classList.toggle(
      "active",
      btn.dataset.panelTarget === altSidePanelActive,
    );
  });
  panel.querySelectorAll("[data-panel]").forEach((section) => {
    section.classList.toggle(
      "active",
      section.dataset.panel === altSidePanelActive,
    );
  });

  [modal, shell].forEach((el) => {
    if (el) el.classList.toggle("is-side-collapsed", altSidePanelCollapsed);
  });

  if (toggle) {
    toggle.setAttribute(
      "aria-expanded",
      altSidePanelCollapsed ? "false" : "true",
    );
    toggle.classList.toggle("is-collapsed", altSidePanelCollapsed);
    const icon = toggle.querySelector("i");
    if (icon) {
      icon.className = altSidePanelCollapsed
        ? "fa-solid fa-chevron-left"
        : "fa-solid fa-chevron-right";
    }
  }
}

function setSidePanelCollapsed(collapsed) {
  altSidePanelCollapsed = Boolean(collapsed);
  syncSidePanelState();
}

function setSidePanelActive(panelName) {
  altSidePanelActive = panelName || "files";
  altSidePanelCollapsed = false;
  syncSidePanelState();
}

function closeMoreActionsPanel() {
  const panel = document.getElementById("altConfMorePanel");
  const button = document.getElementById("altConfMoreActions");
  const shell = document.querySelector(".alt-conference-shell");
  if (panel) panel.hidden = true;
  if (button) button.setAttribute("aria-expanded", "false");
  if (shell) shell.classList.remove("is-more-open");
}

function toggleMoreActionsPanel(forceOpen = null) {
  const panel = document.getElementById("altConfMorePanel");
  const button = document.getElementById("altConfMoreActions");
  const shell = document.querySelector(".alt-conference-shell");
  if (!panel || !button) return;
  const open = forceOpen === null ? panel.hidden : Boolean(forceOpen);
  panel.hidden = !open;
  button.setAttribute("aria-expanded", open ? "true" : "false");
  if (shell) shell.classList.toggle("is-more-open", open);
}

function renderConference(data) {
  altConferenciaData = data;
  const image = data.image || {};
  const metrics = data.metrics || {};
  const latest = data.latest_version || {};
  const summary = data.pre_alteracao_summary || {};
  const files = data.files || {};

  setText("campoNomeImagem", image.imagem_nome);
  setText(
    "altConfDescricao",
    image.alteracao_observacao ||
      "Conferencia da alteracao antes da execucao ou conclusao.",
  );
  setText("altConfObra", image.nomenclatura || image.nome_obra);
  setText(
    "altConfSubtipo",
    image.subtipo_nome || image.subtipo_imagem || "Sem subtipo",
  );
  setText("altConfEtapa", image.nome_status);
  applyStatusImagemClass(
    document.getElementById("altConfEtapa"),
    image.nome_status,
  );
  setText("altConfTipo", image.tipo_imagem);
  setText("altConfStatus", image.alteracao_status);
  const complexidade = image.nivel_complexidade || summary.complexity;
  setText("altConfComplexidade", complexityLabel(complexidade));
  applyComplexityClass(
    document.getElementById("altConfComplexidade"),
    complexidade,
  );
  setText(
    "altConfPrazo",
    formatDate(image.alteracao_prazo || image.imagem_prazo),
  );
  setText("altConfResponsavel", image.nome_colaborador);
  setText(
    "altConfInicio",
    formatDateTime(image.data_recebimento || image.data_inicio),
  );
  setText("altConfAtualizacao", formatDateTime(metrics.last_update));
  setText(
    "altConfVersionTitle",
    latest.indice_envio
      ? `Versao ${latest.indice_envio}`
      : "Ultima versao disponivel",
  );
  setText(
    "altConfVersionMeta",
    latest.data_envio
      ? `Publicada em ${formatDateTime(latest.data_envio)}`
      : "-",
  );

  document.getElementById("imagem_id").value = image.imagem_id || "";
  document.getElementById("opcao_alteracao").value = image.colaborador_id || "";
  document.getElementById("status_alteracao").value =
    image.alteracao_status || "NÃ£o iniciado";
  document.getElementById("prazo_alteracao").value =
    image.alteracao_prazo || "";
  document.getElementById("obs_alteracao").value =
    image.alteracao_observacao || "";

  setCount("altNavResumoCount", summary.has_pre_alt ? 1 : 0);
  setCount("altNavComentariosCount", metrics.comments_count || 0);
  setCount("altNavArquivosCount", metrics.files_count || 0);
  setCount("altNavReferenciasCount", metrics.references_count || 0);
  setCount(
    "altNavHistoricoCount",
    (data?.history?.logs || []).length +
      (data?.history?.approvals || []).length,
  );

  renderReviewLinks(data?.links?.review_studio || "");
  renderMainImage(data);
  renderSummary(summary);
  renderSummary(summary, "altConfResumoApoio");
  renderFiles(
    "altConfArquivos",
    "altConfFileTabs",
    files.uploaded_by_origin || {},
    {
      cliente: "Cliente",
      interno: "Interno",
      triagem: "Triagem",
    },
  );
  renderFiles(
    "altConfReferencias",
    "altConfRefTabs",
    files.references_by_scope || {},
    {
      projeto: "Projeto",
      tipo: "Tipo de imagem",
      imagem: "Imagem",
    },
  );
  document.getElementById("altConfAllFiles")?.addEventListener("click", () => {
    openAltFilesModal(
      "Arquivos enviados na revisao",
      flattenFileGroups(files.uploaded_by_origin || {}, {
        cliente: "Cliente",
        interno: "Interno",
        triagem: "Triagem",
      }),
    );
  });
  document.getElementById("altConfAllRefs")?.addEventListener("click", () => {
    openAltFilesModal(
      "Referencias por escopo",
      flattenFileGroups(files.references_by_scope || {}, {
        projeto: "Projeto",
        tipo: "Tipo de imagem",
        imagem: "Imagem",
      }),
    );
  });
  renderHistory(data);
  renderCommunication(data);
  renderNextSteps(data);
  syncSidePanelState();
}

function abrirModal(idimagem) {
  buildConferenceModalShell();
  const modal = document.getElementById("myModal");
  if (modal) {
    altSidePanelCollapsed = false;
    altSidePanelActive = isCompactConferenceLayout() ? "summary" : "files";
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    closeMoreActionsPanel();
    syncSidePanelState();
  }
  atualizarModal(idimagem);
  idImagemSelecionada = idimagem;
}

function fecharModal() {
  const modal = document.getElementById("myModal");
  if (modal) {
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    closeMoreActionsPanel();
  }
}

function limparCampos() {
  document.getElementById("campoNomeImagem").textContent = "—";
  applyStatusImagemClass(document.getElementById("altConfEtapa"), "");
  applyComplexityClass(document.getElementById("altConfComplexidade"), "");
  document.getElementById("status_alteracao").value = "";
  document.getElementById("prazo_alteracao").value = "";
  document.getElementById("obs_alteracao").value = "";
  document.getElementById("opcao_alteracao").value = "";
  const stage = document.getElementById("altConfImageStage");
  if (stage) {
    stage.innerHTML = `<div class="alt-conf-image-empty"><i class="fa-regular fa-image"></i><span>Carregando...</span></div>`;
  }
}

function atualizarModal(idImagem) {
  limparCampos();

  fetch(`getConferenciaAlteracao.php?imagem_id=${encodeURIComponent(idImagem)}`)
    .then((r) => r.json())
    .then((response) => {
      if (!response.success) {
        throw new Error(response.message || "Erro ao carregar conferencia.");
      }
      renderConference(response);
      return;
      if (response.funcoes && response.funcoes.length > 0) {
        document.getElementById("campoNomeImagem").textContent =
          response.funcoes[0].imagem_nome;

        response.funcoes.forEach((funcao) => {
          if (funcao.nome_funcao === "Alteração") {
            document.getElementById("opcao_alteracao").value =
              funcao.colaborador_id || "";
            document.getElementById("status_alteracao").value =
              funcao.status || "Não iniciado";
            document.getElementById("prazo_alteracao").value =
              funcao.prazo || "";
            document.getElementById("obs_alteracao").value =
              funcao.observacao || "";
          }
        });
      }
    })
    .catch((error) => console.error("Erro ao buscar dados da linha:", error));
}

buildConferenceModalShell();

document
  .getElementById("salvar_funcoes")
  .addEventListener("click", function () {
    if (!idImagemSelecionada) {
      Toastify({
        text: "Nenhuma imagem selecionada",
        duration: 3000,
        gravity: "top",
        position: "right",
        style: {
          background: "#ef4444",
          borderRadius: "8px",
          fontFamily: '"Inter",sans-serif',
          fontSize: "13px",
        },
      }).showToast();
      return;
    }

    const dados = {
      imagem_id: idImagemSelecionada,
      funcao_id: 6,
      colaborador_id: document.getElementById("opcao_alteracao").value || "",
      status: document.getElementById("status_alteracao").value || "",
      prazo: document.getElementById("prazo_alteracao").value || "",
      observacao: document.getElementById("obs_alteracao").value || "",
    };

    $.ajax({
      type: "POST",
      url: "../insereFuncao.php",
      data: dados,
      success: function () {
        Toastify({
          text: "Dados salvos com sucesso!",
          duration: 3000,
          gravity: "top",
          position: "right",
          style: {
            background: "#10b981",
            borderRadius: "8px",
            fontFamily: '"Inter",sans-serif',
            fontSize: "13px",
          },
        }).showToast();
        fecharModal();
        recarregarAlteracao();
      },
      error: function (jqXHR, textStatus, errorThrown) {
        console.error("Erro ao salvar:", textStatus, errorThrown);
        Toastify({
          text: "Erro ao salvar dados.",
          duration: 3000,
          gravity: "top",
          position: "right",
          style: {
            background: "#ef4444",
            borderRadius: "8px",
            fontFamily: '"Inter",sans-serif',
            fontSize: "13px",
          },
        }).showToast();
      },
    });
  });

// Fechar modal pelos botões e overlay
document.getElementById("closeModal").addEventListener("click", fecharModal);
document.getElementById("closeModalBtn").addEventListener("click", fecharModal);
document.getElementById("myModal").addEventListener("click", function (e) {
  if (e.target === this) fecharModal();
});

document
  .getElementById("altConfMoreActions")
  ?.addEventListener("click", (event) => {
    event.stopPropagation();
    toggleMoreActionsPanel();
  });
document.getElementById("altConfMoreClose")?.addEventListener("click", () => {
  closeMoreActionsPanel();
});
document
  .getElementById("altConfHistoryShortcut")
  ?.addEventListener("click", () => {
    closeMoreActionsPanel();
    setSidePanelCollapsed(false);
    document
      .querySelector("#altSecHistorico")
      ?.scrollIntoView({ behavior: "smooth", block: "start" });
  });
document.getElementById("altConfSideToggle")?.addEventListener("click", () => {
  setSidePanelCollapsed(!altSidePanelCollapsed);
});
document.getElementById("altConfSideClose")?.addEventListener("click", () => {
  setSidePanelCollapsed(true);
});
document.querySelectorAll("[data-panel-target]").forEach((button) => {
  button.addEventListener("click", () => {
    setSidePanelActive(button.dataset.panelTarget || "files");
  });
});
document.addEventListener("click", (event) => {
  const panel = document.getElementById("altConfMorePanel");
  const button = document.getElementById("altConfMoreActions");
  if (
    panel &&
    !panel.hidden &&
    !panel.contains(event.target) &&
    !button?.contains(event.target)
  ) {
    closeMoreActionsPanel();
  }
});

document
  .getElementById("altConfZoomOut")
  ?.addEventListener("click", () => updateZoom(-0.1));
document
  .getElementById("altConfZoomIn")
  ?.addEventListener("click", () => updateZoom(0.1));
document.getElementById("altConfOpenImage")?.addEventListener("click", () => {
  if (altCurrentImageUrl) window.open(altCurrentImageUrl, "_blank", "noopener");
});
document
  .getElementById("altConfSendApproval")
  ?.addEventListener("click", () => {
    abrirModalEnvioAprovacao();
  });
document
  .getElementById("altApprovalUploadClose")
  ?.addEventListener("click", fecharModalEnvioAprovacao);
document
  .getElementById("altApprovalUploadModal")
  ?.addEventListener("click", (event) => {
    if (event.target === event.currentTarget) fecharModalEnvioAprovacao();
  });
document
  .getElementById("altSendPrevia")
  ?.addEventListener("click", enviarImagens);
document
  .getElementById("altSendArquivo")
  ?.addEventListener("click", enviarArquivo);
document
  .getElementById("altConfQuestionBtn")
  ?.addEventListener("click", (event) => {
    event.stopPropagation();

    const panel = document.getElementById("altConfMorePanel");

    // Abre o modal/painel
    if (panel) {
      panel.hidden = false;
    }

    // Seleciona HOLD
    const status = document.getElementById("status_alteracao");
    if (status) {
      status.value = "HOLD";
      status.dispatchEvent(new Event("change", { bubbles: true }));
    }

    // Foca no obs_alteracao depois do painel abrir
    setTimeout(() => {
      const obs = document.getElementById("obs_alteracao");
      if (obs) {
        obs.focus();
        obs.select();
      }
    }, 0);
  });

document.getElementById("altConfStart")?.addEventListener("click", (event) => {
  event.stopPropagation();

  const panel = document.getElementById("altConfMorePanel");

  if (panel) {
    panel.hidden = false;
  }

  const status = document.getElementById("status_alteracao");
  if (status) {
    status.value = "Em andamento";
    status.dispatchEvent(new Event("change", { bubbles: true }));
  }

  setTimeout(() => {
    const prazo = document.getElementById("prazo_alteracao");

    if (prazo) {
      prazo.focus();

      // Abre o seletor de data
      if (typeof prazo.showPicker === "function") {
        prazo.showPicker();
      } else {
        prazo.click(); // fallback
      }
    }
  }, 50);
});
document.querySelectorAll(".alt-conf-nav a").forEach((link) => {
  link.addEventListener("click", (event) => {
    event.preventDefault();
    document
      .querySelectorAll(".alt-conf-nav a")
      .forEach((item) => item.classList.remove("active"));
    link.classList.add("active");
    const href = link.getAttribute("href");
    if (href === "#altSecResumo" && isCompactConferenceLayout()) {
      setSidePanelActive("summary");
      document
        .querySelector("#altConfRightPanel")
        ?.scrollIntoView({ behavior: "smooth", block: "nearest" });
      return;
    }
    if (href === "#altSecArquivos") setSidePanelActive("files");
    if (href === "#altSecReferencias") setSidePanelActive("refs");
    document
      .querySelector(href)
      ?.scrollIntoView({ behavior: "smooth", block: "start" });
  });
});
document.querySelectorAll(".alt-conf-btn-review").forEach((link) => {
  link.addEventListener("click", (event) => {
    if (link.classList.contains("is-disabled")) event.preventDefault();
  });
});

// ─── Filtros ───
document
  .getElementById("btn-aplicar-filtros")
  .addEventListener("click", recarregarAlteracao);
document.getElementById("btn-limpar-filtros").addEventListener("click", () => {
  document.getElementById("filtro-status").value = "";
  document.getElementById("filtro-status-imagem").value = "";
  document.getElementById("filtro-obra").value = "";
  document.getElementById("filtro-colaborador").value = "";
  document.getElementById("filtro-busca").value = "";
  recarregarAlteracao();
});

[
  "filtro-status",
  "filtro-status-imagem",
  "filtro-obra",
  "filtro-colaborador",
].forEach((id) => {
  document.getElementById(id).addEventListener("change", recarregarAlteracao);
});

document.getElementById("filtro-busca").addEventListener("input", () => {
  clearTimeout(_filterDebounceTimer);
  _filterDebounceTimer = setTimeout(recarregarAlteracao, 350);
});

// ─── Compact toggle (all cards) ───
const btnToggleCompact = document.getElementById("btn-toggle-compact");
if (btnToggleCompact) {
  btnToggleCompact.addEventListener("click", () => {
    cardsCompactos = !cardsCompactos;
    document.querySelectorAll(".imagem-card").forEach((card) => {
      card.classList.toggle("card-compact", cardsCompactos);
    });
    btnToggleCompact.classList.toggle("ativo", cardsCompactos);
    btnToggleCompact.innerHTML = cardsCompactos
      ? '<i class="fa-solid fa-expand"></i> Expandir'
      : '<i class="fa-solid fa-compress"></i> Compactar';
  });
}

// ESC fecha modal
window.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    if (!document.getElementById("altConfMorePanel")?.hidden) {
      closeMoreActionsPanel();
      return;
    }
    fecharModal();
  }
});

recarregarAlteracao();
