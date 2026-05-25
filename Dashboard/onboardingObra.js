(function () {
  const panel = document.getElementById("onboardingObraPanel");
  const checklistContainer = document.getElementById("onboardingObraChecklist");
  const progressLabel = document.getElementById("onboardingObraProgressLabel");
  const progressFill = document.getElementById("onboardingObraProgressFill");
  const pendingCount = document.getElementById("onboardingObraPendingCount");
  const statusCard = document.getElementById("onboardingObraStatus");
  const summaryCard = document.getElementById("onboardingObraSummary");
  const pendingCard = document.getElementById("onboardingObraPending");
  const pageContainer =
    document.querySelector("body > .container") ||
    document.querySelector(".container");

  if (!panel || !checklistContainer || !progressLabel || !progressFill) {
    return;
  }

  const itemLabels = {
    grupo_cliente: "Grupo cliente criado",
    grupo_interno: "Grupo interno criado",
    imagens_importadas: "Imagens importadas",
    sla_definido: "SLA definido",
    pacotes_definidos: "Pacotes definidos",
  };

  const itemDescriptions = {
    grupo_cliente:
      "Concluir quando o grupo do cliente já estiver criado e pronto para comunicação operacional.",
    grupo_interno:
      "Concluir quando o grupo interno estiver montado com as áreas que vão tocar a obra.",
    imagens_importadas: "Lista base registrada no onboarding inicial.",
    sla_definido:
      "Pacotes contratados e prazos consolidados no start do projeto.",
    pacotes_definidos: "Configuração comercial validada no onboarding.",
  };

  function notify(message, type) {
    if (window.Toastify) {
      Toastify({
        text: message,
        duration: 3200,
        gravity: "top",
        position: "right",
        style: {
          background:
            type === "error"
              ? "linear-gradient(135deg, #dc2626, #991b1b)"
              : "linear-gradient(135deg, #2563eb, #1d4ed8)",
        },
      }).showToast();
      return;
    }
    alert(message);
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function setLockedState(isLocked) {
    document.body.classList.toggle("onboarding-locked", isLocked);
    if (pageContainer) {
      pageContainer.classList.toggle("onboarding-locked", isLocked);
    }
  }

  function packageDetails(packageItem) {
    const details = [];

    if (packageItem.quantidade) {
      details.push(packageItem.quantidade + " img");
    }
    if (packageItem.segundos) {
      details.push(packageItem.segundos + " s");
    }
    if (packageItem.prazo_contratual) {
      details.push(packageItem.prazo_contratual + " d úteis");
    }
    if (packageItem.status) {
      details.push(packageItem.status);
    }

    return details.join(" • ");
  }

  function pendingEntries(checklist) {
    return Object.keys(itemLabels)
      .map((key) => ({
        key: key,
        label: itemLabels[key],
        description: itemDescriptions[key],
        done: !!checklist[key],
      }))
      .filter((entry) => !entry.done);
  }

  function renderStatusCard(data, pendingItems) {
    if (!statusCard) {
      return;
    }

    const isLocked = Number(data.status_obra) === 2 && pendingItems.length > 0;
    statusCard.innerHTML = [
      '<div class="onboarding-obra-card-head">',
      '  <span class="onboarding-obra-eyebrow">Status onboarding</span>',
      '  <h3>Fluxo bloqueante</h3>',
      '</div>',
      '<div class="onboarding-obra-status-stack">',
      '  <span class="onboarding-obra-status-badge ' +
        (isLocked ? 'is-locked' : 'is-ready') +
        '">' +
        (isLocked ? 'Onboarding bloqueado' : 'Pronto para liberar') +
        '</span>',
      '  <p>' +
        escapeHtml(
          isLocked
            ? 'A obra ainda nao deve operar como projeto ativo. Enquanto houver pendencias, a tela permanece restrita ao onboarding.'
            : 'Checklist completo. A liberacao operacional acontece assim que o status for efetivado.',
        ) +
        '</p>',
      '  <div class="onboarding-obra-mini-metrics">',
      '    <div><span>Concluidos</span><strong>' +
        escapeHtml(String(data.completed_items || 0)) +
        '/5</strong></div>',
      '    <div><span>Pendentes</span><strong>' +
        escapeHtml(String(data.pending_items || 0)) +
        '</strong></div>',
      '  </div>',
      '</div>',
    ].join("");
  }

  function renderSummaryCard(summary) {
    if (!summaryCard) {
      return;
    }

    const packages = Array.isArray(summary.packages) ? summary.packages : [];
    const packageHtml = packages.length
      ? packages
          .map(
            (packageItem) =>
              '<div class="onboarding-obra-package-pill">' +
              '<strong>' +
              escapeHtml(packageItem.label || packageItem.tipo || "Pacote") +
              '</strong>' +
              '<span>' +
              escapeHtml(packageDetails(packageItem) || "Sem detalhamento") +
              '</span>' +
              '</div>',
          )
          .join("")
      : '<div class="onboarding-obra-empty">Nenhum pacote registrado.</div>';

    const summaryItems = [
      { label: 'Cliente', value: summary.client_name || 'Nao informado' },
      { label: 'Projeto interno', value: summary.project_internal || 'Nao informado' },
      { label: 'Projeto comercial', value: summary.project_commercial || 'Nao informado' },
      { label: 'Codigo', value: summary.code || 'Nao informado' },
      { label: 'Imagens', value: String(summary.images_count || 0) },
      { label: 'Contatos', value: String(summary.contacts_count || 0) },
    ];

    summaryCard.innerHTML = [
      '<div class="onboarding-obra-card-head">',
      '  <span class="onboarding-obra-eyebrow">Resumo do projeto</span>',
      '  <h3>Snapshot operacional</h3>',
      '</div>',
      '<div class="onboarding-obra-summary-grid">',
      summaryItems
        .map(
          (item) =>
            '<div class="onboarding-obra-summary-item"><span>' +
            escapeHtml(item.label) +
            '</span><strong>' +
            escapeHtml(item.value) +
            '</strong></div>',
        )
        .join(''),
      '</div>',
      '<div class="onboarding-obra-package-list">',
      packageHtml,
      '</div>',
      summary.notes
        ? '<div class="onboarding-obra-note-inline">' +
          '<span>Observacoes</span><strong>' +
          escapeHtml(summary.notes) +
          '</strong></div>'
        : '',
    ].join('');
  }

  function renderPendingCard(pendingItems) {
    if (!pendingCard) {
      return;
    }

    pendingCard.innerHTML = [
      '<div class="onboarding-obra-card-head">',
      '  <span class="onboarding-obra-eyebrow">Pendencias operacionais</span>',
      '  <h3>O que falta liberar</h3>',
      '</div>',
      pendingItems.length
        ? '<div class="onboarding-obra-pending-list">' +
          pendingItems
            .map(
              (item) =>
                '<div class="onboarding-obra-pending-item"><strong>' +
                escapeHtml(item.label) +
                '</strong><span>' +
                escapeHtml(item.description) +
                '</span></div>',
            )
            .join('') +
          '</div>'
        : '<div class="onboarding-obra-empty">Nenhuma pendencia restante.</div>',
      '<div class="onboarding-obra-note-inline is-accent">',
      '  <span>Fechamento automatico</span>',
      '  <strong>Ao concluir os grupos manualmente, a obra volta para o fluxo ativo e o evento ONBOARDING_COMPLETED eh registrado.</strong>',
      '</div>',
    ].join('');
  }

  function resolveObraId() {
    return (
      localStorage.getItem("obraId") ||
      localStorage.getItem("idObra") ||
      new URLSearchParams(window.location.search).get("obra_id") ||
      new URLSearchParams(window.location.search).get("obraId") ||
      ""
    );
  }

  function renderCompletedState() {
    setLockedState(false);
    panel.hidden = false;
    panel.innerHTML = [
      '<div class="onboarding-obra-success">',
      "  <div>",
      "    <strong>Onboarding concluído</strong>",
      "    <p>Os grupos manuais foram finalizados e a obra já voltou para o fluxo ativo.</p>",
      "  </div>",
      '  <span class="onboarding-obra-status is-done">Projeto ativado</span>',
      "</div>",
    ].join("");
  }

  function renderChecklist(data) {
    const checklist = data.checklist || {};
    const entries = Object.keys(itemLabels).map((key) => ({
      key: key,
      done: !!checklist[key],
      label: itemLabels[key],
      description: itemDescriptions[key],
    }));
    const remainingItems = pendingEntries(checklist);

    const completed = Number(data.completed_items || 0);
    const total = entries.length;
    progressLabel.textContent = completed + "/" + total + " concluídos";
    progressFill.style.width = (completed / total) * 100 + "%";
    if (pendingCount) {
      pendingCount.textContent = remainingItems.length + ' pendência(s)';
    }

    checklistContainer.innerHTML = entries
      .map((item) => {
        const isManual =
          item.key === "grupo_cliente" || item.key === "grupo_interno";
        const actionHtml = item.done
          ? '<span class="onboarding-obra-status is-done">Concluído</span>'
          : isManual
            ? '<button type="button" class="onboarding-obra-action" data-onboarding-item="' +
              item.key +
              '">Concluir agora</button>'
            : '<span class="onboarding-obra-status is-pending">Pendente</span>';

        return [
          '<article class="onboarding-obra-item">',
          '  <div class="onboarding-obra-item-main">',
          "    <strong>" + escapeHtml(item.label) + "</strong>",
          "    <span>" + escapeHtml(item.description) + "</span>",
          "  </div>",
          "  <div>" + actionHtml + "</div>",
          "</article>",
        ].join("");
      })
      .join("");

    renderStatusCard(data, remainingItems);
    renderSummaryCard(data.summary || {});
    renderPendingCard(remainingItems);

    panel.hidden = false;
  }

  async function fetchChecklist() {
    const obraId = resolveObraId();
    if (!obraId) {
      return;
    }

    try {
      const response = await fetch(
        "getOnboardingChecklist.php?obra_id=" + encodeURIComponent(obraId),
        {
          credentials: "same-origin",
        },
      );
      const data = await response.json().catch(() => null);

      if (!response.ok || !data || !data.success) {
        throw new Error(
          data && data.message
            ? data.message
            : "Erro ao carregar checklist de onboarding.",
        );
      }

      if (!data.is_onboarding) {
        setLockedState(false);
        panel.hidden = true;
        return;
      }

      renderChecklist(data);
      setLockedState(
        Number(data.status_obra) === 2 && Number(data.pending_items || 0) > 0,
      );
    } catch (error) {
      console.error(error);
      notify(
        error.message || "Erro ao carregar checklist de onboarding.",
        "error",
      );
    }
  }

  async function completeItem(itemKey, button) {
    const obraId = resolveObraId();
    if (!obraId) {
      return;
    }

    button.disabled = true;

    try {
      const response = await fetch("updateOnboardingChecklist.php", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          obra_id: Number(obraId),
          item: itemKey,
        }),
      });
      const data = await response.json().catch(() => null);

      if (!response.ok || !data || !data.success) {
        throw new Error(
          data && data.message
            ? data.message
            : "Erro ao atualizar checklist de onboarding.",
        );
      }

      if (data.completed) {
        renderCompletedState();
        notify("Onboarding concluído. Projeto ativado com sucesso.");
        window.setTimeout(function () {
          window.location.reload();
        }, 1100);
        return;
      }

      notify("Checklist atualizado com sucesso.");
      await fetchChecklist();
    } catch (error) {
      console.error(error);
      notify(
        error.message || "Erro ao atualizar checklist de onboarding.",
        "error",
      );
    } finally {
      button.disabled = false;
    }
  }

  checklistContainer.addEventListener("click", function (event) {
    const button = event.target.closest("[data-onboarding-item]");
    if (!button) {
      return;
    }

    const itemKey = button.getAttribute("data-onboarding-item");
    if (!itemKey) {
      return;
    }

    completeItem(itemKey, button);
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", fetchChecklist);
  } else {
    fetchChecklist();
  }
})();
