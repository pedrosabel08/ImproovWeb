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
  let _currentColabId = null;
  let _listenersWired = false;

  /* ── Heatmap state ───────────────────────────────────────────── */
  let _heatmapFuncaoId = 0;
  let _heatmapTipoImg = "";
  let _heatmapFiltersPopulated = false;

  /* ── Entry point (called by scriptIndex.js) ─────────────────── */
  window.initColabDashboard = function () {
    const d = new Date();
    _currentMes = _currentMes || String(d.getMonth() + 1).padStart(2, "0");
    _currentAno = _currentAno || String(d.getFullYear());

    // Wire up selectors once
    if (!_listenersWired) {
      _listenersWired = true;

      const mesSel = document.getElementById("colab-mes-seletor");
      if (mesSel) {
        mesSel.addEventListener("change", function () {
          const [ano, mes] = this.value.split("-");
          _currentAno = ano;
          _currentMes = mes;
          _loaded = false;
          _loadData();
        });
      }

      const heatmapFuncaoSel = document.getElementById("heatmap-funcao");
      if (heatmapFuncaoSel) {
        heatmapFuncaoSel.addEventListener("change", function () {
          _heatmapFuncaoId = parseInt(this.value, 10) || 0;
          _loadHeatmap(_currentMes, _currentAno);
        });
      }

      const heatmapTipoSel = document.getElementById("heatmap-tipo");
      if (heatmapTipoSel) {
        heatmapTipoSel.addEventListener("change", function () {
          _heatmapTipoImg = this.value;
          _loadHeatmap(_currentMes, _currentAno);
        });
      }

      const colabSel = document.getElementById("colab-colab-seletor");
      if (colabSel) {
        colabSel.addEventListener("change", function () {
          _currentColabId = this.value ? parseInt(this.value, 10) : null;
          _loaded = false;
          _currentMes = null;
          _currentAno = null;
          const dd = new Date();
          _currentMes = String(dd.getMonth() + 1).padStart(2, "0");
          _currentAno = String(dd.getFullYear());
          const ms = document.getElementById("colab-mes-seletor");
          if (ms) ms.innerHTML = '<option value="">Carregando...</option>';
          _loadData();
        });
      }
    }

    _loadData();
  };

  /* ── Data fetch ──────────────────────────────────────────────── */
  function _loadData() {
    let url = `PaginaPrincipal/Overview/getDashboardColaborador.php?mes=${_currentMes}&ano=${_currentAno}`;
    if (_currentColabId) url += `&colaborador_id=${_currentColabId}`;

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
        _loadHeatmap(_currentMes, _currentAno);
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

  /* ── Activity Heatmap ────────────────────────────────────────── */

  function _loadHeatmap(mes, ano) {
    if (!mes || !ano) return;
    const container = document.getElementById("heatmap-container");
    const avgLabel = document.getElementById("heatmap-avg-label");
    const mesLabel = document.getElementById("heatmap-mes-label");
    if (!container) return;

    container.innerHTML = '<div class="hm-loading">Carregando...</div>';

    const url =
      "PaginaPrincipal/buscar_heatmap.php" +
      "?mes=" +
      encodeURIComponent(mes) +
      "&ano=" +
      encodeURIComponent(ano) +
      "&funcao_id=" +
      encodeURIComponent(_heatmapFuncaoId) +
      "&tipo_imagem=" +
      encodeURIComponent(_heatmapTipoImg);

    fetch(url)
      .then(function (r) {
        if (!r.ok) throw new Error("HTTP " + r.status);
        return r.json();
      })
      .then(function (data) {
        if (data.error) throw new Error(data.error);

        var mesesPt = [
          "",
          "Janeiro",
          "Fevereiro",
          "Março",
          "Abril",
          "Maio",
          "Junho",
          "Julho",
          "Agosto",
          "Setembro",
          "Outubro",
          "Novembro",
          "Dezembro",
        ];
        if (mesLabel) {
          mesLabel.textContent = (mesesPt[parseInt(mes, 10)] || "") + " " + ano;
        }

        _populateHeatmapFilters(data.funcoes || [], data.tipos_imagem || []);
        _renderHeatmapCalendar(container, data);

        if (avgLabel) {
          var mediaFmt = parseFloat(data.media_diaria)
            .toFixed(1)
            .replace(".", ",");
          avgLabel.textContent =
            "Média histórica: " + mediaFmt + " tarefas/dia";
        }
      })
      .catch(function (err) {
        console.error("heatmap: erro", err);
        container.innerHTML =
          '<div class="hm-loading" style="color:#f87171">Erro ao carregar heatmap.</div>';
      });
  }

  function _getHeatmapLevel(total, t1, t2) {
    if (total === 0) return 0;
    if (total <= t1) return 1;
    if (total <= t2) return 2;
    return 3;
  }

  function _renderHeatmapCalendar(container, data) {
    var mes = parseInt(data.mes, 10);
    var ano = parseInt(data.ano, 10);
    var porDia = data.por_dia || {};
    var t1 = parseInt(data.t1, 10);
    var t2 = parseInt(data.t2, 10);

    var diasNomes = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"];
    var primeiroDia = new Date(ano, mes - 1, 1);
    var totalDias = new Date(ano, mes, 0).getDate();
    var today = new Date();
    var todayStr =
      today.getFullYear() +
      "-" +
      String(today.getMonth() + 1).padStart(2, "0") +
      "-" +
      String(today.getDate()).padStart(2, "0");

    var offset = primeiroDia.getDay(); // 0=Dom … 6=Sáb
    var cells = [];
    for (var i = 0; i < offset; i++) cells.push(null);
    for (var d = 1; d <= totalDias; d++) cells.push(d);
    while (cells.length % 7 !== 0) cells.push(null);

    var html =
      '<div class="hm-calendar-header"><div class="hm-week-num"></div>';
    diasNomes.forEach(function (n) {
      html += '<div class="hm-day-label">' + n + "</div>";
    });
    html += "</div>";

    var weeks = cells.length / 7;
    for (var w = 0; w < weeks; w++) {
      html +=
        '<div class="hm-week"><div class="hm-week-num">' + (w + 1) + "ª</div>";
      for (var dd = 0; dd < 7; dd++) {
        var day = cells[w * 7 + dd];
        if (day === null) {
          html += '<div class="hm-cell hm-empty"></div>';
        } else {
          var dateStr =
            ano +
            "-" +
            String(mes).padStart(2, "0") +
            "-" +
            String(day).padStart(2, "0");
          var total = porDia[dateStr] || 0;
          var level = _getHeatmapLevel(total, t1, t2);
          var isToday = dateStr === todayStr ? " hm-today" : "";
          var label = total > 0 ? total : "";
          var tooltip =
            total > 0
              ? total +
                " tarefa" +
                (total > 1 ? "s" : "") +
                " em " +
                String(day).padStart(2, "0") +
                "/" +
                String(mes).padStart(2, "0") +
                "/" +
                ano
              : "Nenhuma tarefa em " +
                String(day).padStart(2, "0") +
                "/" +
                String(mes).padStart(2, "0") +
                "/" +
                ano;
          html +=
            '<div class="hm-cell hm-l' +
            level +
            isToday +
            '" title="' +
            tooltip +
            '">' +
            label +
            "</div>";
        }
      }
      html += "</div>";
    }

    container.innerHTML = html;
  }

  function _populateHeatmapFilters(funcoes, tiposImagem) {
    if (_heatmapFiltersPopulated) return;
    _heatmapFiltersPopulated = true;

    var selFuncao = document.getElementById("heatmap-funcao");
    var selTipo = document.getElementById("heatmap-tipo");

    if (selFuncao && funcoes.length) {
      funcoes.forEach(function (f) {
        var opt = document.createElement("option");
        opt.value = f.id;
        opt.textContent = f.nome;
        selFuncao.appendChild(opt);
      });
    }

    if (selTipo && tiposImagem.length) {
      tiposImagem.forEach(function (t) {
        var opt = document.createElement("option");
        opt.value = t;
        opt.textContent = t.charAt(0).toUpperCase() + t.slice(1);
        selTipo.appendChild(opt);
      });
    }
  }
})();
