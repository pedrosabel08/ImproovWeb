const frKpiConfig = window.FR_KPI_CONFIG || {};
const frKpiPermissions = { ...(frKpiConfig.permissions || {}) };
const FR_KPI_ENDPOINT_SCOPE = frKpiConfig.endpointScope || "management";

function syncFrKpiPermissions(permissions) {
  if (
    !permissions ||
    typeof permissions !== "object" ||
    Array.isArray(permissions)
  ) {
    return;
  }

  Object.assign(frKpiPermissions, permissions);
}

function canViewFrKpiScope(scope) {
  if (!scope || scope === "public") {
    return true;
  }

  return Boolean(frKpiPermissions[scope]);
}

function getTaskTipo(tarefa) {
  return (
    tarefa?.tipo_tarefa || (tarefa?.funcao_animacao_id ? "animacao" : "imagem")
  );
}

function getTaskRefId(tarefa) {
  return (
    tarefa?.funcao_ref_id ||
    tarefa?.idfuncao_imagem ||
    tarefa?.funcao_animacao_id
  );
}

function isVideoMedia(media) {
  const tipo = String(media?.media_tipo || "").toLowerCase();
  const mime = String(media?.mime_type || "").toLowerCase();
  const path = String(media?.imagem || "");
  return (
    tipo === "video" ||
    mime.startsWith("video/") ||
    /\.(mp4|webm|mov|m4v)(\?|#|$)/i.test(path)
  );
}

function formatVideoTime(ms) {
  const totalSeconds = Math.max(0, Math.floor((Number(ms) || 0) / 1000));
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return `${minutes}:${String(seconds).padStart(2, "0")}`;
}

document.addEventListener("DOMContentLoaded", function () {
  const params = new URLSearchParams(window.location.search);
  const obraNome = params.get("obra_nome");

  if (obraNome) {
    // Primeiro carrega as tarefas
    fetchObrasETarefas().then(() => {
      // Depois filtra pela obra
      filtrarTarefasPorObra(obraNome);

      // Deep link from PaginaPrincipal kanban: auto-select specific function
      const frGotoRaw = localStorage.getItem("fr_goto");
      if (frGotoRaw) {
        try {
          const frGoto = JSON.parse(frGotoRaw);
          localStorage.removeItem("fr_goto");
          if (
            frGoto.idfuncao_imagem &&
            (!frGoto.nome_obra || frGoto.nome_obra === obraNome)
          ) {
            setTimeout(() => {
              historyAJAX(frGoto.idfuncao_imagem);
              if (window._stabSetActive)
                window._stabSetActive(frGoto.idfuncao_imagem);
            }, 400);
          }
        } catch (e) {
          console.error("fr_goto parse error:", e);
        }
      }
    });

    // support pointer events (touch / pen) for PDF drawing
    const pageLayer = document.querySelector(".pdf-page-layer");
    if (pageLayer) {
      pageLayer.addEventListener("pointerdown", function (event) {
        if (event.pointerType === "mouse" && event.button !== 0) return;
        if (event.ctrlKey) return;
        if (drawingTool === "ponto") return;
        if (!pdfViewerState.logId) return;
        event.stopPropagation();
        isDrawing = true;
        dragMoved = false;
        const rect = canvas.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        drawStartX = ((event.clientX - rect.left) / rect.width) * 100;
        drawStartY = ((event.clientY - rect.top) / rect.height) * 100;
        drawStartClientX = event.clientX;
        drawStartClientY = event.clientY;
        shapeX2 = drawStartX;
        shapeY2 = drawStartY;
        currentDrawRef = canvas;
        const container =
          document.getElementById("pdf_comment_layer") || pageLayer;
        if (drawingTool === "freehand") {
          freehandPoints = [[drawStartX, drawStartY]];
          const svg = createFreehandPreviewSvg(drawStartX, drawStartY);
          container.appendChild(svg);
          freehandSvgPreview = svg;
          freehandPolylineEl = svg.querySelector("polyline");
          freehandDrawContainer = container;
        } else {
          const preview = document.createElement("div");
          preview.id = "drawing-preview";
          preview.className = `drawing-preview drawing-preview-${drawingTool}`;
          preview.style.left = `${drawStartX}%`;
          preview.style.top = `${drawStartY}%`;
          preview.style.width = "0";
          preview.style.height = "0";
          container.appendChild(preview);
        }
      });
    }
  } else {
    fetchObrasETarefas();
  }
  // carrega painel de KPIs (acima dos cards de obra)
  loadKpis(null);
  initProcessHistory();
});

async function revisarTarefa(
  idfuncao_imagem,
  nome_colaborador,
  imagem_nome,
  nome_funcao,
  colaborador_id,
  imagem_id,
  tipoRevisao,
  tipo_tarefa = "imagem",
  funcao_animacao_id = null,
) {
  event.stopPropagation();

  const idcolaborador = localStorage.getItem("idcolaborador");

  let actionText = "";
  switch (tipoRevisao) {
    case "aprovado":
      actionText = "aprovar esta tarefa";
      break;
    case "ajuste":
      actionText = "marcar esta tarefa como necessitando de ajustes";
      break;
    case "aprovado_com_ajustes":
      actionText = "aprovar com ajustes";
      break;
  }

  const { isConfirmed } = await Swal.fire({
    title: "Confirmar ação",
    text: `Você tem certeza de que deseja ${actionText}?`,
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sim, confirmar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#2ecc71",
  });

  if (!isConfirmed) return;

  // ── Etapas de progresso exibidas ao usuário ─────────────────────────────
  const etapas = [
    {
      titulo: "Salvando revisão…",
      detalhe: "Atualizando status no banco de dados.",
    },
    {
      titulo: "Verificando arquivo…",
      detalhe: "Buscando arquivo de upload no servidor.",
    },
    {
      titulo: "Enviando para o servidor…",
      detalhe: "Transferindo arquivo via SFTP. Pode levar alguns instantes.",
    },
    {
      titulo: "Notificando colaboradores…",
      detalhe: "Enviando mensagem no Slack.",
    },
    { titulo: "Finalizando…", detalhe: "Quase pronto!" },
  ];
  let etapaIdx = 0;

  Swal.fire({
    title: etapas[0].titulo,
    html: `<p style="margin:0;color:#555">${etapas[0].detalhe}</p>
           <div id="fr-progress-bar" style="margin-top:14px;height:6px;border-radius:3px;background:#eee;overflow:hidden">
             <div id="fr-progress-fill" style="height:100%;width:0%;background:#2ecc71;transition:width 2.8s ease"></div>
           </div>`,
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
      // Arranca a barra no próximo tick para a transição CSS funcionar
      requestAnimationFrame(() => {
        const fill = document.getElementById("fr-progress-fill");
        if (fill) fill.style.width = "20%";
      });
    },
  });

  const avancarEtapa = setInterval(() => {
    etapaIdx = Math.min(etapaIdx + 1, etapas.length - 1);
    const pct = Math.round(((etapaIdx + 1) / etapas.length) * 85); // vai até 85% enquanto aguarda
    Swal.update({
      title: etapas[etapaIdx].titulo,
      html: `<p style="margin:0;color:#555">${etapas[etapaIdx].detalhe}</p>
             <div id="fr-progress-bar" style="margin-top:14px;height:6px;border-radius:3px;background:#eee;overflow:hidden">
               <div id="fr-progress-fill" style="height:100%;width:${pct}%;background:#2ecc71;transition:width 2.8s ease"></div>
             </div>`,
    });
  }, 3000);

  try {
    const response = await fetch("revisarTarefa.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        idfuncao_imagem,
        nome_colaborador,
        imagem_nome,
        nome_funcao,
        colaborador_id,
        responsavel: idcolaborador,
        imagem_id,
        tipoRevisao,
        tipo_tarefa,
        funcao_animacao_id,
        historico_id: ap_imagem_id ?? null,
      }),
    });

    clearInterval(avancarEtapa);

    if (!response.ok) throw new Error("Erro ao atualizar a tarefa.");
    const data = await response.json();
    // console.log("Resposta do servidor:", data);

    // Barra a 100% antes de fechar
    Swal.update({
      title: data.success ? "Concluído!" : "Ocorreu um erro",
      html: `<p style="margin:0;color:#555">${data.success ? "Revisão registrada com sucesso." : data.message || "Falha ao atualizar a tarefa."}</p>
             <div style="margin-top:14px;height:6px;border-radius:3px;background:#eee;overflow:hidden">
               <div style="height:100%;width:100%;background:${data.success ? "#2ecc71" : "#e74c3c"};transition:width 0.4s ease"></div>
             </div>`,
    });
    await new Promise((r) => setTimeout(r, 700));
    Swal.close();

    let message = "";
    let bgColor = "";
    if (data?.aguardando_direcao) {
      message = data.message || "Aprovacao registrada. Aguardando Direcao.";
      bgColor = "blue";
    } else {
      switch (tipoRevisao) {
        case "aprovado":
          message = "Tarefa aprovada com sucesso!";
          bgColor = "green";
          break;
        case "ajuste":
          message = "Tarefa marcada como necessitando de ajustes!";
          bgColor = "orange";
          break;
        case "aprovado_com_ajustes":
          message = "Tarefa aprovada com ajustes!";
          bgColor = "blue";
          break;
      }
    }

    Toastify({
      text: data.success
        ? message
        : "Falha ao atualizar a tarefa: " + data.message,
      duration: 3000,
      backgroundColor: data.success ? bgColor : "red",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();

    if (data.success) {
      const obraSelecionada = document.getElementById("filtro_obra").value;

      const statusMap = {
        aprovado: "Aprovado",
        ajuste: "Ajuste",
        aprovado_com_ajustes: "Aprovado com ajustes",
      };
      const novoStatus = data.aguardando_direcao
        ? data.status_aprovacao || statusMap[tipoRevisao]
        : statusMap[tipoRevisao];
      if (novoStatus) {
        const task = dadosTarefas.find(
          (t) => t.idfuncao_imagem == idfuncao_imagem,
        );
        if (task) {
          task.status_novo = novoStatus;
          if (data.aguardando_direcao) {
            task.status = "Aguardando Direção";
            task.pendente_direcao = true;
            task.diretor_pode_aprovar = false;
            delete task.finalizador_pode_aprovar;
          }
        }
      }

      filtrarTarefasPorObra(obraSelecionada);

      // ── Conflito SFTP: arquivo já existe no servidor ──────────────────
      if (data.sftp_conflict) {
        resolverConflitoSftp(
          data.sftp_nome_arquivo,
          idfuncao_imagem,
          imagem_id,
          data.sftp_remote_path ?? null,
          data.sftp_caminho_local ?? null,
        );
      }
    }
  } catch (error) {
    clearInterval(avancarEtapa);
    Swal.close();
    console.error("Erro:", error);
    Toastify({
      text: "Ocorreu um erro ao processar a solicitação. " + error.message,
      duration: 3000,
      backgroundColor: "red",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();
  }
}

/**
 * Exibe um diálogo SweetAlert quando o arquivo SFTP já existe no servidor,
 * permitindo ao usuário substituir ou adicionar com um nome diferente.
 *
 * @param {string} nomeArquivo      – Nome limpo do arquivo (sem índice)
 * @param {number} idfuncao_imagem  – ID da função de imagem aprovada
 * @param {number} imagem_id        – ID da imagem
 */
async function resolverConflitoSftp(
  nomeArquivo,
  idfuncao_imagem,
  imagem_id,
  sftp_remote_path = null,
  sftp_caminho_local = null,
) {
  const { isConfirmed: confirmedReplace, isDenied: confirmedAdd } =
    await Swal.fire({
      title: "Arquivo já existe no servidor",
      html: `O arquivo <strong>${nomeArquivo}</strong> já existe no destino.<br>Deseja substituí-lo ou enviá-lo com outro nome?`,
      icon: "warning",
      showCancelButton: true,
      cancelButtonText: "Cancelar",
      confirmButtonText: "Substituir",
      showDenyButton: true,
      denyButtonText: "Adicionar",
      confirmButtonColor: "#c0392b",
      denyButtonColor: "#2980b9",
      reverseButtons: true,
    });

  if (!confirmedReplace && !confirmedAdd) return; // cancelado

  let sftp_action = null;
  let sftp_suffix = null;

  if (confirmedReplace) {
    sftp_action = "replace";
  } else if (confirmedAdd) {
    const baseSemExt = nomeArquivo.replace(/(\.[^.]+)$/, "");
    const ext = nomeArquivo.match(/(\.[^.]+)$/)?.[1] ?? "";
    const { value: sufixo, isConfirmed } = await Swal.fire({
      title: "Novo sufixo para o arquivo",
      html: `O arquivo será salvo como <code>${baseSemExt}_<em>SUFIXO</em>${ext}</code>`,
      input: "text",
      inputLabel: "Ex: Normal, Completa, Cropada",
      inputPlaceholder: "Digite o sufixo desejado",
      showCancelButton: true,
      cancelButtonText: "Cancelar",
      confirmButtonText: "Confirmar",
      inputValidator: (v) =>
        !v.trim() ? "Por favor, informe um sufixo." : null,
    });

    if (!isConfirmed || !sufixo) return;
    sftp_action = "add";
    sftp_suffix = sufixo.trim();
  }

  // ── Progresso do upload SFTP ─────────────────────────────────────────────
  const etapasSftp = [
    {
      titulo: "Conectando ao servidor…",
      detalhe: "Estabelecendo conexão SFTP.",
    },
    {
      titulo: "Baixando arquivo do VPS…",
      detalhe: "Buscando o arquivo de origem. Pode levar alguns segundos.",
    },
    {
      titulo: "Enviando para o destino…",
      detalhe: "Transferindo para o servidor final.",
    },
    { titulo: "Finalizando envio…", detalhe: "Quase pronto!" },
  ];
  let sftpIdx = 0;

  Swal.fire({
    title: etapasSftp[0].titulo,
    html: `<p style="margin:0;color:#555">${etapasSftp[0].detalhe}</p>
           <div style="margin-top:14px;height:6px;border-radius:3px;background:#eee;overflow:hidden">
             <div id="sftp-progress-fill" style="height:100%;width:0%;background:#2980b9;transition:width 2.8s ease"></div>
           </div>`,
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
      requestAnimationFrame(() => {
        const fill = document.getElementById("sftp-progress-fill");
        if (fill) fill.style.width = "20%";
      });
    },
  });

  const avancarSftp = setInterval(() => {
    sftpIdx = Math.min(sftpIdx + 1, etapasSftp.length - 1);
    const pct = Math.round(((sftpIdx + 1) / etapasSftp.length) * 85);
    Swal.update({
      title: etapasSftp[sftpIdx].titulo,
      html: `<p style="margin:0;color:#555">${etapasSftp[sftpIdx].detalhe}</p>
             <div style="margin-top:14px;height:6px;border-radius:3px;background:#eee;overflow:hidden">
               <div id="sftp-progress-fill" style="height:100%;width:${pct}%;background:#2980b9;transition:width 2.8s ease"></div>
             </div>`,
    });
  }, 3500);

  try {
    const res = await fetch("sftp_upload.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        idfuncao_imagem,
        imagem_id,
        sftp_action,
        sftp_suffix,
        sftp_remote_path,
        sftp_caminho_local,
        nome_arquivo: nomeArquivo,
      }),
    });
    const result = await res.json();

    clearInterval(avancarSftp);

    Swal.update({
      title: result.success ? "Arquivo enviado!" : "Falha no envio",
      html: `<p style="margin:0;color:#555">${result.success ? "Arquivo transferido com sucesso." : result.message || "Falha desconhecida."}</p>
             <div style="margin-top:14px;height:6px;border-radius:3px;background:#eee;overflow:hidden">
               <div style="height:100%;width:100%;background:${result.success ? "#2ecc71" : "#e74c3c"};transition:width 0.4s ease"></div>
             </div>`,
    });
    await new Promise((r) => setTimeout(r, 700));
    Swal.close();

    Toastify({
      text: result.success
        ? "Arquivo enviado ao servidor com sucesso!"
        : "Erro ao enviar arquivo: " + (result.message || "falha desconhecida"),
      duration: 4000,
      backgroundColor: result.success ? "green" : "red",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();
  } catch (e) {
    clearInterval(avancarSftp);
    Swal.close();
    console.error("Erro ao resolver conflito SFTP:", e);
    Toastify({
      text: "Erro ao enviar arquivo ao servidor.",
      duration: 3000,
      backgroundColor: "red",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();
  }
}

// Função para alternar a visibilidade dos detalhes da tarefa
function toggleTaskDetails(taskElement) {
  taskElement.classList.toggle("open");
}

let dadosTarefas = [];
let todasAsObras = new Set();
let todosOsColaboradores = new Set();
let todasAsFuncoes = new Set();
let funcaoGlobalSelecionada = null;
let colaboradorGlobalSelecionado = null;
let statusGlobalSelecionado = null;

async function fetchObrasETarefas() {
  try {
    const response = await fetch(`atualizar.php`);
    if (!response.ok) throw new Error("Erro ao buscar tarefas");

    const responseData = await response.json();
    dadosTarefas = responseData.tarefas ?? responseData;

    // Calcula offset entre horário do servidor (Brasília) e relógio local
    if (responseData.server_now) {
      const serverDate = new Date(
        responseData.server_now.replace(" ", "T") + "-03:00",
      );
      _serverTimeOffset = serverDate.getTime() - Date.now();
    }

    todasAsObras = new Set(dadosTarefas.map((t) => t.nomenclatura));
    todosOsColaboradores = new Set(dadosTarefas.map((t) => t.nome_colaborador));
    todasAsFuncoes = new Set(dadosTarefas.map((t) => t.nome_funcao)); // ou o nome do campo correspondente

    exibirCardsDeObra(dadosTarefas); // Mostra os cards
    loadKpis(null); // Atualiza KPI bar (visão geral)

    // ── Sidebar: mostrar seção de obras, ocultar tarefas ──
    const secObrasEl = document.getElementById("fr-section-obras");
    const secTarefasEl = document.getElementById("fr-section-tarefas");
    if (secObrasEl) secObrasEl.classList.remove("hidden");
    if (secTarefasEl) secTarefasEl.classList.add("hidden");

    // Populate função filter na sidebar home
    const frFuncaoHome = document.getElementById("fr-funcao-home");
    if (frFuncaoHome) {
      frFuncaoHome.innerHTML = '<option value="">Todas</option>';
      [...todasAsFuncoes].sort().forEach((funcao) => {
        const option = document.createElement("option");
        option.value = funcao;
        option.textContent = funcao;
        frFuncaoHome.appendChild(option);
      });
    }

    // Populate colaborador filter na sidebar home
    const frColabHome = document.getElementById("fr-colaborador-home");
    if (frColabHome) {
      frColabHome.innerHTML = '<option value="">Todos</option>';
      [...todosOsColaboradores].sort().forEach((colab) => {
        const option = document.createElement("option");
        option.value = colab;
        option.textContent = colab;
        frColabHome.appendChild(option);
      });
    }

    // Populate status filter na sidebar home
    const todosOsStatus = new Set(dadosTarefas.map((t) => t.status));
    const frStatusHome = document.getElementById("fr-status-home");
    if (frStatusHome) {
      frStatusHome.innerHTML = '<option value="">Todos</option>';
      [...todosOsStatus].sort().forEach((status) => {
        const option = document.createElement("option");
        option.value = status;
        option.textContent = status;
        frStatusHome.appendChild(option);
      });
    }

    // Attach sidebar filter listeners only once
    const searchInput = document.getElementById("fr-search-obra");
    if (searchInput && !searchInput._frListenerAdded) {
      searchInput._frListenerAdded = true;
      searchInput.addEventListener("input", applyHomeFilters);
      if (frFuncaoHome) {
        frFuncaoHome.addEventListener("change", () => {
          funcaoGlobalSelecionada = frFuncaoHome.value || null;
          applyHomeFilters();
        });
      }
      if (frColabHome) {
        frColabHome.addEventListener("change", () => {
          colaboradorGlobalSelecionado = frColabHome.value || null;
          applyHomeFilters();
        });
      }
      if (frStatusHome) {
        frStatusHome.addEventListener("change", () => {
          statusGlobalSelecionado = frStatusHome.value || null;
          applyHomeFilters();
        });
      }
    }
  } catch (error) {
    console.error(error);
  }
}

// Filtra os cards de obra com base nos filtros da sidebar home
function applyHomeFilters() {
  const searchVal = (document.getElementById("fr-search-obra")?.value || "")
    .toLowerCase()
    .trim();
  const funcaoVal = document.getElementById("fr-funcao-home")?.value || "";
  const colabVal = document.getElementById("fr-colaborador-home")?.value || "";
  const statusVal = document.getElementById("fr-status-home")?.value || "";

  let filtradas = dadosTarefas;
  if (funcaoVal)
    filtradas = filtradas.filter((t) => t.nome_funcao === funcaoVal);
  if (colabVal)
    filtradas = filtradas.filter((t) => t.nome_colaborador === colabVal);
  if (statusVal) filtradas = filtradas.filter((t) => t.status === statusVal);
  if (searchVal)
    filtradas = filtradas.filter((t) =>
      (t.nomenclatura || "").toLowerCase().includes(searchVal),
    );

  exibirCardsDeObra(filtradas);
}

// ── KPI Bar module ────────────────────────────────────────────────────────────
// Module-level state
let _frKpiPopover = null;
let _frKpiOpenKey = null;

function frFormatHoras(h) {
  if (!h || h < 1) return "<1h";
  if (h < 24) return Math.round(h) + "h";
  const d = Math.floor(h / 24);
  const rem = Math.round(h - d * 24);
  return d + "d" + (rem > 0 ? " " + rem + "h" : "");
}

async function loadKpis(obraId = null) {
  const bar = document.getElementById("fr-kpi-bar");
  if (!bar) return;

  if (!canViewFrKpiScope(FR_KPI_ENDPOINT_SCOPE)) {
    closeFrKpiPopover();
    bar.innerHTML = "";
    bar.classList.add("hidden");
    return;
  }

  // Show skeleton while loading
  bar.innerHTML =
    '<div class="fr-kpi-skeleton"></div><div class="fr-kpi-skeleton"></div><div class="fr-kpi-skeleton"></div>';
  bar.classList.remove("hidden");

  try {
    const url = obraId
      ? `getKpis.php?obra_id=${encodeURIComponent(obraId)}`
      : "getKpis.php";
    const res = await fetch(url);
    if (!res.ok) throw new Error("Erro ao buscar KPIs");
    const data = await res.json();
    syncFrKpiPermissions(data.permissions);
    renderFrKpiBar(data.kpis || []);
  } catch (err) {
    console.error("Erro ao carregar KPIs:", err);
    bar.innerHTML = "";
    bar.classList.add("hidden");
  }
}

function renderFrKpiBar(kpis) {
  const bar = document.getElementById("fr-kpi-bar");
  if (!bar) return;
  if (!kpis.length) {
    closeFrKpiPopover();
    bar.innerHTML = "";
    bar.classList.add("hidden");
    return;
  }

  bar.classList.remove("hidden");

  bar.innerHTML = kpis
    .map((kpi) => {
      const hasDetail = (kpi.detail || []).length > 0;
      const trendIcon =
        kpi.trend_dir === "down"
          ? "fa-arrow-down"
          : kpi.trend_dir === "up"
            ? "fa-arrow-up"
            : "fa-minus";
      return `<article class="fr-kpi-card${hasDetail ? " fr-kpi-card--clickable" : ""}"
          data-tone="${escapeHtml(kpi.tone || "info")}"
          data-kpi-key="${escapeHtml(kpi.key || "")}">
        <div class="kpi-head">
          <p class="kpi-label">${escapeHtml(kpi.label)}</p>
          <span class="kpi-icon"><i class="${escapeHtml(kpi.icon || "fa-solid fa-chart-bar")}"></i></span>
        </div>
        <div class="kpi-value">${escapeHtml(kpi.value_fmt || String(kpi.value))}</div>
        <div class="kpi-meta">
          <span class="kpi-trend kpi-trend--${kpi.trend_dir || "neutral"}">
            <i class="fa-solid ${trendIcon}"></i>${escapeHtml(kpi.trend || "")}
          </span>
        </div>
        ${hasDetail ? `<span class="fr-kpi-expand-hint"><i class="fa-solid fa-chevron-down"></i></span>` : ""}
      </article>`;
    })
    .join("");

  // Wire click handlers for clickable cards
  bar.querySelectorAll(".fr-kpi-card--clickable").forEach((card) => {
    const key = card.dataset.kpiKey;
    const kpiData = kpis.find((k) => k.key === key);
    card.addEventListener("click", (e) => {
      e.stopPropagation();
      if (_frKpiOpenKey === key && _frKpiPopover && !_frKpiPopover.hidden) {
        closeFrKpiPopover();
      } else {
        openFrKpiPopover(kpiData, card);
      }
    });
  });
}

function openFrKpiPopover(kpi, cardEl) {
  if (!_frKpiPopover) {
    _frKpiPopover = document.createElement("div");
    _frKpiPopover.id = "fr-kpi-popover";
    _frKpiPopover.className = "fr-kpi-popover";
    _frKpiPopover.hidden = true;
    document.body.appendChild(_frKpiPopover);
  }

  _frKpiOpenKey = kpi.key;
  const detail = kpi.detail || [];

  let bodyHtml = "";
  if (!detail.length) {
    bodyHtml = '<p class="wpop-empty">Sem dados no período</p>';
  } else if (kpi.key === "em_aprovacao") {
    bodyHtml = detail
      .map(
        (fn) => `<div class="wpop-fn-group">
        <div class="wpop-fn-header">
          <span>${escapeHtml(fn.function)}</span>
          <span class="wpop-fn-count">${fn.value}</span>
        </div>
      </div>`,
      )
      .join("");
  } else if (kpi.key === "pct_ajustes") {
    bodyHtml = detail
      .map(
        (fn) => `<div class="wpop-fn-group">
        <div class="wpop-fn-header">
          <span>${escapeHtml(fn.function)}</span>
          <span class="wpop-fn-count">${fn.value}%</span>
        </div>
        ${fn.raw_label ? `<div class="wpop-item">${escapeHtml(fn.raw_label)} item(s) com ajuste</div>` : ""}
      </div>`,
      )
      .join("");
  } else if (kpi.key === "mediana_aprovacao") {
    bodyHtml = detail
      .map(
        (fn) => `<div class="wpop-fn-group">
        <div class="wpop-fn-header">
          <span>${escapeHtml(fn.function)}</span>
          <span class="wpop-fn-count">${frFormatHoras(fn.value)}</span>
        </div>
      </div>`,
      )
      .join("");
  }

  _frKpiPopover.innerHTML = `
    <div class="wpop-header">
      <span class="wpop-title">${escapeHtml(kpi.label)}</span>
      <button class="wpop-close" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="wpop-body">
      <h4 class="wpop-section-title">
        <i class="${escapeHtml(kpi.icon || "")}"></i> Por função
        <span class="wpop-count">${escapeHtml(kpi.value_fmt || String(kpi.value))}</span>
      </h4>
      ${bodyHtml}
    </div>`;

  _frKpiPopover
    .querySelector(".wpop-close")
    ?.addEventListener("click", closeFrKpiPopover);

  positionFrKpiPopover(cardEl);
  _frKpiPopover.hidden = false;
}

function positionFrKpiPopover(cardEl) {
  const rect = cardEl.getBoundingClientRect();
  const vpW = window.innerWidth;
  const vpH = window.innerHeight;
  const pw = 300;
  const pmh = Math.min(460, vpH - 24);
  let top = rect.bottom + 8;
  let left = rect.left;
  if (left + pw > vpW - 8) left = rect.right - pw;
  if (left < 8) left = 8;
  if (top + pmh > vpH - 8) top = rect.top - pmh - 8;
  if (top < 8) top = 8;
  _frKpiPopover.style.top = `${top}px`;
  _frKpiPopover.style.left = `${left}px`;
  _frKpiPopover.style.width = `${pw}px`;
  _frKpiPopover.style.maxHeight = `${pmh}px`;
}

function closeFrKpiPopover() {
  if (_frKpiPopover) _frKpiPopover.hidden = true;
  _frKpiOpenKey = null;
}

// Close popover when clicking outside
document.addEventListener("click", (e) => {
  if (!_frKpiPopover || _frKpiPopover.hidden) return;
  if (
    !_frKpiPopover.contains(e.target) &&
    !e.target.closest(".fr-kpi-card--clickable")
  ) {
    closeFrKpiPopover();
  }
});

async function buscarMencoesDoUsuario() {
  const response = await fetch("buscar_mencoes.php");
  const data = await response.json();
  _mencoesDados = data;
  return data;
}

async function exibirCardsDeObra(tarefas) {
  const mencoes = await buscarMencoesDoUsuario();

  // if (mencoes.total_mencoes > 0) {
  //     Swal.fire({
  //         title: '📣 Você foi mencionado!',
  //         text: `Há ${mencoes.total_mencoes} menção(ões) nas tarefas.`,
  //         icon: 'info',
  //         confirmButtonText: 'Ver cards'
  //     });
  // }

  const container = document.querySelector(".containerObra");
  container.innerHTML = "";

  if (!Array.isArray(tarefas) || tarefas.length === 0) {
    container.innerHTML =
      '<p style="text-align: center; color: #888; margin-top: 24px;">Não há tarefas de revisão no momento.</p>';
    return;
  }

  const obrasMap = new Map();
  tarefas.forEach((tarefa) => {
    if (!obrasMap.has(tarefa.nomenclatura)) {
      obrasMap.set(tarefa.nomenclatura, []);
    }
    obrasMap.get(tarefa.nomenclatura).push(tarefa);
  });

  // Obras com prioridade primeiro, depois menções
  const obrasOrdenadas = [...obrasMap.entries()].sort(
    ([a, tarefasA], [b, tarefasB]) => {
      const prioA = tarefasA.filter(
        (t) => t.prioridade_aprovacao == 1 && t.status_novo === "Em aprovação",
      ).length;
      const prioB = tarefasB.filter(
        (t) => t.prioridade_aprovacao == 1 && t.status_novo === "Em aprovação",
      ).length;
      if (prioB !== prioA) return prioB - prioA;
      return (
        (mencoes.mencoes_por_obra[b] || 0) - (mencoes.mencoes_por_obra[a] || 0)
      );
    },
  );

  if (mencoes.total_mencoes > 0) {
    const linhas = Object.entries(mencoes.mencoes_por_obra || {})
      .filter(([, q]) => q > 0)
      .map(([obra, qtd]) => `• <b>${obra}</b>: ${qtd} menção(ões)`)
      .join("<br>");
    Swal.fire({
      title: "📣 Você foi mencionado!",
      html: linhas + "<br><br>Confira as obras destacadas!",
      icon: "info",
      confirmButtonText: "Ver",
    });
  }

  const tarefasDirecao = tarefas.filter(
    (t) => t.pendente_direcao && t.diretor_pode_aprovar,
  );
  if (tarefasDirecao.length > 0) {
    const obrasDirMap = {};
    tarefasDirecao.forEach((t) => {
      obrasDirMap[t.nomenclatura] = (obrasDirMap[t.nomenclatura] || 0) + 1;
    });
    const linhasDir = Object.entries(obrasDirMap)
      .map(([obra, qtd]) => `• <b>${obra}</b>: ${qtd} tarefa(s)`)
      .join("<br>");
    Swal.fire({
      title: "⏳ Aguardando sua validação!",
      html:
        linhasDir +
        "<br><br>Finalizadores ou arquitetura aprovaram — aguardando confirmação da direção.",
      icon: "warning",
      confirmButtonText: "Ver",
    });
  }

  // SweetAlert de prioridade de aprovação (só dispara uma vez por carregamento)
  const tarefasPrio = tarefas.filter(
    (t) => t.prioridade_aprovacao == 1 && t.status_novo === "Em aprovação",
  );
  if (tarefasPrio.length > 0 && !_prioAlertShown) {
    _prioAlertShown = true;
    const obrasPrioMap = {};
    tarefasPrio.forEach((t) => {
      obrasPrioMap[t.nomenclatura] = (obrasPrioMap[t.nomenclatura] || 0) + 1;
    });
    const linhasPrio = Object.entries(obrasPrioMap)
      .map(([obra, qtd]) => `• <b>${obra}</b>: ${qtd} tarefa(s)`)
      .join("<br>");
    Swal.fire({
      title: "🔥 Aprovações com prioridade!",
      html:
        linhasPrio + "<br><br>Estas tarefas estão marcadas como prioridade.",
      icon: "warning",
      confirmButtonText: "Ver",
      confirmButtonColor: "#e85e00",
    });
  }

  obrasOrdenadas.forEach(([nomenclatura, tarefasDaObra]) => {
    tarefasDaObra.sort(
      (a, b) => new Date(b.data_aprovacao) - new Date(a.data_aprovacao),
    );
    const tarefaComImagem = tarefasDaObra.find((t) => t.imagem);
    // Use thumbnail for obra preview to reduce load
    const imagemPreview = tarefaComImagem
      ? `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(tarefaComImagem.imagem)}&w=450&q=85`
      : "../assets/logo.jpg";

    const mencoesNaObra = mencoes.mencoes_por_obra[nomenclatura] || 0;
    const pendenteDirecaoNaObra = tarefasDaObra.filter(
      (t) => t.pendente_direcao && t.diretor_pode_aprovar,
    ).length;
    const prioridadeNaObra = tarefasDaObra.filter(
      (t) => t.prioridade_aprovacao == 1 && t.status_novo === "Em aprovação",
    ).length;
    const obraTone =
      prioridadeNaObra > 0
        ? "priority"
        : pendenteDirecaoNaObra > 0
          ? "direction"
          : mencoesNaObra > 0
            ? "mention"
            : null;

    const card = document.createElement("div");
    card.classList.add("obra-card");
    if (obraTone) {
      card.dataset.tone = obraTone;
    }

    card.innerHTML = `
        ${mencoesNaObra > 0 ? `<div class="mencao-badge">💬 ${mencoesNaObra}</div>` : ""}
        ${pendenteDirecaoNaObra > 0 ? `<div class="pendente-direcao-badge obra-direcao-badge" title="Aguardando validação da direção">⏳ ${pendenteDirecaoNaObra}</div>` : ""}
        ${prioridadeNaObra > 0 ? `<div class="prioridade-badge" title="Aprovações com prioridade">🔥 ${prioridadeNaObra}</div>` : ""}
        <div class="obra-img-preview">
            <img src="${imagemPreview}" alt="Imagem da obra ${nomenclatura}">
        </div>
        <div class="obra-info">
            <h3>${tarefasDaObra[0].nomenclatura}</h3>
            <p>${tarefasDaObra.length} aprovações</p>
        </div>
    `;

    card.addEventListener("click", () => {
      filtrarTarefasPorObra(nomenclatura);
    });

    container.appendChild(card);
  });
}

function filtrarTarefasPorObra(obraSelecionada) {
  document.getElementById("filtro_obra").value = obraSelecionada;

  // ── Sidebar: mostrar seção de tarefas, ocultar obras ──
  const secObras = document.getElementById("fr-section-obras");
  const secTarefas = document.getElementById("fr-section-tarefas");
  if (secObras) secObras.classList.add("hidden");
  if (secTarefas) secTarefas.classList.remove("hidden");

  // Filtra todas as tarefas da obra
  const tarefasDaObra = dadosTarefas.filter(
    (t) => t.nomenclatura === obraSelecionada,
  );

  // Atualiza os filtros dinamicamente com base nessa obra
  atualizarFiltrosDinamicos(tarefasDaObra);

  // Captura os novos valores dos selects após atualização
  const colaboradorSelecionado =
    document.getElementById("filtro_colaborador").value;
  let funcaoSelecionada = document.getElementById("nome_funcao").value;
  const statusSelecionado =
    document.getElementById("filtro_status")?.value || "";
  const buscaImagem = (document.getElementById("fr-search-funcao")?.value || "")
    .toLowerCase()
    .trim();

  // Se houver filtro global ativo, aplica e reflete visualmente (apenas na entrada)
  if (funcaoGlobalSelecionada) {
    funcaoSelecionada = funcaoGlobalSelecionada;

    const selectFuncao = document.getElementById("nome_funcao");
    const opcoes = Array.from(selectFuncao.options).map((opt) => opt.value);
    if (opcoes.includes(funcaoGlobalSelecionada)) {
      selectFuncao.value = funcaoGlobalSelecionada;
    }
    funcaoGlobalSelecionada = null; // limpa após aplicar — permite o usuário trocar livremente
  }

  // Aplica filtro de colaborador da home (se houver), apenas na entrada
  if (colaboradorGlobalSelecionado) {
    const selectColab = document.getElementById("filtro_colaborador");
    const opcoesColab = Array.from(selectColab.options).map((opt) => opt.value);
    if (opcoesColab.includes(colaboradorGlobalSelecionado)) {
      selectColab.value = colaboradorGlobalSelecionado;
    }
    colaboradorGlobalSelecionado = null;
  }

  // Aplica filtro de status da home (se houver), apenas na entrada
  if (statusGlobalSelecionado) {
    const selectStatus = document.getElementById("filtro_status");
    if (selectStatus) {
      const opcoesStatus = Array.from(selectStatus.options).map(
        (opt) => opt.value,
      );
      if (opcoesStatus.includes(statusGlobalSelecionado)) {
        selectStatus.value = statusGlobalSelecionado;
      }
    }
    statusGlobalSelecionado = null;
  }

  if (tarefasDaObra.length > 0) {
    const obraId = tarefasDaObra[0].idobra; // ajuste se o campo for diferente
    const nomenclatura = tarefasDaObra[0].nomenclatura;

    const obraNavLinks = document.querySelectorAll(".obra_nav");

    obraNavLinks.forEach((link) => {
      link.href = `https://improov.com.br/flow/ImproovWeb/FlowReview/index.php?obra_nome=${encodeURIComponent(nomenclatura)}`;
      link.textContent = nomenclatura;
    });

    // Atualiza KPI bar com filtro por obra
    loadKpis(obraId);
  }

  // Aplica os filtros adicionais (colaborador, função e status)
  const tarefasFiltradas = tarefasDaObra.filter((t) => {
    const matchColaborador =
      !colaboradorSelecionado || t.nome_colaborador === colaboradorSelecionado;
    const matchFuncao =
      funcaoSelecionada === "Todos" || t.nome_funcao === funcaoSelecionada;
    const matchStatus = !statusSelecionado || t.status === statusSelecionado;
    const imagemBuscaBase = [
      t.imagem_nome,
      t.nome_obra,
      t.nomenclatura,
      t.imagem_id,
    ]
      .filter(Boolean)
      .join(" ")
      .toLowerCase();
    const matchBuscaImagem =
      !buscaImagem || imagemBuscaBase.includes(buscaImagem);
    return matchColaborador && matchFuncao && matchStatus && matchBuscaImagem;
  });

  // Exibe as tarefas filtradas
  exibirTarefas(tarefasFiltradas, tarefasDaObra);
}

function atualizarSelectColaborador(tarefas) {
  const selectColaborador = document.getElementById("filtro_colaborador");
  const valorAnterior = selectColaborador.value;

  const colaboradores = [...new Set(tarefas.map((t) => t.nome_colaborador))];

  selectColaborador.innerHTML = '<option value="">Todos</option>';
  colaboradores.forEach((colab) => {
    const option = document.createElement("option");
    option.value = colab;
    option.textContent = colab;
    selectColaborador.appendChild(option);
  });

  if ([...selectColaborador.options].some((o) => o.value === valorAnterior)) {
    selectColaborador.value = valorAnterior;
  }
}

function atualizarSelectFuncao(tarefas) {
  const selectFuncao = document.getElementById("nome_funcao");
  const valorAnterior = selectFuncao.value;

  const funcoes = [...new Set(tarefas.map((t) => t.nome_funcao))];

  selectFuncao.innerHTML = '<option value="Todos">Todos</option>';
  funcoes.forEach((funcao) => {
    const option = document.createElement("option");
    option.value = funcao;
    option.textContent = funcao;
    selectFuncao.appendChild(option);
  });

  if ([...selectFuncao.options].some((o) => o.value === valorAnterior)) {
    selectFuncao.value = valorAnterior;
  }
}

// Eventos para os filtros
function atualizarFiltrosDinamicos(tarefas) {
  const selectColaborador = document.getElementById("filtro_colaborador");
  const selectFuncao = document.getElementById("nome_funcao");
  const selectStatus = document.getElementById("filtro_status");

  // Salva os valores antes de atualizar
  const valorAnteriorColaborador = selectColaborador.value;
  const valorAnteriorFuncao = selectFuncao.value;
  const valorAnteriorStatus = selectStatus ? selectStatus.value : "";

  const colaboradores = [...new Set(tarefas.map((t) => t.nome_colaborador))];
  const funcoes = [...new Set(tarefas.map((t) => t.nome_funcao))];
  const status = [...new Set(tarefas.map((t) => t.status))];

  // Atualiza select de colaborador
  selectColaborador.innerHTML = '<option value="">Todos</option>';
  colaboradores.forEach((colaborador) => {
    const option = document.createElement("option");
    option.value = colaborador;
    option.textContent = colaborador;
    selectColaborador.appendChild(option);
  });

  // Atualiza select de função
  selectFuncao.innerHTML = '<option value="Todos">Todos</option>';
  funcoes.forEach((funcao) => {
    const option = document.createElement("option");
    option.value = funcao;
    option.textContent = funcao;
    selectFuncao.appendChild(option);
  });

  // Atualiza select de status
  if (selectStatus) {
    selectStatus.innerHTML = '<option value="">Todos</option>';
    status.sort().forEach((st) => {
      const option = document.createElement("option");
      option.value = st;
      option.textContent = st;
      selectStatus.appendChild(option);
    });
  }

  // Reatribui os valores anteriores (se ainda existirem nas opções)
  if (
    [...selectColaborador.options].some(
      (o) => o.value === valorAnteriorColaborador,
    )
  ) {
    selectColaborador.value = valorAnteriorColaborador;
  }

  if ([...selectFuncao.options].some((o) => o.value === valorAnteriorFuncao)) {
    selectFuncao.value = valorAnteriorFuncao;
  }

  if (
    selectStatus &&
    [...selectStatus.options].some((o) => o.value === valorAnteriorStatus)
  ) {
    selectStatus.value = valorAnteriorStatus;
  }
}

document.getElementById("filtro_colaborador").addEventListener("change", () => {
  const obraSelecionada = document.getElementById("filtro_obra").value;
  const colaboradorSelecionado =
    document.getElementById("filtro_colaborador").value;

  const tarefasDaObra = dadosTarefas.filter(
    (t) => t.nomenclatura === obraSelecionada,
  );
  const tarefasFiltradas = tarefasDaObra.filter(
    (t) =>
      !colaboradorSelecionado || t.nome_colaborador === colaboradorSelecionado,
  );

  atualizarSelectFuncao(tarefasFiltradas); // atualiza o outro filtro com base nesse

  filtrarTarefasPorObra(obraSelecionada);
});

document.getElementById("nome_funcao").addEventListener("change", () => {
  const obraSelecionada = document.getElementById("filtro_obra").value;
  const funcaoSelecionada = document.getElementById("nome_funcao").value;

  const tarefasDaObra = dadosTarefas.filter(
    (t) => t.nomenclatura === obraSelecionada,
  );
  const tarefasFiltradas = tarefasDaObra.filter(
    (t) => funcaoSelecionada === "Todos" || t.nome_funcao === funcaoSelecionada,
  );

  atualizarSelectColaborador(tarefasFiltradas); // atualiza o outro filtro com base nesse

  filtrarTarefasPorObra(obraSelecionada);
});

const filtroStatusElement = document.getElementById("filtro_status");
if (filtroStatusElement) {
  filtroStatusElement.addEventListener("change", () => {
    const obraSelecionada = document.getElementById("filtro_obra").value;
    filtrarTarefasPorObra(obraSelecionada);
  });
}

function formatTaskElapsedTime(startValue) {
  const raw = String(startValue || "").trim();
  if (!raw) return "";

  const parsed = new Date(raw.replace(" ", "T") + "-03:00");
  if (Number.isNaN(parsed.getTime())) return "";

  const totalMinutes = Math.max(
    0,
    Math.floor((Date.now() + _serverTimeOffset - parsed.getTime()) / 60000),
  );
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;

  if (hours <= 0) {
    return `${minutes}min`;
  }

  return `${hours}h ${String(minutes).padStart(2, "0")}min`;
}

function getTaskStatusMeta(tarefa) {
  if (tarefa.pendente_direcao) {
    return { key: "direction", label: "Aguardando direção" };
  }

  if (tarefa.angulo_aprovado) {
    return { key: "approved-angle", label: "Ângulo aprovado" };
  }

  switch (tarefa.status_novo) {
    case "Em aprovação":
      return { key: "approval", label: "Em aprovação" };
    case "Ajuste":
      return { key: "critical", label: "Ajuste" };
    case "Aprovado com ajustes":
      return { key: "approved-adjust", label: "Aprovado com ajustes" };
    case "Finalizado":
      return { key: "completed", label: "Concluída" };
    default:
      return {
        key: "default",
        label: tarefa.status_novo || tarefa.status || "Sem status",
      };
  }
}

function getTaskTone(tarefa, mentionCount) {
  if (
    tarefa.prioridade_aprovacao == 1 &&
    tarefa.status_novo === "Em aprovação"
  ) {
    return "priority";
  }

  if (tarefa.pendente_direcao && tarefa.diretor_pode_aprovar) {
    return "direction";
  }

  if (mentionCount > 0) {
    return "mention";
  }

  return "";
}

function getTaskTimeMeta(tarefa, statusMeta) {
  if (
    statusMeta.key === "approval" &&
    tarefa.sla_inicio &&
    tarefa.sla_limite_horas
  ) {
    const { expirado, texto } = calcSlaTimer(
      tarefa.sla_inicio,
      tarefa.sla_limite_horas,
    );

    return {
      key: expirado ? "critical" : statusMeta.key,
      text: texto.replace(/^[⚠⏱]\s*/, ""),
      title: expirado
        ? `SLA excedido! Limite: ${tarefa.sla_limite_horas}h`
        : `Em aprovação há ${texto.replace(/^[⚠⏱]\s*/, "")} (limite: ${tarefa.sla_limite_horas}h)`,
      dataset: {
        timeMode: "sla",
        timeStart: tarefa.sla_inicio,
        slaLimite: tarefa.sla_limite_horas,
      },
    };
  }

  const startValue = tarefa.data_aprovacao || tarefa.sla_inicio;
  const elapsed = formatTaskElapsedTime(startValue);
  if (!elapsed) {
    return null;
  }

  return {
    key: statusMeta.key,
    text: elapsed,
    title: `Última movimentação há ${elapsed}`,
    dataset: {
      timeMode: "elapsed",
      timeStart: startValue,
      timeLabel: "Última movimentação",
    },
  };
}

function renderTaskAvatar(tarefa) {
  const nomeColaborador = tarefa.nome_colaborador || "Colaborador";
  const initials = getProcessHistoryInitials(nomeColaborador);
  const hue = getProcessHistoryHue(nomeColaborador);
  const photoUrl = resolveProcessHistoryPhotoUrl(tarefa.foto_colaborador);

  return `
    <span class="task-author-avatar${photoUrl ? "" : " task-author-avatar--fallback"}" style="--task-avatar-hue:${hue};">
      <span class="task-author-avatar-initials">${escapeHtml(initials)}</span>
      ${
        photoUrl
          ? `<img src="${escapeHtml(photoUrl)}" alt="${escapeHtml(nomeColaborador)}" class="task-author-avatar-img" onerror="this.remove()">`
          : ""
      }
    </span>
  `;
}

function updateTaskTimeBadge(el) {
  const mode = el.dataset.timeMode || "elapsed";
  const start = el.dataset.timeStart;

  if (!start) return;

  if (mode === "sla") {
    const limite = parseFloat(el.dataset.slaLimite);
    if (!limite) return;

    const { expirado, texto } = calcSlaTimer(start, limite);
    el.textContent = texto.replace(/^[⚠⏱]\s*/, "");
    el.title = expirado
      ? `SLA excedido! Limite: ${limite}h`
      : `Em aprovação há ${texto.replace(/^[⚠⏱]\s*/, "")} (limite: ${limite}h)`;
    el.classList.toggle("task-time-badge--critical", expirado);
    return;
  }

  const elapsed = formatTaskElapsedTime(start);
  if (!elapsed) return;

  el.textContent = elapsed;
  el.title = `${el.dataset.timeLabel || "Última movimentação"} há ${elapsed}`;
}

function refreshTaskTimeBadges() {
  document
    .querySelectorAll(".task-time-badge[data-time-start]")
    .forEach((el) => {
      updateTaskTimeBadge(el);
    });
}

// Função para exibir as tarefas e abastecer os filtros
function exibirTarefas(tarefas, tarefasCompletas) {
  const container = document.querySelector(".containerObra");
  container.style.display = "none";

  const tarefasObra = document.querySelector(".tarefasObra");
  tarefasObra.classList.remove("hidden");

  const tarefasImagensObra = document.querySelector(".tarefasImagensObra");
  tarefasImagensObra.innerHTML = "";

  exibirSidebarTabulator(tarefasCompletas);

  if (tarefas.length > 0) {
    const tarefasOrdenadas = [...tarefas].sort((a, b) => {
      const pA =
        a.prioridade_aprovacao == 1 && a.status_novo === "Em aprovação" ? 1 : 0;
      const pB =
        b.prioridade_aprovacao == 1 && b.status_novo === "Em aprovação" ? 1 : 0;
      if (pB !== pA) return pB - pA;

      const mA =
        (_mencoesDados.mencoes_por_funcao_imagem || {})[
          String(a.idfuncao_imagem)
        ] || 0;
      const mB =
        (_mencoesDados.mencoes_por_funcao_imagem || {})[
          String(b.idfuncao_imagem)
        ] || 0;
      return mB - mA;
    });

    tarefasOrdenadas.forEach((tarefa) => {
      const taskItem = document.createElement("div");
      taskItem.classList.add("task-item");
      taskItem.addEventListener("click", () => {
        historyAJAX(tarefa.idfuncao_imagem, getTaskTipo(tarefa));
      });

      const imagemPreview = tarefa.imagem
        ? `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(tarefa.imagem)}&w=450&q=85`
        : "../assets/logo.jpg";
      const qtdMencoesTask =
        (_mencoesDados.mencoes_por_funcao_imagem || {})[
          String(tarefa.idfuncao_imagem)
        ] || 0;
      const taskTone = getTaskTone(tarefa, qtdMencoesTask);
      const statusMeta = getTaskStatusMeta(tarefa);
      const timeMeta = getTaskTimeMeta(tarefa, statusMeta);

      if (taskTone) {
        taskItem.dataset.tone = taskTone;
      }

      const timeAttributes = Object.entries(timeMeta?.dataset || {})
        .map(
          ([key, value]) =>
            `data-${key.replace(/[A-Z]/g, (match) => `-${match.toLowerCase()}`)}="${escapeHtml(String(value))}"`,
        )
        .join(" ");
      const pairBadge = tarefa.par_primario_nome
        ? `<span class="task-card-pair-badge" title="${escapeHtml(`${tarefa.par_primario_nome}: ${tarefa.par_primario_status}`)}">+ ${escapeHtml(tarefa.par_primario_nome)}</span>`
        : "";
      const taskTitle =
        tarefa.nome_obra || tarefa.imagem_nome || tarefa.nome_funcao;
      const taskSubtitle = tarefa.imagem_nome || tarefa.nomenclatura || "";

      taskItem.innerHTML = `
        <div class="task-card-media">
          <div class="task-card-topbar">
            <span class="task-status-badge task-status-badge--${escapeHtml(statusMeta.key)}">${escapeHtml(statusMeta.label)}</span>
            ${
              timeMeta
                ? `<span class="task-time-badge task-time-badge--${escapeHtml(timeMeta.key)}" ${timeAttributes}>${escapeHtml(timeMeta.text)}</span>`
                : ""
            }
          </div>
          <div class="image-wrapper">
            <img src="${imagemPreview}" alt="Imagem da obra ${escapeHtml(taskTitle)}" class="task-image" onerror="this.onerror=null;this.src='../assets/logo.jpg';">
          </div>
        </div>
        <div class="task-card-body">
          <div class="task-card-kicker-row">
            <span class="task-card-kicker"><i class="fa-regular fa-folder-open"></i>${escapeHtml(tarefa.nome_funcao || "Função")}</span>
            ${pairBadge}
          </div>
          <p class="task-card-subtitle" data-obra="${escapeHtml(tarefa.nomenclatura || "")}">${escapeHtml(taskSubtitle)}</p>
          <div class="task-card-footer">
            <div class="task-card-author">
              ${renderTaskAvatar(tarefa)}
              <span class="task-card-author-name">${escapeHtml(tarefa.nome_colaborador || "Colaborador")}</span>
            </div>
            <span class="task-card-date">${escapeHtml(formatarDataHora(tarefa.data_aprovacao))}</span>
          </div>
        </div>
      `;

      if (qtdMencoesTask > 0) {
        const badge = document.createElement("div");
        badge.classList.add("mencao-badge");
        badge.setAttribute("data-task-badge", tarefa.idfuncao_imagem);
        badge.textContent = `💬 ${qtdMencoesTask}`;
        taskItem.appendChild(badge);
      }

      if (tarefa.pendente_direcao && tarefa.diretor_pode_aprovar) {
        const dirBadge = document.createElement("div");
        dirBadge.classList.add("pendente-direcao-badge");
        dirBadge.setAttribute("data-direcao-badge", tarefa.idfuncao_imagem);
        dirBadge.textContent = "⏳";
        dirBadge.title = "Aguardando validação da direção";
        taskItem.appendChild(dirBadge);
      }

      if (
        tarefa.prioridade_aprovacao == 1 &&
        tarefa.status_novo === "Em aprovação"
      ) {
        const prioBadge = document.createElement("div");
        prioBadge.classList.add("prioridade-badge");
        prioBadge.setAttribute("data-prio-badge", tarefa.idfuncao_imagem);
        prioBadge.textContent = "🔥";
        prioBadge.title = "Aprovação com prioridade";
        taskItem.appendChild(prioBadge);
      }

      tarefasImagensObra.appendChild(taskItem);
    });

    refreshTaskTimeBadges();
  } else {
    container.innerHTML =
      '<p style="text-align: center; color: #888;">Não há tarefas de revisão no momento.</p>';
  }
}

function formatarData(data) {
  const [ano, mes, dia] = data.split("-"); // Divide a string no formato 'YYYY-MM-DD'
  return `${dia}/${mes}/${ano}`; // Retorna o formato 'DD/MM/YYYY'
}

/**
 * Calculates how long a task has been in "Em aprovação" and whether it
 * has exceeded its SLA limit.
 *
 * @param {string} inicio       – MySQL datetime string (YYYY-MM-DD HH:MM:SS)
 * @param {number} limiteHoras  – SLA limit in hours
 * @returns {{ expirado: boolean, texto: string, horasDecorridas: number }}
 */
function calcSlaTimer(inicio, limiteHoras) {
  // Parseia como horário de Brasília (UTC-3) para não depender do fuso local
  const inicioDate = new Date(String(inicio).replace(" ", "T") + "-03:00");
  // Usa horário do servidor (corrigido pelo offset calculado no fetch)
  const horasDecorridas =
    (Date.now() + _serverTimeOffset - inicioDate.getTime()) / 36e5;
  const expirado = horasDecorridas >= limiteHoras;
  const h = Math.floor(horasDecorridas);
  const m = Math.floor((horasDecorridas % 1) * 60);
  const texto = expirado ? `⚠ ${h}h ${m}min` : `⏱ ${h}h ${m}min`;
  return { expirado, texto, horasDecorridas };
}

// Live-update all visible SLA timer badges every 60 seconds
setInterval(() => {
  refreshTaskTimeBadges();

  document.querySelectorAll(".sla-timer[data-sla-inicio]").forEach((el) => {
    const inicio = el.dataset.slaInicio;
    const limite = parseFloat(el.dataset.slaLimite);
    if (!inicio || !limite) return;
    const { expirado, texto } = calcSlaTimer(inicio, limite);
    el.textContent = texto;
    el.title = expirado
      ? `SLA excedido! Limite: ${limite}h`
      : `Em aprovação há ${texto.replace("⏱ ", "")} (limite: ${limite}h)`;
    if (expirado) el.classList.add("sla-breach");
    else el.classList.remove("sla-breach");
  });
}, 60000);

function formatarDataHora(data) {
  const date = new Date(data);

  const dia = String(date.getDate()).padStart(2, "0");
  const mes = String(date.getMonth() + 1).padStart(2, "0");
  const ano = date.getFullYear();
  const horas = String(date.getHours()).padStart(2, "0");
  const minutos = String(date.getMinutes()).padStart(2, "0");
  const segundos = String(date.getSeconds()).padStart(2, "0");

  return `${dia}/${mes}/${ano} ${horas}:${minutos}:${segundos}`;
}

function formatarDataComentario(data) {
  if (!data) return "";
  // Normalise MySQL 'YYYY-MM-DD HH:MM:SS' for iOS Safari (needs 'T' separator)
  const date = new Date(data.replace(" ", "T"));
  if (isNaN(date.getTime())) return data;
  const dia = String(date.getDate()).padStart(2, "0");
  const mes = String(date.getMonth() + 1).padStart(2, "0");
  const ano = date.getFullYear();
  const horas = String(date.getHours()).padStart(2, "0");
  const minutos = String(date.getMinutes()).padStart(2, "0");
  const segundos = String(date.getSeconds()).padStart(2, "0");
  return `${dia}/${mes}/${ano} ${horas}:${minutos}:${segundos}`;
}

// Escapa texto para evitar injeção de HTML ao inserir conteúdo dinâmico
function escapeHtml(unsafe) {
  if (unsafe === null || unsafe === undefined) return "";
  return String(unsafe)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

const modalComment = document.getElementById("modalComment");

const idusuario = parseInt(localStorage.getItem("idusuario")); // Obtém o idusuario do localStorage
const idcolaboradorLogado = parseInt(localStorage.getItem("idcolaborador"), 10);

let funcaoImagemId = null; // armazenado globalmente
let currentFuncaoContext = null; // {imagem_id, funcao_imagem_id, colaborador_id, nome_funcao, nome_status, imagem_nome}
let currentIndiceEnvio = null;
let currentAngleSelection = {
  isP00Finalizacao: false,
  hasChosen: false,
  chosen: null,
  available: [],
};
let currentIsFlowAngulo = false;
const processHistoryState = {
  currentImageId: null,
  isOpen: false,
  requestToken: 0,
  abortController: null,
  cache: new Map(),
  initialized: false,
};

function isProcessHistoryDesktop() {
  // Allow on all screen sizes (mobile shows icon-only button, tablet/desktop shows full button)
  return true;
}

function getProcessHistoryElements() {
  const root = document.getElementById("process-history");
  const trigger = document.getElementById("history-trigger");
  const dropdown = document.getElementById("history-dropdown");
  const body = document.getElementById("history-dropdown-body");

  if (!root || !trigger || !dropdown || !body) {
    return null;
  }

  return { root, trigger, dropdown, body };
}

function setProcessHistoryOpen(isOpen) {
  const elements = getProcessHistoryElements();
  if (!elements) return;

  processHistoryState.isOpen = isOpen;
  elements.root.classList.toggle("is-open", isOpen);
  elements.trigger.setAttribute("aria-expanded", String(isOpen));
  elements.dropdown.setAttribute("aria-hidden", String(!isOpen));
}

function closeProcessHistoryDropdown() {
  setProcessHistoryOpen(false);
}

function renderProcessHistoryState(kind, message) {
  const elements = getProcessHistoryElements();
  if (!elements) return;

  const icons = {
    idle: "fa-timeline",
    loading: "fa-spinner fa-spin",
    empty: "fa-box-open",
    error: "fa-circle-exclamation",
  };

  elements.body.innerHTML = `
    <div class="history-state history-state-${kind}">
      <i class="fa-solid ${icons[kind] || icons.idle}" aria-hidden="true"></i>
      <p>${escapeHtml(message)}</p>
    </div>
  `;
}

function getProcessHistoryInitials(name) {
  const tokens = String(name || "")
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2);

  if (!tokens.length) {
    return "FR";
  }

  return tokens.map((token) => token.charAt(0).toUpperCase()).join("");
}

function getProcessHistoryHue(value) {
  let hash = 0;
  const text = String(value || "FlowReview");

  for (let index = 0; index < text.length; index += 1) {
    hash = text.charCodeAt(index) + ((hash << 5) - hash);
  }

  return Math.abs(hash) % 360;
}

function resolveProcessHistoryPhotoUrl(photoPath) {
  const raw = String(photoPath || "").trim();
  if (!raw) return "";

  if (raw.startsWith("data:image/")) {
    return raw;
  }

  const base = "https://improov.com.br/flow/ImproovWeb";
  const marker = "/flow/ImproovWeb/";

  // Keep URL query/hash while normalizing path segments.
  const splitIndex = raw.search(/[?#]/);
  const queryAndHash = splitIndex >= 0 ? raw.slice(splitIndex) : "";
  let pathOnly = splitIndex >= 0 ? raw.slice(0, splitIndex) : raw;

  pathOnly = pathOnly.replace(/\\/g, "/").trim();

  if (pathOnly.startsWith("//")) {
    pathOnly = `https:${pathOnly}`;
  }

  if (/^https?:\/\//i.test(pathOnly)) {
    const idx = pathOnly.indexOf(marker);
    if (idx !== -1) {
      const suffix = pathOnly.slice(idx + marker.length);
      return `${base}/${suffix}${queryAndHash}`;
    }

    // External absolute URL: force into canonical ImproovWeb base.
    try {
      const parsed = new URL(pathOnly);
      const cleanPath = parsed.pathname.replace(/^\/+/, "");
      return `${base}/${cleanPath}${queryAndHash || parsed.search || ""}${queryAndHash ? "" : parsed.hash || ""}`;
    } catch (_e) {
      // Continue with generic cleanup below.
    }
  }

  pathOnly = pathOnly
    .replace(/^\.\//, "")
    .replace(/^(\.\.\/)+/, "")
    .replace(/^\/+/, "");

  if (pathOnly.startsWith("flow/ImproovWeb/")) {
    pathOnly = pathOnly.slice("flow/ImproovWeb/".length);
  }

  return `${base}/${pathOnly}${queryAndHash}`;
}

function renderProcessHistoryItems(items) {
  const elements = getProcessHistoryElements();
  if (!elements) return;

  if (!items.length) {
    renderProcessHistoryState("empty", "Nenhum histórico encontrado");
    return;
  }

  const markup = items
    .map((item, index) => {
      const isCurrent = index === 0;
      const isViewer =
        Number.isFinite(idcolaboradorLogado) &&
        Number(item.colaborador_id) === idcolaboradorLogado;
      const rawName = item.colaborador_nome || "Colaborador não informado";
      const displayName = isViewer ? `${rawName} (Você)` : rawName;
      const initials = getProcessHistoryInitials(rawName);
      const avatarHue = getProcessHistoryHue(rawName);
      const photoUrl = resolveProcessHistoryPhotoUrl(item.foto_colaborador);
      const dateText = item.data_processo
        ? formatarDataComentario(String(item.data_processo))
        : "Sem movimentação";

      return `
        <article class="history-item${isCurrent ? " is-current" : ""}">
          <div class="history-item-marker">
            <span class="history-item-dot" aria-hidden="true"></span>
            <span class="history-avatar" style="--history-avatar-hue:${avatarHue};">
              <span class="history-avatar-initials">${escapeHtml(initials)}</span>
              ${
                photoUrl
                  ? `<img class="history-avatar-img" src="${escapeHtml(photoUrl)}" alt="${escapeHtml(rawName)}" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">`
                  : ""
              }
            </span>
          </div>

          <div class="history-item-content">
            <div class="history-item-top">
              <time class="history-item-date">${escapeHtml(dateText)}</time>
              ${isCurrent ? '<span class="history-item-badge">Atual</span>' : ""}
            </div>
            <strong class="history-item-name">${escapeHtml(displayName)}</strong>
            <span class="history-item-role">${escapeHtml(item.nome_funcao || "Função não identificada")}</span>
          </div>
        </article>
      `;
    })
    .join("");

  elements.body.innerHTML = `<div class="history-timeline">${markup}</div>`;
}

async function loadProcessHistory(imagemId, options = {}) {
  const force = options.force === true;

  if (!imagemId) {
    renderProcessHistoryState("empty", "Nenhum histórico encontrado");
    return;
  }

  if (!force && processHistoryState.cache.has(imagemId)) {
    renderProcessHistoryItems(processHistoryState.cache.get(imagemId) || []);
    return;
  }

  if (processHistoryState.abortController) {
    processHistoryState.abortController.abort();
  }

  const controller = new AbortController();
  const requestToken = processHistoryState.requestToken + 1;
  processHistoryState.requestToken = requestToken;
  processHistoryState.abortController = controller;

  renderProcessHistoryState("loading", "Carregando histórico...");

  try {
    const response = await fetch(
      `buscar_historico_processos.php?imagem_id=${encodeURIComponent(String(imagemId))}`,
      {
        signal: controller.signal,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );

    if (!response.ok) {
      throw new Error("Falha ao carregar o histórico.");
    }

    const data = await response.json();
    if (
      requestToken !== processHistoryState.requestToken ||
      Number(imagemId) !== Number(processHistoryState.currentImageId)
    ) {
      return;
    }

    const items = Array.isArray(data.items) ? data.items : [];
    processHistoryState.cache.set(Number(imagemId), items);
    renderProcessHistoryItems(items);
  } catch (error) {
    if (error.name === "AbortError") {
      return;
    }

    if (requestToken !== processHistoryState.requestToken) {
      return;
    }

    renderProcessHistoryState(
      "error",
      "Não foi possível carregar o histórico.",
    );
  }
}

function setProcessHistoryContext(item) {
  const elements = getProcessHistoryElements();
  if (!elements) return;

  processHistoryState.currentImageId = item?.imagem_id
    ? Number(item.imagem_id)
    : null;
  processHistoryState.requestToken += 1;

  if (processHistoryState.abortController) {
    processHistoryState.abortController.abort();
    processHistoryState.abortController = null;
  }

  closeProcessHistoryDropdown();

  if (processHistoryState.currentImageId) {
    elements.trigger.removeAttribute("disabled");
    elements.root.classList.remove("is-disabled");
    renderProcessHistoryState("idle", "Abra para visualizar o histórico");
    return;
  }

  elements.trigger.setAttribute("disabled", "disabled");
  elements.root.classList.add("is-disabled");
  renderProcessHistoryState("empty", "Nenhum histórico encontrado");
}

function handleProcessHistoryToggle(event) {
  event.preventDefault();
  event.stopPropagation();

  if (!processHistoryState.currentImageId) {
    return;
  }

  if (processHistoryState.isOpen) {
    closeProcessHistoryDropdown();
    return;
  }

  setProcessHistoryOpen(true);
  loadProcessHistory(processHistoryState.currentImageId);
}

function initProcessHistory() {
  if (processHistoryState.initialized) return;

  const elements = getProcessHistoryElements();
  if (!elements) return;

  processHistoryState.initialized = true;
  elements.trigger.setAttribute("disabled", "disabled");
  elements.root.classList.add("is-disabled");
  renderProcessHistoryState("idle", "Abra para visualizar o histórico");

  elements.trigger.addEventListener("click", handleProcessHistoryToggle);

  document.addEventListener("pointerdown", (event) => {
    if (!processHistoryState.isOpen) return;

    const currentElements = getProcessHistoryElements();
    if (!currentElements || currentElements.root.contains(event.target)) {
      return;
    }

    closeProcessHistoryDropdown();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && processHistoryState.isOpen) {
      closeProcessHistoryDropdown();
    }
  });

  window.addEventListener("resize", () => {
    // Close dropdown on resize to avoid mis-positioned dropdown
    closeProcessHistoryDropdown();
  });
}

function isP00FinalizacaoContext(context) {
  const isP00 =
    String(context?.nome_status || "").toLowerCase() === "p00" ||
    String(context?.nome_status_envio || "").toLowerCase() === "p00";
  const nomeFuncao = String(context?.nome_funcao || "").toLowerCase();
  return nomeFuncao === "finalização" && isP00;
}

function isFinalizacaoContext(context) {
  return String(context?.nome_funcao || "").toLowerCase() === "finalização";
}

function isP00FinalizacaoEnvio(context, imagensDoIndice) {
  if (!isFinalizacaoContext(context)) return false;
  if (!Array.isArray(imagensDoIndice) || imagensDoIndice.length === 0) {
    return isP00FinalizacaoContext(context);
  }
  return imagensDoIndice.some(
    (img) =>
      String(img?.nome_status_envio || img?.nome_status || "").toLowerCase() ===
      "p00",
  );
}

function isAnguloDefinitivo(img) {
  const liberada = img?.angulo_liberada == 1 || img?.angulo_liberada === "1";
  const sugerida = img?.angulo_sugerida == 1 || img?.angulo_sugerida === "1";
  return liberada && !sugerida;
}

function getCurrentAngleThumbUrl(img) {
  return `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(img?.imagem || "")}&w=320&q=85`;
}

function updateAngleActionForSelection(imagensDoIndice, context) {
  if (!currentIsFlowAngulo) return;

  const available = Array.isArray(imagensDoIndice) ? imagensDoIndice : [];
  const isP00Finalizacao = isP00FinalizacaoEnvio(context, available);
  const chosen = available.find((img) => isAnguloDefinitivo(img)) || null;

  currentAngleSelection = {
    isP00Finalizacao,
    hasChosen: Boolean(isP00Finalizacao && chosen),
    chosen,
    available,
  };

  const btnOpen = document.getElementById("submit_decision");
  const actionsGroupLabel = document.querySelector(
    ".angulo-actions-group-label",
  );
  if (!btnOpen) return;

  if (!isP00Finalizacao) {
    btnOpen.textContent = "Escolher ângulo";
    btnOpen.disabled = true;
    btnOpen.title = "Selecione um envio P00 para decidir o ângulo.";
    return;
  }

  if (actionsGroupLabel) {
    actionsGroupLabel.textContent = "Decisão do ângulo (P00)";
  }
  btnOpen.disabled = false;
  btnOpen.title = "";
  btnOpen.textContent = chosen ? "Escolher outro ângulo" : "Escolher ângulo";
}

async function atualizarAnguloEscolhido(acao, observacao = "") {
  if (!currentFuncaoContext || !ap_imagem_id) {
    alert("Selecione um ângulo para continuar.");
    return;
  }

  const payload = {
    acao,
    observacao,
    imagem_id: parseInt(currentFuncaoContext.imagem_id, 10),
    funcao_imagem_id: parseInt(currentFuncaoContext.funcao_imagem_id, 10),
    historico_id: parseInt(ap_imagem_id, 10),
  };

  try {
    const res = await fetch("atualizar_angulo.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!data.success) {
      alert(data.message || "Erro ao atualizar ângulo.");
      return;
    }
    historyAJAX(funcaoImagemId);
  } catch (e) {
    console.error(e);
    alert("Erro ao atualizar ângulo.");
  }
}

async function abrirModalEscolhaAngulo() {
  if (!ap_imagem_id) {
    Toastify({
      text: "Selecione um ângulo na lista antes de continuar.",
      duration: 3000,
      backgroundColor: "orange",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();
    return;
  }

  // Show the existing decision modal (positioned under the approval button)
  const modal = document.getElementById("decisionModal");
  if (!modal) {
    Toastify({
      text: "Modal de decisão não encontrado.",
      duration: 3000,
      backgroundColor: "red",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();
    return;
  }

  // Configure radios for P00 flow (only two options)
  const labels = modal.querySelectorAll("label");
  if (labels && labels.length >= 3) {
    labels[0].innerHTML =
      '<input type="radio" name="decision" value="escolhido"> Escolhido';
    labels[1].innerHTML =
      '<input type="radio" name="decision" value="escolhido_com_ajustes"> Escolhido com Ajustes';
    labels[2].innerHTML = ""; // hide third option in P00
  }

  // Reset confirm button listeners by replacing it
  const btnConfirm = replaceElementById("confirmBtn");
  const btnClose = modal.querySelector(".close") || null;
  const cancelBtn = replaceElementById("cancelBtn");

  btnConfirm.classList.remove("hidden");
  btnConfirm.addEventListener("click", () => {
    const selected = document.querySelector(
      'input[name="decision"]:checked',
    )?.value;
    if (!selected) return;
    if (!ap_imagem_id) {
      Toastify({
        text: "Selecione um ângulo antes.",
        duration: 2000,
        backgroundColor: "orange",
        close: true,
        gravity: "top",
        position: "right",
      }).showToast();
      return;
    }
    atualizarAnguloEscolhido(selected);
    modal.classList.add("hidden");
  });

  if (btnClose) {
    btnClose.addEventListener("click", () => {
      modal.classList.add("hidden");
    });
  }

  // Position below the approval button and show
  const trigger =
    document.getElementById("submit_decision") ||
    document.querySelector("#submit_decision");
  positionDecisionModal(trigger, modal);
}

async function trocarAnguloDefinitivo(novoHistoricoId, observacao = "") {
  if (!currentFuncaoContext || !novoHistoricoId) {
    Toastify({
      text: "Selecione um ângulo para continuar.",
      duration: 2500,
      backgroundColor: "orange",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();
    return false;
  }

  const payload = {
    imagem_id: parseInt(currentFuncaoContext.imagem_id, 10),
    funcao_imagem_id: parseInt(currentFuncaoContext.funcao_imagem_id, 10),
    novo_historico_id: parseInt(novoHistoricoId, 10),
    observacao,
  };

  try {
    const res = await fetch("trocar_angulo.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!data.success) {
      Toastify({
        text: data.message || "Erro ao trocar ângulo.",
        duration: 4000,
        backgroundColor: "red",
        close: true,
        gravity: "top",
        position: "right",
      }).showToast();
      return false;
    }

    Toastify({
      text: "Ângulo definitivo trocado.",
      duration: 2500,
      backgroundColor: "green",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();
    historyAJAX(funcaoImagemId);
    return true;
  } catch (e) {
    console.error(e);
    Toastify({
      text: "Erro ao trocar ângulo.",
      duration: 3000,
      backgroundColor: "red",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();
    return false;
  }
}

function abrirModalTrocarAngulo() {
  const modal = document.getElementById("trocarAnguloModal");
  const list = document.getElementById("trocarAnguloList");
  const feedback = document.getElementById("trocarAnguloFeedback");
  const observacao = document.getElementById("trocarAnguloObservacao");
  if (!modal || !list || !feedback || !observacao) {
    Toastify({
      text: "Modal de troca não encontrado.",
      duration: 3000,
      backgroundColor: "red",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();
    return;
  }

  const chosen = currentAngleSelection.chosen;
  const available = Array.isArray(currentAngleSelection.available)
    ? currentAngleSelection.available
    : [];
  if (!chosen || available.length < 2) {
    Toastify({
      text: "Não há outro ângulo disponível para troca.",
      duration: 3000,
      backgroundColor: "orange",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();
    return;
  }

  let selectedId = null;
  list.innerHTML = "";
  feedback.textContent = "";
  observacao.value = "";

  const btnClose = replaceElementById("trocarAnguloClose");
  const btnCancel = replaceElementById("trocarAnguloCancel");
  const btnConfirm = replaceElementById("trocarAnguloConfirm");
  btnConfirm.disabled = true;

  const closeModal = () => {
    modal.classList.add("hidden");
    feedback.textContent = "";
  };

  available.forEach((img, index) => {
    const isCurrent = String(img.id) === String(chosen.id);
    const card = document.createElement("button");
    card.type = "button";
    card.className = "angle-change-card" + (isCurrent ? " is-current" : "");
    card.dataset.historicoId = String(img.id);
    card.innerHTML = `
      ${isCurrent ? '<span class="angle-change-badge">Atual</span>' : ""}
      <span class="angle-change-thumb">
        <img src="${getCurrentAngleThumbUrl(img)}" alt="Ângulo ${index + 1}">
      </span>
      <span class="angle-change-meta">
        <span class="angle-change-title">${escapeHtml(img.nome_arquivo || `Ângulo ${index + 1}`)}</span>
        <span class="angle-change-check"><i class="fa-solid fa-check"></i></span>
      </span>
    `;

    card.addEventListener("click", () => {
      if (isCurrent) {
        selectedId = null;
        feedback.textContent = "Escolha um ângulo diferente do atual.";
        btnConfirm.disabled = true;
        list
          .querySelectorAll(".angle-change-card")
          .forEach((el) => el.classList.remove("is-selected"));
        return;
      }

      selectedId = String(img.id);
      feedback.textContent = "";
      list
        .querySelectorAll(".angle-change-card")
        .forEach((el) => el.classList.remove("is-selected"));
      card.classList.add("is-selected");
      btnConfirm.disabled = false;
    });

    list.appendChild(card);
  });

  btnClose.addEventListener("click", closeModal);
  btnCancel.addEventListener("click", closeModal);
  modal.onclick = (event) => {
    if (event.target === modal) closeModal();
  };

  btnConfirm.addEventListener("click", async () => {
    if (!selectedId || String(selectedId) === String(chosen.id)) {
      feedback.textContent = "Escolha um ângulo diferente do atual.";
      btnConfirm.disabled = true;
      return;
    }

    btnConfirm.disabled = true;
    btnConfirm.textContent = "Trocando...";
    const ok = await trocarAnguloDefinitivo(
      selectedId,
      String(observacao.value || "").trim(),
    );
    btnConfirm.textContent = "Confirmar troca";
    if (ok) {
      closeModal();
    } else {
      btnConfirm.disabled = false;
    }
  });

  modal.classList.remove("hidden");
}

// Position the modal centered to the trigger button; prefer below, fallback to above.
function positionDecisionModal(triggerEl, modalEl) {
  if (!modalEl) return;
  if (!triggerEl) {
    // center on viewport
    const vw = Math.max(
      document.documentElement.clientWidth || 0,
      window.innerWidth || 0,
    );
    modalEl.style.left = Math.round((vw - modalEl.offsetWidth) / 2) + "px";
    modalEl.style.top =
      Math.round(window.innerHeight / 2 - modalEl.offsetHeight / 2) + "px";
    modalEl.classList.remove("hidden");
    return;
  }

  // show to measure (remove hidden if present)
  const wasHidden = modalEl.classList.contains("hidden");
  modalEl.classList.remove("hidden");

  // small timeout to ensure styles applied
  window.setTimeout(() => {
    const tr = triggerEl.getBoundingClientRect();
    const mr = modalEl.getBoundingClientRect();

    const spaceBelow = window.innerHeight - tr.bottom - 8;
    const spaceAbove = tr.top - 8;

    let top;
    if (spaceBelow >= mr.height || spaceBelow >= 80) {
      top = tr.bottom + 8; // place below
      modalEl.style.transform = "translateY(0)";
    } else {
      top = Math.max(8, tr.top - mr.height - 8); // place above
      modalEl.style.transform = "translateY(0)";
    }

    let left = tr.left + tr.width / 2 - mr.width / 2;
    left = Math.max(8, Math.min(left, window.innerWidth - mr.width - 8));

    modalEl.style.left = Math.round(left) + "px";
    modalEl.style.top = Math.round(top) + "px";
    modalEl.classList.remove("hidden");

    // reposition on scroll/resize while visible
    const handler = () => positionDecisionModal(triggerEl, modalEl);
    // store for cleanup
    if (modalEl._positionObserver) modalEl._positionObserver.disconnect();
    const obs = new MutationObserver(() => {
      if (modalEl.classList.contains("hidden")) {
        window.removeEventListener("scroll", handler);
        window.removeEventListener("resize", handler);
        if (modalEl._positionObserver) {
          modalEl._positionObserver.disconnect();
          modalEl._positionObserver = null;
        }
      }
    });
    modalEl._positionObserver = obs;
    obs.observe(modalEl, { attributes: true, attributeFilter: ["class"] });

    window.addEventListener("scroll", handler, { passive: true });
    window.addEventListener("resize", handler);
  }, 8);
}

async function enviarFuncaoParaAjustes() {
  if (!currentFuncaoContext) {
    Toastify({
      text: "Contexto de função não disponível.",
      duration: 3000,
      backgroundColor: "orange",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();
    return;
  }

  const confirmResult = await Swal.fire({
    title: "Enviar para Ajustes",
    text: "Nenhum ângulo foi aprovado. Confirma o envio de toda a função para Ajustes?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sim, enviar para ajustes",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#c0392b",
  });

  if (!confirmResult.isConfirmed) return;

  const idcolaborador = localStorage.getItem("idcolaborador");

  try {
    const res = await fetch("revisarTarefa.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        idfuncao_imagem: currentFuncaoContext.funcao_imagem_id,
        nome_colaborador: currentFuncaoContext.colaborador_nome,
        imagem_nome: currentFuncaoContext.imagem_nome,
        nome_funcao: currentFuncaoContext.nome_funcao,
        colaborador_id: currentFuncaoContext.colaborador_id,
        responsavel: idcolaborador,
        imagem_id: currentFuncaoContext.imagem_id,
        tipoRevisao: "ajuste",
      }),
    });
    const data = await res.json();

    Toastify({
      text: data.success
        ? "Função enviada para Ajustes."
        : "Erro: " + data.message,
      duration: 3000,
      backgroundColor: data.success ? "#e65c00" : "red",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();

    if (data.success) {
      // Atualiza o status_novo em memória para o badge na sidebar
      const task = dadosTarefas.find(
        (t) => t.idfuncao_imagem == currentFuncaoContext.funcao_imagem_id,
      );
      if (task) task.status_novo = "Ajuste";

      const obraSelecionada = document.getElementById("filtro_obra").value;
      if (obraSelecionada) filtrarTarefasPorObra(obraSelecionada);

      historyAJAX(funcaoImagemId);
    }
  } catch (e) {
    console.error(e);
    Toastify({
      text: "Erro ao enviar para ajustes.",
      duration: 3000,
      backgroundColor: "red",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();
  }
}

const enviosComparisonState = {
  active: false,
  grouped: {},
  indices: [],
  viewers: [],
  normalMedia: null,
};
let activeComparisonCommentViewer = null;
let commentPreviewContainer = null;
let historyRequestSequence = 0;
let relativeX = null;
let relativeY = null;

class EnvioCompareViewer {
  constructor(side) {
    this.side = side;
    this.root = document.querySelector(`[data-compare-side="${side}"]`);
    this.select = document.getElementById(`compare-envio-${side}`);
    this.viewport = this.root?.querySelector(".envios-comparison-viewport");
    this.content = this.root?.querySelector(".envios-comparison-content");
    this.commentsCache = this.root?.querySelector(
      ".envios-comparison-comment-cache",
    );
    this.imageId = null;
    this.zoom = 1;
    this.translateX = 0;
    this.translateY = 0;
    this.panning = false;
    this.dragMoved = false;
    this.startClientX = 0;
    this.startClientY = 0;
    this.startTranslateX = 0;
    this.startTranslateY = 0;
    this.loadSequence = 0;
    this.bindEvents();
  }

  bindEvents() {
    if (!this.root) return;
    this.select.addEventListener("change", () => this.loadSelectedEnvio());
    this.root
      .querySelector('[data-compare-action="zoom-in"]')
      ?.addEventListener("click", () => this.changeZoom(0.1));
    this.root
      .querySelector('[data-compare-action="zoom-out"]')
      ?.addEventListener("click", () => this.changeZoom(-0.1));
    this.root
      .querySelector('[data-compare-action="zoom-reset"]')
      ?.addEventListener("click", () => this.resetTransform());

    this.viewport.addEventListener(
      "wheel",
      (event) => {
        if (!event.ctrlKey) return;
        event.preventDefault();
        event.stopPropagation();
        this.changeZoom(event.deltaY < 0 ? 0.1 : -0.1);
      },
      { passive: false },
    );

    document.addEventListener("mousemove", (event) => {
      if (!this.panning) return;
      event.preventDefault();
      const dx = event.clientX - this.startClientX;
      const dy = event.clientY - this.startClientY;
      if (Math.abs(dx) > 3 || Math.abs(dy) > 3) this.dragMoved = true;
      this.translateX = this.startTranslateX + dx;
      this.translateY = this.startTranslateY + dy;
      this.applyTransform();
    });

    document.addEventListener("mouseup", () => {
      if (!this.panning) return;
      this.panning = false;
      this.viewport.classList.remove("is-panning");
      window.setTimeout(() => {
        this.dragMoved = false;
      }, 0);
    });
  }

  setOptions(indices, selectedIndex) {
    this.select.innerHTML = "";
    indices.forEach((indice) => {
      const option = document.createElement("option");
      option.value = indice;
      option.textContent = `Envio ${indice}`;
      this.select.appendChild(option);
    });
    this.select.value = selectedIndex || indices[0] || "";
  }

  getSelectedMedia() {
    const items = [...(enviosComparisonState.grouped[this.select.value] || [])];
    items.sort((a, b) => new Date(b.data_envio) - new Date(a.data_envio));
    return items[0] || null;
  }

  activateCommentTarget() {
    activeComparisonCommentViewer = this;
    commentPreviewContainer = this.content;
    ap_imagem_id = this.imageId;
    currentMediaMode = "image";
    currentVideoTimeMs = null;
    pdfViewerState.logId = null;
  }

  async loadSelectedEnvio() {
    const requestSequence = ++this.loadSequence;
    const media = this.getSelectedMedia();
    this.imageId = media?.id || null;
    this.commentsCache.innerHTML = "";
    this.content.innerHTML = "";
    this.resetTransform();

    if (!media || isVideoMedia(media)) {
      const empty = document.createElement("div");
      empty.className = "envios-comparison-empty";
      empty.textContent = media
        ? "Este envio não contém uma imagem comparável."
        : "Envio sem imagem.";
      this.content.appendChild(empty);
      return;
    }

    const img = document.createElement("img");
    img.alt =
      media.nome_arquivo || media.imagem || `Envio ${this.select.value}`;
    img.draggable = false;
    img.src = `https://improov.com.br/flow/ImproovWeb/${encodeURI(media.imagem)}`;
    this.content.appendChild(img);

    img.addEventListener("click", (event) => {
      if (this.dragMoved || drawingTool !== "ponto") return;
      if (_replyingToCommentId !== null) return;
      this.activateCommentTarget();
      const rect = img.getBoundingClientRect();
      relativeX = ((event.clientX - rect.left) / rect.width) * 100;
      relativeY = ((event.clientY - rect.top) / rect.height) * 100;
      _editingCommentId = null;
      if (quillComentario) quillComentario.setContents([]);
      const title = document.querySelector("#comentarioModal h3");
      if (title) title.textContent = "Novo Comentário";
      document.getElementById("imagemComentario").value = "";
      showCommentPreview();
      openCommentModalAtPoint(event.clientX, event.clientY);
      mencionadosIds = [];
    });

    img.addEventListener("mousedown", (event) => {
      if (event.button !== 0 || event.ctrlKey) return;
      this.activateCommentTarget();
      if (drawingTool !== "ponto") {
        startDrawingOnReviewElement(event, img, this.content);
        return;
      }
      this.panning = true;
      this.dragMoved = false;
      this.startClientX = event.clientX;
      this.startClientY = event.clientY;
      this.startTranslateX = this.translateX;
      this.startTranslateY = this.translateY;
      this.viewport.classList.add("is-panning");
    });

    await renderComments(media.id, {
      comentariosDiv: this.commentsCache,
      markerContainer: this.content,
      hideList: true,
      isCurrent: () => requestSequence === this.loadSequence,
    });
  }

  async refreshComments() {
    if (!this.imageId) return;
    const requestSequence = this.loadSequence;
    await renderComments(this.imageId, {
      comentariosDiv: this.commentsCache,
      markerContainer: this.content,
      hideList: true,
      isCurrent: () => requestSequence === this.loadSequence,
    });
  }

  changeZoom(delta) {
    this.zoom = Math.max(0.5, this.zoom + delta);
    if (this.zoom === 0.5) {
      this.translateX = 0;
      this.translateY = 0;
    }
    this.applyTransform();
  }

  resetTransform() {
    this.zoom = 1;
    this.translateX = 0;
    this.translateY = 0;
    this.applyTransform();
  }

  applyTransform() {
    if (!this.content) return;
    this.content.style.transform = `scale(${this.zoom}) translate(${this.translateX}px, ${this.translateY}px)`;
    this.content.querySelectorAll(".comment").forEach((comment) => {
      comment.style.transform = `translate(-50%, -50%) scale(${1 / this.zoom})`;
    });
    this.content.querySelectorAll(".comment-shape-badge").forEach((badge) => {
      badge.style.transform = `scale(${1 / this.zoom})`;
      badge.style.transformOrigin = "top left";
    });
  }
}

function updateEnviosComparisonAvailability() {
  const button = document.getElementById("compare-envios-btn");
  if (!button) return;
  const isDesktop = window.matchMedia("(min-width: 1025px)").matches;
  button.hidden = !isDesktop || enviosComparisonState.indices.length < 2;
  if (!isDesktop && enviosComparisonState.active) {
    exitEnviosComparison(true);
  }
}

function setEnviosComparisonData(grouped, indices) {
  enviosComparisonState.grouped = grouped || {};
  enviosComparisonState.indices = Array.isArray(indices) ? [...indices] : [];
  updateEnviosComparisonAvailability();
}

function enterEnviosComparison() {
  if (
    enviosComparisonState.active ||
    enviosComparisonState.indices.length < 2 ||
    !window.matchMedia("(min-width: 1025px)").matches
  ) {
    return;
  }

  enviosComparisonState.normalMedia = {
    id: ap_imagem_id,
    mode: currentMediaMode,
    downloadUrl: currentDownloadUrl,
    pdfLogId: pdfViewerState.logId,
    pdfPage: pdfViewerState.page,
  };
  enviosComparisonState.active = true;
  closeCommentPopup();

  const layout = document.querySelector(".imagens");
  const normalViewer = document.getElementById("image_wrapper");
  const comparison = document.getElementById("envios-comparison");
  const button = document.getElementById("compare-envios-btn");
  const wrapperSidebar = document.getElementById("wrapper-sidebar");
  wrapperSidebar.classList.add("collapsed");
  layout?.classList.add("envios-comparison-active");
  if (normalViewer) normalViewer.hidden = true;
  if (comparison) comparison.hidden = false;
  if (button) {
    button.querySelector("span").textContent = "Sair da comparação";
    button.title = "Sair da comparação";
  }

  const [first, second] = enviosComparisonState.indices;
  enviosComparisonState.viewers.forEach((viewer, index) => {
    viewer.setOptions(
      enviosComparisonState.indices,
      index === 0 ? first : second,
    );
    viewer.loadSelectedEnvio();
  });
}

function exitEnviosComparison(restoreNormalViewer = true) {
  if (!enviosComparisonState.active) return;
  enviosComparisonState.active = false;
  activeComparisonCommentViewer = null;
  commentPreviewContainer = null;
  closeCommentPopup();
  removeCommentPreview();

  document
    .querySelector(".imagens")
    ?.classList.remove("envios-comparison-active");
  const normalViewer = document.getElementById("image_wrapper");
  const comparison = document.getElementById("envios-comparison");
  const button = document.getElementById("compare-envios-btn");
  const wrapperSidebar = document.getElementById("wrapper-sidebar");
  wrapperSidebar.classList.remove("collapsed");
  if (normalViewer) normalViewer.hidden = false;
  if (comparison) comparison.hidden = true;
  if (button) {
    button.querySelector("span").textContent = "Comparar envios";
    button.title = "Comparar envios";
  }

  const normalMedia = enviosComparisonState.normalMedia;
  enviosComparisonState.normalMedia = null;
  if (restoreNormalViewer && normalMedia) {
    ap_imagem_id = normalMedia.id;
    currentMediaMode = normalMedia.mode || "image";
    currentDownloadUrl = normalMedia.downloadUrl || null;
    if (normalMedia.mode === "pdf" && normalMedia.pdfLogId) {
      pdfViewerState.logId = normalMedia.pdfLogId;
      pdfViewerState.page = normalMedia.pdfPage || 1;
      renderComments({
        arquivo_log_id: normalMedia.pdfLogId,
        pagina: pdfViewerState.page,
      });
    } else if (normalMedia.id) {
      renderComments(normalMedia.id);
    }
  }
}

function refreshCurrentCommentTarget() {
  if (
    enviosComparisonState.active &&
    activeComparisonCommentViewer &&
    String(activeComparisonCommentViewer.imageId) === String(ap_imagem_id)
  ) {
    return activeComparisonCommentViewer.refreshComments();
  }
  return renderComments(ap_imagem_id);
}

function initializeEnviosComparison() {
  const button = document.getElementById("compare-envios-btn");
  if (!button) return;
  enviosComparisonState.viewers = [
    new EnvioCompareViewer("a"),
    new EnvioCompareViewer("b"),
  ];
  button.addEventListener("click", () => {
    if (enviosComparisonState.active) exitEnviosComparison(true);
    else enterEnviosComparison();
  });
  window.addEventListener("resize", updateEnviosComparisonAvailability);
  updateEnviosComparisonAvailability();
}

initializeEnviosComparison();

function captureFlowReviewViewState() {
  const comentarioModal = document.getElementById("comentarioModal");
  const video = document.getElementById("video_atual");
  const comparison = enviosComparisonState.active
    ? enviosComparisonState.viewers.map((viewer) => ({
        side: viewer.side,
        indice: viewer.select?.value || "",
        zoom: viewer.zoom,
        translateX: viewer.translateX,
        translateY: viewer.translateY,
        imageId: viewer.imageId,
      }))
    : [];

  const filterIds = [
    "filtro_obra",
    "filtro_colaborador",
    "nome_funcao",
    "filtro_status",
    "fr-search-funcao",
    "stab-search",
    "stab-funcao",
    "stab-colab",
  ];
  const filters = {};
  filterIds.forEach((id) => {
    const element = document.getElementById(id);
    if (element) filters[id] = element.value;
  });

  return {
    mediaId: enviosComparisonState.active
      ? enviosComparisonState.normalMedia?.id
      : ap_imagem_id,
    mediaMode: enviosComparisonState.active
      ? enviosComparisonState.normalMedia?.mode
      : currentMediaMode,
    pdfLogId: enviosComparisonState.active
      ? enviosComparisonState.normalMedia?.pdfLogId
      : pdfViewerState.logId,
    pdfPage: enviosComparisonState.active
      ? enviosComparisonState.normalMedia?.pdfPage
      : pdfViewerState.page,
    indiceEnvio: document.getElementById("indiceSelect")?.value || "",
    zoom: currentZoom,
    translateX: currentTranslateX,
    translateY: currentTranslateY,
    videoTime: video ? video.currentTime : null,
    comparisonActive: enviosComparisonState.active,
    comparison,
    filters,
    navScrollTop: document.querySelector(".imagens > nav")?.scrollTop || 0,
    navScrollLeft: document.querySelector(".imagens > nav")?.scrollLeft || 0,
    commentsScrollTop: document.querySelector(".comentarios")?.scrollTop || 0,
    taskScrollTop: document.getElementById("stab-items")?.scrollTop || 0,
    windowScrollX: window.scrollX,
    windowScrollY: window.scrollY,
    rightCollapsed: document
      .querySelector(".sidebar-direita")
      ?.classList.contains("collapsed"),
    leftCollapsed: document
      .getElementById("wrapper-sidebar")
      ?.classList.contains("collapsed"),
    commentModalOpen: comentarioModal?.style.display === "flex",
    commentDraft: quillComentario?.root?.innerHTML || "",
    editingCommentId: _editingCommentId,
    replyingToCommentId: _replyingToCommentId,
    editingReplyId: _editingReplyId,
  };
}

function restoreFlowReviewFilters(filters) {
  Object.entries(filters || {}).forEach(([id, value]) => {
    const element = document.getElementById(id);
    if (!element) return;
    const supportsValue =
      element.tagName !== "SELECT" ||
      Array.from(element.options).some((option) => option.value === value);
    if (supportsValue) element.value = value;
  });
  const stabFuncao = document.getElementById("stab-funcao");
  if (stabFuncao) stabFuncao.dispatchEvent(new Event("change", { bubbles: true }));
}

async function restoreFlowReviewViewState(state) {
  if (!state) return;

  restoreFlowReviewFilters(state.filters);

  const indiceSelect = document.getElementById("indiceSelect");
  if (
    indiceSelect &&
    state.indiceEnvio &&
    Array.from(indiceSelect.options).some(
      (option) => option.value === String(state.indiceEnvio),
    )
  ) {
    indiceSelect.value = String(state.indiceEnvio);
    indiceSelect.dispatchEvent(new Event("change"));
  }

  if (state.mediaId) {
    const selectedMedia = document.querySelector(
      `#imagens [data-id="${String(state.mediaId)}"]`,
    );
    if (selectedMedia) selectedMedia.click();
  }

  if (
    state.mediaMode === "pdf" &&
    state.pdfLogId &&
    String(pdfViewerState.logId) === String(state.pdfLogId) &&
    state.pdfPage > 1
  ) {
    for (let attempt = 0; attempt < 20 && !pdfViewerState.doc; attempt += 1) {
      await new Promise((resolve) => setTimeout(resolve, 100));
    }
    if (pdfViewerState.doc) {
      pdfViewerState.page = Math.min(
        Number(state.pdfPage) || 1,
        pdfViewerState.pages || Number(state.pdfPage) || 1,
      );
      await renderizarPaginaPdf();
    }
  }

  currentZoom = Number(state.zoom) || 1;
  currentTranslateX = Number(state.translateX) || 0;
  currentTranslateY = Number(state.translateY) || 0;
  applyTransforms();

  const currentVideo = document.getElementById("video_atual");
  if (currentVideo && state.videoTime !== null) {
    currentVideo.currentTime = Math.max(0, Number(state.videoTime) || 0);
  }

  if (state.comparisonActive && enviosComparisonState.indices.length >= 2) {
    enterEnviosComparison();
    await Promise.all(
      enviosComparisonState.viewers.map(async (viewer) => {
        const saved = state.comparison.find((item) => item.side === viewer.side);
        if (!saved) return;
        if (
          saved.indice &&
          Array.from(viewer.select.options).some(
            (option) => option.value === String(saved.indice),
          )
        ) {
          viewer.select.value = String(saved.indice);
          await viewer.loadSelectedEnvio();
        }
        viewer.zoom = Number(saved.zoom) || 1;
        viewer.translateX = Number(saved.translateX) || 0;
        viewer.translateY = Number(saved.translateY) || 0;
        viewer.applyTransform();
      }),
    );
  }

  const rightSidebar = document.querySelector(".sidebar-direita");
  const leftSidebar = document.getElementById("wrapper-sidebar");
  rightSidebar?.classList.toggle("collapsed", Boolean(state.rightCollapsed));
  leftSidebar?.classList.toggle("collapsed", Boolean(state.leftCollapsed));

  if (state.commentModalOpen) {
    _editingCommentId = state.editingCommentId;
    _replyingToCommentId = state.replyingToCommentId;
    _editingReplyId = state.editingReplyId;
    if (quillComentario?.root) quillComentario.root.innerHTML = state.commentDraft;
    const modal = document.getElementById("comentarioModal");
    if (modal) modal.style.display = "flex";
  }

  requestAnimationFrame(() => {
    const nav = document.querySelector(".imagens > nav");
    const comments = document.querySelector(".comentarios");
    const tasks = document.getElementById("stab-items");
    if (nav) {
      nav.scrollTop = state.navScrollTop;
      nav.scrollLeft = state.navScrollLeft;
    }
    if (comments) comments.scrollTop = state.commentsScrollTop;
    if (tasks) tasks.scrollTop = state.taskScrollTop;
    window.scrollTo(state.windowScrollX, state.windowScrollY);
  });
}

function historyAJAX(idfuncao_imagem, tipo_tarefa = null, options = {}) {
  const preservedViewState = options.preserveView
    ? captureFlowReviewViewState()
    : null;
  const requestSequence = ++historyRequestSequence;
  exitEnviosComparison(false);
  setEnviosComparisonData({}, []);
  funcaoImagemId = idfuncao_imagem;
  const tarefaAtualPre = dadosTarefas.find(
    (t) => String(t.idfuncao_imagem) === String(idfuncao_imagem),
  );
  const tipoTarefaAtual = tipo_tarefa || getTaskTipo(tarefaAtualPre);

  // Marca menções desta tarefa como vistas e atualiza badges
  fetch("marcar_mencoes_visto.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ funcao_imagem_id: idfuncao_imagem }),
  }).then(() => {
    const chave = String(idfuncao_imagem);
    if (_mencoesDados.mencoes_por_funcao_imagem[chave]) {
      delete _mencoesDados.mencoes_por_funcao_imagem[chave];
      // Remove badge do card de tarefa
      const badge = document.querySelector(
        `[data-task-badge="${idfuncao_imagem}"]`,
      );
      if (badge) badge.remove();
      // Recalcula contagem por obra e atualiza badge da obra
      const obraEl = document.querySelector(".obra-info h3"); // fallback if needed
      // Rebusca menções para atualizar badge da obra
      buscarMencoesDoUsuario();
    }
  });

  return fetch(
    `historico.php?ajid=${encodeURIComponent(String(idfuncao_imagem))}&tipo_tarefa=${encodeURIComponent(tipoTarefaAtual)}`,
  )
    .then((response) => response.json())
    .then((response) => {
      if (requestSequence !== historyRequestSequence) return;
      // console.log("Funcao Imagem:", idfuncao_imagem);
      const main = document.querySelector(".main");
      main.classList.add("hidden");

      const comentariosDiv = document.querySelector(".comentarios");
      comentariosDiv.innerHTML = "";
      const comentarioGeralEnvio = document.getElementById(
        "comentario-geral-envio",
      );
      if (comentarioGeralEnvio) {
        comentarioGeralEnvio.classList.add("hidden");
        comentarioGeralEnvio.innerHTML = "";
      }

      const container_aprovacao = document.querySelector(
        ".container-aprovacao",
      );
      container_aprovacao.classList.remove("hidden");

      // Oculta KPI bar durante a revisão de tarefa
      const frKpiBar = document.getElementById("fr-kpi-bar");
      if (frKpiBar) frKpiBar.classList.add("hidden");
      closeFrKpiPopover();

      const sidebarDiv = document.getElementById("sidebarTabulator");

      // Clona e substitui botões para evitar múltiplos event listeners
      const btnOpen = replaceElementById("submit_decision");
      const btnAjustesFuncao = replaceElementById("submit_ajustes_funcao");
      const addAnguloBtn = replaceElementById("add-angulo-btn");
      const modal = document.getElementById("decisionModal");
      const btnClose = replaceElementByClass("close");
      const cancelBtn = replaceElementById("cancelBtn");
      const btnConfirm = replaceElementById("confirmBtn");

      // Clona e substitui radios
      document.querySelectorAll('input[name="decision"]').forEach((radio) => {
        const clone = radio.cloneNode(true);
        radio.replaceWith(clone);
      });
      const radios = document.querySelectorAll('input[name="decision"]');

      const { historico, imagens, pdf } = response;
      const item = historico[0];

      currentFuncaoContext = item || null;
      setProcessHistoryContext(item || null);

      // Sincroniza sidebarTabulator com a função da tarefa aberta
      const tarefaAtual = dadosTarefas.find(
        (t) => String(t.idfuncao_imagem) === String(idfuncao_imagem),
      );
      if (currentFuncaoContext) {
        currentFuncaoContext.tipo_tarefa = tipoTarefaAtual;
        currentFuncaoContext.funcao_animacao_id =
          tarefaAtual?.funcao_animacao_id || item?.funcao_animacao_id || null;
      }
      if (tarefaAtual && window._stabSetFuncao) {
        window._stabSetFuncao(tarefaAtual.nome_funcao, idfuncao_imagem);
      } else if (window._stabSetActive) {
        window._stabSetActive(idfuncao_imagem);
      }

      const hasPdfPreferido = !!(pdf && pdf.id);
      const pdfRawUrl = hasPdfPreferido
        ? `../FlowDrive/visualizar_pdf_log.php?idlog=${encodeURIComponent(String(pdf.id))}&raw=1`
        : null;
      const pdfDownloadUrl = hasPdfPreferido
        ? `../FlowDrive/visualizar_pdf_log.php?idlog=${encodeURIComponent(String(pdf.id))}&raw=1&download=1`
        : null;
      let pdfShownOnce = false;

      // Preencher o container de aprovação (se houver um responsavel registrado)
      try {
        const approvalContainer = document.getElementById("approval_info");
        if (approvalContainer) {
          approvalContainer.style.display = "none";
          if (Array.isArray(historico) && historico.length > 0) {
            // procura o último registro com 'responsavel' preenchido
            const normalizeApprovalStatus = (value) =>
              String(value || "")
                .replace(/Ã§/g, "c")
                .replace(/Ã£/g, "a")
                .replace(/Ã¡/g, "a")
                .replace(/Ã©/g, "e")
                .replace(/Ãª/g, "e")
                .replace(/Ã³/g, "o")
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .toLowerCase()
                .trim();
            const currentApprovalStatus = normalizeApprovalStatus(
              tarefaAtual?.status || item?.status || item?.status_novo,
            );
            const approver = currentApprovalStatus.startsWith("em aprova")
              ? null
              : [...historico]
                  .filter((h) => h.responsavel && h.responsavel !== "0")
                  .sort(
                    (a, b) =>
                      new Date(b.data_aprovacao || b.data || 0) -
                      new Date(a.data_aprovacao || a.data || 0),
                  )
                  .find(
                    (h) =>
                      !normalizeApprovalStatus(
                        h.status_novo || h.status,
                      ).startsWith("em aprova"),
                  ) || null;
            if (approver) {
              const name = approver.responsavel_nome || "—";
              const status = approver.status_novo || approver.status || "—";
              const dt = approver.data_aprovacao || approver.data || null;
              const fecha = dt ? formatarDataHora(new Date(dt)) : "";
              let displayStatus = status;
              if (status === "Aguardando Direção") {
                try {
                  const obs = approver.observacoes
                    ? JSON.parse(approver.observacoes)
                    : null;
                  displayStatus = obs?.aprovacao_operacional || displayStatus;
                } catch (_) {
                  displayStatus = status;
                }
                if (
                  !["Aprovado", "Aprovado com ajustes"].includes(displayStatus)
                ) {
                  displayStatus = ["Aprovado", "Aprovado com ajustes"].includes(
                    approver.status_anterior,
                  )
                    ? approver.status_anterior
                    : "Aprovado";
                }
              } else if (
                status === "Aprovado" &&
                (tarefaAtual?.status === "Aprovado com ajustes" ||
                  item?.status === "Aprovado com ajustes")
              ) {
                displayStatus = "Aprovado com ajustes";
              }
              if (status !== "Em aprovação") {
                approvalContainer.innerHTML = `<div><strong>${escapeHtml(name)}</strong> — <span>${escapeHtml(displayStatus)} ${fecha ? '<br><small style="color:#666">' + escapeHtml(fecha) + "</small>" : ""}</div>`;
                approvalContainer.style.display = "block";
              }
            } else {
              approvalContainer.innerHTML = "";
              approvalContainer.style.display = "none";
            }
          }
        }
      } catch (e) {
        console.error("Erro ao preencher approval_info", e);
      }

      const isFlowAngulo =
        isP00FinalizacaoContext(item) &&
        Array.isArray(imagens) &&
        imagens.some(
          (img) =>
            String(
              img?.nome_status_envio || img?.nome_status || "",
            ).toLowerCase() === "p00",
        );
      currentIsFlowAngulo = isFlowAngulo;
      if (!isFlowAngulo) {
        currentAngleSelection = {
          isP00Finalizacao: false,
          hasChosen: false,
          chosen: null,
          available: [],
        };
      }

      const podeAprovar =
        Boolean(tarefaAtual) &&
        ([1, 2, 9, 20, 3].includes(idusuario) ||
          (idusuario === 8 &&
            [23, 40].includes(Number(item?.colaborador_id))) ||
          tarefaAtual?.diretor_pode_aprovar === true ||
          tarefaAtual?.finalizador_pode_aprovar === true) &&
        // Bloqueia o finalizador após a 1ª aprovação (pendente direção)
        !(
          tarefaAtual?.pendente_direcao &&
          !tarefaAtual?.diretor_pode_aprovar &&
          ![1, 2].includes(idusuario)
        );

      if (podeAprovar) {
        const actionsGroup = document.querySelector(".angulo-actions-group");
        if (actionsGroup) actionsGroup.style.display = "";
        const actionsGroupLabel = document.querySelector(
          ".angulo-actions-group-label",
        );
        if (isFlowAngulo) {
          if (actionsGroupLabel)
            actionsGroupLabel.textContent = "Decisão do ângulo (P00)";
          btnOpen.textContent = "Escolher ângulo";
          btnOpen.disabled = false;
          btnOpen.title = "";
          btnOpen.style.display = "flex";
          modal.classList.add("hidden");
          btnOpen.addEventListener("click", () => {
            if (currentAngleSelection.hasChosen) {
              abrirModalTrocarAngulo();
            } else {
              abrirModalEscolhaAngulo();
            }
          });

          btnAjustesFuncao.style.display = "flex";
          btnAjustesFuncao.addEventListener("click", () => {
            enviarFuncaoParaAjustes();
          });
        } else {
          btnAjustesFuncao.style.display = "none";
          btnOpen.style.display = "flex";
          if (actionsGroupLabel)
            actionsGroupLabel.textContent = "Enviar Aprovação";
          btnOpen.textContent = "Enviar aprovação";
          btnOpen.disabled = false;
          btnOpen.title = "";
          const labels = document.querySelectorAll("#decisionModal label");
          if (labels[0])
            labels[0].innerHTML =
              '<input type="radio" name="decision" value="aprovado"> Aprovado';
          if (labels[1])
            labels[1].innerHTML =
              '<input type="radio" name="decision" value="aprovado_com_ajustes"> Aprovado com ajustes';
          if (labels[2])
            labels[2].innerHTML =
              '<input type="radio" name="decision" value="ajuste"> Ajuste';

          document
            .querySelectorAll('input[name="decision"]')
            .forEach((radio) => {
              const clone = radio.cloneNode(true);
              radio.replaceWith(clone);
            });
          const updatedRadios = document.querySelectorAll(
            'input[name="decision"]',
          );

          btnOpen.addEventListener("click", () => {
            // Position and show the decision modal relative to the trigger button
            try {
              positionDecisionModal(btnOpen, modal);
            } catch (e) {
              // fallback: just show if positioning fails
              modal.classList.remove("hidden");
            }
          });

          btnClose.addEventListener("click", () => {
            modal.classList.add("hidden");
            btnConfirm.classList.add("hidden");
          });

          cancelBtn.addEventListener("click", () => {
            modal.classList.add("hidden");
            btnConfirm.classList.add("hidden");
            updatedRadios.forEach((r) => (r.checked = false));
          });

          updatedRadios.forEach((radio) => {
            radio.addEventListener("change", () => {
              btnConfirm.classList.remove("hidden");
            });
          });

          btnConfirm.addEventListener("click", () => {
            const selected = Array.from(updatedRadios).find(
              (r) => r.checked,
            )?.value;
            if (!selected) return;
            const tipoRevisaoTarefa = getTaskTipo(tarefaAtual);
            const tarefaRefId =
              getTaskRefId(tarefaAtual) ||
              item.funcao_animacao_id ||
              item.idfuncao_imagem ||
              item.funcao_imagem_id;

            revisarTarefa(
              tarefaRefId,
              item.colaborador_nome,
              item.imagem_nome,
              item.nome_funcao,
              item.colaborador_id,
              item.imagem_id,
              selected,
              tipoRevisaoTarefa,
              tipoRevisaoTarefa === "animacao" ? tarefaRefId : null,
            );

            modal.classList.add("hidden");
            btnConfirm.classList.add("hidden");
            updatedRadios.forEach((r) => (r.checked = false));
          });
        }
      } else {
        btnOpen.style.display = "none";
        btnAjustesFuncao.style.display = "none";
        const actionsGroup = document.querySelector(".angulo-actions-group");
        if (actionsGroup) actionsGroup.style.display = "none";
      }

      // addAnguloBtn.style.display = 'inline-flex';
      addAnguloBtn.addEventListener("click", () => {
        if (!currentFuncaoContext || !funcaoImagemId || !currentIndiceEnvio) {
          alert("Selecione um envio para adicionar novos ângulos.");
          return;
        }
        document.getElementById("imagem-modal").style.display = "flex";
      });

      const titulo = document.getElementById("funcao_nome");
      titulo.textContent = `${item.colaborador_nome} - ${item.nome_funcao}`;
      const imagemNomeHeader = document.getElementById("imagem_nome");
      const dataEnvioHeader = document.getElementById("header_data_envio");
      const statusInicial =
        item?.nome_status_envio || item?.nome_status || "Sem status";
      imagemNomeHeader.textContent = `${item.imagem_nome} (${statusInicial})`;

      const atualizarDataHeader = (dataValor) => {
        if (!dataEnvioHeader) return;
        if (!dataValor) {
          dataEnvioHeader.textContent = "";
          return;
        }
        dataEnvioHeader.textContent = `${formatarDataHora(dataValor)}`;
      };

      atualizarDataHeader(item?.data_aprovacao || null);

      const imageContainer = document.getElementById("imagens");
      imageContainer.innerHTML = "";

      // Clona e substitui select
      let indiceSelect = document.getElementById("indiceSelect");
      indiceSelect = indiceSelect.cloneNode(true);
      document.getElementById("indiceSelect").replaceWith(indiceSelect);
      indiceSelect.innerHTML = "";

      const imagensAgrupadas = imagens.reduce((acc, img) => {
        if (!acc[img.indice_envio]) acc[img.indice_envio] = [];
        acc[img.indice_envio].push(img);
        return acc;
      }, {});

      const indicesOrdenados = Object.keys(imagensAgrupadas).sort(
        (a, b) => b - a,
      );
      setEnviosComparisonData(imagensAgrupadas, indicesOrdenados);

      if (indicesOrdenados.length === 0) {
        indiceSelect.style.display = "none";

        // Fallback: quando não há imagens/JPGs, mas existe um PDF preferido, mostra o PDF direto.
        if (hasPdfPreferido && pdfRawUrl) {
          const nome =
            pdf && pdf.nome_arquivo
              ? pdf.nome_arquivo
              : item?.nome_funcao || "PDF";
          mostrarPdfCompleto(pdfRawUrl, pdfDownloadUrl, nome, pdf.id);
          pdfShownOnce = true;
        }
      } else {
        indiceSelect.style.display = "block";

        indicesOrdenados.forEach((indice) => {
          const option = document.createElement("option");
          option.value = indice;
          option.textContent = `Envio ${indice}`;
          indiceSelect.appendChild(option);
        });

        indiceSelect.value = indicesOrdenados[0];
        indiceSelect.dispatchEvent(new Event("change"));
      }

      indiceSelect.addEventListener("change", () => {
        const indiceSelecionado = indiceSelect.value;
        currentIndiceEnvio = indiceSelecionado
          ? parseInt(indiceSelecionado, 10)
          : null;
        imageContainer.innerHTML = "";

        const imagensDoIndice = imagensAgrupadas[indiceSelecionado];
        if (isFlowAngulo) {
          updateAngleActionForSelection(imagensDoIndice, item);
        }

        const textoGeral = Array.isArray(imagensDoIndice)
          ? String(
              imagensDoIndice.find((img) =>
                String(img.angulo_motivo || "").trim(),
              )?.angulo_motivo || "",
            ).trim()
          : "";
        // if (comentarioGeralEnvio) {
        //   if (textoGeral) {
        //     comentarioGeralEnvio.innerHTML = `<span class="label">Comentário geral</span><span>${escapeHtml(textoGeral)}</span>`;
        //     comentarioGeralEnvio.classList.remove("hidden");
        //   } else {
        //     comentarioGeralEnvio.classList.add("hidden");
        //     comentarioGeralEnvio.innerHTML = "";
        //   }
        // }

        if (imagensDoIndice && imagensDoIndice.length > 0) {
          imagensDoIndice.sort(
            (a, b) => new Date(b.data_envio) - new Date(a.data_envio),
          );
          const maisRecente = imagensDoIndice[0];

          if (maisRecente) {
            const statusEnvio =
              maisRecente.nome_status_envio ||
              item?.nome_status ||
              "Sem status";
            imagemNomeHeader.textContent = `${item.imagem_nome} (${statusEnvio})`;
            atualizarDataHeader(
              maisRecente.data_aprovacao ||
                maisRecente.data_envio ||
                item?.data_aprovacao ||
                null,
            );
          }

          if (maisRecente) {
            if (hasPdfPreferido && !pdfShownOnce && pdfRawUrl) {
              const nome =
                pdf && pdf.nome_arquivo
                  ? pdf.nome_arquivo
                  : item?.nome_funcao || "PDF";
              mostrarPdfCompleto(pdfRawUrl, pdfDownloadUrl, nome, pdf.id);
              pdfShownOnce = true;
            } else {
              const mediaUrl = `https://improov.com.br/flow/ImproovWeb/${maisRecente.imagem}`;
              if (isVideoMedia(maisRecente)) {
                mostrarVideoCompleto(mediaUrl, maisRecente.id, maisRecente);
              } else {
                mostrarImagemCompleta(mediaUrl, maisRecente.id);
              }
            }
          }

          imagensDoIndice.forEach((img) => {
            const wrapper = document.createElement("div");
            wrapper.className = "imageWrapper";

            // Estado do ângulo (para P00 + Finalização)
            const anguloLiberada =
              img.angulo_liberada == 1 || img.angulo_liberada === "1";
            const anguloSugerida =
              img.angulo_sugerida == 1 || img.angulo_sugerida === "1";
            if (anguloLiberada) {
              wrapper.style.outline = "2px solid #2e7d32";
              wrapper.style.outlineOffset = "2px";
            } else if (anguloSugerida) {
              wrapper.style.outline = "2px solid #ef6c00";
              wrapper.style.outlineOffset = "2px";
            } else {
              // pendente
              wrapper.style.outline = "2px solid transparent";
            }

            // thumbnail for gallery thumbnails; clicking opens full image via mostrarImagemCompleta
            const fullImageUrl = `https://improov.com.br/flow/ImproovWeb/${encodeURI(img.imagem)}`;
            let imgElement;
            if (isVideoMedia(img)) {
              if (img.poster_path) {
                imgElement = document.createElement("button");
                imgElement.type = "button";
                imgElement.className = "image video-thumb video-thumb--poster";
                imgElement.setAttribute("data-id", img.id);
                const posterThumb = `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(img.poster_path)}&w=200&q=85`;
                imgElement.innerHTML = `<img src="${posterThumb}" alt="${escapeHtml(img.imagem || "Vídeo")}"><span class="video-thumb-play"><i class="fa-solid fa-play"></i></span>`;
                imgElement.addEventListener("click", () => {
                  mostrarVideoCompleto(fullImageUrl, img.id, img);
                });
              } else {
                imgElement = document.createElement("button");
                imgElement.type = "button";
                imgElement.className = "image video-thumb";
                imgElement.setAttribute("data-id", img.id);
                imgElement.innerHTML =
                  '<i class="fa-solid fa-play"></i><span>Vídeo</span>';
                imgElement.addEventListener("click", () => {
                  mostrarVideoCompleto(fullImageUrl, img.id, img);
                });
              }
            } else {
              imgElement = document.createElement("img");
              imgElement.src = `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(img.imagem)}&w=200&q=85`;
              imgElement.alt = img.imagem;
              imgElement.className = "image";
              imgElement.setAttribute("data-id", img.id);
              imgElement.addEventListener("click", () => {
                mostrarImagemCompleta(fullImageUrl, img.id);
              });
            }

            // imgElement.addEventListener("contextmenu", (event) => {
            //   event.preventDefault();
            //   ap_imagem_id = img.id;
            //   abrirMenuContexto(event.pageX, event.pageY, img.id, fullImageUrl);
            // });

            if (img.has_comments == "1" || img.has_comments === 1) {
              const notificationDot = document.createElement("div");
              const pendentes = parseInt(
                img.pending_count ?? img.comment_count ?? 0,
                10,
              );
              const total = parseInt(img.comment_count ?? 0, 10);
              const todosOk = total > 0 && pendentes === 0;
              notificationDot.className =
                "notification-dot" + (todosOk ? " notification-dot--ok" : "");
              notificationDot.textContent = todosOk
                ? "✓"
                : String(pendentes > 0 ? pendentes : total);
              notificationDot.title = todosOk
                ? "Todos os comentários concluídos"
                : `${pendentes} comentário(s) pendente(s)`;
              wrapper.appendChild(notificationDot);
            }

            wrapper.appendChild(imgElement);
            imageContainer.appendChild(wrapper);
          });
        }
      });

      if (indicesOrdenados.length > 0) {
        indiceSelect.value = indicesOrdenados[0];
        indiceSelect.dispatchEvent(new Event("change"));
      }

      if (preservedViewState) {
        return restoreFlowReviewViewState(preservedViewState);
      }
    })
    .catch((error) => console.error("Erro ao buscar dados:", error));
}

// Função utilitária para substituir elementos por ID
function replaceElementById(id) {
  const oldEl = document.getElementById(id);
  const newEl = oldEl.cloneNode(true);
  oldEl.replaceWith(newEl);
  return newEl;
}

// Função utilitária para substituir elementos por classe (única ocorrência)
function replaceElementByClass(className) {
  const oldEl = document.querySelector(`.${className}`);
  const newEl = oldEl.cloneNode(true);
  oldEl.replaceWith(newEl);
  return newEl;
}

function exibirSidebarTabulator(tarefas) {
  const sidebarDiv = document.getElementById("sidebarTabulator");

  // Preserva os valores dos filtros da sidebar antes de reconstruir o HTML
  const prevSearch = document.getElementById("stab-search")?.value || "";
  const prevFuncao = document.getElementById("stab-funcao")?.value ?? null;
  const prevColab = document.getElementById("stab-colab")?.value ?? null;

  sidebarDiv.innerHTML = "";

  // Listas únicas para os selects de filtro
  const funcoes = [...new Set(tarefas.map((t) => t.nome_funcao))].sort();
  const colabs = [...new Set(tarefas.map((t) => t.nome_colaborador))].sort();

  // Valor inicial dos filtros:
  // – usa o valor que já estava na sidebar (se existia), senão usa os selects principais
  const initSearch = prevSearch;
  const initFuncao =
    prevFuncao !== null
      ? prevFuncao
      : document.getElementById("nome_funcao")?.value || "Todos";
  const initColab =
    prevColab !== null
      ? prevColab
      : document.getElementById("filtro_colaborador")?.value || "";

  // ── Painel de filtros (mesmo estilo da fr-sidebar) ─────────────────────────────
  const filterDiv = document.createElement("div");
  filterDiv.className = "stab-filters";
  filterDiv.innerHTML = `
    <p class="fr-section-label">Filtros</p>
    <div class="stab-filter-group">
      <label class="stab-filter-label">
        <i class="fa-solid fa-magnifying-glass"></i> Buscar imagem
      </label>
      <div class="fr-input-wrap">
        <i class="fa-solid fa-magnifying-glass fr-input-icon"></i>
        <input type="search" id="stab-search" autocomplete="off" placeholder="Buscar imagem..." value="${escapeHtml(initSearch)}">
      </div>
    </div>
    <div class="stab-filter-group">
      <label class="stab-filter-label" for="stab-funcao">
        <i class="fa-solid fa-layer-group"></i> Fun\u00e7\u00e3o
      </label>
      <select id="stab-funcao">
        <option value="Todos">Todas as fun\u00e7\u00f5es</option>
        ${funcoes.map((f) => `<option value="${escapeHtml(f)}"${f === initFuncao ? " selected" : ""}>${escapeHtml(f)}</option>`).join("")}
      </select>
    </div>
    <div class="stab-filter-group">
      <label class="stab-filter-label" for="stab-colab">
        <i class="fa-solid fa-user"></i> Colaborador
      </label>
      <select id="stab-colab">
        <option value="">Todos</option>
        ${colabs.map((c) => `<option value="${escapeHtml(c)}"${c === initColab ? " selected" : ""}>${escapeHtml(c)}</option>`).join("")}
      </select>
    </div>
  `;

  // ── Container de itens ───────────────────────────────────────────────
  const itemsDiv = document.createElement("div");
  itemsDiv.id = "stab-items";
  itemsDiv.className = "stab-items";

  function renderStabItems() {
    const search = (document.getElementById("stab-search")?.value || "")
      .toLowerCase()
      .trim();
    const funcao = document.getElementById("stab-funcao")?.value || "Todos";
    const colab = document.getElementById("stab-colab")?.value || "";

    const filtered = tarefas.filter((t) => {
      const matchFuncao = funcao === "Todos" || t.nome_funcao === funcao;
      const matchColab = !colab || t.nome_colaborador === colab;
      const matchSearch =
        !search ||
        (t.imagem_nome || "").toLowerCase().includes(search) ||
        (t.nome_colaborador || "").toLowerCase().includes(search) ||
        (t.nome_funcao || "").toLowerCase().includes(search);
      return matchFuncao && matchColab && matchSearch;
    });

    itemsDiv.innerHTML = "";
    if (filtered.length === 0) {
      itemsDiv.innerHTML =
        '<p class="stab-empty">Nenhuma imagem encontrada.</p>';
      return;
    }

    filtered.forEach((t) => {
      const statusClass =
        t.status_novo === "Em aprovação"
          ? "tarefa-status--approval"
          : t.status_novo === "Ajuste"
            ? "tarefa-status--adjust"
            : t.status_novo === "Aprovado com ajustes"
              ? "tarefa-status--approved-adjust"
              : t.status_novo === "Aprovado"
                ? "tarefa-status--approved"
                : t.pendente_direcao
                  ? "tarefa-status--direction"
                  : "tarefa-status--default";

      const item = document.createElement("div");
      item.className = "tarefa-item";
      item.dataset.id = t.idfuncao_imagem;
      const imgSrc = t.imagem
        ? `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(t.imagem)}&w=400&q=85`
        : "../assets/logo.jpg";
      item.innerHTML = `
        <img src="${imgSrc}" class="tab-img" alt="${escapeHtml(t.imagem_nome || "")}">
        <div class="tarefa-item-body">
          <span class="tarefa-status ${statusClass}">${escapeHtml(t.status_novo || "")}</span>
          <span class="tarefa-label">${escapeHtml(t.nome_colaborador || "")} — ${escapeHtml(t.imagem_nome || "")}</span>
        </div>
      `;
      item.addEventListener("click", () => {
        itemsDiv
          .querySelectorAll(".tarefa-item")
          .forEach((el) => el.classList.remove("active"));
        item.classList.add("active");
        historyAJAX(t.idfuncao_imagem, getTaskTipo(t));
      });
      itemsDiv.appendChild(item);
    });
  }

  // Wire up filtros
  filterDiv.addEventListener("input", renderStabItems);
  filterDiv.addEventListener("change", renderStabItems);

  sidebarDiv.appendChild(filterDiv);
  sidebarDiv.appendChild(itemsDiv);

  // Render inicial
  renderStabItems();

  // Expõe funções para historyAJAX sincronizar o item ativo
  window._stabSetActive = function (idfuncao_imagem) {
    const items = document.querySelectorAll("#stab-items .tarefa-item");
    items.forEach((el) =>
      el.classList.toggle("active", el.dataset.id == idfuncao_imagem),
    );
    const active = document.querySelector("#stab-items .tarefa-item.active");
    if (active) active.scrollIntoView({ block: "nearest" });
  };

  window._stabSetFuncao = function (funcao, idfuncao_imagem) {
    const sel = document.getElementById("stab-funcao");
    if (sel && funcao) {
      const opt = Array.from(sel.options).find((o) => o.value === funcao);
      if (opt) sel.value = funcao;
    }
    renderStabItems();
    if (idfuncao_imagem !== undefined) {
      setTimeout(() => {
        if (window._stabSetActive) window._stabSetActive(idfuncao_imagem);
      }, 0);
    }
  };
}

document.querySelector(".close").addEventListener("click", () => {
  document.getElementById("imagem-modal").style.display = "none";
  document.getElementById("input-imagens").value = "";
  document.getElementById("preview").innerHTML = "";
});

// Close #imagem-modal when clicking on the dark overlay (outside the modal-content)
document.getElementById("imagem-modal").addEventListener("click", (e) => {
  if (e.target === document.getElementById("imagem-modal")) {
    document.getElementById("imagem-modal").style.display = "none";
    document.getElementById("input-imagens").value = "";
    document.getElementById("preview").innerHTML = "";
  }
});

document
  .getElementById("input-imagens")
  .addEventListener("change", function () {
    const preview = document.getElementById("preview");
    preview.innerHTML = "";

    const arquivos = this.files;

    for (let i = 0; i < arquivos.length; i++) {
      const reader = new FileReader();
      reader.onload = function (e) {
        const img = document.createElement("img");
        img.src = e.target.result;
        preview.appendChild(img);
      };
      reader.readAsDataURL(arquivos[i]);
    }
  });

document.getElementById("btn-enviar-imagens").addEventListener("click", () => {
  const input = document.getElementById("input-imagens");
  const arquivos = input.files;
  if (arquivos.length === 0 || !funcaoImagemId || !currentIndiceEnvio) return;

  const formData = new FormData();
  for (let i = 0; i < arquivos.length; i++) {
    formData.append("imagens[]", arquivos[i]);
  }

  // Extrai numero e nomenclatura do nome da imagem (mesmo padrão do scriptIndex.js)
  const imagemNome = currentFuncaoContext?.imagem_nome || "";
  const numeroImagem = imagemNome.match(/^\d+/)?.[0] || "";
  const nomenclaturaMatch = imagemNome.match(/^\d+\.\s*([A-Z0-9_]+)/i);
  const nomenclatura =
    currentFuncaoContext?.nomenclatura ||
    (nomenclaturaMatch ? nomenclaturaMatch[1] : "");

  formData.append("dataIdFuncoes", funcaoImagemId);
  formData.append("idimagem", String(currentFuncaoContext?.imagem_id || 0));
  formData.append("nome_funcao", currentFuncaoContext?.nome_funcao || "");
  formData.append("nome_imagem", imagemNome);
  formData.append("numeroImagem", numeroImagem);
  formData.append("nomenclatura", nomenclatura);
  // Passa o índice atual para adicionar ângulos ao mesmo envio
  formData.append("indice_envio_forcado", String(currentIndiceEnvio));

  fetch("../uploadArquivos.php", {
    method: "POST",
    body: formData,
  })
    .then((r) => r.json())
    .then((res) => {
      if (res.success) {
        Toastify({
          text: "Ângulos enviados com sucesso!",
          duration: 3000,
          backgroundColor: "green",
          close: true,
          gravity: "top",
          position: "right",
        }).showToast();
        document.getElementById("imagem-modal").style.display = "none";
        document.getElementById("input-imagens").value = "";
        document.getElementById("preview").innerHTML = "";
        historyAJAX(funcaoImagemId);
      } else {
        Toastify({
          text: res.error || "Erro ao enviar ângulos.",
          duration: 4000,
          backgroundColor: "red",
          close: true,
          gravity: "top",
          position: "right",
        }).showToast();
      }
    })
    .catch((e) => {
      console.error(e);
      Toastify({
        text: "Erro na comunicação com o servidor.",
        duration: 3000,
        backgroundColor: "red",
        close: true,
        gravity: "top",
        position: "right",
      }).showToast();
    });
});

function abrirMenuContexto(x, y, id, src) {
  const menu = document.getElementById("menuContexto");

  // Coloca info da imagem (caso precise usar depois)
  menu.setAttribute("data-id", id);
  menu.setAttribute("data-src", src);

  menu.style.top = `${y}px`;
  menu.style.left = `${x}px`;
  menu.style.display = "block";
}

function excluirImagem() {
  const menu = document.getElementById("menuContexto");
  const idImagem = menu.getAttribute("data-id");

  if (!idImagem) {
    alert("ID da imagem não encontrado!");
    return;
  }

  if (confirm("Tem certeza que deseja excluir esta imagem?")) {
    fetch("excluir_imagem.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `id=${idImagem}`,
    })
      .then((response) => response.text())
      .then((data) => {
        // console.log(data);
        // Remove a imagem da tela também, se quiser
        const imgElement = document.querySelector(`img[data-id='${idImagem}']`);
        if (imgElement) {
          imgElement.parentElement.remove(); // Remove o wrapper da imagem
        }
        // Esconde o menu
        menu.style.display = "none";
      })
      .catch((error) => {
        console.error("Erro ao excluir imagem:", error);
        alert("Erro ao excluir imagem.");
      });
  } else {
    // Fecha o menu caso cancele
    menu.style.display = "none";
  }
}

document.addEventListener("click", (e) => {
  const menu = document.getElementById("menuContexto");
  if (!menu.contains(e.target)) {
    menu.style.display = "none";
  }
  const menuImg = document.getElementById("menuContextoImagem");
  if (menuImg && !menuImg.contains(e.target)) {
    menuImg.style.display = "none";
  }
});

function abrirMenuContextoImagem(x, y) {
  const menu = document.getElementById("menuContextoImagem");
  if (!menu) return;
  menu.style.top = `${y}px`;
  menu.style.left = `${x}px`;
  menu.style.display = "block";
}

// async function marcarPrioridadeAprovacao() {
//   const menu = document.getElementById("menuContextoImagem");
//   if (menu) menu.style.display = "none";
//
//   const fimId = funcaoImagemId || currentFuncaoContext?.funcao_imagem_id;
//   if (!fimId) {
//     Swal.fire({
//       title: "Erro",
//       text: "Nenhuma tarefa selecionada.",
//       icon: "error",
//     });
//     return;
//   }
//
//   try {
//     const res = await fetch("marcar_prioridade_aprovacao.php", {
//       method: "POST",
//       headers: { "Content-Type": "application/json" },
//       body: JSON.stringify({ funcao_imagem_id: fimId }),
//     });
//     const data = await res.json();
//     if (!data.success) throw new Error(data.message || "Erro desconhecido");
//
//     const isPrioridade = data.prioridade === 1;
//     Swal.fire({
//       title: isPrioridade
//         ? "🔥 Marcada como prioridade!"
//         : "Prioridade removida",
//       text: isPrioridade
//         ? "Esta tarefa será exibida em destaque na fila de aprovação."
//         : "A prioridade desta tarefa foi removida.",
//       icon: "success",
//       timer: 2500,
//       showConfirmButton: false,
//     });
//
//     // Atualiza dado local para refletir imediatamente nos cards
//     const task = dadosTarefas.find((t) => t.idfuncao_imagem == fimId);
//     if (task) task.prioridade_aprovacao = isPrioridade ? 1 : 0;
//
//     const obraSelecionada = document.getElementById("filtro_obra").value;
//     if (obraSelecionada) {
//       filtrarTarefasPorObra(obraSelecionada);
//     }
//   } catch (err) {
//     Swal.fire({ title: "Erro", text: err.message, icon: "error" });
//   }
// }

let tribute; // variável global
let mencionadosIds = []; // armazenar os IDs dos mencionados
let _cachedUsers = []; // cache da lista de usuários para highlightMentions
let _editingCommentId = null; // ID do comentário em edição (null = novo comentário)
let _replyingToCommentId = null; // ID do comentário sendo respondido (null = não é resposta)
let _editingReplyId = null; // ID da resposta em edição (null = não é edição de resposta)
let quillComentario = null; // instância do Quill no modal de comentário
let _prioAlertShown = false; // garante que o alerta de prioridade só dispara uma vez por carregamento
let _serverTimeOffset = 0; // offset (ms) entre horário do servidor Brasília e relógio local
let _mencoesDados = {
  total_mencoes: 0,
  mencoes_por_obra: {},
  mencoes_por_funcao_imagem: {},
  comentarios_mencionados: [],
  respostas_mencionadas: [],
};

// ── Comment modal near-click positioning ────────────────────────────────────
let _commentPreviewMarker = null;

function openCommentModalAtPoint(cx, cy) {
  const modal = document.getElementById("comentarioModal");
  const content = modal.querySelector(".modal-content");
  if (!content) {
    modal.style.display = "flex";
    return;
  }

  // Show first so we can measure its size
  modal.style.display = "flex";
  const w = content.offsetWidth || 360;
  const h = content.offsetHeight || 340;
  const margin = 12;
  const vw = window.innerWidth;
  const vh = window.innerHeight;

  // Prefer placing to the right of the click; fall back to left
  let left = cx + 18;
  if (left + w + margin > vw) left = cx - w - 18;
  if (left < margin) left = margin;

  // Prefer just below the click; fall back to above
  let top = cy - 24;
  if (top + h + margin > vh) top = vh - h - margin;
  if (top < margin) top = margin;

  content.style.left = Math.round(left) + "px";
  content.style.top = Math.round(top) + "px";
}

function showCommentPreview() {
  removeCommentPreview();
  const imageWrapper =
    commentPreviewContainer || document.getElementById("image_wrapper");
  if (!imageWrapper) return;
  const marker = document.createElement("div");
  marker.className = "comment comment-preview";
  marker.id = "comment-preview-marker";
  marker.style.left = relativeX + "%";
  marker.style.top = relativeY + "%";
  marker.textContent = "+";
  imageWrapper.appendChild(marker);
  _commentPreviewMarker = marker;
}

function removeCommentPreview() {
  if (_commentPreviewMarker) {
    _commentPreviewMarker.remove();
    _commentPreviewMarker = null;
  }
  const stale = document.getElementById("comment-preview-marker");
  if (stale) stale.remove();
}
// ─────────────────────────────────────────────────────────────────────────────

document.addEventListener("DOMContentLoaded", async () => {
  try {
    const response = await fetch("buscar_usuarios.php");
    const users = await response.json();

    tribute = new Tribute({
      values: users.map((user) => ({
        key: user.nome_colaborador,
        value: user.nome_colaborador,
        id: user.idcolaborador,
      })),
      selectTemplate: (item) => {
        // Evita duplicados
        if (!mencionadosIds.includes(item.original.id)) {
          mencionadosIds.push(item.original.id);
        }
        return `@${item.original.value}`; // Aparece só o nome no texto
      },
      menuItemTemplate: (item) => item.string,
    });

    // Tribute will be attached to the Quill editor root after Quill is initialized
  } catch (error) {
    console.error("Erro ao carregar usuários:", error);
  }

  // ── Sidebar back button ──
  const frBackBtn = document.getElementById("fr-back-btn");
  if (frBackBtn) {
    frBackBtn.addEventListener("click", () => {
      // Restaura visão de obras
      const secObras = document.getElementById("fr-section-obras");
      const secTarefas = document.getElementById("fr-section-tarefas");
      if (secObras) secObras.classList.remove("hidden");
      if (secTarefas) secTarefas.classList.add("hidden");

      // Esconde tarefas, mostra cards de obra
      const tarefasObra = document.querySelector(".tarefasObra");
      const containerObra = document.querySelector(".containerObra");
      if (tarefasObra) tarefasObra.classList.add("hidden");
      if (containerObra) containerObra.style.display = "";

      // Limpa filtro de obra
      const filtroObraEl = document.getElementById("filtro_obra");
      if (filtroObraEl) filtroObraEl.value = "";

      // Reseta filtros globais de entrada
      funcaoGlobalSelecionada = null;
      colaboradorGlobalSelecionado = null;

      // Reaplica filtros da sidebar home (limpos)
      applyHomeFilters();

      // Recarrega KPIs no contexto geral (sem filtro de obra)
      const frKpiBarBack = document.getElementById("fr-kpi-bar");
      if (frKpiBarBack) frKpiBarBack.classList.remove("hidden");
      loadKpis(null);
    });
  }

  // Busca de imagem na sidebar (fr-section-tarefas)
  const frSearchFuncao = document.getElementById("fr-search-funcao");
  if (frSearchFuncao) {
    frSearchFuncao.addEventListener("input", () => {
      const obraSelecionada = document.getElementById("filtro_obra")?.value;
      if (obraSelecionada) filtrarTarefasPorObra(obraSelecionada);
    });
  }

  // Toggle do sidebarTabulator (mobile/tablet)
  const stabToggleBtn = document.getElementById("stab-toggle-btn");
  const wrapperSidebarEl = document.getElementById("wrapper-sidebar");
  if (stabToggleBtn && wrapperSidebarEl) {
    stabToggleBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      wrapperSidebarEl.classList.toggle("stab-open");
    });
    document.addEventListener("click", (e) => {
      if (
        wrapperSidebarEl.classList.contains("stab-open") &&
        !wrapperSidebarEl.contains(e.target) &&
        e.target !== stabToggleBtn
      ) {
        wrapperSidebarEl.classList.remove("stab-open");
      }
    });
  }

  // Recolher sidebar esquerda (wrapper-sidebar)
  const leftCollapseBtn = document.getElementById("left-collapse-btn");
  if (leftCollapseBtn && wrapperSidebarEl) {
    leftCollapseBtn.addEventListener("click", () => {
      wrapperSidebarEl.classList.toggle("collapsed");
      const icon = leftCollapseBtn.querySelector("i");
      if (icon) {
        icon.className = wrapperSidebarEl.classList.contains("collapsed")
          ? "fa-solid fa-chevron-right"
          : "fa-solid fa-chevron-left";
      }
      setTimeout(() => ajustarNavSelectAoTamanhoDaImagem(), 260);
    });
  }

  // Recolher sidebar direita (.sidebar-direita)
  const rightCollapseBtn = document.getElementById("right-collapse-btn");
  if (rightCollapseBtn) {
    rightCollapseBtn.addEventListener("click", () => {
      const sd = document.querySelector(".sidebar-direita");
      if (sd) {
        sd.classList.toggle("collapsed");
        const icon = rightCollapseBtn.querySelector("i");
        if (icon) {
          icon.className = sd.classList.contains("collapsed")
            ? "fa-solid fa-chevron-left"
            : "fa-solid fa-chevron-right";
        }
        setTimeout(() => ajustarNavSelectAoTamanhoDaImagem(), 260);
      }
    });
  }

  // Inicializa o editor Quill no modal de comentário
  if (window.Quill) {
    quillComentario = new Quill("#comentario-quill-editor", {
      theme: "snow",
      modules: {
        toolbar: [
          ["bold", "italic", "underline", "strike"],
          [{ color: [] }, { background: [] }],
          [{ list: "ordered" }, { list: "bullet" }],
          ["link"],
          ["clean"],
        ],
        clipboard: {
          matchVisual: false,
        },
      },
      placeholder: "Digite um comentário...",
    });

    // Anexa o Tribute ao editor Quill para menções (@)
    if (tribute) tribute.attach(quillComentario.root);

    // Captura colagem dentro do editor Quill
    quillComentario.root.addEventListener("paste", function (event) {
      const items = (event.clipboardData || event.originalEvent.clipboardData)
        ?.items;
      if (!items) return;

      // Se colou uma imagem, captura e ignora o resto
      for (let index in items) {
        const item = items[index];
        if (item.kind === "file" && item.type.startsWith("image/")) {
          event.preventDefault();
          event.stopPropagation();
          const blob = item.getAsFile();
          if (blob) {
            const fileInput = document.getElementById("imagemComentario");
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(
              new File([blob], "imagem_colada.png", { type: blob.type }),
            );
            fileInput.files = dataTransfer.files;
            Toastify({
              text: "Imagem colada com sucesso!",
              duration: 3000,
              backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
              close: true,
              gravity: "top",
              position: "right",
            }).showToast();
          }
          return; // não processa o texto junto
        }
      }

      // Para texto: cancela o paste nativo e insere apenas texto puro
      const text = event.clipboardData?.getData("text/plain");
      if (text) {
        event.preventDefault();
        event.stopPropagation();
        const range = quillComentario.getSelection(true);
        quillComentario.insertText(range.index, text, "user");
        quillComentario.setSelection(range.index + text.length, 0, "silent");
      }
    });
  }

  // Modal: fechar
  document.getElementById("fecharComentarioModal").onclick = () => {
    document.getElementById("comentarioModal").style.display = "none";
    removeCommentPreview();
    _editingCommentId = null;
    _replyingToCommentId = null;
    _editingReplyId = null;
    if (quillComentario) quillComentario.setContents([]);
    const modalTitle = document.querySelector("#comentarioModal h3");
    if (modalTitle) modalTitle.textContent = "Novo Comentário";
    const replyCtx = document.getElementById("reply-context");
    if (replyCtx) {
      replyCtx.style.display = "none";
      replyCtx.innerHTML = "";
    }
  };

  // Toolbar de ferramentas de desenho
  ["ponto", "rect", "circle", "freehand"].forEach((tool) => {
    const btn = document.getElementById(`tool-${tool}`);
    if (!btn) return;
    btn.addEventListener("click", () => {
      drawingTool = tool;
      document
        .querySelectorAll(".draw-tool-btn")
        .forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      // cursor + touch-action
      const iw = document.getElementById("image_wrapper");
      if (iw) {
        iw.style.cursor = tool === "ponto" ? "" : "crosshair";
        if (tool === "ponto") {
          iw.classList.remove("drawing-mode");
        } else {
          iw.classList.add("drawing-mode");
        }
      }
      if (tool === "ponto") {
        document.body.classList.remove("drawing-crosshair");
      } else {
        document.body.classList.add("drawing-crosshair");
      }
    });
  });

  // Color picker para ferramentas de desenho
  const colorPickerEl = document.getElementById("draw-color");
  if (colorPickerEl) {
    // Initialize drawingColor from the input value so defaults match the UI
    try {
      if (typeof drawingColor === "undefined") {
        // drawingColor declared later; safe-guard: set via property on window if needed
        window._initialDrawingColor = colorPickerEl.value;
      } else {
        drawingColor = colorPickerEl.value;
      }
    } catch (e) {
      // ignore
    }

    colorPickerEl.addEventListener("input", (e) => {
      drawingColor = e.target.value;
    });
    colorPickerEl.addEventListener("change", (e) => {
      drawingColor = e.target.value;
    });
  }

  // pdf.js worker (local)
  try {
    if (window.pdfjsLib && window.pdfjsLib.GlobalWorkerOptions) {
      window.pdfjsLib.GlobalWorkerOptions.workerSrc =
        "../assets/pdfjs/pdf.worker.min.js";
    }
  } catch (e) {
    console.warn("pdf.js não carregou corretamente:", e);
  }
});

