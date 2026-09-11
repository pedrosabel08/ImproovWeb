"use strict";
(() => {
  const $ = (id) => document.getElementById(id),
    money = (v) =>
      new Intl.NumberFormat("pt-BR", {
        style: "currency",
        currency: "BRL",
      }).format((Number(v) || 0) / 100),
    pct = (v) =>
      v == null
        ? "—"
        : new Intl.NumberFormat("pt-BR", { maximumFractionDigits: 1 }).format(
            v,
          ) + "%";
  const esc = (v) =>
    String(v ?? "").replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );
  const badge = (s) =>
    `<span class="badge ${esc(s.codigo)}" title="${esc(s.motivo)}"><span aria-hidden="true">${s.codigo === "critico" ? "!" : s.codigo === "atencao" ? "△" : s.codigo === "saudavel" ? "✓" : "○"}</span>${esc(s.texto)}</span>`;
  let data = null,
    sort = "nome",
    direction = 1,
    page = 0,
    request = 0,
    detailRequest = 0,
    controller = null;
  const csrf = document.querySelector(".cost-main").dataset.csrf;
  async function api(url, options = {}) {
    const r = await fetch(url, {
      credentials: "same-origin",
      ...options,
      headers: { Accept: "application/json", ...options.headers },
    });
    let body;
    try {
      body = await r.json();
    } catch (_) {
      throw Error(
        "Resposta inválida. Atualize a página ou verifique sua sessão.",
      );
    }
    if (!r.ok || body.error)
      throw Error(
        body.error +
          (body.erros
            ? "\n" +
              body.erros.map((e) => `Linha ${e.linha}: ${e.erro}`).join("\n")
            : ""),
      );
    return body;
  }
  const line = (label, v, cls = "") =>
    `<div class="detail-money ${cls}"><span>${esc(label)}</span><b>${money(v)}</b></div>`;
  const date = (v) => {
    if (!v) return "—";
    const d = String(v).slice(0, 10).split("-");
    return d.length === 3 ? `${d[2]}/${d[1]}/${d[0]}` : esc(v);
  };
  function commercialItems() {
    return data
      ? [
          ...data.imagens.flatMap((i) =>
            i.comercial.map((c) => ({ ...c, nome: i.nome })),
          ),
          ...data.custos_gerais.comercial.map((c) => ({
            ...c,
            nome: "Serviço fotográfico",
          })),
        ]
      : [];
  }
  async function load() {
    const id = $("obra").value,
      n = ++request;
    detailRequest++;
    controller?.abort();
    controller = new AbortController();
    data = null;
    $("dashboard").hidden = true;
    $("open-commercial").disabled = true;
    $("page-message").hidden = false;
    $("page-message").className = "notice";
    $("page-message").textContent = id
      ? "Carregando dados financeiros…"
      : "Selecione uma obra para consultar os custos.";
    if (!id) return;
    try {
      const result = await api(
        `getCustosObra.php?obra_id=${encodeURIComponent(id)}`,
        { signal: controller.signal },
      );
      if (n !== request) return;
      data = result;
      page = 0;
      $("page-message").hidden = true;
      $("dashboard").hidden = false;
      $("open-commercial").disabled = false;
      $("breadcrumb-obra").textContent = data.obra.nomenclatura;
      const url = new URL(location.href);
      url.searchParams.set("obra_id", id);
      history.replaceState(null, "", url);
      render();
    } catch (e) {
      if (e.name === "AbortError" || n !== request) return;
      $("page-message").className = "notice error";
      $("page-message").textContent = e.message;
    }
  }
  function render() {
    const r = data.resumo;
    for (const [id, key] of Object.entries({
      "k-vendido": "vendido",
      "k-liquido": "liquido",
      "k-realizado": "realizado",
      "k-a-pagar": "a_pagar",
      "k-projetado": "projetado",
      "k-margem": "margem",
    }))
      $(id).textContent = money(r[key]);
    $("k-margem-pct").textContent = pct(r.margem_percentual);
    $("k-margem").classList.toggle("negative", r.margem < 0);
    $("k-realizado-note").textContent =
      r.liquido > 0
        ? `${pct((r.realizado / r.liquido) * 100)} da receita líquida`
        : "Pagamentos registrados";
    $("project-health").innerHTML = badge(r.saude);
    const parts = [
        ["Produção realizada", r.realizado, "var(--cost-info)"],
        ["A pagar", r.a_pagar, "var(--cost-warning)"],
        ["Margem projetada", Math.max(0, r.margem), "var(--cost-success)"],
      ],
      base = parts.reduce((s, p) => s + Math.max(0, p[1]), 0);
    $("health-bar").innerHTML = base
      ? parts
          .filter((p) => p[1] > 0)
          .map(
            ([label, v, color]) =>
              `<div class="health-segment" style="width:${(v / base) * 100}%;--segment:${color}" title="${label}: ${money(v)}"><strong>${v / base > 0.16 ? money(v) : ""}</strong><small>${v / base > 0.08 ? pct((v / base) * 100) : ""}</small></div>`,
          )
          .join("")
      : '<span class="empty">Sem valores para compor a visualização</span>';
    $("health-legend").innerHTML = parts
      .map(
        ([label, v, color], i) =>
          `<div><i class="dot" style="--segment:${color}"></i>${label}<b${i === 2 && r.margem < 0 ? ' class="negative"' : ""}>${money(i === 2 ? r.margem : v)}</b></div>`,
      )
      .join("");
    $("health-note").textContent =
      r.margem < 0
        ? `Produção excede a receita líquida em ${money(-r.margem)}. A barra representa os custos; não há margem restante.`
        : r.saude.motivo;
    renderDistribution();
    renderImages();
    const g = data.custos_gerais;
    $("general-total").textContent = money(g.totais.projetado);
    $("general-content").innerHTML = g.producao.length
      ? g.producao
          .map(
            (t) =>
              `<div class="general-row"><div>${esc(t.nome_funcao)}<small>Realizado ${money(t.pago)} · A pagar ${money(t.a_pagar)}</small></div><b>${money(t.projetado)}</b></div>`,
          )
          .join("")
      : '<div class="empty">Nenhum custo geral registrado.</div>';
    if (g.comercial.length)
      $("general-content").innerHTML +=
        `<div class="general-row"><div>Serviço fotográfico<small>Receita da obra, sem rateio entre imagens</small></div><b>${money(g.totais.vendido)}</b></div>`;
    $("recent-payments").innerHTML = data.pagamentos_recentes.length
      ? data.pagamentos_recentes
          .map(
            (p) =>
              `<div class="payment-row"><div>${esc(p.descricao)}<small>${esc(p.imagem_nome)} · ${date(p.criado_em)} · competência ${esc(p.mes_ref)}</small></div><b>${money(Math.round(Number(p.valor) * 100))}</b></div>`,
          )
          .join("")
      : '<div class="empty">Nenhum pagamento registrado no livro financeiro.</div>';
    const alerts = [
      ...data.alertas,
      ...g.alertas,
      ...data.imagens.flatMap((i) => i.alertas.map((a) => i.nome + ": " + a)),
    ];
    $("audit-summary").textContent = alerts.length
      ? `△ Qualidade dos dados · ${alerts.length} pontos para conferir`
      : "✓ Qualidade dos dados · sem divergências identificadas";
    $("audit-content").innerHTML = alerts.length
      ? alerts.map((a) => `<p>${esc(a)}</p>`).join("")
      : "<p>Os valores conciliam com os lançamentos identificados.</p>";
  }
  function renderDistribution() {
    if (!data) return;
    const key = $("distribution-mode").value;
    let rows = [...data.distribuicao].sort((a, b) => b[key] - a[key]);
    const extra = rows.length > 6 ? rows.splice(5) : [];
    if (extra.length)
      rows.push({
        nome: "Outros",
        [key]: extra.reduce((s, r) => s + r[key], 0),
      });
    const total = rows.reduce((s, r) => s + Math.max(0, r[key]), 0);
    let offset = 0;
    const circles = rows
      .map((r, i) => {
        const part = total ? (Math.max(0, r[key]) / total) * 100 : 0,
          c = `<circle cx="70" cy="70" r="54" fill="none" stroke="var(--cost-chart-${i + 1})" stroke-width="15" pathLength="100" stroke-dasharray="${part} ${100 - part}" stroke-dashoffset="${-offset}" transform="rotate(-90 70 70)"><title>${esc(r.nome)}: ${money(r[key])}</title></circle>`;
        offset += part;
        return c;
      })
      .join("");
    $("donut").innerHTML =
      `<svg viewBox="0 0 140 140" role="img" aria-label="Distribuição da produção"><circle cx="70" cy="70" r="54" fill="none" stroke="var(--cost-border)" stroke-width="15"/>${circles}<text x="70" y="68" text-anchor="middle" font-size="13" font-weight="750">${money(total)}</text><text class="chart-caption" x="70" y="82" text-anchor="middle">${key === "realizado" ? "Total realizado" : "Total projetado"}</text></svg>`;
    const row = (r, i) =>
      `<div class="distribution-row"><span><i class="dot" style="--segment:var(--cost-chart-${i + 1})"></i>${esc(r.nome)}</span><b>${money(r[key])}</b><small>${pct(total ? (r[key] / total) * 100 : 0)}</small></div>`;
    $("distribution-list").innerHTML = rows.length
      ? rows.map(row).join("")
      : '<p class="muted">Sem custos de produção.</p>';
    $("other-functions").hidden = !extra.length;
    $("other-list").innerHTML = extra.map((r) => row(r, 5)).join("");
  }
  function renderImages() {
    if (!data) return;
    const term = $("search").value.trim().toLocaleLowerCase("pt-BR"),
      filter = $("health-filter").value;
    let rows = data.imagens.filter(
      (i) =>
        (i.nome + " " + (i.tipo || ""))
          .toLocaleLowerCase("pt-BR")
          .includes(term) &&
        (!filter ||
          (filter === "sem_comercial"
            ? !i.comercial.length
            : i.saude.codigo === filter)),
    );
    rows.sort(
      (a, b) =>
        direction *
        (sort === "nome"
          ? a.nome.localeCompare(b.nome, "pt-BR", { numeric: true })
          : a.totais[sort] - b.totais[sort]),
    );
    const pages = Math.max(1, Math.ceil(rows.length / 20));
    page = Math.min(page, pages - 1);
    $("image-count").textContent = `${rows.length} de ${data.imagens.length}`;
    $("images-body").innerHTML =
      rows
        .slice(page * 20, page * 20 + 20)
        .map(
          (i) =>
            `<tr><td><button class="image-name" data-image="${Number(i.id)}"><span class="thumb">${i.thumbnail ? `<img src="${esc(i.thumbnail)}" loading="lazy" alt="">` : "▧"}</span><span><strong>${esc(i.nome)}</strong><small>${esc([i.tipo, i.subtipo].filter(Boolean).join(" · ") || "Imagem")}</small></span></button></td>${["vendido", "liquido", "realizado", "a_pagar", "projetado"].map((k) => `<td>${!i.comercial.length && ["vendido", "liquido"].includes(k) ? '<span title="Sem cadastro comercial">—</span>' : money(i.totais[k])}</td>`).join("")}<td class="${i.totais.margem < 0 ? "negative" : ""}">${money(i.totais.margem)}<span class="money-secondary">${pct(i.totais.margem_percentual)}</span></td><td>${badge(i.saude)}</td></tr>`,
        )
        .join("") ||
      '<tr><td colspan="8" class="empty">Nenhuma imagem encontrada para estes filtros.</td></tr>';
    for (const img of $("images-body").querySelectorAll("img"))
      img.addEventListener(
        "error",
        () => {
          img.parentElement.textContent = "▧";
        },
        { once: true },
      );
    $("prev-page").disabled = page === 0;
    $("next-page").disabled = page >= pages - 1;
    $("page-number").textContent = `${page + 1} / ${pages}`;
  }
  async function detail(id) {
    const n = ++detailRequest,
      obra = $("obra").value;
    $("detail-content").innerHTML = '<p class="muted">Carregando detalhe…</p>';
    $("image-detail").showModal();
    try {
      const i = await api(
        `getCustosImagem.php?obra_id=${obra}&imagem_id=${id}`,
      );
      if (n !== detailRequest) return;
      const t = i.totais;
      $("detail-content").innerHTML =
        `<h2>${esc(i.nome)}</h2><p class="muted">${esc(i.tipo || "Imagem")}</p>${badge(i.saude)}${i.alertas.length ? `<div class="detail-alert">${i.alertas.map(esc).join("<br>")}</div>` : ""}<section class="detail-section"><h3>Comercial</h3>${line("Valor vendido", t.vendido)}${line("(−) Impostos", t.impostos)}${line("(−) Comissão comercial", t.comissao)}${line("Receita líquida", t.liquido, "total")}</section><section class="detail-section"><h3>Produção</h3><div class="table-scroll"><table><thead><tr><th>Função</th><th>Previsto</th><th>Pago</th><th>Restante</th></tr></thead><tbody>${i.producao.map((f) => `<tr><td>${esc(f.nome_funcao)}</td><td>${money(f.previsto)}</td><td>${money(f.pago)}</td><td>${money(f.a_pagar)}</td></tr>`).join("") || '<tr><td colspan="4">Nenhuma tarefa registrada.</td></tr>'}</tbody></table></div>${line("Produção realizada", t.realizado)}${line("A pagar", t.a_pagar)}${line("Produção projetada", t.projetado, "total")}</section><section class="detail-section">${line("Margem projetada", t.margem, "total " + (t.margem < 0 ? "negative" : ""))}<p class="muted">${pct(t.margem_percentual)} da receita líquida</p></section><details><summary>Ver lançamentos que compõem o realizado</summary>${i.producao.flatMap((f) => f.lancamentos.map((p) => `<div class="payment-row"><div>${esc(f.nome_funcao)}<small>#${p.idpagamento_item} · pagamento #${p.pagamento_id} · ${esc(p.tipo)}<br>${date(p.criado_em)} · competência ${esc(p.mes_ref)}</small></div><b>${money(Math.round(Number(p.valor) * 100))}</b></div>`)).join("") || '<p class="muted">Sem lançamentos registrados.</p>'}</details>`;
    } catch (e) {
      if (n === detailRequest) $("detail-content").textContent = e.message;
    }
  }
  function commercial() {
    if (!data) return;
    const items = commercialItems();
    $("commercial-summary").textContent =
      `${data.obra.nomenclatura} · ${items.length} itens · ${money(data.resumo.vendido)}`;
    $("commercial-list").innerHTML =
      items
        .map(
          (c, n) =>
            `<div class="commercial-row"><span>${esc(c.nome)}</span><b>${money(Math.round(Number(c.valor) * 100))}</b><button data-commercial="${n}">Editar</button></div>`,
        )
        .join("") ||
      '<p class="muted">Nenhum item cadastrado. Adicione os valores cobrados deste projeto.</p>';
    $("commercial-image").innerHTML = data.imagens
      .map((i) => `<option value="${Number(i.id)}">${esc(i.nome)}</option>`)
      .join("");
  }
  function toggleFields() {
    const photo = $("commercial-form").elements.categoria.value === "foto";
    $("commercial-image-label").hidden = photo;
    document
      .querySelectorAll("[data-image-field]")
      .forEach((e) => (e.hidden = photo));
  }
  function editCommercial(index) {
    const f = $("commercial-form");
    f.reset();
    f.elements.id.value = "";
    const c = index == null ? null : commercialItems()[index];
    if (c)
      for (const [k, v] of Object.entries(c))
        if (f.elements[k]) f.elements[k].value = v ?? "";
    $("commercial-form-title").textContent = c
      ? "Editar valores"
      : "Adicionar item";
    f.hidden = false;
    $("import-form").hidden = true;
    $("commercial-message").textContent = "";
    toggleFields();
    f.scrollIntoView({ block: "nearest" });
    f.elements.valor.focus();
  }
  $("theme-toggle").addEventListener("click", () => {
    const theme =
      document.documentElement.dataset.theme === "light" ? "dark" : "light";
    document.documentElement.dataset.theme = theme;
    try {
      localStorage.setItem("theme", theme);
    } catch (_) {}
  });
  window.addEventListener("storage", (e) => {
    if (e.key === "theme" && ["dark", "light"].includes(e.newValue))
      document.documentElement.dataset.theme = e.newValue;
  });
  $("obra").addEventListener("change", load);
  $("distribution-mode").addEventListener("change", renderDistribution);
  for (const id of ["search", "health-filter"])
    $(id).addEventListener("input", () => {
      page = 0;
      renderImages();
    });
  document.querySelectorAll("[data-sort]").forEach((b) =>
    b.addEventListener("click", () => {
      direction = sort === b.dataset.sort ? -direction : 1;
      sort = b.dataset.sort;
      document
        .querySelectorAll("[data-sort]")
        .forEach((x) => x.closest("th").removeAttribute("aria-sort"));
      b.closest("th").setAttribute(
        "aria-sort",
        direction === 1 ? "ascending" : "descending",
      );
      renderImages();
    }),
  );
  $("prev-page").addEventListener("click", () => {
    page--;
    renderImages();
  });
  $("next-page").addEventListener("click", () => {
    page++;
    renderImages();
  });
  $("images-body").addEventListener("click", (e) => {
    const b = e.target.closest("[data-image]");
    if (b) detail(b.dataset.image);
  });
  document
    .querySelectorAll(".close-dialog")
    .forEach((b) =>
      b.addEventListener("click", () => b.closest("dialog").close()),
    );
  $("image-detail").addEventListener("close", () => detailRequest++);
  $("open-commercial").addEventListener("click", () => {
    commercial();
    $("commercial-form").hidden = true;
    $("import-form").hidden = true;
    $("commercial-message").textContent = "";
    $("commercial-dialog").showModal();
  });
  $("commercial-list").addEventListener("click", (e) => {
    const b = e.target.closest("[data-commercial]");
    if (b) editCommercial(Number(b.dataset.commercial));
  });
  $("add-commercial").addEventListener("click", () => editCommercial(null));
  $("cancel-commercial").addEventListener(
    "click",
    () => ($("commercial-form").hidden = true),
  );
  $("commercial-form").elements.categoria.addEventListener(
    "change",
    toggleFields,
  );
  for (const field of ["valor", "imposto", "comissao_comercial"])
    $("commercial-form").elements[field].addEventListener("input", () => {
      const f = $("commercial-form").elements;
      f.valor_imposto.value = (
        (Number(f.valor.value) * Number(f.imposto.value)) /
        100
      ).toFixed(2);
      f.valor_comissao_comercial.value = (
        (Number(f.valor.value) * Number(f.comissao_comercial.value)) /
        100
      ).toFixed(2);
    });
  $("show-import").addEventListener("click", () => {
    $("import-form").hidden = false;
    $("commercial-form").hidden = true;
  });
  $("commercial-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const b = e.submitter;
    b.disabled = true;
    try {
      const payload = Object.fromEntries(new FormData(e.target));
      payload.obra_id = $("obra").value;
      await api("salvarComercial.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf },
        body: JSON.stringify(payload),
      });
      await load();
      if (data) {
        commercial();
        $("commercial-form").hidden = true;
        $("commercial-message").textContent = "Valores salvos.";
      }
    } catch (err) {
      $("commercial-message").textContent = err.message;
    } finally {
      b.disabled = false;
    }
  });
  $("import-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const b = e.submitter;
    b.disabled = true;
    try {
      const f = new FormData(e.target);
      f.set("obra_id", $("obra").value);
      const r = await api("importar_comerciais.php", {
        method: "POST",
        headers: { "X-CSRF-Token": csrf },
        body: f,
      });
      await load();
      if (data) {
        commercial();
        $("import-form").hidden = true;
        $("commercial-message").textContent = `${r.itens} itens importados.`;
      }
    } catch (err) {
      $("commercial-message").textContent = err.message;
    } finally {
      b.disabled = false;
    }
  });
  const initial = new URLSearchParams(location.search).get("obra_id");
  if (initial && [...$("obra").options].some((o) => o.value === initial))
    $("obra").value = initial;
  load();
})();
