if (colaborador_id === 9 || colaborador_id === 21) {
  document.getElementById("idcolab").style.display = "flex"; // libera
} else {
  document.getElementById("idcolab").style.display = "none"; // esconde
}
// const idusuario = 1;

document.getElementById("idcolab").addEventListener("change", function () {
  const idcolab = parseInt(this.value, 10);
  carregarDados(idcolab);
});

// Converte um caminho SFTP/servidor para a URL pública onde os JPGs ficam acessíveis
// Ex: /mnt/clientes/2025/TES_TES/05.Exchange/01.Input/Angulo_definido/Fachada/IMG/teste2/file.jpg
// => https://improov.com.br/uploads/angulo_definido/Fachada/IMG/teste2/file.jpg
function sftpToPublicUrl(rawPath) {
  if (!rawPath) return null;
  // normaliza barras
  const p = rawPath.replace(/\\/g, "/");
  // Primeira tentativa: detectar caminho completo com nomenclatura
  // /mnt/clientes/<ano>/<nomenclatura>/05.Exchange/01.Input/<rest>
  const mFull = p.match(
    /\/mnt\/clientes\/\d+\/([^\/]+)\/05\.Exchange\/01\.Input\/(.*)/i,
  );
  if (mFull && mFull[1] && mFull[2]) {
    const nomen = mFull[1];
    const rest = mFull[2];
    // Monta com a nomenclatura logo após angulo_definido conforme solicitado
    return (
      "https://improov.com.br/flow/ImproovWeb/uploads/angulo_definido/" +
      nomen +
      "/" +
      rest
    );
  }

  // Segunda tentativa: localizar Angulo_definido no caminho e usar o que vem depois
  const m = p.match(/\/Angulo_definido\/(.*)/i);
  if (m && m[1]) {
    return (
      "https://improov.com.br/flow/ImproovWeb/uploads/angulo_definido/" + m[1]
    );
  }

  // Terceira tentativa: pega tudo depois de /05.Exchange/01.Input/
  const idx = p.indexOf("/05.Exchange/01.Input/");
  if (idx >= 0) {
    const after = p.substring(idx + "/05.Exchange/01.Input/".length);
    return "https://improov.com.br/flow/ImproovWeb/uploads/" + after;
  }

  return null;
}

function carregarDados(colaborador_id) {
  let url = `PaginaPrincipal/getFuncoesPorColaborador.php?colaborador_id=${colaborador_id}`;

  const xhr = new XMLHttpRequest();

  // Mostra loading quando iniciar a requisição
  xhr.addEventListener("loadstart", () => {
    document.getElementById("loading").style.display = "block";
  });

  // Esconde loading quando terminar
  xhr.addEventListener("loadend", () => {
    document.getElementById("loading").style.display = "none";
  });

  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4) {
      if (xhr.status === 200) {
        try {
          const data = JSON.parse(xhr.responseText);

          // Atualiza mini-calendar (se implementado)
          if (window.updateMiniCalendarWithData) {
            try {
              window.updateMiniCalendarWithData(data);
            } catch (e) {
              console.error("mini-calendar update error", e);
            }
          }

          // Chama o tratamento do kanban
          processarDados(data);

          // Atualiza a lista (tabela) quando disponível
          if (window.updateListaTabela) {
            try {
              window.updateListaTabela(data);
            } catch (e) {
              console.error("updateListaTabela error", e);
            }
          }
        } catch (err) {
          console.error("Erro ao parsear JSON:", err);
        }
      } else {
        console.error("Erro na requisição:", xhr.status);
      }
    }
  };

  xhr.open("GET", url, true);
  xhr.send();
}

let ultimoResumoPendencia = "";

function abrirModalUploadFinalPendente(card) {
  if (!card || !cardModal) return;

  cardSelecionado = card;
  idfuncao_imagem = card.getAttribute("data-id");
  idimagem = card.getAttribute("data-id-imagem");
  titulo = card.querySelector("h5")?.innerText || "";
  subtitulo = card.getAttribute("data-funcao_nome");
  obra = card.getAttribute("data-obra_nome");
  nome_status = card.getAttribute("data-nome_status");

  modalPrazo.value = card.dataset.prazo || "";
  modalObs.value = card.dataset.observacao || "";

  imagensSelecionadas = [];
  arquivosFinais = [];
  renderizarLista(imagensSelecionadas, "fileListPrevia");
  renderizarLista(arquivosFinais, "fileListFinal");

  document.querySelector(".modalPrazo").style.display = "none";
  document.querySelector(".modalObs").style.display = "none";
  document.querySelector(".modalUploads").style.display = "flex";
  document.querySelector(".buttons").style.display = "none";
  document.querySelector(".statusAnterior").style.display = "none";

  const etapaPreviaEl = document.getElementById("etapaPrevia");
  const etapaFinalEl = document.getElementById("etapaFinal");
  if (etapaPreviaEl) etapaPreviaEl.style.display = "none";
  if (etapaFinalEl) etapaFinalEl.style.display = "";

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

  cardModal.classList.add("active");
  card.classList.add("selected");

  const modalWidth = cardModal.offsetWidth || 400;
  const modalHeight = cardModal.offsetHeight || 560;
  const left = Math.max(10, Math.round((window.innerWidth - modalWidth) / 2));
  const top = Math.max(10, Math.round((window.innerHeight - modalHeight) / 2));
  cardModal.style.left = `${left}px`;
  cardModal.style.top = `${top}px`;
}

function alertarPendenciasSeNecessario(data) {
  const funcoes = data && Array.isArray(data.funcoes) ? data.funcoes : [];
  const pendentes = funcoes.filter(
    (item) => Number(item.requires_file_upload || 0) === 1,
  );
  const quantidade = pendentes.length;

  if (quantidade <= 0) {
    ultimoResumoPendencia = "";
    return;
  }

  const chave = `${colaborador_id}:${quantidade}`;
  if (ultimoResumoPendencia === chave) return;
  ultimoResumoPendencia = chave;

  Swal.fire({
    icon: "warning",
    title: "Arquivo pendente",
    html: `Você tem <b>${quantidade}</b> card(s) com arquivo pendente.`,
    showCancelButton: true,
    confirmButtonText: "Enviar agora",
    cancelButtonText: "Depois",
  }).then((result) => {
    if (!result.isConfirmed) return;
    const primeiroCardPendente = document.querySelector(
      '.kanban-card.tarefa-imagem[data-requires-file-upload="1"]',
    );
    if (primeiroCardPendente) {
      abrirModalUploadFinalPendente(primeiroCardPendente);
    }
  });
}

// extrai a lógica do fetch para uma função reutilizável
function processarDados(data) {
  const statusMap = {
    "Não iniciado": "to-do",
    "Em andamento": "in-progress",
    "Em aprovação": "in-review",
    Ajuste: "ajuste",
    Aprovado: "aprovado",
    "Aprovado com ajustes": "aprovado",
    Finalizado: "done",
    HOLD: "hold",
  };

  // Ensure standard columns are reset
  Object.values(statusMap).forEach((colId) => {
    const col = document.getElementById(colId);
    if (col) {
      col.style.display = "";
      col.querySelector(".content").innerHTML = "";
    }
  });
  // Extra column for "Aprovado com ajustes"
  const extraCols = ["aprovado-ajustes"];
  extraCols.forEach((colId) => {
    const col = document.getElementById(colId);
    if (col) {
      col.style.display = "";
      col.querySelector(".content").innerHTML = "";
    }
  });
  // Função auxiliar para criar cards
  function criarCard(item, tipo, media) {
    // Define status real (mantemos 'Aprovado com ajustes' separado)
    // Normalize incoming status: trim and compare case-insensitively
    const rawStatus = (item.status || "Não iniciado").toString().trim();
    const s = rawStatus.toLowerCase();
    let status = "Não iniciado";
    if (s === "ajuste") status = "Ajuste";
    else if (s === "em aprovação" || s === "em aprovacao")
      status = "Em aprovação";
    else if (s === "em andamento" || s === "em-andamento")
      status = "Em andamento";
    else if (s === "aprovado") status = "Aprovado";
    else if (s === "aprovado com ajustes" || s === "aprovado_com_ajustes") {
      // Para Finalização (funcao_id=4), manter como "Aprovado com ajustes" sempre.
      const fid = Number(item.funcao_id || item.funcaoId || 0);
      if (fid === 4) {
        status = "Aprovado com ajustes";
      } else {
        // Se já existe arquivo associado à função, mostramos visualmente como Finalizado
        // mas NÃO alteramos o status no banco (isso é responsabilidade do backend).
        if (item.requires_file_upload == 0) {
          status = "Finalizado";
        } else {
          status = "Aprovado com ajustes";
        }
      }
    } else if (s === "finalizado") status = "Finalizado";
    else if (
      s === "não iniciado" ||
      s === "nao iniciado" ||
      s === "não-iniciado"
    )
      status = "Não iniciado";
    else if (s === "hold") status = "HOLD";
    else status = rawStatus || "Não iniciado";

    // default mapping
    let colunaId = statusMap[status];
    // special-case: 'Aprovado com ajustes' should go to its own column
    // but ONLY for função finalização (funcao_id == 4). Otherwise fall back to 'aprovado'.
    if (status === "Aprovado com ajustes") {
      try {
        const fid = Number(item.funcao_id || item.funcaoId || 0);
        if (fid === 4) {
          colunaId = "aprovado-ajustes";
        } else {
          colunaId = "aprovado";
        }
      } catch (e) {
        colunaId = "aprovado";
      }
    }
    // DEBUG: log status mapping for troubleshooting (use console.log to ensure visibility)
    try {
      const parentBox = document
        .getElementById(colunaId)
        ?.closest(".kanban-box");
      const parentId = parentBox ? parentBox.id : null;
      const parentTitle = parentBox
        ? parentBox.querySelector(".title span")?.textContent
        : null;
    } catch (e) {
      console.error("criarCard debug error", e);
    }
    const coluna = document.getElementById(colunaId)?.querySelector(".content");
    if (!coluna) return;

    // Se já existe um card com este id em outra coluna, remove-o antes
    try {
      if (item.idfuncao_imagem) {
        const existing = document.querySelector(
          `.kanban-card[data-id="${item.idfuncao_imagem}"]`,
        );
        if (existing) {
          existing.remove();
          console.log(
            "[criarCard] removed existing duplicate for idfuncao_imagem=",
            item.idfuncao_imagem,
          );
        }
      }
    } catch (e) {
      /* ignore */
    }

    // Define a classe da tarefa (criada ou imagem)
    const tipoClasse = tipo === "imagem" ? "tarefa-imagem" : "tarefa-criada";

    // Normaliza prioridade (número ou string)
    if (item.prioridade == 3 || item.prioridade === "baixa") {
      item.prioridade = "baixa";
    } else if (
      item.prioridade == 2 ||
      item.prioridade === "media" ||
      item.prioridade === "média"
    ) {
      item.prioridade = "media";
    } else {
      item.prioridade = "alta";
    }

    // Nome a exibir
    const titulo = tipo === "imagem" ? item.imagem_nome : item.titulo;
    const subtitulo = tipo === "imagem" ? item.nome_funcao : item.descricao;

    function getTempoClass(tempo, media) {
      if (!tempo || tempo === 0) return ""; // sem tempo registrado

      if (tempo <= media) {
        return "tempo-bom"; // verde
      } else if (tempo <= media * 1.3) {
        return "tempo-atenção"; // amarelo
      } else {
        return "tempo-ruim"; // vermelho
      }
    }

    // Pega a média da função específica
    const mediaFuncao = media[item.funcao_id] || 0;

    // Bolinha só no "Não iniciado"
    let bolinhaHTML = "";
    let liberado =
      String(item.liberada) === "false" || Number(item.liberada) === 0
        ? "0"
        : "1";

    // Cria card
    const card = document.createElement("div");
    card.className = `kanban-card ${tipoClasse}`; // só a classe base
    const hasPendingFile =
      tipo === "imagem" && Number(item.requires_file_upload || 0) === 1;
    let cardEmHold = false;
    let imagemEmHold = false;

    if (hasPendingFile) {
      card.classList.add("arquivo-pendente");
    }

    if (tipo === "imagem") {
      // lógica específica para imagem
      const nomeStatusImagem = (item.nome_status || "")
        .toString()
        .trim()
        .toLowerCase();
      const imagemStatusId = Number(item.imagem_status_id || 0);
      imagemEmHold = nomeStatusImagem === "hold" || imagemStatusId === 7;

      if (imagemEmHold) {
        bolinhaHTML = `<span class="bolinha vermelho" data-status-anterior="${item.status_funcao_anterior || ""}"></span>`;
        liberado = "0";
        cardEmHold = true;
      } else if (status === "Não iniciado") {
        const statusAnterior = item.status_funcao_anterior || "";
        if (
          ["Aprovado", "Finalizado", "Aprovado com ajustes"].includes(
            statusAnterior,
          )
        ) {
          bolinhaHTML = `<span class="bolinha verde" data-status-anterior="${statusAnterior}"></span>`;
          liberado = "1";
        } else if (item.liberada) {
          bolinhaHTML = `<span class="bolinha verde" data-status-anterior="${statusAnterior || ""}"></span>`;
          liberado = "1";
        } else if (item.nome_funcao === "Filtro de assets") {
          bolinhaHTML = `<span class="bolinha verde" data-status-anterior="${statusAnterior || ""}"></span>`;
          liberado = "1";
        } else {
          bolinhaHTML = `<span class="bolinha vermelho" data-status-anterior="${statusAnterior || ""}"></span>`;
          liberado = "0";
        }
      }

      // store original previous status (comes from getFuncoesPorColaborador -> status_funcao_anterior)
      const statusAnteriorFull = item.status_funcao_anterior || "";

      card.setAttribute("data-id", `${item.idfuncao_imagem}`);
      card.setAttribute("data-status-anterior", statusAnteriorFull);
      card.setAttribute("data-id-imagem", `${item.imagem_id}`);
      card.setAttribute("data-id-funcao", `${item.funcao_id}`);
      card.setAttribute("liberado", liberado);
      card.dataset.liberado = liberado;
      card.setAttribute("data-nome_status", `${item.nome_status}`); // para filtro
      card.setAttribute("data-prazo", `${item.prazo}`); // para filtro
      card.dataset.imagemEmHold = imagemEmHold ? "1" : "0";
      card.dataset.requiresFileUpload = String(
        Number(item.requires_file_upload || 0),
      );
    } else {
      // lógica para tarefas criadas
      bolinhaHTML = "";
      // 🟢 Lógica para tarefas criadas
      card.dataset.id = item.id; // apenas id simples
      card.dataset.titulo = item.titulo; // se precisar para modal
      card.dataset.descricao = item.descricao;
      card.dataset.prazo = item.prazo;
      card.dataset.status = item.status;
      card.dataset.prioridade = item.prioridade;
      card.setAttribute("liberado", "1"); // sempre liberado
      card.dataset.liberado = "1";
      card.dataset.imagemEmHold = "0";
      card.dataset.requiresFileUpload = "0";
    }

    const holdMovel = tipo === "imagem" && status === "HOLD" && !cardEmHold;

    // adiciona bloqueado se necessário
    if (liberado === "0" && !holdMovel) {
      card.classList.add("bloqueado");
    }

    if (cardEmHold) {
      const holdMotivo = (
        item.hold_justificativa_recente ||
        item.descricao ||
        item.justificativa ||
        ""
      )
        .toString()
        .trim();
      const holdTexto = holdMotivo
        ? `Imagem em HOLD\nMotivo: ${holdMotivo}`
        : "Imagem em HOLD\nMotivo: não informado";

      card.addEventListener("mouseenter", (event) => {
        showHoldTooltip(event, holdTexto);
      });

      card.addEventListener("mousemove", (event) => {
        moveHoldTooltip(event);
      });

      card.addEventListener("mouseleave", () => {
        hideHoldTooltip();
      });
    }

    function isAtrasada(prazoStr) {
      // Divide a string 'YYYY-MM-DD'
      const [ano, mes, dia] = prazoStr.split("-").map(Number);
      const prazo = new Date(ano, mes - 1, dia);

      const hoje = new Date();
      const hojeLimpo = new Date(
        hoje.getFullYear(),
        hoje.getMonth(),
        hoje.getDate(),
      );

      return prazo < hojeLimpo;
    }

    // Marca como atrasada apenas se estiver 'Em andamento' e o prazo já passou
    const atrasada =
      status === "Em andamento" && item.prazo ? isAtrasada(item.prazo) : false;

    // Normalize ultima_imagem: if it's an SFTP server path (/mnt/clientes/...), convert to public URL
    const ultimaImagemRaw = item.ultima_imagem || "";
    let ultimaImagemPublic = ultimaImagemRaw;
    try {
      if (
        typeof ultimaImagemPublic === "string" &&
        ultimaImagemPublic.startsWith("/mnt/clientes")
      ) {
        ultimaImagemPublic = sftpToPublicUrl(ultimaImagemPublic);
      }
    } catch (e) {
      // if conversion fails, fallback to raw path
      console.error("sftpToPublicUrl error for", ultimaImagemRaw, e);
      ultimaImagemPublic = ultimaImagemRaw;
    }

    // Decide image src: if we have an http(s) public URL, use it directly; otherwise use thumb.php to generate a thumbnail
    let imgSrc = "";

    // Special override: se for o colaborador Marcio (id 8) ou nome 'Marcio', usar a imagem local fixa
    try {
      // const nomeColl = String(item.nome_colaborador || '').trim();
      // if ((typeof colaborador_id !== 'undefined' && Number(colaborador_id) === 8) || nomeColl === 'Marcio') {
      //     imgSrc = 'assets/marcio_cafezinho.jpg';
      // } else {
      if (ultimaImagemPublic) {
        if (
          ultimaImagemPublic.startsWith("http://") ||
          ultimaImagemPublic.startsWith("https://")
        ) {
          imgSrc = ultimaImagemPublic;
        } else {
          imgSrc = `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(ultimaImagemPublic)}&w=360&q=70`;
        }
      } else {
        imgSrc = `https://improov.com.br/flow/ImproovWeb/${ultimaImagemPublic || ""}`;
      }
      // }
    } catch (e) {
      // fallback to original logic if anything falhar
      if (ultimaImagemPublic) {
        if (
          ultimaImagemPublic.startsWith("http://") ||
          ultimaImagemPublic.startsWith("https://")
        ) {
          imgSrc = ultimaImagemPublic;
        } else {
          imgSrc = `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURIComponent(ultimaImagemPublic)}&w=360&q=70`;
        }
      } else {
        imgSrc = `https://improov.com.br/flow/ImproovWeb/${ultimaImagemPublic || ""}`;
      }
    }

    card.innerHTML = `
                    ${hasPendingFile ? `<div class="pending-file-ribbon"><i class="ri-alert-line"></i> Arquivo pendente</div>` : ""}
                    <div class="header-kanban">
                        <span class="priority ${item.prioridade || "medium"}">
                            ${item.prioridade || "Medium"}
                        </span>
                        ${bolinhaHTML}
                        ${
                          item.notificacoes_nao_lidas &&
                          Number(item.notificacoes_nao_lidas) > 0
                            ? `
                            <span class="notif-icon" title="${item.notificacoes_nao_lidas} notificação(s)">
                                <i class="ri-notification-3-line"></i>
                                <small class="notif-count">${item.notificacoes_nao_lidas}</small>
                            </span>
                        `
                            : ""
                        }
                    </div>
                        <h5>${titulo || "-"}</h5>
                        <!-- Use server-side thumb generator to reduce weight for thumbnails -->
                        <img loading="lazy" src="${imgSrc}" alt="" style="max-width: 100%; height: auto; margin-bottom: 8px;">
                        <p>${subtitulo || "-"}</p>
                    <div class="card-footer">
                        <span class="date ${atrasada ? "atrasada" : ""}">
                            <i class="fa-regular fa-calendar"></i>
                            ${item.prazo ? formatarData(item.prazo) : "-"}
                        </span>
                    </div>
                    <div class="card-log">
                            <span 
                                class="date tooltip ${getTempoClass(item.tempo_calculado, mediaFuncao)}" 
                                data-tooltip="${formatarDuracao(mediaFuncao)}"
                                data-inicio="${item.tempo_calculado || ""}">
                                <i class="ri-time-line"></i> 
                                ${item.tempo_calculado ? formatarDuracao(item.tempo_calculado) : "-"}
                                </span>
                    <div class="comments">
                        ${item.indice_envio_atual ? `<span class="indice_envio"><i class="ri-file-line"></i> ${item.indice_envio_atual} |</span>` : ""}
                        ${
                          item.indice_envio_atual
                            ? item.comentarios_ultima_versao > 0
                              ? `<span class="numero_comments"><i class="ri-chat-3-line"></i> ${item.comentarios_ultima_versao}</span>`
                              : `<span class="numero_comments">0</span>`
                            : ""
                        }
                    </div>

                    </div>
                `;

    // Atributos para filtros
    card.dataset.obra_nome = item.nomenclatura || ""; // nome da obra
    card.dataset.funcao_nome = item.nome_funcao || ""; // nome da função
    card.dataset.status = status; // status normalizado

    card.addEventListener("click", () => {
      document
        .querySelectorAll(".kanban-card.selected")
        .forEach((c) => c.classList.remove("selected"));
      card.classList.add("selected");
      if (card.classList.contains("tarefa-criada")) {
        const idTarefa = card.dataset.id;
        abrirSidebarTarefaCriada(idTarefa);
      } else if (card.classList.contains("tarefa-imagem")) {
        if (card.dataset.requiresFileUpload === "1") {
          Swal.fire({
            icon: "warning",
            title: "Arquivo pendente",
            text: "Este card possui arquivo pendente. Deseja enviar o arquivo final agora?",
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: "Enviar arquivo",
            denyButtonText: "Ver detalhes",
            cancelButtonText: "Fechar",
          }).then((result) => {
            if (result.isConfirmed) {
              abrirModalUploadFinalPendente(card);
              return;
            }
            if (result.isDenied) {
              const idFuncao = card.dataset.id;
              const idImagem = card.dataset.idImagem;
              abrirSidebar(idFuncao, idImagem);
            }
          });
          return;
        }
        const idFuncao = card.dataset.id;
        const idImagem = card.dataset.idImagem;
        abrirSidebar(idFuncao, idImagem);
      }
    });

    if (liberado === "1") {
      // Inserir no topo da coluna, antes dos bloqueados
      const primeiroBloqueado = coluna.querySelector(".kanban-card.bloqueado");
      if (primeiroBloqueado) {
        coluna.insertBefore(card, primeiroBloqueado);
      } else {
        coluna.appendChild(card);
      }
    } else {
      // Bloqueados vão no final
      coluna.appendChild(card);
    }
  }

  // Adiciona tarefas criadas
  if (data.tarefas) {
    data.tarefas.forEach((item) => criarCard(item, "criada", {}));
  }

  // Adiciona funções (tarefas de imagem)
  if (data.funcoes) {
    data.funcoes.forEach((item) =>
      criarCard(item, "imagem", data.media_tempo_em_andamento),
    );
  }

  atualizarTaskCount();

  preencherFiltros();
  alertarPendenciasSeNecessario(data);
}