let ap_imagem_id = null; // Variável para armazenar o ID da imagem atual
let currentMediaMode = "image";
let currentVideoTimeMs = null;

// Estado do PDF (arquivo_log) quando em modo PDF
const pdfViewerState = {
  logId: null,
  rawUrl: null,
  doc: null,
  page: 1,
  pages: 0,
  title: "PDF",
};

// Cache para comentários de PDF (evita buscar por página)
const pdfCommentsCache = {
  logId: null,
  comentarios: null,
  fetchedAt: 0,
};

// Quando um comentário de outra página for clicado, guardamos aqui para focar após render
let pendingPdfFocusCommentId = null;

// Para o botão de download funcionar tanto para JPG quanto para PDF.
let currentDownloadUrl = null;

async function renderizarPaginaPdf() {
  const imageWrapper = document.getElementById("image_wrapper");
  const canvas = document.getElementById("pdf_canvas");
  const canvasWrap = document.getElementById("pdf_canvas_wrap");
  const pageLayer = document.getElementById("pdf_page_layer");
  const pageLabel = document.getElementById("pdf_page_label");
  const btnPrev = document.getElementById("pdf_prev_page");
  const btnNext = document.getElementById("pdf_next_page");

  if (!canvas || !canvasWrap || !pdfViewerState.doc) return;

  try {
    const page = await pdfViewerState.doc.getPage(pdfViewerState.page);
    const viewport1 = page.getViewport({ scale: 1 });

    // Largura útil do container (desconta padding do canvasWrap)
    let wrapWidth =
      canvasWrap && canvasWrap.getBoundingClientRect
        ? canvasWrap.getBoundingClientRect().width
        : canvasWrap?.clientWidth || imageWrapper?.clientWidth || 800;

    if (canvasWrap) {
      const cs = window.getComputedStyle(canvasWrap);
      const padL = parseFloat(cs.paddingLeft || "0") || 0;
      const padR = parseFloat(cs.paddingRight || "0") || 0;
      wrapWidth = wrapWidth - padL - padR;
    }

    const availableWidth = Math.max(320, wrapWidth || 800);
    const scale = availableWidth / viewport1.width;
    const viewport = page.getViewport({ scale });
    const outputScale = window.devicePixelRatio || 1;

    canvas.width = Math.floor(viewport.width * outputScale);
    canvas.height = Math.floor(viewport.height * outputScale);
    canvas.style.width = `${viewport.width}px`;
    canvas.style.height = `${viewport.height}px`;

    if (pageLayer) {
      pageLayer.style.width = canvas.style.width;
      pageLayer.style.height = canvas.style.height;
    }

    const ctx = canvas.getContext("2d");
    ctx.setTransform(outputScale, 0, 0, outputScale, 0, 0);

    await page.render({ canvasContext: ctx, viewport }).promise;

    if (pageLabel)
      pageLabel.textContent = `Página ${pdfViewerState.page}/${pdfViewerState.pages || "?"}`;
    if (btnPrev) btnPrev.disabled = pdfViewerState.page <= 1;
    if (btnNext)
      btnNext.disabled = pdfViewerState.pages
        ? pdfViewerState.page >= pdfViewerState.pages
        : false;
  } catch (e) {
    console.error("Erro ao renderizar PDF:", e);
  }

  if (pdfViewerState.logId) {
    const focusId = pendingPdfFocusCommentId;
    pendingPdfFocusCommentId = null;
    renderComments({
      arquivo_log_id: pdfViewerState.logId,
      pagina: pdfViewerState.page,
      focus_comment_id: focusId,
    });
  }
}

