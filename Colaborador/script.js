let dataTable = null;
let atuacoesFuncoes = {};

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function atualizarAtuacoesFuncoes() {
  const selecionadas = $("#funcaoSelect option:selected").toArray();
  const grupo = document.getElementById("atuacoesFuncoesGroup");
  const container = document.getElementById("atuacoesFuncoes");

  grupo.hidden = selecionadas.length === 0;
  if (selecionadas.length === 0) {
    container.innerHTML = "";
    return;
  }

  container.innerHTML = selecionadas
    .map((option) => {
      const id = String(option.value);
      const tipo =
        atuacoesFuncoes[id] === "PRINCIPAL" ? "PRINCIPAL" : "SECUNDARIA";
      const nome = escapeHtml(option.textContent.trim());
      return `
        <div class="atuacao-funcao">
          <span class="atuacao-funcao__nome">${nome}</span>
          <div class="atuacao-funcao__opcoes" role="radiogroup" aria-label="Atuação em ${nome}">
            <label>
              <input type="radio" name="tipo_atuacao[${id}]" value="PRINCIPAL" ${tipo === "PRINCIPAL" ? "checked" : ""}>
              Principal
            </label>
            <label>
              <input type="radio" name="tipo_atuacao[${id}]" value="SECUNDARIA" ${tipo !== "PRINCIPAL" ? "checked" : ""}>
              Secundária
            </label>
          </div>
        </div>`;
    })
    .join("");
}

function atualizarNivelFinalizacao() {
  const funcoesSelecionadas = $("#funcaoSelect option:selected");
  const possuiFinalizacao = funcoesSelecionadas.toArray().some((option) => {
    return option.dataset.finalizacao === "1";
  });
  const possuiArquitetura = funcoesSelecionadas.toArray().some((option) => {
    return option.dataset.arquitetura === "1";
  });
  const possuiAnimacao = funcoesSelecionadas.toArray().some((option) => {
    return option.dataset.animacao === "1";
  });
  const grupoNivel = document.getElementById("nivelFinalizacaoGroup");
  const selectNivel = document.getElementById("nivelFinalizacao");
  const grupoTipo = document.getElementById("tipoFinalizacaoGroup");
  const selectTipo = document.getElementById("tipoFinalizacao");
  const grupoNivelArquitetura = document.getElementById(
    "nivelArquiteturaGroup",
  );
  const selectNivelArquitetura = document.getElementById("nivelArquitetura");
  const grupoNivelAnimacao = document.getElementById("nivelAnimacaoGroup");
  const selectNivelAnimacao = document.getElementById("nivelAnimacao");

  grupoNivel.hidden = !possuiFinalizacao;
  selectNivel.required = possuiFinalizacao;
  grupoTipo.hidden = !possuiFinalizacao;
  selectTipo.required = possuiFinalizacao;
  grupoNivelArquitetura.hidden = !possuiArquitetura;
  selectNivelArquitetura.required = possuiArquitetura;
  grupoNivelAnimacao.hidden = !possuiAnimacao;
  selectNivelAnimacao.required = possuiAnimacao;

  if (!possuiFinalizacao) {
    selectNivel.value = "";
    $(selectTipo).val([]).trigger("change");
  }
  if (!possuiArquitetura) {
    selectNivelArquitetura.value = "";
  }
  if (!possuiAnimacao) {
    selectNivelAnimacao.value = "";
  }
}

function showToast(message, type = "info") {
  const colors = { success: "#10b981", error: "#ef4444", info: "#4f80e1" };
  Toastify({
    text: message,
    duration: 3000,
    gravity: "top",
    position: "right",
    style: {
      background: colors[type] || colors.info,
      borderRadius: "8px",
      fontFamily: '"Inter", sans-serif',
      fontSize: "13px",
      fontWeight: "500",
    },
  }).showToast();
}

