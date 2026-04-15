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
  // carrega painel de métricas (acima do select de funções)
  if (typeof loadMetrics === "function") loadMetrics();
});

async function revisarTarefa(
  idfuncao_imagem,
  nome_colaborador,
  imagem_nome,
  nome_funcao,
  colaborador_id,
  imagem_id,
  tipoRevisao,
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
      const novoStatus = statusMap[tipoRevisao];
      if (novoStatus) {
        const task = dadosTarefas.find(
          (t) => t.idfuncao_imagem == idfuncao_imagem,
        );
        if (task) task.status_novo = novoStatus;
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

async function fetchObrasETarefas() {
  try {
    const response = await fetch(`atualizar.php`);
    if (!response.ok) throw new Error("Erro ao buscar tarefas");

    dadosTarefas = await response.json();

    todasAsObras = new Set(dadosTarefas.map((t) => t.nome_obra));
    todosOsColaboradores = new Set(dadosTarefas.map((t) => t.nome_colaborador));
    todasAsFuncoes = new Set(dadosTarefas.map((t) => t.nome_funcao)); // ou o nome do campo correspondente

    exibirCardsDeObra(dadosTarefas); // Mostra os cards

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

  let filtradas = dadosTarefas;
  if (funcaoVal)
    filtradas = filtradas.filter((t) => t.nome_funcao === funcaoVal);
  if (colabVal)
    filtradas = filtradas.filter((t) => t.nome_colaborador === colabVal);
  if (searchVal)
    filtradas = filtradas.filter(
      (t) =>
        (t.nome_obra || "").toLowerCase().includes(searchVal) ||
        (t.nomenclatura || "").toLowerCase().includes(searchVal),
    );

  exibirCardsDeObra(filtradas);
}

// Carrega métricas agregadas por função e renderiza no painel
async function loadMetrics() {
  try {
    const res = await fetch("getMetrics.php");
    if (!res.ok) throw new Error("Erro ao buscar métricas");
    const data = await res.json();

    const panel = document.getElementById("metrics-panel");
    if (!panel) return;
    panel.innerHTML = "";

    const grid = document.createElement("div");
    grid.style.display = "flex";
    grid.style.gap = "8px";
    grid.style.flexWrap = "wrap";

    data.forEach((row) => {
      const card = document.createElement("div");
      card.className = "metrics-card";
      card.style.padding = "8px 10px";
      card.style.background = "#f5f7fa";
      card.style.border = "1px solid #e0e6ef";
      card.style.borderRadius = "6px";
      card.style.minWidth = "160px";
      card.style.boxSizing = "border-box";

      const title = document.createElement("div");
      title.textContent = row.nome_funcao || "-";
      title.style.fontWeight = "600";
      title.style.marginBottom = "6px";

      const avg = document.createElement("div");
      avg.textContent = `Média (h): ${row.media_horas_em_aprovacao !== null ? row.media_horas_em_aprovacao : "-"} `;
      avg.style.color = "#333";

      const total = document.createElement("div");
      total.textContent = `Total: ${row.total_tarefas}`;
      total.style.color = "#666";

      card.appendChild(title);
      card.appendChild(avg);
      card.appendChild(total);

      // SLA breach count indicator
      if (row.sla_limite_horas) {
        const slaInfo = document.createElement("div");
        slaInfo.style.marginTop = "4px";
        slaInfo.style.fontSize = "11px";
        if (row.em_breach > 0) {
          slaInfo.innerHTML = `<span style="color:#ef4444;font-weight:700;">⚠ ${row.em_breach} acima do SLA (${row.sla_limite_horas}h)</span>`;
        } else {
          slaInfo.innerHTML = `<span style="color:#10b981;">✓ SLA OK (${row.sla_limite_horas}h)</span>`;
        }
        card.appendChild(slaInfo);
      }

      grid.appendChild(card);
    });

    panel.appendChild(grid);
  } catch (err) {
    console.error("Erro ao carregar métricas:", err);
  }
}

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
    if (!obrasMap.has(tarefa.nome_obra)) {
      obrasMap.set(tarefa.nome_obra, []);
    }
    obrasMap.get(tarefa.nome_obra).push(tarefa);
  });

  // Obras com menções não lidas primeiro
  const obrasOrdenadas = [...obrasMap.entries()].sort(
    ([a], [b]) =>
      (mencoes.mencoes_por_obra[b] || 0) - (mencoes.mencoes_por_obra[a] || 0),
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
      obrasDirMap[t.nome_obra] = (obrasDirMap[t.nome_obra] || 0) + 1;
    });
    const linhasDir = Object.entries(obrasDirMap)
      .map(([obra, qtd]) => `• <b>${obra}</b>: ${qtd} tarefa(s)`)
      .join("<br>");
    Swal.fire({
      title: "⏳ Aguardando sua validação!",
      html:
        linhasDir +
        "<br><br>Finalizadores aprovaram — aguardando confirmação da direção.",
      icon: "warning",
      confirmButtonText: "Ver",
    });
  }

  obrasOrdenadas.forEach(([nome_obra, tarefasDaObra]) => {
    tarefasDaObra.sort(
      (a, b) => new Date(b.data_aprovacao) - new Date(a.data_aprovacao),
    );
    const tarefaComImagem = tarefasDaObra.find((t) => t.imagem);
    // Use thumbnail for obra preview to reduce load
    const imagemPreview = tarefaComImagem
      ? `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(tarefaComImagem.imagem)}&w=450&q=85`
      : "../assets/logo.jpg";

    const mencoesNaObra = mencoes.mencoes_por_obra[nome_obra] || 0;
    const pendenteDirecaoNaObra = tarefasDaObra.filter(
      (t) => t.pendente_direcao && t.diretor_pode_aprovar,
    ).length;

    const card = document.createElement("div");
    card.classList.add("obra-card");

    card.innerHTML = `
        ${mencoesNaObra > 0 ? `<div class="mencao-badge">💬 ${mencoesNaObra}</div>` : ""}
        ${pendenteDirecaoNaObra > 0 ? `<div class="pendente-direcao-badge obra-direcao-badge" title="Aguardando validação da direção">⏳ ${pendenteDirecaoNaObra}</div>` : ""}
        <div class="obra-img-preview">
            <img src="${imagemPreview}" alt="Imagem da obra ${nome_obra}">
        </div>
        <div class="obra-info">
            <h3>${tarefasDaObra[0].nomenclatura}</h3>
            <p>${tarefasDaObra.length} aprovações</p>
        </div>
    `;

    card.addEventListener("click", () => {
      filtrarTarefasPorObra(nome_obra);
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
    (t) => t.nome_obra === obraSelecionada,
  );

  // Atualiza os filtros dinamicamente com base nessa obra
  atualizarFiltrosDinamicos(tarefasDaObra);

  // Captura os novos valores dos selects após atualização
  const colaboradorSelecionado =
    document.getElementById("filtro_colaborador").value;
  let funcaoSelecionada = document.getElementById("nome_funcao").value;

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

  if (tarefasDaObra.length > 0) {
    const obraId = tarefasDaObra[0].idobra; // ajuste se o campo for diferente
    const nomeObra = tarefasDaObra[0].nome_obra;
    const nomenclatura = tarefasDaObra[0].nomenclatura;

    const obraNavLinks = document.querySelectorAll(".obra_nav");

    obraNavLinks.forEach((link) => {
      link.href = `https://improov.com.br/flow/ImproovWeb/FlowReview/index.php?obra_nome=${nomeObra}`;
      link.textContent = nomenclatura;
    });
  }

  // Aplica os filtros adicionais (colaborador e função)
  const tarefasFiltradas = tarefasDaObra.filter((t) => {
    const matchColaborador =
      !colaboradorSelecionado || t.nome_colaborador === colaboradorSelecionado;
    const matchFuncao =
      funcaoSelecionada === "Todos" || t.nome_funcao === funcaoSelecionada;
    return matchColaborador && matchFuncao;
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

  // Salva os valores antes de atualizar
  const valorAnteriorColaborador = selectColaborador.value;
  const valorAnteriorFuncao = selectFuncao.value;

  const colaboradores = [...new Set(tarefas.map((t) => t.nome_colaborador))];
  const funcoes = [...new Set(tarefas.map((t) => t.nome_funcao))];

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
}

document.getElementById("filtro_colaborador").addEventListener("change", () => {
  const obraSelecionada = document.getElementById("filtro_obra").value;
  const colaboradorSelecionado =
    document.getElementById("filtro_colaborador").value;

  const tarefasDaObra = dadosTarefas.filter(
    (t) => t.nome_obra === obraSelecionada,
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
    (t) => t.nome_obra === obraSelecionada,
  );
  const tarefasFiltradas = tarefasDaObra.filter(
    (t) => funcaoSelecionada === "Todos" || t.nome_funcao === funcaoSelecionada,
  );

  atualizarSelectColaborador(tarefasFiltradas); // atualiza o outro filtro com base nesse

  filtrarTarefasPorObra(obraSelecionada);
});

// Função para exibir as tarefas e abastecer os filtros
function exibirTarefas(tarefas, tarefasCompletas) {
  const container = document.querySelector(".containerObra");
  container.style.display = "none"; // Esconde o container de obras

  const containerMain = document.querySelector(".container-main");
  // containerMain.classList.add('expanded');

  const tarefasObra = document.querySelector(".tarefasObra");
  tarefasObra.classList.remove("hidden");

  const tarefasImagensObra = document.querySelector(".tarefasImagensObra");

  tarefasImagensObra.innerHTML = ""; // Limpa as tarefas anteriores

  exibirSidebarTabulator(tarefasCompletas);

  if (tarefas.length > 0) {
    const tarefasOrdenadas = [...tarefas].sort((a, b) => {
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
      taskItem.setAttribute(
        "onclick",
        `historyAJAX(${tarefa.idfuncao_imagem}, '${tarefa.nome_funcao}', '${tarefa.imagem_nome}', '${tarefa.nome_colaborador}')`,
      );

      // use thumbnail for task list previews; full image used only in mostrarImagemCompleta
      const imagemPreview = tarefa.imagem
        ? `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(tarefa.imagem)}&w=450&q=85`
        : "../assets/logo.jpg";

      // Define a cor de fundo com base no status
      const color = tarefa.pendente_direcao
        ? "#4a3200"
        : tarefa.angulo_aprovado
          ? "#003322"
          : tarefa.status_novo === "Em aprovação"
            ? "#000a59"
            : tarefa.status_novo === "Ajuste"
              ? "#590000"
              : tarefa.status_novo === "Aprovado com ajustes"
                ? "#2e0059ff"
                : "transparent";
      const bgColor = tarefa.pendente_direcao
        ? "#ffd966"
        : tarefa.angulo_aprovado
          ? "#66ffcc"
          : tarefa.status_novo === "Em aprovação"
            ? "#90c2ff"
            : tarefa.status_novo === "Ajuste"
              ? "#ff5050"
              : tarefa.status_novo === "Aprovado com ajustes"
                ? "#ae90ffff"
                : "transparent";
      taskItem.innerHTML = `
                <div class="task-info">
                  <div class="image-wrapper">
                     <img src="${imagemPreview}" alt="Imagem da obra ${tarefa.nome_obra}" class="task-image" onerror="this.onerror=null;this.src='../assets/logo.jpg';">
                </div>
                    <h3 class="nome_funcao">${tarefa.nome_funcao}${tarefa.par_primario_nome ? `<span class="par-primario-badge" title="${tarefa.par_primario_nome}: ${tarefa.par_primario_status}"> + ${tarefa.par_primario_nome}</span>` : ""}</h3><span class="colaborador">${tarefa.nome_colaborador}</span>
                    <p class="imagem_nome" data-obra="${tarefa.nome_obra}">${tarefa.imagem_nome}</p>
                    <p class="data_aprovacao">${formatarDataHora(tarefa.data_aprovacao)}</p>       
                    <p id="status_funcao" style="color: ${color}; background-color: ${bgColor}">${tarefa.pendente_direcao ? "Aguardando Direção" : tarefa.angulo_aprovado ? "Ângulo aprovado" : tarefa.status_novo}</p>
                </div>
            `;

      // Badge de menções não vistas nesta tarefa
      const qtdMencoesTask =
        (_mencoesDados.mencoes_por_funcao_imagem || {})[
          String(tarefa.idfuncao_imagem)
        ] || 0;
      if (qtdMencoesTask > 0) {
        const badge = document.createElement("div");
        badge.classList.add("mencao-badge");
        badge.setAttribute("data-task-badge", tarefa.idfuncao_imagem);
        badge.textContent = `💬 ${qtdMencoesTask}`;
        taskItem.appendChild(badge);
      }

      // Badge de pendência de direção (⏳) — canto superior esquerdo
      if (tarefa.pendente_direcao && tarefa.diretor_pode_aprovar) {
        const dirBadge = document.createElement("div");
        dirBadge.classList.add("pendente-direcao-badge");
        dirBadge.setAttribute("data-direcao-badge", tarefa.idfuncao_imagem);
        dirBadge.textContent = "⏳";
        dirBadge.title = "Aguardando validação da direção";
        taskItem.appendChild(dirBadge);
      }

      // SLA timer badge — only for tasks in "Em aprovação" with SLA data
      if (
        tarefa.status_novo === "Em aprovação" &&
        tarefa.sla_inicio &&
        tarefa.sla_limite_horas
      ) {
        const slaBadge = document.createElement("div");
        slaBadge.classList.add("sla-timer");
        slaBadge.dataset.slaInicio = tarefa.sla_inicio;
        slaBadge.dataset.slaLimite = tarefa.sla_limite_horas;
        const { expirado, texto } = calcSlaTimer(
          tarefa.sla_inicio,
          tarefa.sla_limite_horas,
        );
        slaBadge.textContent = texto;
        if (expirado) slaBadge.classList.add("sla-breach");
        slaBadge.title = expirado
          ? `SLA excedido! Limite: ${tarefa.sla_limite_horas}h`
          : `Em aprovação há ${texto.replace("⏱ ", "")} (limite: ${tarefa.sla_limite_horas}h)`;
        taskItem.appendChild(slaBadge);
      }

      tarefasImagensObra.appendChild(taskItem);
    });
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
  const inicioDate = new Date(String(inicio).replace(" ", "T"));
  const horasDecorridas = (Date.now() - inicioDate.getTime()) / 36e5;
  const expirado = horasDecorridas >= limiteHoras;
  const h = Math.floor(horasDecorridas);
  const m = Math.floor((horasDecorridas % 1) * 60);
  const texto = expirado
    ? `⚠ ${h}h ${m}min`
    : `⏱ ${h}h ${m}min`;
  return { expirado, texto, horasDecorridas };
}

// Live-update all visible SLA timer badges every 60 seconds
setInterval(() => {
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

let funcaoImagemId = null; // armazenado globalmente
let currentFuncaoContext = null; // {imagem_id, funcao_imagem_id, colaborador_id, nome_funcao, nome_status, imagem_nome}
let currentIndiceEnvio = null;

function isP00FinalizacaoContext(context) {
  const isP00 = String(context?.nome_status || "").toLowerCase() === "p00";
  const nomeFuncao = String(context?.nome_funcao || "").toLowerCase();
  return isP00 && nomeFuncao === "finalização";
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

function historyAJAX(idfuncao_imagem) {
  funcaoImagemId = idfuncao_imagem;

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

  fetch(`historico.php?ajid=${idfuncao_imagem}`)
    .then((response) => response.json())
    .then((response) => {
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

      // Sincroniza sidebarTabulator com a função da tarefa aberta
      const tarefaAtual = dadosTarefas.find(
        (t) => t.idfuncao_imagem == idfuncao_imagem,
      );
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
            const reversed = [...historico].slice().reverse();
            const approver =
              reversed.find((h) => h.responsavel && h.responsavel !== "0") ||
              null;
            if (approver) {
              const name = approver.responsavel_nome || "—";
              const status = approver.status_novo || approver.status || "—";
              const dt = approver.data_aprovacao || approver.data || null;
              const fecha = dt ? formatarDataHora(new Date(dt)) : "";
              const displayStatus =
                status === "Aguardando Direção" ? "Aprovado" : status;
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
        imagens.length > 0;

      const podeAprovar =
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
          btnOpen.style.display = "flex";
          modal.classList.add("hidden");
          btnOpen.addEventListener("click", () => {
            abrirModalEscolhaAngulo();
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

            revisarTarefa(
              item.funcao_imagem_id,
              item.colaborador_nome,
              item.imagem_nome,
              item.nome_funcao,
              item.colaborador_id,
              item.imagem_id,
              selected,
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
        dataEnvioHeader.textContent = `Enviado em: ${formatarDataHora(dataValor)}`;
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
              mostrarImagemCompleta(
                `https://improov.com.br/flow/ImproovWeb/${maisRecente.imagem}`,
                maisRecente.id,
              );
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

            const imgElement = document.createElement("img");
            // thumbnail for gallery thumbnails; clicking opens full image via mostrarImagemCompleta
            const fullImageUrl = `https://improov.com.br/flow/ImproovWeb/${encodeURI(img.imagem)}`;
            imgElement.src = `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(img.imagem)}&w=200&q=85`;
            imgElement.alt = img.imagem;
            imgElement.className = "image";
            imgElement.setAttribute("data-id", img.id);

            imgElement.addEventListener("click", () => {
              mostrarImagemCompleta(fullImageUrl, img.id);
            });

            imgElement.addEventListener("contextmenu", (event) => {
              event.preventDefault();
              ap_imagem_id = img.id;
              abrirMenuContexto(event.pageX, event.pageY, img.id, fullImageUrl);
            });

            if (img.has_comments == "1" || img.has_comments === 1) {
              const notificationDot = document.createElement("div");
              notificationDot.className = "notification-dot";
              notificationDot.textContent = `${img.comment_count}`;
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
      const color =
        t.status_novo === "Em aprovação"
          ? "#000a59"
          : t.status_novo === "Ajuste"
            ? "#590000"
            : t.status_novo === "Aprovado com ajustes"
              ? "#2e0059ff"
              : t.status_novo === "Aprovado"
                ? "#155900"
                : t.pendente_direcao
                  ? "#4a3200"
                  : "transparent";
      const bgColor =
        t.status_novo === "Em aprovação"
          ? "#90c2ff"
          : t.status_novo === "Ajuste"
            ? "#ff5050"
            : t.status_novo === "Aprovado com ajustes"
              ? "#ae90ffff"
              : t.status_novo === "Aprovado"
                ? "#6ed64e"
                : t.pendente_direcao
                  ? "#ffd966"
                  : "transparent";

      const item = document.createElement("div");
      item.className = "tarefa-item";
      item.dataset.id = t.idfuncao_imagem;
      const imgSrc = t.imagem
        ? `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(t.imagem)}&w=400&q=85`
        : "../assets/logo.jpg";
      item.innerHTML = `
        <img src="${imgSrc}" class="tab-img" alt="${escapeHtml(t.imagem_nome || "")}">
        <span class="tarefa-status" style="background-color:${bgColor};color:${color}">${escapeHtml(t.status_novo || "")}</span>
        <span class="tarefa-label">${escapeHtml(t.nome_colaborador || "")} — ${escapeHtml(t.imagem_nome || "")}</span>
      `;
      item.addEventListener("click", () => {
        itemsDiv
          .querySelectorAll(".tarefa-item")
          .forEach((el) => el.classList.remove("active"));
        item.classList.add("active");
        historyAJAX(t.idfuncao_imagem);
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
});

let tribute; // variável global
let mencionadosIds = []; // armazenar os IDs dos mencionados
let _cachedUsers = []; // cache da lista de usuários para highlightMentions
let _editingCommentId = null; // ID do comentário em edição (null = novo comentário)
let _replyingToCommentId = null; // ID do comentário sendo respondido (null = não é resposta)
let _editingReplyId = null; // ID da resposta em edição (null = não é edição de resposta)
let quillComentario = null; // instância do Quill no modal de comentário
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
  const imageWrapper = document.getElementById("image_wrapper");
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
    });
  }

  // Busca de função na sidebar (fr-section-tarefas)
  const frSearchFuncao = document.getElementById("fr-search-funcao");
  if (frSearchFuncao) {
    frSearchFuncao.addEventListener("input", () => {
      const val = frSearchFuncao.value.toLowerCase().trim();
      const sel = document.getElementById("nome_funcao");
      if (!sel) return;
      Array.from(sel.options).forEach((opt) => {
        if (opt.value === "Todos") {
          opt.hidden = false;
          opt.disabled = false;
          return;
        }
        const matches = !val || opt.text.toLowerCase().includes(val);
        opt.hidden = !matches;
        opt.disabled = !matches;
      });
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
  ap_imagem_id = id;
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

  imageWrapper.classList.remove("pdf-mode");

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
      renderComments(ap_imagem_id);
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
  formData.append("x", relativeX);
  formData.append("y", relativeY);
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
        renderComments(ap_imagem_id);
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
    wrapper.classList.add("highlight");
    const cardNum = document.querySelector(
      `.comment-card[data-id="${comentario.id}"] .comment-number`,
    );
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

async function renderComments(id) {
  // console.log("renderComments", id); // debug
  const comentariosDiv = document.querySelector(".comentarios");
  comentariosDiv.innerHTML = "";
  const imagemCompletaDiv = document.getElementById("image_wrapper");

  const isPdf = typeof id === "object" && id && id.arquivo_log_id;
  const markerContainer = isPdf
    ? document.getElementById("pdf_comment_layer") || imagemCompletaDiv
    : imagemCompletaDiv;

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
      pdfCommentsCache.logId = logId;
      pdfCommentsCache.comentarios = Array.isArray(all) ? all : [];
      pdfCommentsCache.fetchedAt = Date.now();
    }

    comentarios = pdfCommentsCache.comentarios || [];
  } else {
    const url = `buscar_comentarios.php?id=${encodeURIComponent(String(id))}`;
    const response = await fetch(url);
    const data = await response.json();
    comentarios = Array.isArray(data) ? data : [];
  }

  // Remove marcadores anteriores (pontos, formas e freehand)
  markerContainer
    .querySelectorAll(".comment, .comment-shape, .comment-freehand")
    .forEach((c) => c.remove());

  // Oculta a sidebar-direita se não houver comentários
  if (comentarios.length === 0) {
    comentariosDiv.style.display = "none";
  } else {
    comentariosDiv.style.display = "flex";
  }

  const users = await fetch("buscar_usuarios.php").then((res) => res.json());
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
    const pageInfo =
      isPdf && comentario.pagina
        ? `<div class="comment-page">Pág. ${comentario.pagina}</div>`
        : "";
    header.innerHTML = `
            <div class="comment-number">${comentario.numero_comentario}</div>
            <div class="comment-user">${comentario.nome_responsavel}</div>
            ${pageInfo}
        `;

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

    let commentDiv = document.createElement("div");
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
    } else if (isShape) {
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
    } else {
      commentDiv.classList.add("comment");
      commentDiv.innerText = comentario.numero_comentario;
      commentDiv.style.left = `${comentario.x}%`;
      commentDiv.style.top = `${comentario.y}%`;
      commentDiv.style.backgroundColor = cor;
      commentDiv.style.color = "#fff";
    }
    commentDiv.setAttribute("data-id", comentario.id);

    // Generic marker click (ponto + shapes; freehand uses SVG hit handler above)
    if (!isFreehand) {
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
    if (!isPdf) {
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
        renderComments(ap_imagem_id);
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
      if (_mtFh) _mtFh.textContent = "Novo Comentário";
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