async function carregarPdf(rawUrl) {
  if (!window.pdfjsLib) {
    console.error("pdf.js não está disponível (window.pdfjsLib)");
    _vplFecharLoadingPdf();
    return;
  }

  pdfViewerState.doc = null;
  pdfViewerState.pages = 0;
  pdfViewerState.page = 1;

  try {
    const loadingTask = window.pdfjsLib.getDocument({
      url: rawUrl,
      disableRange: false,
      disableStream: false,
      rangeChunkSize: 65536,
    });
    pdfViewerState.doc = await loadingTask.promise;
    pdfViewerState.pages = pdfViewerState.doc.numPages || 0;
    pdfViewerState.page = 1;
    await renderizarPaginaPdf();
  } catch (e) {
    console.error("Erro ao carregar PDF:", e);
  } finally {
    _vplFecharLoadingPdf();
  }
}

function _vplFecharLoadingPdf() {
  if (window._vplPdfTicker) {
    clearInterval(window._vplPdfTicker);
    window._vplPdfTicker = null;
  }
  const bar = document.getElementById("vpl-pdf-bar");
  if (bar) bar.style.width = "100%";
  setTimeout(() => {
    if (window.Swal) Swal.close();
  }, 300);
}

function mostrarPdfCompleto(
  rawUrl,
  downloadUrl,
  titulo = "PDF",
  arquivoLogId = null,
) {
  ap_imagem_id = null;
  currentMediaMode = "pdf";
  currentVideoTimeMs = null;
  currentDownloadUrl = downloadUrl || rawUrl || null;

  pdfViewerState.logId = arquivoLogId ? String(arquivoLogId) : null;
  pdfViewerState.rawUrl = rawUrl;
  pdfViewerState.title = titulo || "PDF";
  pdfViewerState.page = 1;

  const imageWrapper = document.getElementById("image_wrapper");
  const sidebar = document.querySelector(".sidebar-direita");
  const imagem_completa = document.getElementById("imagem_completa");
  if (sidebar) {
    sidebar.classList.remove("collapsed");
    sidebar.style.display = "flex";
    const rightIcon = document.querySelector("#right-collapse-btn i");
    if (rightIcon) rightIcon.className = "fa-solid fa-chevron-right";
  }
  const wrapperSb = document.querySelector(".wrapper-sidebar");
  if (wrapperSb) {
    wrapperSb.classList.remove("collapsed");
    const leftIcon = document.querySelector("#left-collapse-btn i");
    if (leftIcon) leftIcon.className = "fa-solid fa-chevron-left";
  }

  if (imageWrapper) {
    imageWrapper.querySelectorAll(".comment").forEach((c) => c.remove());
    while (imageWrapper.firstChild)
      imageWrapper.removeChild(imageWrapper.firstChild);

    imagem_completa.style.width = "90%";

    imageWrapper.classList.remove("video-mode");
    imageWrapper.classList.add("pdf-mode");

    const toolbar = document.createElement("div");
    toolbar.className = "pdf-toolbar";

    const titleEl = document.createElement("div");
    titleEl.className = "pdf-title";
    titleEl.textContent = pdfViewerState.title;

    const controls = document.createElement("div");
    controls.className = "pdf-controls";

    const btnPrev = document.createElement("button");
    btnPrev.id = "pdf_prev_page";
    btnPrev.type = "button";
    btnPrev.textContent = "◀";

    const label = document.createElement("span");
    label.id = "pdf_page_label";
    label.textContent = "Página -/-";

    const btnNext = document.createElement("button");
    btnNext.id = "pdf_next_page";
    btnNext.type = "button";
    btnNext.textContent = "▶";

    controls.appendChild(btnPrev);
    controls.appendChild(label);
    controls.appendChild(btnNext);

    toolbar.appendChild(titleEl);
    toolbar.appendChild(controls);

    const wrap = document.createElement("div");
    wrap.id = "pdf_canvas_wrap";
    wrap.className = "pdf-canvas-wrap";

    const pageLayer = document.createElement("div");
    pageLayer.id = "pdf_page_layer";
    pageLayer.className = "pdf-page-layer";

    const canvas = document.createElement("canvas");
    canvas.id = "pdf_canvas";
    canvas.className = "pdf-canvas";

    const overlay = document.createElement("div");
    overlay.id = "pdf_comment_layer";
    overlay.className = "pdf-comment-layer";

    pageLayer.appendChild(canvas);
    pageLayer.appendChild(overlay);
    wrap.appendChild(pageLayer);

    imageWrapper.appendChild(toolbar);
    imageWrapper.appendChild(wrap);

    // Clique no PDF para criar comentário (ponto)
    pageLayer.addEventListener("click", function (event) {
      if (dragMoved) return;
      if (drawingTool !== "ponto") return; // formas tratadas por mousedown
      if (_replyingToCommentId !== null) return; // bloqueia novo comentário enquanto resposta está ativa
      if (!pdfViewerState.logId) return;

      const rect = canvas.getBoundingClientRect();
      if (!rect.width || !rect.height) return;

      relativeX = ((event.clientX - rect.left) / rect.width) * 100;
      relativeY = ((event.clientY - rect.top) / rect.height) * 100;

      _editingCommentId = null;
      if (quillComentario) quillComentario.setContents([]);
      const _mtPdf = document.querySelector("#comentarioModal h3");
      if (_mtPdf) _mtPdf.textContent = "Novo Comentário";
      document.getElementById("imagemComentario").value = "";

      showCommentPreview();
      openCommentModalAtPoint(event.clientX, event.clientY);
      mencionadosIds = [];
    });

    // Inicia desenho de forma no PDF
    pageLayer.addEventListener("mousedown", function (event) {
      if (event.button !== 0 || event.ctrlKey) return;
      if (drawingTool === "ponto") return;
      if (!pdfViewerState.logId) return;
      event.stopPropagation();
      isDrawing = true;
      dragMoved = false;
      const rect = canvas.getBoundingClientRect();
      if (!rect.width || !rect.height) return;
      drawStartX = ((event.clientX - rect.left) / rect.width) * 100;
      drawStartY = ((event.clientY - rect.top) / rect.height) * 100;
      drawStartClientX = event.clientX;
      drawStartClientY = event.clientY;
      shapeX2 = drawStartX;
      shapeY2 = drawStartY;
      currentDrawRef = canvas;
      const container =
        document.getElementById("pdf_comment_layer") || pageLayer;
      if (drawingTool === "freehand") {
        freehandPoints = [[drawStartX, drawStartY]];
        const svg = createFreehandPreviewSvg(drawStartX, drawStartY);
        container.appendChild(svg);
        freehandSvgPreview = svg;
        freehandPolylineEl = svg.querySelector("polyline");
        freehandDrawContainer = container;
      } else {
        const preview = document.createElement("div");
        preview.id = "drawing-preview";
        preview.className = `drawing-preview drawing-preview-${drawingTool}`;
        preview.style.left = `${drawStartX}%`;
        preview.style.top = `${drawStartY}%`;
        preview.style.width = "0";
        preview.style.height = "0";
        container.appendChild(preview);
      }
    });

    btnPrev.addEventListener("click", async () => {
      if (!pdfViewerState.doc) return;
      if (pdfViewerState.page <= 1) return;
      pdfViewerState.page -= 1;
      await renderizarPaginaPdf();
    });

    btnNext.addEventListener("click", async () => {
      if (!pdfViewerState.doc) return;
      if (pdfViewerState.pages && pdfViewerState.page >= pdfViewerState.pages)
        return;
      pdfViewerState.page += 1;
      await renderizarPaginaPdf();
    });
  }

  // Overlay de carregamento (SweetAlert2) — fecha quando carregarPdf concluir
  if (window.Swal) {
    let _vplProgress = 0;
    Swal.fire({
      title: "PDF sendo carregado…",
      html: `<p style="margin:0;color:#888">Buscando arquivo no servidor. Pode levar alguns instantes.</p>
             <div style="margin-top:14px;height:6px;border-radius:3px;background:#eee;overflow:hidden">
               <div id="vpl-pdf-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#2563eb,#06b6d4);transition:width .4s ease"></div>
             </div>`,
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => {
        window._vplPdfTicker = setInterval(() => {
          _vplProgress += Math.ceil(Math.random() * 6);
          if (_vplProgress > 88) _vplProgress = 88;
          const bar = document.getElementById("vpl-pdf-bar");
          if (bar) bar.style.width = _vplProgress + "%";
        }, 350);
      },
    });
  }

  // Carrega e renderiza o PDF
  carregarPdf(rawUrl);

  // Tenta renderizar de novo no resize (ex: sidebar abre/fecha)
  window.setTimeout(() => renderizarPaginaPdf(), 150);
}

