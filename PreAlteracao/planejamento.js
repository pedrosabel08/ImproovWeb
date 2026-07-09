(function () {
  "use strict";

  const BASE = "./";
  const loteId = Number(document.body.dataset.loteId || 0);
  const refs = {
    title: document.getElementById("planTitle"),
    subtitle: document.getElementById("planSubtitle"),
    statusBar: document.getElementById("planStatusBar"),
    imageCount: document.getElementById("imageCount"),
    imageList: document.getElementById("imageList"),
    canvas: document.getElementById("dependencyCanvas"),
    propertyTitle: document.getElementById("propertyTitle"),
    propertyPanel: document.getElementById("propertyPanel"),
    validationPanel: document.getElementById("validationPanel"),
    depForm: document.getElementById("dependencyForm"),
    depOrigin: document.getElementById("depOrigin"),
    depTarget: document.getElementById("depTarget"),
    depCondition: document.getElementById("depCondition"),
    depNote: document.getElementById("depNote"),
    btnSave: document.getElementById("btnSave"),
    btnPublish: document.getElementById("btnPublish"),
    btnValidate: document.getElementById("btnValidate"),
    btnAutoLayout: document.getElementById("btnAutoLayout"),
    btnAutoGenerate: document.getElementById("btnAutoGenerate"),
    btnAddGroup: document.getElementById("btnAddGroup"),
    btnAddGate: document.getElementById("btnAddGate"),
    btnFit: document.getElementById("btnFit"),
  };

  const state = {
    lote: null,
    diagram: null,
    itemsSource: [],
    groups: [],
    items: [],
    gates: [],
    dependencies: [],
    colaboradores: [],
    selected: null,
    cy: null,
    dirty: false,
    lastValidation: null,
  };

  function esc(value) {
    return String(value ?? "").replace(
      /[&<>"']/g,
      (ch) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#039;",
        })[ch],
    );
  }

  function toast(message, color = "#0f172a") {
    if (window.Toastify) {
      Toastify({
        text: message,
        duration: 3000,
        gravity: "top",
        position: "right",
        style: { background: color, borderRadius: "8px" },
      }).showToast();
      return;
    }
    console.log(message);
  }

  function markDirty() {
    state.dirty = true;
    renderStatus();
  }

  function sourceByRef(ref) {
    const item = state.items.find((row) => row.ref === ref);
    if (!item) return null;
    return (
      state.itemsSource.find(
        (row) => Number(row.pre_alt_item_id) === Number(item.pre_alt_item_id),
      ) || null
    );
  }

  function groupByRef(ref) {
    return state.groups.find((row) => row.ref === ref) || null;
  }

  function gateByRef(ref) {
    return state.gates.find((row) => row.ref === ref) || null;
  }

  function labelForNode(type, ref) {
    if (type === "GRUPO") return groupByRef(ref)?.name || ref;
    if (type === "GATE") return gateByRef(ref)?.title || ref;
    return sourceByRef(ref)?.nome || ref;
  }

  function typeFromRef(ref) {
    if (ref.startsWith("group-")) return "GRUPO";
    if (ref.startsWith("gate-")) return "GATE";
    return "ITEM";
  }

  function collectNodeOptions() {
    return [
      ...state.groups.map((row) => ({
        type: "GRUPO",
        ref: row.ref,
        label: `Grupo: ${row.name}`,
      })),
      ...state.gates.map((row) => ({
        type: "GATE",
        ref: row.ref,
        label: `Gate: ${row.title}`,
      })),
      ...state.items.map((row) => ({
        type: "ITEM",
        ref: row.ref,
        label: `Imagem: ${sourceByRef(row.ref)?.nome || row.ref}`,
      })),
    ];
  }

  function collaboratorOptions(selected) {
    const options = ['<option value="">Herdar/sem responsável</option>'];
    state.colaboradores.forEach((col) => {
      const id = Number(col.idcolaborador);
      options.push(
        `<option value="${id}" ${Number(selected || 0) === id ? "selected" : ""}>${esc(col.nome_colaborador)}</option>`,
      );
    });
    return options.join("");
  }

  function buildPayload() {
    return {
      lote_id: loteId,
      lote: state.lote,
      diagram: state.diagram,
      items_source: state.itemsSource,
      groups: state.groups,
      items: state.items,
      gates: state.gates,
      dependencies: state.dependencies,
    };
  }

  async function requestJson(url, options = {}) {
    const response = await fetch(url, options);
    const json = await response.json().catch(() => ({}));
    if (!response.ok || !json.success) {
      throw new Error(json.error || json.message || "Erro na requisição.");
    }
    return json;
  }

  function applyGraph(data) {
    state.lote = data.lote || data.graph?.lote || {};
    state.diagram = data.diagram || data.graph?.diagram || {};
    state.itemsSource = data.items_source || data.graph?.items_source || [];
    state.groups = data.groups || data.graph?.groups || [];
    state.items = data.items || data.graph?.items || [];
    state.gates = data.gates || data.graph?.gates || [];
    state.dependencies = data.dependencies || data.graph?.dependencies || [];
    state.colaboradores =
      data.colaboradores ||
      data.graph?.colaboradores ||
      state.colaboradores ||
      [];
    state.selected = null;
    state.dirty = false;
    state.lastValidation = data.validation || null;
  }

  function renderAll() {
    renderHeader();
    renderStatus();
    renderImageList();
    renderCanvas();
    renderDependencyOptions();
    renderProperties();
    renderValidation(state.lastValidation);
  }

  function renderHeader() {
    const lote = state.lote || {};
    const diagram = state.diagram || {};
    refs.title.textContent =
      diagram.name || lote.nomenclatura || "Planejamento";
    refs.subtitle.textContent = `${lote.nomenclatura || "Obra"} - ${lote.nome_cliente || "Cliente não informado"} - ${lote.nome_status || "Etapa"}`;
  }

  function renderStatus() {
    const diagram = state.diagram || {};
    const status = diagram.status || "RASCUNHO";
    refs.statusBar.innerHTML = `
      <span class="status-pill status-${esc(status.toLowerCase())}"><i class="fa-solid fa-circle"></i> ${esc(status)}</span>
      <span>${state.groups.length} grupo(s)</span>
      <span>${state.items.length} imagem(ns)</span>
      <span>${state.gates.length} gate(s)</span>
      <span>${state.dependencies.length} dependência(s)</span>
      ${state.dirty ? '<strong class="dirty-dot">Alterações não salvas</strong>' : "<span>Sem alterações pendentes</span>"}
    `;
  }

  function renderImageList() {
    refs.imageCount.textContent = `${state.items.length} imagem${state.items.length === 1 ? "" : "s"}`;
    if (!state.items.length) {
      refs.imageList.innerHTML =
        '<div class="empty-card">Nenhuma imagem com alteração neste lote.</div>';
      return;
    }
    refs.imageList.innerHTML = state.items
      .map((item) => {
        const source = sourceByRef(item.ref) || {};
        const group = groupByRef(item.group_ref || "");
        return `
        <button type="button" class="pa-image-card ${state.selected?.ref === item.ref ? "active" : ""}" data-ref="${esc(item.ref)}">
          <span class="thumb">${source.thumb_url ? `<img src="${esc(source.thumb_url)}" alt="">` : '<i class="fa-regular fa-image"></i>'}</span>
          <span class="info">
            <strong>${esc(source.nome || item.ref)}</strong>
            <small>${esc(group?.name || "Sem grupo")} • N${esc(source.nivel_complexidade || "-")}</small>
          </span>
        </button>
      `;
      })
      .join("");
    refs.imageList.querySelectorAll(".pa-image-card").forEach((button) => {
      button.addEventListener("click", () =>
        selectNode("ITEM", button.dataset.ref),
      );
    });
  }

  function nodeId(ref) {
    return `n:${ref}`;
  }

  function edgeId(dep, idx) {
    return `e:${dep.ref || idx}`;
  }

  function renderCanvas() {
    if (!window.cytoscape) {
      refs.canvas.innerHTML =
        '<div class="canvas-error">Biblioteca Cytoscape não carregada.</div>';
      return;
    }

    const elements = [];
    state.groups.forEach((group) => {
      elements.push({
        group: "nodes",
        data: {
          id: nodeId(group.ref),
          ref: group.ref,
          type: "GRUPO",
          label: group.name,
        },
        position: { x: Number(group.x || 80), y: Number(group.y || 80) },
        classes: "node-group",
      });
    });
    state.items.forEach((item) => {
      const source = sourceByRef(item.ref) || {};
      const data = {
        id: nodeId(item.ref),
        ref: item.ref,
        type: "ITEM",
        label: source.nome || item.ref,
        complexity: source.nivel_complexidade || "",
      };
      if (item.group_ref) data.parent = nodeId(item.group_ref);
      elements.push({
        group: "nodes",
        data,
        position: { x: Number(item.x || 140), y: Number(item.y || 180) },
        classes: "node-item",
      });
    });
    state.gates.forEach((gate) => {
      elements.push({
        group: "nodes",
        data: {
          id: nodeId(gate.ref),
          ref: gate.ref,
          type: "GATE",
          label: gate.title,
          gateType: gate.gate_type,
        },
        position: { x: Number(gate.x || 220), y: Number(gate.y || 220) },
        classes: "node-gate",
      });
    });
    state.dependencies.forEach((dep, idx) => {
      elements.push({
        group: "edges",
        data: {
          id: edgeId(dep, idx),
          ref: dep.ref || `dep-new-${idx}`,
          source: nodeId(dep.origin_ref),
          target: nodeId(dep.target_ref),
          label: dep.condition || "APROVADA",
        },
        classes: "dep-edge",
      });
    });

    if (state.cy) {
      state.cy.destroy();
    }
    state.cy = cytoscape({
      container: refs.canvas,
      elements,
      minZoom: 0.25,
      maxZoom: 2.5,
      wheelSensitivity: 0.15,
      style: [
        {
          selector: "node",
          style: {
            label: "data(label)",
            "font-size": 11,
            "font-family": "Inter, Arial, sans-serif",
            color: "#0f172a",
            "text-wrap": "wrap",
            "text-max-width": 150,
            "text-valign": "center",
            "text-halign": "center",
            "border-width": 1,
            "border-color": "#cbd5e1",
            "background-color": "#ffffff",
            width: 170,
            height: 64,
          },
        },
        {
          selector: ".node-group",
          style: {
            "background-color": "#eef2ff",
            "background-opacity": 0.35,
            "border-color": "#818cf8",
            "border-width": 2,
            shape: "round-rectangle",
            "text-valign": "top",
            "text-margin-y": -8,
            "font-weight": 700,
            padding: 24,
          },
        },
        {
          selector: ".node-item",
          style: {
            shape: "round-rectangle",
            "background-color": "#ffffff",
            "border-color": "#94a3b8",
            "font-weight": 600,
          },
        },
        {
          selector: ".node-gate",
          style: {
            shape: "diamond",
            "background-color": "#fef3c7",
            "border-color": "#f59e0b",
            width: 120,
            height: 72,
          },
        },
        {
          selector: "edge",
          style: {
            label: "data(label)",
            "font-size": 9,
            "curve-style": "bezier",
            "target-arrow-shape": "triangle",
            "target-arrow-color": "#64748b",
            "line-color": "#64748b",
            "text-background-color": "#ffffff",
            "text-background-opacity": 0.9,
            "text-background-padding": 2,
            width: 2,
          },
        },
        {
          selector: ":selected",
          style: {
            "border-color": "#0f766e",
            "border-width": 3,
            "line-color": "#0f766e",
            "target-arrow-color": "#0f766e",
          },
        },
      ],
      layout: { name: "preset", fit: true, padding: 40 },
    });

    state.cy.on("tap", "node", (event) => {
      const data = event.target.data();
      selectNode(data.type, data.ref, false);
    });
    state.cy.on("tap", "edge", (event) => {
      const data = event.target.data();
      const dep = state.dependencies.find(
        (row, idx) => edgeId(row, idx) === data.id || row.ref === data.ref,
      );
      if (dep) {
        state.selected = { kind: "DEPENDENCY", ref: dep.ref };
        renderProperties();
      }
    });
    state.cy.on("tap", (event) => {
      if (event.target === state.cy) {
        state.selected = null;
        renderProperties();
      }
    });
    state.cy.on("dragfree", "node", (event) => {
      const data = event.target.data();
      const pos = event.target.position();
      updateNodePosition(data.type, data.ref, pos.x, pos.y);
      markDirty();
    });
  }

  function updateNodePosition(type, ref, x, y) {
    const bucket =
      type === "GRUPO"
        ? state.groups
        : type === "GATE"
          ? state.gates
          : state.items;
    const row = bucket.find((item) => item.ref === ref);
    if (!row) return;
    row.x = Math.round(x);
    row.y = Math.round(y);
  }

  function selectNode(type, ref, syncCy = true) {
    state.selected = { kind: type, ref };
    if (syncCy && state.cy) {
      state.cy.elements().unselect();
      const ele = state.cy.getElementById(nodeId(ref));
      if (ele) ele.select();
    }
    renderImageList();
    renderProperties();
  }

  function renderDependencyOptions() {
    const options = collectNodeOptions()
      .map(
        (row) =>
          `<option value="${esc(row.type)}|${esc(row.ref)}">${esc(row.label)}</option>`,
      )
      .join("");
    refs.depOrigin.innerHTML = options;
    refs.depTarget.innerHTML = options;
  }

  function renderProperties() {
    if (!state.selected) {
      refs.propertyTitle.textContent = "Nada selecionado";
      refs.propertyPanel.innerHTML = `
        <div class="empty-card">
          Selecione uma imagem, grupo, gate ou conexão no canvas para editar as propriedades.
        </div>
      `;
      return;
    }

    if (state.selected.kind === "GRUPO")
      return renderGroupProperties(groupByRef(state.selected.ref));
    if (state.selected.kind === "ITEM")
      return renderItemProperties(
        state.items.find((row) => row.ref === state.selected.ref),
      );
    if (state.selected.kind === "GATE")
      return renderGateProperties(gateByRef(state.selected.ref));
    return renderDependencyProperties(
      state.dependencies.find((row) => row.ref === state.selected.ref),
    );
  }

  function renderGroupProperties(group) {
    if (!group) return;
    refs.propertyTitle.textContent = `Grupo: ${group.name}`;
    refs.propertyPanel.innerHTML = `
      <label><span>Nome</span><input id="propGroupName" value="${esc(group.name)}"></label>
      <label><span>Responsável padrão</span><select id="propGroupResp">${collaboratorOptions(group.responsavel_id)}</select></label>
      <label><span>Ordem</span><input id="propGroupOrder" type="number" min="0" value="${esc(group.order || 0)}"></label>
      <button type="button" class="btn btn-danger" id="propDeleteGroup"><i class="fa-solid fa-trash"></i> Remover grupo</button>
    `;
    document
      .getElementById("propGroupName")
      .addEventListener("input", (event) => {
        group.name = event.target.value;
        markDirty();
        renderCanvas();
        renderDependencyOptions();
      });
    document
      .getElementById("propGroupResp")
      .addEventListener("change", (event) => {
        group.responsavel_id = event.target.value
          ? Number(event.target.value)
          : null;
        markDirty();
      });
    document
      .getElementById("propGroupOrder")
      .addEventListener("input", (event) => {
        group.order = Number(event.target.value || 0);
        markDirty();
      });
    document
      .getElementById("propDeleteGroup")
      .addEventListener("click", () => deleteGroup(group.ref));
  }

  function renderItemProperties(item) {
    if (!item) return;
    const source = sourceByRef(item.ref) || {};
    refs.propertyTitle.textContent = source.nome || "Imagem";
    refs.propertyPanel.innerHTML = `
      <div class="prop-preview">${source.thumb_url ? `<img src="${esc(source.thumb_url)}" alt="">` : '<i class="fa-regular fa-image"></i>'}</div>
      <label><span>Grupo</span><select id="propItemGroup">
        <option value="">Sem grupo</option>
        ${state.groups.map((group) => `<option value="${esc(group.ref)}" ${item.group_ref === group.ref ? "selected" : ""}>${esc(group.name)}</option>`).join("")}
      </select></label>
      <label><span>Responsável da imagem</span><select id="propItemResp">${collaboratorOptions(item.responsavel_id)}</select></label>
      <label><span>Ordem</span><input id="propItemOrder" type="number" min="0" value="${esc(item.order || 0)}"></label>
      <div class="readonly-grid">
        <span>ID imagem<strong>${esc(source.imagem_id || "-")}</strong></span>
        <span>Complexidade<strong>N${esc(source.nivel_complexidade || "-")}</strong></span>
        <span>Tipo<strong>${esc(source.tipo_imagem || "-")}</strong></span>
      </div>
      <div class="prop-note"><span>Orientação da triagem</span><p>${esc(source.acao || "Sem observação.")}</p></div>
    `;
    document
      .getElementById("propItemGroup")
      .addEventListener("change", (event) => {
        item.group_ref = event.target.value || null;
        item.group_id = null;
        markDirty();
        renderImageList();
        renderCanvas();
      });
    document
      .getElementById("propItemResp")
      .addEventListener("change", (event) => {
        item.responsavel_id = event.target.value
          ? Number(event.target.value)
          : null;
        markDirty();
      });
    document
      .getElementById("propItemOrder")
      .addEventListener("input", (event) => {
        item.order = Number(event.target.value || 0);
        markDirty();
      });
  }

  function renderGateProperties(gate) {
    if (!gate) return;
    refs.propertyTitle.textContent = `Gate: ${gate.title}`;
    refs.propertyPanel.innerHTML = `
      <label><span>Título</span><input id="propGateTitle" value="${esc(gate.title)}"></label>
      <label><span>Tipo</span><select id="propGateType">
        <option value="APROVACAO" ${gate.gate_type === "APROVACAO" ? "selected" : ""}>Aprovação</option>
        <option value="FINALIZACAO" ${gate.gate_type === "FINALIZACAO" ? "selected" : ""}>Finalização</option>
        <option value="MANUAL" ${gate.gate_type === "MANUAL" ? "selected" : ""}>Manual</option>
      </select></label>
      <button type="button" class="btn btn-danger" id="propDeleteGate"><i class="fa-solid fa-trash"></i> Remover gate</button>
    `;
    document
      .getElementById("propGateTitle")
      .addEventListener("input", (event) => {
        gate.title = event.target.value;
        markDirty();
        renderCanvas();
        renderDependencyOptions();
      });
    document
      .getElementById("propGateType")
      .addEventListener("change", (event) => {
        gate.gate_type = event.target.value;
        markDirty();
      });
    document
      .getElementById("propDeleteGate")
      .addEventListener("click", () => deleteGate(gate.ref));
  }

  function renderDependencyProperties(dep) {
    if (!dep) return;
    refs.propertyTitle.textContent = "Dependência";
    refs.propertyPanel.innerHTML = `
      <div class="readonly-grid dep-readonly">
        <span>Origem<strong>${esc(labelForNode(dep.origin_type, dep.origin_ref))}</strong></span>
        <span>Destino<strong>${esc(labelForNode(dep.target_type, dep.target_ref))}</strong></span>
      </div>
      <label><span>Condição</span><select id="propDepCondition">
        <option value="APROVADA" ${dep.condition === "APROVADA" ? "selected" : ""}>Aprovada</option>
        <option value="FINALIZADA" ${dep.condition === "FINALIZADA" ? "selected" : ""}>Finalizada</option>
      </select></label>
      <label><span>Observação</span><textarea id="propDepNote" rows="3">${esc(dep.note || "")}</textarea></label>
      <button type="button" class="btn btn-danger" id="propDeleteDep"><i class="fa-solid fa-trash"></i> Remover dependência</button>
    `;
    document
      .getElementById("propDepCondition")
      .addEventListener("change", (event) => {
        dep.condition = event.target.value;
        markDirty();
        renderCanvas();
      });
    document
      .getElementById("propDepNote")
      .addEventListener("input", (event) => {
        dep.note = event.target.value;
        markDirty();
      });
    document
      .getElementById("propDeleteDep")
      .addEventListener("click", () => deleteDependency(dep.ref));
  }

  function deleteGroup(ref) {
    state.items.forEach((item) => {
      if (item.group_ref === ref) item.group_ref = null;
    });
    state.dependencies = state.dependencies.filter(
      (dep) => dep.origin_ref !== ref && dep.target_ref !== ref,
    );
    state.groups = state.groups.filter((group) => group.ref !== ref);
    state.selected = null;
    markDirty();
    renderAll();
  }

  function deleteGate(ref) {
    state.dependencies = state.dependencies.filter(
      (dep) => dep.origin_ref !== ref && dep.target_ref !== ref,
    );
    state.gates = state.gates.filter((gate) => gate.ref !== ref);
    state.selected = null;
    markDirty();
    renderAll();
  }

  function deleteDependency(ref) {
    state.dependencies = state.dependencies.filter((dep) => dep.ref !== ref);
    state.selected = null;
    markDirty();
    renderAll();
  }

  function createGroup() {
    const name = window.prompt("Nome do grupo");
    if (!name || !name.trim()) return;
    const ref = `group-new-${Date.now()}`;
    state.groups.push({
      id: null,
      ref,
      name: name.trim(),
      responsavel_id: state.lote?.responsavel_id || null,
      order: state.groups.length + 1,
      x: 120 + state.groups.length * 240,
      y: 120,
      width: 240,
      height: 160,
      visual: {},
    });
    markDirty();
    renderAll();
    selectNode("GRUPO", ref);
  }

  function createGate() {
    const title = window.prompt("Título do gate", "Aprovação do grupo");
    if (!title || !title.trim()) return;
    const ref = `gate-new-${Date.now()}`;
    state.gates.push({
      id: null,
      ref,
      title: title.trim(),
      gate_type: "APROVACAO",
      x: 260,
      y: 260,
      width: 120,
      height: 72,
      visual: {},
    });
    markDirty();
    renderAll();
    selectNode("GATE", ref);
  }

  function addDependency(originValue, targetValue, condition, note) {
    const [originType, originRef] = originValue.split("|");
    const [targetType, targetRef] = targetValue.split("|");
    if (!originRef || !targetRef || originRef === targetRef) {
      toast("Escolha origem e destino diferentes.", "#f59e0b");
      return;
    }
    const exists = state.dependencies.some(
      (dep) =>
        dep.origin_ref === originRef &&
        dep.target_ref === targetRef &&
        dep.condition === condition,
    );
    if (exists) {
      toast("Esta dependência já existe.", "#f59e0b");
      return;
    }
    state.dependencies.push({
      id: null,
      ref: `dep-new-${Date.now()}`,
      origin_type: originType,
      origin_ref: originRef,
      target_type: targetType,
      target_ref: targetRef,
      condition,
      aggregation: "ALL",
      note: note || "",
    });
    refs.depNote.value = "";
    markDirty();
    renderCanvas();
    renderProperties();
  }

  function autoLayout() {
    if (!state.cy) return;
    const name =
      state.cytoscapeDagreReady || (window.dagre ? "dagre" : "breadthfirst");
    try {
      const layout = state.cy.layout({
        name,
        rankDir: "LR",
        nodeSep: 60,
        rankSep: 120,
        fit: true,
        padding: 50,
        animate: true,
        animationDuration: 250,
      });
      layout.one("layoutstop", () => {
        state.cy.nodes().forEach((node) => {
          const data = node.data();
          const pos = node.position();
          updateNodePosition(data.type, data.ref, pos.x, pos.y);
        });
        markDirty();
      });
      layout.run();
    } catch (err) {
      state.cy
        .layout({
          name: "breadthfirst",
          directed: true,
          fit: true,
          padding: 50,
        })
        .run();
    }
  }

  function regenerateBase() {
    if (
      !window.confirm(
        "Gerar a base novamente vai remover grupos, gates e dependências ainda não salvos. Continuar?",
      )
    ) {
      return;
    }
    const groups = new Map();
    state.items = state.itemsSource.map((source, idx) => {
      const groupName = source.tipo_imagem || "Sem grupo";
      const key = groupName.toLowerCase();
      if (!groups.has(key)) {
        groups.set(key, {
          id: null,
          ref: `group-new-${key.replace(/[^a-z0-9]+/g, "-") || Date.now()}`,
          name: groupName,
          responsavel_id: state.lote?.responsavel_id || null,
          order: groups.size + 1,
          x: 100 + groups.size * 260,
          y: 100,
          width: 240,
          height: 160,
          visual: {},
        });
      }
      const group = groups.get(key);
      return {
        id: null,
        ref: `item-${source.pre_alt_item_id}`,
        pre_alt_item_id: source.pre_alt_item_id,
        group_ref: group.ref,
        group_id: null,
        responsavel_id: source.item_responsavel_id || null,
        order: idx + 1,
        x: 140 + (idx % 4) * 220,
        y: 180 + Math.floor(idx / 4) * 110,
        width: 180,
        height: 76,
        visual: {},
      };
    });
    state.groups = Array.from(groups.values());
    state.gates = [];
    state.dependencies = [];
    state.selected = null;
    markDirty();
    renderAll();
  }

  async function validateGraph(showToast = true) {
    const json = await requestJson(BASE + "validate_planejamento.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(buildPayload()),
    });
    state.lastValidation = json.validation;
    renderValidation(json.validation);
    if (showToast) {
      toast(
        json.validation.valid ? "Diagrama válido." : "Diagrama possui erros.",
        json.validation.valid ? "#16a34a" : "#dc2626",
      );
    }
    return json.validation;
  }

  function renderValidation(validation) {
    if (!validation) {
      refs.validationPanel.innerHTML =
        '<div class="empty-card">A validação estrutural ainda não foi executada.</div>';
      return;
    }
    const block = (title, rows, cls) =>
      rows?.length
        ? `<div class="validation-block ${cls}"><strong>${esc(title)}</strong>${rows.map((row) => `<span>${esc(row)}</span>`).join("")}</div>`
        : "";
    refs.validationPanel.innerHTML = `
      ${block("Erros", validation.errors || [], "errors")}
      ${block("Avisos", validation.warnings || [], "warnings")}
      ${block("Informações", validation.infos || [], "infos")}
      ${validation.valid ? '<div class="validation-block ok"><strong>Estrutura válida</strong><span>O diagrama pode ser publicado.</span></div>' : ""}
    `;
  }

  async function saveGraph() {
    refs.btnSave.disabled = true;
    try {
      const json = await requestJson(BASE + "save_planejamento.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(buildPayload()),
      });
      applyGraph(json.graph);
      state.lastValidation = json.validation;
      renderAll();
      toast("Planejamento salvo.", "#16a34a");
    } catch (err) {
      toast(err.message, "#dc2626");
    } finally {
      refs.btnSave.disabled = false;
    }
  }

  async function publishGraph() {
    if (
      state.dirty &&
      !window.confirm(
        "Existem alterações não salvas. Publicar a última versão salva mesmo assim?",
      )
    ) {
      return;
    }
    refs.btnPublish.disabled = true;
    try {
      const json = await requestJson(BASE + "publish_planejamento.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ lote_id: loteId }),
      });
      if (!json.published && json.validation) {
        state.lastValidation = json.validation;
        renderValidation(json.validation);
        toast("Corrija os erros antes de publicar.", "#dc2626");
        return;
      }
      applyGraph(json.graph);
      state.lastValidation = json.validation;
      renderAll();
      toast("Planejamento publicado.", "#16a34a");
    } catch (err) {
      toast(err.message, "#dc2626");
    } finally {
      refs.btnPublish.disabled = false;
    }
  }

  async function load() {
    if (!loteId) {
      refs.canvas.innerHTML =
        '<div class="canvas-error">lote_id não informado.</div>';
      return;
    }
    try {
      const data = await requestJson(
        BASE + `get_planejamento.php?lote_id=${encodeURIComponent(loteId)}`,
      );
      applyGraph(data);
      renderAll();
      if (state.cy) state.cy.fit(undefined, 40);
    } catch (err) {
      refs.canvas.innerHTML = `<div class="canvas-error">${esc(err.message)}</div>`;
      toast(err.message, "#dc2626");
    }
  }

  refs.btnAddGroup.addEventListener("click", createGroup);
  refs.btnAddGate.addEventListener("click", createGate);
  refs.btnAutoLayout.addEventListener("click", autoLayout);
  refs.btnAutoGenerate.addEventListener("click", regenerateBase);
  refs.btnSave.addEventListener("click", saveGraph);
  refs.btnPublish.addEventListener("click", publishGraph);
  refs.btnValidate.addEventListener("click", () =>
    validateGraph(true).catch((err) => toast(err.message, "#dc2626")),
  );
  refs.btnFit.addEventListener("click", () => state.cy?.fit(undefined, 40));
  refs.depForm.addEventListener("submit", (event) => {
    event.preventDefault();
    addDependency(
      refs.depOrigin.value,
      refs.depTarget.value,
      refs.depCondition.value,
      refs.depNote.value,
    );
  });

  if (window.cytoscape && window.cytoscapeDagre && window.dagre) {
    window.cytoscape.use(window.cytoscapeDagre);
    state.cytoscapeDagreReady = "dagre";
  }

  load();
})();