document.getElementById("modalDaily").style.display = "none";

// checkDailyAccess agora retorna uma Promise
function checkDailyAccess() {
  return new Promise((resolve, reject) => {
    const modalDaily = document.getElementById("modalDaily");
    const dailyForm = document.getElementById("dailyForm");

    if (modalDaily) modalDaily.style.display = "none";

    fetch("verifica_respostas.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `idcolaborador=${idColaborador}`,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.hasResponses) {
          // Se já respondeu, segue para checkRender
          if (modalDaily) modalDaily.style.display = "none";
          resolve();
        } else {
          // Se não respondeu, exibe modal e interrompe fluxo (não resolve ainda)
          if (!dailyForm) {
            reject();
            return;
          }

          if (modalDaily) modalDaily.style.display = "flex";
          // Resolve apenas após o envio do formulário
          dailyForm.addEventListener("submit", function onSubmit(e) {
            e.preventDefault();
            this.removeEventListener("submit", onSubmit); // evita múltiplas submissões

            const formData = new FormData(this);

            fetch("submit_respostas.php", {
              method: "POST",
              body: formData,
            })
              .then((response) => response.json())
              .then((data) => {
                if (data.success) {
                  if (modalDaily) modalDaily.style.display = "none";
                  Swal.fire({
                    icon: "success",
                    text: "Respostas enviadas com sucesso!",
                    showConfirmButton: false,
                    timer: 1200,
                  }).then(() => {
                    if (typeof checkFuncoesEmAndamento === "function") {
                      checkFuncoesEmAndamento(idColaborador)
                        .catch((err) =>
                          console.error(
                            "Erro ao checar funções em andamento após Daily:",
                            err,
                          ),
                        )
                        .finally(() => resolve());
                    } else {
                      resolve();
                    }
                  });
                } else {
                  Swal.fire({
                    icon: "error",
                    text: "Erro ao enviar as tarefas, tente novamente!",
                    showConfirmButton: false,
                    timer: 2000,
                  });
                  reject(); // interrompe a sequência
                }
              })
              .catch((error) => {
                console.error("Erro:", error);
                reject();
              });
          });
        }
      })
      .catch((error) => {
        console.error("Erro ao verificar respostas:", error);
        reject();
      });
  });
}

function checkFuncoesSomentePrimeiroAcesso() {
  const hoje = new Date().toISOString().split("T")[0]; // ex: 2025-09-25
  const chave = "funcoes_visto_" + hoje;

  if (!localStorage.getItem(chave)) {
    // Primeira vez no dia → chama a verificação primeiro.
    // Só marca como visto após a verificação completar, assim falhas não impedem
    // novas tentativas durante o dia.
    if (typeof checkFuncoesEmAndamento === "function") {
      return checkFuncoesEmAndamento(idColaborador)
        .then(() => {
          try {
            localStorage.setItem(chave, "1");
          } catch (e) {
            console.error(
              "Não foi possível salvar funcoes_visto no localStorage:",
              e,
            );
          }
        })
        .catch((err) => {
          console.error("Erro ao checar funções em andamento:", err);
          // resolvemos para não travar a sequência principal
        });
    }

    // Se a função não existir, apenas resolve para seguir o fluxo
    return Promise.resolve();
  } else {
    // Já viu hoje → não faz nada
    return Promise.resolve();
  }
}

// checkRenderItems também retorna uma Promise
function checkRenderItems(idColaborador) {
  return new Promise((resolve, reject) => {
    fetch("verifica_render.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `idcolaborador=${idColaborador}`,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.total > 0) {
          Swal.fire({
            title: `Você tem ${data.total} item(ns) na sua lista de render!`,
            text: "Deseja ver agora ou depois?",
            icon: "info",
            showCancelButton: true,
            confirmButtonText: "Ver agora",
            cancelButtonText: "Ver depois",
          }).then((result) => {
            if (result.isConfirmed) {
              window.location.href = "./Render/";
            } else {
              resolve(); // segue o fluxo
            }
          });
        } else {
          resolve(); // segue o fluxo mesmo sem render
        }
      })
      .catch((error) => {
        console.error("Erro ao verificar itens de render:", error);
        reject();
      });
  });
}

// --- Resumo inteligente & nav toggles ---
function mostrarResumoInteligente() {
  return new Promise((resolve, reject) => {
    const resumoModal = document.getElementById("resumoModal");
    const resumoContent = document.getElementById("resumo-content");

    resumoContent.innerHTML = "<p>Carregando resumo...</p>";

    fetch("PaginaPrincipal/Overview/getResumo.php")
      .then((r) => (r.ok ? r.json() : Promise.reject("Erro na resposta")))
      .then((data) => {
        if (data.error) {
          resumoContent.innerHTML = `<p style="color:red">${data.error}</p>`;
          resumoModal.style.display = "flex";
          resolve();
          return;
        }

        const parts = [];

        // Tarefas do dia
        parts.push("<h3>Tarefas do dia</h3>");
        if (data.tarefasHoje && data.tarefasHoje.length) {
          parts.push("<ul>");
          data.tarefasHoje.forEach((t) => {
            parts.push(
              `<li><strong>${t.nome_funcao || "Função"}</strong> — ${t.imagem_nome || ""} <small style="color:#64748b">(${t.prazo ? t.prazo.split(" ")[0] : ""})</small></li>`,
            );
          });
          parts.push("</ul>");
        } else {
          parts.push("<p>Nenhuma tarefa com prazo para hoje.</p>");
        }

        // Tarefas atrasadas
        parts.push("<h3>Tarefas atrasadas</h3>");
        if (data.tarefasAtrasadas && data.tarefasAtrasadas.length) {
          parts.push("<ul>");
          data.tarefasAtrasadas.forEach((t) => {
            parts.push(
              `<li><strong>${t.nome_funcao || "Função"}</strong> — ${t.imagem_nome || ""} <span style="color:#ef4444">(${t.prazo ? t.prazo.split(" ")[0] : ""})</span></li>`,
            );
          });
          parts.push("</ul>");
        } else {
          parts.push("<p>Sem tarefas atrasadas.</p>");
        }

        // Tarefas próximas
        parts.push("<h3>Tarefas próximas (7 dias)</h3>");
        if (data.tarefasProximas && data.tarefasProximas.length) {
          parts.push("<ul>");
          data.tarefasProximas.forEach((t) => {
            parts.push(
              `<li><strong>${t.nome_funcao || "Função"}</strong> — ${t.imagem_nome || ""} <small style="color:#64748b">(${t.prazo ? t.prazo.split(" ")[0] : ""})</small></li>`,
            );
          });
          parts.push("</ul>");
        } else {
          parts.push("<p>Sem tarefas próximas nos próximos 7 dias.</p>");
        }

        // Últimos ajustes
        parts.push("<h3>Últimos ajustes</h3>");
        if (data.ultimosAjustes && data.ultimosAjustes.length) {
          parts.push("<ul>");
          data.ultimosAjustes.forEach((t) => {
            parts.push(
              `<li><strong>${t.nome_funcao || "Função"}</strong> — ${t.imagem_nome || ""} <small style="color:#64748b">${t.status || ""} ${t.updated_at ? "• " + t.updated_at.split(" ")[0] : ""}</small></li>`,
            );
          });
          parts.push("</ul>");
        } else {
          parts.push("<p>Sem ajustes recentes.</p>");
        }

        resumoContent.innerHTML = parts.join("");
        resumoModal.style.display = "flex";
        resolve();
      })
      .catch((err) => {
        console.error("Erro ao obter resumo:", err);
        resumoContent.innerHTML = "<p>Erro ao carregar resumo.</p>";
        resumoModal.style.display = "flex";
        resolve();
      });
  });
}

// Busca os dados do painel diário e exibe modal com as informações (se necessário)
function fetchDailyPanel() {
  // Ensure the user has answered the daily before showing the daily panel.
  // This is a safe double-check in case fetchDailyPanel() is called from elsewhere.
  try {
    fetch("verifica_respostas.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `idcolaborador=${idColaborador}`,
    })
      .then((r) => (r.ok ? r.json() : Promise.reject("Erro na resposta")))
      .then((resp) => {
        if (!resp || !resp.hasResponses) {
          // user hasn't answered the daily yet — do not show the daily panel
          return;
        }

        // user answered — proceed to fetch and show the daily panel
        fetch("PaginaPrincipal/get_daily_panel.php")
          .then((r) => (r.ok ? r.json() : Promise.reject("Erro na resposta")))
          .then((data) => {
            if (!data || data.error) return;
            if (!data.show) return; // não mostrar hoje

            // Preenche contadores
            document.getElementById("daily_renders").textContent =
              data.renders ?? 0;
            document.getElementById("daily_ajustes").textContent =
              data.ajustes ?? 0;
            document.getElementById("daily_atrasadas").textContent =
              data.atrasadas ?? 0;
            document.getElementById("daily_hoje").textContent = data.hoje ?? 0;

            // Preenche últimas páginas (apenas 3) como botões com o título
            const container = document.getElementById("daily_recent_pages");
            container.innerHTML = "";
            if (Array.isArray(data.recent_pages) && data.recent_pages.length) {
              data.recent_pages.slice(0, 3).forEach((p) => {
                const btn = document.createElement("button");
                btn.type = "button";
                btn.className = "recent-page-btn";

                let label = p.tela || p.url || "Página";
                try {
                  if (String(label).trim() === "Detalhes da Obra") {
                    const obraNome = localStorage.getItem("obraNome") || "";
                    if (obraNome) label = `${label} (${obraNome})`;
                  }
                } catch (e) {}

                const labelSpan = document.createElement("span");
                labelSpan.className = "recent-page-label";
                labelSpan.textContent = label;
                btn.appendChild(labelSpan);

                const icon = document.createElement("i");
                icon.className =
                  "fa-solid fa-circle-arrow-right recent-page-icon";
                btn.appendChild(icon);

                btn.addEventListener("click", () => {
                  const url = p.url || "#";
                  if (url === "#") return;
                  window.open(url, "_blank");
                });

                container.appendChild(btn);
              });
            } else {
              const span = document.createElement("span");
              span.textContent = "Nenhuma página registrada.";
              container.appendChild(span);
            }

            const modal = document.getElementById("dailyPanelModal");
            if (modal) modal.style.display = "flex";

            // Bind buttons (only once)
            const goTasks = document.getElementById("daily_go_tasks");

            function markSeenAndClose(redirect) {
              fetch("PaginaPrincipal/mark_daily_panel_seen.php", {
                method: "POST",
              })
                .then((r) => r.json())
                .finally(() => {
                  const m = document.getElementById("dailyPanelModal");
                  if (m) m.style.display = "none";
                });
            }

            if (goTasks) {
              goTasks.onclick = () => {
                markSeenAndClose(true);
                modal.style.display = "none";
              };
            }
          })
          .catch((err) => console.error("Erro ao buscar painel diário:", err));
      })
      .catch((err) =>
        console.error(
          "Erro ao verificar respostas antes do painel diário:",
          err,
        ),
      );
  } catch (e) {
    console.error("fetchDailyPanel unexpected error:", e);
  }
}

// Nav button handlers
const btnOverview = document.getElementById("overview");
const btnKanban = document.getElementById("kanban");
const overviewSection = document.getElementById("overview-section");
const kanbanSection = document.getElementById("kanban-section");

function setActive(button) {
  [btnOverview, btnKanban].forEach((b) => b.classList.remove("active"));
  if (button) button.classList.add("active");
}

if (btnOverview)
  btnOverview.addEventListener("click", () => {
    overviewSection.style.display = "flex";
    kanbanSection.style.display = "none";
    setActive(btnOverview);
  });

if (btnKanban)
  btnKanban.addEventListener("click", () => {
    overviewSection.style.display = "none";
    kanbanSection.style.display = "flex";
    setActive(btnKanban);
  });

// Resumo modal button handlers
document.getElementById("resumo-overview").addEventListener("click", () => {
  document.getElementById("resumoModal").style.display = "none";
  btnOverview.click();
});

document.getElementById("resumo-kanban").addEventListener("click", () => {
  document.getElementById("resumoModal").style.display = "none";
  btnKanban.click();
});

document.getElementById("resumo-close").addEventListener("click", () => {
  document.getElementById("resumoModal").style.display = "none";
});

function checkFuncoesEmAndamento(idColaborador) {
  return new Promise((resolve, reject) => {
    fetch("getFuncoesEmAndamento.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `idcolaborador=${idColaborador}`,
    })
      .then((res) => res.json())
      .then((funcoes) => {
        if (!funcoes || funcoes.length === 0) {
          resolve(); // nada em andamento, segue fluxo
          return;
        }

        // Processa em sequência cada função
        let index = 0;

        function perguntarProximo() {
          if (index >= funcoes.length) {
            resolve(); // terminou todas
            return;
          }

          const funcao = funcoes[index];
          Swal.fire({
            title: `Você ainda está trabalhando em ${funcao.imagem_nome}?`,
            text: `Função: ${funcao.nome_funcao}`,
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "Sim, estou fazendo",
            cancelButtonText: "Não, colocar em HOLD",
          }).then((result) => {
            if (result.isConfirmed) {
              // continua sem alterar
              index++;
              perguntarProximo();
            } else {
              // pede observação
              Swal.fire({
                title: "Observação",
                input: "text",
                inputPlaceholder: "Por que não está fazendo?",
                showCancelButton: false,
                confirmButtonText: "Salvar",
              }).then((obsResult) => {
                const obs = obsResult.value || "";

                fetch("atualizarFuncao.php", {
                  method: "POST",
                  headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                  },
                  body: `idfuncao_imagem=${funcao.idfuncao_imagem}&observacao=${encodeURIComponent(obs)}`,
                }).finally(() => {
                  index++;
                  perguntarProximo();
                  carregarDados(idColaborador); // atualiza o kanban
                });
              });
            }
          });
        }

        perguntarProximo();
      })
      .catch((err) => {
        console.error("Erro ao verificar funções em andamento:", err);
        reject();
      });
  });
}

// const MODO_TESTE = true;

// if (MODO_TESTE) {
//     checkFuncoesEmAndamento(idColaborador);
// } else {
checkDailyAccess()
  .then(() => checkRenderItems(idColaborador))
  .then(() => checkFuncoesSomentePrimeiroAcesso()) // ✅ só na 1ª vez do dia
  .then(() => {
    buscarTarefas();
    mostrarChangelogSeNecessario();
    try {
      fetchDailyPanel();
    } catch (e) {
      console.error(e);
    }
    // mostrarResumoInteligente();
  })
  .catch(() => console.log("Fluxo interrompido"));

// }

carregarDados(colaborador_id);

carregarEventosEntrega();

const data = new Date();

// Pega o mês abreviado em pt-BR (ex: set, out, nov...)
let mes = data.toLocaleDateString("pt-BR", { month: "short" });
mes = mes.charAt(0).toUpperCase() + mes.slice(1).replace(".", ""); // Capitaliza e remove ponto

const dia = data.getDate();
const ano = data.getFullYear();

const formatted = `${mes} ${dia}, ${ano}`;

document.querySelector("#date span").textContent = formatted;

// Inicializa mini FullCalendar (visão semanal) e integra com o calendário full (modal)
(function initMiniCalendar() {
  const miniEl = document.getElementById("mini-calendar");
  if (!miniEl || typeof FullCalendar === "undefined") return;

  const miniCalendar = new FullCalendar.Calendar(miniEl, {
    initialView: "dayGridWeek",
    headerToolbar: false,
    height: 110,
    locale: "pt-br",
    displayEventTime: false,
    selectable: false,
    events: [],
    dateClick: function (info) {
      // abre modal do calendário expandido e vai para o dia
      document.getElementById("calendarFullModal").style.display = "flex";
      openFullCalendar();
      setTimeout(() => {
        if (fullCalendar) {
          fullCalendar.gotoDate(info.date);
          fullCalendar.changeView("dayGridMonth");
        }
      }, 250);
    },
    eventClick: function (info) {
      // prevent the native click from bubbling to the global window handler
      // which would immediately hide the modal we open on this click
      try {
        info.jsEvent?.stopPropagation();
      } catch (e) {
        /* ignore */
      }

      const ev = info.event;
      if (ev.id && ev.id.startsWith("t_")) {
        abrirSidebarTarefaCriada(ev.id.replace("t_", ""));
      } else if (ev.id && ev.id.startsWith("f_")) {
        abrirSidebar(
          ev.id.replace("f_", ""),
          ev.extendedProps?.imagem_id || "",
        );
      }
    },
  });

  miniCalendar.render();
  window.miniCalendar = miniCalendar;

  // Atualizador a ser chamado por carregarDados (passando o JSON já parseado)
  window.updateMiniCalendarWithData = function (data) {
    try {
      const evs = [];
      if (data && data.funcoes) {
        data.funcoes.forEach((f) => {
          const date = f.prazo || f.imagem_prazo;
          if (date) {
            evs.push({
              id: `f_${f.idfuncao_imagem}`,
              title: `${f.nome_funcao} — ${f.imagem_nome}`,
              start: date,
              allDay: true,
              extendedProps: {
                tipo: "funcao",
                status: f.status,
                imagem_id: f.imagem_id,
              },
            });
          }
        });
      }
      if (data && data.tarefas) {
        data.tarefas.forEach((t) => {
          if (t.prazo) {
            evs.push({
              id: `t_${t.id}`,
              title: t.titulo,
              start: t.prazo,
              allDay: true,
              extendedProps: { tipo: "tarefa", status: t.status },
            });
          }
        });
      }

      // keep latest mini events available globally so fullCalendar can reuse them
      window.miniCalendarEvents = evs;

      miniCalendar.removeAllEvents();
      if (evs.length) miniCalendar.addEventSource(evs);

      // if the full calendar modal is open, refresh its events to include these
      if (typeof fullCalendar !== "undefined" && fullCalendar) {
        try {
          fullCalendar.removeAllEvents();
          if (Array.isArray(events) && events.length)
            fullCalendar.addEventSource(events);
          if (
            Array.isArray(window.miniCalendarEvents) &&
            window.miniCalendarEvents.length
          )
            fullCalendar.addEventSource(window.miniCalendarEvents);
        } catch (err) {
          console.error(
            "Erro ao atualizar fullCalendar com eventos do mini:",
            err,
          );
        }
      }
    } catch (e) {
      console.error("updateMiniCalendarWithData error", e);
    }
  };

  // Fechar modal do calendário full
  document
    .getElementById("closeFullCalendar")
    ?.addEventListener("click", function () {
      document.getElementById("calendarFullModal").style.display = "none";
    });
})();

let events = [];

function carregarEventosEntrega() {
  fetch(`./Dashboard/Calendario/getEventosEntrega.php`)
    .then((res) => res.json())
    .then((data) => {
      console.log("Eventos de entrega:", data);

      events = data.map((evento) => {
        delete evento.eventDate;

        const colors = getEventColors(evento); // 👈 adiciona o título

        return {
          id: evento.id,
          title: evento.descricao,
          start: evento.start,
          end: evento.end && evento.end !== evento.start ? evento.end : null,
          allDay: evento.end ? true : false,
          tipo_evento: evento.tipo_evento,
          backgroundColor: colors.backgroundColor,
          color: colors.color,
        };
      });
      if (!fullCalendar) {
        openFullCalendar();
      } else {
        fullCalendar.removeAllEvents();
        fullCalendar.addEventSource(events);
      }

      if (
        colaborador_id === 1 ||
        colaborador_id === 9 ||
        colaborador_id === 21
      ) {
        notificarEventosDaSemana(events);
      }
    });
}
// 👇 Função que retorna eventos desta semana
function notificarEventosDaSemana(eventos) {
  const hoje = new Date();
  const inicioSemana = new Date(hoje);
  inicioSemana.setDate(hoje.getDate() - hoje.getDay()); // domingo
  const fimSemana = new Date(inicioSemana);
  fimSemana.setDate(inicioSemana.getDate() + 6); // sábado

  const eventosSemana = eventos.filter((evento) => {
    const startDate = new Date(evento.start);
    return startDate >= inicioSemana && startDate <= fimSemana;
  });

  if (eventosSemana.length > 0) {
    const listaEventos = eventosSemana
      .map(
        (ev) =>
          `<li><strong>${ev.title}</strong> em ${new Date(ev.start).toLocaleDateString()}</li>`,
      )
      .join("");

    Swal.fire({
      icon: "info",
      title: "Eventos desta semana",
      html: `<ul style="text-align: left; padding: 0 20px">${listaEventos}</ul>`,
      confirmButtonText: "Entendi",
    });
  }
}

// Função para definir as cores com base no tipo_evento
function getEventColors(event) {
  // Only differentiate colors when the event is a 'funcao' or 'tarefa'
  // prefer extendedProps.tipo, fall back to tipo or tipo_evento if present
  const tipo =
    event?.extendedProps?.tipo || event?.tipo || event?.tipo_evento || "";

  if (String(tipo).toLowerCase() === "funcao") {
    return { backgroundColor: "#ff9f89", color: "#000000" };
  }

  if (String(tipo).toLowerCase() === "tarefa") {
    return { backgroundColor: "#90ee90", color: "#000000" };
  }

  // default: no special color
  return { backgroundColor: "#d3d3d3", color: "#000000" };
}

let fullCalendar;