function carregarUsuarios() {
  fetch("usuarios.php")
    .then((response) => response.json())
    .then((data) => {
      let result = "";

      data.forEach((element) => {
        const ativoBadge =
          element.ativo == 1
            ? '<span class="status-badge s-ativo">Ativo</span>'
            : '<span class="status-badge s-inativo">Inativo</span>';
        result += `
                    <tr class="usuario-row" data-idusuario="${element.idusuario}" data-idcolaborador="${element.idcolaborador}" data-ativo="${element.ativo}">
                        <td>${element.idusuario}</td>
                        <td>${element.nome_colaborador || "-"}</td>
                        <td>${element.nome_usuario}</td>
                        <td>${element.login || "-"}</td>
                        <td>${element.nivel_acesso ?? "-"}</td>
                        <td>${element.nome_cargo || "-"}</td>
                        <td class="col-center">${ativoBadge}</td>
                    </tr>
                `;
      });

      document.querySelector("#usuarios tbody").innerHTML = result;

      if (dataTable) {
        dataTable.destroy();
      }

      dataTable = $("#usuarios").DataTable({
        paging: true,
        lengthChange: false,
        info: false,
        ordering: true,
        searching: true,
        pageLength: 15,
        order: [[0, "desc"]],
        language: {
          url: "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Portuguese.json",
        },
      });

      document.getElementById("resultsCount").textContent = data.length;

      document.querySelectorAll(".usuario-row").forEach((row) => {
        row.addEventListener("click", function () {
          abrirModalEdicao(this.getAttribute("data-idusuario"));
        });

        row.addEventListener("contextmenu", function (event) {
          event.preventDefault();
          abrirMenuStatus(event, this);
        });
      });
    });
}

function abrirModalEdicao(idusuario) {
  const modal = document.getElementById("modal");
  modal.classList.add("is-open");
  document.getElementById("modalTitle").textContent = "Editar colaborador";
  document.getElementById("action").value = "update";
  document.getElementById("btnExcluir").style.display = "inline-flex";

  fetch(`get_usuario.php?idusuario=${idusuario}`)
    .then((response) => response.json())
    .then((data) => {
      document.getElementById("idusuario").value = data.usuario.idusuario;
      document.getElementById("idcolaborador").value =
        data.usuario.idcolaborador;
      document.getElementById("nome_colaborador").value =
        data.usuario.nome_colaborador || "";
      document.getElementById("nome_usuario").value =
        data.usuario.nome_usuario || "";
      document.getElementById("login").value = data.usuario.login || "";
      document.getElementById("senha").value = "";
      document.getElementById("nivel_acesso").value =
        data.usuario.nivel_acesso ?? "";

      $("#cargoSelect").val(data.cargos).trigger("change");
      atuacoesFuncoes = data.funcoes_atuacao || {};
      $("#funcaoSelect").val(data.funcoes).trigger("change");
      $("#nivelFinalizacao").val(data.nivel_finalizacao ?? "");
      $("#tipoFinalizacao")
        .val(data.tipos_finalizacao || data.tipo_finalizacao || [])
        .trigger("change");
      $("#nivelArquitetura").val(data.nivel_arquitetura ?? "");
      $("#nivelAnimacao").val(data.nivel_animacao ?? "");
      atualizarNivelFinalizacao();
      atualizarAtuacoesFuncoes();
      document.getElementById("elegivelCapacidade").checked =
        parseInt(data.usuario.elegivel_capacidade ?? 1, 10) === 1;

      const ativo = parseInt(data.usuario.ativo) === 1;
      const btnToggle = document.getElementById("btnToggleStatus");
      btnToggle.style.display = "inline-flex";
      btnToggle.className =
        "btn-toggle-status " + (ativo ? "is-ativo" : "is-inativo");
      btnToggle.setAttribute("data-idusuario", data.usuario.idusuario);
      btnToggle.setAttribute("data-ativo", ativo ? "1" : "0");
      btnToggle.innerHTML = ativo
        ? '<i class="fa-solid fa-ban"></i> Desativar'
        : '<i class="fa-solid fa-circle-check"></i> Ativar';
    });
}

