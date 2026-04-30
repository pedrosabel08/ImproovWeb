var modal = document.getElementById("modal");
var modalRender = document.getElementById("renderModal");
var openModalBtn = document.getElementById("openModalBtn");
var openModalBtnRender = document.getElementById("openModalBtnRender");
var closeModal = document.getElementById("closeModalBtn");
var closeModalRender = document.getElementsByClassName("closeModalRender")[0];
const formPosProducao = document.getElementById("formPosProducao");

function limparCampos() {
  document.getElementById("opcao_finalizador").selectedIndex = 0;
  document.getElementById("opcao_obra").selectedIndex = 0;
  document.getElementById("imagem_id_pos").value = "";
  document.getElementById("id-pos").value = "";
  document.getElementById("caminhoPasta").value = "";
  document.getElementById("numeroBG").value = "";
  document.getElementById("referenciasCaminho").value = "";
  document.getElementById("observacao").value = "";
  document.getElementById("alterar_imagem").value = "false";
  document.getElementById("render_id_pos").value = "";
  document.getElementById("modal-title-imagem").textContent = "Pós-Produção";
  document.getElementById("modal-subtitle-obra").textContent = "";
  _limparTodasSaveStatus();
  _atualizarBotaoFinalizar(1); // reset para estado padrão
}

openModalBtn.onclick = function () {
  modal.style.display = "flex";
  limparCampos();
};
openModalBtnRender.onclick = function () {
  modalRender.style.display = "flex";
};

closeModal.onclick = function () {
  modal.style.display = "none";
  limparCampos();
};
closeModalRender.onclick = function () {
  modalRender.style.display = "none";
};

// ==========================================
// AUTO-SAVE — Infraestrutura
// ==========================================

const AUTOSAVE_DELAY = 900; // ms

function _debounce(fn, delay) {
  let timer;
  return function () {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, arguments), delay);
  };
}

function _setSaveStatus(el, state) {
  if (!el) return;
  el.className = "save-status";
  if (state === "typing") {
    el.innerHTML = '<i class="fa-solid fa-keyboard"></i> Digitando...';
    el.classList.add("ss-typing");
  } else if (state === "saving") {
    el.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';
    el.classList.add("ss-saving");
  } else if (state === "saved") {
    el.innerHTML = '<i class="fa-solid fa-check"></i> Salvo';
    el.classList.add("ss-saved");
    // A animação CSS já faz o fade-out; limpamos após 3s
    setTimeout(() => {
      if (el.classList.contains("ss-saved")) {
        el.innerHTML = "";
        el.className = "save-status";
      }
    }, 3000);
  } else if (state === "error") {
    el.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Erro';
    el.classList.add("ss-error");
  } else {
    el.innerHTML = "";
  }
}

function _limparTodasSaveStatus() {
  document.querySelectorAll(".save-status").forEach(function (el) {
    el.innerHTML = "";
    el.className = "save-status";
  });
}