// Mostra imagem e abre modal
function mostrarImagemCompleta(src, id) {
  closeCommentPopup();
  activeComparisonCommentViewer = null;
  commentPreviewContainer = null;
  ap_imagem_id = id;
  currentMediaMode = "image";
  currentVideoTimeMs = null;
  currentDownloadUrl = src || null;

  // Sai do modo PDF
  pdfViewerState.logId = null;
  pdfViewerState.rawUrl = null;
  pdfViewerState.doc = null;
  pdfViewerState.page = 1;
  pdfViewerState.pages = 0;

  const imageWrapper = document.getElementById("image_wrapper");

  // Garante que ambas as sidebars estejam expandidas ao trocar de imagem
  const sidebar = document.querySelector(".sidebar-direita");
  if (sidebar) {
    sidebar.classList.remove("collapsed");
    sidebar.style.display = "flex";
    const rightIcon = document.querySelector("#right-collapse-btn i");
    if (rightIcon) rightIcon.className = "fa-solid fa-chevron-right";
  }
  const wrapperSb = document.querySelector(".wrapper-sidebar");
  if (wrapperSb) {
    wrapperSb.classList.remove("collapsed");
    const leftIcon = document.querySelector("#left-collapse-btn i");
    if (leftIcon) leftIcon.className = "fa-solid fa-chevron-left";
  }

  imageWrapper.classList.remove("pdf-mode", "video-mode");

  while (imageWrapper.firstChild) {
    imageWrapper.removeChild(imageWrapper.firstChild);
  }

  const imgElement = document.createElement("img");
  imgElement.id = "imagem_atual";
  imgElement.src = src;
  imgElement.style.width = "100%";

  imageWrapper.appendChild(imgElement);
  // document
  //   .querySelector("#imagem_atual")
  //   .scrollIntoView({ behavior: "smooth" });
  renderComments(id);
  ajustarNavSelectAoTamanhoDaImagem();

  // imgElement.addEventListener("contextmenu", (event) => {
  //   event.preventDefault();
  //   document.getElementById("menuContexto").style.display = "none";
  //   abrirMenuContextoImagem(event.pageX, event.pageY);
  // });

  imgElement.addEventListener("click", function (event) {
    if (dragMoved) return;
    if (drawingTool !== "ponto") return; // formas são tratadas por mousedown
    if (_replyingToCommentId !== null) return; // bloqueia novo comentário enquanto resposta está ativa
    // if (![1, 2, 9, 20, 3].includes(idusuario)) return;

    const rect = imgElement.getBoundingClientRect();
    relativeX = ((event.clientX - rect.left) / rect.width) * 100;
    relativeY = ((event.clientY - rect.top) / rect.height) * 100;

    _editingCommentId = null;
    if (quillComentario) quillComentario.setContents([]);
    const _mtImg = document.querySelector("#comentarioModal h3");
    if (_mtImg) _mtImg.textContent = "Novo Comentário";
    document.getElementById("imagemComentario").value = "";

    showCommentPreview();
    openCommentModalAtPoint(event.clientX, event.clientY);

    // Limpa os mencionados quando abre um novo comentário
    mencionadosIds = [];
  });

  // Inicia desenho de forma geométrica na imagem JPG
  imgElement.addEventListener("mousedown", function (event) {
    if (event.button !== 0 || event.ctrlKey) return;
    if (drawingTool === "ponto") return;
    event.stopPropagation();
    isDrawing = true;
    dragMoved = false;
    const rect = imgElement.getBoundingClientRect();
    drawStartX = ((event.clientX - rect.left) / rect.width) * 100;
    drawStartY = ((event.clientY - rect.top) / rect.height) * 100;
    drawStartClientX = event.clientX;
    drawStartClientY = event.clientY;
    shapeX2 = drawStartX;
    shapeY2 = drawStartY;
    currentDrawRef = imgElement;
    if (drawingTool === "freehand") {
      freehandPoints = [[drawStartX, drawStartY]];
      const svg = createFreehandPreviewSvg(drawStartX, drawStartY);
      imageWrapper.appendChild(svg);
      freehandSvgPreview = svg;
      freehandPolylineEl = svg.querySelector("polyline");
      freehandDrawContainer = imageWrapper;
    } else {
      const preview = document.createElement("div");
      preview.id = "drawing-preview";
      preview.className = `drawing-preview drawing-preview-${drawingTool}`;
      preview.style.left = `${drawStartX}%`;
      preview.style.top = `${drawStartY}%`;
      preview.style.width = "0";
      preview.style.height = "0";
      imageWrapper.appendChild(preview);
    }
  });

  // support pointer events (touch / pen) for image drawing
  imgElement.addEventListener("pointerdown", function (event) {
    if (event.pointerType === "mouse" && event.button !== 0) return;
    if (event.ctrlKey) return;
    if (drawingTool === "ponto") return;
    event.stopPropagation();
    isDrawing = true;
    dragMoved = false;
    const rect = imgElement.getBoundingClientRect();
    drawStartX = ((event.clientX - rect.left) / rect.width) * 100;
    drawStartY = ((event.clientY - rect.top) / rect.height) * 100;
    drawStartClientX = event.clientX;
    drawStartClientY = event.clientY;
    shapeX2 = drawStartX;
    shapeY2 = drawStartY;
    currentDrawRef = imgElement;
    if (drawingTool === "freehand") {
      freehandPoints = [[drawStartX, drawStartY]];
      const svg = createFreehandPreviewSvg(drawStartX, drawStartY);
      imageWrapper.appendChild(svg);
      freehandSvgPreview = svg;
      freehandPolylineEl = svg.querySelector("polyline");
      freehandDrawContainer = imageWrapper;
    } else {
      const preview = document.createElement("div");
      preview.id = "drawing-preview";
      preview.className = `drawing-preview drawing-preview-${drawingTool}`;
      preview.style.left = `${drawStartX}%`;
      preview.style.top = `${drawStartY}%`;
      preview.style.width = "0";
      preview.style.height = "0";
      imageWrapper.appendChild(preview);
    }
  });
}