function openFullCalendar() {
  if (!fullCalendar) {
    fullCalendar = new FullCalendar.Calendar(
      document.getElementById("calendarFull"),
      {
        initialView: "dayGridMonth",
        editable: true,
        selectable: true,
        locale: "pt-br",
        displayEventTime: false,
        events: [], // we'll add event sources after render (delivery events + mini events)
        eventDidMount: function (info) {
          // Pass the real event object so getEventColors can read extendedProps.tipo
          try {
            const colors = getEventColors(info.event);
            if (colors && colors.backgroundColor)
              info.el.style.backgroundColor = colors.backgroundColor;
            if (colors && colors.color) info.el.style.color = colors.color;
            info.el.style.borderColor = colors.backgroundColor || "";
          } catch (e) {
            console.error("eventDidMount color error", e);
          }
        },
        datesSet: function (info) {
          const tituloOriginal = info.view.title;
          const partes = tituloOriginal.replace("de ", "").split(" ");
          const mes = partes[0];
          const ano = partes[1];
          const mesCapitalizado = mes.charAt(0).toUpperCase() + mes.slice(1);
          document.querySelector(
            "#calendarFull .fc-toolbar-title",
          ).textContent = `${mesCapitalizado} ${ano}`;
        },

        dateClick: function (info) {
          const clickedDate = new Date(info.date);
          const formattedDate = clickedDate.toISOString().split("T")[0];

          // document.getElementById('eventId').value = '';
          // document.getElementById('eventTitle').value = '';
          // document.getElementById('eventDate').value = formattedDate;
          // document.getElementById('eventModal').style.display = 'flex';
        },

        eventClick: function (info) {
          // prevent the native click from bubbling to the global window handler
          // which would immediately hide the modal we open on this click
          try {
            info.jsEvent?.stopPropagation();
          } catch (e) {
            /* ignore */
          }

          // Show a simple detail modal on event click (Nome da função, Nome da imagem, Status, Prazo)
          try {
            showEventDetails(info.event, info.el);
          } catch (e) {
            console.error("Erro ao mostrar detalhes do evento:", e);
          }
        },

        eventDrop: function (info) {
          const event = info.event;
          updateEvent(event);
        },
      },
    );

    fullCalendar.render();

    // add both delivery events and mini-calendar events (if any)
    try {
      if (Array.isArray(events) && events.length)
        fullCalendar.addEventSource(events);
      if (
        Array.isArray(window.miniCalendarEvents) &&
        window.miniCalendarEvents.length
      )
        fullCalendar.addEventSource(window.miniCalendarEvents);
    } catch (err) {
      console.error("Erro ao adicionar fontes de evento ao fullCalendar:", err);
    }
  } else {
    // refresh event lists so both delivery events and mini events are present
    try {
      fullCalendar.removeAllEvents();
      if (Array.isArray(events) && events.length)
        fullCalendar.addEventSource(events);
      if (
        Array.isArray(window.miniCalendarEvents) &&
        window.miniCalendarEvents.length
      )
        fullCalendar.addEventSource(window.miniCalendarEvents);
    } catch (err) {
      console.error(
        "Erro ao atualizar eventos do fullCalendar existente:",
        err,
      );
      fullCalendar.refetchEvents();
    }
  }
}

function closeEventModal() {
  document.getElementById("eventModal").style.display = "none";
  carregarEventosEntrega();
}

function showToast(message, type = "success") {
  let backgroundColor;

  switch (type) {
    case "create":
      backgroundColor = "linear-gradient(to right, #00b09b, #96c93d)"; // verde limão
      break;
    case "update":
      backgroundColor = "linear-gradient(to right, #2193b0, #6dd5ed)"; // azul claro
      break;
    case "delete":
      backgroundColor = "linear-gradient(to right, #ff416c, #ff4b2b)"; // vermelho/rosa
      break;
    case "error":
      backgroundColor = "linear-gradient(to right, #e53935, #e35d5b)"; // vermelho
      break;
    default:
      backgroundColor = "linear-gradient(to right, #00b09b, #96c93d)"; // sucesso padrão
  }

  Toastify({
    text: message,
    duration: 4000,
    gravity: "top",
    position: "right",
    backgroundColor: backgroundColor,
  }).showToast();
}

// document.getElementById('eventForm').addEventListener('submit', function (e) {
//     e.preventDefault();
//     const id = document.getElementById('eventId').value;
//     const title = document.getElementById('eventTitle').value;
//     const start = document.getElementById('eventDate').value;
//     const type = document.getElementById('eventType').value;
//     const obraId = document.getElementById('obra_calendar').value;

//     if (id) {
//         fetch('./Dashboard/Calendario/eventoController.php', {
//             method: 'PUT',
//             headers: { 'Content-Type': 'application/json' },
//             body: JSON.stringify({ id, title, start, type })
//         })
//             .then(res => res.json())
//             .then(res => {
//                 if (res.error) throw new Error(res.message);
//                 closeEventModal(); // ✅ fecha o modal após excluir
//                 showToast(res.message, 'update'); // para PUT
//             })
//             .catch(err => showToast(err.message, 'error'));
//     } else {
//         fetch('./Dashboard/Calendario/eventoController.php', {
//             method: 'POST',
//             headers: { 'Content-Type': 'application/json' },
//             body: JSON.stringify({ title, start, type, obra_id: obraId })
//         })
//             .then(res => res.json())
//             .then(res => {
//                 if (res.error) throw new Error(res.message);
//                 closeEventModal(); // ✅ fecha o modal após excluir
//                 showToast(res.message, 'create'); // para POST
//             })
//             .catch(err => showToast(err.message, 'error'));
//     }
// });

function deleteEvent() {
  const id = document.getElementById("eventId").value;
  if (!id) return;

  fetch("./Dashboard/Calendario/eventoController.php", {
    method: "DELETE",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id }),
  })
    .then((res) => res.json())
    .then((res) => {
      if (res.error) throw new Error(res.message);
      closeEventModal(); // ✅ fecha o modal após excluir

      showToast(res.message, "delete");
    })
    .catch((err) => showToast(err.message, "error"));
}

// Show a simple read-only detail view for a clicked event inside #eventModal
function showEventDetails(ev, el) {
  const modal = document.getElementById("eventModal");
  const detail = document.getElementById("eventDetail");
  const form = document.getElementById("eventForm");

  if (form) form.style.display = "none";
  if (detail) detail.style.display = "flex";

  const nomeFuncaoEl = document.getElementById("detailNomeFuncao");
  const nomeImagemEl = document.getElementById("detailNomeImagem");
  const statusEl = document.getElementById("detailStatus");
  const prazoEl = document.getElementById("detailPrazo");

  let nomeFuncao = "-";
  let nomeImagem = "-";
  let status =
    ev.extendedProps?.status ||
    ev.extendedProps?.tipo ||
    ev.tipo_evento ||
    ev.extendedProps?.tipo_evento ||
    "-";
  let prazo = ev.start
    ? new Date(ev.start).toISOString().split("T")[0]
    : ev.startStr || "-";

  if (ev.id && ev.id.startsWith("f_")) {
    if (ev.title && ev.title.includes("—")) {
      const parts = ev.title.split("—").map((s) => s.trim());
      nomeFuncao = parts[0] || "-";
      nomeImagem = parts[1] || "-";
    } else {
      nomeFuncao = ev.title || "-";
      nomeImagem = ev.extendedProps?.imagem_id
        ? String(ev.extendedProps.imagem_id)
        : "-";
    }
  } else if (ev.id && ev.id.startsWith("t_")) {
    nomeFuncao = ev.title || "-";
    nomeImagem = "-";
  } else {
    nomeFuncao = ev.title || "-";
    nomeImagem = ev.extendedProps?.obra_nome || "-";
  }

  nomeFuncaoEl.textContent = nomeFuncao;
  nomeImagemEl.textContent = nomeImagem;
  statusEl.textContent = status;
  prazoEl.textContent = prazo;

  // === posição dinâmica ===
  const rect = el.getBoundingClientRect();
  const offsetX = 10; // espaço entre evento e modal
  const offsetY = 0;

  // Ajusta posição (para ficar à direita do evento)
  modal.style.top = `${window.scrollY + rect.top + offsetY}px`;
  modal.style.left = `${window.scrollX + rect.right + offsetX}px`;

  modal.style.display = "block";
  modal.classList.add("show");

  // Fecha ao clicar no botão
  document.getElementById("closeEventDetail")?.addEventListener(
    "click",
    () => {
      modal.style.display = "none";
    },
    { once: true },
  );
}

const eventModal = document.getElementById("eventModal");

// Safe global handler: only attach if the modal exists and protect against missing children
if (eventModal) {
  ["click", "touchstart", "keydown"].forEach((eventType) => {
    window.addEventListener(eventType, function (event) {
      try {
        // If modal is not visible, ignore
        if (
          !eventModal.style ||
          !eventModal.style.display ||
          eventModal.style.display === "none"
        )
          return;

        const eventosDiv = eventModal.querySelector(".eventos");

        // Close on click/touch when clicking on overlay background (the modal element itself)
        // or when clicking outside the inner '.eventos' container (if it exists)
        if (eventType === "click" || eventType === "touchstart") {
          if (event.target === eventModal) {
            eventModal.style.display = "none";
            return;
          }

          if (eventosDiv && !eventosDiv.contains(event.target)) {
            eventModal.style.display = "none";
            return;
          }
        }

        // Close on Escape key
        if (eventType === "keydown" && event.key === "Escape") {
          eventModal.style.display = "none";
        }
      } catch (e) {
        console.error("modal global handler error", e);
      }
    });
  });
}

function updateEvent(event) {
  fetch("./Dashboard/Calendario/eventoController.php", {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      id: event.id,
      title: event.title,
      start: event.start.toISOString().substring(0, 10),
      type: event.extendedProps?.tipo_evento, // 👈 forma segura de acessar
    }),
  })
    .then((res) => res.json())
    .then((res) => {
      if (res.error) throw new Error(res.message);
      showToast(res.message);
    })
    .catch((err) => showToast(err.message, false));
}

function atualizarTemposEmAndamento() {
  const spans = document.querySelectorAll(".card-log .date[data-inicio]");

  spans.forEach((span) => {
    // pega o card correto
    const card = span.closest(".kanban-card");
    if (!card || card.dataset.status !== "Em andamento") return;

    // pega o valor de data-inicio (em minutos)
    let minutosIniciais = parseInt(span.dataset.inicio, 10);
    if (isNaN(minutosIniciais)) minutosIniciais = 0;

    // salva timestamp do carregamento da página
    if (!span.dataset.startTimestamp) {
      span.dataset.startTimestamp = Date.now();
    }

    const agora = Date.now();
    const diffMs = agora - span.dataset.startTimestamp; // ms desde que abriu
    const diffSeg = Math.floor(diffMs / 1000); // segundos decorridos desde que abriu

    // converte minutos iniciais em segundos e soma
    const totalSegundos = minutosIniciais * 60 + diffSeg;

    // calcula dias, horas, minutos e segundos
    const dias = Math.floor(totalSegundos / 86400); // 86400 = 24*60*60
    const horas = Math.floor((totalSegundos % 86400) / 3600);
    const minutos = Math.floor((totalSegundos % 3600) / 60);
    const segundos = totalSegundos % 60;

    // monta a string formatada
    let partes = [];
    if (dias > 0) partes.push(`${dias}d`);
    if (horas > 0) partes.push(`${horas}h`);
    if (minutos > 0) partes.push(`${minutos}min`);
    partes.push(`${segundos}s`);

    span.innerHTML = `<i class="ri-time-line"></i> ${partes.join(" ")}`;
  });
}

// Atualiza a cada segundo
setInterval(atualizarTemposEmAndamento, 1000);

// Atualiza imediatamente ao carregar
atualizarTemposEmAndamento();

const statusMap = {
  "Não iniciado": "to-do",
  "Em andamento": "in-progress",
  "Em aprovação": "in-review",
  Ajuste: "ajuste",
  Finalizado: "done",
  HOLD: "hold",
  Aprovado: "aprovado",
  "Aprovado com ajustes": "aprovado",
};

// Atualiza contagem de tarefas
function atualizarTaskCount() {
  const boxes = document.querySelectorAll(".kanban-box");
  boxes.forEach((box) => {
    const content = box.querySelector(".content");
    if (!content) return;

    const cards = Array.from(content.querySelectorAll(".kanban-card")).filter(
      (n) => {
        const style = window.getComputedStyle(n);
        return style.display !== "none" && n.offsetParent !== null;
      },
    );

    const count = cards.length;
    const badge = box.querySelector(".task-count");
    if (badge) badge.textContent = count;

    // Esconder colunas vazias (ajuste, aprovado, aprovado-ajustes)
    if (["ajuste", "aprovado", "aprovado-ajustes"].includes(box.id)) {
      box.style.display = count === 0 ? "none" : "";
    }
  });
}

// Mind map modal (detalhes)
const mindmapModal = document.getElementById("mindmapModal");
const mindmapContent = document.getElementById("mindmap-content");
const mindmapNotifications = document.getElementById("mindmap-notifications");
const closeMindmap = document.getElementById("closeMindmap");

function openMindmapModal() {
  if (!mindmapModal) return;
  mindmapModal.classList.remove("mindmap-exit");
  mindmapModal.style.display = "flex";
  // trigger enter animation
  requestAnimationFrame(() => mindmapModal.classList.add("mindmap-enter"));
}

function resetMindmapModal() {
  if (!mindmapContent) return;
  mindmapContent.innerHTML = "";
  if (mindmapNotifications) mindmapNotifications.innerHTML = "";
  mindmapContent.classList.remove("mindmap-blurred-mode");
  mindmapContent.classList.remove("mindmap-has-notifications");
}

function closeMindmapModal() {
  if (!mindmapModal) return;
  // play exit animation, then hide and reset
  mindmapModal.classList.remove("mindmap-enter");
  mindmapModal.classList.add("mindmap-exit");
  const onAnimEnd = (e) => {
    if (e.animationName && e.animationName.includes("mindmapFadeOut")) {
      mindmapModal.style.display = "none";
      mindmapModal.classList.remove("mindmap-exit");
      mindmapModal.removeEventListener("animationend", onAnimEnd);
      resetMindmapModal();
    }
  };
  mindmapModal.addEventListener("animationend", onAnimEnd);
}

if (closeMindmap) {
  closeMindmap.addEventListener("click", closeMindmapModal);
}

if (mindmapModal) {
  mindmapModal.addEventListener("click", (e) => {
    if (e.target === mindmapModal) closeMindmapModal();
  });
}

if (mindmapContent) {
  mindmapContent.addEventListener("click", (e) => {
    if (
      e.target.closest(".mindmap-card") ||
      e.target.closest(".mindmap-header") ||
      e.target.closest(".notificacoes-container") ||
      e.target.closest(".mindmap-notifications-card")
    ) {
      return;
    }
    closeMindmapModal();
  });
}

// =====================
// PDF Viewer Modal (in-page)
// =====================
// Backward compatible:
// - openPdfViewerModal(idarquivo, titulo)
// - openPdfViewerModal({ rawUrl, downloadUrl, titulo })
function openPdfViewerModal(idarquivoOrOptions, titulo = "PDF") {
  const baseViewerUrl =
    "https://improov.com.br/flow/ImproovWeb/Arquivos/visualizar_pdf.php";

  let rawUrl = "";
  let downloadUrl = "";
  let headerTitle = String(titulo || "PDF");

  if (idarquivoOrOptions && typeof idarquivoOrOptions === "object") {
    rawUrl = String(idarquivoOrOptions.rawUrl || "");
    downloadUrl = String(idarquivoOrOptions.downloadUrl || "");
    if (idarquivoOrOptions.titulo)
      headerTitle = String(idarquivoOrOptions.titulo);
  } else {
    const idarquivo = idarquivoOrOptions;
    if (!idarquivo) return;
    rawUrl = `${baseViewerUrl}?idarquivo=${encodeURIComponent(String(idarquivo))}&raw=1`;
    downloadUrl = `${baseViewerUrl}?idarquivo=${encodeURIComponent(String(idarquivo))}&raw=1&download=1`;
  }

  if (!rawUrl) return;

  let modal = document.getElementById("pdfViewerModal");
  if (!modal) {
    modal = document.createElement("div");
    modal.id = "pdfViewerModal";
    modal.className = "modal";
    modal.style.display = "none";
    modal.innerHTML = `
            <div class="modal-content pdf-modal-content">
                <div class="pdf-modal-header">
                    <div class="pdf-modal-title"></div>
                    <div class="pdf-modal-actions">
                        <a class="pdf-modal-btn secondary" data-action="download" href="#">Baixar</a>
                        <a class="pdf-modal-btn" data-action="newtab" href="#" target="_blank" rel="noopener">Abrir em nova aba</a>
                        <button type="button" class="pdf-modal-close" aria-label="Fechar">×</button>
                    </div>
                </div>
                <div class="pdf-modal-body">
                    <iframe class="pdf-modal-iframe" title="PDF" loading="lazy"></iframe>
                </div>
            </div>
        `;
    document.body.appendChild(modal);

    // Close handlers
    modal.addEventListener("click", (e) => {
      if (e.target === modal) closePdfViewerModal();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closePdfViewerModal();
    });

    modal
      .querySelector(".pdf-modal-close")
      ?.addEventListener("click", closePdfViewerModal);
  }

  const safeTitle = headerTitle;
  const titleEl = modal.querySelector(".pdf-modal-title");
  if (titleEl) titleEl.textContent = safeTitle;

  const iframe = modal.querySelector(".pdf-modal-iframe");
  if (iframe) iframe.src = rawUrl;

  const btnNewTab = modal.querySelector('[data-action="newtab"]');
  if (btnNewTab) btnNewTab.href = rawUrl;

  const btnDownload = modal.querySelector('[data-action="download"]');
  if (btnDownload) {
    btnDownload.href = downloadUrl || rawUrl;
    btnDownload.onclick = (e) => {
      // allow navigation for download but don't bubble to modal close
      e.stopPropagation();
    };
  }

  modal.style.display = "flex";
}

function closePdfViewerModal() {
  const modal = document.getElementById("pdfViewerModal");
  if (!modal) return;
  const iframe = modal.querySelector(".pdf-modal-iframe");
  if (iframe) iframe.src = "about:blank";
  modal.style.display = "none";
}

function abrirSidebarTarefaCriada(idTarefa) {
  fetch(`PaginaPrincipal/getInfosTarefaCriada.php?idtarefa=${idTarefa}`)
    .then((res) => res.json())
    .then((data) => {
      resetMindmapModal();

      // Acessa o primeiro item do array dentro de "tarefa"
      const t = data.tarefa && data.tarefa[0] ? data.tarefa[0] : {};

      const card = document.createElement("div");
      card.className = "mindmap-card mindmap-center simple";
      card.innerHTML = `
                <div class="mindmap-center-title">Detalhes da tarefa</div>
                <div class="mindmap-main">${t.titulo || "-"}</div>
                <div class="mindmap-meta">
                    <div><strong>Descrição:</strong> ${t.descricao || "-"}</div>
                    <div><strong>Prazo:</strong> ${t.prazo || "-"}</div>
                    <div><strong>Status:</strong> ${t.status || "-"}</div>
                    <div><strong>Prioridade:</strong> ${t.prioridade || "-"}</div>
                    <div><strong>Data de Criação:</strong> ${t.data_criacao || "-"}</div>
                </div>
            `;

      if (mindmapContent) {
        mindmapContent.innerHTML = "";
        mindmapContent.appendChild(card);
      }
      openMindmapModal();
    });
}

