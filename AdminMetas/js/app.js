/**
 * AdminMetas/js/app.js
 *
 * Módulo de administração de metas por colaborador.
 * - Carrega dados via AJAX
 * - Renderiza accordions por função
 * - Rastreia alterações inline
 * - Envia apenas metas alteradas/novas ao salvar
 */

(function () {
  "use strict";

  // ── State ─────────────────────────────────────────────────────────────────

  const state = {
    mes: window.APP_MES,
    ano: window.APP_ANO,
    data: null,
    openFuncaoId: null,
    changes: new Map(), // 'funcaoId:colabId' → { meta_tarefas: number }
  };

  // ── Init ──────────────────────────────────────────────────────────────────

  function init() {
    bindFilters();
    bindSaveButton();
    carregarDados();
  }

  // ── Events ────────────────────────────────────────────────────────────────

  function bindFilters() {
    document
      .getElementById("btnAplicar")
      .addEventListener("click", aplicarFiltro);
    document.getElementById("selMes").addEventListener("keydown", (e) => {
      if (e.key === "Enter") aplicarFiltro();
    });
    document.getElementById("selAno").addEventListener("keydown", (e) => {
      if (e.key === "Enter") aplicarFiltro();
    });
  }

  function aplicarFiltro() {
    const mes = parseInt(document.getElementById("selMes").value, 10);
    const ano = parseInt(document.getElementById("selAno").value, 10);

    if (hasPendingChanges()) {
      Swal.fire({
        title: "Alterações pendentes",
        text: "Ao mudar o período, as alterações não salvas serão descartadas. Continuar?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Continuar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#4f80e1",
      }).then((result) => {
        if (result.isConfirmed) {
          mudarPeriodo(mes, ano);
        }
      });
    } else {
      mudarPeriodo(mes, ano);
    }
  }

  function mudarPeriodo(mes, ano) {
    state.mes = mes;
    state.ano = ano;
    state.changes.clear();
    state.openFuncaoId = null;
    atualizarBotaoSalvar();
    carregarDados();
  }

  function bindSaveButton() {
    document.getElementById("btnSalvar").addEventListener("click", salvarMetas);
  }

  // ── Data Loading ──────────────────────────────────────────────────────────

  function carregarDados() {
    renderSkeleton();

    fetch(`backend/carregar_dados.php?mes=${state.mes}&ano=${state.ano}`)
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then((data) => {
        if (!data.success) throw new Error(data.error || "Erro desconhecido");
        state.data = data;
        renderFuncoes(data);
      })
      .catch((err) => {
        document.getElementById("listaAcordoes").innerHTML = `
          <div class="alert-box danger">
            <i class="fa-solid fa-circle-xmark"></i>
            <div>Erro ao carregar dados: ${escHtml(err.message)}</div>
          </div>`;
      });
  }

  // ── Skeleton ──────────────────────────────────────────────────────────────

  function renderSkeleton() {
    const list = document.getElementById("listaAcordoes");
    let html = "";
    for (let i = 0; i < 7; i++) {
      html += `<div class="skeleton-card" style="height:62px;margin-bottom:0"></div>`;
    }
    list.innerHTML = html;
    document.getElementById("resultsCount").textContent = "…";
  }

  // ── Render Functions ──────────────────────────────────────────────────────

  function renderFuncoes(data) {
    const list = document.getElementById("listaAcordoes");
    list.innerHTML = "";

    const totalColabs = data.funcoes.reduce(
      (s, f) => s + f.colaboradores.length,
      0,
    );
    document.getElementById("resultsCount").textContent = totalColabs;

    data.funcoes.forEach((funcao) => {
      list.appendChild(criarAcordao(funcao));
    });

    // Reabre o accordion que estava aberto antes do reload
    if (state.openFuncaoId !== null) {
      const body = document.getElementById(`body-${state.openFuncaoId}`);
      const header = document.querySelector(
        `[data-funcao-id="${state.openFuncaoId}"] .accordion-header`,
      );
      if (body && header) {
        abrirBody(body, header, state.openFuncaoId);
      }
    }
  }

  function criarAcordao(funcao) {
    const wrapper = document.createElement("div");
    wrapper.className = "accordion-funcao";
    wrapper.dataset.funcaoId = funcao.funcao_id;

    const header = document.createElement("div");
    header.className = "accordion-header";
    header.innerHTML = buildHeaderHTML(funcao);
    header.addEventListener("click", () => toggleAcordao(funcao.funcao_id));

    const body = document.createElement("div");
    body.className = "accordion-body";
    body.id = `body-${funcao.funcao_id}`;
    body.innerHTML = buildBodyHTML(funcao);

    wrapper.appendChild(header);
    wrapper.appendChild(body);

    return wrapper;
  }

  function buildHeaderHTML(funcao) {
    const count = funcao.colaboradores.length;
    const plural = count !== 1 ? "es" : "";

    const equipeBrokenRecord =
      funcao.recorde_equipe > 0 &&
      funcao.total_parcial >= funcao.recorde_equipe;
    const recordeHtml = equipeBrokenRecord
      ? `<span class="ind ind-recorde sm" title="Recorde da equipe atingido!"><i class="fa-solid fa-trophy"></i></span>`
      : "";

    return `
      <div class="accordion-header-left">
        <span class="expand-icon"><i class="fa-solid fa-chevron-right"></i></span>
        <span class="funcao-dot" style="background:${funcao.cor}"></span>
        <span class="funcao-nome">${escHtml(funcao.nome_funcao)}</span>
        <span class="funcao-count">${count} colaborador${plural}</span>
      </div>
      <div class="accordion-header-stats">
        <div class="stat-pill">
          <span class="stat-label">Parcial</span>
          <span class="stat-val">${funcao.total_parcial}${recordeHtml}</span>
        </div>
        <div class="stat-pill">
          <span class="stat-label">Mês Ant.</span>
          <span class="stat-val">${funcao.total_anterior}</span>
        </div>
        <div class="stat-pill">
          <span class="stat-label">Recorde</span>
          <span class="stat-val">${funcao.recorde_equipe}</span>
        </div>
      </div>`;
  }

  function buildBodyHTML(funcao) {
    if (funcao.colaboradores.length === 0) {
      return `<div class="empty-state">Nenhum colaborador encontrado para esta função nos últimos 18 meses.</div>`;
    }

    let rows = "";

    funcao.colaboradores.forEach((colab) => {
      const key = `${funcao.funcao_id}:${colab.colaborador_id}`;

      // Valor atual do input (considera mudanças pendentes)
      let metaVal = "";
      if (state.changes.has(key)) {
        const v = state.changes.get(key).meta_tarefas;
        metaVal = v !== null ? v : "";
      } else {
        metaVal = colab.meta !== null ? colab.meta : "";
      }

      const originalVal = colab.meta !== null ? String(colab.meta) : "";
      const isDirty = state.changes.has(key) ? "dirty" : "";

      const indicator = buildIndicator(
        colab.parcial,
        colab.meta,
        colab.recorde,
      );
      const recStr = colab.recorde > 0 ? colab.recorde : "—";

      rows += `
        <tr>
          <td class="colab-nome">${escHtml(colab.nome)}</td>
          <td class="col-center">${colab.mes_anterior}</td>
          <td class="col-center">${recStr}</td>
          <td class="col-center">
            <span>${colab.parcial}</span>
            ${indicator}
          </td>
          <td class="col-center">
            <input
              type="number"
              class="meta-input ${isDirty}"
              min="0"
              placeholder="—"
              value="${metaVal}"
              data-original="${escHtml(originalVal)}"
              data-funcao-id="${funcao.funcao_id}"
              data-colab-id="${colab.colaborador_id}"
            >
          </td>
        </tr>`;
    });

    return `
      <div class="accordion-body-inner">
        <table class="colab-table">
          <thead>
            <tr>
              <th>Colaborador</th>
              <th class="col-center">Mês Ant.</th>
              <th class="col-center">Recorde</th>
              <th class="col-center">Parcial</th>
              <th class="col-center">Meta</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
  }

  // ── Indicator ─────────────────────────────────────────────────────────────

  function buildIndicator(parcial, meta, recorde) {
    if (meta === null) return "";

    if (recorde > 0 && parcial >= recorde) {
      return `<span class="ind ind-recorde" title="Recorde individual atingido!"><i class="fa-solid fa-trophy"></i></span>`;
    }
    if (parcial > meta) {
      return `<span class="ind ind-superada" title="Meta superada"><i class="fa-solid fa-arrow-up"></i></span>`;
    }
    if (parcial === meta && meta > 0) {
      return `<span class="ind ind-atingida" title="Meta atingida"><i class="fa-solid fa-check"></i></span>`;
    }
    // parcial < meta
    return `<span class="ind ind-below" title="Abaixo da meta"><i class="fa-solid fa-arrow-down"></i></span>`;
  }

  // ── Accordion Toggle ──────────────────────────────────────────────────────

  function toggleAcordao(funcaoId) {
    const body = document.getElementById(`body-${funcaoId}`);
    const header = document.querySelector(
      `[data-funcao-id="${funcaoId}"] .accordion-header`,
    );
    if (!body || !header) return;

    const isOpen = body.classList.contains("is-open");

    // Fecha o accordion atualmente aberto
    if (state.openFuncaoId !== null && state.openFuncaoId !== funcaoId) {
      fecharAcordaoAtivo();
    }

    if (isOpen) {
      fecharBody(body, header);
      state.openFuncaoId = null;
    } else {
      abrirBody(body, header, funcaoId);
    }
  }

  function abrirBody(body, header, funcaoId) {
    body.classList.add("is-open");
    body.style.display = "block";
    header.classList.add("open");
    state.openFuncaoId = funcaoId;
    bindBodyInputs(body);
  }

  function fecharBody(body, header) {
    body.classList.remove("is-open");
    body.style.display = "none";
    header.classList.remove("open");
  }

  function fecharAcordaoAtivo() {
    if (state.openFuncaoId === null) return;
    const prevBody = document.getElementById(`body-${state.openFuncaoId}`);
    const prevHeader = document.querySelector(
      `[data-funcao-id="${state.openFuncaoId}"] .accordion-header`,
    );
    if (prevBody && prevHeader) fecharBody(prevBody, prevHeader);
  }

  // ── Input Binding ─────────────────────────────────────────────────────────

  function bindBodyInputs(bodyEl) {
    bodyEl.querySelectorAll(".meta-input").forEach((input) => {
      if (input.dataset.bound) return;
      input.dataset.bound = "1";
      input.addEventListener("input", () => onMetaChange(input));
    });
  }

  // ── Change Tracking ───────────────────────────────────────────────────────

  function onMetaChange(input) {
    const funcaoId = parseInt(input.dataset.funcaoId, 10);
    const colabId = parseInt(input.dataset.colabId, 10);
    const key = `${funcaoId}:${colabId}`;
    const original = input.dataset.original;
    const newVal = input.value.trim();

    const isSameAsOriginal =
      newVal === original || (newVal === "" && original === "");

    if (isSameAsOriginal) {
      state.changes.delete(key);
      input.classList.remove("dirty");
    } else {
      const parsedVal = newVal === "" ? null : parseInt(newVal, 10);
      state.changes.set(key, {
        funcao_id: funcaoId,
        colaborador_id: colabId,
        meta_tarefas: parsedVal,
      });
      input.classList.add("dirty");
    }

    atualizarBotaoSalvar();
  }

  function hasPendingChanges() {
    return state.changes.size > 0;
  }

  function atualizarBotaoSalvar() {
    const btn = document.getElementById("btnSalvar");
    const badge = document.getElementById("pendingBadge");
    const count = state.changes.size;

    if (count > 0) {
      btn.classList.add("has-changes");
      badge.textContent = count;
      badge.style.display = "inline-flex";
    } else {
      btn.classList.remove("has-changes");
      badge.style.display = "none";
    }
  }

  // ── Save ──────────────────────────────────────────────────────────────────

  function salvarMetas() {
    if (!hasPendingChanges()) {
      showToast("Nenhuma alteração pendente.", "info");
      return;
    }

    // Monta payload com metas válidas
    const metas = [];
    state.changes.forEach((change) => {
      if (change.meta_tarefas === null || change.meta_tarefas < 0) return;
      metas.push({
        colaborador_id: change.colaborador_id,
        funcao_id: change.funcao_id,
        meta_tarefas: change.meta_tarefas,
      });
    });

    if (metas.length === 0) {
      showToast("Nenhuma meta válida para salvar.", "info");
      return;
    }

    const btn = document.getElementById("btnSalvar");
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando…';

    fetch("backend/salvar_metas.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ mes: state.mes, ano: state.ano, metas }),
    })
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then((data) => {
        if (data.success) {
          const parts = [];
          if (data.inserted > 0)
            parts.push(
              `${data.inserted} inserida${data.inserted !== 1 ? "s" : ""}`,
            );
          if (data.updated > 0)
            parts.push(
              `${data.updated} atualizada${data.updated !== 1 ? "s" : ""}`,
            );
          showToast(`Metas salvas! ${parts.join(", ")}.`, "success");

          // Atualiza data-original e remove dirty de todos os inputs salvos
          state.changes.forEach((change, key) => {
            if (change.meta_tarefas === null) return;
            const input = document.querySelector(
              `.meta-input[data-funcao-id="${change.funcao_id}"][data-colab-id="${change.colaborador_id}"]`,
            );
            if (input) {
              input.dataset.original = String(change.meta_tarefas);
              input.classList.remove("dirty");
            }
          });

          state.changes.clear();
          atualizarBotaoSalvar();
        } else {
          showToast(data.error || "Erro ao salvar metas.", "error");
        }
      })
      .catch(() => showToast("Falha de conexão. Tente novamente.", "error"))
      .finally(() => {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> Salvar metas <span id="pendingBadge" class="pending-badge" style="display:none">0</span>`;
        atualizarBotaoSalvar();
      });
  }

  // ── Utils ─────────────────────────────────────────────────────────────────

  function escHtml(str) {
    const d = document.createElement("div");
    d.textContent = String(str);
    return d.innerHTML;
  }

  function showToast(text, type) {
    const colors = { success: "#10b981", error: "#ef4444", info: "#4f80e1" };
    Toastify({
      text,
      duration: 4000,
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

  // ── Bootstrap ─────────────────────────────────────────────────────────────

  document.addEventListener("DOMContentLoaded", init);
})();