function startDrawingOnReviewElement(event, refElement, containerElement) {
  if (event.button !== undefined && event.button !== 0) return;
  if (event.ctrlKey) return;
  if (drawingTool === "ponto") return;
  event.stopPropagation();

  isDrawing = true;
  dragMoved = false;
  const rect = refElement.getBoundingClientRect();
  if (!rect.width || !rect.height) return;

  drawStartX = ((event.clientX - rect.left) / rect.width) * 100;
  drawStartY = ((event.clientY - rect.top) / rect.height) * 100;
  drawStartClientX = event.clientX;
  drawStartClientY = event.clientY;
  shapeX2 = drawStartX;
  shapeY2 = drawStartY;
  currentDrawRef = refElement;

  if (currentMediaMode === "video" && refElement.currentTime !== undefined) {
    currentVideoTimeMs = Math.max(
      0,
      Math.round((refElement.currentTime || 0) * 1000),
    );
  }

  if (drawingTool === "freehand") {
    freehandPoints = [[drawStartX, drawStartY]];
    const svg = createFreehandPreviewSvg(drawStartX, drawStartY);
    containerElement.appendChild(svg);
    freehandSvgPreview = svg;
    freehandPolylineEl = svg.querySelector("polyline");
    freehandDrawContainer = containerElement;
  } else {
    const preview = document.createElement("div");
    preview.id = "drawing-preview";
    preview.className = `drawing-preview drawing-preview-${drawingTool}`;
    preview.style.left = `${drawStartX}%`;
    preview.style.top = `${drawStartY}%`;
    preview.style.width = "0";
    preview.style.height = "0";
    containerElement.appendChild(preview);
  }
}