function abrirModalNovo() {
  const modal = document.getElementById("modal");
  modal.classList.add("is-open");
  document.getElementById("modalTitle").textContent = "Novo colaborador";
  document.getElementById("action").value = "create";
  document.getElementById("btnExcluir").style.display = "none";
  document.getElementById("btnToggleStatus").style.display = "none";

  document.getElementById("form").reset();
  document.getElementById("idusuario").value = "";
  document.getElementById("idcolaborador").value = "";
  $("#cargoSelect").val([]).trigger("change");
  $("#funcaoSelect").val([]).trigger("change");
  atuacoesFuncoes = {};
  $("#nivelFinalizacao").val("");
  $("#tipoFinalizacao").val([]).trigger("change");
  $("#nivelArquitetura").val("");
  $("#nivelAnimacao").val("");
  document.getElementById("elegivelCapacidade").checked = true;
  atualizarNivelFinalizacao();
  atualizarAtuacoesFuncoes();
}

function fecharModal() {
  $("#modal").removeClass("is-open");
}

function abrirMenuStatus(event, row) {
  const menu = document.getElementById("statusMenu");
  const btn = document.getElementById("toggleStatusBtn");
  const ativo = row.getAttribute("data-ativo") === "1";

  btn.textContent = ativo ? "Desativar colaborador" : "Ativar colaborador";
  btn.onclick = function () {
    toggleStatus(row.getAttribute("data-idusuario"), ativo ? 0 : 1);
  };

  menu.style.display = "block";
  menu.setAttribute("aria-hidden", "false");

  const menuRect = menu.getBoundingClientRect();
  const viewportWidth = window.innerWidth;
  const viewportHeight = window.innerHeight;

  let left = event.clientX;
  let top = event.clientY;

  if (left + menuRect.width > viewportWidth - 12) {
    left = event.clientX - menuRect.width;
  }

  if (top + menuRect.height > viewportHeight - 12) {
    top = event.clientY - menuRect.height;
  }

  menu.style.left = `${Math.max(12, left)}px`;
  menu.style.top = `${Math.max(12, top)}px`;
}

function esconderMenuStatus() {
  const menu = document.getElementById("statusMenu");
  menu.style.display = "none";
  menu.setAttribute("aria-hidden", "true");
}

function toggleStatus(idusuario, ativo, fromModal = false) {
  $.ajax({
    type: "POST",
    url: "salvar_colaborador.php",
    data: { action: "toggle_status", idusuario, ativo },
    dataType: "json",
    success: function (response) {
      if (response.success) {
        carregarUsuarios();
        esconderMenuStatus();
        const msg =
          ativo == 1
            ? "Colaborador ativado com sucesso!"
            : "Colaborador desativado com sucesso!";
        showToast(msg, "success");
        if (fromModal) fecharModal();
      } else {
        showToast(response.message || "Erro ao atualizar status.", "error");
      }
    },
    error: function () {
      showToast("Erro ao atualizar status.", "error");
    },
  });
}

carregarUsuarios();

// Fechar modal ao clicar fora dele
window.onclick = function (event) {
  const modal = document.getElementById("modal");
  const menu = document.getElementById("statusMenu");
  if (event.target == modal) {
    fecharModal();
  }
  if (menu && !menu.contains(event.target)) {
    esconderMenuStatus();
  }
};

$(document).ready(function () {
  $("#cargoSelect").select2({
    placeholder: "Selecione os cargos",
    allowClear: true,
    dropdownParent: $("#modal"),
  });

  $("#funcaoSelect").select2({
    placeholder: "Selecione as funcoes",
    allowClear: true,
    dropdownParent: $("#modal"),
  });

  $("#tipoFinalizacao").select2({
    placeholder: "Selecione os tipos",
    allowClear: true,
    closeOnSelect: false,
    dropdownParent: $("#modal"),
  });

  $("#funcaoSelect").on("change", function () {
    atualizarNivelFinalizacao();
    atualizarAtuacoesFuncoes();
  });

  $(document).on("change", 'input[name^="tipo_atuacao"]', function () {
    const match = this.name.match(/\[(\d+)\]/);
    if (match) {
      atuacoesFuncoes[match[1]] = this.value;
    }
  });

  $("#btnAdicionar").on("click", function () {
    abrirModalNovo();
  });

  $("#btnFecharModal, #btnCancelar").on("click", function () {
    fecharModal();
  });

  $("#btnToggleStatus").on("click", function () {
    const idusuario = $(this).attr("data-idusuario");
    const ativo = $(this).attr("data-ativo") === "1" ? 0 : 1;
    toggleStatus(idusuario, ativo, true);
  });

  $(document).on("scroll", function () {
    esconderMenuStatus();
  });

  $(document).on("click", function (event) {
    const menu = document.getElementById("statusMenu");
    if (menu && !menu.contains(event.target)) {
      esconderMenuStatus();
    }
  });
});