function _autoSave(statusEl) {
  var idPos = document.getElementById("id-pos").value;
  if (!idPos || document.getElementById("alterar_imagem").value !== "true")
    return;

  _setSaveStatus(statusEl, "saving");

  var payload = {
    id_pos: idPos,
    caminho_pasta: document.getElementById("caminhoPasta").value,
    numero_bg: document.getElementById("numeroBG").value,
    refs: document.getElementById("referenciasCaminho").value,
    obs: document.getElementById("observacao").value,
    status_id: document.getElementById("opcao_status").value,
    colaborador_id: document.getElementById("opcao_finalizador").value,
    responsavel_id: document.getElementById("responsavel_id").value,
  };

  fetch("autosave_pos.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  })
    .then(function (r) {
      return r.json();
    })
    .then(function (res) {
      _setSaveStatus(statusEl, res.success ? "saved" : "error");
    })
    .catch(function () {
      _setSaveStatus(statusEl, "error");
    });
}

// Registra listeners de auto-save nos campos do modal
function _initAutoSaveListeners() {
  // Text inputs com debounce
  var textFields = [
    { id: "caminhoPasta", ssId: "ss-caminho" },
    { id: "numeroBG", ssId: "ss-bg" },
    { id: "referenciasCaminho", ssId: "ss-refs" },
    { id: "observacao", ssId: "ss-obs" },
  ];
  textFields.forEach(function (f) {
    var el = document.getElementById(f.id);
    var ss = document.getElementById(f.ssId);
    var debouncedSave = _debounce(function () {
      _autoSave(ss);
    }, AUTOSAVE_DELAY);
    el.addEventListener("input", function () {
      _setSaveStatus(ss, "typing");
      debouncedSave();
    });
  });

  // Selects: salva imediatamente no change
  var selectFields = [
    { id: "opcao_finalizador", ssId: "ss-finalizador" },
    { id: "opcao_obra", ssId: "ss-obra" },
    { id: "opcao_status", ssId: "ss-status" },
    { id: "responsavel_id", ssId: "ss-responsavel" },
  ];
  selectFields.forEach(function (f) {
    var el = document.getElementById(f.id);
    var ss = document.getElementById(f.ssId);
    el.addEventListener("change", function () {
      _autoSave(ss);
    });
  });
}

_initAutoSaveListeners();

// Botão de copiar caminho pasta
document
  .getElementById("btnCopyCaminho")
  .addEventListener("click", function () {
    var valor = document.getElementById("caminhoPasta").value;
    if (!valor) return;
    navigator.clipboard
      .writeText(valor)
      .then(function () {
        var btn = document.getElementById("btnCopyCaminho");
        btn.classList.add("copied");
        btn.innerHTML = '<i class="fa-solid fa-check"></i>';
        setTimeout(function () {
          btn.classList.remove("copied");
          btn.innerHTML = '<i class="fa-solid fa-copy"></i>';
        }, 1800);
      })
      .catch(function () {
        Toastify({
          text: "Não foi possível copiar o caminho.",
          duration: 2500,
          gravity: "top",
          position: "right",
          style: {
            background: "#ef4444",
            borderRadius: "var(--radius-sm)",
            fontFamily: '"Inter", sans-serif',
            fontSize: "13px",
          },
        }).showToast();
      });
  });

// ==========================================
// BOTÃO FINALIZAR / VOLTAR PÓS
// ==========================================

function _atualizarBotaoFinalizar(statusPos) {
  var btn = document.getElementById("btnFinalizarPos");
  btn.dataset.statusPos = String(statusPos);
  if (parseInt(statusPos) === 0) {
    btn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Voltar pós';
    btn.className = "btn-modal-voltar";
  } else {
    btn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Finalizar pós';
    btn.className = "btn-modal-finalizar";
  }
}

document
  .getElementById("btnFinalizarPos")
  .addEventListener("click", async function () {
    var idPos = document.getElementById("id-pos").value;
    if (!idPos) return;

    var currentStatus = parseInt(this.dataset.statusPos);
    var isFinalizing = currentStatus === 1;

    var result = await Swal.fire({
      title: isFinalizing ? "Finalizar pós-produção?" : "Reabrir pós-produção?",
      text: isFinalizing
        ? "Marcar esta imagem como pós finalizada."
        : "Marcar esta imagem como pendente novamente.",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: isFinalizing ? "Finalizar" : "Reabrir",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#4f80e1",
    });

    if (!result.isConfirmed) return;

    var formData = new FormData();
    formData.append("id_pos", idPos);

    fetch("toggle_status_pos.php", { method: "POST", body: formData })
      .then(function (r) {
        return r.json();
      })
      .then(function (res) {
        if (!res.success) {
          Swal.fire({
            icon: "error",
            title: "Erro",
            text: "Não foi possível alterar o status.",
            timer: 3000,
            timerProgressBar: true,
          });
          return;
        }
        _atualizarBotaoFinalizar(res.new_status);
        atualizarTabela();
        var msg =
          res.new_status === 0
            ? "Pós finalizada com sucesso!"
            : "Pós reaberta com sucesso!";
        Toastify({
          text: msg,
          duration: 3000,
          gravity: "top",
          position: "right",
          style: {
            background: res.new_status === 0 ? "#10b981" : "#f59e0b",
            borderRadius: "var(--radius-sm)",
            fontFamily: '"Inter", sans-serif',
            fontSize: "13px",
            fontWeight: "500",
          },
        }).showToast();
      })
      .catch(function () {
        Swal.fire({
          icon: "error",
          title: "Erro",
          text: "Falha na requisição.",
          timer: 3000,
          timerProgressBar: true,
        });
      });
  });

// Fechar modais ao pressionar ESC
document.addEventListener("keydown", function (event) {
  if (event.key === "Escape") {
    // ou event.keyCode === 27
    if (modal.style.display === "flex") {
      modal.style.display = "none";
      limparCampos();
    }
    if (modalRender.style.display === "flex") {
      modalRender.style.display = "none";
      limparCampos();
    }
  }
});

window.onclick = function (event) {
  if (event.target == modalRender) {
    modalRender.style.display = "none";
  }
};

var tabelaGlobal = null; // Acessível globalmente para o filter bar e cards

document.getElementById("opcao_obra").addEventListener("change", function () {
  var obraId = this.value;
  buscarImagens(obraId);
});

formPosProducao.addEventListener("submit", function (e) {
  e.preventDefault();

  var formData = new FormData(this);

  fetch("inserir_pos_producao.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.text())
    .then((data) => {
      document.getElementById("modal").style.display = "none";
      limparCampos();
      atualizarTabela();
      // buscarImagens();
      Toastify({
        text: "Dados inseridos com sucesso!",
        duration: 3000,
        close: true,
        gravity: "top",
        position: "left",
        backgroundColor: "green",
        stopOnFocus: true,
      }).showToast();
    })
    .catch((error) => console.error("Erro:", error));
});

document
  .getElementById("deleteButton")
  .addEventListener("click", async function () {
    const idPos = document.getElementById("id-pos").value;

    if (!idPos) {
      Toastify({
        text: "Nenhum item selecionado para deletar.",
        duration: 3000,
        gravity: "top",
        position: "right",
        style: {
          background: "#ef4444",
          borderRadius: "var(--radius-sm)",
          fontFamily: '"Inter", sans-serif',
          fontSize: "13px",
        },
      }).showToast();
      return;
    }

    const result = await Swal.fire({
      title: "Excluir registro?",
      text: "Esta ação não pode ser desfeita.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Excluir",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#dc2626",
    });

    if (!result.isConfirmed) return;

    fetch("delete.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id_pos: idPos }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          Toastify({
            text: "Item deletado com sucesso.",
            duration: 3000,
            gravity: "top",
            position: "right",
            style: {
              background: "#f59e0b",
              borderRadius: "var(--radius-sm)",
              fontFamily: '"Inter", sans-serif',
              fontSize: "13px",
            },
          }).showToast();
          modal.style.display = "none";
          atualizarTabela();
        } else {
          Swal.fire({
            icon: "error",
            title: "Erro",
            text: "Erro ao deletar: " + data.message,
            timer: 3000,
            timerProgressBar: true,
          });
        }
      })
      .catch((error) => {
        console.error("Erro ao deletar:", error);
        Swal.fire({
          icon: "error",
          title: "Erro",
          text: "Ocorreu um erro ao tentar deletar o item.",
          timer: 3000,
          timerProgressBar: true,
        });
      });
  });