function mostrarVideoCompleto(src, id, media = {}) {
  closeCommentPopup();
  ap_imagem_id = id;
  currentMediaMode = "video";
  currentVideoTimeMs = null;
  currentDownloadUrl = src || null;

  pdfViewerState.logId = null;
  pdfViewerState.rawUrl = null;
  pdfViewerState.doc = null;
  pdfViewerState.page = 1;
  pdfViewerState.pages = 0;

  const imageWrapper = document.getElementById("image_wrapper");

  const sidebar = document.querySelector(".sidebar-direita");
  if (sidebar) {
    sidebar.classList.remove("collapsed");
    sidebar.style.display = "flex";
    const rightIcon = document.querySelector("#right-collapse-btn i");
    if (rightIcon) rightIcon.className = "fa-solid fa-chevron-right";
  }
  const wrapperSb = document.querySelector(".wrapper-sidebar");
  if (wrapperSb) {
    wrapperSb.classList.remove("collapsed");
    const leftIcon = document.querySelector("#left-collapse-btn i");
    if (leftIcon) leftIcon.className = "fa-solid fa-chevron-left";
  }

  imageWrapper.classList.remove("pdf-mode");
  imageWrapper.classList.add("video-mode");
  while (imageWrapper.firstChild) {
    imageWrapper.removeChild(imageWrapper.firstChild);
  }

  const generalBtn = document.createElement("button");
  generalBtn.type = "button";
  generalBtn.className = "video-general-comment-btn";
  generalBtn.textContent = "Comentário geral";
  generalBtn.addEventListener("click", (event) => {
    event.stopPropagation();
    relativeX = null;
    relativeY = null;
    currentVideoTimeMs = null;
    _editingCommentId = null;
    if (quillComentario) quillComentario.setContents([]);
    const modalTitle = document.querySelector("#comentarioModal h3");
    if (modalTitle) modalTitle.textContent = "Novo Comentário";
    document.getElementById("imagemComentario").value = "";
    openCommentModalAtPoint(event.clientX, event.clientY);
    mencionadosIds = [];
  });

  const videoElement = document.createElement("video");
  videoElement.id = "video_atual";
  videoElement.className = "approval-video";
  videoElement.src = src;
  videoElement.controls = true;
  videoElement.playsInline = true;
  videoElement.preload = "metadata";
  if (media.poster_path) {
    videoElement.poster = `https://improov.com.br/flow/ImproovWeb/${media.poster_path}`;
  }

  imageWrapper.appendChild(generalBtn);
  imageWrapper.appendChild(videoElement);

  renderComments(id);
  ajustarNavSelectAoTamanhoDaImagem();

  videoElement.addEventListener("click", function (event) {
    if (dragMoved) return;
    if (drawingTool !== "ponto") return;
    if (_replyingToCommentId !== null) return;

    const rect = videoElement.getBoundingClientRect();
    if (!rect.width || !rect.height) return;

    relativeX = ((event.clientX - rect.left) / rect.width) * 100;
    relativeY = ((event.clientY - rect.top) / rect.height) * 100;
    currentVideoTimeMs = Math.max(
      0,
      Math.round((videoElement.currentTime || 0) * 1000),
    );

    _editingCommentId = null;
    if (quillComentario) quillComentario.setContents([]);
    const modalTitle = document.querySelector("#comentarioModal h3");
    if (modalTitle)
      modalTitle.textContent = `Novo Comentário (${formatVideoTime(currentVideoTimeMs)})`;
    document.getElementById("imagemComentario").value = "";

    showCommentPreview();
    openCommentModalAtPoint(event.clientX, event.clientY);
    mencionadosIds = [];
  });

  videoElement.addEventListener("mousedown", function (event) {
    startDrawingOnReviewElement(event, videoElement, imageWrapper);
  });

  videoElement.addEventListener("pointerdown", function (event) {
    if (event.pointerType === "mouse" && event.button !== 0) return;
    startDrawingOnReviewElement(event, videoElement, imageWrapper);
  });
}

function ajustarNavSelectAoTamanhoDaImagem() {
  // On mobile/tablet (≤1024px) let CSS handle the nav-select width — setting
  // an inline pixel value would cause the toolbar to overflow the viewport.
  if (window.innerWidth <= 1024) return;

  const img = document.getElementById("imagem_atual");
  const navSelect = document.querySelector(".nav-select");
  if (img && navSelect) {
    const doAjuste = () => {
      const header = document.querySelector(".container-aprovacao header");
      const headerH = header ? header.offsetHeight : 74;
      const navH = navSelect.offsetHeight || 40;
      const maxH = window.innerHeight - headerH - navH - 20;
      img.style.maxHeight = maxH + "px";
      img.style.maxWidth = "100%";
      img.style.width = "auto";
      img.style.height = "auto";
      requestAnimationFrame(() => {
        const cont = document.getElementById("imagem_completa");
        if (cont)
          navSelect.style.width = cont.getBoundingClientRect().width + "px";
      });
    };
    img.onload = doAjuste;
    if (img.complete) doAjuste();
  }
}

const btnDownload = document.getElementById("btn-download-imagem");
if (btnDownload) {
  btnDownload.addEventListener("click", async function () {
    const url =
      currentDownloadUrl || document.getElementById("imagem_atual")?.src || "";
    if (!url) return;

    const isIOS = /iP(ad|hone|od)/.test(navigator.userAgent);

    // iOS Safari: download attribute is not supported — open in new tab instead
    if (isIOS) {
      window.open(url, "_blank");
      return;
    }

    try {
      // Try fetching the resource as a blob (works reliably in Safari desktop)
      const resp = await fetch(url, { mode: "cors" });
      const blob = await resp.blob();
      const blobUrl = URL.createObjectURL(blob);

      const link = document.createElement("a");
      link.href = blobUrl;
      link.download = url.split("/").pop() || "download";
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      // Revoke object URL shortly after
      setTimeout(() => URL.revokeObjectURL(blobUrl), 1500);
    } catch (err) {
      // Fallback: open in a new tab (e.g., when CORS blocks fetch)
      const link = document.createElement("a");
      link.href = url;
      link.target = "_blank";
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  });
}

// Capturar colagem de imagem no campo de texto (fallback sem Quill)
// Quill handles paste natively; only attach for non-Quill environments
if (!window.Quill) {
  document
    .getElementById("comentarioTexto")
    .addEventListener("paste", function (event) {
      const items = (event.clipboardData || event.originalEvent.clipboardData)
        .items;

      for (let index in items) {
        const item = items[index];
        if (item.kind === "file") {
          const blob = item.getAsFile();
          if (blob && blob.type.startsWith("image/")) {
            const fileInput = document.getElementById("imagemComentario");

            // Cria um objeto DataTransfer para injetar o arquivo no input
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(
              new File([blob], "imagem_colada.png", { type: blob.type }),
            );

            fileInput.files = dataTransfer.files;

            Toastify({
              text: "Imagem colada com sucesso!",
              duration: 3000,
              backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
              close: true,
              gravity: "top",
              position: "right",
            }).showToast();
          }
        }
      }
    });
}

// Capturar colagem de imagem dentro do Quill
// Função para enviar o comentário
document.getElementById("enviarComentario").onclick = async () => {
  const texto = quillComentario
    ? quillComentario.root.innerHTML.trim()
    : document.getElementById("comentarioTexto").value.trim();
  const textoVazio = !texto || texto === "<p><br></p>";
  const imagemFile = document.getElementById("imagemComentario").files[0];

  if (textoVazio && !imagemFile) {
    Toastify({
      text: "Escreva um comentário ou anexe uma imagem!",
      duration: 3000,
      backgroundColor: "orange",
      close: true,
      gravity: "top",
      position: "right",
    }).showToast();
    return;
  }

  // ── Modo resposta ─────────────────────────────────────────────────────────
  if (_replyingToCommentId !== null) {
    const replyId = _replyingToCommentId;
    _replyingToCommentId = null;
    document.getElementById("comentarioModal").style.display = "none";
    const replyCtx = document.getElementById("reply-context");
    if (replyCtx) {
      replyCtx.style.display = "none";
      replyCtx.innerHTML = "";
    }
    if (quillComentario) quillComentario.setContents([]);
    const modalTitle = document.querySelector("#comentarioModal h3");
    if (modalTitle) modalTitle.textContent = "Novo Comentário";
    const mencionadosResposta = [...mencionadosIds];
    mencionadosIds = [];

    const respostaSalva = await salvarResposta(
      replyId,
      textoVazio ? "" : texto,
      imagemFile || null,
      mencionadosResposta,
    );
    if (respostaSalva) {
      adicionarRespostaDOM(replyId, respostaSalva);
    }
    return;
  }

  // ── Modo edição de resposta ────────────────────────────────────────────────
  if (_editingReplyId !== null) {
    const replyId = _editingReplyId;
    _editingReplyId = null;
    document.getElementById("comentarioModal").style.display = "none";
    if (quillComentario) quillComentario.setContents([]);
    const modalTitle = document.querySelector("#comentarioModal h3");
    if (modalTitle) modalTitle.textContent = "Novo Comentário";
    const mencionadosEditReply = [...mencionadosIds];
    mencionadosIds = [];

    const result = await updateReply(
      replyId,
      textoVazio ? "" : texto,
      imagemFile || null,
      mencionadosEditReply,
    );
    if (result?.sucesso) {
      const replyEl = document.querySelector(
        `.resposta[data-reply-id="${replyId}"]`,
      );
      if (replyEl) {
        const textoEl = replyEl.querySelector(".resposta-texto");
        if (textoEl)
          textoEl.innerHTML = highlightMentions(textoVazio ? "" : texto);
        if (result.imagem) {
          const thumb = `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(result.imagem)}&w=200&q=85`;
          let imgDiv = replyEl.querySelector(".comment-image");
          if (!imgDiv) {
            imgDiv = document.createElement("div");
            imgDiv.classList.add("comment-image");
            replyEl
              .querySelector(".resposta-nome")
              .insertAdjacentElement("afterend", imgDiv);
          }
          imgDiv.innerHTML = `<img src="${thumb}" class="comment-img-thumb" onclick="abrirImagemModal('${result.imagem}')">`;
        }
      }
    }
    return;
  }

  // ── Modo edição ──────────────────────────────────────────────────────────
  if (_editingCommentId !== null) {
    const editId = _editingCommentId;
    _editingCommentId = null;
    document.getElementById("comentarioModal").style.display = "none";
    removeCommentPreview();
    if (quillComentario) quillComentario.setContents([]);
    const modalTitle = document.querySelector("#comentarioModal h3");
    if (modalTitle) modalTitle.textContent = "Novo Comentário";
    const mencionadosEditComment = [...mencionadosIds];
    mencionadosIds = [];

    await updateComment(
      editId,
      textoVazio ? "" : texto,
      imagemFile || null,
      mencionadosEditComment,
    );

    if (pdfViewerState.logId) {
      pdfCommentsCache.logId = null;
      pdfCommentsCache.comentarios = null;
      renderComments({
        arquivo_log_id: pdfViewerState.logId,
        pagina: pdfViewerState.page,
      });
    } else {
      refreshCurrentCommentTarget();
    }
    return;
  }

  // ── Novo comentário ──────────────────────────────────────────────────────
  const formData = new FormData();
  if (pdfViewerState.logId) {
    formData.append("arquivo_log_id", pdfViewerState.logId);
    formData.append("pagina", String(pdfViewerState.page || 1));
  } else {
    formData.append("ap_imagem_id", ap_imagem_id);
  }
  if (relativeX !== null && relativeX !== undefined && relativeX !== "") {
    formData.append("x", relativeX);
  }
  if (relativeY !== null && relativeY !== undefined && relativeY !== "") {
    formData.append("y", relativeY);
  }
  if (currentMediaMode === "video" && currentVideoTimeMs !== null) {
    formData.append("video_time_ms", String(currentVideoTimeMs));
  }
  formData.append("tipo", drawingTool);
  formData.append("cor", drawingColor);
  if (drawingTool === "freehand") {
    formData.append("path_data", JSON.stringify(freehandPoints));
  } else if (drawingTool !== "ponto") {
    formData.append("x2", shapeX2);
    formData.append("y2", shapeY2);
  }
  formData.append("texto", textoVazio ? "" : texto);
  formData.append("mencionados", JSON.stringify(mencionadosIds));

  if (imagemFile) {
    formData.append("imagem", imagemFile);
  }

  try {
    const response = await fetch("salvar_comentario.php", {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    document.getElementById("comentarioModal").style.display = "none";
    removeCommentPreview();
    if (quillComentario) quillComentario.setContents([]);

    if (result.sucesso) {
      Toastify({
        text: "Comentário adicionado com sucesso!",
        duration: 3000,
        backgroundColor: "green",
        close: true,
        gravity: "top",
        position: "left",
      }).showToast();

      // Atualiza comentários
      if (pdfViewerState.logId) {
        // Invalida cache e recarrega comentários do PDF (todos)
        if (pdfViewerState.logId) {
          pdfCommentsCache.logId = null;
          pdfCommentsCache.comentarios = null;
        }
        renderComments({
          arquivo_log_id: pdfViewerState.logId,
          pagina: pdfViewerState.page,
        });
      } else {
        refreshCurrentCommentTarget();
      }
    } else {
      Toastify({
        text: result.mensagem || "Erro ao salvar comentário!",
        duration: 3000,
        backgroundColor: "red",
        close: true,
        gravity: "top",
        position: "left",
      }).showToast();
    }

    // Limpa os mencionados depois do envio
    mencionadosIds = [];
  } catch (error) {
    console.error("Erro na requisição:", error);
    Toastify({
      text: "Erro de conexão! Tente novamente.",
      duration: 3000,
      backgroundColor: "red",
      close: true,
      gravity: "top",
      position: "left",
    }).showToast();
  }
};

function addComment(x, y) {
  const imagemCompletaDiv = document.getElementById("imagem_completa");

  // Cria o div do comentário
  const commentDiv = document.createElement("div");
  commentDiv.classList.add("comment");
  commentDiv.style.left = `${x}%`;
  commentDiv.style.top = `${y}%`;

  imagemCompletaDiv.appendChild(commentDiv);
}

const image = document.getElementById("imagem_atual");

// ---- CONFIGURAÇÃO ---------------------------------------------------------
const USERS_PERMITIDOS = [1, 2, 3, 9, 20]; // quem pode editar / excluir
// --------------------------------------------------------------------------

// ---- Ferramenta de desenho (formas geométricas) --------------------------
let drawingTool = "ponto"; // 'ponto' | 'rect' | 'circle' | 'freehand'
let drawingColor =
  window._initialDrawingColor &&
  /^#[0-9a-fA-F]{6}$/.test(window._initialDrawingColor)
    ? window._initialDrawingColor
    : "#000000"; // cor selecionada pelo usuário
let isDrawing = false;
let drawStartX = 0; // % relativo ao elemento de referência
let drawStartY = 0;
let drawStartClientX = 0; // px (para calcular delta)
let drawStartClientY = 0;
let shapeX2 = 0; // coordenada final em %
let shapeY2 = 0;
let currentDrawRef = null; // elemento usado para calcular coords (img ou canvas)
// Freehand especial
let freehandPoints = []; // [[x%,y%], ...]
let freehandSvgPreview = null;
let freehandPolylineEl = null;
let freehandDrawContainer = null;
// ---------------------------------------------------------------------------

function createFreehandPreviewSvg(startX, startY) {
  const svgNS = "http://www.w3.org/2000/svg";
  const svg = document.createElementNS(svgNS, "svg");
  svg.id = "drawing-preview";
  svg.setAttribute("viewBox", "0 0 100 100");
  svg.setAttribute("preserveAspectRatio", "none");
  svg.setAttribute("pointer-events", "none");
  svg.style.cssText =
    "position:absolute;top:0;left:0;width:100%;height:100%;overflow:visible;z-index:850;";
  const poly = document.createElementNS(svgNS, "polyline");
  poly.setAttribute("points", `${startX},${startY}`);
  poly.setAttribute("fill", "none");
  poly.setAttribute("stroke", drawingColor);
  poly.setAttribute("stroke-width", "0.6");
  poly.setAttribute("stroke-linecap", "round");
  poly.setAttribute("stroke-linejoin", "round");
  svg.appendChild(poly);
  return svg;
}

// ---- Atualiza badge de comentários na thumbnail da nav lateral --------------
function _atualizarBadgeImagem(apImagemId, total, pendentes) {
  const imgEl = document.querySelector(
    `#imagens .imageWrapper [data-id="${apImagemId}"]`,
  );
  if (!imgEl) return;
  const wrapper = imgEl.closest(".imageWrapper");
  if (!wrapper) return;

  let dot = wrapper.querySelector(".notification-dot");
  if (total === 0) {
    if (dot) dot.remove();
    return;
  }
  if (!dot) {
    dot = document.createElement("div");
    wrapper.appendChild(dot);
  }
  const todosOk = pendentes === 0;
  dot.className = "notification-dot" + (todosOk ? " notification-dot--ok" : "");
  dot.textContent = todosOk ? "✓" : String(pendentes);
  dot.title = todosOk
    ? "Todos os comentários concluídos"
    : `${pendentes} comentário(s) pendente(s)`;
}
// ---------------------------------------------------------------------------

// ---- Comment inline popup (shown when clicking a .comment marker) ---------
let activeCommentPopup = null;

function closeCommentPopup() {
  if (activeCommentPopup) {
    activeCommentPopup.remove();
    activeCommentPopup = null;
  }
}

function showCommentPopup(markerEl, comentarioId) {
  closeCommentPopup();

  const card = document.querySelector(
    `.comment-card[data-id="${comentarioId}"]`,
  );
  if (!card) return;

  const popup = document.createElement("div");
  popup.className = "comment-popup";
  popup.setAttribute("data-popup-id", String(comentarioId));

  // Close button
  const closeBtn = document.createElement("button");
  closeBtn.className = "comment-popup-close";
  closeBtn.innerHTML = "&#x2715;"; // ✕
  closeBtn.title = "Fechar";
  closeBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    closeCommentPopup();
    document
      .querySelectorAll(".comment-number")
      .forEach((n) => n.classList.remove("highlight"));
    document
      .querySelectorAll(
        ".comment.highlight, .comment-shape.highlight, .comment-freehand.highlight",
      )
      .forEach((n) => n.classList.remove("highlight"));
  });
  popup.appendChild(closeBtn);

  // Clone the card content
  const clone = card.cloneNode(true);
  // Remove interactive action buttons from clone to keep it read-only
  clone
    .querySelectorAll(".comment-resp, .comment-edit, .comment-delete")
    .forEach((btn) => btn.remove());
  popup.appendChild(clone);

  document.body.appendChild(popup);
  activeCommentPopup = popup;

  // Position relative to marker (fixed coordinates)
  const mr = markerEl.getBoundingClientRect();
  const pr = popup.getBoundingClientRect();
  const gap = 14;

  const spaceRight = window.innerWidth - mr.right - gap;
  const spaceLeft = mr.left - gap;

  let left, arrowClass;
  if (spaceRight >= pr.width || spaceRight >= spaceLeft) {
    left = mr.right + gap;
    arrowClass = "popup-right";
  } else {
    left = mr.left - gap - pr.width;
    arrowClass = "popup-left";
  }

  let top = mr.top + mr.height / 2 - pr.height / 2;
  top = Math.max(8, Math.min(top, window.innerHeight - pr.height - 8));

  popup.classList.add(arrowClass);
  popup.style.left = Math.round(left) + "px";
  popup.style.top = Math.round(top) + "px";
}

// Close popup on click outside
document.addEventListener("click", (e) => {
  if (
    activeCommentPopup &&
    !activeCommentPopup.contains(e.target) &&
    !e.target.classList.contains("comment")
  ) {
    closeCommentPopup();
  }
});
// ---------------------------------------------------------------------------

// Destaca menções @nome inline no texto de comentários e respostas.
// Funciona por regex sobre o texto fora de tags HTML.
// Usa a lista de usuários em cache (_cachedUsers) para match exato de nomes
// completos; cai para @palavra se a lista ainda não estiver disponível.
function highlightMentions(html) {
  if (!html) return html;
  const names = _cachedUsers
    .map((u) => u.nome_colaborador)
    .filter(Boolean)
    .sort((a, b) => b.length - a.length); // mais longos primeiro

  return html.replace(/(>|^)([^<]+)/g, (_, tag, text) => {
    let result = text;
    if (names.length > 0) {
      for (const name of names) {
        const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        result = result.replace(
          new RegExp(`@(${escaped})`, "g"),
          '<span class="mention-highlight">@$1</span>',
        );
      }
    } else {
      // Fallback: @PrimeiroNome (letras + acentuados)
      result = result.replace(
        /@([\wÀ-ÿ\u00C0-\u024F]+)/g,
        '<span class="mention-highlight">@$1</span>',
      );
    }
    return tag + result;
  });
}

// ---- Checklist de comentários -----------------------------------------------

async function toggleCommentConcluido(comentarioId, concluido) {
  try {
    const res = await fetch("marcar_comentario_concluido.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        comentario_id: comentarioId,
        concluido: concluido ? 1 : 0,
      }),
    });
    return await res.json();
  } catch (e) {
    console.error("Erro ao marcar comentário:", e);
    return null;
  }
}

