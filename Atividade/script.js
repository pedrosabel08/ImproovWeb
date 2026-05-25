/* ============================================================
   ATIVIDADE — Analytics de Uso
   ============================================================ */
(function () {
  "use strict";

  // ── Referências DOM ──────────────────────────────────────
  const btnRefresh = document.getElementById("btnRefresh");
  const btnAplicar = document.getElementById("btnAplicar");
  const btnLimpar = document.getElementById("btnLimpar");
  const tabBtns = document.querySelectorAll(".tab-btn");
  const tabPanels = document.querySelectorAll(".tab-panel");
  const btnOpenFlt = document.getElementById("btnOpenFilters");
  const btnCloseFlt = document.getElementById("btnCloseFilters");
  const filtersPanel = document.getElementById("filtersPanel");
  const filtersBack = document.getElementById("filtersBackdrop");

  // ── Estado ──────────────────────────────────────────────
  let activeTab = "online";
  let onlineInterval = null;
  let chartTelas = null;
  let chartAtivos = null;
  let historicoPage = 1;

  // Detecta tema para Chart.js
  function isDark() {
    return window.matchMedia("(prefers-color-scheme: dark)").matches;
  }

  function getChartColors() {
    const style = getComputedStyle(document.documentElement);
    return {
      text: style.getPropertyValue("--text-secondary").trim() || "#4b5563",
      muted: style.getPropertyValue("--text-muted").trim() || "#9ca3af",
      grid: style.getPropertyValue("--border-table").trim() || "#e8ecf1",
      accent: style.getPropertyValue("--accent").trim() || "#4f80e1",
      bg: style.getPropertyValue("--bg-card").trim() || "#ffffff",
      tooltip: style.getPropertyValue("--bg-filter").trim() || "#ffffff",
    };
  }

  // ── Filtros atuais ───────────────────────────────────────
  function getFilters() {
    return {
      periodo: document.getElementById("filtPeriodo").value,
      usuario_id: document.getElementById("filtUsuario").value,
      tela: document.getElementById("filtTela").value.trim(),
      status: document.getElementById("filtStatus").value,
    };
  }

  function buildQS(extra) {
    const f = Object.assign(getFilters(), extra || {});
    return Object.entries(f)
      .filter(([, v]) => v !== "")
      .map(([k, v]) => encodeURIComponent(k) + "=" + encodeURIComponent(v))
      .join("&");
  }

  // ── Fetch helper ────────────────────────────────────────
  function apiFetch(action, extra) {
    return fetch("data.php?action=" + action + "&" + buildQS(extra)).then(
      function (r) {
        if (!r.ok) throw new Error("HTTP " + r.status);
        return r.json();
      },
    );
  }

  // ── Formatação de data/hora ──────────────────────────────
  function fmtDate(s) {
    if (!s) return "—";
    const d = new Date(s.replace(" ", "T"));
    if (isNaN(d)) return s;
    return (
      d.toLocaleDateString("pt-BR") +
      " " +
      d.toLocaleTimeString("pt-BR", {
        hour: "2-digit",
        minute: "2-digit",
      })
    );
  }

  function fmtTime(s) {
    if (!s) return "—";
    const d = new Date(s.replace(" ", "T"));
    if (isNaN(d)) return s;
    return d.toLocaleTimeString("pt-BR", {
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
    });
  }

  function userInitials(name) {
    if (!name) return "?";
    return name
      .split(" ")
      .slice(0, 2)
      .map(function (w) {
        return w[0];
      })
      .join("")
      .toUpperCase();
  }

  // ── Tempo relativo ───────────────────────────────────────
  function relTime(s) {
    if (!s) return "—";
    const d = new Date(s.replace(" ", "T"));
    if (isNaN(d)) return s;
    const diff = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diff < 60)    return "há " + diff + "s";
    if (diff < 3600)  return "há " + Math.floor(diff / 60) + "min";
    if (diff < 86400) return "há " + Math.floor(diff / 3600) + "h";
    return "há " + Math.floor(diff / 86400) + " dias";
  }

  // ── KPIs ────────────────────────────────────────────────
  function loadKPIs() {
    apiFetch("kpis")
      .then(function (d) {
        document.getElementById("kpiOnline").textContent =
          d.online_agora ?? "—";
        document.getElementById("kpiSessoes").textContent =
          d.sessoes_hoje ?? "—";
        document.getElementById("kpiTotal").textContent = (
          d.total_acessos_hoje ?? 0
        ).toLocaleString("pt-BR");
        document.getElementById("onlineCountNum").textContent =
          d.online_agora ?? "—";

        // Tela mais acessada: mostra apenas o nome curto
        const tela = d.tela_mais_acessada || "—";
        document.getElementById("kpiTela").textContent =
          tela.length > 18 ? tela.slice(0, 17) + "…" : tela;
        document.getElementById("kpiTela").title = tela;

        // Usuário mais ativo
        const usr = d.usuario_mais_ativo || "—";
        const usrEl = document.getElementById("kpiUsuario");
        usrEl.textContent = usr.length > 16 ? usr.slice(0, 15) + "…" : usr;
        usrEl.title = usr;
      })
      .catch(function () {
        /* silent */
      });
  }

  // ── Tab: ONLINE ─────────────────────────────────────────
  function renderOnlineBadge(status) {
    const map = {
      online: ["badge-online", '<span class="badge-dot"></span> Online'],
      ausente: ["badge-ausente", '<span class="badge-dot"></span> Ausente'],
      offline: ["badge-offline", '<span class="badge-dot"></span> Offline'],
    };
    const [cls, label] = map[status] || map.offline;
    return '<span class="badge ' + cls + '">' + label + "</span>";
  }

  function loadOnline() {
    apiFetch("online")
      .then(function (data) {
        const grid = document.getElementById("colabsGrid");
        const rows = data.rows || [];

        if (!rows.length) {
          grid.innerHTML =
            '<div class="colabs-empty"><i class="fa-solid fa-users-slash"></i><p>Nenhum colaborador encontrado.</p></div>';
          return;
        }

        // Atualiza badge de online no header
        const onlineCount = rows.filter(function (r) {
          return r.status === "online";
        }).length;
        document.getElementById("onlineCountNum").textContent = onlineCount;

        grid.innerHTML = rows
          .map(function (r) {
            const initials = userInitials(r.nome_colaborador);
            const st = r.status || "nunca";

            // Badge de status
            const badgeMap = {
              online:  ["badge-online",  "Online"],
              ausente: ["badge-ausente", "Ausente"],
              offline: ["badge-offline", "Offline"],
              nunca:   ["badge-offline", "Nunca acessou"],
            };
            const [badgeCls, badgeLabel] = badgeMap[st] || badgeMap.offline;
            const badge =
              '<span class="badge ' + badgeCls + '">' +
              '<span class="badge-dot"></span> ' + badgeLabel +
              "</span>";

            // Sub-info: tela atual (se ativo) ou última vez vista
            let sub = "";
            if ((st === "online" || st === "ausente") && r.tela_atual) {
              sub =
                '<div class="colab-screen">' +
                '<i class="fa-solid fa-display"></i> ' +
                escHtml(r.tela_atual) +
                "</div>";
            } else if (r.ultima_atividade && st !== "nunca") {
              sub =
                '<div class="colab-lastseen">' +
                '<i class="fa-regular fa-clock"></i> ' +
                relTime(r.ultima_atividade) +
                "</div>";
            } else {
              sub =
                '<div class="colab-lastseen" style="color:var(--text-muted)">Sem registro de acesso</div>';
            }

            return (
              '<div class="colab-card colab-card--' + st + '">' +
              '<div class="colab-avatar-wrap">' +
              '<div class="colab-avatar-big">' + initials + "</div>" +
              '<span class="colab-status-dot colab-status-dot--' + st + '"></span>' +
              "</div>" +
              '<div class="colab-info">' +
              '<div class="colab-name">' + escHtml(r.nome_colaborador) + "</div>" +
              sub +
              "</div>" +
              badge +
              "</div>"
            );
          })
          .join("");

        // Timestamp
        const tsEl = document.getElementById("lastRefreshOnline");
        if (tsEl)
          tsEl.textContent =
            "Atualizado " +
            (data.ts ||
              new Date().toLocaleTimeString("pt-BR", {
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit",
              }));
      })
      .catch(function () {
        document.getElementById("colabsGrid").innerHTML =
          '<div class="colabs-empty"><i class="fa-solid fa-triangle-exclamation"></i><p>Erro ao carregar dados.</p></div>';
      });
  }

  function startOnlineRefresh() {
    stopOnlineRefresh();
    onlineInterval = setInterval(function () {
      loadOnline();
      loadKPIs();
    }, 30000);
  }

  function stopOnlineRefresh() {
    if (onlineInterval) {
      clearInterval(onlineInterval);
      onlineInterval = null;
    }
  }

  // ── Tab: TELAS ──────────────────────────────────────────
  function buildBarChart(canvasId, labels, values, existingChart, label) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    const c = getChartColors();
    const accent = c.accent;

    if (existingChart) {
      existingChart.destroy();
    }

    return new Chart(ctx, {
      type: "bar",
      data: {
        labels: labels,
        datasets: [
          {
            label: label,
            data: values,
            backgroundColor: accent + "33",
            borderColor: accent,
            borderWidth: 1.5,
            borderRadius: 4,
            hoverBackgroundColor: accent + "55",
          },
        ],
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            backgroundColor: c.tooltip,
            titleColor: c.text,
            bodyColor: c.text,
            borderColor: c.grid,
            borderWidth: 1,
            callbacks: {
              label: function (ctx) {
                return " " + ctx.parsed.x.toLocaleString("pt-BR") + " acessos";
              },
            },
          },
        },
        scales: {
          x: {
            grid: {
              color: c.grid,
            },
            ticks: {
              color: c.muted,
              font: {
                size: 11,
              },
            },
          },
          y: {
            grid: {
              display: false,
            },
            ticks: {
              color: c.text,
              font: {
                size: 11,
              },
            },
          },
        },
      },
    });
  }

  function loadTelas() {
    apiFetch("telas")
      .then(function (data) {
        const rows = data.rows || [];
        const tbody = document.getElementById("tbodyTelas");

        if (!rows.length) {
          tbody.innerHTML =
            '<tr class="empty-row"><td colspan="5">Nenhum dado encontrado.</td></tr>';
          if (chartTelas) {
            chartTelas.destroy();
            chartTelas = null;
          }
          return;
        }

        // Tabela
        tbody.innerHTML = rows
          .map(function (r, i) {
            return (
              "<tr>" +
              '<td style="color:var(--text-muted); font-size:11px; font-weight:600;">' +
              (i + 1) +
              "</td>" +
              '<td><span class="screen-chip"><i class="fa-solid fa-display" style="font-size:10px;"></i> ' +
              escHtml(r.tela) +
              "</span></td>" +
              '<td class="col-right" style="font-weight:600;">' +
              Number(r.total_acessos).toLocaleString("pt-BR") +
              "</td>" +
              '<td class="col-right" style="color:var(--text-secondary);">' +
              r.usuarios_unicos +
              "</td>" +
              '<td style="color:var(--text-tertiary); font-size:12px;">' +
              fmtDate(r.ultimo_acesso) +
              "</td>" +
              "</tr>"
            );
          })
          .join("");

        // Gráfico (top 10)
        const top10 = rows.slice(0, 10);
        chartTelas = buildBarChart(
          "chartTelas",
          top10.map(function (r) {
            return r.tela;
          }),
          top10.map(function (r) {
            return r.total_acessos;
          }),
          chartTelas,
          "Acessos",
        );
      })
      .catch(function () {
        document.getElementById("tbodyTelas").innerHTML =
          '<tr class="empty-row"><td colspan="5">Erro ao carregar dados.</td></tr>';
      });
  }

  // ── Tab: HISTÓRICO ───────────────────────────────────────
  function loadHistorico(page) {
    page = page || 1;
    historicoPage = page;
    apiFetch("historico", {
      page: page,
    })
      .then(function (data) {
        const rows = data.rows || [];
        const tbody = document.getElementById("tbodyHistorico");

        if (!rows.length) {
          tbody.innerHTML =
            '<tr class="empty-row"><td colspan="4">Nenhum registro encontrado.</td></tr>';
        } else {
          tbody.innerHTML = rows
            .map(function (r) {
              return (
                "<tr>" +
                '<td><div class="user-chip">' +
                '<div class="user-avatar">' +
                userInitials(r.nome_usuario) +
                "</div>" +
                '<span class="user-name">' +
                escHtml(r.nome_usuario) +
                "</span>" +
                "</div></td>" +
                '<td><span class="screen-chip"><i class="fa-solid fa-display" style="font-size:10px;"></i> ' +
                escHtml(r.tela || "—") +
                "</span></td>" +
                '<td style="font-family:Menlo,Consolas,monospace; font-size:11px; color:var(--text-tertiary);">' +
                escHtml(r.ip || "—") +
                "</td>" +
                '<td style="color:var(--text-tertiary); font-size:12px;">' +
                fmtDate(r.created_at) +
                "</td>" +
                "</tr>"
              );
            })
            .join("");
        }

        renderPagination(
          data.total || 0,
          data.page || 1,
          data.pages || 1,
          data.per_page || 50,
          loadHistorico,
        );
      })
      .catch(function () {
        document.getElementById("tbodyHistorico").innerHTML =
          '<tr class="empty-row"><td colspan="4">Erro ao carregar dados.</td></tr>';
      });
  }

  function renderPagination(total, page, pages, perPage, loadFn) {
    const infoEl = document.getElementById("paginInfoHistorico");
    const ctrlEl = document.getElementById("paginCtrlHistorico");
    if (!infoEl || !ctrlEl) return;

    const from = Math.min(total, (page - 1) * perPage + 1);
    const to = Math.min(total, page * perPage);
    infoEl.textContent =
      "Exibindo " + from + "–" + to + " de " + total.toLocaleString("pt-BR");

    let html = "";
    // Prev
    html +=
      '<button class="page-btn" ' +
      (page <= 1 ? "disabled" : "") +
      ' data-p="' +
      (page - 1) +
      '"><i class="fa-solid fa-chevron-left"></i></button>';

    // Window of pages
    const start = Math.max(1, page - 2);
    const end = Math.min(pages, page + 2);
    for (let p = start; p <= end; p++) {
      html +=
        '<button class="page-btn' +
        (p === page ? " current" : "") +
        '" data-p="' +
        p +
        '">' +
        p +
        "</button>";
    }

    // Next
    html +=
      '<button class="page-btn" ' +
      (page >= pages ? "disabled" : "") +
      ' data-p="' +
      (page + 1) +
      '"><i class="fa-solid fa-chevron-right"></i></button>';

    ctrlEl.innerHTML = html;
    ctrlEl
      .querySelectorAll(".page-btn:not(:disabled):not(.current)")
      .forEach(function (btn) {
        btn.addEventListener("click", function () {
          loadFn(parseInt(this.dataset.p, 10));
        });
      });
  }

  // ── Tab: MAIS ATIVOS ─────────────────────────────────────
  function loadAtivos() {
    apiFetch("ativos")
      .then(function (data) {
        const rows = data.rows || [];
        const tbody = document.getElementById("tbodyAtivos");

        if (!rows.length) {
          tbody.innerHTML =
            '<tr class="empty-row"><td colspan="5">Nenhum dado encontrado.</td></tr>';
          if (chartAtivos) {
            chartAtivos.destroy();
            chartAtivos = null;
          }
          return;
        }

        tbody.innerHTML = rows
          .map(function (r, i) {
            return (
              "<tr>" +
              '<td style="color:var(--text-muted); font-size:11px; font-weight:600;">' +
              (i + 1) +
              "</td>" +
              '<td><div class="user-chip">' +
              '<div class="user-avatar">' +
              userInitials(r.nome_usuario) +
              "</div>" +
              '<span class="user-name">' +
              escHtml(r.nome_usuario) +
              "</span>" +
              "</div></td>" +
              '<td class="col-right" style="font-weight:600;">' +
              Number(r.total_acessos).toLocaleString("pt-BR") +
              "</td>" +
              '<td style="color:var(--text-tertiary); font-size:12px;">' +
              fmtDate(r.ultima_atividade) +
              "</td>" +
              "<td>" +
              (r.tela_mais_acessada
                ? '<span class="screen-chip"><i class="fa-solid fa-display" style="font-size:10px;"></i> ' +
                  escHtml(r.tela_mais_acessada) +
                  "</span>"
                : '<span style="color:var(--text-muted)">—</span>') +
              "</td>" +
              "</tr>"
            );
          })
          .join("");

        // Gráfico top 10
        const top10 = rows.slice(0, 10);
        chartAtivos = buildBarChart(
          "chartAtivos",
          top10.map(function (r) {
            return r.nome_usuario || "ID " + r.usuario_id;
          }),
          top10.map(function (r) {
            return r.total_acessos;
          }),
          chartAtivos,
          "Acessos",
        );
      })
      .catch(function () {
        document.getElementById("tbodyAtivos").innerHTML =
          '<tr class="empty-row"><td colspan="5">Erro ao carregar dados.</td></tr>';
      });
  }

  // ── Tab dispatcher ───────────────────────────────────────
  function loadTab(tab) {
    // Oculta filtro de status fora da aba Online
    const grpStatus = document.getElementById("grpStatus");
    if (grpStatus) grpStatus.style.display = tab === "online" ? "" : "none";

    switch (tab) {
      case "online":
        loadOnline();
        startOnlineRefresh();
        break;
      case "telas":
        stopOnlineRefresh();
        loadTelas();
        break;
      case "historico":
        stopOnlineRefresh();
        loadHistorico(1);
        break;
      case "ativos":
        stopOnlineRefresh();
        loadAtivos();
        break;
    }
  }

  // ── Escape HTML ──────────────────────────────────────────
  function escHtml(str) {
    if (str == null) return "—";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  // ── Refresh manual ───────────────────────────────────────
  function refreshAll() {
    btnRefresh.classList.add("spinning");
    loadKPIs();
    loadTab(activeTab);
    setTimeout(function () {
      btnRefresh.classList.remove("spinning");
    }, 600);
  }

  // ── Event listeners ──────────────────────────────────────
  tabBtns.forEach(function (btn) {
    btn.addEventListener("click", function () {
      const tab = this.dataset.tab;
      if (tab === activeTab) return;
      activeTab = tab;

      tabBtns.forEach(function (b) {
        b.classList.remove("active");
        b.setAttribute("aria-selected", "false");
      });
      tabPanels.forEach(function (p) {
        p.classList.remove("active");
      });

      this.classList.add("active");
      this.setAttribute("aria-selected", "true");
      const panel = document.getElementById("panel-" + tab);
      if (panel) panel.classList.add("active");

      loadTab(tab);
    });
  });

  btnRefresh.addEventListener("click", refreshAll);

  btnAplicar.addEventListener("click", function () {
    historicoPage = 1;
    loadKPIs();
    loadTab(activeTab);
    closeSheet();
  });

  btnLimpar.addEventListener("click", function () {
    document.getElementById("filtPeriodo").value = "today";
    document.getElementById("filtUsuario").value = "";
    document.getElementById("filtTela").value = "";
    document.getElementById("filtStatus").value = "";
    historicoPage = 1;
    loadKPIs();
    loadTab(activeTab);
    closeSheet();
  });

  // ── Bottom-sheet (mobile/tablet) ─────────────────────────
  function openSheet() {
    filtersPanel.classList.add("sheet-open");
    filtersBack.classList.add("is-visible");
    btnOpenFlt.setAttribute("aria-expanded", "true");
  }

  function closeSheet() {
    filtersPanel.classList.remove("sheet-open");
    filtersBack.classList.remove("is-visible");
    btnOpenFlt.setAttribute("aria-expanded", "false");
  }

  if (btnOpenFlt) btnOpenFlt.addEventListener("click", openSheet);
  if (btnCloseFlt) btnCloseFlt.addEventListener("click", closeSheet);
  if (filtersBack) filtersBack.addEventListener("click", closeSheet);

  // ── Tema adaptativo para gráficos ───────────────────────
  window
    .matchMedia("(prefers-color-scheme: dark)")
    .addEventListener("change", function () {
      if (activeTab === "telas") loadTelas();
      if (activeTab === "ativos") loadAtivos();
    });

  // ── Init ─────────────────────────────────────────────────
  loadKPIs();
  loadTab("online");
})();