function atualizarTabela() {
  fetch("atualizar_tabela.php")
    .then((response) => {
      if (!response.ok) {
        throw new Error(`Erro HTTP: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      if (!Array.isArray(data) || data.length === 0) {
        console.warn(
          "Dados vazios ou inválidos recebidos. A tabela pode não renderizar.",
        );
        document.getElementById("tabela-imagens").innerHTML =
          "<p>Nenhum dado encontrado para exibir.</p>";
        document.getElementById("total-pos").innerText = 0;
        return;
      }

      function listaValores(col) {
        let valores = [];
        data.forEach((item) => {
          if (item[col] && !valores.includes(item[col])) {
            valores.push(item[col]);
          }
        });
        return valores.sort();
      }

      // Se a tabela ainda não existe, cria ela
      if (!tabelaGlobal) {
        tabelaGlobal = new Tabulator("#tabela-imagens", {
          data: data,
          layout: "fitColumns",
          pagination: "local",
          responsiveLayout: true,
          index: "idpos_producao",
          height: "100%",
          pagination: true,
          paginationSize: 100,

          rowFormatter: function (row) {
            let rowData = row.getData();
            let rowIdValue = rowData.idpos_producao;

            row.getElement().setAttribute("data-tabulator-id", rowIdValue);
          },

          columns: [
            {
              title: "Status Render",
              field: "status_render",
              formatter: (cell) => {
                let val = cell.getValue() || "";
                let cores = {
                  Finalizado: "green",
                  Aprovado: "green",
                  "Em andamento": "orange",
                  Erro: "red",
                  Reprovado: "red",
                  "Em aprovação": "blue",
                };
                let cor = cores[val] || "gray"; // fallback caso venha um status inesperado
                return `<span style="background:${cor};color:white;padding:4px 6px;border-radius:4px;font-size:12px">${val}</span>`;
              },
            },
            { title: "Nome Finalizador", field: "nome_colaborador" },
            { title: "Nome Obra", field: "nomenclatura" },
            {
              title: "Data",
              field: "data_pos",
              formatter: (cell) => formatarDataHora(cell.getValue()),
            },
            {
              title: "Prazo",
              field: "prazo",

              hozAlign: "center",
              width: 120,
              formatter: function (cell) {
                var row = cell.getRow().getData();
                // Não mostrar badge quando status_pos == 0
                if (String(row.status_pos) === "0" || row.status_pos === 0)
                  return "";
                // Usa o valor da célula (prazo) ou fallback para row.prazo
                var val = cell.getValue() || row.prazo;
                return formatDeadlineBadge(val);
              },
            },
            {
              title: "Nome imagem",
              field: "imagem_nome",
              widthGrow: 3,
              formatter: function (cell) {
                var nome = cell.getValue() || "";
                return nome;
              },
            },
            {
              title: "Status",
              field: "status_pos",
              formatter: (cell) => {
                let val = cell.getValue();
                let txt = val == 1 ? "Não começou" : "Finalizado";
                let cor = val == 1 ? "red" : "green";
                return `<span style="background:${cor};color:white;padding:4px 6px;border-radius:4px;font-size:12px">${txt}</span>`;
              },
            },
            {
              title: "Revisão",
              field: "nome_status",
              formatter: function (cell) {
                let val = (cell.getValue() || "");
                let cor = val.toLowerCase();
                return `<span style="background:var(--si-${cor});color:black;padding:4px 6px;border-radius:4px;font-size:12px;font-weight:bold">${val}</span>`;
              },
            },
            // { title: "Responsável", field: "nome_responsavel", headerFilter: "list", headerFilterParams: { values: listaValores("nome_responsavel") } },
          ],
        });
        // Atualiza total ao filtrar
        tabelaGlobal.on("dataFiltered", function (filters, rows) {
          document.getElementById("total-pos").innerText = rows.length;
        });
        // Renderiza o widget de prazos com os dados iniciais
        try {
          renderWidgetPrazos(data);
        } catch (e) {
          console.warn("Erro ao renderizar widget de prazos:", e);
        }
        popularSelectsFiltros(data);
        // *** Adiciona o listener de clique no container da tabela APÓS a criação da tabela ***
        document
          .getElementById("tabela-imagens")
          .addEventListener("click", function (event) {
            // Verifica se o clique foi em uma linha da tabela (ou em um filho de uma linha)
            let target = event.target;
            let rowElement = null;

            rowElement = target.closest(".tabulator-row");

            if (rowElement) {
              let rowId = rowElement.getAttribute("data-tabulator-id");

              if (rowId) {
                // Certifique-se de que o rowId não é nulo antes de tentar usá-lo
                const clickedRow = tabelaGlobal.getRow(rowId); // Isso deve funcionar agora!
                if (clickedRow) {
                  let dados = clickedRow.getData();

                  const statusRender = dados.status_render?.trim() || "";
                  if (!modal) {
                    console.error(
                      "Elemento modal não encontrado no DOM. Verifique o ID ou se o elemento existe.",
                    );
                    return;
                  }

                  if (
                    statusRender !== "Finalizado" &&
                    statusRender !== "Aprovado"
                  ) {
                    Swal.fire({
                      icon: "warning",
                      title: "Atenção",
                      text: 'O status deste item não é "Finalizado". Deseja continuar?',
                      showCancelButton: true,
                      confirmButtonText: "OK",
                      cancelButtonText: "Sair",
                    }).then((result) => {
                      if (result.isConfirmed) {
                        modal.style.display = "flex";
                        limparCampos();
                        buscarInfosImagem(dados.idpos_producao);
                      }
                    });
                  } else {
                    modal.style.display = "flex";
                    limparCampos();
                    buscarInfosImagem(dados.idpos_producao);
                  }
                } else {
                  console.warn(
                    "Não foi possível encontrar os dados da linha para o elemento clicado.",
                  );
                }
              } else {
                console.warn(
                  "WARN: O atributo 'data-tabulator-id' não foi encontrado no elemento da linha clicada.",
                );
              }
            }
          });
      } else {
        // Se a tabela já existe, apenas atualiza os dados
        tabelaGlobal.setData(data); // ou .replaceData(data)
        // Atualiza widget de prazos quando os dados mudam
        try {
          renderWidgetPrazos(data);
        } catch (e) {
          console.warn("Erro ao renderizar widget de prazos:", e);
        }
      }

      document.getElementById("total-pos").innerText =
        tabelaGlobal.getDataCount("active");
    })
    .catch((error) =>
      console.error("Erro ao atualizar a tabela ou buscar dados:", error),
    );
}

atualizarTabela();
carregarMetricas();

// Atualiza tabela automaticamente quando outro usuário faz uma alteração (via WebSocket)
window.addEventListener("improov:posProducaoUpdated", () => {
  atualizarTabela();
});

// Garantir conexão WebSocket mesmo sem sessão de upload ativa
(function () {
  const STORAGE_KEY = "improov_client_id";
  function ensureWs() {
    if (!window.improovUploadWS) return;
    if (!localStorage.getItem(STORAGE_KEY)) {
      localStorage.setItem(
        STORAGE_KEY,
        "pos_" + Math.random().toString(36).slice(2, 10),
      );
    }
    window.improovUploadWS.subscribe(localStorage.getItem(STORAGE_KEY));
  }
  // Aguarda upload-ws.js inicializar (carregado via sidebar)
  if (document.readyState === "complete") ensureWs();
  else window.addEventListener("load", ensureWs);
})();

// Filter bar — Enter no campo de busca
document.getElementById("fb-busca").addEventListener("keydown", function (e) {
  if (e.key === "Enter") document.getElementById("fb-aplicar").click();
});

// Filter bar — Selects aplicam filtro automaticamente ao mudar
["fb-status-render", "fb-status-pos", "fb-obra", "fb-finalizador"].forEach(
  function (id) {
    document.getElementById(id).addEventListener("change", function () {
      document.getElementById("fb-aplicar").click();
    });
  },
);

document.getElementById("fb-busca").addEventListener("input", function () {
  document.getElementById("fb-aplicar").click();
});

// Filter bar — Aplicar
document.getElementById("fb-aplicar").addEventListener("click", function () {
  if (!tabelaGlobal) return;
  document.querySelectorAll(".metric-card").forEach(function (c) {
    c.classList.remove("metric-card--active");
  });
  tabelaGlobal.clearFilter();
  var filtersArr = [];
  var busca = document.getElementById("fb-busca").value.trim();
  if (busca)
    filtersArr.push({ field: "imagem_nome", type: "like", value: busca });
  var statusRenderVal = document.getElementById("fb-status-render").value;
  if (statusRenderVal)
    filtersArr.push({
      field: "status_render",
      type: "=",
      value: statusRenderVal,
    });
  var statusPosVal = document.getElementById("fb-status-pos").value;
  if (statusPosVal !== "")
    filtersArr.push({
      field: "status_pos",
      type: "=",
      value: parseInt(statusPosVal),
    });
  var obraVal = document.getElementById("fb-obra").value;
  if (obraVal)
    filtersArr.push({ field: "nomenclatura", type: "=", value: obraVal });
  var finalizadorVal = document.getElementById("fb-finalizador").value;
  if (finalizadorVal)
    filtersArr.push({
      field: "nome_colaborador",
      type: "=",
      value: finalizadorVal,
    });
  if (filtersArr.length > 0) tabelaGlobal.setFilter(filtersArr);
});

// Filter bar — Limpar
document.getElementById("fb-limpar").addEventListener("click", function () {
  resetarFiltrosBar();
  document.querySelectorAll(".metric-card").forEach(function (c) {
    c.classList.remove("metric-card--active");
  });
  if (tabelaGlobal) tabelaGlobal.clearFilter();
});

// Metric cards — click para filtrar tabela
document.querySelectorAll(".metric-card").forEach(function (card) {
  card.addEventListener("click", function () {
    var filterType = this.dataset.filter;
    document.querySelectorAll(".metric-card").forEach(function (c) {
      c.classList.remove("metric-card--active");
    });
    this.classList.add("metric-card--active");
    resetarFiltrosBar();
    aplicarFiltroCard(filterType);
  });
});

function setSelectValue(selectId, valueToSelect) {
  var selectElement = document.getElementById(selectId);
  var options = selectElement.options;

  for (var i = 0; i < options.length; i++) {
    if (options[i].text === valueToSelect) {
      selectElement.selectedIndex = i;
      break;
    }
  }
}

function buscarInfosImagem(idImagemSelecionada) {
  $.ajax({
    type: "GET",
    dataType: "json",
    url: "https://www.improov.com.br/flow/ImproovWeb/Pos-Producao/buscaAJAX.php",
    data: { ajid: idImagemSelecionada },
    success: function (response) {
      if (response.length > 0) {
        var d = response[0];
        setSelectValue("opcao_finalizador", d.nome_colaborador);
        setSelectValue("opcao_obra", d.nome_obra);
        document.getElementById("imagem_id_pos").value = d.id_imagem;
        document.getElementById("id-pos").value = d.idpos_producao;
        document.getElementById("caminhoPasta").value = d.caminho_pasta || "";
        document.getElementById("numeroBG").value = d.numero_bg || "";
        document.getElementById("referenciasCaminho").value = d.refs || "";
        document.getElementById("observacao").value = d.obs || "";
        document.getElementById("render_id_pos").value = d.idrender || "";
        setSelectValue("opcao_status", d.nome_status);
        setSelectValue("responsavel_id", d.nome_responsavel);
        document.getElementById("alterar_imagem").value = "true";

        // Título e subtítulo do header do modal
        document.getElementById("modal-title-imagem").textContent =
          d.imagem_nome || "Pós-Produção";
        document.getElementById("modal-subtitle-obra").textContent =
          d.nome_obra || "";

        // Botão Finalizar / Voltar
        _atualizarBotaoFinalizar(d.status_pos);

        _limparTodasSaveStatus();
      } else {
        console.log("Nenhum produto encontrado.");
      }
    },
    error: function (jqXHR, textStatus, errorThrown) {
      console.error("Erro na requisição AJAX: " + textStatus, errorThrown);
    },
  });
}

function formatarDataHora(data) {
  const date = new Date(data); // Cria um objeto Date a partir da string datetime

  const dia = String(date.getDate()).padStart(2, "0"); // Pega o dia e formata com 2 dígitos
  const mes = String(date.getMonth() + 1).padStart(2, "0"); // Pega o mês e formata com 2 dígitos (mes começa do 0)
  const ano = date.getFullYear(); // Pega o ano
  const horas = String(date.getHours()).padStart(2, "0"); // Pega a hora e formata com 2 dígitos
  const minutos = String(date.getMinutes()).padStart(2, "0"); // Pega os minutos e formata com 2 dígitos

  return `${dia}/${mes}/${ano} ${horas}:${minutos}`; // Retorna o formato desejado
}

// Converte string de prazo em objeto Date.
// Suporta formatos: "YYYY-MM-DD" (interpreta como fim do dia local) e strings com hora como "YYYY-MM-DD HH:MM:SS".
function parsePrazoToDate(deadlineIso) {
  if (!deadlineIso) return null;
  var s = String(deadlineIso).trim();
  // Match apenas data YYYY-MM-DD
  var dateOnly = /^\d{4}-\d{2}-\d{2}$/.test(s);
  if (dateOnly) {
    var parts = s.split("-");
    var y = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10) - 1;
    var d = parseInt(parts[2], 10);
    // Usa fim do dia local para que a data inteira seja considerada durante o dia
    return new Date(y, m, d, 23, 59, 59);
  }

  // Tenta normalizar espaço entre data e hora para 'T' e criar Date
  var normalized = s.replace(" ", "T");
  var dt = new Date(normalized);
  if (!isNaN(dt.getTime())) return dt;

  // Fallback: tenta criar Date diretamente
  dt = new Date(s);
  if (!isNaN(dt.getTime())) return dt;

  return null;
}

// Formata e retorna um badge HTML para o prazo
function formatDeadlineBadge(deadlineIso) {
  var dl = parsePrazoToDate(deadlineIso);
  if (!dl)
    return '<span class="deadline-badge badge-none" title="Sem prazo">—</span>';
  var now = new Date();
  // Zeramos horas/minutos/segundos de 'now' para comparar dias inteiros corretamente
  var diffMs = dl.getTime() - now.getTime();
  var diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
  // Quando o prazo é para o mesmo dia (diffDays === 0), já será tratado como Hoje
  var text, cls;
  if (diffDays < 0) {
    text = "Atraso";
    cls = "badge-overdue";
  } else if (diffDays === 0) {
    text = "Hoje";
    cls = "badge-overdue";
  } else if (diffDays <= 2) {
    text = "Em " + diffDays + "d";
    cls = "badge-urgent";
  } else if (diffDays <= 7) {
    text = "Em " + diffDays + "d";
    cls = "badge-soon";
  } else {
    text = "Em " + diffDays + "d";
    cls = "badge-normal";
  }
  // Formata data para tooltip (se o objeto Date não tiver hora, parsePrazoToDate define 23:59)
  var fullDate = dl.toLocaleString("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
  });
  var title =
    "Entrega: " +
    fullDate +
    " — faltam " +
    (diffDays < 0
      ? Math.abs(diffDays) + " dias (atrasado)"
      : diffDays + " dias");
  return (
    '<span class="deadline-badge ' +
    cls +
    '" title="' +
    title +
    '">' +
    text +
    "</span>"
  );
}

var POS_PODE_REORDENAR = false; // Será definido no index.php via script inline
const colaborador_id = localStorage.getItem("idcolaborador") || null;
if (colaborador_id == 9 || colaborador_id == 21) {
  POS_PODE_REORDENAR = true;
}

// ──────────────────────────────────────────────
// WIDGET — Próximos Prazos
// ──────────────────────────────────────────────
var _widgetData = [];
var _openGrupos = new Set(); // grupos expandidos (persistem entre re-renders)
var _grupoMouseupInit = false;

function renderWidgetPrazos(data) {
  _widgetData = data || [];
  var container = document.getElementById("widget-prazos-content");
  if (!container) return;

  var items = _widgetData.filter(function (d) {
    return String(d.status_pos) === "1" || d.status_pos === 1;
  });

  if (items.length === 0) {
    container.innerHTML =
      '<div class="widget-empty">Nenhuma pós pendente</div>';
    return;
  }

  // Agrupa por obra; extrai idobra e prioridade_obra de cada item
  var grupos = {};
  var obraIds = {};
  var prioridadesObra = {};

  items.forEach(function (it) {
    var obra = it.nomenclatura || it.nome_obra || "Sem obra";
    if (!grupos[obra]) {
      grupos[obra] = [];
      obraIds[obra] = it.idobra || null;
      prioridadesObra[obra] =
        it.prioridade_obra != null ? parseInt(it.prioridade_obra) : null;
    }
    grupos[obra].push(it);
  });

  // Ordena imagens dentro de cada grupo: urgentes primeiro, depois prioridade
  Object.keys(grupos).forEach(function (obra) {
    grupos[obra].sort(function (a, b) {
      var fa = parseInt(a.flag_urgente) || 0;
      var fb = parseInt(b.flag_urgente) || 0;
      if (fb !== fa) return fb - fa;
      return (parseInt(a.prioridade) || 0) - (parseInt(b.prioridade) || 0);
    });
  });

  // Ordena obras: manuais (prioridade_obra definida) primeiro → pelo número
  //               novas (null) → pelo prazo mais próximo
  var obrasSorted = Object.keys(grupos).sort(function (a, b) {
    var pa = prioridadesObra[a];
    var pb = prioridadesObra[b];
    if (pa != null && pb != null) return pa - pb;
    if (pa != null) return -1;
    if (pb != null) return 1;
    var ta = (
      _prazoMaisProximo(grupos[a]) || {
        getTime: function () {
          return Infinity;
        },
      }
    ).getTime();
    var tb = (
      _prazoMaisProximo(grupos[b]) || {
        getTime: function () {
          return Infinity;
        },
      }
    ).getTime();
    return ta - tb;
  });

  var html = "";
  obrasSorted.forEach(function (obra) {
    var itens = grupos[obra];
    var prazoRef = _prazoMaisProximo(itens);
    var prazoLbl = _labelPrazo(prazoRef);
    var grupoId = "wg-" + obra.replace(/[^a-zA-Z0-9]/g, "_");
    var obraId = obraIds[obra] || "";

    html +=
      '<div class="widget-grupo" data-obra="' +
      _esc(obra) +
      '" data-obra-id="' +
      obraId +
      '">';

    // Header — drag handle de obra separado do toggle
    html += '<div class="widget-grupo-header">';
    if (POS_PODE_REORDENAR) {
      html +=
        '<span class="widget-grupo-drag-handle" title="Arrastar para reordenar obra">⠿</span>';
    }
    html +=
      '<span class="widget-grupo-toggle" id="' +
      grupoId +
      '-arr" onclick="toggleWidgetGrupo(\'' +
      grupoId +
      "')\">▶</span>";
    html +=
      '<span class="widget-grupo-nome" onclick="toggleWidgetGrupo(\'' +
      grupoId +
      "')\">" +
      _esc(obra) +
      "</span>";
    if (prazoLbl.text) {
      html +=
        '<span class="deadline-badge ' +
        prazoLbl.cls +
        '">' +
        prazoLbl.text +
        "</span>";
    }
    html += '<span class="widget-grupo-count">' + itens.length + "</span>";
    html += "</div>"; // .widget-grupo-header

    html +=
      '<div class="widget-grupo-body" id="' +
      grupoId +
      '" style="display:none">';
    html += '<ul class="widget-lista" data-obra="' + _esc(obra) + '">';

    itens.forEach(function (it) {
      var urgente = parseInt(it.flag_urgente) === 1;
      html +=
        '<li class="widget-linha' +
        (urgente ? " widget-linha--urgente" : "") +
        '"' +
        ' data-id="' +
        it.idpos_producao +
        '"' +
        ' data-prioridade="' +
        (it.prioridade || 0) +
        '"' +
        ' data-urgente="' +
        (urgente ? "1" : "0") +
        '"' +
        (POS_PODE_REORDENAR ? ' draggable="true"' : "") +
        ">";
      if (POS_PODE_REORDENAR) {
        html +=
          '<span class="widget-drag-handle" title="Arrastar para reordenar imagem">⠿</span>';
      }
      html +=
        '<span class="widget-linha-nome" title="' +
        _esc(it.imagem_nome) +
        '">' +
        _esc(it.imagem_nome) +
        "</span>";
      if (POS_PODE_REORDENAR) {
        html +=
          '<button class="widget-urgente-btn' +
          (urgente ? " widget-urgente-btn--on" : "") +
          '"' +
          ' onclick="toggleUrgente(this,' +
          it.idpos_producao +
          ')"' +
          ' title="' +
          (urgente ? "Remover urgente" : "Marcar urgente") +
          '">' +
          (urgente ? "🔴" : "⚪") +
          "</button>";
      } else if (urgente) {
        html += '<span class="widget-urgente-badge">🔴</span>';
      }
      html += "</li>";
    });

    html += "</ul></div>"; // .widget-lista / .widget-grupo-body
    html += "</div>"; // .widget-grupo
  });

  container.innerHTML = html;

  // Restaura grupos que estavam abertos antes do re-render
  _openGrupos.forEach(function (grupoId) {
    var body = document.getElementById(grupoId);
    var arr = document.getElementById(grupoId + "-arr");
    if (body) body.style.display = "block";
    if (arr) arr.textContent = "▼";
  });

  // Drag das imagens (dentro de cada grupo)
  if (POS_PODE_REORDENAR) {
    container.querySelectorAll(".widget-lista").forEach(function (lista) {
      _initDragDrop(lista);
    });

    // Drag das obras (grupos inteiros)
    _initDragDropGrupos(container);
  }
}

// ── Helpers ──────────────────────────────────

function _prazoMaisProximo(itens) {
  var dl = null;
  itens.forEach(function (it) {
    var d = parsePrazoToDate(it.prazo);
    if (d && (!dl || d < dl)) dl = d;
  });
  return dl;
}

function _labelPrazo(dl) {
  if (!dl) return { text: "", cls: "" };
  var diffDays = Math.floor((dl.getTime() - Date.now()) / 86400000);
  if (diffDays < 0) return { text: "Atraso", cls: "badge-overdue" };
  if (diffDays === 0) return { text: "Hoje", cls: "badge-overdue" };
  if (diffDays <= 2)
    return { text: "Em " + diffDays + "d", cls: "badge-urgent" };
  if (diffDays <= 7) return { text: "Em " + diffDays + "d", cls: "badge-soon" };
  return { text: "Em " + diffDays + "d", cls: "badge-normal" };
}

function _esc(str) {
  return String(str || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

// ── Expand/collapse ──────────────────────────

function toggleWidgetGrupo(grupoId) {
  var body = document.getElementById(grupoId);
  var arr = document.getElementById(grupoId + "-arr");
  if (!body) return;
  var open = body.style.display !== "none";
  if (open) {
    body.style.display = "none";
    if (arr) arr.textContent = "▶";
    _openGrupos.delete(grupoId);
  } else {
    body.style.display = "block";
    if (arr) arr.textContent = "▼";
    _openGrupos.add(grupoId);
  }
}

// ── Urgente toggle ───────────────────────────

function toggleUrgente(btn, id) {
  if (!POS_PODE_REORDENAR) return;
  var li = btn.closest(".widget-linha");
  var urgente = li.dataset.urgente === "1" ? 0 : 1;

  fetch("prioridade_pos.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "urgente", id: id, flag_urgente: urgente }),
  })
    .then(function (r) {
      return r.json();
    })
    .then(function (res) {
      if (!res.success) {
        console.warn("Erro urgente:", res.message);
        return;
      }
      _widgetData.forEach(function (d) {
        if (parseInt(d.idpos_producao) === parseInt(id))
          d.flag_urgente = urgente;
      });
      renderWidgetPrazos(_widgetData);
      Toastify({
        text: urgente ? "🔴 Marcado como urgente" : "Urgente removido",
        duration: 3000,
        gravity: "top",
        position: "right",
        style: {
          background: urgente ? "#dc2626" : "#10b981",
          borderRadius: "var(--radius-sm)",
          fontFamily: '"Inter", sans-serif',
          fontSize: "13px",
          fontWeight: "500",
        },
      }).showToast();
      atualizarTabela(); // Recarrega tabela para refletir mudança de urgente
    })
    .catch(function (e) {
      console.error("Erro toggleUrgente:", e);
    });
}

// ── Drag & drop — imagens dentro de um grupo ─

function _initDragDrop(lista) {
  var dragSrc = null;

  lista.querySelectorAll(".widget-linha").forEach(function (li) {
    li.addEventListener("dragstart", function (e) {
      dragSrc = this;
      this.classList.add("widget-linha--dragging");
      e.dataTransfer.effectAllowed = "move";
      e.stopPropagation(); // não propaga para o drag de grupos
    });
    li.addEventListener("dragend", function (e) {
      e.stopPropagation(); // impede que o dragend borbulhe para o grupo
      this.classList.remove("widget-linha--dragging");
      lista.querySelectorAll(".widget-linha").forEach(function (el) {
        el.classList.remove("widget-linha--over");
      });
      _salvarOrdem(lista);
    });
    li.addEventListener("dragover", function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (this === dragSrc) return;
      lista.querySelectorAll(".widget-linha").forEach(function (el) {
        el.classList.remove("widget-linha--over");
      });
      this.classList.add("widget-linha--over");
      var mid =
        this.getBoundingClientRect().top +
        this.getBoundingClientRect().height / 2;
      if (e.clientY < mid) lista.insertBefore(dragSrc, this);
      else lista.insertBefore(dragSrc, this.nextSibling);
    });
    li.addEventListener("drop", function (e) {
      e.preventDefault();
      e.stopPropagation();
    });
  });
}

function _salvarOrdem(lista) {
  var items = [];
  lista.querySelectorAll(".widget-linha").forEach(function (li, idx) {
    items.push({ id: parseInt(li.dataset.id), prioridade: idx + 1 });
  });
  items.forEach(function (item) {
    _widgetData.forEach(function (d) {
      if (parseInt(d.idpos_producao) === item.id)
        d.prioridade = item.prioridade;
    });
  });
  fetch("prioridade_pos.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "reorder", items: items }),
  })
    .then(function (r) {
      return r.json();
    })
    .then(function (res) {
      if (!res.success) {
        console.warn("Erro ao salvar ordem:", res.message);
        Toastify({
          text: "Erro ao salvar ordem das imagens.",
          duration: 3000,
          gravity: "top",
          position: "right",
          style: {
            background: "#ef4444",
            borderRadius: "var(--radius-sm)",
            fontFamily: '"Inter", sans-serif',
            fontSize: "13px",
            fontWeight: "500",
          },
        }).showToast();
        return;
      }
      Toastify({
        text: "Ordem das imagens salva!",
        duration: 3000,
        gravity: "top",
        position: "right",
        style: {
          background: "#10b981",
          borderRadius: "var(--radius-sm)",
          fontFamily: '"Inter", sans-serif',
          fontSize: "13px",
          fontWeight: "500",
        },
      }).showToast();
      atualizarTabela(); // Recarrega tabela para refletir nova ordem
    })
    .catch(function (e) {
      console.error("Erro _salvarOrdem:", e);
    });
}

// ── Drag & drop — obras (grupos inteiros) ────

function _initDragDropGrupos(container) {
  // Listener de mouseup adicionado apenas uma vez — remove draggable ao soltar
  if (!_grupoMouseupInit) {
    _grupoMouseupInit = true;
    document.addEventListener("mouseup", function () {
      document
        .querySelectorAll("#widget-prazos-content .widget-grupo")
        .forEach(function (g) {
          g.removeAttribute("draggable");
        });
    });
  }

  var dragSrcGrupo = null;

  // O handle de obra habilita draggable no pai (grupo inteiro) ao pressionar
  container
    .querySelectorAll(".widget-grupo-drag-handle")
    .forEach(function (handle) {
      handle.addEventListener("mousedown", function () {
        var grupo = this.closest(".widget-grupo");
        if (grupo) grupo.setAttribute("draggable", "true");
      });
    });

  container.querySelectorAll(".widget-grupo").forEach(function (grupo) {
    grupo.addEventListener("dragstart", function (e) {
      if (!this.getAttribute("draggable")) {
        e.preventDefault();
        return;
      }
      dragSrcGrupo = this;
      this.classList.add("widget-grupo--dragging");
      e.dataTransfer.effectAllowed = "move";
    });

    grupo.addEventListener("dragend", function () {
      this.removeAttribute("draggable");
      this.classList.remove("widget-grupo--dragging");
      container.querySelectorAll(".widget-grupo").forEach(function (g) {
        g.classList.remove("widget-grupo--over");
      });
      var foiDragGrupo = dragSrcGrupo !== null;
      dragSrcGrupo = null;
      if (foiDragGrupo) _salvarOrdemObras(container);
    });

    grupo.addEventListener("dragover", function (e) {
      if (!dragSrcGrupo || dragSrcGrupo === this) return;
      e.preventDefault();
      container.querySelectorAll(".widget-grupo").forEach(function (g) {
        g.classList.remove("widget-grupo--over");
      });
      this.classList.add("widget-grupo--over");
      var mid =
        this.getBoundingClientRect().top +
        this.getBoundingClientRect().height / 2;
      if (e.clientY < mid) container.insertBefore(dragSrcGrupo, this);
      else container.insertBefore(dragSrcGrupo, this.nextSibling);
    });

    grupo.addEventListener("drop", function (e) {
      e.preventDefault();
      e.stopPropagation();
    });
  });
}

function _salvarOrdemObras(container) {
  var items = [];
  container.querySelectorAll(".widget-grupo").forEach(function (grupo, idx) {
    var obraId = parseInt(grupo.dataset.obraId);
    if (obraId) items.push({ obra_id: obraId, prioridade: idx + 1 });
  });
  if (!items.length) return;

  items.forEach(function (item) {
    _widgetData.forEach(function (d) {
      if (parseInt(d.idobra) === item.obra_id)
        d.prioridade_obra = item.prioridade;
    });
  });

  fetch("prioridade_pos.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action: "reorder_obra", items: items }),
  })
    .then(function (r) {
      return r.json();
    })
    .then(function (res) {
      if (!res.success) {
        console.warn("Erro ao salvar ordem das obras:", res.message);
        Toastify({
          text: "Erro ao salvar ordem das obras.",
          duration: 3000,
          gravity: "top",
          position: "right",
          style: {
            background: "#ef4444",
            borderRadius: "var(--radius-sm)",
            fontFamily: '"Inter", sans-serif',
            fontSize: "13px",
            fontWeight: "500",
          },
        }).showToast();
        return;
      }
      Toastify({
        text: "Ordem das obras salva!",
        duration: 3000,
        gravity: "top",
        position: "right",
        style: {
          background: "#10b981",
          borderRadius: "var(--radius-sm)",
          fontFamily: '"Inter", sans-serif',
          fontSize: "13px",
          fontWeight: "500",
        },
      }).showToast();
      atualizarTabela(); // Recarrega tabela para refletir nova ordem de obras
    })
    .catch(function (e) {
      console.error("Erro _salvarOrdemObras:", e);
    });
}

function contarLinhasTabela() {
  const tabela = document.getElementById("tabela-imagens");
  const tbody = tabela.getElementsByTagName("tbody")[0];
  const linhas = tbody.getElementsByTagName("tr");
  let totalImagens = 0;

  for (let i = 0; i < linhas.length; i++) {
    if (linhas[i].style.display !== "none") {
      totalImagens++;
    }
  }

  document.getElementById("total-pos").innerText = totalImagens;
}

function aplicarFiltros() {
  const indiceColuna = document.getElementById("colunaFiltro").value;
  const filtro = document.getElementById("filtro-input").value.toLowerCase();
  const filtroMes = document.getElementById("filtro-mes").value;
  const anoAtual = new Date().getFullYear();
  const tabela = document.querySelector("#tabela-imagens tbody");
  const linhas = tabela.getElementsByTagName("tr");

  for (let i = 0; i < linhas.length; i++) {
    const linha = linhas[i];
    const cols = linha.getElementsByTagName("td");
    let mostraLinha = true;

    // Filtro por coluna
    if (cols[indiceColuna]) {
      const valorColuna =
        cols[indiceColuna].textContent || cols[indiceColuna].innerText;
      if (valorColuna.toLowerCase().indexOf(filtro) === -1) {
        mostraLinha = false;
      }
    }

    // Filtro por mês e ano atual
    const dataCell = linha.cells[3];
    if (dataCell) {
      const dataTexto = dataCell.textContent || dataCell.innerText;
      const [anoData, mesData] = dataTexto.split("-");
      if (
        filtroMes !== "" &&
        (mesData !== filtroMes || anoData !== anoAtual.toString())
      ) {
        mostraLinha = false;
      }
    }

    // Exibe ou oculta a linha com base nos filtros
    linha.style.display = mostraLinha ? "" : "none";
  }

  contarLinhasTabela(); // Atualiza o contador
}

// // Atualiza os eventos para chamar a nova função de filtro combinado
// document.getElementById("colunaFiltro").addEventListener("change", aplicarFiltros);
// document.getElementById("filtro-input").addEventListener("input", aplicarFiltros);
// document.getElementById("filtro-mes").addEventListener("change", aplicarFiltros);

/* ==========================================
   NOVAS FUNÇÕES — Dashboard de Métricas + Filter Bar
========================================== */

function carregarMetricas() {
  fetch("metricas.php")
    .then(function (response) {
      return response.json();
    })
    .then(function (data) {
      var el;
      el = document.getElementById("metric-pendentes");
      if (el) el.textContent = data.total_pendentes ?? "—";
      el = document.getElementById("metric-atraso");
      if (el) el.textContent = data.em_atraso ?? "—";
      el = document.getElementById("metric-hoje");
      if (el) el.textContent = data.finalizados_hoje ?? "—";
      el = document.getElementById("metric-semana");
      if (el) el.textContent = data.finalizados_semana ?? "—";
    })
    .catch(function (err) {
      console.warn("Erro ao carregar métricas:", err);
    });
}

function popularSelectsFiltros(data) {
  var obraSelect = document.getElementById("fb-obra");
  var finalizadorSelect = document.getElementById("fb-finalizador");
  if (!obraSelect || !finalizadorSelect) return;

  // Popula obras a partir dos dados carregados (nomenclatura)
  // Remove opções anteriores exceto a primeira ("Nome Obra")
  while (obraSelect.options.length > 1) obraSelect.remove(1);
  var obras = Array.from(
    new Set(
      data
        .map(function (d) {
          return d.nomenclatura;
        })
        .filter(Boolean),
    ),
  ).sort();
  obras.forEach(function (o) {
    var opt = document.createElement("option");
    opt.value = o;
    opt.textContent = o;
    obraSelect.appendChild(opt);
  });

  // Popula finalizadores
  while (finalizadorSelect.options.length > 1) finalizadorSelect.remove(1);
  var finalizadores = Array.from(
    new Set(
      data
        .map(function (d) {
          return d.nome_colaborador;
        })
        .filter(Boolean),
    ),
  ).sort();
  finalizadores.forEach(function (f) {
    var opt = document.createElement("option");
    opt.value = f;
    opt.textContent = f;
    finalizadorSelect.appendChild(opt);
  });
}

function resetarFiltrosBar() {
  var ids = [
    "fb-busca",
    "fb-status-render",
    "fb-status-pos",
    "fb-obra",
    "fb-finalizador",
  ];
  ids.forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.value = "";
  });
}

function aplicarFiltroCard(filterType) {
  if (!tabelaGlobal) return;
  tabelaGlobal.clearFilter();

  if (filterType === "pendentes") {
    tabelaGlobal.setFilter("status_pos", "=", 1);
  } else if (filterType === "atraso") {
    tabelaGlobal.setFilter(function (data) {
      if (parseInt(data.status_pos) !== 1) return false;
      var dl = parsePrazoToDate(data.prazo);
      if (!dl) return false;
      return dl < new Date();
    });
  } else if (filterType === "hoje") {
    tabelaGlobal.setFilter(function (data) {
      if (parseInt(data.status_pos) !== 0) return false;
      var d = new Date(data.data_pos);
      var hoje = new Date();
      return (
        d.getFullYear() === hoje.getFullYear() &&
        d.getMonth() === hoje.getMonth() &&
        d.getDate() === hoje.getDate()
      );
    });
  } else if (filterType === "semana") {
    tabelaGlobal.setFilter(function (data) {
      if (parseInt(data.status_pos) !== 0) return false;
      var d = new Date(data.data_pos);
      var hoje = new Date();
      var day = hoje.getDay();
      var diff = day === 0 ? -6 : 1 - day; // Monday start
      var startOfWeek = new Date(hoje);
      startOfWeek.setDate(hoje.getDate() + diff);
      startOfWeek.setHours(0, 0, 0, 0);
      var endOfWeek = new Date(startOfWeek);
      endOfWeek.setDate(startOfWeek.getDate() + 6);
      endOfWeek.setHours(23, 59, 59, 999);
      return d >= startOfWeek && d <= endOfWeek;
    });
  }
}
