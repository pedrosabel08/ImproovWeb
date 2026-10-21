/* Timeline da obra: classificação e filtros compartilhados com a renderização.
 * Registros originais são preservados. Lotes só são unidos com chave explícita. */
(function (root) {
  "use strict";
  const normalize = (value) =>
    String(value ?? "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  const kinds = {
    entregas: ["Entregas", "fa-flag", "green"],
    arquivos: ["Arquivos / Referências", "fa-file-lines", "blue"],
    manuais: ["Atualizações manuais", "fa-comment", "orange"],
    aprovacoes: ["Aprovações", "fa-user-check", "purple"],
    onboarding: ["Onboarding", "fa-clipboard-check", "red"],
    outros: ["Outros", "fa-ellipsis", "gray"],
  };
  const periods = {
    7: "Últimos 7 dias",
    30: "Últimos 30 dias",
    90: "Últimos 3 meses",
    all: "Todo o período",
  };
  function category(row) {
    const type = normalize(row.tipo).trim();
    if (["entrega", "entregas"].includes(type)) return "entregas";
    if (["arquivo", "arquivos", "file", "upload", "anexo"].includes(type))
      return "arquivos";
    return "manuais";
  }
  function kind(row) {
    const cat = category(row),
      type = normalize(row.tipo);
    if (cat !== "manuais") return cat;
    // O tipo explícito manual prevalece sobre palavras como "aprovação" no texto.
    if (type === "manual") return "manuais";
    if (
      /^(project_start|sla_defined|groups_created|images_imported|images_extras_added|onboarding_completed|extras_added)$/.test(
        type,
      ) ||
      /onboarding/.test(type)
    )
      return "onboarding";
    if (/aprov|confirm/.test(type)) return "aprovacoes";
    return ["", "manual"].includes(type) ? "manuais" : "outros";
  }
  function date(row) {
    const value = String(row.data || "");
    if (!/^\d{4}-\d{2}-\d{2}/.test(value) || value.startsWith("0000"))
      return null;
    const result = new Date(value.replace(" ", "T"));
    // DATE usa meia-noite local, não UTC (evita deslocar o dia no Brasil).
    const local = value.length === 10 ? new Date(`${value}T00:00:00`) : result;
    return Number.isNaN(local.getTime()) ? null : local;
  }
  function inPeriod(row, period, now = new Date()) {
    if (period === "all") return true;
    const value = date(row);
    if (!value) return false;
    const start = new Date(now);
    start.setHours(0, 0, 0, 0);
    if (period === "90") {
      const day = start.getDate();
      start.setDate(1);
      start.setMonth(start.getMonth() - 3);
      const last = new Date(
        start.getFullYear(),
        start.getMonth() + 1,
        0,
      ).getDate();
      start.setDate(Math.min(day, last));
    } else start.setDate(start.getDate() - Number(period) + 1);
    return value >= start && value <= now;
  }
  function search(row, query) {
    const meta = row.metadata || {};
    const text = normalize(
      [
        row.assunto,
        row.tipo,
        kinds[kind(row)][0],
        row.usuario_nome,
        row.status,
        row.entrega?.etapa,
        row.entrega?.status,
        meta.ambiente,
        meta.grupo,
        meta.status,
        meta.observacoes,
        ...(Array.isArray(meta.pacotes) ? meta.pacotes : []),
        ...(row.arquivos || []).map((f) => f.nome_original),
      ].join(" "),
    );
    return normalize(query)
      .trim()
      .split(/\s+/)
      .every((word) => text.includes(word));
  }
  const filter = (rows, state, now) =>
    rows.filter(
      (r) =>
        (state.category === "todos" || category(r) === state.category) &&
        (!state.kind || state.kind === "all" || kind(r) === state.kind) &&
        search(r, state.query) &&
        inPeriod(r, state.period, now),
    );
  function group(rows) {
    const groups = new Map();
    [...rows]
      .sort(
        (a, b) =>
          (date(b)?.getTime() || 0) - (date(a)?.getTime() || 0) ||
          Number(b.id) - Number(a.id),
      )
      .forEach((row) => {
        const key = date(row) ? String(row.data).slice(0, 10) : "unknown";
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(row);
      });
    return groups;
  }
  function consolidate(rows) {
    const result = [],
      batches = new Map();
    rows.forEach((row) => {
      const m = row.metadata || {},
        batch = m.upload_batch_id || m.lote_id;
      if (category(row) !== "arquivos" || !batch) {
        result.push([row]);
        return;
      }
      const key = JSON.stringify([
        batch,
        m.grupo_id || m.grupo || "",
        m.ambiente_id || m.ambiente || "",
        m.categoria_id || "",
      ]);
      if (!batches.has(key)) {
        const list = [];
        batches.set(key, list);
        result.push(list);
      }
      batches.get(key).push(row);
    });
    return result;
  }
  function mount(container, options) {
    const section = container.closest(".history-section");
    let rows = [],
      state = { category: "todos", kind: "all", period: "all", query: "" },
      limit = 80;
    let nodes = new Map(),
      previous = "",
      revision = 0;
    let openMenu = null;
    const closeMenu = () => {
      if (openMenu) openMenu.open = false;
      openMenu = null;
    };
    document.addEventListener("click", (event) => {
      if (openMenu && !openMenu.contains(event.target)) closeMenu();
    });
    section.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && openMenu) {
        const summary = openMenu.querySelector("summary");
        closeMenu();
        summary.focus();
      }
    });
    const el = (tag, cls, text) => {
      const n = document.createElement(tag);
      if (cls) n.className = cls;
      if (text != null) n.textContent = text;
      return n;
    };
    const icon = (name) => {
      const n = el("i", `fa-solid ${name}`);
      n.setAttribute("aria-hidden", "true");
      return n;
    };
    const button = (label, name, action, cls = "history-link") => {
      const n = el("button", cls);
      n.type = "button";
      if (name) n.append(icon(name));
      n.append(document.createTextNode(label));
      n.addEventListener("click", action);
      return n;
    };
    const fmt = (value) => {
      const d = date({ data: value });
      return d ? d.toLocaleDateString("pt-BR") : "—";
    };
    function card(row) {
      if (nodes.has(row.id)) return nodes.get(row.id);
      const type = kind(row),
        [, glyph, color] = kinds[type];
      const article = el(
        "article",
        `acomp-conteudo history-card history-${color}`,
      );
      const mark = el("div", "history-event-icon");
      mark.append(
        icon(
          category(row) === "arquivos" &&
            /\b(IMG|imagem|imagens)\b/i.test(row.assunto)
            ? "fa-image"
            : glyph,
        ),
      );
      const body = el("div", "history-card-body");
      const title = el("p", "acomp-assunto");
      title.append(el("span", "acomp-texto", row.assunto));
      const edit = () => options.edit(row, title);
      body.append(title);
      const meta = row.metadata || {},
        delivery = row.entrega;
      const details = [
        meta.ambiente || meta.grupo,
        delivery?.etapa,
        delivery?.status || meta.status,
        ...(Array.isArray(meta.pacotes) ? meta.pacotes : []),
      ].filter(Boolean);
      if (delivery?.quantidade_imagens != null)
        details.push(`${delivery.quantidade_imagens} imagens na entrega`);
      if (details.length)
        body.append(el("p", "history-meta", [...new Set(details)].join(" · ")));
      if (meta.observacoes)
        body.append(el("p", "history-meta", meta.observacoes));
      if (delivery && (delivery.data_recebimento || delivery.data_conclusao)) {
        const track = el("div", "history-delivery-track");
        if (delivery.data_recebimento)
          track.append(
            el("div", "", `Recebida\n${fmt(delivery.data_recebimento)}`),
          );
        if (delivery.data_conclusao)
          track.append(
            el("div", "", `Concluída\n${fmt(delivery.data_conclusao)}`),
          );
        body.append(track);
      }
      // Anexos devem vir de vínculos explícitos, nunca da semelhança de assunto/data.
      const isDelivery = category(row) === "entregas";
      const files = isDelivery
        ? delivery?.imagens || []
        : Array.isArray(row.arquivos)
          ? row.arquivos
          : [];
      if (files.length) {
        const attachments = el("div", "history-attachments");
        files.slice(0, 5).forEach((file) => {
          const href = safeUrl(file.url),
            thumb = safeUrl(file.thumbnail_url);
          const link = el(href ? "a" : "span", "history-file");
          link.append(
            el("span", "history-file-name", file.nome_original || "Arquivo"),
          );
          if (href) {
            link.href = href;
            link.target = "_blank";
            link.rel = "noopener";
          }
          link.title = file.nome_original || "Arquivo";
          if (thumb) {
            const img = el("img");
            img.src = thumb;
            img.alt = file.nome_original || "";
            img.loading = "lazy";
            img.decoding = "async";
            img.width = 88;
            img.height = 58;
            img.addEventListener("load", () =>
              img.classList.add("history-thumbnail-ready"),
            );
            img.addEventListener("error", () => {
              img.remove();
              link.classList.remove("history-thumbnail");
            });
            link.prepend(img);
            link.classList.add("history-thumbnail");
          } else link.prepend(icon("fa-file"));
          attachments.append(link);
        });
        const total = isDelivery
          ? delivery?.total_previews || files.length
          : files.length;
        if (total > 5)
          attachments.append(
            button(`+${total - 5}`, "", (event) =>
              isDelivery
                ? options.delivery(row, event.currentTarget)
                : options.files(row),
            ),
          );
        body.append(attachments);
      }
      if (row.usuario_nome) {
        const author = el("div", "history-author");
        const fallback = icon("fa-circle-user");
        const photo = safeUrl(row.usuario_thumb);
        if (photo) {
          const img = el("img", "history-avatar");
          img.src = photo;
          img.alt = "";
          img.width = 24;
          img.height = 24;
          img.loading = "lazy";
          img.decoding = "async";
          img.addEventListener("error", () => img.replaceWith(fallback), {
            once: true,
          });
          author.append(img);
        } else author.append(fallback);
        author.append(document.createTextNode(row.usuario_nome));
        body.append(author);
      }
      const actions = el("div", "history-card-actions");
      if (category(row) === "entregas")
        actions.append(
          button(
            "Ver imagens",
            "fa-images",
            (event) => options.delivery(row, event.currentTarget),
            "history-link btn-ver-entrega",
          ),
        );
      if (category(row) === "arquivos")
        actions.append(
          button(
            files.length ? "Ver arquivos" : "Arquivos da obra",
            "fa-folder-open",
            () => options.files(row),
          ),
        );
      const menu = el("details", "history-menu");
      const summary = el("summary");
      summary.title = "Ações do registro";
      summary.setAttribute("aria-label", "Ações do registro");
      summary.append(icon("fa-ellipsis"));
      summary.addEventListener("click", () => {
        if (!menu.open) {
          closeMenu();
          openMenu = menu;
        } else if (openMenu === menu) openMenu = null;
      });
      const panel = el("div", "history-menu-panel");
      panel.append(
        button("Editar", "fa-pen", () => {
          closeMenu();
          edit();
        }),
        button("Excluir", "fa-trash", () => {
          closeMenu();
          options.remove(row);
        }),
      );
      menu.append(summary, panel);
      actions.append(menu);
      article.append(mark, body, actions);
      nodes.set(row.id, article);
      return article;
    }
    function safeUrl(raw) {
      if (!raw) return "";
      try {
        const url = new URL(raw, document.baseURI);
        return /^https?:$/.test(url.protocol) ? url.href : "";
      } catch {
        return "";
      }
    }
    function stats(visible) {
      const searched = rows.filter((r) => search(r, state.query));
      const scoped = searched.filter((r) => inPeriod(r, state.period));
      section.querySelectorAll(".acomp-filter-btn").forEach((btn) => {
        const selected = btn.dataset.category === state.category;
        btn.classList.toggle("active", selected);
        btn.setAttribute("aria-pressed", String(selected));
        btn.querySelector(".history-count").textContent = scoped.filter(
          (r) =>
            btn.dataset.category === "todos" ||
            category(r) === btn.dataset.category,
        ).length;
      });
      const dates = visible
        .map(date)
        .filter(Boolean)
        .sort((a, b) => a - b);
      section.querySelector("[data-history-total]").textContent =
        visible.length;
      section.querySelector("[data-history-first]").textContent = dates.length
        ? dates[0].toLocaleDateString("pt-BR")
        : "—";
      section.querySelector("[data-history-last]").textContent = dates.length
        ? dates.at(-1).toLocaleDateString("pt-BR")
        : "—";
      const types = section.querySelector("[data-history-types]");
      types.replaceChildren();
      Object.entries(kinds).forEach(([key, [label, glyph, color]]) => {
        const line = button(
            "",
            "",
            () =>
              apply({
                category:
                  state.kind === key
                    ? "todos"
                    : ["arquivos", "entregas"].includes(key)
                      ? key
                      : "manuais",
                kind: state.kind === key ? "all" : key,
              }),
            `history-stat-row history-${color}${state.kind === key ? " active" : ""}`,
          ),
          ico = el("span", "history-type-icon");
        line.setAttribute("aria-pressed", String(state.kind === key));
        line.setAttribute("aria-label", label);
        ico.append(icon(glyph));
        line.append(
          ico,
          el("span", "", label),
          el(
            "span",
            "history-count",
            visible.filter((r) => kind(r) === key).length,
          ),
        );
        types.append(line);
      });
      const periodsEl = section.querySelector("[data-history-periods]");
      periodsEl.replaceChildren();
      Object.entries(periods).forEach(([key, label]) => {
        const count = searched.filter(
          (r) =>
            (state.category === "todos" || category(r) === state.category) &&
            (state.kind === "all" || kind(r) === state.kind) &&
            inPeriod(r, key),
        ).length;
        const btn = button(
          label,
          "",
          () => apply({ period: key }),
          `history-stat-row${state.period === key ? " active" : ""}`,
        );
        btn.setAttribute("aria-pressed", String(state.period === key));
        btn.append(el("span", "history-count", count));
        periodsEl.append(btn);
      });
      section.querySelector("#history-period").value = state.period;
    }
    function render() {
      const visible = filter(rows, state).sort(
          (a, b) =>
            (date(b)?.getTime() || 0) - (date(a)?.getTime() || 0) ||
            Number(b.id) - Number(a.id),
        ),
        signature = `${revision}:${limit}:${visible.map((r) => r.id).join(",")}`;
      stats(visible);
      if (signature === previous) return;
      previous = signature;
      const fragment = document.createDocumentFragment();
      if (!visible.length) {
        const empty = el("div", "history-empty");
        empty.append(
          icon("fa-clock-rotate-left"),
          el(
            "h3",
            "",
            rows.length
              ? "Nenhum evento neste filtro"
              : "O histórico começa aqui",
          ),
          el(
            "p",
            "",
            rows.length
              ? "Experimente outro período ou termo de busca."
              : "Os registros e marcos da obra aparecerão nesta timeline.",
          ),
        );
        if (rows.length)
          empty.append(
            button("Limpar filtros", "", () => {
              section.querySelector("#history-search").value = "";
              apply({
                category: "todos",
                kind: "all",
                period: "all",
                query: "",
              });
            }),
          );
        fragment.append(empty);
      }
      group(visible.slice(0, limit)).forEach((dayRows, day) => {
        const groupEl = el("section", "history-day"),
          heading = el("div", "history-day-heading"),
          dt = date(dayRows[0]);
        const total =
          visible.filter(
            (r) => (date(r) ? String(r.data).slice(0, 10) : "unknown") === day,
          ).length || dayRows.length;
        heading.append(
          el(
            "h3",
            "",
            dt
              ? dt
                  .toLocaleDateString("pt-BR", {
                    day: "2-digit",
                    month: "long",
                    year: "numeric",
                  })
                  .replace(/ de /g, " ")
                  .toLocaleUpperCase("pt-BR")
              : "Data não informada",
          ),
          el("span", "", `${total} ${total === 1 ? "evento" : "eventos"}`),
          el(
            "span",
            "history-weekday",
            dt ? dt.toLocaleDateString("pt-BR", { weekday: "long" }) : "",
          ),
        );
        groupEl.append(heading);
        consolidate(dayRows).forEach((batch) => {
          const row = batch[0],
            item = el("div", `history-entry history-${kinds[kind(row)][2]}`),
            dt = date(row);
          const hasTime = /[T ]\d{2}:\d{2}/.test(String(row.data));
          const time = el(
            "time",
            "history-time",
            hasTime && dt
              ? dt.toLocaleTimeString("pt-BR", {
                  hour: "2-digit",
                  minute: "2-digit",
                })
              : "Sem horário",
          );
          if (dt) time.dateTime = row.data;
          item.append(time, el("span", "history-marker"));
          if (batch.length === 1) item.append(card(row));
          else {
            const details = el("details", "history-batch"),
              sum = el(
                "summary",
                "",
                `${batch.length} alterações de arquivos · Ver detalhes`,
              );
            details.append(sum);
            batch.forEach((r) => details.append(card(r)));
            item.append(details);
          }
          groupEl.append(item);
        });
        fragment.append(groupEl);
      });
      container.replaceChildren(fragment);
      const more = section.querySelector("#btnMostrarAcomps");
      more.hidden = visible.length <= limit;
      more.textContent = `Mostrar mais eventos (${Math.max(0, visible.length - limit)} restantes)`;
    }
    function apply(patch) {
      closeMenu();
      options.change?.();
      state = { ...state, ...patch };
      limit = 80;
      render();
    }
    section
      .querySelectorAll(".acomp-filter-btn")
      .forEach((btn) =>
        btn.addEventListener("click", () =>
          apply({ category: btn.dataset.category, kind: "all" }),
        ),
      );
    section
      .querySelector("#history-period")
      .addEventListener("change", (e) => apply({ period: e.target.value }));
    let timer;
    section.querySelector("#history-search").addEventListener("input", (e) => {
      clearTimeout(timer);
      timer = setTimeout(() => apply({ query: e.target.value }), 120);
    });
    section.querySelector("#btnMostrarAcomps").addEventListener("click", () => {
      limit += 80;
      render();
    });
    return {
      setRows(value) {
        closeMenu();
        options.change?.();
        rows = value;
        nodes = new Map();
        revision++;
        render();
      },
      state: () => ({ ...state }),
      refresh() {
        nodes.clear();
        revision++;
        render();
      },
    };
  }
  root.FlowObraHistory = {
    category,
    kind,
    date,
    inPeriod,
    search,
    filter,
    group,
    consolidate,
    mount,
  };
})(typeof window !== "undefined" ? window : globalThis);
