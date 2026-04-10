let allRenders = [];
let currentPage = 1;
let totalRenders = 0;
const PAGE_LIMIT = 100;

function renderObraFilter() {
  const obras = [
    ...new Set(allRenders.map((r) => r.obra_nomenclatura).filter(Boolean)),
  ].sort();
  $("#filterObra").html('<option value="">Todas as Obras</option>');
  obras.forEach((nome) => {
    $("#filterObra").append(`<option value="${nome}">${nome}</option>`);
  });
  const selected = $("#filterObra").val();
  // restore previous selection if still available
  if (selected && obras.includes(selected)) {
    $("#filterObra").val(selected);
  } else {
    $("#filterObra").val("");
  }
}

function renderCollaboratorFilter() {
  // Extrai nomes únicos dos colaboradores
  const colaboradores = [
    ...new Set(allRenders.map((r) => r.nome_colaborador).filter(Boolean)),
  ].sort();
  $("#filterColaborador").html(
    '<option value="">Todos os Responsáveis</option>',
  );
  colaboradores.forEach((nome) => {
    $("#filterColaborador").append(`<option value="${nome}">${nome}</option>`);
  });
  const selected = $("#filterColaborador").val();
  if (selected && colaboradores.includes(selected)) {
    $("#filterColaborador").val(selected);
  } else {
    $("#filterColaborador").val("");
  }
}

function renderStatusFilter() {
  const status = [
    ...new Set(allRenders.map((r) => r.status).filter(Boolean)),
  ].sort();
  $("#filterStatus").html('<option value="">Todos os Status</option>');
  status.forEach((nome) => {
    $("#filterStatus").append(`<option value="${nome}">${nome}</option>`);
  });
  const selected = $("#filterStatus").val();
  if (selected && status.includes(selected)) {
    $("#filterStatus").val(selected);
  } else {
    $("#filterStatus").val("");
  }
}

function renderStatusImagemFilter() {
  const statusImagens = [
    ...new Set(allRenders.map((r) => r.nome_status).filter(Boolean)),
  ].sort();
  $("#filterStatusImagem").html(
    '<option value="">Todos os Status Imagem</option>',
  );
  statusImagens.forEach((nome) => {
    $("#filterStatusImagem").append(`<option value="${nome}">${nome}</option>`);
  });
  const selected = $("#filterStatusImagem").val();
  if (selected && statusImagens.includes(selected)) {
    $("#filterStatusImagem").val(selected);
  } else {
    $("#filterStatusImagem").val("");
  }
}

function formatarData(data) {
  const dataObj = data instanceof Date ? data : new Date(data);

  const pad = (num) => num.toString().padStart(2, "0");

  const dia = pad(dataObj.getDate());
  const mes = pad(dataObj.getMonth() + 1);
  const ano = dataObj.getFullYear();

  const hora = pad(dataObj.getHours());
  const min = pad(dataObj.getMinutes());
  const seg = pad(dataObj.getSeconds());

  return `${dia}/${mes}/${ano} ${hora}:${min}:${seg}`;
}

/* --- Badge helper --- */
function getStatusBadgeClass(status) {
  const map = {
    Finalizado: "s-finalizado",
    Aprovado: "s-aprovado",
    "Em andamento": "s-andamento",
    "Em aprovação": "s-aprovacao",
    Reprovado: "s-reprovado",
    Refazendo: "s-refazendo",
    Erro: "s-erro",
    RVW_DONE: "s-rvw-done",
    PRE_ALT: "s-pre-alt",
    READY_FOR_PLANNING: "s-ready-for-planning",
  };
  return map[status] || "s-outro";
}

function getStatusIcon(status) {
  const icons = {
    Finalizado: "fa-circle-check",
    Aprovado: "fa-circle-check",
    "Em andamento": "fa-circle-half-stroke",
    "Em aprovação": "fa-circle-dot",
    Reprovado: "fa-circle-xmark",
    Refazendo: "fa-rotate-right",
    Erro: "fa-circle-exclamation",
    RVW_DONE: "fa-circle-check",
    PRE_ALT: "fa-magnifying-glass",
    READY_FOR_PLANNING: "fa-circle-check",
  };
  return icons[status] || "fa-circle";
}

