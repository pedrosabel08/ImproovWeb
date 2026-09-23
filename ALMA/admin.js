(() => {
  const app = document.getElementById("almaAdmin");
  if (!app) return;

  const state = {
    versions: [],
    library: null,
    dimensionCode: null,
    itemId: null,
  };
  const esc = (value) =>
    String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  const api = async (action, options = {}) => {
    const method = options.method || "GET";
    const response = await fetch(
      `api.php?${new URLSearchParams({ action, ...(options.params || {}) })}`,
      {
        method,
        headers:
          method === "POST"
            ? { "Content-Type": "application/json", Accept: "application/json" }
            : { Accept: "application/json" },
        body:
          method === "POST"
            ? JSON.stringify({ action, ...(options.body || {}) })
            : undefined,
      },
    );
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.success === false)
      throw new Error(data.message || "Não foi possível concluir a operação.");
    return data;
  };
  const toast = (message, error = false) => {
    const target = document.getElementById("almaToast");
    target.textContent = message;
    target.classList.toggle("error", error);
    target.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => {
      target.hidden = true;
    }, 4200);
  };
  const busy = (button, enabled, label = "Processando...") => {
    if (!button) return;
    if (enabled) {
      button.dataset.original = button.innerHTML;
      button.disabled = true;
      button.textContent = label;
    } else {
      button.disabled = false;
      button.innerHTML = button.dataset.original || button.innerHTML;
    }
  };
  const askFields = (title, fields, submitLabel) =>
    new Promise((resolve) => {
      const dialog = document.createElement("dialog");
      dialog.className = "alma-admin-prompt";
      dialog.innerHTML = `<form method="dialog"><h2>${esc(title)}</h2>${fields
        .map(
          (field) =>
            `<label class="alma-field"><span>${esc(field.label)}</span>${field.multiline ? `<textarea name="${esc(field.name)}" ${field.required ? "required" : ""}></textarea>` : `<input name="${esc(field.name)}" ${field.required ? "required" : ""}>`}</label>`,
        )
        .join(
          "",
        )}<div class="alma-actions"><button class="alma-btn" value="cancel">Cancelar</button><button class="alma-btn primary" value="save">${esc(submitLabel)}</button></div></form>`;
      document.body.append(dialog);
      dialog.addEventListener(
        "close",
        () => {
          const result =
            dialog.returnValue === "save"
              ? Object.fromEntries(
                  new FormData(dialog.querySelector("form")).entries(),
                )
              : null;
          dialog.remove();
          resolve(result);
        },
        { once: true },
      );
      dialog.showModal();
      dialog.querySelector("input, textarea")?.focus();
    });
  const dimensions = () => state.library?.dimensoes || [];
  const currentDimension = () =>
    dimensions().find(
      (dimension) => dimension.codigo === state.dimensionCode,
    ) || null;
  const currentItem = () =>
    currentDimension()?.itens?.find(
      (item) => item.id === Number(state.itemId),
    ) || null;

  async function loadVersions(preferredId = null) {
    const data = await api("admin_versoes");
    state.versions = data.versoes || [];
    const versionId =
      preferredId ||
      state.library?.versao?.id ||
      Number(state.versions[0]?.id || 0);
    if (!versionId)
      throw new Error("Nenhuma versão da Biblioteca ALMA foi encontrada.");
    await loadLibrary(versionId);
  }

  async function loadLibrary(versionId) {
    const data = await api("biblioteca", { params: { versao_id: versionId } });
    state.library = data.biblioteca;
    if (
      !dimensions().some(
        (dimension) => dimension.codigo === state.dimensionCode,
      )
    ) {
      state.dimensionCode = state.library.pilares[0]?.codigo || null;
    }
    const dim = currentDimension();
    if (!dim?.itens?.some((item) => item.id === Number(state.itemId))) {
      state.itemId = dim?.itens?.[0]?.id || null;
    }
    render();
  }

  function versionHeader() {
    const version = state.library.versao;
    const isDraft = version.estado === "RASCUNHO";
    return `<header class="alma-topbar alma-admin-header">
      <div><a class="alma-back" href="../inicio.php"><i class="ri-arrow-left-line"></i> Voltar ao Flow</a>
        <div class="alma-kicker">Administração separada da direção das imagens</div>
        <div class="alma-title-row"><h1>Biblioteca Oficial ALMA</h1><span class="alma-badge is-${version.estado.toLowerCase()}">${esc(version.estado)}</span></div>
        <p class="alma-byline">As versões publicadas são imutáveis. Direções existentes permanecem ligadas à versão em que foram criadas.</p>
      </div>
      <div class="alma-actions">
        <label class="alma-field"><span class="alma-kicker">Versão</span><select id="almaAdminVersion">${state.versions.map((item) => `<option value="${item.id}" ${Number(item.id) === Number(version.id) ? "selected" : ""}>v${esc(item.codigo)} · ${esc(item.estado)}</option>`).join("")}</select></label>
        <button class="alma-btn" id="almaCloneVersion"><i class="ri-file-copy-line"></i> Nova versão</button>
        ${isDraft ? `<button class="alma-btn primary" id="almaPublishVersion"><i class="ri-send-plane-line"></i> Publicar versão</button>` : ""}
      </div>
    </header>`;
  }

  function navigation() {
    return `<aside class="alma-card alma-library-nav"><div class="alma-library-version"><strong>ALMA v${esc(state.library.versao.codigo)}</strong><span>${esc(state.library.versao.nome)}</span></div>${state.library.pilares
      .map((pillar) => {
        const dims = pillar.filhas?.length
          ? pillar.filhas
          : [
              dimensions().find((item) => item.codigo === pillar.codigo) ||
                pillar,
            ];
        return `<section><div class="alma-library-stage"><b>${String(pillar.ordem_jornada).padStart(2, "0")} ${esc(pillar.etapa_nome)}</b><span>${esc(pillar.pilar_nome)}</span></div>${dims.map((dim) => `<button type="button" class="alma-library-dimension ${state.dimensionCode === dim.codigo ? "active" : ""}" data-dimension-code="${esc(dim.codigo)}"><span>${esc(dim.nome)}</span><small>${dim.itens?.length || 0} itens</small></button>`).join("")}</section>`;
      })
      .join("")}</aside>`;
  }

  function itemsList() {
    const dim = currentDimension();
    if (!dim) return "";
    const canAdd = state.library.versao.estado === "RASCUNHO";
    return `<section class="alma-card alma-library-items"><div class="alma-library-pane-head"><div><div class="alma-kicker">${esc(dim.etapa_nome)} / ${esc(dim.pilar_nome)}</div><h2>${esc(dim.nome)}</h2></div><div class="alma-actions"><span>${dim.exige_item_biblioteca ? "Seleções oficiais" : "Dimensão contextual"}</span>${canAdd ? '<button type="button" class="alma-btn compact" id="almaAddLibraryItem"><i class="ri-add-line"></i> Novo item</button>' : ""}</div></div>${dim.itens?.length ? `<div class="alma-library-item-buttons">${dim.itens.map((item) => `<button type="button" class="${Number(state.itemId) === item.id ? "active" : ""}" data-library-item="${item.id}"><strong>${esc(item.titulo)}</strong><span>${esc(item.resumo || "Sem resumo")}</span></button>`).join("")}</div>` : `<div class="alma-empty alma-library-empty"><p>Esta dimensão não possui itens oficiais na Biblioteca v${esc(state.library.versao.codigo)}. A direção registra somente contexto da imagem, sem inventar categorias.</p></div>`}</section>`;
  }

  const contentBlockTypes = {
    text: "Texto",
    positive_list: "Lista positiva",
    negative_list: "Lista negativa",
    principle: "Princípio fundamental",
    material_guideline: "Material / recomendações",
  };
  const contentItems = (name, values = [], disabled = "") =>
    `<div class="alma-content-editor-items" data-content-items="${name}">${values
      .map(
        (value) =>
          `<div><input data-content-item value="${esc(value)}" ${disabled}><button type="button" class="alma-btn compact" data-remove-content-item ${disabled}>×</button></div>`,
      )
      .join(
        "",
      )}</div><button type="button" class="alma-btn compact" data-add-content-item="${name}" ${disabled}><i class="ri-add-line"></i> Adicionar item</button>`;
  const contentBlock = (block, disabled = "") => {
    const type = contentBlockTypes[block.type] ? block.type : "text";
    const title = `<label class="alma-field"><span>Título</span><input data-content-field="title" value="${esc(block.title || "")}" ${disabled}></label>`;
    const controls = `<div class="alma-content-block-actions"><button type="button" class="alma-btn compact" data-move-content-block="up" ${disabled} aria-label="Mover bloco para cima">↑</button><button type="button" class="alma-btn compact" data-move-content-block="down" ${disabled} aria-label="Mover bloco para baixo">↓</button><button type="button" class="alma-btn compact danger" data-remove-content-block ${disabled}>Remover</button></div>`;
    let fields = "";
    if (type === "text" || type === "principle") {
      fields = `${title}<label class="alma-field"><span>Conteúdo</span><textarea data-content-field="content" class="is-large" ${disabled}>${esc(block.content || "")}</textarea></label>`;
    } else if (type === "positive_list" || type === "negative_list") {
      fields = `${title}${contentItems("items", block.items || [], disabled)}`;
    } else {
      fields = `${title}<div class="alma-content-material-columns"><section><strong>Priorizar</strong>${contentItems("positive", block.positive || [], disabled)}</section><section><strong>Evitar</strong>${contentItems("negative", block.negative || [], disabled)}</section></div>`;
    }
    return `<article class="alma-content-block alma-content-block--${type}" data-content-block data-content-type="${type}"><header><strong>${esc(contentBlockTypes[type])}</strong>${controls}</header>${fields}</article>`;
  };
  const structuredContentEditor = (item, isDraft) => {
    const content = item.conteudo_estruturado || { version: 1, blocks: [] };
    const disabled = isDraft ? "" : "disabled";
    const legacy = [
      item.descricao,
      item.principio_fundamental,
      item.diretriz_completa,
    ]
      .filter(Boolean)
      .join("\n\n");
    return `<fieldset class="alma-content-editor"><legend>Conteúdo estruturado</legend><p class="alma-content-editor-intro">Blocos semânticos reutilizáveis. O conteúdo legado permanece preservado como fallback até ser convertido.</p><div id="almaContentBlocks">${(content.blocks || []).map((block) => contentBlock(block, disabled)).join("")}</div>${
      isDraft
        ? `<div class="alma-content-editor-add"><select id="almaContentBlockType">${Object.entries(
            contentBlockTypes,
          )
            .map(([type, label]) => `<option value="${type}">${label}</option>`)
            .join(
              "",
            )}</select><button type="button" class="alma-btn" id="almaAddContentBlock"><i class="ri-add-line"></i> Adicionar bloco</button></div>`
        : ""
    }${legacy ? `<details class="alma-library-source"><summary>Conteúdo legado preservado</summary><p>${esc(legacy)}</p></details>` : ""}</fieldset>`;
  };

  function itemEditor() {
    const item = currentItem();
    if (!item)
      return `<section class="alma-card alma-library-editor alma-empty"><p>Selecione um item oficial para consultar seu conteúdo.</p></section>`;
    const isDraft = state.library.versao.estado === "RASCUNHO";
    const editableSections = (item.secoes || []).filter(
      (section) => section.codigo !== "fonte_oficial",
    );
    const provenance = (item.secoes || []).find(
      (section) => section.codigo === "fonte_oficial",
    );
    const disabled = isDraft ? "" : "disabled";
    const field = (name, label, value, large = false) =>
      `<label class="alma-field"><span>${esc(label)}</span><textarea name="${name}" ${disabled} class="${large ? "is-large" : ""}">${esc(value || "")}</textarea></label>`;
    const sectionEntries = (section) => {
      const entries = section.entradas || [];
      const types = new Set(
        entries.map((entry) => String(entry.tipo || "ITEM").toUpperCase()),
      );
      return [...types].length > 1
        ? entries
            .map(
              (entry) =>
                `[${String(entry.tipo || "ITEM").toUpperCase()}] ${entry.texto}`,
            )
            .join("\n")
        : entries.map((entry) => entry.texto).join("\n");
    };
    return `<form class="alma-card alma-library-editor" id="almaLibraryItemForm" data-item-id="${item.id}">
      <div class="alma-library-pane-head"><div><div class="alma-kicker">Item oficial</div><h2>${esc(item.titulo)}</h2></div>${isDraft ? `<label class="alma-library-active"><input type="checkbox" name="ativo" ${item.ativo ? "checked" : ""}> Disponível para seleção</label>` : `<span>Somente leitura</span>`}</div>
      <label class="alma-field"><span>Título</span><input name="titulo" value="${esc(item.titulo)}" ${disabled}></label>
      <div class="alma-form-grid">${field("resumo", "Resumo", item.resumo)}${field("diferenca_principal", "Diferença principal", item.diferenca_principal)}</div>
      ${structuredContentEditor(item, isDraft)}
      ${editableSections.map((section) => `<fieldset class="alma-library-section" data-section-id="${section.id}" data-section-code="${esc(section.codigo)}"><legend>${esc(section.titulo)}</legend><label class="alma-field"><span>Título da seção</span><input data-section-field="titulo" value="${esc(section.titulo)}" ${disabled}></label><label class="alma-field"><span>Conteúdo</span><textarea data-section-field="conteudo" ${disabled}>${esc(section.conteudo || "")}</textarea></label><label class="alma-field"><span>Entradas — uma por linha${(section.entradas || []).some((entry, index, entries) => index > 0 && entry.tipo !== entries[0].tipo) ? " · use [TIPO] texto para preservar categorias" : ""}</span><textarea data-section-field="entradas" ${disabled}>${esc(sectionEntries(section))}</textarea></label><input type="hidden" data-section-entry-type value="${esc(section.entradas?.[0]?.tipo || "ITEM")}"></fieldset>`).join("")}
      ${provenance ? `<details class="alma-library-source"><summary>Conteúdo oficial integral importado</summary><p>${esc(provenance.conteudo)}</p></details>` : ""}
      ${isDraft ? `<div class="alma-actions"><button class="alma-btn primary" id="almaSaveLibraryItem" type="submit"><i class="ri-save-line"></i> Salvar item</button></div>` : ""}
    </form>`;
  }

  function render() {
    app.innerHTML = `<div class="alma-shell">${versionHeader()}<div class="alma-library-layout">${navigation()}<main class="alma-library-work">${itemsList()}${itemEditor()}</main></div></div>`;
    bind();
  }

  function serializeStructuredContent(form) {
    const blocks = [...form.querySelectorAll("[data-content-block]")]
      .map((element) => {
        const type = element.dataset.contentType;
        const title =
          element.querySelector('[data-content-field="title"]')?.value.trim() ||
          "";
        const values = (name) =>
          [
            ...element.querySelectorAll(
              `[data-content-items="${name}"] [data-content-item]`,
            ),
          ]
            .map((input) => input.value.trim())
            .filter(Boolean);
        if (type === "text" || type === "principle") {
          const content =
            element
              .querySelector('[data-content-field="content"]')
              ?.value.trim() || "";
          return title || content ? { type, title, content } : null;
        }
        if (type === "positive_list" || type === "negative_list") {
          const items = values("items");
          return title || items.length ? { type, title, items } : null;
        }
        const positive = values("positive");
        const negative = values("negative");
        return title || positive.length || negative.length
          ? { type, title, positive, negative }
          : null;
      })
      .filter(Boolean);
    return blocks.length ? { version: 1, blocks } : null;
  }

  function bind() {
    document
      .getElementById("almaAdminVersion")
      ?.addEventListener("change", (event) =>
        loadLibrary(Number(event.target.value)).catch((error) =>
          toast(error.message, true),
        ),
      );
    document.querySelectorAll("[data-dimension-code]").forEach((button) =>
      button.addEventListener("click", () => {
        state.dimensionCode = button.dataset.dimensionCode;
        state.itemId = currentDimension()?.itens?.[0]?.id || null;
        render();
      }),
    );
    document.querySelectorAll("[data-library-item]").forEach((button) =>
      button.addEventListener("click", () => {
        state.itemId = Number(button.dataset.libraryItem);
        render();
      }),
    );
    document
      .getElementById("almaAddLibraryItem")
      ?.addEventListener("click", async (event) => {
        const button = event.currentTarget;
        const values = await askFields(
          "Novo item oficial",
          [
            { name: "titulo", label: "Título", required: true },
            { name: "resumo", label: "Resumo", multiline: true },
          ],
          "Criar item",
        );
        if (!values?.titulo?.trim()) return;
        try {
          busy(button, true, "Adicionando...");
          const data = await api("admin_adicionar_item", {
            method: "POST",
            body: {
              versao_id: state.library.versao.id,
              dimensao_codigo: state.dimensionCode,
              titulo: values.titulo.trim(),
              resumo: values.resumo.trim(),
            },
          });
          state.library = data.biblioteca;
          const dim = currentDimension();
          state.itemId = dim?.itens?.at(-1)?.id || null;
          render();
          toast("Item adicionado. Complete as diretrizes e salve.");
        } catch (error) {
          toast(error.message, true);
        }
      });
    document
      .getElementById("almaCloneVersion")
      ?.addEventListener("click", async (event) => {
        const button = event.currentTarget;
        const values = await askFields(
          "Nova versão da Biblioteca",
          [
            {
              name: "codigo",
              label: "Código semântico (ex.: 1.3)",
              required: true,
            },
            { name: "nome", label: "Nome da versão", required: true },
          ],
          "Criar rascunho",
        );
        if (!values) return;
        try {
          busy(button, true, "Clonando...");
          const data = await api("admin_clonar_versao", {
            method: "POST",
            body: {
              versao_origem_id: state.library.versao.id,
              codigo: values.codigo.trim(),
              nome: values.nome.trim(),
            },
          });
          state.library = data.biblioteca;
          state.dimensionCode = state.library.pilares[0]?.codigo || null;
          state.itemId = null;
          await loadVersions(state.library.versao.id);
          toast(
            "Nova versão em rascunho criada sem alterar a versão publicada.",
          );
        } catch (error) {
          toast(error.message, true);
        } finally {
          busy(button, false);
        }
      });
    document
      .getElementById("almaPublishVersion")
      ?.addEventListener("click", async (event) => {
        if (
          !window.confirm(
            "Publicar esta versão? Depois de publicada, ela ficará imutável.",
          )
        )
          return;
        const button = event.currentTarget;
        try {
          busy(button, true, "Publicando...");
          await api("admin_publicar_versao", {
            method: "POST",
            body: { versao_id: state.library.versao.id },
          });
          await loadVersions(state.library.versao.id);
          toast("Versão publicada e protegida contra edição destrutiva.");
        } catch (error) {
          toast(error.message, true);
        } finally {
          busy(button, false);
        }
      });
    document
      .getElementById("almaLibraryItemForm")
      ?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = document.getElementById("almaSaveLibraryItem");
        const sections = [...form.querySelectorAll("[data-section-id]")].map(
          (section) => {
            const type = section.querySelector(
              "[data-section-entry-type]",
            ).value;
            return {
              id: Number(section.dataset.sectionId),
              titulo: section
                .querySelector('[data-section-field="titulo"]')
                .value.trim(),
              conteudo: section
                .querySelector('[data-section-field="conteudo"]')
                .value.trim(),
              entradas: section
                .querySelector('[data-section-field="entradas"]')
                .value.split(/\r?\n/)
                .map((line, index) => {
                  const match = line
                    .trim()
                    .match(/^\[([A-Za-z0-9_]+)\]\s*(.*)$/);
                  return {
                    texto: (match ? match[2] : line).trim(),
                    tipo:
                      (match ? match[1] : type).trim().toUpperCase() || "ITEM",
                    ordem: index + 1,
                  };
                })
                .filter((entry) => entry.texto),
            };
          },
        );
        const body = Object.fromEntries(new FormData(form).entries());
        body.item_id = Number(form.dataset.itemId);
        body.ativo = form.querySelector('[name="ativo"]')?.checked ?? false;
        body.secoes = sections;
        body.conteudo_estruturado = serializeStructuredContent(form);
        try {
          busy(button, true, "Salvando...");
          const data = await api("admin_salvar_item", { method: "POST", body });
          state.library = data.biblioteca;
          render();
          toast("Item salvo na versão em rascunho.");
        } catch (error) {
          toast(error.message, true);
        } finally {
          busy(button, false);
        }
      });
    document
      .getElementById("almaLibraryItemForm")
      ?.addEventListener("click", (event) => {
        const button = event.target.closest("button");
        if (!button) return;
        if (button.id === "almaAddContentBlock") {
          const type =
            document.getElementById("almaContentBlockType")?.value || "text";
          document
            .getElementById("almaContentBlocks")
            ?.insertAdjacentHTML("beforeend", contentBlock({ type }));
        } else if (button.matches("[data-remove-content-block]")) {
          button.closest("[data-content-block]")?.remove();
        } else if (button.matches("[data-move-content-block]")) {
          const block = button.closest("[data-content-block]");
          if (button.dataset.moveContentBlock === "up")
            block?.previousElementSibling?.before(block);
          else block?.nextElementSibling?.after(block);
        } else if (button.matches("[data-add-content-item]")) {
          const holder = button.parentElement?.querySelector(
            `[data-content-items="${button.dataset.addContentItem}"]`,
          );
          holder?.insertAdjacentHTML(
            "beforeend",
            '<div><input data-content-item><button type="button" class="alma-btn compact" data-remove-content-item>×</button></div>',
          );
        } else if (button.matches("[data-remove-content-item]")) {
          button.parentElement.remove();
        }
      });
  }

  loadVersions().catch((error) => {
    app.innerHTML = `<section class="alma-card alma-empty"><div class="alma-empty-mark">!</div><h1>Não foi possível abrir a Biblioteca ALMA</h1><p>${esc(error.message)}</p><a class="alma-btn" href="../inicio.php">Voltar ao Flow</a></section>`;
  });
})();