function renderCommentProgress(container, comentarios) {
  const total = comentarios.length;
  const concluidos = comentarios.filter(
    (c) => parseInt(c.concluido ?? 0, 10) === 1,
  ).length;
  const pct = total > 0 ? Math.round((concluidos / total) * 100) : 0;
  const todosOk = total > 0 && concluidos === total;

  let bar = container.querySelector(".comment-progress-bar");
  if (!bar) {
    bar = document.createElement("div");
    bar.className = "comment-progress-bar";
    container.prepend(bar);
  }

  bar.innerHTML = `
    <div class="comment-progress-label">
      <span>Ajustes concluídos</span>
      <span class="comment-progress-pct ${todosOk ? "all-done" : ""}">${concluidos}/${total} (${pct}%)</span>
    </div>
    <div class="comment-progress-track">
      <div class="comment-progress-fill ${todosOk ? "all-done" : ""}" style="width:${pct}%"></div>
    </div>
  `;
}

// ---------------------------------------------------------------------------

const commentRenderVersions = new WeakMap();

async function renderComments(id, target = {}) {
  // console.log("renderComments", id); // debug
  const comentariosDiv =
    target.comentariosDiv || document.querySelector(".comentarios");
  const renderVersion = (commentRenderVersions.get(comentariosDiv) || 0) + 1;
  commentRenderVersions.set(comentariosDiv, renderVersion);
  const isCurrentRender = () =>
    commentRenderVersions.get(comentariosDiv) === renderVersion &&
    (!target.isCurrent || target.isCurrent());
  comentariosDiv.innerHTML = "";
  const imagemCompletaDiv =
    target.markerContainer || document.getElementById("image_wrapper");

  const isPdf = typeof id === "object" && id && id.arquivo_log_id;
  const isVideo = !isPdf && (target.mediaMode || currentMediaMode) === "video";
  const markerContainer = isPdf
    ? document.getElementById("pdf_comment_layer") || imagemCompletaDiv
    : target.markerContainer || imagemCompletaDiv;

  // No PDF: não busca por página; busca tudo uma vez e filtra no front
  let comentarios = [];
  if (isPdf) {
    const logId = String(id.arquivo_log_id);
    const shouldFetch =
      !pdfCommentsCache.comentarios || pdfCommentsCache.logId !== logId;

    if (shouldFetch) {
      const urlAll = `buscar_comentarios.php?arquivo_log_id=${encodeURIComponent(logId)}`;
      const response = await fetch(urlAll);
      const all = await response.json();
      if (!isCurrentRender()) return;
      pdfCommentsCache.logId = logId;
      pdfCommentsCache.comentarios = Array.isArray(all) ? all : [];
      pdfCommentsCache.fetchedAt = Date.now();
    }

    comentarios = pdfCommentsCache.comentarios || [];
  } else {
    const url = `buscar_comentarios.php?id=${encodeURIComponent(String(id))}`;
    const response = await fetch(url);
    const data = await response.json();
    if (!isCurrentRender()) return;
    comentarios = Array.isArray(data) ? data : [];
  }

  if (!isPdf) {
    const total = comentarios.length;
    const pendentes = comentarios.filter(
      (comentario) => parseInt(comentario.concluido ?? 0, 10) !== 1,
    ).length;
    _atualizarBadgeImagem(id, total, pendentes);
  }

  // Remove marcadores anteriores (pontos, formas e freehand)
  markerContainer
    .querySelectorAll(".comment, .comment-shape, .comment-freehand")
    .forEach((c) => c.remove());

  // Oculta a sidebar-direita se não houver comentários
  if (target.hideList) {
    comentariosDiv.style.display = "none";
  } else if (comentarios.length === 0) {
    comentariosDiv.style.display = "none";
  } else {
    comentariosDiv.style.display = "flex";
    renderCommentProgress(comentariosDiv, comentarios);
  }

  const users = await fetch("buscar_usuarios.php").then((res) => res.json());
  if (!isCurrentRender()) return;
  if (users.length > 0) _cachedUsers = users;

  const tribute = new Tribute({
    values: users.map((user) => ({
      key: user.nome_colaborador,
      value: user.nome_colaborador,
    })),
    selectTemplate: function (item) {
      return `@${item.original.value}`;
    },
  });

  // No PDF: lista mostra todos; marcadores mostram só a página atual
  const paginaAtual = isPdf ? parseInt(String(id.pagina || 1), 10) : null;

  comentarios.forEach((comentario) => {
    const commentCard = document.createElement("div");
    commentCard.classList.add("comment-card");
    commentCard.setAttribute("data-id", comentario.id);

    const header = document.createElement("div");
    header.classList.add("comment-header");
    const isConcluido = parseInt(comentario.concluido ?? 0, 10) === 1;

    const pageInfo =
      isPdf && comentario.pagina
        ? `<div class="comment-page">Pág. ${comentario.pagina}</div>`
        : "";
    const videoTimeInfo =
      isVideo &&
      comentario.video_time_ms !== null &&
      comentario.video_time_ms !== undefined &&
      comentario.video_time_ms !== ""
        ? `<button type="button" class="comment-video-time" data-video-time="${Number(comentario.video_time_ms) || 0}">${formatVideoTime(comentario.video_time_ms)}</button>`
        : "";
    header.innerHTML = `
            <div class="comment-number">${comentario.numero_comentario}</div>
            <div class="comment-user">${comentario.nome_responsavel}</div>
            ${pageInfo}
            ${videoTimeInfo}
        `;
    const videoTimeButton = header.querySelector(".comment-video-time");
    if (videoTimeButton) {
      videoTimeButton.addEventListener("click", (event) => {
        event.stopPropagation();
        const video = document.getElementById("video_atual");
        if (!video) return;
        const ms = Number(videoTimeButton.dataset.videoTime || 0);
        video.currentTime = Math.max(0, ms / 1000);
        video.focus();
      });
    }

    // Botão de checklist (marcar/desmarcar como concluído)
    const checkBtn = document.createElement("button");
    checkBtn.className = "comment-check" + (isConcluido ? " checked" : "");
    checkBtn.title = isConcluido
      ? "Desmarcar ajuste"
      : "Marcar ajuste como concluído";
    checkBtn.setAttribute("aria-label", checkBtn.title);
    checkBtn.innerHTML = isConcluido ? "✅" : "⬜";
    checkBtn.addEventListener("click", async (e) => {
      e.stopPropagation();
      const novo = !isConcluido;
      checkBtn.disabled = true;
      const result = await toggleCommentConcluido(comentario.id, novo);
      checkBtn.disabled = false;
      if (result && result.sucesso) {
        // Re-renderiza comentários com dados atualizados
        await renderComments(id);
        // Atualiza badge da imagem na nav lateral
        _atualizarBadgeImagem(
          typeof id === "object" ? id.arquivo_log_id : id,
          result.total,
          result.pendentes,
        );
      }
    });
    header.appendChild(checkBtn);

    if (isConcluido) {
      commentCard.classList.add("comment-concluido");
    }

    const commentBody = document.createElement("div");
    commentBody.classList.add("comment-body");

    const p = document.createElement("p");
    p.classList.add("comment-input");
    p.innerHTML = highlightMentions(comentario.texto || "");

    commentBody.appendChild(p);

    const footer = document.createElement("div");
    footer.classList.add("comment-footer");
    footer.innerHTML = `
            <div class="comment-date">${formatarDataComentario(comentario.data)}</div>
            <div class="comment-actions">
                <button class="comment-resp">&#8617</button>
                <button class="comment-edit">✏️</button>
                <button class="comment-delete">🗑️</button>
            </div>
        `;

    const respostas = document.createElement("div");
    respostas.classList.add("respostas-container");
    respostas.id = `respostas-${comentario.id}`;

    commentCard.appendChild(header);
    if (comentario.imagem) {
      const imagemDiv = document.createElement("div");
      imagemDiv.classList.add("comment-image");
      const thumb = `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(comentario.imagem)}&w=200&q=85`;
      imagemDiv.innerHTML = `
                    <img src="${thumb}" class="comment-img-thumb" onclick="abrirImagemModal('${comentario.imagem}')">
                `;
      commentCard.appendChild(imagemDiv);
    }
    commentCard.appendChild(commentBody);
    commentCard.appendChild(footer);
    commentCard.appendChild(respostas);

    // Permissões: edit e delete só para o autor do comentário
    const idColabAtual = parseInt(localStorage.getItem("idcolaborador"));
    const isAuthorComment =
      parseInt(comentario.responsavel_id) === idColabAtual;
    if (!isAuthorComment) {
      footer.querySelector(".comment-delete").style.display = "none";
      footer.querySelector(".comment-edit").style.display = "none";
    }

    const deleteButton = footer.querySelector(".comment-delete");
    deleteButton.addEventListener("click", async (e) => {
      e.stopPropagation();
      const { isConfirmed } = await Swal.fire({
        title: "Excluir comentário?",
        text: "Esta ação não pode ser desfeita.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sim, excluir",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#c0392b",
      });
      if (!isConfirmed) return;
      await deleteComment(comentario.id);
    });

    const editButton = footer.querySelector(".comment-edit");

    editButton.addEventListener("click", (e) => {
      e.stopPropagation();
      _editingCommentId = comentario.id;

      // Preenche o Quill com o texto atual do comentário
      if (quillComentario) {
        quillComentario.root.innerHTML = comentario.texto || "";
      }

      // Atualiza título do modal
      const modalTitle = document.querySelector("#comentarioModal h3");
      if (modalTitle) modalTitle.textContent = "Editar Comentário";

      // Limpa o input de imagem
      document.getElementById("imagemComentario").value = "";

      // Posiciona o modal à direita do marcador do comentário na imagem
      const markerEl = markerContainer.querySelector(
        `[data-id="${comentario.id}"]`,
      );
      if (markerEl) {
        const r = markerEl.getBoundingClientRect();
        openCommentModalAtPoint(r.right + 8, r.top + r.height / 2);
      } else {
        openCommentModalAtPoint(e.clientX, e.clientY);
      }
      mencionadosIds = [];
    });

    let commentDiv = null;
    const hasPointCoordinates =
      comentario.x !== null &&
      comentario.x !== undefined &&
      comentario.x !== "" &&
      comentario.y !== null &&
      comentario.y !== undefined &&
      comentario.y !== "";
    const isShape = comentario.tipo === "rect" || comentario.tipo === "circle";
    const isFreehand = comentario.tipo === "freehand";
    const cor = comentario.cor || "#000000";
    const corR = parseInt(cor.slice(1, 3), 16);
    const corG = parseInt(cor.slice(3, 5), 16);
    const corB = parseInt(cor.slice(5, 7), 16);

    if (isFreehand) {
      const svgNS = "http://www.w3.org/2000/svg";
      let pts = [];
      try {
        pts = JSON.parse(comentario.path_data || "[]");
      } catch (e) {}
      if (pts.length >= 2) {
        // Wrapper div covers the full image and acts as the commentDiv
        const wrapper = document.createElement("div");
        wrapper.classList.add("comment-freehand");
        wrapper.style.cssText =
          "position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:450;";

        const svg = document.createElementNS(svgNS, "svg");
        svg.setAttribute("viewBox", "0 0 100 100");
        svg.setAttribute("preserveAspectRatio", "none");
        svg.setAttribute("pointer-events", "none");
        svg.style.cssText =
          "position:absolute;top:0;left:0;width:100%;height:100%;overflow:visible;";

        const ptStr = pts.map((p) => p.join(",")).join(" ");

        const visible = document.createElementNS(svgNS, "polyline");
        visible.setAttribute("points", ptStr);
        visible.setAttribute("fill", "none");
        visible.setAttribute("stroke", cor);
        visible.setAttribute("stroke-width", "0.3");
        visible.setAttribute("stroke-linecap", "round");
        visible.setAttribute("stroke-linejoin", "round");
        visible.setAttribute("pointer-events", "none");
        svg.appendChild(visible);

        const hit = document.createElementNS(svgNS, "polyline");
        hit.setAttribute("points", ptStr);
        hit.setAttribute("fill", "none");
        hit.setAttribute("stroke", "transparent");
        hit.setAttribute("stroke-width", "3");
        hit.setAttribute("stroke-linecap", "round");
        hit.setAttribute("pointer-events", "stroke");
        hit.style.cursor = "pointer";
        svg.appendChild(hit);

        wrapper.appendChild(svg);

        // Badge – same element as shape badges, no SVG distortion
        const sp = pts[0];
        const badge = document.createElement("span");
        badge.className = "comment-shape-badge";
        badge.textContent = String(comentario.numero_comentario);
        badge.style.cssText = `position:absolute;left:${sp[0]}%;top:${sp[1]}%;transform:translate(-50%,-50%);background:${cor};border-color:rgba(0,0,0,0.25);color:#fff;pointer-events:none;`;
        wrapper.appendChild(badge);

        hit.addEventListener("click", (e) => {
          e.stopPropagation();
          document
            .querySelectorAll(".comment-number")
            .forEach((n) => n.classList.remove("highlight"));
          document
            .querySelectorAll(
              ".comment.highlight, .comment-shape.highlight, .comment-freehand.highlight",
            )
            .forEach((n) => n.classList.remove("highlight"));
          wrapper.classList.add("highlight");
          const cardNum = document.querySelector(
            `.comment-card[data-id="${comentario.id}"] .comment-number`,
          );
          if (cardNum) cardNum.classList.add("highlight");
          showCommentPopup(hit, comentario.id);
        });

        commentDiv = wrapper;
      }
    } else if (isShape && hasPointCoordinates) {
      commentDiv = document.createElement("div");
      commentDiv.classList.add("comment-shape");
      commentDiv.classList.add(
        comentario.tipo === "rect"
          ? "comment-shape-rect"
          : "comment-shape-circle",
      );
      const cx1 = parseFloat(comentario.x) || 0;
      const cy1 = parseFloat(comentario.y) || 0;
      const cx2 = comentario.x2 != null ? parseFloat(comentario.x2) : cx1 + 5;
      const cy2 = comentario.y2 != null ? parseFloat(comentario.y2) : cy1 + 5;
      commentDiv.style.left = `${Math.min(cx1, cx2)}%`;
      commentDiv.style.top = `${Math.min(cy1, cy2)}%`;
      commentDiv.style.width = `${Math.abs(cx2 - cx1)}%`;
      commentDiv.style.height = `${Math.abs(cy2 - cy1)}%`;
      commentDiv.style.borderColor = cor;
      commentDiv.style.backgroundColor = `rgba(${corR},${corG},${corB},0.10)`;
      const badge = document.createElement("span");
      badge.className = "comment-shape-badge";
      badge.textContent = comentario.numero_comentario;
      badge.style.backgroundColor = cor;
      badge.style.borderColor = cor;
      badge.style.color = "#fff";
      commentDiv.appendChild(badge);
    } else if (hasPointCoordinates) {
      commentDiv = document.createElement("div");
      commentDiv.classList.add("comment");
      commentDiv.innerText = comentario.numero_comentario;
      commentDiv.style.left = `${comentario.x}%`;
      commentDiv.style.top = `${comentario.y}%`;
      commentDiv.style.backgroundColor = cor;
      commentDiv.style.color = "#fff";
    }

    // Marca visualmente o marker como concluído
    if (commentDiv && isConcluido) {
      commentDiv.classList.add("comment-marker-concluido");
    }

    if (commentDiv) {
      commentDiv.setAttribute("data-id", comentario.id);
    }

    // Generic marker click (ponto + shapes; freehand uses SVG hit handler above)
    if (commentDiv && !isFreehand) {
      commentDiv.addEventListener("click", (e) => {
        // No PDF, a bolinha fica em cima do canvas; evita abrir um novo comentário ao clicar nela.
        if (e && typeof e.stopPropagation === "function") e.stopPropagation();

        // Highlight marker and card number
        document
          .querySelectorAll(".comment-number")
          .forEach((n) => n.classList.remove("highlight"));
        document
          .querySelectorAll(
            ".comment.highlight, .comment-shape.highlight, .comment-freehand.highlight",
          )
          .forEach((n) => n.classList.remove("highlight"));
        const number = document.querySelector(
          `.comment-card[data-id="${comentario.id}"] .comment-number`,
        );
        if (number) number.classList.add("highlight");
        commentDiv.classList.add("highlight");

        // Show inline popup next to the marker
        showCommentPopup(commentDiv, comentario.id);
      });
    }

    commentCard.addEventListener("click", async () => {
      if (
        isVideo &&
        comentario.video_time_ms !== null &&
        comentario.video_time_ms !== undefined &&
        comentario.video_time_ms !== ""
      ) {
        const video = document.getElementById("video_atual");
        if (video) {
          video.currentTime = Math.max(
            0,
            (Number(comentario.video_time_ms) || 0) / 1000,
          );
        }
      }

      // No PDF, ao clicar em um comentário: ir para a página correspondente
      if (isPdf && comentario.pagina) {
        const targetPage = parseInt(String(comentario.pagina), 10);
        if (
          Number.isFinite(targetPage) &&
          targetPage >= 1 &&
          targetPage <= (pdfViewerState.pages || targetPage)
        ) {
          if (pdfViewerState.page !== targetPage) {
            pendingPdfFocusCommentId = comentario.id;
            pdfViewerState.page = targetPage;
            await renderizarPaginaPdf();
            return;
          }
        }
      }

      // Remove highlight de todos os marcadores e números de card
      document
        .querySelectorAll(
          ".comment.highlight, .comment-shape.highlight, .comment-freehand.highlight",
        )
        .forEach((n) => n.classList.remove("highlight"));
      document
        .querySelectorAll(".comment-number.highlight")
        .forEach((n) => n.classList.remove("highlight"));

      // Destaca o número do card clicado
      const cardNum = commentCard.querySelector(".comment-number");
      if (cardNum) cardNum.classList.add("highlight");

      // Pega o marcador correspondente ao comentário
      const freehandEl = document.querySelector(
        `.comment-freehand[data-id="${comentario.id}"]`,
      );
      const markerEl = freehandEl
        ? freehandEl
        : document.querySelector(
            `.comment[data-id="${comentario.id}"], .comment-shape[data-id="${comentario.id}"]`,
          );

      if (markerEl) {
        markerEl.classList.add("highlight");

        if (currentZoom <= 1) {
          return;
        }

        // Pan to the marker, accounting for the CSS zoom transform.
        // scrollIntoView() uses layout positions and ignores CSS transforms,
        // so it navigates to the wrong place when zoomed. Instead, compute
        // the marker's natural % position and adjust the translate so it
        // appears centred in the image_wrapper viewport.
        const iw = document.getElementById("image_wrapper");
        const iwW = iw.offsetWidth; // natural size, unaffected by transform
        const iwH = iw.offsetHeight;

        let targetX = 50; // % from left
        let targetY = 50; // % from top

        if (comentario.tipo === "rect" || comentario.tipo === "circle") {
          const cx1 = parseFloat(comentario.x) || 0;
          const cy1 = parseFloat(comentario.y) || 0;
          const cx2 = comentario.x2 != null ? parseFloat(comentario.x2) : cx1;
          const cy2 = comentario.y2 != null ? parseFloat(comentario.y2) : cy1;
          targetX = (cx1 + cx2) / 2;
          targetY = (cy1 + cy2) / 2;
        } else if (comentario.tipo === "freehand") {
          let pts = [];
          try {
            pts = JSON.parse(comentario.path_data || "[]");
          } catch (e) {}
          if (pts.length > 0) {
            targetX = pts[0][0];
            targetY = pts[0][1];
          }
        } else {
          targetX = parseFloat(comentario.x) || 50;
          targetY = parseFloat(comentario.y) || 50;
        }

        // With transform: scale(zoom) translate(tx, ty), the visual distance
        // of a natural point from the wrapper centre is:
        //   zoom * (naturalOffset + tx)
        // Setting tx = −naturalOffset makes the marker appear at the centre.
        currentTranslateX = -(targetX / 100 - 0.5) * iwW;
        currentTranslateY = -(targetY / 100 - 0.5) * iwH;
        applyTransforms();
      }
    });

    const respButton = commentCard.querySelector(".comment-resp");

    respButton.addEventListener("click", (e) => {
      e.stopPropagation();
      _replyingToCommentId = comentario.id;
      _editingCommentId = null;

      // Mostra o contexto da mensagem original acima do editor
      const replyCtx = document.getElementById("reply-context");
      if (replyCtx) {
        const textoOriginal = comentario.texto || "";
        const autorOriginal = comentario.nome_responsavel || "";
        replyCtx.innerHTML = `<strong>${escapeHtml(autorOriginal)}:</strong> ${textoOriginal}`;
        replyCtx.style.display = "block";
      }

      const modalTitle = document.querySelector("#comentarioModal h3");
      if (modalTitle) modalTitle.textContent = "Responder Comentário";

      if (quillComentario) quillComentario.setContents([]);
      document.getElementById("imagemComentario").value = "";

      openCommentModalAtPoint(e.clientX, e.clientY);
      mencionadosIds = [];
    });

    // Marcadores: no PDF, só da página atual
    if (!commentDiv) {
      // Comentario geral sem coordenadas: aparece somente no sidebar.
    } else if (!isPdf) {
      markerContainer.appendChild(commentDiv);
    } else {
      const paginaDoComentario = parseInt(String(comentario.pagina || ""), 10);
      if (
        Number.isFinite(paginaDoComentario) &&
        paginaDoComentario === paginaAtual
      ) {
        markerContainer.appendChild(commentDiv);
      }
    }
    comentariosDiv.appendChild(commentCard);

    if (comentario.respostas && comentario.respostas.length > 0) {
      comentario.respostas.forEach((resposta) => {
        adicionarRespostaDOM(comentario.id, resposta);
      });
    }
  });

  // Se veio um foco pendente (após mudar página), destaca no painel
  if (isPdf && id && id.focus_comment_id) {
    const focusId = String(id.focus_comment_id);
    const number = document.querySelector(
      `.comment-card[data-id="${focusId}"] .comment-number`,
    );
    if (number) {
      document
        .querySelectorAll(".comment-number")
        .forEach((n) => n.classList.remove("highlight"));
      number.classList.add("highlight");
      number.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }
}

// Função para enviar resposta pro backend
async function salvarResposta(
  comentarioId,
  texto,
  imagemFile = null,
  mencionados = [],
) {
  if (imagemFile) {
    const fd = new FormData();
    fd.append("comentario_id", comentarioId);
    fd.append("texto", texto);
    fd.append("imagem", imagemFile);
    fd.append("mencionados", JSON.stringify(mencionados));
    const response = await fetch("responder_comentario.php", {
      method: "POST",
      body: fd,
    });
    return await response.json();
  }
  const response = await fetch("responder_comentario.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      comentario_id: comentarioId,
      texto: texto,
      mencionados: mencionados,
    }),
  });
  return await response.json();
}

