(() => {
  const dialog = document.getElementById("deadlineDiagnosis");
  if (!dialog) return;
  const content = document.getElementById("deadlineDiagnosisContent");
  const status = document.getElementById("deadlineDiagnosisStatus");
  const more = document.getElementById("deadlineDiagnosisMore");
  const state = { open: "Aberto", monitoring: "Em monitoramento", resolved: "Resolvido" };
  const role = { cause: "Causa", symptom: "Sintoma" };
  const historyLabels = {
    opened: "Incidente aberto", reopened: "Incidente reaberto", monitoring: "Em monitoramento",
    resolved: "Incidente resolvido", open: "Incidente voltou ao estado aberto",
    alert_shadow: "Evento em shadow", alert_active: "Evento enfileirado no Flow Connect",
  };
  const unavailable = {
    worker: "Worker", task_id: "Tarefa", frame: "Frames", occurred_at: "Horário da ocorrência",
    plugin: "Plugin", peak_ram: "Pico de RAM", renderer: "Renderer",
    worker_capacity: "Capacidade do Worker", report_body: "Conteúdo do relatório",
    full_excerpt: "Parte do log foi omitida pelo limite de coleta",
  };
  let scope;
  let next;
  let request;

  function node(tag, text, parent) {
    const element = document.createElement(tag);
    if (text !== null && text !== undefined) element.textContent = text;
    parent?.append(element);
    return element;
  }

  function time(value) {
    return value ? new Date(value.replace(" ", "T") + "Z").toLocaleString("pt-BR") : "Indisponível";
  }

  function fields(parent, entries) {
    const list = node("dl", null, parent);
    list.className = "render-ef-details";
    for (const [label, value] of entries) {
      if (value === null || value === undefined || value === "") continue;
      const item = node("div", null, list);
      node("dt", label, item);
      node("dd", value, item);
    }
  }

  function list(parent, lines) {
    if (!lines?.length) return;
    const items = node("ul", null, parent);
    for (const line of lines) node("li", line, items);
  }

  function severity(value) {
    return value === "CRITICO" ? "Crítica" : "Requer atenção";
  }

  function gib(bytes) {
    return bytes == null ? null : `${(bytes / (1024 ** 3)).toLocaleString("pt-BR", { maximumFractionDigits: 1 })} GiB`;
  }

  function occurrence(item, expand) {
    const diagnosis = item.diagnosis;
    const evidence = item.evidence;
    const report = item.report;
    const details = node("details", null, content);
    details.open = expand;
    node("summary", `${time(item.occurred_at)} · ${item.worker || "Worker indisponível"} · ${diagnosis?.category_label || "Relatório pendente"}`, details);
    const capacity = report.capacity || {};
    const assessment = diagnosis?.confidence === "indeterminado" ? "Diagnóstico" :
      diagnosis?.role === "symptom" ? "Sintoma identificado" : "Causa";
    fields(details, [
      ["Erro registrado", evidence.main], [assessment, diagnosis?.diagnosis],
      ["Confiança", diagnosis?.confidence || "indeterminado"], ["Severidade", diagnosis && severity(diagnosis.severity)],
      ["Estado", item.incident_state && (item.episode === item.current_episode ? state[item.incident_state] : "Resolvido em episódio anterior")],
      ["Incidente", item.incident_id && `#${item.incident_id} · Episódio ${item.episode}`],
      ["Job", item.job_name || item.job_id], ["ID do job", item.job_id],
      ["Coletado em", item.collected_at && time(item.collected_at)],
      ["Tarefa", item.task_id], ["Frames", item.frame], ["Processo", evidence.process],
      ["Exceção", evidence.exception], ["Aplicativo", evidence.application_version],
      ["Renderer", evidence.renderer], ["Plugin", report.plugin],
      ["RAM reportada no momento da falha", evidence.ram_percent == null ? null : `${evidence.ram_percent}%`],
      ["Pico de RAM (valor do relatório)", report.peak_ram], ["RAM física informada na coleta", gib(capacity.physical_bytes)],
      ["RAM disponível na coleta", gib(capacity.free_bytes)], ["Requisito declarado de RAM", gib(capacity.required_bytes)],
      ["Grupos do Worker na coleta", capacity.groups?.join(", ")], ["Pools do Worker na coleta", capacity.pools?.join(", ")],
      ["Grupo do job", report.job_group], ["Pool do job", report.job_pool],
      ["Explicação", diagnosis?.technical], ["Recomendação", diagnosis?.recommendation],
      ["Regra", diagnosis && `${diagnosis.rule} v${diagnosis.version} · Catálogo v${diagnosis.catalog_version}`],
      ["Assinatura", item.signature], ["Relatório no Deadline Monitor", item.report_id],
    ]);
    if (item.original_url) {
      const link = node("a", "Abrir relatório original", details);
      link.href = item.original_url;
      link.target = "_blank";
      link.rel = "noopener noreferrer";
    }
    if (diagnosis?.alternatives.length) {
      node("h3", "Sintomas e hipóteses alternativas", details);
      list(details, diagnosis.alternatives.map((item) => `${role[item.role]} · ${item.confidence} · ${item.technical}`));
    }
    if (diagnosis?.factors.length) {
      node("h3", "Possíveis fatores contribuintes", details);
      list(details, diagnosis.factors);
    }
    if (evidence.unavailable?.length) {
      node("h3", "Dados indisponíveis ou parciais", details);
      list(details, evidence.unavailable.map((key) => unavailable[key] || key));
    }
    if (evidence.excerpts?.length) {
      const source = node("details", null, details);
      node("summary", "Trechos do relatório", source);
      node("pre", evidence.excerpts.join("\n"), source);
    }
    if (item.classifications.length > 1) {
      const history = node("details", null, details);
      node("summary", "Histórico de classificação", history);
      list(history, item.classifications.map((event) => `${time(event.recorded_at)} · ${event.rule} · ${event.confidence} · Catálogo v${event.catalog_version}`));
    }
  }

  async function load(params, append = false) {
    request?.abort();
    const controller = new AbortController();
    request = controller;
    more.hidden = true;
    status.textContent = "Consultando o Deadline no Flow…";
    if (!append) {
      scope = params;
      content.replaceChildren();
      if (params.incident_id) {
        const back = node("button", "Voltar aos incidentes", content);
        back.type = "button";
        back.className = "btn-action";
        back.addEventListener("click", () => load({}));
      }
    }
    try {
      const query = new URLSearchParams({ action: "getDeadlineDiagnoses", ...params });
      const response = await fetch(`ajax.php?${query}`, { signal: controller.signal });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message);
      status.textContent = "";
      if (!append && result.incident) {
        const incident = result.incident;
        node("h3", `Incidente #${incident.id} · ${state[incident.state]}`, content);
        fields(content, [
          ["Worker", incident.worker], ["Episódio", incident.episode],
          ["Primeira ocorrência", time(incident.first_at)], ["Última ocorrência", time(incident.last_at)],
          ["Jobs neste episódio", result.correlation.jobs_in_incident],
          ["Workers com a mesma assinatura nos últimos 30 dias", result.correlation.workers_with_signature],
          ["Jobs com falha neste Worker nos últimos 30 dias", result.correlation.jobs_on_worker],
          ["Workers com falhas nos jobs deste episódio nos últimos 30 dias", result.correlation.workers_on_jobs],
          ["Falhas do mesmo renderer nos últimos 30 dias", result.correlation.renderer_failures],
          ["Resolução", incident.resolved_at && `${time(incident.resolved_at)} · ${incident.resolution_reason}`],
        ]);
        if (result.history.length) {
          const history = node("details", null, content);
          node("summary", "Histórico do incidente", history);
          list(history, result.history.map((event) => [time(event.recorded_at), historyLabels[event.kind],
            `Episódio ${event.episode}`, event.detail?.reason || event.detail?.action].filter(Boolean).join(" · ")));
        }
        node("h3", "Ocorrências", content);
      }
      if (result.incidents) {
        for (const item of result.incidents) {
          const line = node("p", null, content);
          const label = `#${item.id} · ${item.worker || "Worker indisponível"} · ${item.diagnosis?.category_label || "Diagnóstico pendente"} · ${state[item.state]} · ${item.occurrences} ocorrência(s)`;
          const button = node("button", label, line);
          button.type = "button";
          button.className = "btn-action";
          button.addEventListener("click", () => load({ incident_id: item.id }));
        }
      }
      result.occurrences?.forEach((item, index) => occurrence(item, index === 0 && !append));
      if (!append && !(result.incidents || result.occurrences || []).length) {
        status.textContent = result.collection?.last_scan_at ? "Nenhuma ocorrência registrada." :
          params.render_id ? "Nenhuma ocorrência disponível para este render." : "Nenhum incidente registrado.";
      }
      if (result.collection?.last_error) {
        status.textContent = "A coleta do job está incompleta. Os relatórios registrados estão disponíveis abaixo.";
      }
      next = result.next;
      more.hidden = !next;
    } catch (error) {
      if (error.name === "AbortError") return;
      status.textContent = error.message || "Não foi possível consultar o diagnóstico.";
      more.hidden = !append;
    }
  }

  function open(params) {
    if (!dialog.open) dialog.showModal();
    load(params);
  }

  document.getElementById("openDeadlineIncidents")?.addEventListener("click", () => open({}));
  document.getElementById("openRenderDiagnoses")?.addEventListener("click", () => {
    const renderId = Number(document.getElementById("modal_idrender").textContent);
    if (renderId) open({ render_id: renderId });
  });
  more.addEventListener("click", () => load({ ...scope, before: next }, true));
  dialog.addEventListener("close", () => request?.abort());
})();
