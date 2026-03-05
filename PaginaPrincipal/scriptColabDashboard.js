/**
 * scriptColabDashboard.js
 * Personal monthly production dashboard for collaborators.
 * Loaded by inicio.php; the init entry-point is called from scriptIndex.js
 * when a non-gestor user clicks "Visão Geral".
 */

(function () {
  "use strict";

  /* ── State ───────────────────────────────────────────────────── */
  let _loaded = false;
  let _currentMes = null;
  let _currentAno = null;

  /* ── Entry point (called by scriptIndex.js) ─────────────────── */
  window.initColabDashboard = function () {
    const d = new Date();
    _currentMes = _currentMes || String(d.getMonth() + 1).padStart(2, "0");
    _currentAno = _currentAno || String(d.getFullYear());

    // Wire up month selector (once)
    if (!_loaded) {
      const sel = document.getElementById("colab-mes-seletor");
      if (sel) {
        sel.addEventListener("change", function () {
          const [ano, mes] = this.value.split("-");
          _currentAno = ano;
          _currentMes = mes;
          _loaded = false; // force reload on month change
          _loadData();
        });
      }
    }

    _loadData();
  };

  /* ── Data fetch ──────────────────────────────────────────────── */
  function _loadData() {
    const url = `PaginaPrincipal/Overview/getDashboardColaborador.php?mes=${_currentMes}&ano=${_currentAno}`;

    _setLoadingState(true);

    fetch(url)
      .then(function (r) {
        if (!r.ok) throw new Error("HTTP " + r.status);
        return r.json();
      })
      .then(function (data) {
        if (data.error) throw new Error(data.error);

        // Populate month selector on first load
        if (!_loaded) {
          _populateMesSeletor(data.meses_disponiveis || []);
        }

        _renderKpis(data.kpis || {});
        _renderEtapas(data.por_etapa || []);
        _renderTarefas(data.tarefas || []);
        _updateMesLabel(data.kpis || {});

        _loaded = true;
      })
      .catch(function (err) {
        console.error("colabDashboard: erro ao carregar dados", err);
        _showToastError("Erro ao carregar painel de produção.");
        _setLoadingState(false);
      });
  }

  /* ── Month selector ──────────────────────────────────────────── */
  function _populateMesSeletor(meses) {
    const sel = document.getElementById("colab-mes-seletor");
    if (!sel) return;
    sel.innerHTML = "";
    meses.forEach(function (m) {
      const opt = document.createElement("option");
      opt.value = m.valor; // "YYYY-MM"
      opt.textContent = m.label;
      if (m.valor === _currentAno + "-" + _currentMes) opt.selected = true;
      sel.appendChild(opt);
    });
  }

  function _updateMesLabel(kpis) {
    const el = document.getElementById("colab-mes-nome");
    if (el && kpis.mes_label) el.textContent = kpis.mes_label;
  }

  /* ── KPI cards ───────────────────────────────────────────────── */
  function _renderKpis(k) {
    _setKpiValue("colab-kpi-novas", k.total_novas ?? 0, false);
    _setKpiValue("colab-kpi-valor", _formatBRL(k.valor_a_receber ?? 0), true);
    _setKpiValue(
      "colab-kpi-ajustes",
      k.media_ajustes != null ? parseFloat(k.media_ajustes).toFixed(1) : "0.0",
      true,
    );
  }

  function _setKpiValue(id, value, isText) {
    const el = document.getElementById(id);
    if (!el) return;
    if (isText) {
      el.textContent = value;
    } else {
      _animateCount(el, Number(value));
    }
  }

  function _animateCount(el, target) {
    const duration = 600;
    const start = performance.now();
    const from = parseInt(el.textContent) || 0;
    function step(now) {
      const progress = Math.min((now - start) / duration, 1);
      const ease = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.round(from + (target - from) * ease);
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  /* ── Etapas grid ─────────────────────────────────────────────── */
  function _renderEtapas(etapas) {
    const grid = document.getElementById("colab-etapas-grid");
    const badge = document.getElementById("colab-etapas-count");
    if (!grid) return;

    if (badge) badge.textContent = etapas.length;

    if (!etapas.length) {
      grid.innerHTML =
        '<p style="color:var(--cd-muted,#94a3b8);font-size:13px;">Nenhuma etapa encontrada.</p>';
      return;
    }

    grid.innerHTML = etapas
      .map(function (e) {
        const tempoStr =
          e.tempo_medio_horas != null
            ? "⌀ " + _formatTempo(e.tempo_medio_horas)
            : "";
        return `<div class="etapa-card">
          <div class="etapa-nome">${_esc(e.nome_funcao)}</div>
          <div class="etapa-total">${e.total}</div>
          ${tempoStr ? `<div class="etapa-tempo">${_esc(tempoStr)}</div>` : ""}
        </div>`;
      })
      .join("");
  }

  /* ── Tasks table ─────────────────────────────────────────────── */
  function _renderTarefas(tarefas) {
    const tbody = document.getElementById("colab-tasks-body");
    const badge = document.getElementById("colab-tarefas-count");
    if (!tbody) return;

    if (badge) badge.textContent = tarefas.length;

    if (!tarefas.length) {
      tbody.innerHTML =
        '<tr><td colspan="6" class="empty-row">Nenhuma tarefa no período.</td></tr>';
      return;
    }

    tbody.innerHTML = tarefas
      .map(function (t) {
        const statusCls = _getStatusClass(t.status);
        const pagoParcialCount = parseInt(t.pago_parcial_count || 0, 10);
        const pagoCompletaCount = parseInt(t.pago_completa_count || 0, 10);
        const isFinalizacaoCompleta =
          parseInt(t.funcao_id, 10) === 4 &&
          pagoParcialCount > 0 &&
          pagoCompletaCount === 0;
        const isPago = parseInt(t.pagamento) === 1 && !isFinalizacaoCompleta;
        const valorCls = isPago ? "valor-pago" : "valor-pendente";

        // Build tooltip text
        let pagoTooltip = "";
        if (isFinalizacaoCompleta) {
          // Show which partial payment was already made
          if (t.pagamentos_info) {
            const parcialLines = t.pagamentos_info
              .split(";")
              .filter(function (entry) {
                return entry.indexOf("Finalização Parcial") !== -1;
              })
              .map(function (entry) {
                const parts = entry.split("|");
                return parts[1] ? parts[0] + " pago em " + parts[1] : parts[0];
              });
            pagoTooltip =
              (parcialLines.length ? parcialLines.join("\n") + "\n" : "") +
              "Aguardando pagamento final";
          } else {
            pagoTooltip = "Aguardando pagamento final";
          }
        } else if (isPago && t.pagamentos_info) {
          pagoTooltip = t.pagamentos_info
            .split(";")
            .map(function (entry) {
              const parts = entry.split("|");
              return parts[1] ? parts[0] + " pago em " + parts[1] : parts[0];
            })
            .join("\n");
        } else if (isPago) {
          pagoTooltip = "Pago";
        } else {
          pagoTooltip = "Pendente";
        }

        let pagoIcon, pagoBadge;
        if (isPago) {
          pagoIcon = '<i class="ri-checkbox-circle-fill icone-pago"></i>';
          pagoBadge =
            '<span class="pago-badge pago-badge-completo">Pago</span>';
        } else if (isFinalizacaoCompleta) {
          pagoIcon = '<i class="ri-time-line icone-pago-parcial"></i>';
          pagoBadge =
            '<span class="pago-badge pago-badge-parcial">Pago Parcial</span>';
        } else {
          pagoIcon = '<i class="ri-time-line icone-pendente"></i>';
          pagoBadge = "";
        }

        const ajuste = parseInt(t.qtd_ajustes) || 0;
        const ajusteCls =
          ajuste === 0
            ? "ajuste-zero"
            : ajuste === 1
              ? "ajuste-um"
              : "ajuste-mais";

        return `<tr>
          <td>${_esc(t.imagem_nome || "—")}</td>
          <td>${_esc(t.nome_funcao || "—")}</td>
          <td><span class="status-badge ${statusCls}">${_esc(t.status || "—")}</span></td>
          <td class="col-right"><span class="${valorCls}">${_formatBRL(t.valor)}</span></td>
          <td class="col-center pago-cell" data-tooltip="${_esc(pagoTooltip)}">${pagoIcon}${pagoBadge}</td>
          <td class="col-center"><span class="${ajusteCls}">${ajuste}</span></td>
        </tr>`;
      })
      .join("");

    _initPagoTooltips(tbody);
  }

  /* ── Pago tooltip wiring ──────────────────────────────────────── */
  function _getOrCreateTooltip() {
    let tip = document.getElementById("colab-pago-tooltip");
    if (!tip) {
      tip = document.createElement("div");
      tip.id = "colab-pago-tooltip";
      tip.style.cssText =
        "position:fixed;z-index:9999;background:#1e293b;color:#e6eef8;" +
        "font-size:12px;padding:6px 10px;border-radius:6px;" +
        "border:1px solid rgba(255,255,255,0.1);pointer-events:none;" +
        "white-space:pre-line;display:none;max-width:280px;line-height:1.5;" +
        "box-shadow:0 4px 12px rgba(0,0,0,0.4)";
      document.body.appendChild(tip);
    }
    return tip;
  }

  function _initPagoTooltips(tbody) {
    const tip = _getOrCreateTooltip();
    tbody.querySelectorAll(".pago-cell[data-tooltip]").forEach(function (cell) {
      cell.addEventListener("mouseenter", function (e) {
        tip.textContent = cell.dataset.tooltip;
        tip.style.display = "block";
        tip.style.left = e.clientX + "px";
        tip.style.top = e.clientY - 30 + "px";
      });
      cell.addEventListener("mouseleave", function () {
        tip.style.display = "none";
      });
      cell.addEventListener("mousemove", function (e) {
        tip.style.left = e.clientX + "px";
        tip.style.top = e.clientY - 30 + "px";
      });
    });
  }

  /* ── Loading state ───────────────────────────────────────────── */
  function _setLoadingState(on) {
    const ids = ["colab-kpi-novas", "colab-kpi-valor", "colab-kpi-ajustes"];
    ids.forEach(function (id) {
      const el = document.getElementById(id);
      if (!el) return;
      if (on) {
        el.innerHTML = '<span class="kpi-loading"></span>';
      }
    });

    const grid = document.getElementById("colab-etapas-grid");
    if (grid && on) {
      grid.innerHTML = Array(4)
        .fill('<div class="etapa-skeleton"></div>')
        .join("");
    }

    const tbody = document.getElementById("colab-tasks-body");
    if (tbody && on) {
      tbody.innerHTML =
        '<tr><td colspan="6" class="empty-row">Carregando...</td></tr>';
    }
  }

  /* ── Helpers ─────────────────────────────────────────────────── */
  function _formatBRL(val) {
    const n = parseFloat(val) || 0;
    return n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  }

  function _formatTempo(horas) {
    const h = parseFloat(horas);
    if (isNaN(h)) return "—";
    if (h < 1) return Math.round(h * 60) + "min";
    if (h < 24) {
      const hInt = Math.floor(h);
      const m = Math.round((h - hInt) * 60);
      return m > 0 ? `${hInt}h ${m}min` : `${hInt}h`;
    }
    const d = Math.floor(h / 24);
    const rem = Math.round(h % 24);
    return rem > 0 ? `${d}d ${rem}h` : `${d}d`;
  }

  function _getStatusClass(status) {
    if (!status) return "status-default";
    const s = status.toLowerCase();
    if (s === "finalizado") return "status-finalizado";
    if (s === "em andamento") return "status-andamento";
    if (s === "em aprovação" || s === "em aprovacao") return "status-aprovacao";
    if (s === "ajuste" || s === "em ajuste") return "status-ajuste";
    if (s === "aprovado com ajustes") return "status-aprovado-ajuste";
    if (s === "aprovado") return "status-aprovado";
    return "status-default";
  }

  function _esc(str) {
    return String(str ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function _showToastError(msg) {
    if (window.Toastify) {
      Toastify({
        text: msg,
        duration: 4000,
        gravity: "top",
        position: "right",
        style: { background: "#fb7185" },
      }).showToast();
    }
  }
})();