// Função pra adicionar resposta no DOM
function adicionarRespostaDOM(comentarioId, resposta) {
  const container = document.getElementById(`respostas-${comentarioId}`);
  if (!container || !resposta?.id) return;
  if (
    container.querySelector(
      `.resposta[data-reply-id="${String(resposta.id)}"]`,
    )
  ) {
    return;
  }
  const respostaDiv = document.createElement("div");
  respostaDiv.classList.add("resposta");
  respostaDiv.setAttribute("data-reply-id", resposta.id);

  let imagemHtml = "";
  if (resposta.imagem) {
    const thumb = `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(resposta.imagem)}&w=200&q=85`;
    imagemHtml = `<div class="comment-image"><img src="${thumb}" class="comment-img-thumb" onclick="abrirImagemModal('${resposta.imagem}')"></div>`;
  }

  const idColabAtual = parseInt(localStorage.getItem("idcolaborador"));
  const isAuthor =
    resposta.responsavel && parseInt(resposta.responsavel) === idColabAtual;

  respostaDiv.innerHTML = `
        <div class="resposta-nome"><span class="reply-icon">&#8617;</span>  ${resposta.nome_responsavel}</div>
        ${imagemHtml}
        <div class="corpo-resposta">
            <div class="resposta-texto">${highlightMentions(resposta.texto || "")}</div>
            <div class="resposta-data">${formatarDataComentario(resposta.data)}</div>
        </div>
        ${
          isAuthor
            ? `<div class="reply-actions">
          <button class="reply-edit-btn" title="Editar resposta">✏️</button>
          <button class="reply-delete-btn" title="Excluir resposta">🗑️</button>
        </div>`
            : ""
        }
    `;

  if (isAuthor) {
    respostaDiv
      .querySelector(".reply-edit-btn")
      .addEventListener("click", (e) => {
        e.stopPropagation();
        _editingReplyId = resposta.id;
        _editingCommentId = null;
        _replyingToCommentId = null;

        // Lê o texto atual do DOM (pode ter sido editado antes)
        const currentText =
          respostaDiv.querySelector(".resposta-texto")?.innerHTML ||
          resposta.texto ||
          "";
        if (quillComentario) quillComentario.root.innerHTML = currentText;
        document.getElementById("imagemComentario").value = "";

        const replyCtx = document.getElementById("reply-context");
        if (replyCtx) {
          replyCtx.style.display = "none";
          replyCtx.innerHTML = "";
        }

        const modalTitle = document.querySelector("#comentarioModal h3");
        if (modalTitle) modalTitle.textContent = "Editar Resposta";

        openCommentModalAtPoint(e.clientX, e.clientY);
        mencionadosIds = [];
      });

    respostaDiv
      .querySelector(".reply-delete-btn")
      .addEventListener("click", async (e) => {
        e.stopPropagation();
        const { isConfirmed } = await Swal.fire({
          title: "Excluir resposta?",
          text: "Esta ação não pode ser desfeita.",
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Sim, excluir",
          cancelButtonText: "Cancelar",
          confirmButtonColor: "#c0392b",
        });
        if (!isConfirmed) return;
        await deleteReply(resposta.id, respostaDiv);
      });
  }

  container.appendChild(respostaDiv);
}

// Atualiza uma resposta no backend
async function updateReply(
  replyId,
  novoTexto,
  imagemFile = null,
  mencionados = [],
) {
  try {
    let response;
    if (imagemFile) {
      const fd = new FormData();
      fd.append("id", replyId);
      fd.append("texto", novoTexto);
      fd.append("imagem", imagemFile);
      fd.append("mencionados", JSON.stringify(mencionados));
      response = await fetch("atualizar_resposta.php", {
        method: "POST",
        body: fd,
      });
    } else {
      response = await fetch("atualizar_resposta.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id: replyId,
          texto: novoTexto,
          mencionados: mencionados,
        }),
      });
    }
    const result = await response.json();
    if (result.sucesso) {
      Toastify({
        text: "Resposta atualizada com sucesso!",
        duration: 3000,
        backgroundColor: "green",
        close: true,
        gravity: "top",
        position: "left",
      }).showToast();
    }
    return result;
  } catch (err) {
    console.error("Erro ao atualizar resposta:", err);
  }
}

// Exclui uma resposta no backend e remove do DOM
async function deleteReply(replyId, replyEl) {
  try {
    const response = await fetch("excluir_resposta.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: replyId }),
    });
    const result = await response.json();
    if (result.sucesso) {
      replyEl.remove();
      Toastify({
        text: "Resposta excluída!",
        duration: 3000,
        backgroundColor: "green",
        close: true,
        gravity: "top",
        position: "left",
      }).showToast();
    }
  } catch (err) {
    console.error("Erro ao excluir resposta:", err);
  }
}

// Função para atualizar o comentário no banco de dados
async function updateComment(
  commentId,
  novoTexto,
  imagemFile = null,
  mencionados = [],
) {
  try {
    let response;
    if (imagemFile) {
      const formData = new FormData();
      formData.append("id", commentId);
      formData.append("texto", novoTexto);
      formData.append("imagem", imagemFile);
      formData.append("mencionados", JSON.stringify(mencionados));
      response = await fetch("atualizar_comentario.php", {
        method: "POST",
        body: formData,
      });
    } else {
      response = await fetch("atualizar_comentario.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id: commentId,
          texto: novoTexto,
          mencionados: mencionados,
        }),
      });
    }

    const result = await response.json();
    if (result.sucesso) {
      Toastify({
        text: "Comentário atualizado com sucesso!",
        duration: 3000,
        backgroundColor: "green",
        close: true,
        gravity: "top",
        position: "left",
      }).showToast();
    } else {
      Toastify({
        text: "Erro ao atualizar comentário!",
        duration: 3000,
        backgroundColor: "green",
        close: true,
        gravity: "top",
        position: "left",
      }).showToast();
    }

    // Se estiver em PDF, invalida cache para refletir texto atualizado
    if (pdfViewerState.logId) {
      pdfCommentsCache.logId = null;
      pdfCommentsCache.comentarios = null;
    }
  } catch (error) {
    console.error("Erro ao atualizar comentário:", error);
    alert("Ocorreu um erro ao tentar atualizar o comentário.");
  }
}

// Função para excluir o comentário do banco de dados
async function deleteComment(commentId) {
  try {
    const response = await fetch("excluir_comentario.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: commentId }),
    });

    const result = await response.json();
    if (result.sucesso) {
      Toastify({
        text: "Comentário excluído com sucesso!",
        duration: 3000,
        backgroundColor: "green",
        close: true,
        gravity: "top",
        position: "left",
      }).showToast();
      if (pdfViewerState.logId) {
        pdfCommentsCache.logId = null;
        pdfCommentsCache.comentarios = null;
        renderComments({
          arquivo_log_id: pdfViewerState.logId,
          pagina: pdfViewerState.page,
        });
      } else {
        refreshCurrentCommentTarget();
      }
    } else {
      Toastify({
        text: "Erro ao excluir comentário!",
        duration: 3000,
        backgroundColor: "green",
        close: true,
        gravity: "top",
        position: "left",
      }).showToast();
    }
  } catch (error) {
    console.error("Erro ao excluir comentário:", error);
    alert("Ocorreu um erro ao tentar excluir o comentário.");
  }
}

function abrirImagemModal(src) {
  const modal = document.getElementById("modal-imagem");
  const imagem = document.getElementById("imagem-ampliada");
  imagem.src = src;
  modal.style.display = "flex";
}

function fecharImagemModal() {
  const modal = document.getElementById("modal-imagem");
  modal.style.display = "none";
}

document.addEventListener("keydown", function (event) {
  if (event.key === "Escape") {
    // Close inline comment popup first
    if (activeCommentPopup) {
      closeCommentPopup();
      return;
    }

    // Close #imagem-modal (add ângulos)
    const imagemModal = document.getElementById("imagem-modal");
    if (imagemModal && imagemModal.style.display === "flex") {
      imagemModal.style.display = "none";
      const inputImagens = document.getElementById("input-imagens");
      if (inputImagens) inputImagens.value = "";
      const previewEl = document.getElementById("preview");
      if (previewEl) previewEl.innerHTML = "";
      return;
    }

    const comentarioModal = document.getElementById("comentarioModal");

    if (comentarioModal.style.display === "flex") {
      comentarioModal.style.display = "none";
      removeCommentPreview();
      _editingCommentId = null;
      _replyingToCommentId = null;
      _editingReplyId = null;
      if (quillComentario) quillComentario.setContents([]);
      const _mtEsc = document.querySelector("#comentarioModal h3");
      if (_mtEsc) _mtEsc.textContent = "Novo Comentário";
      const replyCtxEsc = document.getElementById("reply-context");
      if (replyCtxEsc) {
        replyCtxEsc.style.display = "none";
        replyCtxEsc.innerHTML = "";
      }
      return; // Interrompe aqui se o modal estava visível
    }

    // const main = document.querySelector(".main");
    // main.classList.remove("hidden");

    // const container_aprovacao = document.querySelector(".container-aprovacao");
    // container_aprovacao.classList.add("hidden");

    // const imagemWrapperDiv = document.querySelector(".image_wrapper");
    // imagemWrapperDiv.innerHTML = "";

    // const comentariosDiv = document.querySelector(".comentarios");
    // comentariosDiv.innerHTML = "";
  }
});

const imageWrapper = document.getElementById("image_wrapper");
let currentZoom = 1;
const zoomStep = 0.1;
const minZoom = 0.5;

// Pan variables
let isDragging = false;
let startX;
let startY;
let currentTranslateX = 0;
let currentTranslateY = 0;
let dragMoved = false;

// Function to apply transforms (zoom and pan)
function applyTransforms() {
  imageWrapper.style.transform = `scale(${currentZoom}) translate(${currentTranslateX}px, ${currentTranslateY}px)`;

  // Adjust comment bubble scaling based on the new currentZoom
  document.querySelectorAll("#image_wrapper .comment").forEach((comment) => {
    comment.style.transform = `translate(-50%, -50%) scale(${1 / currentZoom})`;
  });

  // Keep shape badges at a constant visual size regardless of zoom
  document
    .querySelectorAll("#image_wrapper .comment-shape-badge")
    .forEach((badge) => {
      badge.style.transform = `scale(${1 / currentZoom})`;
      badge.style.transformOrigin = "top left";
    });
}

// --- Zoom functionality ---
document.addEventListener(
  "wheel",
  function (event) {
    if (event.ctrlKey) {
      event.preventDefault(); // Prevent default browser zoom/scroll

      const oldZoom = currentZoom; // Store old zoom for potential pan adjustment (not used in your current code but good practice)

      if (event.deltaY < 0) {
        currentZoom += zoomStep;
      } else {
        currentZoom -= zoomStep;
      }

      currentZoom = Math.max(minZoom, currentZoom);

      if (currentZoom === minZoom) {
        // When zoomed out completely, reset pan to origin
        currentTranslateX = 0;
        currentTranslateY = 0;
      }

      applyTransforms();
    }
  },
  { passive: false },
);

document.getElementById("btn-mais-zoom").addEventListener("click", function () {
  currentZoom += zoomStep;
  applyTransforms();
});

document
  .getElementById("btn-menos-zoom")
  .addEventListener("click", function () {
    currentZoom = Math.max(currentZoom - zoomStep, minZoom);
    applyTransforms();
  });

document.getElementById("reset-zoom").addEventListener("click", function () {
  currentZoom = 1;
  currentTranslateX = 0; // reseta deslocamento horizontal
  currentTranslateY = 0; // reseta deslocamento vertical
  applyTransforms();
});

imageWrapper.addEventListener("mousedown", (e) => {
  if (drawingTool !== "ponto") return; // deixa o handler de forma assumir o controle
  if (e.button === 0 && !e.ctrlKey) {
    isDragging = true;
    dragMoved = false; // reset
    imageWrapper.style.cursor = "grabbing"; // mão fechada

    imageWrapper.classList.add("grabbing");
    startX = e.clientX - currentTranslateX;
    startY = e.clientY - currentTranslateY;
    imageWrapper.style.transition = "none";
  }
});

function handlePointerMove(e) {
  // normalize event for touch (use clientX/Y)
  const clientX = e.clientX;
  const clientY = e.clientY;

  // --- Desenho de forma geométrica ---
  if (isDrawing && currentDrawRef) {
    if (e.cancelable) e.preventDefault(); // impede scroll/pan durante o desenho
    const ref = currentDrawRef.getBoundingClientRect();
    if (ref.width && ref.height) {
      const newX = Math.max(
        0,
        Math.min(100, ((clientX - ref.left) / ref.width) * 100),
      );
      const newY = Math.max(
        0,
        Math.min(100, ((clientY - ref.top) / ref.height) * 100),
      );
      if (drawingTool === "freehand") {
        freehandPoints.push([newX, newY]);
        if (freehandPolylineEl) {
          freehandPolylineEl.setAttribute(
            "points",
            freehandPoints.map((p) => p.join(",")).join(" "),
          );
        }
      } else {
        shapeX2 = newX;
        shapeY2 = newY;
        const x1 = Math.min(drawStartX, shapeX2);
        const y1 = Math.min(drawStartY, shapeY2);
        const w = Math.abs(shapeX2 - drawStartX);
        const h = Math.abs(shapeY2 - drawStartY);
        const preview = document.getElementById("drawing-preview");
        if (preview) {
          preview.style.left = `${x1}%`;
          preview.style.top = `${y1}%`;
          preview.style.width = `${w}%`;
          preview.style.height = `${h}%`;
        }
      }
    }
    return; // não faz pan enquanto desenha
  }

  if (!isDragging) return;
  imageWrapper.style.cursor = "grabbing"; // mão fechada

  if (e.cancelable) e.preventDefault();

  const dx = clientX - startX;
  const dy = clientY - startY;

  // Marcar que houve movimento significativo
  if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
    dragMoved = true;
  }

  currentTranslateX = dx;
  currentTranslateY = dy;

  applyTransforms();
}

document.addEventListener("mousemove", handlePointerMove, { passive: false });
document.addEventListener("pointermove", handlePointerMove, { passive: false });

function handlePointerUp(e) {
  // use client coords
  const clientX = e.clientX;
  const clientY = e.clientY;

  // --- Finaliza desenho de forma ---
  if (isDrawing) {
    isDrawing = false;
    const preview = document.getElementById("drawing-preview");
    if (preview) preview.remove();
    if (freehandSvgPreview) {
      freehandSvgPreview.remove();
      freehandSvgPreview = null;
      freehandPolylineEl = null;
      freehandDrawContainer = null;
    }
    currentDrawRef = null;

    const dx = Math.abs(drawStartClientX - clientX);
    const dy = Math.abs(drawStartClientY - clientY);

    // Considera finalização válida se houve arraste significativo em pixels
    // ou se estamos no modo freehand e coletamos mais de um ponto
    const isSignificant =
      dx > 8 ||
      dy > 8 ||
      (drawingTool === "freehand" &&
        Array.isArray(freehandPoints) &&
        freehandPoints.length > 1);

    if (isSignificant) {
      if (drawingTool === "freehand") {
        relativeX = freehandPoints[0]?.[0] ?? drawStartX;
        relativeY = freehandPoints[0]?.[1] ?? drawStartY;
      } else {
        // Normaliza para que x≤x2 e y≤y2
        relativeX = Math.min(drawStartX, shapeX2);
        relativeY = Math.min(drawStartY, shapeY2);
        shapeX2 = Math.max(drawStartX, shapeX2);
        shapeY2 = Math.max(drawStartY, shapeY2);
      }

      _editingCommentId = null;
      if (quillComentario) quillComentario.setContents([]);
      const _mtFh = document.querySelector("#comentarioModal h3");
      if (_mtFh) {
        _mtFh.textContent =
          currentMediaMode === "video" && currentVideoTimeMs !== null
            ? `Novo Comentário (${formatVideoTime(currentVideoTimeMs)})`
            : "Novo Comentário";
      }
      document.getElementById("imagemComentario").value = "";
      openCommentModalAtPoint(
        drawStartClientX || window.innerWidth / 2,
        drawStartClientY || window.innerHeight / 2,
      );
      mencionadosIds = [];
    }
    imageWrapper.style.cursor = "crosshair !important";
    return;
  }

  if (isDragging) {
    isDragging = false;
    imageWrapper.style.cursor = "grab"; // mão aberta
    imageWrapper.classList.remove("grabbing");
    imageWrapper.style.transition = "transform 0.1s ease-out";
  }
}

document.addEventListener("mouseup", handlePointerUp);
document.addEventListener("pointerup", handlePointerUp);

// Initialize transforms
applyTransforms();

const id_revisao = document.getElementById("id_revisao");

// function addObservacao(id) {
//     const modal = document.getElementById('historico_modal');
//     const idRevisao = document.getElementById('id_revisao');
//     const historicoAdd = modal.querySelector('.historico-add');

//     historicoAdd.classList.toggle('hidden');

//     if (historicoAdd.classList.contains('hidden')) {
//         modal.classList.remove('complete');
//     } else {
//         modal.classList.add('complete');
//     }

//     idRevisao.innerText = `${id}`;
// }

// Inicializa o editor Quill
// var quill = new Quill('#text_obs', {
//     theme: 'snow',  // Tema claro
//     modules: {
//         toolbar: [
//             ['bold', 'italic', 'underline'], // Negrito, itálico, sublinhado
//             [{ 'header': 1 }, { 'header': 2 }], // Títulos
//             [{ 'list': 'ordered' }, { 'list': 'bullet' }], // Listas
//             [{ 'color': [] }, { 'background': [] }], // Cores
//             ['clean'] // Limpar formatação
//         ]
//     }
// });

// const historico_modal = document.getElementById('historico_modal');
// const historicoAdd = historico_modal.querySelector('.historico-add');

// window.addEventListener('click', function (event) {
//     if (event.target == historico_modal) {
//         historico_modal.style.display = "none"
//         historico_modal.classList.remove('complete');
//         historicoAdd.classList.add('hidden');
//     }
// });

// window.addEventListener('touchstart', function (event) {
//     if (event.target == historico_modal) {
//         historico_modal.style.display = "none"
//         historico_modal.classList.remove('complete');
//         historicoAdd.classList.add('hidden');

//     }
// });

// Captura o evento de envio do formulário
// document.getElementById('adicionar_obs').addEventListener('submit', function (event) {
//     event.preventDefault(); // Previne o comportamento padrão do envio do formulário

//     // Exibe um prompt para o usuário digitar o número da revisão
//     const numeroRevisao = document.getElementById('id_revisao').textContent;
//     const idfuncao_imagem = document.getElementById("id_funcao").value;

//     if (numeroRevisao) {
//         // Captura o conteúdo do editor Quill
//         const observacao = quill.root.innerHTML;

//         // Exibe os valores no console (você pode remover esta parte depois)
//         console.log("Número da Revisão: " + numeroRevisao);
//         console.log("Observação: " + observacao);

//         // Envia os dados para o servidor via fetch
//         fetch('atualizar_historico.php', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json'
//             },
//             body: JSON.stringify({
//                 revisao: numeroRevisao,
//                 observacao: observacao
//             })
//         })
//             .then(response => response.json())
//             .then(data => {
//                 // Verifica se a atualização foi bem-sucedida
//                 if (data.success) {
//                     Toastify({
//                         text: 'Observação adicionada com sucesso!',
//                         duration: 3000,
//                         backgroundColor: 'green',
//                         close: true,
//                         gravity: "top",
//                         position: "right"
//                     }).showToast();

//                     historico_modal.classList.remove('complete');
//                     historicoAdd.classList.toggle('hidden');
//                     historyAJAX(idfuncao_imagem)
//                 } else {
//                     Toastify({
//                         text: "Falha ao atualizar a tarefa: " + data.message,
//                         duration: 3000,
//                         backgroundColor: "red",
//                         close: true,
//                         gravity: "top",
//                         position: "right"
//                     }).showToast();
//                 }
//             })
//             .catch(error => {
//                 console.error("Erro ao enviar dados para o servidor:", error);
//                 alert("Ocorreu um erro ao tentar adicionar a observação.");
//             });
//     } else {
//         alert("Número de revisão é obrigatório!");
//     }
// });

// Fallback: floating tooltip appended to body to avoid stacking-context issues
(function () {
  let activeTooltip = null;

  function createTooltip(text) {
    const el = document.createElement("div");
    el.className = "floating-tooltip";
    el.textContent = text;
    document.body.appendChild(el);
    return el;
  }

  function showTooltipFor(target) {
    if (!target) return;
    const text = target.getAttribute("data-tooltip");
    if (!text) return;
    if (activeTooltip) hideTooltipFor(target);
    activeTooltip = createTooltip(text);

    function position() {
      if (!activeTooltip) return;
      const rect = target.getBoundingClientRect();
      const ttRect = activeTooltip.getBoundingClientRect();
      let left = rect.left + rect.width / 2 - ttRect.width / 2;
      left = Math.max(8, Math.min(left, window.innerWidth - ttRect.width - 8));
      let top = rect.top - ttRect.height - 8;
      if (top < 8) top = rect.bottom + 8;
      activeTooltip.style.left = Math.round(left) + "px";
      activeTooltip.style.top = Math.round(top) + "px";
    }

    position();

    const onScroll = () => position();
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onScroll);

    // store cleanup fn on element so we can remove listeners on hide
    target._floatingTooltipCleanup = function () {
      window.removeEventListener("scroll", onScroll);
      window.removeEventListener("resize", onScroll);
    };
  }

  function hideTooltipFor(target) {
    if (activeTooltip) {
      activeTooltip.remove();
      activeTooltip = null;
    }
    if (target && target._floatingTooltipCleanup) {
      try {
        target._floatingTooltipCleanup();
      } catch (e) {}
      delete target._floatingTooltipCleanup;
    }
  }

  // Delegated pointer handlers
  document.addEventListener("pointerenter", function (e) {
    const t = e.target.closest && e.target.closest(".tooltip");
    if (t) showTooltipFor(t);
  });

  document.addEventListener("pointerleave", function (e) {
    const t = e.target.closest && e.target.closest(".tooltip");
    if (t) hideTooltipFor(t);
  });

  // Also handle focus for accessibility
  document.addEventListener("focusin", function (e) {
    const t = e.target.closest && e.target.closest(".tooltip");
    if (t) showTooltipFor(t);
  });

  document.addEventListener("focusout", function (e) {
    const t = e.target.closest && e.target.closest(".tooltip");
    if (t) hideTooltipFor(t);
  });
})();

// Atualizações em tempo real do FlowReview (Redis -> WebSocket -> navegador)
const flowReviewRealtimeSeen = new Set();
let flowReviewRealtimeQueue = Promise.resolve();
let flowReviewRealtimeIndicatorTimer = null;

function flowReviewSameId(left, right) {
  return left !== null &&
    left !== undefined &&
    left !== "" &&
    right !== null &&
    right !== undefined &&
    right !== "" &&
    String(left) === String(right);
}

function getFlowReviewRealtimeScope() {
  const task = dadosTarefas.find(
    (item) => String(item.idfuncao_imagem) === String(funcaoImagemId),
  );
  const selectedObra = document.getElementById("filtro_obra")?.value || "";
  const obraTask = dadosTarefas.find(
    (item) => item.nomenclatura === selectedObra,
  );

  return {
    taskOpen:
      Boolean(funcaoImagemId) &&
      !document.querySelector(".container-aprovacao")?.classList.contains("hidden"),
    funcaoId: funcaoImagemId,
    tipoTarefa:
      currentFuncaoContext?.tipo_tarefa || getTaskTipo(task || currentFuncaoContext),
    imagemId:
      currentFuncaoContext?.imagem_id || task?.imagem_id || null,
    obraId:
      currentFuncaoContext?.obra_id ||
      currentFuncaoContext?.idobra ||
      task?.idobra ||
      obraTask?.idobra ||
      null,
    obraNome: selectedObra,
  };
}

function flowReviewEventBelongsToView(payload, scope) {
  if (!payload || !scope) return false;
  const payloadFuncao =
    payload.funcao_imagem_id || payload.funcao_animacao_id || null;

  if (scope.taskOpen) {
    return (
      flowReviewSameId(payloadFuncao, scope.funcaoId) ||
      flowReviewSameId(payload.imagem_id, scope.imagemId)
    );
  }

  // Na visão geral nenhuma obra está selecionada: qualquer evento do
  // FlowReview pode introduzir um novo card e precisa atualizar a listagem.
  if (!scope.obraNome) {
    return Boolean(payload.obra_id || payload.imagem_id || payloadFuncao);
  }

  return flowReviewSameId(payload.obra_id, scope.obraId);
}

async function refreshFlowReviewTaskSnapshot() {
  const response = await fetch("atualizar.php", { cache: "no-store" });
  if (!response.ok) throw new Error("Erro ao atualizar tarefas do FlowReview");
  const responseData = await response.json();
  dadosTarefas = responseData.tarefas ?? responseData;
  todasAsObras = new Set(dadosTarefas.map((task) => task.nomenclatura));
  todosOsColaboradores = new Set(
    dadosTarefas.map((task) => task.nome_colaborador),
  );
  todasAsFuncoes = new Set(dadosTarefas.map((task) => task.nome_funcao));

  if (responseData.server_now) {
    const serverDate = new Date(
      responseData.server_now.replace(" ", "T") + "-03:00",
    );
    _serverTimeOffset = serverDate.getTime() - Date.now();
  }
}

function refreshFlowReviewVisibleTaskList(scope) {
  if (scope.taskOpen) return;
  if (scope.obraNome) {
    filtrarTarefasPorObra(scope.obraNome);
  } else {
    applyHomeFilters();
    loadKpis(null);
  }
}

async function refreshFlowReviewCommentsFromEvent(payload) {
  const historicoId = payload.historico_id;
  const arquivoLogId = payload.arquivo_log_id;
  let refreshed = false;

  if (
    arquivoLogId &&
    flowReviewSameId(arquivoLogId, pdfViewerState.logId)
  ) {
    pdfCommentsCache.logId = null;
    pdfCommentsCache.comentarios = null;
    await renderComments({
      arquivo_log_id: pdfViewerState.logId,
      pagina: pdfViewerState.page,
    });
    refreshed = true;
  }

  if (enviosComparisonState.active) {
    const matchingViewers = enviosComparisonState.viewers.filter((viewer) =>
      flowReviewSameId(viewer.imageId, historicoId),
    );
    if (matchingViewers.length) {
      await Promise.all(matchingViewers.map((viewer) => viewer.refreshComments()));
      refreshed = true;
    }
  } else if (flowReviewSameId(ap_imagem_id, historicoId)) {
    await renderComments(ap_imagem_id);
    refreshed = true;
  }

  return refreshed;
}

function showFlowReviewRealtimeIndicator(payload) {
  const navSelect = document.querySelector(".nav-select");
  if (!navSelect) return;
  let indicator = document.getElementById("flowreview-realtime-indicator");
  if (!indicator) {
    indicator = document.createElement("span");
    indicator.id = "flowreview-realtime-indicator";
    indicator.className = "flowreview-realtime-indicator";
    indicator.setAttribute("role", "status");
    indicator.setAttribute("aria-live", "polite");
    navSelect.insertBefore(indicator, navSelect.querySelector(".buttons"));
  }

  const envio = payload.indice_envio || payload.versao;
  const actor = payload.actor_name ? ` por ${payload.actor_name}` : "";
  indicator.textContent = envio
    ? `Novo envio ${envio}${actor}`
    : `Novo envio recebido${actor}`;
  indicator.classList.add("is-visible");
  document.querySelector(".imagens > nav")?.classList.add("has-realtime-update");

  clearTimeout(flowReviewRealtimeIndicatorTimer);
  flowReviewRealtimeIndicatorTimer = setTimeout(() => {
    indicator?.classList.remove("is-visible");
    document
      .querySelector(".imagens > nav")
      ?.classList.remove("has-realtime-update");
  }, 8000);
}

async function handleFlowReviewRealtimeEvent(payload) {
  if (!payload?.event) return;
  const eventId = payload.event_id || `${payload.event}:${payload.ts || ""}`;
  if (flowReviewRealtimeSeen.has(eventId)) return;
  flowReviewRealtimeSeen.add(eventId);
  if (flowReviewRealtimeSeen.size > 200) {
    flowReviewRealtimeSeen.delete(flowReviewRealtimeSeen.values().next().value);
  }

  const scope = getFlowReviewRealtimeScope();
  if (!flowReviewEventBelongsToView(payload, scope)) return;

  if (String(payload.event).startsWith("comment.")) {
    await refreshFlowReviewCommentsFromEvent(payload);
    return;
  }

  const isApproval = String(payload.event).startsWith("approval.");
  const isMedia = String(payload.event).startsWith("media.");
  if (!isApproval && !isMedia) return;

  await refreshFlowReviewTaskSnapshot();
  if (scope.taskOpen && scope.funcaoId) {
    await historyAJAX(scope.funcaoId, scope.tipoTarefa, {
      preserveView: true,
      realtime: true,
    });
  } else {
    refreshFlowReviewVisibleTaskList(scope);
  }

  if (payload.event === "media.created") {
    showFlowReviewRealtimeIndicator(payload);
  }
}

window.addEventListener("improov:flowReviewUpdated", (event) => {
  flowReviewRealtimeQueue = flowReviewRealtimeQueue
    .then(() => handleFlowReviewRealtimeEvent(event.detail))
    .catch((error) => console.error("FlowReview realtime:", error));
});

document.addEventListener("DOMContentLoaded", () => {
  if (window.improovUploadWS?.connect) {
    window.improovUploadWS.connect();
  }
});