function abrirSidebar(idFuncao, idImagem) {
  return fetch(
    `PaginaPrincipal/getInfosCard.php?idfuncao=${idFuncao}&imagem_id=${idImagem}`,
  )
    .then((res) => {
      if (!res.ok) throw new Error("Network response was not ok");
      return res.json();
    })
    .then((data) => {
      resetMindmapModal();
      openMindmapModal();

      const funcao = data.funcoes && data.funcoes[0] ? data.funcoes[0] : {};

      let pendingNotifications = null;
      if (data.notificacoes && data.notificacoes.length > 0) {
        const notificacoesDiv = document.createElement("div");
        notificacoesDiv.className = "notificacoes-container";
        notificacoesDiv.innerHTML = `<h3>Notificações</h3>`;

        // blur other cards while notifications exist
        if (mindmapContent)
          mindmapContent.classList.add("mindmap-has-notifications");

        data.notificacoes.forEach((notif) => {
          const notifEl = document.createElement("div");
          notifEl.className = "func-notif";
          notifEl.dataset.notId = notif.id;

          const msgSpan = document.createElement("div");
          msgSpan.className = "msg";
          msgSpan.textContent = notif.mensagem;

          const rightDiv = document.createElement("div");
          rightDiv.style.display = "flex";
          rightDiv.style.alignItems = "center";

          const dataSpan = document.createElement("div");
          dataSpan.className = "data";
          dataSpan.textContent = notif.data ? notif.data.split(" ")[0] : "";

          const markBtn = document.createElement("button");
          markBtn.className = "mark-btn";
          markBtn.textContent = "Marcar lida";

          // Click handler: mark as read via backend then remove element
          function marcarLida() {
            const id = notifEl.dataset.notId;
            fetch("PaginaPrincipal/markNotificacao.php", {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: `id=${encodeURIComponent(id)}`,
            })
              .then((r) => r.json())
              .then((res) => {
                if (res && res.success) {
                  // remove from DOM
                  notifEl.remove();

                  // if there are no more notifications, remove sidebar blur
                  try {
                    if (!notificacoesDiv.querySelector(".func-notif")) {
                      notificacoesDiv.remove();
                      if (mindmapContent)
                        mindmapContent.classList.remove(
                          "mindmap-has-notifications",
                        );
                    }
                  } catch (e) {
                    console.error("Erro ao atualizar blur do mapa:", e);
                  }
                  // update any card icon counts if present
                  const card = document.querySelector(
                    `.kanban-card[data-id="${notif.funcao_imagem_id || notif.funcao_imagem || ""}"]`,
                  );
                  if (card) {
                    const countEl = card.querySelector(".notif-count");
                    if (countEl) {
                      let n = Number(countEl.textContent || 0);
                      n = Math.max(0, n - 1);
                      if (n === 0) {
                        const icon = card.querySelector(".notif-icon");
                        if (icon) icon.remove();
                      } else {
                        countEl.textContent = n;
                      }
                    }
                  }
                  showToast("Notificação marcada como lida", "update");
                } else {
                  showToast("Não foi possível marcar como lida", "error");
                }
              })
              .catch((err) => {
                console.error("Erro markNotificacao:", err);
                showToast("Erro ao conectar com o servidor", "error");
              });
          }

          // clicking the whole element marks as read (manual reading)
          notifEl.addEventListener("click", function (e) {
            // avoid double-trigger when clicking the button
            if (e.target === markBtn) return;
            marcarLida();
          });

          markBtn.addEventListener("click", function (e) {
            e.stopPropagation();
            marcarLida();
          });

          rightDiv.appendChild(dataSpan);
          rightDiv.appendChild(markBtn);

          notifEl.appendChild(msgSpan);
          notifEl.appendChild(rightDiv);

          notificacoesDiv.appendChild(notifEl);
        });

        pendingNotifications = notificacoesDiv;
      }

      function getFuncaoStatusColor(status) {
        const s = String(status || "").toLowerCase();
        switch (s) {
          case "em aprovação":
          case "em aprovacao":
            return "#4a90e2";
          case "finalizado":
          case "aprovado":
            return "#28a745";
          case "aprovado com ajustes":
            return "#5e07ffff";
          case "não iniciado":
          case "nao iniciado":
            return "#6c757d";
          case "em andamento":
            return "#ff9800";
          case "ajuste":
          case "hold":
            return "#dc3545";
          default:
            return "#777";
        }
      }

      function getImagemStatusColor(status) {
        const s = String(status || "").toLowerCase();
        switch (s) {
          case "p00":
            return "#c2ff1cff";
          case "r00":
            return "#1cf4ff";
          case "r01":
            return "#ff9800";
          case "r02":
            return "#ff3c00";
          case "r03":
          case "r04":
          case "r05":
            return "#dc3545";
          case "ef":
            return "#0dff00";
          default:
            return "#777";
        }
      }

      // ===== Canvas =====
      const canvas = document.createElement("div");
      canvas.className = "mindmap-layout";

      const grid = document.createElement("div");
      grid.className = "mindmap-grid";
      canvas.appendChild(grid);

      function createSlot(className, innerClassName) {
        const slot = document.createElement("div");
        slot.className = `mindmap-slot ${className}`;
        const inner = document.createElement("div");
        inner.className = `slot-inner ${innerClassName}`;
        slot.appendChild(inner);
        grid.appendChild(slot);
        return inner;
      }

      const topSlot = createSlot("slot-top", "slot-inner-bottom");
      const leftSlot = createSlot("slot-left", "slot-stack slot-stack-left");
      const centerSlot = createSlot("slot-center", "slot-inner-center");
      const rightSlot = createSlot("slot-right", "slot-stack slot-stack-right");
      const bottomSlot = createSlot("slot-bottom", "slot-inner-top");

      const center = document.createElement("div");
      center.className = "mindmap-card mindmap-center";
      center.style.setProperty("--anim-delay", "0s");
      center.innerHTML = `
                <div class="mindmap-center-title">Núcleo principal</div>
                <div class="mindmap-main">${funcao.imagem_nome || "-"} - <span class="mindmap-status" style="font-size: 1rem; background:${getImagemStatusColor(data.status_imagem.nome_status)};">${data.status_imagem.nome_status || "-"}</span></div>
                <div class="mindmap-meta">
                    <div><strong>Função:</strong> ${funcao.nome_funcao || "-"}</div>
                    <div><strong>Status:</strong> <span class="mindmap-status" style="background:${getFuncaoStatusColor(funcao.status)};">${funcao.status || "-"}</span></div>
                    <div><strong>Prazo:</strong> ${funcao.prazo ? formatarData(funcao.prazo) : "-"}</div>
                    <div><strong>Observação:</strong> ${funcao.observacao || "-"}</div>
                </div>
            `;
      centerSlot.appendChild(center);

      // Redraw connectors when clicking the center card (but not when clicking its drawer headers)
      center.addEventListener("click", (e) => {
        if (e.target.closest(".mindmap-center-drawer-title")) return;
        requestAnimationFrame(drawMindmapLines);
      });

      let mindmapNodeIndex = 0;

      function createNode(title, className, options = {}, parent = null) {
        const node = document.createElement("div");
        node.className = `mindmap-card mindmap-node ${className}`;
        const delay = 0.06 * mindmapNodeIndex + 0.1;
        node.style.setProperty("--anim-delay", `${delay}s`);
        node.dataset.animDelay = String(delay);
        mindmapNodeIndex += 1;

        const header = document.createElement("div");
        header.className = "mindmap-node-title";

        const titleSpan = document.createElement("span");
        titleSpan.textContent = title;

        const toggle = document.createElement("span");
        toggle.className = "mindmap-drawer-toggle";
        toggle.innerHTML = "&#9662;";

        header.appendChild(titleSpan);
        header.appendChild(toggle);

        const body = document.createElement("div");
        body.className = "mindmap-node-body";

        node.appendChild(header);
        node.appendChild(body);
        (parent || grid).appendChild(node);

        if (options.collapsible === false) {
          toggle.style.display = "none";
        } else {
          node.classList.add("mindmap-drawer");
          header.addEventListener("click", () => {
            node.classList.toggle("drawer-collapsed");
          });

          // trigger redraw of connectors only when the node itself is clicked (not the header)
          node.addEventListener("click", (e) => {
            if (e.target.closest(".mindmap-node-title")) return;
            requestAnimationFrame(drawMindmapLines);
          });
        }

        return body;
      }

      const isAnguloDefinido = (it) => {
        const raw = `${it?.caminho || ""} ${it?.nome_interno || ""} ${it?.nome_arquivo || ""}`;
        return /angulo_definido/i.test(raw);
      };

      const arquivosImagemAll = Array.isArray(data.arquivos_imagem)
        ? data.arquivos_imagem
        : [];
      const arquivosTipoAll = Array.isArray(data.arquivos_tipo)
        ? data.arquivos_tipo
        : [];

      const anguloItems = [...arquivosImagemAll, ...arquivosTipoAll].filter(
        isAnguloDefinido,
      );
      const arquivosImagem = arquivosImagemAll.filter(
        (it) => !isAnguloDefinido(it),
      );
      const arquivosTipo = arquivosTipoAll.filter(
        (it) => !isAnguloDefinido(it),
      );

      // Arquivos (3 núcleos à esquerda)
      if (pendingNotifications) {
        const notifBody = createNode(
          "Notificações",
          "mindmap-notifications-card mindmap-notifications-focus",
          {},
          topSlot,
        );
        notifBody.appendChild(pendingNotifications);
      }

      const arquivosImagemBody = createNode(
        "Arquivos da imagem",
        "mindmap-files-image",
        {},
        leftSlot,
      );
      const arquivosTipoBody = createNode(
        "Arquivos do tipo de imagem",
        "mindmap-files-type",
        {},
        leftSlot,
      );
      const arquivosAnterioresBody = createNode(
        "Processos anteriores",
        "mindmap-files-previous",
        {},
        leftSlot,
      );

      // Helper: group array of arquivos by categoria_nome -> tipo
      function groupArquivos(arr) {
        const grouped = {}; // { categoria: { tipo: [items] } }
        arr.forEach((a) => {
          const cat = a.categoria_nome || "Sem categoria";
          const tipo =
            a.tipo ||
            a.nome_interno?.split(".").pop()?.toUpperCase() ||
            "Outros";
          if (!grouped[cat]) grouped[cat] = {};
          if (!grouped[cat][tipo]) grouped[cat][tipo] = [];
          grouped[cat][tipo].push(a);
        });
        return grouped;
      }

      // normalize path: replace /mnt/clientes -> Z:, use backslashes, and trim
      function normalizePath(rawPath, isTipoLevel = false) {
        if (!rawPath) return "";
        let p = rawPath;
        // replace linux mount prefix with drive letter
        p = p.replace(/^\/\/*mnt\/clientes/i, "Z:");
        // normalize slashes to backslashes
        p = p.replace(/\//g, "\\");
        // remove trailing backslashes
        p = p.replace(/\\+$/g, "");

        const parts = p.split("\\").filter(Boolean);
        if (parts.length === 0) return p;

        const TYPES = ["IMG", "DWG", "PDF", "Outros", "SKP"];
        let idx = -1;
        for (let i = 0; i < parts.length; i++) {
          if (TYPES.includes(parts[i].toUpperCase())) {
            idx = i;
            break;
          }
        }

        if (idx >= 0) {
          // for type-level files, stop at the type folder
          if (isTipoLevel) {
            return parts.slice(0, idx + 1).join("\\");
          }
          // for image-specific files, keep the folder after the type as well (if present)
          if (idx + 1 < parts.length) {
            return parts.slice(0, idx + 2).join("\\");
          }
          return parts.slice(0, idx + 1).join("\\");
        }

        // fallback: if last segment looks like a filename (has an extension), drop it
        const last = parts[parts.length - 1];
        if (/\.[A-Za-z0-9]{1,6}$/.test(last)) {
          return parts.slice(0, -1).join("\\");
        }

        // otherwise return full normalized path
        return parts.join("\\");
      }

      function renderGroupedArquivos(
        title,
        arr,
        isTipoLevel = false,
        target = null,
      ) {
        if (!arr || arr.length === 0) return null;

        const section = document.createElement("div");
        section.classList.add("arquivos-section");

        const header = document.createElement("h3");
        header.innerHTML = `📁 ${title}`;
        section.appendChild(header);

        const grouped = groupArquivos(arr);

        Object.keys(grouped).forEach((cat) => {
          const catDiv = document.createElement("div");
          catDiv.classList.add("arquivos-categoria");

          // total por categoria
          const totalCat = Object.values(grouped[cat]).reduce(
            (s, arr) => s + arr.length,
            0,
          );

          const catHeader = document.createElement("div");
          catHeader.classList.add("cat-header");
          catHeader.innerHTML = `🏗️ ${cat} <span class="count">(${totalCat})</span>`;
          catDiv.appendChild(catHeader);

          // tipos dentro da categoria
          Object.keys(grouped[cat]).forEach((tipo) => {
            const tipoArr = grouped[cat][tipo];
            const tipoDiv = document.createElement("div");
            tipoDiv.classList.add("arquivos-tipo");

            // Se a categoria contém itens com categoria_id === 7 (ex: JPGs),
            // não adicionamos o cabeçalho de tipo. Isso evita repetir o tipo
            // para entradas de imagem que têm apresentação especial abaixo.
            const containsCategoria7 = tipoArr.some(
              (it) => parseInt(it.categoria_id, 10) === 7,
            );
            if (!containsCategoria7) {
              tipoDiv.innerHTML = `\n                <div class="tipo-header">↳ ${tipo} <span class="count">(${tipoArr.length})</span></div>\n            `;
            } else {
              // Marca o elemento para estilos alternativos, se necessário
              tipoDiv.classList.add("no-tipo-header");
            }

            const infoDiv = document.createElement("div");
            infoDiv.classList.add("tipo-info");

            // separate items where categoria_id == 7 (special: show JPG filename + observação)
            const jpgItems = tipoArr.filter(
              (it) => parseInt(it.categoria_id, 10) === 7,
            );
            const otherItems = tipoArr.filter(
              (it) => parseInt(it.categoria_id, 10) !== 7,
            );

            // First render other items as folder paths (existing behavior)
            const rawPaths = Array.from(
              new Set(otherItems.map((it) => it.caminho).filter(Boolean)),
            );
            const paths = rawPaths.map((p) => normalizePath(p, isTipoLevel));
            const uniquePaths = Array.from(new Set(paths));

            if (uniquePaths.length > 0) {
              uniquePaths.forEach((p) => {
                // Linha do caminho da pasta
                const pDiv = document.createElement("div");
                pDiv.classList.add("path");
                pDiv.innerHTML = `📂 ${p}`;
                infoDiv.appendChild(pDiv);

                // Arquivos que compõem este caminho
                const filesForPath = otherItems.filter((it) => {
                  const np = normalizePath(it.caminho, isTipoLevel);
                  return np === p;
                });

                if (filesForPath.length > 0) {
                  const listDiv = document.createElement("div");
                  listDiv.classList.add("path-files");

                  filesForPath.forEach((it) => {
                    const fileEntry = document.createElement("div");
                    fileEntry.classList.add("file-entry");

                    // Nome do arquivo
                    const nome = it.nome_interno || it.nome_arquivo || "—";
                    const titleDiv = document.createElement("div");
                    titleDiv.classList.add("file-title");
                    titleDiv.textContent = `↳ ${nome}`;

                    // Botão de visualização para PDF
                    const tipoArquivo = String(it.tipo || "").toUpperCase();
                    const idarquivo = it.idarquivo ? String(it.idarquivo) : "";
                    if (tipoArquivo === "PDF" && idarquivo) {
                      const link = document.createElement("a");
                      link.classList.add("btn-view-pdf");
                      link.href = "#";
                      link.innerHTML = '<i class="fa-solid fa-file-pdf"></i>';
                      link.addEventListener("click", (e) => {
                        e.preventDefault();
                        // evita interferir com eventos de clique do sidebar
                        e.stopPropagation();
                        openPdfViewerModal(idarquivo, nome);
                      });
                      titleDiv.appendChild(link);
                    }
                    listDiv.appendChild(titleDiv);

                    // Metadados: data (recebido_em), sufixo, descricao
                    const metaDiv = document.createElement("div");
                    metaDiv.classList.add("file-meta");

                    // Data recebido_em formatada dd/mm/aaaa
                    let dataStr = "";
                    const rawDate =
                      it.recebido_em || it.data || it.data_recebimento || "";
                    if (rawDate) {
                      const d = new Date(rawDate);
                      if (!isNaN(d.getTime())) {
                        const dd = String(d.getDate()).padStart(2, "0");
                        const mm = String(d.getMonth() + 1).padStart(2, "0");
                        const yyyy = d.getFullYear();
                        dataStr = `${dd}/${mm}/${yyyy}`;
                      } else {
                        // se não for parseável, mostra como veio
                        dataStr = String(rawDate);
                      }
                    }

                    const partes = [];
                    if (dataStr) partes.push(`📅 ${dataStr}`);
                    if (it.sufixo) partes.push(`📝 ${it.sufixo}`);
                    if (it.descricao) partes.push(`⚠️ ${it.descricao}`);

                    if (partes.length > 0) {
                      metaDiv.textContent = partes.join(" | ");
                      listDiv.appendChild(metaDiv);
                    }
                  });

                  infoDiv.appendChild(listDiv);
                }
              });
            } else if (otherItems.length === 0 && jpgItems.length === 0) {
              const noneDiv = document.createElement("div");
              noneDiv.classList.add("path");
              noneDiv.textContent = "Sem caminho";
              infoDiv.appendChild(noneDiv);
            }

            // Then render jpg items: show filename (from nome_interno or from caminho) and observação if present
            if (jpgItems.length > 0) {
              jpgItems.forEach((it) => {
                const pDiv = document.createElement("div");
                pDiv.classList.add("path", "jpg-entry");

                // extrai nome do arquivo
                let filename = it.nome_interno || "";
                if (!filename && it.caminho) {
                  const parts = it.caminho.split(/[\\\/]/).filter(Boolean);
                  filename = parts.length
                    ? parts[parts.length - 1]
                    : it.caminho;
                }

                // tenta construir URL pública
                let url = null;
                if (it.caminho) {
                  url = sftpToPublicUrl(it.caminho);
                }

                console.log("URL pública para", filename, ":", url);

                if (url) {
                  const img = document.createElement("img");
                  img.src = encodeURI(url);
                  img.alt = filename;
                  img.title = filename;
                  img.classList.add("thumb");

                  const filenameSpan = document.createElement("span");
                  filenameSpan.textContent = filename;

                  // adiciona o nome primeiro
                  pDiv.appendChild(filenameSpan);
                  // e depois a imagem
                  pDiv.appendChild(img);
                } else {
                  pDiv.textContent = `🖼️ ${filename}`;
                }

                // adiciona observação
                if (it.descricao) {
                  const descDiv = document.createElement("div");
                  descDiv.classList.add("arquivo-descricao");
                  descDiv.textContent = `⚠️ ${it.descricao}`;
                  pDiv.appendChild(descDiv);
                }

                infoDiv.appendChild(pDiv);
              });
            }

            tipoDiv.appendChild(infoDiv);
            catDiv.appendChild(tipoDiv);
          });

          section.appendChild(catDiv);
        });

        if (target) target.appendChild(section);
        return section;
      }

      // Render previous-task arquivos (logs) with custom layout:
      // - .cat-header = nome_funcao
      // - .arquivos-tipo will contain only .tipo-info with caminhos
      function renderArquivosAnteriores(title, arr, target = null) {
        if (!arr || arr.length === 0) return null;

        const section = document.createElement("div");
        section.classList.add("arquivos-section");

        const header = document.createElement("h3");
        header.innerHTML = `📁 ${title}`;
        section.appendChild(header);

        // group by nome_funcao -> tipo -> items
        const groupedByFunc = {};
        arr.forEach((a) => {
          const func = a.nome_funcao || "Sem função";
          const tipo =
            a.tipo ||
            a.nome_arquivo?.split(".").pop()?.toUpperCase() ||
            "Outros";
          if (!groupedByFunc[func]) groupedByFunc[func] = {};
          if (!groupedByFunc[func][tipo]) groupedByFunc[func][tipo] = [];
          groupedByFunc[func][tipo].push(a);
        });

        Object.keys(groupedByFunc).forEach((funcName) => {
          const catDiv = document.createElement("div");
          catDiv.classList.add("arquivos-categoria");

          // total por função
          const totalFunc = Object.values(groupedByFunc[funcName]).reduce(
            (s, arr) => s + arr.length,
            0,
          );

          const catHeader = document.createElement("div");
          catHeader.classList.add("cat-header");
          catHeader.innerHTML = `🏗️ ${funcName} <span class="count">(${totalFunc})</span>`;
          catDiv.appendChild(catHeader);

          // for each tipo, show paths and allow per-file "Ver PDF" when tipo == PDF
          Object.keys(groupedByFunc[funcName]).forEach((tipo) => {
            const tipoArr = groupedByFunc[funcName][tipo];
            const tipoDiv = document.createElement("div");
            tipoDiv.classList.add("arquivos-tipo");

            const rawPaths = Array.from(
              new Set(tipoArr.map((it) => it.caminho).filter(Boolean)),
            );
            const paths = rawPaths.map((p) => normalizePath(p, false));
            const uniquePaths = Array.from(new Set(paths));

            const infoDiv = document.createElement("div");
            infoDiv.classList.add("tipo-info");

            if (uniquePaths.length > 0) {
              uniquePaths.forEach((p) => {
                const pDiv = document.createElement("div");
                pDiv.classList.add("path");
                pDiv.innerHTML = `📂 ${p}`;
                infoDiv.appendChild(pDiv);

                const filesForPath = tipoArr.filter((it) => {
                  const np = normalizePath(it.caminho, false);
                  return np === p;
                });

                if (filesForPath.length > 0) {
                  const listDiv = document.createElement("div");
                  listDiv.classList.add("path-files");

                  filesForPath.forEach((it) => {
                    const fileEntry = document.createElement("div");
                    fileEntry.classList.add("file-entry");

                    let nomeArquivo = it.nome_arquivo || it.nome_interno || "";
                    if (!nomeArquivo && it.caminho) {
                      const parts = String(it.caminho)
                        .split(/[\\/]/)
                        .filter(Boolean);
                      nomeArquivo = parts.length
                        ? parts[parts.length - 1]
                        : String(it.caminho);
                    }
                    if (!nomeArquivo) nomeArquivo = "—";

                    const titleDiv = document.createElement("div");
                    titleDiv.classList.add("file-title");
                    titleDiv.textContent = `↳ ${nomeArquivo}`;

                    const tipoArquivo = String(it.tipo || "").toUpperCase();
                    const idlog = it.id ? String(it.id) : "";
                    if (tipoArquivo === "PDF" && idlog) {
                      const link = document.createElement("a");
                      link.classList.add("btn-view-pdf");
                      link.href = "#";
                      link.innerHTML = '<i class="fa-solid fa-file-pdf"></i>';
                      link.addEventListener("click", (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        const base =
                          "https://improov.com.br/flow/ImproovWeb/Arquivos/visualizar_pdf_log.php";
                        openPdfViewerModal({
                          rawUrl: `${base}?idlog=${encodeURIComponent(idlog)}&raw=1`,
                          downloadUrl: `${base}?idlog=${encodeURIComponent(idlog)}&raw=1&download=1`,
                          titulo: nomeArquivo,
                        });
                      });
                      titleDiv.appendChild(link);
                    }

                    fileEntry.appendChild(titleDiv);
                    listDiv.appendChild(fileEntry);
                  });

                  infoDiv.appendChild(listDiv);
                }
              });
            } else {
              const noneDiv = document.createElement("div");
              noneDiv.classList.add("path");
              noneDiv.textContent = "Sem caminho";
              infoDiv.appendChild(noneDiv);
            }

            tipoDiv.appendChild(infoDiv);
            catDiv.appendChild(tipoDiv);
          });

          section.appendChild(catDiv);
        });

        if (target) target.appendChild(section);
        return section;
      }
      renderGroupedArquivos(
        "Arquivos da imagem",
        arquivosImagem,
        false,
        arquivosImagemBody,
      ) ||
        arquivosImagemBody.appendChild(
          Object.assign(document.createElement("div"), {
            className: "mindmap-empty",
            textContent: "Sem arquivos da imagem",
          }),
        );
      renderGroupedArquivos(
        "Arquivos do tipo de imagem",
        arquivosTipo,
        true,
        arquivosTipoBody,
      ) ||
        arquivosTipoBody.appendChild(
          Object.assign(document.createElement("div"), {
            className: "mindmap-empty",
            textContent: "Sem arquivos do tipo de imagem",
          }),
        );
      renderArquivosAnteriores(
        "Processos anteriores",
        data.arquivos_anteriores,
        arquivosAnterioresBody,
      ) ||
        arquivosAnterioresBody.appendChild(
          Object.assign(document.createElement("div"), {
            className: "mindmap-empty",
            textContent: "Sem processos anteriores",
          }),
        );

      function renderAnguloDefinido(arr, target) {
        if (!arr || arr.length === 0) return null;

        const list = document.createElement("div");
        list.className = "mindmap-angulo-list";

        arr.forEach((it) => {
          const item = document.createElement("div");
          item.className = "mindmap-angulo-item";

          let filename = it.nome_interno || it.nome_arquivo || "";
          if (!filename && it.caminho) {
            const parts = String(it.caminho).split(/[\\/]/).filter(Boolean);
            filename = parts.length
              ? parts[parts.length - 1]
              : String(it.caminho);
          }
          if (!filename) filename = "—";

          let url = null;
          if (it.caminho) {
            url = sftpToPublicUrl(it.caminho);
          }

          if (url) {
            const img = document.createElement("img");
            img.src = encodeURI(url);
            img.alt = filename;
            img.title = filename;
            img.classList.add("thumb");

            const filenameSpan = document.createElement("span");
            filenameSpan.textContent = filename;

            item.appendChild(filenameSpan);
            item.appendChild(img);
          } else {
            item.textContent = `🖼️ ${filename}`;
          }

          if (it.descricao) {
            const descDiv = document.createElement("div");
            descDiv.classList.add("arquivo-descricao");
            descDiv.textContent = `⚠️ ${it.descricao}`;
            item.appendChild(descDiv);
          }

          list.appendChild(item);
        });

        if (target) target.appendChild(list);
        return list;
      }

      const anguloBody = createNode(
        "Ângulo definido",
        "mindmap-angle",
        {},
        rightSlot,
      );
      renderAnguloDefinido(anguloItems, anguloBody) ||
        anguloBody.appendChild(
          Object.assign(document.createElement("div"), {
            className: "mindmap-empty",
            textContent: "Sem ângulo definido",
          }),
        );

      // Colaboradores/Logs — elementos expandíveis dentro do núcleo principal
      const centerDrawers = [];
      function createCenterDrawer(title) {
        const wrapper = document.createElement("div");
        wrapper.className = "mindmap-center-drawer drawer-collapsed";

        const header = document.createElement("div");
        header.className = "mindmap-center-drawer-title";

        const titleSpan = document.createElement("span");
        titleSpan.textContent = title;

        const toggle = document.createElement("span");
        toggle.className = "mindmap-center-drawer-toggle";
        toggle.innerHTML = "&#9662;";

        header.appendChild(titleSpan);
        header.appendChild(toggle);

        const body = document.createElement("div");
        body.className = "mindmap-center-drawer-body";

        wrapper.appendChild(header);
        wrapper.appendChild(body);

        centerDrawers.push(wrapper);

        header.addEventListener("click", () => {
          const willOpen = wrapper.classList.contains("drawer-collapsed");
          if (willOpen) {
            centerDrawers.forEach((other) => {
              if (other !== wrapper) {
                other.classList.add("drawer-collapsed");
              }
            });
          }
          wrapper.classList.toggle("drawer-collapsed");
        });

        return { wrapper, body };
      }

      const { wrapper: colabsDrawer, body: colabsBody } =
        createCenterDrawer("Colaboradores");
      center.appendChild(colabsDrawer);

      if (data.colaboradores && data.colaboradores.length > 0) {
        const ul = document.createElement("ul");
        ul.className = "mindmap-colabs-list";
        data.colaboradores.forEach((col) => {
          let funcoes = col.funcoes || "";
          if (funcoes) {
            const arr = funcoes.split(",").map((f) => f.trim());
            if (arr.length > 1) {
              const last = arr.pop();
              funcoes = arr.join(", ") + " e " + last;
            }
          }
          const li = document.createElement("li");
          li.textContent = `${col.nome_colaborador} - ${funcoes}`;
          ul.appendChild(li);
        });
        colabsBody.appendChild(ul);
      } else {
        const empty = document.createElement("div");
        empty.className = "mindmap-empty";
        empty.textContent = "Sem colaboradores vinculados";
        colabsBody.appendChild(empty);
      }

      // Logs — elemento expandível dentro do núcleo principal
      const { wrapper: logsDrawer, body: logsBody } =
        createCenterDrawer("Logs");
      center.appendChild(logsDrawer);

      const logDiv = document.createElement("div");
      logDiv.classList.add("log-alteracoes");
      if (data.log_alteracoes && data.log_alteracoes.length > 0) {
        data.log_alteracoes.forEach((log) => {
          const li = document.createElement("div");
          li.classList.add("log-entry");

          let corBorda;
          switch (String(log.status_novo || "").toLowerCase()) {
            case "em aprovação":
              corBorda = "#4a90e2";
              break;
            case "finalizado":
            case "aprovado":
              corBorda = "#28a745";
              break;
            case "aprovado com ajustes":
              corBorda = "#5e07ffff";
              break;
            case "não iniciado":
              corBorda = "#6c757d";
              break;
            case "em andamento":
              corBorda = "#ff9800";
              break;
            case "ajuste":
            case "hold":
              corBorda = "#dc3545";
              break;
            default:
              corBorda = "#777";
          }

          li.style.borderLeft = `3px solid ${corBorda}`;
          li.style.paddingLeft = "10px";
          li.style.marginBottom = "10px";

          li.innerHTML = `<strong>${log.data}</strong> ${log.status_anterior} → <em>${log.status_novo}</em> (${log.responsavel})`;
          logDiv.appendChild(li);
        });
      } else {
        const empty = document.createElement("div");
        empty.className = "mindmap-empty";
        empty.textContent = "Sem alterações recentes";
        logDiv.appendChild(empty);
      }
      logsBody.appendChild(logDiv);

      function drawMindmapLines() {
        if (!mindmapContent) return;
        const existing = canvas.querySelector(".mindmap-lines");
        if (existing) existing.remove();

        const svg = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "svg",
        );
        svg.classList.add("mindmap-lines");
        svg.setAttribute("width", "100%");
        svg.setAttribute("height", "100%");

        const defs = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "defs",
        );
        const marker = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "marker",
        );
        marker.setAttribute("id", "arrowhead");
        marker.setAttribute("markerWidth", "8");
        marker.setAttribute("markerHeight", "8");
        marker.setAttribute("refX", "6");
        marker.setAttribute("refY", "4");
        marker.setAttribute("orient", "auto");
        const arrowPath = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "path",
        );
        arrowPath.setAttribute("d", "M0,0 L8,4 L0,8 Z");
        arrowPath.setAttribute("fill", "#3b3b3b");
        marker.appendChild(arrowPath);
        defs.appendChild(marker);
        svg.appendChild(defs);

        const canvasRect = canvas.getBoundingClientRect();
        const centerRect = center.getBoundingClientRect();

        const startLeft = {
          x: centerRect.left - canvasRect.left,
          y: centerRect.top - canvasRect.top + centerRect.height / 2,
        };
        const startRight = {
          x: centerRect.left - canvasRect.left + centerRect.width,
          y: centerRect.top - canvasRect.top + centerRect.height / 2,
        };
        const startTop = {
          x: centerRect.left - canvasRect.left + centerRect.width / 2,
          y: centerRect.top - canvasRect.top,
        };
        const startBottom = {
          x: centerRect.left - canvasRect.left + centerRect.width / 2,
          y: centerRect.top - canvasRect.top + centerRect.height,
        };

        const centerPoint = {
          x: centerRect.left - canvasRect.left + centerRect.width / 2,
          y: centerRect.top - canvasRect.top + centerRect.height / 2,
        };

        const nodes = Array.from(canvas.querySelectorAll(".mindmap-node"));
        nodes.forEach((node) => {
          const rect = node.getBoundingClientRect();
          const end = {
            x: rect.left - canvasRect.left + rect.width / 2,
            y: rect.top - canvasRect.top + rect.height / 2,
          };

          const delay = node.dataset.animDelay
            ? `${node.dataset.animDelay}s`
            : "0s";
          const line = document.createElementNS(
            "http://www.w3.org/2000/svg",
            "line",
          );
          line.setAttribute("x1", centerPoint.x);
          line.setAttribute("y1", centerPoint.y);
          line.setAttribute("x2", end.x);
          line.setAttribute("y2", end.y);
          line.setAttribute("stroke", "#3b3b3b");
          line.setAttribute("stroke-width", "3");
          line.setAttribute("marker-end", "url(#arrowhead)");
          line.style.setProperty("--anim-delay", delay);
          svg.appendChild(line);
        });

        canvas.appendChild(svg);
      }

      if (mindmapContent) {
        mindmapContent.appendChild(canvas);
        requestAnimationFrame(drawMindmapLines);
      }

      if (!window.__mindmapResizeBound) {
        window.__mindmapResizeBound = true;
        window.addEventListener("resize", () => {
          requestAnimationFrame(drawMindmapLines);
        });
      }

      const pathEl = mindmapContent
        ? mindmapContent.querySelectorAll(".path")
        : [];
      pathEl.forEach((el) => {
        if (el.classList.contains("jpg-entry") || el.querySelector("img"))
          return;
        el.innerHTML = el.textContent.replace(/[\\/]/g, "$&<wbr>");
      });

      return data; // expose fetched data to caller
    });
}

function formatarDuracao(minutos) {
  if (!minutos || minutos < 0) return "-";

  const dias = Math.floor(minutos / 1440); // 1440 = 60*24
  const horas = Math.floor((minutos % 1440) / 60);
  const mins = minutos % 60;

  let partes = [];
  if (dias > 0) partes.push(`${dias}d`);
  if (horas > 0) partes.push(`${horas}h`);
  if (mins > 0) partes.push(`${mins}min`);

  return partes.join(" ");
}

// Preenche os filtros dinâmicos
function preencherFiltros() {
  const obras = new Set();
  const funcoes = new Set();

  document.querySelectorAll(".kanban-card").forEach((card) => {
    if (card.dataset.obra_nome) obras.add(card.dataset.obra_nome);
    if (card.dataset.funcao_nome) funcoes.add(card.dataset.funcao_nome);
  });

  const filtroObra = document.getElementById("filtroObra");
  const filtroFuncao = document.getElementById("filtroFuncao");

  filtroObra.innerHTML =
    '<label><input type="checkbox" value=""> Todas as obras</label>';
  filtroFuncao.innerHTML =
    '<label><input type="checkbox" value=""> Todas as funções</label>';

  obras.forEach((o) => {
    filtroObra.innerHTML += `<label><input type="checkbox" value="${o}"> ${o}</label>`;
  });

  funcoes.forEach((f) => {
    filtroFuncao.innerHTML += `<label><input type="checkbox" value="${f}"> ${f}</label>`;
  });

  // Reaplica os eventos de filtro
  document
    .querySelectorAll(
      "#filtroObra input, #filtroFuncao input, #filtroStatus input",
    )
    .forEach((chk) => chk.addEventListener("change", aplicarFiltros));
}

const statusMapInvertido = {
  "to-do": "Não iniciado",
  "in-progress": "Em andamento",
  "in-review": "Em aprovação",
  ajuste: "Ajuste",
  aprovado: "Aprovado",
  "aprovado-ajustes": "Aprovado com ajustes",
  done: "Finalizado",
};

flatpickr("#prazoRange", {
  mode: "range",
  dateFormat: "Y-m-d",
  onChange: aplicarFiltros, // Chama a função de filtro sempre que mudar
});

const prazoInput = document.getElementById("prazoRange");
const resetBtn = document.getElementById("resetPrazo");

// Inicialmente esconde o botão
resetBtn.style.display = "none";

// Mostra/esconde o botão conforme o valor do input
prazoInput.addEventListener("input", () => {
  resetBtn.style.display = prazoInput.value ? "inline-block" : "none";
});

// Também mantém o botão escondido quando clicamos para resetar
resetBtn.addEventListener("click", () => {
  prazoInput.value = "";
  resetBtn.style.display = "none";
  aplicarFiltros(); // reaplica os filtros sem considerar o prazo
});

// Aplica os filtros selecionados
function aplicarFiltros() {
  const obrasSelecionadas = Array.from(
    document.querySelectorAll("#filtroObra input:checked"),
  )
    .map((el) => el.value)
    .filter((v) => v);
  const funcoesSelecionadas = Array.from(
    document.querySelectorAll("#filtroFuncao input:checked"),
  )
    .map((el) => el.value)
    .filter((v) => v);
  const statusSelecionados = Array.from(
    document.querySelectorAll("#filtroStatus input:checked"),
  )
    .map((el) => el.value)
    .filter((v) => v);

  const prazoRange = document.getElementById("prazoRange").value.split(" to "); // Flatpickr usa "to" para range
  const prazoInicio = prazoRange[0] ? new Date(prazoRange[0]) : null;
  const prazoFim = prazoRange[1] ? new Date(prazoRange[1]) : prazoInicio;

  document.querySelectorAll(".kanban-card").forEach((card) => {
    let mostrar = true;

    if (
      obrasSelecionadas.length &&
      !obrasSelecionadas.includes(card.dataset.obra_nome)
    )
      mostrar = false;
    if (
      funcoesSelecionadas.length &&
      !funcoesSelecionadas.includes(card.dataset.funcao_nome)
    )
      mostrar = false;
    if (
      statusSelecionados.length &&
      !statusSelecionados.includes(card.dataset.status)
    )
      mostrar = false;

    if (prazoInicio) {
      const cardPrazo = new Date(card.dataset.prazo);
      if (cardPrazo < prazoInicio || cardPrazo > prazoFim) mostrar = false;
    }

    card.style.display = mostrar ? "block" : "none";
  });

  atualizarTaskCount();
}

// Vincula eventos de mudança dos selects
["filtroObra", "filtroFuncao", "filtroStatus"].forEach((id) => {
  document.getElementById(id)?.addEventListener("change", aplicarFiltros);
});

function formatarData(data) {
  const partes = data.split("-");
  const dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
  return dataFormatada;
}

const buttons = document.querySelectorAll(".nav-left button");

buttons.forEach((btn) => {
  btn.addEventListener("click", () => {
    // Remove active de todos
    buttons.forEach((b) => b.classList.remove("active"));
    // Adiciona active no botão clicado
    btn.classList.add("active");
  });
});

const add_task = document.getElementById("add-task");
add_task.addEventListener("click", () => {
  const modal = document.getElementById("task-modal");
  modal.style.display = "flex";
  modal.classList.add("active");

  // pega id do colaborador no localStorage
  const selectColab = document.getElementById("task-colab");
  console.log("colab id:", colaborador_id);
  if (Number(colaborador_id) === 9 || Number(colaborador_id) === 21) {
    selectColab.disabled = false; // libera
  } else {
    selectColab.disabled = true; // bloqueia
    selectColab.classList.add("hidden");
  }
});

const form = document.getElementById("task-form");
const modal = document.getElementById("task-modal");
const closeBtn = document.getElementById("close-modal");

// Fecha o modal
closeBtn.addEventListener("click", () => {
  modal.style.display = "none";
});

// Submit AJAX
form.addEventListener("submit", (e) => {
  e.preventDefault();

  const formData = new FormData(form);

  fetch("PaginaPrincipal/addTask.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((response) => {
      if (response.success) {
        alert("✅ Tarefa adicionada com sucesso!");
        form.reset();
        modal.style.display = "none";
        // aqui você pode recarregar o Kanban
        carregarDados(colaborador_id);
      } else {
        alert("❌ Erro: " + response.message);
      }
    })
    .catch((err) => {
      console.error("Erro no fetch:", err);
      alert("Erro ao enviar tarefa.");
    });
});

const cardModal = document.getElementById("cardModal");
const modalPrazo = document.getElementById("modalPrazo");
const modalObs = document.getElementById("modalObs");
let cardSelecionado = null;

// Fechar modal
document.getElementById("fecharModal").addEventListener("click", () => {
  cardModal.classList.remove("active");
  cardSelecionado = null;
});

// Salvar alterações
document.getElementById("salvarModal").addEventListener("click", () => {
  if (!cardSelecionado) return;

  // Verifica se o prazo está vazio
  if (modalPrazo.offsetParent !== null && !modalPrazo.value) {
    Toastify({
      text: "Por favor, preencha o prazo antes de salvar.",
      duration: 3000,
      close: true,
      gravity: "top",
      position: "left",
      backgroundColor: "red",
    }).showToast();

    return; // interrompe o envio
  }

  cardSelecionado.dataset.prazo = modalPrazo.value;
  cardSelecionado.dataset.observacao = modalObs.value;

  // Mapeamento de IDs de coluna para status
  const statusMap = {
    "to-do": "Não iniciado",
    hold: "HOLD",
    "in-progress": "Em andamento",
    "in-review": "Em aprovação",
    ajuste: "Ajuste",
    aprovado: "Aprovado",
    "aprovado-ajustes": "Aprovado com ajustes",
    done: "Finalizado",
  };

  if (cardSelecionado.classList.contains("tarefa-criada")) {
    // Se for tarefa criada, atualiza via outro script
    const dadosTarefa = {
      tarefa_id: cardSelecionado.dataset.id, // ou outro atributo se necessário
      prazo: modalPrazo.value,
      observacao: modalObs.value,
      status: statusMap[cardSelecionado.closest(".kanban-box").id] || null,
    };

    $.ajax({
      type: "POST",
      url: "PaginaPrincipal/atualizaTarefa.php",
      data: dadosTarefa,
      success: function (response) {
        Toastify({
          text: "Tarefa atualizada com sucesso!",
          duration: 3000,
          close: true,
          gravity: "top",
          position: "left",
          backgroundColor: "green",
          stopOnFocus: true,
        }).showToast();
        cardModal.classList.remove("active");
        cardSelecionado = null;
        carregarDados(colaborador_id); // Recarrega o Kanban para refletir mudanças
      },
      error: function (jqXHR, textStatus, errorThrown) {
        console.error("Erro ao atualizar tarefa: " + textStatus, errorThrown);
        Toastify({
          text: "Erro ao atualizar tarefa.",
          duration: 3000,
          close: true,
          gravity: "top",
          position: "left",
          backgroundColor: "red",
          stopOnFocus: true,
        }).showToast();
      },
    });
  } else {
    // capture the original previous status from the card (provided by server as status_funcao_anterior)
    const previousStatus = (
      cardSelecionado.getAttribute("data-status-anterior") ||
      cardSelecionado.getAttribute("data-nome_status") ||
      cardSelecionado.dataset.status ||
      ""
    ).toString();

    const dados = {
      imagem_id: cardSelecionado.dataset.idImagem,
      funcao_id: cardSelecionado.dataset.idFuncao,
      cardId: cardSelecionado.dataset.id,
      status: statusMap[cardSelecionado.closest(".kanban-box").id] || null,
      prazo: modalPrazo.value,
      observacao: modalObs.value,
    };

    $.ajax({
      type: "POST",
      url: "insereFuncao.php",
      data: dados,
      success: function (response) {
        let payload = response;
        if (typeof payload === "string") {
          try {
            payload = JSON.parse(payload);
          } catch (e) {
            payload = { success: true };
          }
        }

        if (payload && (payload.error || payload.success === false)) {
          Toastify({
            text: payload.message || "Não foi possível atualizar o status.",
            duration: 4500,
            close: true,
            gravity: "top",
            position: "left",
            backgroundColor: "red",
            stopOnFocus: true,
          }).showToast();
          cardModal.classList.remove("active");
          cardSelecionado = null;
          carregarDados(colaborador_id);
          return;
        }

        Toastify({
          text: "Dados salvos com sucesso!",
          duration: 3000,
          close: true,
          gravity: "top",
          position: "left",
          backgroundColor: "green",
          stopOnFocus: true,
        }).showToast();
        cardModal.classList.remove("active");
        cardSelecionado = null;
        carregarDados(colaborador_id); // Recarrega o Kanban para refletir mudanças

        try {
          const novo = (dados.status || "").toString().toLowerCase();
          const prev = (previousStatus || "").toString().toLowerCase();
          if (novo === "em andamento" && prev === "aprovado com ajustes") {
            // open mind map and get data so we can show the previous function name
            abrirSidebar(dados.cardId, dados.imagem_id)
              .then((data) => {
                // ensure notifications container exists
                let notificacoesDiv = mindmapContent?.querySelector(
                  ".notificacoes-container",
                );
                if (!notificacoesDiv) {
                  notificacoesDiv = document.createElement("div");
                  notificacoesDiv.className = "notificacoes-container";
                  notificacoesDiv.innerHTML = `<h3>Notificações</h3>`;
                  const topSlot = mindmapContent?.querySelector(
                    ".slot-top .slot-inner",
                  );
                  if (topSlot) {
                    let notifBody = topSlot.querySelector(
                      ".mindmap-notifications-card .mindmap-node-body",
                    );
                    if (!notifBody) {
                      const card = document.createElement("div");
                      card.className =
                        "mindmap-card mindmap-node mindmap-notifications-card mindmap-notifications-focus";
                      const header = document.createElement("div");
                      header.className = "mindmap-node-title";
                      header.textContent = "Notificações";
                      const body = document.createElement("div");
                      body.className = "mindmap-node-body";
                      card.appendChild(header);
                      card.appendChild(body);
                      topSlot.appendChild(card);
                      notifBody = body;
                    }
                    notifBody.appendChild(notificacoesDiv);
                  }
                }

                // blur other cards while notifications exist
                if (mindmapContent)
                  mindmapContent.classList.add("mindmap-has-notifications");

                // build reminder message using function name from fetched data if available
                const funcName =
                  data &&
                  data.funcoes &&
                  data.funcoes[0] &&
                  data.funcoes[0].nome_funcao
                    ? data.funcoes[0].nome_funcao
                    : "";
                const prevReadable = previousStatus || "Aprovado com Ajustes";
                const mensagem = funcName
                  ? `Lembrete: Função "${funcName}" veio de \"${prevReadable}\". Verifique comentários/ajustes anteriores.`
                  : `Lembrete: Função veio de \"${prevReadable}\". Verifique comentários/ajustes anteriores.`;

                const reminder = document.createElement("div");
                reminder.className = "func-notif reminder";
                reminder.dataset.notId = "client-reminder-" + Date.now();

                const msgSpan = document.createElement("div");
                msgSpan.className = "msg";
                msgSpan.textContent = mensagem;

                const rightDiv = document.createElement("div");
                rightDiv.style.display = "flex";
                rightDiv.style.alignItems = "center";

                const dataSpan = document.createElement("div");
                dataSpan.className = "data";
                dataSpan.textContent = new Date().toISOString().split("T")[0];

                const markBtn = document.createElement("button");
                markBtn.className = "mark-btn";
                markBtn.textContent = "Fechar";

                function dismissReminder() {
                  try {
                    reminder.remove();
                    if (!notificacoesDiv.querySelector(".func-notif")) {
                      if (mindmapContent)
                        mindmapContent.classList.remove(
                          "mindmap-has-notifications",
                        );
                      notificacoesDiv.remove();
                    }
                  } catch (e) {
                    console.error("Erro ao remover lembrete:", e);
                  }
                }

                reminder.addEventListener("click", function (e) {
                  if (e.target === markBtn) return;
                  dismissReminder();
                });
                markBtn.addEventListener("click", function (e) {
                  e.stopPropagation();
                  dismissReminder();
                });

                rightDiv.appendChild(dataSpan);
                rightDiv.appendChild(markBtn);
                reminder.appendChild(msgSpan);
                reminder.appendChild(rightDiv);

                notificacoesDiv.insertBefore(
                  reminder,
                  notificacoesDiv.querySelector(".func-notif") || null,
                );

                // update card UI counter if present
                try {
                  const card = document.querySelector(
                    `.kanban-card[data-id="${dados.cardId}"]`,
                  );
                  if (card) {
                    let countEl = card.querySelector(".notif-count");
                    if (!countEl) {
                      const icon = document.createElement("span");
                      icon.className = "notif-icon";
                      icon.innerHTML = `<i class="ri-notification-3-line"></i><small class="notif-count">1</small>`;
                      card.querySelector(".header-kanban")?.appendChild(icon);
                    } else {
                      let n = Number(countEl.textContent || 0);
                      countEl.textContent = String(n + 1);
                    }
                  }
                } catch (e) {
                  console.error(
                    "Erro ao atualizar contador de notificação no card:",
                    e,
                  );
                }
              })
              .catch((err) =>
                console.error(
                  "Erro ao abrir sidebar para mostrar lembrete:",
                  err,
                ),
              );
          }
        } catch (e) {
          console.error("Erro na lógica pós-salvar:", e);
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        console.error("Erro ao salvar dados: " + textStatus, errorThrown);
        Toastify({
          text: "Erro ao salvar dados.",
          duration: 3000,
          close: true,
          gravity: "top",
          position: "left",
          backgroundColor: "red",
          stopOnFocus: true,
        }).showToast();
      },
    });
  }
});

function configurarDropzone(areaId, inputId, listaId, arquivosArray) {
  const dropArea = document.getElementById(areaId);
  const fileInput = document.getElementById(inputId);

  // Funções nomeadas para poder remover depois
  function handleDrop(e) {
    e.preventDefault();
    dropArea.classList.remove("highlight");
    for (let file of e.dataTransfer.files) arquivosArray.push(file);
    renderizarLista(arquivosArray, listaId);
  }
  function handleChange() {
    for (let file of fileInput.files) arquivosArray.push(file);
    renderizarLista(arquivosArray, listaId);
  }
  function handleClick() {
    fileInput.click();
  }
  function handleDragOver(e) {
    e.preventDefault();
    dropArea.classList.add("highlight");
  }
  function handleDragLeave() {
    dropArea.classList.remove("highlight");
  }

  // Remove listeners antigos
  dropArea.removeEventListener("click", dropArea._handleClick);
  dropArea.removeEventListener("dragover", dropArea._handleDragOver);
  dropArea.removeEventListener("dragleave", dropArea._handleDragLeave);
  dropArea.removeEventListener("drop", dropArea._handleDrop);
  fileInput.removeEventListener("change", fileInput._handleChange);

  // Adiciona listeners e guarda referência para remover depois
  dropArea.addEventListener("click", handleClick);
  dropArea.addEventListener("dragover", handleDragOver);
  dropArea.addEventListener("dragleave", handleDragLeave);
  dropArea.addEventListener("drop", handleDrop);
  fileInput.addEventListener("change", handleChange);

  // Guarda referência
  dropArea._handleClick = handleClick;
  dropArea._handleDragOver = handleDragOver;
  dropArea._handleDragLeave = handleDragLeave;
  dropArea._handleDrop = handleDrop;
  fileInput._handleChange = handleChange;
}

function renderizarLista(array, listaId) {
  const lista = document.getElementById(listaId);
  lista.innerHTML = "";
  array.forEach((file, i) => {
    // Calcula o tamanho em B, KB, MB ou GB
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
    li.innerHTML = `<div class="file-info">
            <span>${file.name} <small style="color:#888;">(${tamanhoStr})</small></span>
            <span onclick="removerArquivo(${i}, '${listaId}')" style="cursor:pointer;color: #c00;font-weight: bold;font-size: 1.2em;">×</span>
        </div>`;
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

var idfuncao_imagem = null;
var titulo = null;
var subtitulo = null;
var obra = null;
var idimagem = null;
var nome_status = null;
const dropArea = document.getElementById("drop-area");
const fileInput = document.getElementById("fileElem");
const fileList = document.getElementById("fileList");
let arquivosFinais = [];
let dataIdFuncoes = [];
let imagensSelecionadas = [];

// Inicializa Sortable nas colunas
const colunas = document.querySelectorAll(".kanban-box .content");
colunas.forEach((col) => {
  new Sortable(col, {
    group: "kanban",
    animation: 150,
    ghostClass: "sortable-ghost",
    touchStartThreshold: 10, // move 10px antes de iniciar o drag
    onMove: function (evt) {
      const fromId = evt.from.closest(".kanban-box")?.id;
      const toId = evt.to.closest(".kanban-box")?.id;
      const dragged = evt.dragged;
      const imagemEmHold = dragged?.dataset?.imagemEmHold === "1";
      const requiresFileUpload = dragged?.dataset?.requiresFileUpload === "1";
      const holdMovel = fromId === "hold" && !imagemEmHold;

      if (imagemEmHold) return false;

      if (toId === "in-progress" && requiresFileUpload) return false;

      if (dragged.classList.contains("bloqueado") && !holdMovel) return false;

      if (toId === "ajuste") return false;

      if (toId === "to-do" && fromId !== "to-do") return false;

      if (fromId === "em-andamento" && toId === "to-do") return false;

      return true; // caso contrário, libera o movimento
    },
    onEnd: (evt) => {
      const card = evt.item;
      const deColuna = evt.from.closest(".kanban-box");
      const novaColuna = evt.to.closest(".kanban-box");
      const novoIndex = evt.newIndex;
      const imagemEmHold = card?.dataset?.imagemEmHold === "1";
      const requiresFileUpload = card?.dataset?.requiresFileUpload === "1";
      const holdMovel = deColuna?.id === "hold" && !imagemEmHold;

      if (imagemEmHold) {
        evt.from.appendChild(card);
        alert("Esta função não pode ser movida porque a imagem está em HOLD.");
        return;
      }

      if (card.dataset.liberado === "0" && !holdMovel) {
        evt.from.appendChild(card);
        alert("Esta função ainda não foi liberada.");
        return;
      }

      if (novaColuna?.id === "in-progress" && requiresFileUpload) {
        evt.from.appendChild(card);
        alert(
          "Existe arquivo pendente da etapa anterior. Envie o arquivo final antes de mover para Em andamento.",
        );
        return;
      }

      // Se houver pendências ao mover para 'Em andamento', apenas avisamos
      // e impedimos a abertura do modal de card (cardModal). O movimento continua.
      let bloquearAberturaModal = false;
      if (novaColuna?.id === "in-progress") {
        const qtdPendentes = document.querySelectorAll(
          '.kanban-card.tarefa-imagem[data-requires-file-upload="1"]',
        ).length;
        if (qtdPendentes > 0) {
          Swal.fire({
            icon: "warning",
            title: "Atenção",
            text: `Existem ${qtdPendentes} card(s) com arquivo pendente.`,
          });
          bloquearAberturaModal = true;
        }
      }

      console.log(
        `Card movido de ${deColuna.id} para ${novaColuna.id}, índice: ${novoIndex}`,
      );

      // Só abre modal se mudou de coluna e não estivermos bloqueando a abertura
      if (deColuna.id !== novaColuna.id) {
        if (
          typeof bloquearAberturaModal !== "undefined" &&
          bloquearAberturaModal
        ) {
          // Não abre o cardModal por enquanto — apenas mantém o card movido.
          card.classList.remove("selected");
          return;
        }
        cardSelecionado = card;

        idfuncao_imagem = card.getAttribute("data-id");
        idimagem = card.getAttribute("data-id-imagem");
        titulo = card.querySelector("h5")?.innerText || "";
        subtitulo = card.getAttribute("data-funcao_nome");
        obra = card.getAttribute("data-obra_nome");
        nome_status = card.getAttribute("data-nome_status");

        // Preenche os campos comuns
        modalPrazo.value = card.dataset.prazo || "";
        modalObs.value = card.dataset.observacao || "";

        // Reset modal: mostra tudo inicialmente
        document.querySelector(".modalPrazo").style.display = "flex";
        document.querySelector(".modalObs").style.display = "flex";
        document.querySelector(".modalUploads").style.display = "flex";
        document.querySelector(".buttons").style.display = "flex";

        // Limpa listas de arquivos ao abrir o modal
        imagensSelecionadas = [];
        arquivosFinais = [];
        renderizarLista(imagensSelecionadas, "fileListPrevia");
        renderizarLista(arquivosFinais, "fileListFinal");

        // Ativar modal
        cardModal.classList.add("active");

        cardSelecionado.classList.add("selected");
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
        // Garantir que as sub-etapas (prévia / arquivo final) estejam visíveis por padrão
        const etapaPreviaEl = document.getElementById("etapaPrevia");
        const etapaFinalEl = document.getElementById("etapaFinal");
        if (etapaPreviaEl) etapaPreviaEl.style.display = "";
        if (etapaFinalEl) etapaFinalEl.style.display = "";

        // Ajusta modal de acordo com a coluna de destino
        switch (novaColuna.id) {
          case "hold":
            // Apenas observação e botões
            document.querySelector(".modalPrazo").style.display = "none";
            document.querySelector(".modalUploads").style.display = "none";
            document.querySelector(".statusAnterior").style.display = "none";
            break;
          case "in-progress":
            // Apenas observação e botões
            document.querySelector(".modalUploads").style.display = "none";
            document.querySelector(".statusAnterior").style.display = "flex";
            break;
          case "in-review": // "Em aprovação"
            // Mostra ambos inputs de arquivo (prévia e arquivo final)
            document.querySelector(".modalPrazo").style.display = "none";
            document.querySelector(".modalObs").style.display = "none";
            document.querySelector(".modalUploads").style.display = "flex";
            document.querySelector(".buttons").style.display = "none";
            document.querySelector(".statusAnterior").style.display = "none";
            // // Em aprovação: somente envio de PRÉVIA (esconde envio de arquivo final)
            // if (etapaPreviaEl) etapaPreviaEl.style.display = '';
            // if (etapaFinalEl) etapaFinalEl.style.display = 'none';
            break;
          case "done": // "Finalizado"
            // Mostra prazo, observação e botões
            document.querySelector(".modalPrazo").style.display = "flex";
            document.querySelector(".modalObs").style.display = "flex";
            document.querySelector(".modalUploads").style.display = "flex";
            document.querySelector(".statusAnterior").style.display = "flex";
            // // Finalizado: somente envio do ARQUIVO FINAL (esconde prévias)
            // if (etapaPreviaEl) etapaPreviaEl.style.display = 'none';
            // if (etapaFinalEl) etapaFinalEl.style.display = '';
            break;
          default:
            // padrão: tudo visível
            break;
        }

        // ✅ Sobrescreve se for tarefa-criada (regra final)
        if (card.classList.contains("tarefa-criada")) {
          document.querySelector(".modalPrazo").style.display = "flex";
          document.querySelector(".modalObs").style.display = "flex";
          document.querySelector(".buttons").style.display = "flex";
          document.querySelector(".modalUploads").style.display = "none";
          document.querySelector(".statusAnterior").style.display = "none";
        }

        // Posicionar modal ao lado da coluna de destino
        const rect = novaColuna.getBoundingClientRect();
        const modalWidth = cardModal.offsetWidth;
        const modalHeight = cardModal.offsetHeight;

        let left = rect.right + 10;
        let top = rect.top + 10;

        if (left + modalWidth > window.innerWidth) {
          left = rect.left - modalWidth - 10;
        }
        if (top + modalHeight > window.innerHeight) {
          top = window.innerHeight - modalHeight - 10;
          if (top < 10) top = 10;
        }

        cardModal.style.left = `${left}px`;
        cardModal.style.top = `${top}px`;
      }
    },
  });
});

function enviarImagens() {
  if (imagensSelecionadas.length === 0) {
    Toastify({
      text: "Selecione pelo menos uma imagem para enviar a prévia.",
      duration: 3000,
      gravity: "top",
      backgroundColor: "#f44336",
    }).showToast();
    return;
  }

  const formData = new FormData();
  imagensSelecionadas.forEach((file) => formData.append("imagens[]", file));
  formData.append("dataIdFuncoes", idfuncao_imagem);
  formData.append("idimagem", idimagem);
  formData.append("nome_funcao", subtitulo);
  formData.append("nome_imagem", titulo);

  const numeroImagem = titulo.match(/^\d+/)?.[0] || "";
  formData.append("numeroImagem", numeroImagem);
  formData.append("nomenclatura", obra);

  // Extrai a primeira palavra da descrição (depois da nomenclatura)
  // aceita letras maiúsculas, underscores e dígitos na nomenclatura (ex: MEN_991)
  const descricaoMatch = titulo.match(/^\d+\.\s*[A-Z0-9_]+\s+([^\s]+)/i);
  const primeiraPalavra = descricaoMatch ? descricaoMatch[1] : "";
  formData.append("primeiraPalavra", primeiraPalavra);

  // Container de progresso
  const progressContainer = document.createElement("div");
  progressContainer.style.fontSize = "16px";
  progressContainer.innerHTML = `
        <progress id="uploadProgress" value="0" max="100" style="width:100%;height:20px;"></progress>
        <div id="uploadStatus">Enviando... 0%</div>
        <div id="uploadTempo">Tempo: 0s</div>
        <div id="uploadVelocidade">Velocidade: 0 MB/s</div>
        <div id="uploadEstimativa">Tempo restante: ...</div>
        <button id="cancelarUpload" style="margin-top:10px;padding:5px 10px;">Cancelar</button>
    `;

  Swal.fire({
    title: "Enviando prévia...",
    html: progressContainer,
    showConfirmButton: false,
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    didOpen: () => {
      // Avoid backdrop clicks bubbling to global handlers (which might close other modals/kanban UI)
      try {
        const container = Swal.getContainer();
        if (container) {
          ["click", "mousedown", "touchstart", "pointerdown"].forEach((evt) => {
            container.addEventListener(evt, (e) => e.stopPropagation(), true);
          });
        }
      } catch (e) {}

      const xhr = new XMLHttpRequest();
      const startTime = Date.now();
      let uploadCancelado = false;

      xhr.open("POST", "uploadArquivos.php");

      xhr.upload.addEventListener("progress", (e) => {
        if (e.lengthComputable) {
          const now = Date.now();
          const elapsed = (now - startTime) / 1000;
          const uploadedMB = e.loaded / (1024 * 1024);
          const totalMB = e.total / (1024 * 1024);
          const percent = (e.loaded / e.total) * 100;
          const speed = uploadedMB / elapsed;
          const remainingMB = totalMB - uploadedMB;
          const estimatedTime = remainingMB / (speed || 1);

          document.getElementById("uploadProgress").value = percent;
          document.getElementById("uploadStatus").innerText =
            `Enviando... ${percent.toFixed(2)}%`;
          document.getElementById("uploadTempo").innerText =
            `Tempo: ${elapsed.toFixed(1)}s`;
          document.getElementById("uploadVelocidade").innerText =
            `Velocidade: ${speed.toFixed(2)} MB/s`;
          document.getElementById("uploadEstimativa").innerText =
            `Tempo restante: ${estimatedTime.toFixed(1)}s`;
        }
      });

      xhr.onreadystatechange = () => {
        if (xhr.readyState === 4 && !uploadCancelado) {
          try {
            const res = JSON.parse(xhr.responseText);

            if (res.error) {
              Toastify({
                text: "Erro: " + res.error,
                duration: 3000,
                gravity: "top",
                backgroundColor: "#f44336",
              }).showToast();
            } else {
              Swal.fire({
                position: "center",
                icon: "success",
                title: "Prévia enviada com sucesso!",
                showConfirmButton: false,
                timer: 2000,
              });
            }
          } catch (err) {
            Toastify({
              text: "Erro ao processar resposta do servidor",
              duration: 3000,
              gravity: "top",
              backgroundColor: "#f44336",
            }).showToast();
            console.error(err);
          }
        }
      };

      xhr.onerror = () => {
        if (!uploadCancelado) {
          Toastify({
            text: "Erro ao enviar prévia",
            duration: 3000,
            gravity: "top",
            backgroundColor: "#f44336",
          }).showToast();
        }
      };

      document
        .getElementById("cancelarUpload")
        .addEventListener("click", () => {
          uploadCancelado = true;
          xhr.abort();
          Swal.fire({
            icon: "warning",
            title: "Upload cancelado",
            showConfirmButton: false,
            timer: 1500,
          });
        });

      xhr.send(formData);
    },
  });
}

function enviarArquivo() {
  if (arquivosFinais.length === 0) {
    Toastify({
      text: "Selecione pelo menos um arquivo para enviar o final.",
      duration: 3000,
      gravity: "top",
      backgroundColor: "#f44336",
    }).showToast();
    return;
  }

  // Monta os campos exatamente como o backend espera
  const file = arquivosFinais[0];
  const formData = new FormData();
  formData.append("arquivo_final", file);
  formData.append("dataIdFuncoes", JSON.stringify([idfuncao_imagem]));
  formData.append("idimagem", idimagem);
  formData.append("nome_funcao", subtitulo);
  const campoNomeImagem = titulo;
  formData.append("nome_imagem", campoNomeImagem);

  const numeroImagem = campoNomeImagem.match(/^\d+/)?.[0] || "";
  formData.append("numeroImagem", numeroImagem);
  const nomenclatura = obra;
  formData.append("nomenclatura", nomenclatura);
  const descricaoMatch = campoNomeImagem.match(
    /^\d+\.\s*[A-Z0-9_]+\s+([^\s]+)/i,
  );
  const primeiraPalavra = descricaoMatch ? descricaoMatch[1] : "";
  formData.append("primeiraPalavra", primeiraPalavra);
  formData.append("idcolaborador", colaborador_id);

  // Progresso visual da fase 1 (HTTP enqueue)
  const progressContainer = document.createElement("div");
  progressContainer.style.fontSize = "16px";
  progressContainer.innerHTML = `
        <progress id="uploadProgress" value="0" max="100" style="width: 100%; height: 20px;"></progress>
        <div id="uploadStatus">Enviando... 0%</div>
        <div id="uploadTempo">Tempo: 0s</div>
        <div id="uploadVelocidade">Velocidade: 0 MB/s</div>
        <div id="uploadEstimativa">Tempo restante: ...</div>
        <button id="cancelarUpload" style="margin-top:10px;padding:5px 10px;">Cancelar</button>
    `;

  Swal.fire({
    title: "Enviando arquivo...",
    html: progressContainer,
    showConfirmButton: false,
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    didOpen: () => {
      // Avoid backdrop clicks bubbling to global handlers (which might close other modals/kanban UI)
      try {
        const container = Swal.getContainer();
        if (container) {
          ["click", "mousedown", "touchstart", "pointerdown"].forEach((evt) => {
            container.addEventListener(evt, (e) => e.stopPropagation(), true);
          });
        }
      } catch (e) {}

      const xhr = new XMLHttpRequest();
      const startTime = Date.now();
      let uploadCancelado = false;

      xhr.open(
        "POST",
        "https://improov.com.br/flow/ImproovWeb/upload_enqueue.php",
      );

      xhr.upload.addEventListener("progress", (e) => {
        if (e.lengthComputable) {
          const now = Date.now();
          const elapsed = (now - startTime) / 1000;
          const uploadedMB = e.loaded / (1024 * 1024);
          const totalMB = e.total / (1024 * 1024);
          const percent = (e.loaded / e.total) * 100;
          const speed = uploadedMB / elapsed;
          const remainingMB = totalMB - uploadedMB;
          const estimatedTime = remainingMB / (speed || 1);

          document.getElementById("uploadProgress").value = percent;
          document.getElementById("uploadStatus").innerText =
            `Enviando... ${percent.toFixed(2)}%`;
          document.getElementById("uploadTempo").innerText =
            `Tempo: ${elapsed.toFixed(1)}s`;
          document.getElementById("uploadVelocidade").innerText =
            `Velocidade: ${speed.toFixed(2)} MB/s`;
          document.getElementById("uploadEstimativa").innerText =
            `Tempo restante: ${estimatedTime.toFixed(1)}s`;
        }
      });

      xhr.onload = () => {
        if (uploadCancelado) return;
        let res = null;
        try {
          res = JSON.parse(xhr.responseText || "null");
        } catch (err) {
          console.error("Resposta não-JSON do servidor:", xhr.responseText);
        }

        if (xhr.status >= 200 && xhr.status < 300) {
          Swal.fire({
            position: "center",
            icon: "success",
            text: "Arquivo enfileirado. O envio continuará em segundo plano.",
            showConfirmButton: false,
            timer: 2000,
          });
          cardModal.classList.remove("active");
          cardSelecionado = null;
          carregarDados(colaborador_id);

          // Assinar progresso em tempo real (opcional)
          try {
            if (window.subscribeUploadProgress) {
              const id = Array.isArray(res) && res[0]?.id ? res[0].id : null;
              if (id) {
                window.subscribeUploadProgress(id, function (payload) {
                  // atualizar algum indicador se desejar
                });
              }
            }
          } catch (e) {
            console.warn("Progresso em tempo real indisponível:", e);
          }
        } else {
          const serverMsg = xhr.responseText
            ? xhr.responseText
            : `Status ${xhr.status}`;
          Swal.close();
          Toastify({
            text: "Erro no servidor: " + serverMsg,
            duration: 6000,
            gravity: "top",
            backgroundColor: "#f44336",
          }).showToast();
        }
      };

      xhr.onerror = () => {
        if (!uploadCancelado) {
          Swal.close();
          Toastify({
            text: "Erro ao enfileirar arquivo",
            duration: 3000,
            gravity: "top",
            backgroundColor: "#f44336",
          }).showToast();
        }
      };

      document
        .getElementById("cancelarUpload")
        .addEventListener("click", () => {
          uploadCancelado = true;
          xhr.abort();
          Swal.fire({
            icon: "warning",
            title: "Upload cancelado",
            showConfirmButton: false,
            timer: 1500,
          });
        });

      xhr.send(formData);
    },
  });
}

const btnFilter = document.getElementById("filter");
const modalFilter = document.getElementById("modalFilter");

btnFilter.addEventListener("click", function (e) {
  e.stopPropagation(); // impede que o clique no botão feche o modal
  modalFilter.classList.add("active");

  const rect = btnFilter.getBoundingClientRect();
  modalFilter.style.left = `${rect.left + rect.width / 2 - modalFilter.offsetWidth / 2}px`;
  modalFilter.style.top = `${rect.bottom + 5}px`; // 5px de espaçamento
});

// Fecha modal ao clicar fora ou pressionar Esc
document.addEventListener("click", function (e) {
  if (
    modalFilter.classList.contains("active") &&
    !modalFilter.contains(e.target) &&
    e.target !== btnFilter
  ) {
    modalFilter.classList.remove("active");
    // remove seleção dos outros
    document.querySelectorAll(".dropdown-content.show").forEach((c) => {
      c.classList.remove("show");
    });
  }
});

["click", "touchstart", "keydown"].forEach((eventType) => {
  window.addEventListener(eventType, function (event) {
    // Fecha os modais ao clicar fora ou pressionar Esc
    if (eventType === "keydown" && event.key !== "Escape") return;

    // if (event.target == cardModal || (eventType === 'keydown' && event.key === 'Escape')) {
    //     cardModal.classList.remove('active');
    // }
    // if (!cardModal.querySelector('.modal-content').contains(event.target)) {
    //     cardModal.classList.remove('active');
    // }
  });
});

document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") {
    if (modalFilter.classList.contains("active")) {
      modalFilter.classList.remove("active");
    }
    if (cardModal.classList.contains("active")) {
      cardModal.classList.remove("active");
    }
  }
});

document.querySelectorAll(".dropbtn").forEach((btn) => {
  btn.addEventListener("click", function (e) {
    e.stopPropagation();

    // Fecha todos antes
    document
      .querySelectorAll(".dropdown-content")
      .forEach((dc) => dc.classList.remove("show"));

    // Pega o dropdown-content mais próximo do botão clicado
    const dropdown =
      this.closest(".dropdown").querySelector(".dropdown-content");
    dropdown.classList.toggle("show");
  });
});

// List view (Tabulator) - visual only
(function () {
  let tabelaLista = null;

  function normalizarStatus(status) {
    if (!status) return "Não iniciado";
    if (status === "Hold") return "HOLD";
    if (status === "Aprovado" || status === "Aprovado com ajustes")
      return "Finalizado";
    return status;
  }

  function garantirTabela() {
    if (tabelaLista || !window.Tabulator) return;

    tabelaLista = new Tabulator("#tarefas-table", {
      layout: "fitColumns",
      height: "70vh",
      placeholder: "Sem tarefas para exibir",
      reactiveData: false,
      movableColumns: false,
      columns: [
        {
          title: "Nome da imagem",
          field: "imagem_nome",
          headerFilter: "input",
          headerFilterPlaceholder: "Filtrar imagem...",
          sorter: "string",
        },
        {
          title: "Nome da função",
          field: "nome_funcao",
          headerFilter: "input",
          headerFilterPlaceholder: "Filtrar função...",
          sorter: "string",
        },
        {
          title: "Status",
          field: "status",
          headerFilter: "list",
          headerFilterParams: {
            values: [
              "Não iniciado",
              "HOLD",
              "Em andamento",
              "Em aprovação",
              "Ajuste",
              "Finalizado",
            ],
            clearable: true,
          },
          headerFilterFunc: "=",
          formatter: function (cell) {
            const v = (cell.getValue() || "").toString();
            const statusKey = v
              .normalize("NFD")
              .replace(/[\u0300-\u036f]/g, "")
              .toLowerCase()
              .replace(/\s+/g, "-")
              .replace(/[^a-z0-9\-]/g, "");

            return `<span class="status-pill status-${statusKey}">${v || "-"}</span>`;
          },
          sorter: "string",
        },
      ],
    });
  }

  // Exposta para o scriptIndex.js reaproveitar o mesmo payload do Kanban
  window.updateListaTabela = function (payload) {
    try {
      garantirTabela();
      if (!tabelaLista) return;

      const funcoes =
        payload && Array.isArray(payload.funcoes) ? payload.funcoes : [];

      const linhas = funcoes.map((f) => ({
        imagem_nome: f.imagem_nome || "-",
        nome_funcao: f.nome_funcao || "-",
        status: normalizarStatus(f.status),
      }));

      tabelaLista.setData(linhas);
    } catch (e) {
      console.error("updateListaTabela error", e);
    }
  };
})();

// ─────────────────────────────────────────────────────────────────
//  Visão Geral (Overview) – carrega uma vez por sessão de página
// ─────────────────────────────────────────────────────────────────
let _overviewLoaded = false;

async function carregarOverview() {
  if (_overviewLoaded) return;
  _overviewLoaded = true;

  // ── Banner + Calendar ──────────────────────────────────────────
  let entregas = [];
  try {
    const res = await fetch("Entregas/listar_entregas.php");
    entregas = await res.json();
    if (!Array.isArray(entregas)) entregas = [];
  } catch (e) {
    console.error("carregarOverview: erro ao buscar entregas", e);
  }

  const hoje = new Date();
  hoje.setHours(0, 0, 0, 0);
  const em15 = new Date(hoje);
  em15.setDate(em15.getDate() + 15);

  const atrasadas = entregas.filter((e) => e.kanban_status === "atrasada");
  const proximas = entregas.filter((e) => {
    if (e.kanban_status === "concluida" || e.kanban_status === "atrasada")
      return false;
    const dp = new Date(e.data_prevista + "T00:00:00");
    return dp >= hoje && dp <= em15;
  });

  function _fmtDt(str) {
    if (!str) return "";
    const [, m, d] = str.split("-");
    return `${d}/${m}`;
  }

  const listAtrasadas = document.getElementById("banner-atrasadas-list");
  if (listAtrasadas) {
    if (atrasadas.length === 0) {
      listAtrasadas.innerHTML =
        '<p class="banner-empty">Nenhuma entrega atrasada ✓</p>';
    } else {
      listAtrasadas.innerHTML = atrasadas
        .map(
          (e) => `
                <div class="banner-item banner-item-atrasada" data-obra-id="${e.obra_id}" data-entrega-id="${e.id}">
                    <span class="banner-item-obra">${e.nomenclatura}</span>
                    <span class="banner-item-etapa">${e.nome_etapa}</span>
                    <span class="banner-item-data">${_fmtDt(e.data_prevista)}</span>
                </div>`,
        )
        .join("");

      // abrir modal ao clicar em uma entrega atrasada
      listAtrasadas.addEventListener("click", (ev) => {
        const item = ev.target.closest(".banner-item");
        if (!item) return;
        const obraId = item.dataset.obraId || item.getAttribute("data-obra-id");
        const nome =
          item.querySelector(".banner-item-obra")?.textContent || "Obra";
        const etapa = item.querySelector(".banner-item-etapa")?.textContent?.trim() || "";
        if (obraId) openObraImagesModal(Number(obraId), nome, etapa);
      });
    }
  }

  const listProximas = document.getElementById("banner-proximas-list");
  if (listProximas) {
    if (proximas.length === 0) {
      listProximas.innerHTML =
        '<p class="banner-empty">Sem entregas nos próximos 15 dias</p>';
    } else {
      listProximas.innerHTML = proximas
        .map((e) => {
          const dp = new Date(e.data_prevista + "T00:00:00");
          const diff = Math.round((dp - hoje) / 86400000);
          return `
                <div class="banner-item banner-item-proxima" data-obra-id="${e.obra_id}" data-entrega-id="${e.id}">
                    <span class="banner-item-obra">${e.nomenclatura}</span>
                    <span class="banner-item-etapa">${e.nome_etapa}</span>
                    <span class="banner-item-data">${diff === 0 ? "Hoje" : diff + "d"}</span>
                </div>`;
        })
        .join("");

      listProximas.addEventListener("click", (ev) => {
        const item = ev.target.closest(".banner-item");
        if (!item) return;
        const obraId = item.dataset.obraId || item.getAttribute("data-obra-id");
        const nome =
          item.querySelector(".banner-item-obra")?.textContent || "Obra";
        const etapa = item.querySelector(".banner-item-etapa")?.textContent?.trim() || "";
        if (obraId) openObraImagesModal(Number(obraId), nome, etapa);
      });
    }
  }

  // ── Atualizar contadores dos indicadores compactos ──
  const cntAtrasadas = document.getElementById("indicator-atrasadas-count");
  if (cntAtrasadas) cntAtrasadas.textContent = atrasadas.length;
  const cntProximas = document.getElementById("indicator-proximas-count");
  if (cntProximas) cntProximas.textContent = proximas.length;

  // ── Toggle dropdown dos indicator-cards ──
  document.querySelectorAll(".indicator-card").forEach((card) => {
    card.addEventListener("click", (e) => {
      // Fecha outros abertos
      document.querySelectorAll(".indicator-card.open").forEach((c) => {
        if (c !== card) c.classList.remove("open");
      });
      card.classList.toggle("open");
      e.stopPropagation();
    });
  });
  // Fechar ao clicar fora
  document.addEventListener("click", () => {
    document
      .querySelectorAll(".indicator-card.open")
      .forEach((c) => c.classList.remove("open"));
  });

  // ── Calendar ───────────────────────────────────────────────────
  const calendarEl = document.getElementById("overview-calendar");
  if (calendarEl && window.FullCalendar) {
    const eventos = entregas.map((e) => ({
      id: e.id,
      title: `${e.nomenclatura} – ${e.nome_etapa}`,
      start: e.data_prevista,
      allDay: true,
      backgroundColor:
        e.kanban_status === "atrasada"
          ? "#ef4444"
          : e.kanban_status === "parcial"
            ? "#f59e0b"
            : e.kanban_status === "concluida"
              ? "#22c55e"
              : "#3b82f6",
      borderColor: "transparent",
      textColor: "#fff",
      extendedProps: {
        obra_id: e.obra_id,
        entrega_id: e.id,
        kanban_status: e.kanban_status,
      },
    }));

    const overviewCal = new FullCalendar.Calendar(calendarEl, {
      initialView: "dayGridMonth",
      locale: "pt-br",
      height: "100%",
      weekends: true,
      headerToolbar: { left: "prev", center: "title", right: "next" },
      events: eventos,
      eventClick: function (info) {
        try {
          info.jsEvent?.stopPropagation();
        } catch (e) {}
        const obraId = info.event.extendedProps?.obra_id || null;
        const titulo = info.event.title || "";
        // Extrai etapa do título (formato: "NOMENCLATURA – ETAPA")
        const etapaParts = titulo.split("–");
        const etapaFromTitle = etapaParts.length > 1 ? etapaParts[etapaParts.length - 1].trim() : "";
        if (obraId) {
          openObraImagesModal(Number(obraId), titulo, etapaFromTitle);
        } else {
          // fallback: show details
          try {
            showEventDetails(info.event, info.el);
          } catch (e) {
            console.error(e);
          }
        }
      },
    });
    overviewCal.render();
  }

  // ── Dashboard de produção ──────────────────────────────────────
  const now = new Date();
  const mes = now.getMonth() + 1;
  const ano = now.getFullYear();
  const mesesNomes = [
    "Janeiro",
    "Fevereiro",
    "Março",
    "Abril",
    "Maio",
    "Junho",
    "Julho",
    "Agosto",
    "Setembro",
    "Outubro",
    "Novembro",
    "Dezembro",
  ];

  const labelEl = document.getElementById("overview-mes-label");
  if (labelEl) labelEl.textContent = `${mesesNomes[mes - 1]} ${ano}`;

  const tbody = document.getElementById("overview-prod-tbody");
  try {
    const resProd = await fetch(
      `TelaGerencial/buscar_producao_funcao.php?mes=${mes}&ano=${ano}`,
    );
    const dadosProd = await resProd.json();

    if (!dadosProd || dadosProd.error || !dadosProd.length) {
      if (tbody)
        tbody.innerHTML =
          '<tr><td colspan="3" class="overview-loading">Sem dados para este mês</td></tr>';
      return;
    }

    if (tbody) {
      tbody.innerHTML = dadosProd
        .map((row) => {
          const qtd = parseInt(row.quantidade) || 0;
          const recorde = parseInt(row.recorde_producao) || qtd;
          const pct =
            recorde > 0 ? Math.min(100, Math.round((qtd / recorde) * 100)) : 0;
          return `
                    <tr>
                        <td class="prod-funcao">${row.nome_funcao}</td>
                        <td class="prod-qtd">
                            <div class="prod-bar-wrap">
                                <div class="prod-bar-track">
                                    <div class="prod-bar" style="width:${pct}%"></div>
                                </div>
                                <span>${qtd}</span>
                            </div>
                        </td>
                        <td class="prod-recorde">${recorde}</td>
                    </tr>`;
        })
        .join("");
    }
  } catch (e) {
    console.error("carregarOverview: erro ao buscar produção", e);
    if (tbody)
      tbody.innerHTML =
        '<tr><td colspan="3" class="overview-loading">Erro ao carregar dados</td></tr>';
  }
}

// ─────────────────────────────────────────────────────────────────
//  Toggle entre Visão Geral, Kanban e Lista
// ─────────────────────────────────────────────────────────────────
(function () {
  const btnOverview = document.getElementById("overviewBtn");
  const btnKanban = document.getElementById("kanbanBtn");
  const btnLista = document.getElementById("listBtn");
  const overviewSec = document.getElementById("overview-section");
  const kanbanSec = document.getElementById("kanban-section");
  const listSec = document.getElementById("list-section");
  const navRight = document.querySelector("main nav .nav-right");

  if (!btnKanban || !btnLista || !kanbanSec || !listSec) return;

  const isGestorView = [1, 9, 21].includes(colaborador_id);

  function showOverview() {
    if (kanbanSec) kanbanSec.style.display = "none";
    if (listSec) listSec.style.display = "none";
    if (overviewSec) overviewSec.style.display = "block";
    if (btnOverview) btnOverview.classList.add("active");
    btnKanban.classList.remove("active");
    btnLista.classList.remove("active");
    if (navRight) navRight.style.visibility = "hidden";
    carregarOverview();
  }

  function showKanban() {
    if (overviewSec) overviewSec.style.display = "none";
    listSec.style.display = "none";
    kanbanSec.style.display = "flex";
    btnKanban.classList.add("active");
    btnLista.classList.remove("active");
    if (btnOverview) btnOverview.classList.remove("active");
    if (navRight) navRight.style.visibility = "visible";
  }

  function showLista() {
    if (overviewSec) overviewSec.style.display = "none";
    kanbanSec.style.display = "none";
    listSec.style.display = "block";
    btnLista.classList.add("active");
    btnKanban.classList.remove("active");
    if (btnOverview) btnOverview.classList.remove("active");
    if (navRight) navRight.style.visibility = "visible";
  }

  // Mostrar botão e ativar overview por padrão para gestores
  if (isGestorView && btnOverview) {
    btnOverview.style.display = "flex";
    showOverview();
  }

  if (btnOverview) btnOverview.addEventListener("click", showOverview);
  btnKanban.addEventListener("click", showKanban);
  btnLista.addEventListener("click", showLista);
})();

// -------------------------
// Modal de Imagens da Obra — réplica fiel da tabela de obra.php
// -------------------------
let currentObraId = null;
let modalDadosImagens = []; // armazena dados para filtros

// --- Tooltip compartilhado ---
const modalTooltip = (() => {
  let el = document.getElementById("modal-obra-tooltip");
  if (!el) {
    el = document.createElement("div");
    el.id = "modal-obra-tooltip";
    el.style.cssText =
      "position:fixed;z-index:9999;background:#333;color:#fff;padding:4px 8px;border-radius:4px;font-size:11px;pointer-events:none;display:none;max-width:300px;white-space:pre-wrap;";
    document.body.appendChild(el);
  }
  return el;
})();

// --- Helpers (replicados do scriptObra.js) ---
function modalFormatarDataDiaMes(data) {
  if (!data && data !== 0) return "-";
  try {
    const s = String(data).trim();
    if (s.indexOf("/") !== -1) {
      const parts = s.split("/");
      return parts.length >= 2 ? parts[0] + "/" + parts[1] : s;
    }
    const partes = s.split("-");
    if (partes.length < 3) return s;
    return partes[2] + "/" + partes[1];
  } catch (e) {
    return "-";
  }
}

function modalDisplayImageName(name) {
  if (!name && name !== 0) return "";
  const s = String(name);
  const firstSpace = s.indexOf(" ");
  if (firstSpace === -1) return s;
  const left = s.slice(0, firstSpace);
  const right = s.slice(firstSpace + 1).replace(/_/g, "/");
  return left + " " + right;
}

function modalNormalizeFilterValue(value) {
  if (value === null || value === undefined) return "";
  return String(value).trim().toLowerCase();
}

// --- Status colors (imagem) ---
function modalApplyStatusImagem(cell, status, descricao) {
  switch (status) {
    case "P00":
      cell.style.backgroundColor = "#ffc21c";
      cell.style.color = "black";
      break;
    case "R00":
      cell.style.backgroundColor = "#1cf4ff";
      cell.style.color = "black";
      break;
    case "R01":
      cell.style.backgroundColor = "#ff6200";
      cell.style.color = "black";
      break;
    case "R02":
      cell.style.backgroundColor = "#ff3c00";
      cell.style.color = "black";
      break;
    case "R03":
      cell.style.backgroundColor = "#ff0000";
      cell.style.color = "black";
      break;
    case "R04":
      cell.style.backgroundColor = "#6449ff";
      cell.style.color = "black";
      break;
    case "R05":
      cell.style.backgroundColor = "#7d36f7";
      cell.style.color = "black";
      break;
    case "EF":
      cell.style.backgroundColor = "#0dff00";
      cell.style.color = "black";
      break;
    case "HOLD":
      cell.style.backgroundColor = "#ff0000";
      cell.addEventListener("mouseenter", (event) => {
        modalTooltip.textContent =
          descricao && String(descricao).trim()
            ? descricao
            : "HOLD sem justificativa cadastrada.";
        modalTooltip.style.display = "block";
        modalTooltip.style.left = event.clientX + "px";
        modalTooltip.style.top = event.clientY - 30 + "px";
      });
      cell.addEventListener("mouseleave", () => {
        modalTooltip.style.display = "none";
      });
      cell.addEventListener("mousemove", (event) => {
        modalTooltip.style.left = event.clientX + "px";
        modalTooltip.style.top = event.clientY - 30 + "px";
      });
      break;
    case "TEA":
      cell.style.backgroundColor = "#f7eb07";
      cell.style.color = "black";
      break;
    case "REN":
      cell.style.backgroundColor = "#0c9ef2";
      break;
    case "APR":
      cell.style.backgroundColor = "#0c45f2";
      cell.style.color = "white";
      break;
    case "APP":
      cell.style.backgroundColor = "#7d36f7";
      break;
    case "RVW":
      cell.style.backgroundColor = "green";
      cell.style.color = "white";
      break;
    case "OK":
      cell.style.backgroundColor = "cornflowerblue";
      cell.style.color = "white";
      break;
    case "TO-DO":
      cell.style.backgroundColor = "cornflowerblue";
      cell.style.color = "white";
      break;
    case "FIN":
      cell.style.backgroundColor = "green";
      cell.style.color = "white";
      break;
    case "DRV":
      cell.style.backgroundColor = "#00f3ff";
      cell.style.color = "black";
      break;
  }
}

// --- Status colors (funções) ---
function modalApplyStatusStyle(cell, status, colaborador) {
  if (colaborador === "Não se aplica") return;
  switch (status) {
    case "Finalizado":
      cell.style.backgroundColor = "green";
      cell.style.color = "white";
      break;
    case "Em andamento":
      cell.style.backgroundColor = "#f7eb07";
      cell.style.color = "black";
      break;
    case "Em aprovação":
      cell.style.backgroundColor = "#0c45f2";
      cell.style.color = "white";
      break;
    case "Aprovado":
      cell.style.backgroundColor = "lightseagreen";
      cell.style.color = "black";
      break;
    case "Ajuste":
      cell.style.backgroundColor = "orangered";
      cell.style.color = "black";
      break;
    case "Aprovado com ajustes":
      cell.style.backgroundColor = "mediumslateblue";
      cell.style.color = "black";
      break;
    case "Não iniciado":
      cell.style.backgroundColor = "#eee";
      cell.style.color = "black";
      break;
    case "HOLD":
      cell.style.backgroundColor = "#ff0000";
      cell.style.color = "black";
      break;
    default:
      cell.style.backgroundColor = "";
      cell.style.color = "";
      break;
  }
}

function modalApplyStyleNone(cell, cell2, nome) {
  if (nome === "Não se aplica") {
    if (cell) {
      cell.style.backgroundColor = "#b4b4b4";
      cell.style.color = "black";
    }
    if (cell2) {
      cell2.style.backgroundColor = "#b4b4b4";
      cell2.style.color = "black";
    }
  }
}

// --- Fetch data ---
async function fetchImagesForObra(obraId) {
  try {
    const url = `Dashboard/infosObra.php?obraId=${encodeURIComponent(obraId)}`;
    const res = await fetch(url);
    const json = await res.json();
    if (!json) return [];
    return Array.isArray(json.imagens) ? json.imagens : [];
  } catch (e) {
    console.error("Erro ao buscar imagens da obra (infosObra):", e);
    return [];
  }
}

// --- Open modal ---
async function openObraImagesModal(obraId, obraNome, etapaInicial) {
  currentObraId = obraId;
  const modal = document.getElementById("obraImagesModal");
  const title = document.getElementById("obraImagesTitle");
  const expandBtn = document.getElementById("obraImagesExpand");
  const closeBtn = document.getElementById("obraImagesClose");

  if (!modal) return;
  if (title) title.textContent = `Imagens — ${obraNome || obraId}`;

  if (expandBtn) {
    expandBtn.onclick = function () {
      try {
        localStorage.setItem("obraId", String(currentObraId));
      } catch (e) {}
      window.location.href = "Dashboard/obra.php";
    };
  }

  if (closeBtn)
    closeBtn.onclick = () => {
      modal.style.display = "none";
      modalCloseFuncFilterMenu();
    };

  // Clear filters on open
  modalColaboradorFilters = {};
  modalStatusFilters = {};

  // Reset global filter selects
  ["modal_tipo_imagem", "modal_antecipada", "modal_status_etapa", "modal_status_imagem"].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.selectedIndex = 0;
  });

  modal.style.display = "flex";
  await loadObraImages(obraId, etapaInicial || "");
}

// --- Colaborador / status filters per function ---
let modalColaboradorFilters = {};
let modalStatusFilters = {};
const __modalFuncFilterUI = {
  menuEl: null,
  activeFunc: null,
  activeHeader: null,
};

// --- Build table rows ---
async function loadObraImages(obraId, etapaInicial) {
  const tbody = document.querySelector("#modal-tabela-obra tbody");
  if (!tbody) return;

  tbody.innerHTML =
    '<tr><td colspan="11" style="padding:20px;text-align:center;color:#666">Carregando imagens...</td></tr>';

  const imagens = await fetchImagesForObra(obraId);
  modalDadosImagens = imagens;
  tbody.innerHTML = "";

  if (!imagens || imagens.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="11" style="padding:20px;text-align:center;color:#666">Sem imagens para exibir</td></tr>';
    return;
  }

  // Coleta valores únicos para filtros
  const statusEtapaUnicos = new Set();
  const statusUnicos = new Set();
  const tipoImagemUnicos = new Set();

  const colunas = [
    { col: "caderno", label: "Caderno" },
    { col: "filtro", label: "Filtro" },
    { col: "modelagem", label: "Modelagem" },
    { col: "composicao", label: "Composição" },
    { col: "finalizacao", label: "Finalização" },
    { col: "pos_producao", label: "Pós Produção" },
    { col: "alteracao", label: "Alteração" },
  ];

  imagens.forEach(function (item) {
    const row = document.createElement("tr");
    row.classList.add("linha-tabela");
    row.setAttribute("data-id", item.imagem_id);
    row.setAttribute("tipo-imagem", item.tipo_imagem);
    row.setAttribute("status", item.imagem_status);
    const holdMotivo = item.hold_justificativa_recente || item.descricao || "";

    // Etapa (status etapa)
    const cellStatus = document.createElement("td");
    cellStatus.textContent = item.imagem_status;
    cellStatus.setAttribute("data-field", "status_etapa");
    row.appendChild(cellStatus);
    if (!(item.imagem_status === "EF" && item.imagem_sub_status === "EF")) {
      modalApplyStatusImagem(cellStatus, item.imagem_status, holdMotivo);
    }

    // Imagem (nome)
    const cellNome = document.createElement("td");
    cellNome.textContent = modalDisplayImageName(item.imagem_nome);
    cellNome.setAttribute("antecipada", item.antecipada);
    cellNome.setAttribute("data-field", "nome_imagem");
    row.appendChild(cellNome);

    cellNome.addEventListener("mouseenter", (event) => {
      modalTooltip.textContent = modalDisplayImageName(item.imagem_nome);
      modalTooltip.style.display = "block";
      modalTooltip.style.left = event.clientX + "px";
      modalTooltip.style.top = event.clientY - 30 + "px";
    });
    cellNome.addEventListener("mouseleave", () => {
      modalTooltip.style.display = "none";
    });
    cellNome.addEventListener("mousemove", (event) => {
      modalTooltip.style.left = event.clientX + "px";
      modalTooltip.style.top = event.clientY - 30 + "px";
    });

    if (Boolean(parseInt(item.antecipada))) {
      cellNome.style.backgroundColor = "#ff9d00";
    }

    // Status (sub_status)
    const cellSubStatus = document.createElement("td");
    cellSubStatus.textContent = item.imagem_sub_status;
    cellSubStatus.setAttribute("data-field", "status_imagem");
    row.appendChild(cellSubStatus);
    if (!(item.imagem_status === "EF" && item.imagem_sub_status === "EF")) {
      modalApplyStatusImagem(cellSubStatus, item.imagem_sub_status, holdMotivo);
    }

    cellSubStatus.addEventListener("mouseenter", (event) => {
      const isHold =
        String(item.imagem_sub_status || "")
          .trim()
          .toUpperCase() === "HOLD";
      if (isHold) {
        modalTooltip.textContent =
          holdMotivo && String(holdMotivo).trim()
            ? `Motivo: ${holdMotivo}`
            : "Motivo do HOLD não informado.";
      } else {
        modalTooltip.textContent = item.nome_completo || "";
      }
      modalTooltip.style.display = "block";
      modalTooltip.style.left = event.clientX + "px";
      modalTooltip.style.top = event.clientY - 30 + "px";
    });
    cellSubStatus.addEventListener("mouseleave", () => {
      modalTooltip.style.display = "none";
    });
    cellSubStatus.addEventListener("mousemove", (event) => {
      modalTooltip.style.left = event.clientX + "px";
      modalTooltip.style.top = event.clientY - 30 + "px";
    });

    statusEtapaUnicos.add(item.imagem_status);
    statusUnicos.add(item.imagem_sub_status);
    tipoImagemUnicos.add(item.tipo_imagem);

    // Prazo
    const cellPrazo = document.createElement("td");
    let prazoText = "-";
    if (
      item.prazo &&
      typeof item.prazo === "string" &&
      /^\d{4}-\d{2}-\d{2}$/.test(item.prazo)
    ) {
      prazoText = modalFormatarDataDiaMes(item.prazo);
    }
    cellPrazo.textContent = prazoText;
    cellPrazo.setAttribute("data-field", "prazo");
    row.appendChild(cellPrazo);

    // Colunas de funções
    colunas.forEach((coluna) => {
      const colaborador = item[`${coluna.col}_colaborador`] || "-";
      const status = item[`${coluna.col}_status`] || "-";

      const cellColab = document.createElement("td");
      cellColab.textContent = colaborador;
      cellColab.setAttribute("data-status", status);
      cellColab.setAttribute("data-funcao", coluna.col);
      cellColab.classList.add("func-cell", `func-${coluna.col}`);

      cellColab.addEventListener("mouseenter", (event) => {
        modalTooltip.textContent = colaborador + (status ? " — " + status : "");
        modalTooltip.style.display = "block";
        modalTooltip.style.left = event.clientX + "px";
        modalTooltip.style.top = event.clientY - 30 + "px";
      });
      cellColab.addEventListener("mouseleave", () => {
        modalTooltip.style.display = "none";
      });
      cellColab.addEventListener("mousemove", (event) => {
        modalTooltip.style.left = event.clientX + "px";
        modalTooltip.style.top = event.clientY - 30 + "px";
      });

      row.appendChild(cellColab);

      modalApplyStyleNone(cellColab, null, colaborador);

      if (!(item.imagem_status === "EF" && item.imagem_sub_status === "EF")) {
        modalApplyStatusStyle(cellColab, status, colaborador);
      } else {
        cellColab.style.backgroundColor = "";
        cellColab.style.color = "";
      }
    });

    if (item.imagem_status === "EF" && item.imagem_sub_status === "EF") {
      row.classList.add("linha-ef");
    }

    tbody.appendChild(row);
  });

  // Popula selects de filtro
  const tipoSel = document.getElementById("modal_tipo_imagem");
  const statusEtapaSel = document.getElementById("modal_status_etapa");
  const statusSel = document.getElementById("modal_status_imagem");

  if (tipoSel) {
    tipoSel.innerHTML = '<option value="0">Tipo imagem</option>';
    tipoImagemUnicos.forEach((t) => {
      const o = document.createElement("option");
      o.value = t;
      o.textContent = t;
      tipoSel.appendChild(o);
    });
  }
  if (statusEtapaSel) {
    statusEtapaSel.innerHTML = '<option value="">Etapa</option>';
    statusEtapaUnicos.forEach((s) => {
      const o = document.createElement("option");
      o.value = s;
      o.textContent = s;
      statusEtapaSel.appendChild(o);
    });
    // Pré-seleciona a etapa se fornecida (ex: "R00" vindo do banner/calendário)
    if (etapaInicial) {
      const match = Array.from(statusEtapaSel.options).find(o => o.value === etapaInicial);
      if (match) statusEtapaSel.value = etapaInicial;
    }
  }
  if (statusSel) {
    statusSel.innerHTML = '<option value="">Status</option>';
    statusUnicos.forEach((s) => {
      const o = document.createElement("option");
      o.value = s;
      o.textContent = s;
      statusSel.appendChild(o);
    });
  }

  // Inicializa listeners de filtros (apenas uma vez)
  initModalFilters();
  initModalFuncHeaderFilters();
  modalFiltrarTabela();
}

// --- Global filters ---
let __modalFiltersInitialized = false;

function initModalFilters() {
  if (__modalFiltersInitialized) return;
  __modalFiltersInitialized = true;

  const ids = [
    "modal_tipo_imagem",
    "modal_antecipada",
    "modal_status_etapa",
    "modal_status_imagem",
  ];
  ids.forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.addEventListener("change", () => modalFiltrarTabela());
  });

  const clearBtn = document.getElementById("modalClearFilters");
  if (clearBtn) {
    clearBtn.addEventListener("click", () => {
      ids.forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.selectedIndex = 0;
      });
      modalColaboradorFilters = {};
      modalStatusFilters = {};
      modalFiltrarTabela();
      modalUpdateFuncHeaderIndicators();
    });
  }
}

function modalReadGlobalFilters() {
  const getValue = (id) => {
    const el = document.getElementById(id);
    if (!el) return [];
    return el.value ? [el.value] : [];
  };
  return {
    tipoImagemFiltro: getValue("modal_tipo_imagem"),
    antecipadaFiltro: getValue("modal_antecipada"),
    statusEtapaImagemFiltro: getValue("modal_status_etapa"),
    statusImagemFiltro: getValue("modal_status_imagem"),
  };
}

function modalRowMatchesGlobalFilters(row, globals) {
  const tipoImagem = modalNormalizeFilterValue(
    row.getAttribute("tipo-imagem") || "",
  );
  const antecipadaTd = row.querySelector("td[antecipada]");
  const isAntecipada = antecipadaTd
    ? antecipadaTd.getAttribute("antecipada") === "1"
    : false;
  const statusEtapa =
    row.querySelector('td[data-field="status_etapa"]')?.textContent.trim() ||
    "";
  const statusImagem =
    row.querySelector('td[data-field="status_imagem"]')?.textContent.trim() ||
    "";

  if (
    globals.tipoImagemFiltro.length > 0 &&
    !globals.tipoImagemFiltro.includes("0")
  ) {
    if (
      !tipoImagem ||
      !globals.tipoImagemFiltro.some(
        (v) => modalNormalizeFilterValue(v) === tipoImagem,
      )
    )
      return false;
  }
  if (
    globals.antecipadaFiltro.length > 0 &&
    !globals.antecipadaFiltro.includes("")
  ) {
    if (
      !globals.antecipadaFiltro.some(
        (v) => (v === "1" && isAntecipada) || (v !== "1" && !isAntecipada),
      )
    )
      return false;
  }
  if (
    globals.statusImagemFiltro.length > 0 &&
    !globals.statusImagemFiltro.includes("")
  ) {
    if (!globals.statusImagemFiltro.some((v) => v === statusImagem))
      return false;
  }
  if (
    globals.statusEtapaImagemFiltro.length > 0 &&
    !globals.statusEtapaImagemFiltro.includes("")
  ) {
    if (!globals.statusEtapaImagemFiltro.some((v) => v === statusEtapa))
      return false;
  }
  return true;
}

function modalGetFuncCell(row, funcao) {
  return row.querySelector(`td[data-funcao="${funcao}"]`);
}

function modalRowMatchesFuncFilters(row, opts = {}) {
  const ignoreStatusFunc = opts.ignoreStatusFunc || null;
  for (const func of Object.keys(modalColaboradorFilters)) {
    const sel = modalColaboradorFilters[func];
    if (!sel) continue;
    const cell = modalGetFuncCell(row, func);
    const nome = cell ? cell.textContent.trim() : "";
    if (nome !== sel) return false;
  }
  for (const func of Object.keys(modalStatusFilters)) {
    if (ignoreStatusFunc && func === ignoreStatusFunc) continue;
    const selected = Array.isArray(modalStatusFilters[func])
      ? modalStatusFilters[func]
      : [];
    if (!selected.length) continue;
    const cell = modalGetFuncCell(row, func);
    const st = modalNormalizeFilterValue(
      cell ? cell.getAttribute("data-status") : "",
    );
    if (!st || !selected.includes(st)) return false;
  }
  return true;
}

function modalRowMatchesAllFilters(row, globals, opts = {}) {
  return (
    modalRowMatchesGlobalFilters(row, globals) &&
    modalRowMatchesFuncFilters(row, opts)
  );
}

function modalFiltrarTabela() {
  const tabela = document.getElementById("modal-tabela-obra");
  if (!tabela) return;
  const tbody = tabela.querySelector("tbody");
  if (!tbody) return;
  const linhas = tbody.querySelectorAll("tr.linha-tabela");
  const globals = modalReadGlobalFilters();

  let total = 0;
  linhas.forEach((row) => {
    const mostrar = modalRowMatchesAllFilters(row, globals);
    row.style.display = mostrar ? "" : "none";
    if (mostrar) total++;
  });

  const counter = document.getElementById("modal-imagens-totais");
  if (counter) counter.textContent = `Total: ${total}`;

  modalUpdateFuncHeaderIndicators();

  // Se menu de função aberto, atualiza
  if (
    __modalFuncFilterUI.activeFunc &&
    __modalFuncFilterUI.menuEl &&
    __modalFuncFilterUI.menuEl.style.display !== "none"
  ) {
    modalRenderFuncFilterMenu(__modalFuncFilterUI.activeFunc);
    modalPositionFuncFilterMenu();
  }
}

// --- Function header filter menus ---
function modalGetFuncHeaders() {
  return Array.from(
    document.querySelectorAll("#modal-tabela-obra thead th.modal-func-header"),
  );
}

function modalGetFuncHeaderByFunc(funcao) {
  return (
    modalGetFuncHeaders().find((th) => th.dataset.funcao === funcao) || null
  );
}

function modalGetFuncRows() {
  return Array.from(
    document.querySelectorAll("#modal-tabela-obra tbody tr.linha-tabela"),
  );
}

function modalUpdateFuncHeaderIndicators() {
  modalGetFuncHeaders().forEach((th) => {
    const func = th.dataset.funcao;
    const hasColab = !!modalColaboradorFilters[func];
    const hasStatus =
      Array.isArray(modalStatusFilters[func]) &&
      modalStatusFilters[func].length > 0;
    th.classList.toggle("func-filter-active", hasColab || hasStatus);
  });
}

function modalGetCollabOptions(funcao) {
  const set = new Set();
  modalGetFuncRows().forEach((row) => {
    const cell = modalGetFuncCell(row, funcao);
    const nome = cell ? cell.textContent.trim() : "";
    if (
      !nome ||
      nome === "-" ||
      modalNormalizeFilterValue(nome) === "não se aplica"
    )
      return;
    set.add(nome);
  });
  return Array.from(set).sort((a, b) => a.localeCompare(b, "pt-BR"));
}

function modalGetStatusOptions(funcao) {
  const map = new Map();
  modalGetFuncRows().forEach((row) => {
    const cell = modalGetFuncCell(row, funcao);
    const raw = cell
      ? String(cell.getAttribute("data-status") || "").trim()
      : "";
    const key = modalNormalizeFilterValue(raw);
    if (!key || key === "-" || key === "não se aplica") return;
    if (!map.has(key)) map.set(key, raw);
  });
  return Array.from(map.entries())
    .map(([key, label]) => ({ key, label }))
    .sort((a, b) => a.label.localeCompare(b.label, "pt-BR"));
}

function modalGetStatusCounts(funcao) {
  const counts = {};
  const globals = modalReadGlobalFilters();
  modalGetFuncRows().forEach((row) => {
    if (!modalRowMatchesAllFilters(row, globals, { ignoreStatusFunc: funcao }))
      return;
    const cell = modalGetFuncCell(row, funcao);
    const key = modalNormalizeFilterValue(
      cell ? cell.getAttribute("data-status") || "" : "",
    );
    if (!key || key === "-" || key === "não se aplica") return;
    counts[key] = (counts[key] || 0) + 1;
  });
  return counts;
}

function modalEnsureFuncFilterMenu() {
  if (__modalFuncFilterUI.menuEl) return __modalFuncFilterUI.menuEl;
  const menu = document.createElement("div");
  menu.id = "modalFuncFilterMenu";
  menu.className = "modal-func-filter-menu";
  menu.style.display = "none";
  document.body.appendChild(menu);
  __modalFuncFilterUI.menuEl = menu;
  return menu;
}

function modalPositionFuncFilterMenu() {
  const menu = __modalFuncFilterUI.menuEl;
  const header = __modalFuncFilterUI.activeHeader;
  if (!menu || !header || menu.style.display === "none") return;
  const rect = header.getBoundingClientRect();
  const margin = 8;
  const preferredWidth = Math.max(400, Math.min(520, window.innerWidth - 24));
  menu.style.width = preferredWidth + "px";
  const menuRect = menu.getBoundingClientRect();
  let left = rect.left;
  if (left + menuRect.width > window.innerWidth - margin)
    left = window.innerWidth - menuRect.width - margin;
  if (left < margin) left = margin;
  let top = rect.bottom + 6;
  if (top + menuRect.height > window.innerHeight - margin)
    top = Math.max(margin, rect.top - menuRect.height - 6);
  menu.style.left = left + "px";
  menu.style.top = top + "px";
}

function modalCloseFuncFilterMenu() {
  if (!__modalFuncFilterUI.menuEl) return;
  __modalFuncFilterUI.menuEl.style.display = "none";
  if (__modalFuncFilterUI.activeHeader)
    __modalFuncFilterUI.activeHeader.classList.remove("func-filter-open");
  __modalFuncFilterUI.activeHeader = null;
  __modalFuncFilterUI.activeFunc = null;
}

function modalRenderFuncFilterMenu(funcao) {
  const menu = modalEnsureFuncFilterMenu();
  menu.innerHTML = "";
  const header = modalGetFuncHeaderByFunc(funcao);
  const title = header ? header.textContent.trim() : funcao;
  const collaborators = modalGetCollabOptions(funcao);
  const statusOptions = modalGetStatusOptions(funcao);
  const statusCounts = modalGetStatusCounts(funcao);
  const selectedColab = modalColaboradorFilters[funcao] || "";
  const selectedStatus = new Set(
    Array.isArray(modalStatusFilters[funcao]) ? modalStatusFilters[funcao] : [],
  );

  const topEl = document.createElement("div");
  topEl.className = "func-filter-menu-top";
  topEl.textContent = title;
  menu.appendChild(topEl);

  const body = document.createElement("div");
  body.className = "func-filter-menu-body";

  // Left: collaborators
  const left = document.createElement("div");
  left.className = "func-filter-col";
  const leftTitle = document.createElement("div");
  leftTitle.className = "func-filter-col-title";
  leftTitle.textContent = "Colaboradores";
  left.appendChild(leftTitle);

  const allColab = document.createElement("label");
  allColab.className = "func-filter-item";
  allColab.innerHTML = `<input type="radio" name="modal-func-colab-${funcao}" value="" ${!selectedColab ? "checked" : ""}> <span>Todos</span>`;
  left.appendChild(allColab);

  collaborators.forEach((nome) => {
    const item = document.createElement("label");
    item.className = "func-filter-item";
    const input = document.createElement("input");
    input.type = "radio";
    input.name = `modal-func-colab-${funcao}`;
    input.value = nome;
    input.checked = selectedColab === nome;
    const span = document.createElement("span");
    span.textContent = nome;
    item.appendChild(input);
    item.appendChild(span);
    left.appendChild(item);
  });

  // Right: statuses
  const right = document.createElement("div");
  right.className = "func-filter-col";
  const rightTitle = document.createElement("div");
  rightTitle.className = "func-filter-col-title";
  rightTitle.textContent = "Status";
  right.appendChild(rightTitle);

  const allStatus = document.createElement("label");
  allStatus.className = "func-filter-item";
  allStatus.innerHTML = `<input type="checkbox" value="__all__" ${selectedStatus.size === 0 ? "checked" : ""}> <span>Todos</span>`;
  right.appendChild(allStatus);

  statusOptions.forEach((status) => {
    const item = document.createElement("label");
    item.className = "func-filter-item";
    const input = document.createElement("input");
    input.type = "checkbox";
    input.value = status.key;
    input.checked = selectedStatus.has(status.key);
    const text = document.createElement("span");
    text.className = "func-filter-label";
    text.textContent = status.label;
    const count = document.createElement("span");
    count.className = "func-filter-count";
    count.textContent = String(statusCounts[status.key] || 0);
    item.appendChild(input);
    item.appendChild(text);
    item.appendChild(count);
    right.appendChild(item);
  });

  body.appendChild(left);
  body.appendChild(right);
  menu.appendChild(body);

  // Footer: clear
  const footer = document.createElement("div");
  footer.className = "func-filter-menu-footer";
  const clearBtn = document.createElement("button");
  clearBtn.type = "button";
  clearBtn.className = "func-filter-clear";
  clearBtn.textContent = "Limpar";
  clearBtn.addEventListener("click", () => {
    delete modalColaboradorFilters[funcao];
    delete modalStatusFilters[funcao];
    modalFiltrarTabela();
    modalRenderFuncFilterMenu(funcao);
    modalUpdateFuncHeaderIndicators();
    modalPositionFuncFilterMenu();
  });
  footer.appendChild(clearBtn);
  menu.appendChild(footer);

  // Collaborator radio events
  menu
    .querySelectorAll(`input[name="modal-func-colab-${funcao}"]`)
    .forEach((input) => {
      input.addEventListener("change", () => {
        const val = input.value || "";
        if (!val) delete modalColaboradorFilters[funcao];
        else modalColaboradorFilters[funcao] = val;
        modalFiltrarTabela();
        modalRenderFuncFilterMenu(funcao);
        modalUpdateFuncHeaderIndicators();
        modalPositionFuncFilterMenu();
      });
    });

  // Status checkbox events
  right.querySelectorAll('input[type="checkbox"]').forEach((input) => {
    input.addEventListener("change", () => {
      if (input.value === "__all__") {
        delete modalStatusFilters[funcao];
      } else {
        const sel = new Set(
          Array.isArray(modalStatusFilters[funcao])
            ? modalStatusFilters[funcao]
            : [],
        );
        if (input.checked) sel.add(input.value);
        else sel.delete(input.value);
        if (sel.size > 0) modalStatusFilters[funcao] = Array.from(sel);
        else delete modalStatusFilters[funcao];
      }
      modalFiltrarTabela();
      modalRenderFuncFilterMenu(funcao);
      modalUpdateFuncHeaderIndicators();
      modalPositionFuncFilterMenu();
    });
  });
}

function modalOpenFuncFilterMenu(funcao, anchorHeader) {
  const header = anchorHeader || modalGetFuncHeaderByFunc(funcao);
  if (!header) return;
  modalEnsureFuncFilterMenu();
  if (
    __modalFuncFilterUI.activeFunc === funcao &&
    __modalFuncFilterUI.menuEl.style.display !== "none"
  ) {
    modalCloseFuncFilterMenu();
    return;
  }
  if (__modalFuncFilterUI.activeHeader)
    __modalFuncFilterUI.activeHeader.classList.remove("func-filter-open");
  __modalFuncFilterUI.activeFunc = funcao;
  __modalFuncFilterUI.activeHeader = header;
  header.classList.add("func-filter-open");
  modalRenderFuncFilterMenu(funcao);
  __modalFuncFilterUI.menuEl.style.display = "block";
  modalPositionFuncFilterMenu();
}

let __modalFuncFiltersInitialized = false;

function initModalFuncHeaderFilters() {
  if (__modalFuncFiltersInitialized) return;
  __modalFuncFiltersInitialized = true;

  modalGetFuncHeaders().forEach((th) => {
    th.addEventListener("click", (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
      const func = th.dataset.funcao;
      if (!func) return;
      modalOpenFuncFilterMenu(func, th);
    });
  });

  // Close on outside click
  document.addEventListener("click", (ev) => {
    const menu = __modalFuncFilterUI.menuEl;
    if (!menu || menu.style.display === "none") return;
    const inMenu = menu.contains(ev.target);
    const inHeader =
      __modalFuncFilterUI.activeHeader &&
      __modalFuncFilterUI.activeHeader.contains(ev.target);
    if (!inMenu && !inHeader) modalCloseFuncFilterMenu();
  });

  document.addEventListener("keydown", (ev) => {
    if (ev.key === "Escape") modalCloseFuncFilterMenu();
  });

  // Reposition on scroll inside modal wrap
  const wrap = document.getElementById("obraImagesTableWrap");
  if (wrap)
    wrap.addEventListener("scroll", modalPositionFuncFilterMenu, {
      passive: true,
    });
  window.addEventListener("resize", modalPositionFuncFilterMenu);
}

// Expor para uso por outras partes
window.openObraImagesModal = openObraImagesModal;