$("#form").on("submit", function (e) {
  e.preventDefault();

  const formData = {
    action: $("#action").val(),
    idusuario: $("#idusuario").val(),
    idcolaborador: $("#idcolaborador").val(),
    nome_colaborador: $("#nome_colaborador").val(),
    nome_usuario: $("#nome_usuario").val(),
    login: $("#login").val(),
    senha: $("#senha").val(),
    nivel_acesso: $("#nivel_acesso").val(),
    cargos: $("#cargoSelect").val(),
    funcoes: $("#funcaoSelect").val(),
    nivel_finalizacao: $("#nivelFinalizacao").val(),
    tipo_finalizacao: $("#tipoFinalizacao").val() || [],
    nivel_arquitetura: $("#nivelArquitetura").val(),
    nivel_animacao: $("#nivelAnimacao").val(),
    tipo_atuacao: Object.fromEntries(
      $("#funcaoSelect option:selected")
        .toArray()
        .map((option) => {
          const id = String(option.value);
          const selecionada = document.querySelector(
            `input[name="tipo_atuacao[${id}]"]:checked`,
          );
          return [id, selecionada?.value || "SECUNDARIA"];
        }),
    ),
    elegivel_capacidade: document.getElementById("elegivelCapacidade").checked
      ? 1
      : 0,
  };

  if (
    document.getElementById("nivelFinalizacao").required &&
    !formData.nivel_finalizacao
  ) {
    showToast("Selecione o nivel de finalizacao.", "error");
    return;
  }
  if (
    document.getElementById("tipoFinalizacao").required &&
    !formData.tipo_finalizacao.length
  ) {
    showToast("Selecione o tipo de finalizacao.", "error");
    return;
  }
  if (
    document.getElementById("nivelArquitetura").required &&
    !formData.nivel_arquitetura
  ) {
    showToast("Selecione o nivel de Arquitetura.", "error");
    return;
  }
  if (
    document.getElementById("nivelAnimacao").required &&
    !formData.nivel_animacao
  ) {
    showToast("Selecione o nivel de Animacao.", "error");
    return;
  }

  $.ajax({
    type: "POST",
    url: "salvar_colaborador.php",
    data: formData,
    dataType: "json",
    success: function (response) {
      if (response.success) {
        showToast(response.message || "Salvo com sucesso!", "success");
        fecharModal();
        carregarUsuarios();
      } else {
        showToast(response.message || "Erro ao salvar.", "error");
      }
    },
    error: function () {
      showToast("Erro ao salvar.", "error");
    },
  });
});

$("#btnExcluir").on("click", function () {
  const idusuario = $("#idusuario").val();
  const idcolaborador = $("#idcolaborador").val();

  if (!idusuario || !idcolaborador) {
    showToast("Selecione um colaborador válido.", "error");
    return;
  }

  if (!confirm("Tem certeza que deseja excluir este colaborador?")) {
    return;
  }

  $.ajax({
    type: "POST",
    url: "salvar_colaborador.php",
    data: { action: "delete", idusuario, idcolaborador },
    dataType: "json",
    success: function (response) {
      if (response.success) {
        showToast(response.message || "Colaborador excluído.", "success");
        fecharModal();
        carregarUsuarios();
      } else {
        showToast(response.message || "Erro ao excluir.", "error");
      }
    },
    error: function () {
      showToast("Erro ao excluir.", "error");
    },
  });
});