function formatDateShort(dateStr) {
  if (!dateStr) return "—";
  const d = new Date(dateStr);
  if (isNaN(d)) return "—";
  return d.toLocaleDateString("pt-BR", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

function renderCards(renders) {
  const grid = document.getElementById("renderGrid");

  if (!renders.length) {
    grid.innerHTML = `
      <div class="empty-state">
        <i class="fa-solid fa-layer-group"></i>
        <p>Nenhum render encontrado</p>
        <span>Tente ajustar os filtros aplicados</span>
      </div>`;
    updateResultsBadge(0, true, totalRenders);
    return;
  }

  let html = "";
  renders.forEach(function (render) {
    const imgUrl = render.previa_jpg
      ? `https://improov.com.br/flow/ImproovWeb/thumb.php?path=${encodeURI("uploads/renders/" + render.previa_jpg)}&w=360&q=75`
      : "../assets/logo.jpg";

    const sc = getStatusBadgeClass(render.status);
    const ico = getStatusIcon(render.status);
    const dateLabel = formatDateShort(render.data);
    const obra = render.obra_nomenclatura || "—";
    const colab = render.nome_colaborador || "—";

    html += `
      <div class="render-card" data-id="${render.idrender_alta}" data-status="${render.status}">
        <div class="card-thumb-wrap">
          <img loading="lazy" decoding="async" src="${imgUrl}" alt="" class="loading"
               onload="this.classList.remove('loading')">
        </div>
        <div class="card-body">
          <p class="card-title" title="${render.imagem_nome}">${render.imagem_nome}</p>
          <div class="card-meta-row">
            <div class="card-meta-item">
              <i class="fa-solid fa-building"></i>
              <span title="${obra}">${obra} - ${render.nome_status || "—"}</span>
            </div>
            <div class="card-meta-item">
              <i class="fa-solid fa-user"></i>
              <span title="${colab}">${colab}</span>
            </div>
          </div>
          <div class="card-footer">
            <span class="status-badge ${sc}">
              <i class="fa-solid ${ico}"></i>
              ${render.status}
            </span>
            <span class="card-date">
              <i class="fa-regular fa-calendar"></i>
              ${dateLabel}
            </span>
          </div>
        </div>
      </div>`;
  });

  grid.innerHTML = html;
  updateResultsBadge(renders.length, isFilterActive(), totalRenders);
}

// Use event delegation to avoid re-attaching handlers on every re-render
$("#renderGrid")
  .off("click.renderClick")
  .on("click.renderClick", ".render-card", function () {
    const idrender_alta = $(this).data("id");
    editRender(idrender_alta);
  });
function loadRenders(page) {
  page = page || 1;
  const btn = document.getElementById("btnLoadMore");
  if (btn) btn.classList.add("loading");

  $.ajax({
    url: "ajax.php",
    method: "GET",
    data: { action: "getRenders", page: page, limit: PAGE_LIMIT },
    dataType: "json",
    success: function (response) {
      if (response.status === "sucesso") {
        if (page === 1) {
          allRenders = response.renders;
        } else {
          allRenders = allRenders.concat(response.renders);
        }
        currentPage = page;

        const total = response.total || 0;
        totalRenders = total;
        const loaded = allRenders.length;
        const hasMore = loaded < total;

        renderObraFilter();
        renderCollaboratorFilter();
        renderStatusFilter();
        renderStatusImagemFilter();
        filterRenders();

        // Show / hide "Carregar mais"
        const wrap = document.getElementById("loadMoreWrap");
        if (wrap) wrap.style.display = hasMore ? "flex" : "none";
        const counter = document.getElementById("loadMoreCounter");
        if (counter)
          counter.textContent = hasMore ? `(${loaded} / ${total})` : "";
      }
    },
    complete: function () {
      if (btn) btn.classList.remove("loading");
    },
  });
}

/* --- Results badge --- */
function updateResultsBadge(count, hasFilter, total) {
  const badge = document.getElementById("resultsBadge");
  const countEl = document.getElementById("resultsCount");
  const totalEl = document.getElementById("resultsTotal");
  const dot = document.getElementById("filterDot");
  if (!badge) return;
  countEl.textContent = count;
  if (total !== undefined && total > 0) {
    totalEl.textContent = " / " + total;
  } else {
    totalEl.textContent = "";
  }
  if (hasFilter) {
    badge.classList.add("has-filter");
    dot.classList.add("visible");
  } else {
    badge.classList.remove("has-filter");
    dot.classList.remove("visible");
  }
}

function isFilterActive() {
  return !!(
    $("#filterStatus").val() ||
    $("#filterStatusImagem").val() ||
    $("#filterColaborador").val() ||
    $("#filterObra").val() ||
    $("#filterSearch").val() ||
    $("#filterDateFrom").val() ||
    $("#filterDateTo").val()
  );
}

function filterRenders() {
  const status = $("#filterStatus").val();
  const statusImagem = $("#filterStatusImagem").val();
  const colaborador = $("#filterColaborador").val();
  const obra = $("#filterObra").val();
  const search = $("#filterSearch").val().trim().toLowerCase();
  const dateFrom = $("#filterDateFrom").val();
  const dateTo = $("#filterDateTo").val();

  const hasFilter =
    colaborador ||
    status ||
    statusImagem ||
    obra ||
    search ||
    dateFrom ||
    dateTo;
  document.getElementById("btnLimpar").style.display = hasFilter
    ? "inline-flex"
    : "none";

  const filtered = allRenders.filter((r) => {
    if (status && r.status !== status) return false;
    if (statusImagem && r.nome_status !== statusImagem) return false;
    if (colaborador && r.nome_colaborador !== colaborador) return false;
    if (obra && r.obra_nomenclatura !== obra) return false;

    if (search) {
      const haystack = [
        r.imagem_nome,
        r.obra_nomenclatura,
        r.nome_colaborador,
        r.status,
      ]
        .filter(Boolean)
        .join(" ")
        .toLowerCase();
      if (!haystack.includes(search)) return false;
    }

    if (dateFrom || dateTo) {
      const rawDate = r.submitted || r.data;
      const d = rawDate ? new Date(rawDate) : null;
      if (!d || isNaN(d)) return false;
      if (dateFrom) {
        const [fy, fm, fd] = dateFrom.split("-").map(Number);
        const from = new Date(fy, fm - 1, fd, 0, 0, 0, 0);
        if (d < from) return false;
      }
      if (dateTo) {
        const [ty, tm, td] = dateTo.split("-").map(Number);
        const end = new Date(ty, tm - 1, td, 23, 59, 59, 999);
        if (d > end) return false;
      }
    }

    return true;
  });

  renderCards(filtered);
}

// Filter events — real-time on search, applied on select/date change
$("#filterStatus, #filterStatusImagem, #filterColaborador, #filterObra").on(
  "change",
  filterRenders,
);
$("#filterDateFrom, #filterDateTo").on("change", filterRenders);
$("#filterSearch").on("input", filterRenders);

$("#btnAplicar").on("click", filterRenders);

$("#btnLimpar").on("click", function () {
  $("#filterStatus, #filterStatusImagem, #filterColaborador, #filterObra").val(
    "",
  );
  $("#filterSearch").val("");
  $("#filterDateFrom, #filterDateTo").val("");
  filterRenders();
});

// Função para abrir o modal e carregar os dados para edição
function editRender(idrender_alta) {
  $.ajax({
    url: "ajax.php",
    method: "GET",
    data: { action: "getRender", idrender_alta: idrender_alta },
    dataType: "json",
    success: function (response) {
      if (response.status == "sucesso") {
        const r = response.render;

        // — Header fields —
        $("#modal_imagem_id").text(r.imagem_nome || "—");
        $("#modal_obra_nome").text(r.obra_nomenclatura || "");

        // Status badge in header subtitle
        const sc = getStatusBadgeClass(r.status);
        const ico = getStatusIcon(r.status);
        $("#modal_status_badge").html(
          `<span class="status-badge ${sc}"><i class="fa-solid ${ico}"></i> ${r.status}</span>`,
        );

        // — Detail fields —
        $("#modal_idrender").text(r.idrender_alta || "—");
        $("#modal_numero_bg").text(r.numero_bg || "—");

        $("#modal_status").text(r.status || "—");
        $("#modal_status_id").text(r.nome_status || "—");
        $("#modal_responsavel_id").text(r.nome_colaborador || "—");
        $("#modal_computer").text(r.computer || "—");

        $("#modal_submitted").text(
          r.submitted ? formatarData(r.submitted) : "—",
        );
        $("#modal_last_updated").text(
          r.last_updated ? formatarData(r.last_updated) : "—",
        );

        $("#modal_job_folder").text(r.job_folder || "—");
        $("#modal_previa_jpg").text(r.previa_jpg || "—");

        // — Error section —
        const errors = r.errors || "";
        if (errors) {
          $("#errorsContainer").show();
          $("#modal_errors").text(errors).slideUp(0);
          $("#toggleErrors").removeClass("open");
        } else {
          $("#errorsContainer").hide();
        }

        $("#toggleErrors")
          .off("click")
          .on("click", function (event) {
            event.preventDefault();
            const $body = $("#modal_errors");
            const $btn = $(this);
            if ($btn.hasClass("open")) {
              $btn.removeClass("open");
              $body.slideUp(200);
            } else {
              $btn.addClass("open");
              $body.slideDown(200);
            }
          });

        // — Image preview + gallery —
        const previews = response.previews || [];
        const $gallery = $("#modalGallery");
        $gallery.empty();

        if (previews.length > 0) {
          const firstUrl = `https://improov.com.br/flow/ImproovWeb/uploads/renders/${encodeURIComponent(previews[0].filename)}`;
          $("#modalPreviewImg").attr("src", firstUrl);

          previews.forEach(function (p, idx) {
            const thumbUrl = `https://improov.com.br/flow/ImproovWeb/uploads/renders/${encodeURIComponent(p.filename)}`;
            const $thumb = $(
              `<img class="modal-thumb${idx === 0 ? " active" : ""}" loading="lazy" decoding="async" src="${thumbUrl}" alt="Preview ${idx + 1}">`,
            );
            $gallery.append($thumb);
          });

          // Thumbnail click
          $gallery
            .off("click.gallery")
            .on("click.gallery", ".modal-thumb", function () {
              $("#modalPreviewImg").attr("src", $(this).attr("src"));
              $gallery.find(".modal-thumb").removeClass("active");
              $(this).addClass("active");
            });
        } else {
          const imgUrl = r.previa_jpg
            ? `https://improov.com.br/flow/ImproovWeb/uploads/renders/${encodeURIComponent(r.previa_jpg)}`
            : "../assets/logo.jpg";
          $("#modalPreviewImg").attr("src", imgUrl);
        }

        // — Open modal —
        $("#myModal").addClass("is-open");

        // — Action button visibility —
        if (r.status === "Reprovado" || r.status === "Refazendo") {
          $("#aprovarRender").hide();
          $("#reprovarRender").hide();
        } else if (r.status === "Aprovado") {
          $("#aprovarRender").hide();
          $("#reprovarRender")
            .show()
            .data("target-status", "Refazendo")
            .html('<i class="fa-solid fa-rotate-right"></i> Refazer');
        } else {
          $("#aprovarRender").show();
          $("#reprovarRender")
            .show()
            .data("target-status", "Reprovado")
            .html('<i class="fa-solid fa-rotate-right"></i> Reprovar');
        }
      }
    },
  });
}

$("#modalPreviewImg")
  .off("click")
  .on("click", function () {
    const src = $(this).attr("src");

    // Criar modal fullscreen
    const fullScreenDiv = $(`
        <div id="fullscreenImgDiv">
            <div id="image_wrapper">
                <img id="fullscreenImg" src="${src}">
            </div>
        </div>
    `);

    $("body").append(fullScreenDiv);

    // scope the elements inside the newly created fullScreenDiv to avoid global selectors
    const $imageWrapper = fullScreenDiv.find("#image_wrapper");
    const $img = fullScreenDiv.find("#fullscreenImg");

    // Zoom & Pan variables
    let currentZoom = 1;
    const zoomStep = 0.1;
    const maxZoom = 5;
    const minZoom = 0.1;

    let isDragging = false;
    let startX, startY;
    let currentTranslateX = 0;
    let currentTranslateY = 0;
    let dragMoved = false;

    // Função para aplicar transformações
    function applyTransforms() {
      $imageWrapper.css(
        "transform",
        `scale(${currentZoom}) translate(${currentTranslateX}px, ${currentTranslateY}px)`,
      );
    }

    // Zoom com Ctrl + scroll
    fullScreenDiv.on("wheel", function (event) {
      if (event.ctrlKey) {
        event.preventDefault();
        if (event.originalEvent.deltaY < 0) {
          currentZoom = Math.min(currentZoom + zoomStep, maxZoom);
        } else {
          currentZoom = Math.max(currentZoom - zoomStep, minZoom);
        }

        if (currentZoom === minZoom) {
          currentTranslateX = 0;
          currentTranslateY = 0;
        }

        applyTransforms();
      }
    });

    // Iniciar drag
    $imageWrapper.on("mousedown.fullscreen", function (e) {
      if (e.button === 0 && !e.ctrlKey) {
        isDragging = true;
        dragMoved = false;
        startX = e.clientX - currentTranslateX;
        startY = e.clientY - currentTranslateY;
        $imageWrapper.css("cursor", "grabbing").css("transition", "none");
      }
    });

    // Use namespaced document handlers so we can remove them when closing
    const mouseMoveHandler = function (e) {
      if (!isDragging) return;
      e.preventDefault();
      const dx = e.clientX - startX;
      const dy = e.clientY - startY;

      if (Math.abs(dx) > 3 || Math.abs(dy) > 3) dragMoved = true;

      currentTranslateX = dx;
      currentTranslateY = dy;
      applyTransforms();
    };

    const mouseUpHandler = function () {
      if (isDragging) {
        isDragging = false;
        $imageWrapper
          .css("cursor", "grab")
          .css("transition", "transform 0.1s ease-out");
      }
    };

    $(document).on("mousemove.fullscreen", mouseMoveHandler);
    $(document).on("mouseup.fullscreen", mouseUpHandler);

    // Fechar modal clicando no fundo - limpar handlers namespaced
    fullScreenDiv.on("click", function (e) {
      if (e.target.id === "fullscreenImgDiv") {
        $(document).off(".fullscreen");
        $(this).remove();
      }
    });

    // Ensure handlers are cleaned up if the element is removed programmatically
    fullScreenDiv.on("remove", function () {
      $(document).off(".fullscreen");
    });

    applyTransforms();
  });

// Fechar o modal (novo botão + clique no overlay)
$("#closeModal").on("click", function () {
  $("#myModal").removeClass("is-open");
});

$("#myModal").on("click", function (e) {
  if (e.target === this) {
    $(this).removeClass("is-open");
  }
});

$("#aprovarRender").click(function () {
  const idrender_alta = $("#modal_idrender").text();

  $.post(
    "ajax.php",
    {
      action: "updateRender",
      idrender_alta: idrender_alta,
      status: "Aprovado",
    },
    function (response) {
      console.log("Resposta updateRender:", response);
      if (response.status === "sucesso") {
        // Atualiza os renders
        loadRenders();
        Toastify({
          text: "Render aprovado com sucesso!",
          duration: 3000,
          gravity: "top",
          position: "right",
          backgroundColor: "#4caf50", // verde
        }).showToast();

        // Abre o modal POS
        $("#modalPOS").addClass("is-open");
        $("#pos_render_id").val(idrender_alta);

        // NÃO fechamos o modal principal
      } else {
        Toastify({
          text: "Erro ao atualizar Render!",
          duration: 3000,
          gravity: "top",
          position: "right",
          backgroundColor: "#f44336", // vermelho
        }).showToast();
      }
    },
    "json",
  ).fail(function (xhr, status, error) {
    console.error("AJAX error:", error, xhr.responseText);
    Toastify({
      text: "Erro de comunicação com o servidor!",
      duration: 3000,
      gravity: "top",
      position: "right",
      backgroundColor: "#f44336",
    }).showToast();
  });
});

// Fechar modal POS
$("#fecharPOS").click(function () {
  $("#modalPOS").removeClass("is-open");
  $("#pos_caminho").val("");
  $("#pos_referencias").val("");
});

// Enviar dados do POS
$("#enviarPOS").click(function () {
  const render_id = $("#pos_render_id").val();
  const refs = $("#pos_caminho").val();
  const obs = $("#pos_referencias").val();

  if (!render_id) {
    Toastify({
      text: "ID do render não definido!",
      duration: 3000,
      gravity: "top",
      position: "right",
      backgroundColor: "#f44336", // vermelho
    }).showToast();
    return;
  }

  $.post(
    "ajax.php",
    {
      action: "updatePOS",
      render_id: render_id,
      refs: refs,
      obs: obs,
    },
    function (response) {
      if (response.status === "sucesso") {
        Toastify({
          text: "Pós-produção atualizada com sucesso!",
          duration: 3000,
          gravity: "top",
          position: "right",
          backgroundColor: "#4caf50", // verde
        }).showToast();

        $("#modalPOS").removeClass("is-open");
        $("#pos_caminho").val("");
        $("#pos_referencias").val("");
        $("#myModal").removeClass("is-open");
      } else {
        Toastify({
          text: "Erro ao atualizar pós-produção!",
          duration: 3000,
          gravity: "top",
          position: "right",
          backgroundColor: "#f44336", // vermelho
        }).showToast();
      }
    },
    "json",
  ).fail(function (xhr, status, error) {
    console.error("AJAX error:", error, xhr.responseText);
    Toastify({
      text: "Erro de comunicação com o servidor!",
      duration: 3000,
      gravity: "top",
      position: "right",
      backgroundColor: "#f44336",
    }).showToast();
  });
});

$("#reprovarRender").click(function () {
  const idrender_alta = $("#modal_idrender").text();
  const targetStatus = $(this).data("target-status") || "Reprovado";
  $.post(
    "ajax.php",
    {
      action: "updateRender",
      idrender_alta: idrender_alta,
      status: targetStatus,
    },
    function (response) {
      if (response.status === "sucesso") {
        loadRenders();
        $("#myModal").removeClass("is-open");
        Toastify({
          text: "Render reprovado com sucesso!",
          duration: 3000,
          gravity: "top",
          position: "right",
          backgroundColor: "#4ca8afff", // verde
        }).showToast();
      } else {
        Toastify({
          text: "Erro ao reprovar Render!",
          duration: 3000,
          gravity: "top",
          position: "right",
          backgroundColor: "#f44336", // vermelho
        }).showToast();
      }
    },
    "json",
  ).fail(function (xhr, status, error) {
    console.error("AJAX error:", error, xhr.responseText);
    Toastify({
      text: "Erro de comunicação com o servidor!",
      duration: 3000,
      gravity: "top",
      position: "right",
      backgroundColor: "#f44336",
    }).showToast();
  });
});

// Excluir o render
$("#deleteRender")
  .off("click")
  .on("click", function (e) {
    e.preventDefault(); // Evita submit do formulário se for button type="submit"
    const idrender_alta = $("#modal_idrender").text();
    $.ajax({
      url: "ajax.php",
      method: "POST",
      data: {
        action: "deleteRender",
        idrender_alta: idrender_alta,
      },
      dataType: "json",
      success: function (response) {
        if (response.status == "sucesso") {
          loadRenders();
          $("#myModal").removeClass("is-open");

          Toastify({
            text: "Render excluído com sucesso!",
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: "#f15e1aff", // verde
          }).showToast();
        }
      },
    });
  });

// Carregar os renders quando a página for carregada
$(document).ready(function () {
  loadRenders(1);

  $("#btnLoadMore").on("click", function () {
    loadRenders(currentPage + 1);
  });

  // ── Context menu: trocar responsável ──────────────────────────────
  let ctxRenderId = null;
  const $ctxMenu = $("#ctxMenu");

  // Pré-carregar colaboradores uma vez
  $.ajax({
    url: "ajax.php",
    method: "GET",
    data: { action: "getColaboradores" },
    dataType: "json",
    success: function (res) {
      if (res.status === "sucesso") {
        const $sel = $("#ctxResponsavelSelect");
        $sel.empty();
        res.colaboradores.forEach(function (c) {
          $sel.append(
            `<option value="${c.idcolaborador}">${c.nome_colaborador}</option>`,
          );
        });
      }
    },
  });

  // Abrir menu no botão direito do card
  $("#renderGrid").on("contextmenu", ".render-card", function (e) {
    e.preventDefault();
    ctxRenderId = $(this).data("id");

    // Posicionar o menu perto do cursor, ajustando para não sair da tela
    const menuW = 230,
      menuH = 130;
    let x = e.clientX,
      y = e.clientY;
    if (x + menuW > window.innerWidth) x = window.innerWidth - menuW - 8;
    if (y + menuH > window.innerHeight) y = window.innerHeight - menuH - 8;

    $ctxMenu.css({ top: y, left: x }).addClass("is-open");
  });

  // Salvar novo responsável
  $("#ctxSalvar").on("click", function () {
    if (!ctxRenderId) return;
    const responsavel_id = $("#ctxResponsavelSelect").val();

    $.post(
      "ajax.php",
      {
        action: "updateResponsavel",
        idrender_alta: ctxRenderId,
        responsavel_id: responsavel_id,
      },
      function (res) {
        if (res.status === "sucesso") {
          $ctxMenu.removeClass("is-open");
          loadRenders(1);
          Toastify({
            text: "Responsável atualizado com sucesso!",
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: "#4caf50",
          }).showToast();
        } else {
          Toastify({
            text: "Erro ao atualizar responsável!",
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: "#f44336",
          }).showToast();
        }
      },
      "json",
    );
  });

  // Fechar ao clicar fora
  $(document).on("click", function (e) {
    if (!$(e.target).closest("#ctxMenu").length) {
      $ctxMenu.removeClass("is-open");
    }
  });

  // Fechar ao pressionar Escape
  $(document).on("keydown.ctx", function (e) {
    if (e.key === "Escape") $ctxMenu.removeClass("is-open");
  });
});

// Mobile filter toggle (Render)
(function () {
  try {
    const $filters = $("#filters");
    const $toggle = $("#filter-toggle-btn");
    if ($filters.length && $toggle.length) {
      $toggle.on("click", function (e) {
        e.preventDefault();
        const isOpen = $filters.hasClass("open");
        if (isOpen) {
          $filters.removeClass("open");
          $(this).attr("aria-expanded", "false");
        } else {
          $filters.addClass("open");
          $(this).attr("aria-expanded", "true");
          // ensure the sheet is visible on some devices
          setTimeout(() => {
            try {
              $filters[0].scrollIntoView({ behavior: "smooth", block: "end" });
            } catch (e) {}
          }, 50);
        }
      });

      // close when tapping outside filters or the FAB
      $(document).on("click touchstart", function (ev) {
        if (!$(ev.target).closest("#filters, #filter-toggle-btn").length) {
          if ($filters.hasClass("open")) {
            $filters.removeClass("open");
            $toggle.attr("aria-expanded", "false");
          }
        }
      });
    }
  } catch (err) {
    console.error("filter toggle init error:", err);
  }
})();
