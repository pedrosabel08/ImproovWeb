(() => {
  const config = window.FlowBlockConfig || {};
  const $ = (selector, parent = document) => parent.querySelector(selector);
  const esc = (value) =>
    String(value ?? "").replace(
      /[&<>'"]/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          "'": "&#39;",
          '"': "&quot;",
        })[c],
    );
  const api = async (action, options = {}) => {
    const method = options.method || "GET";
    let url = `${config.api}?action=${encodeURIComponent(action)}`;
    if (options.query) url += `&${new URLSearchParams(options.query)}`;
    const response = await fetch(url, {
      method,
      headers: options.body ? { "Content-Type": "application/json" } : {},
      body: options.body ? JSON.stringify(options.body) : undefined,
    });
    const data = await response.json();
    if (!response.ok || !data.ok)
      throw new Error(data.message || "Não foi possível concluir a ação.");
    return data;
  };

  function slaInfo(issue) {
    const now = new Date();
    const deadline = new Date(issue.proxima_cobranca_em);

    if(issue.status == "RESOLVIDA" || issue.status == "CANCELADA") {
      return `
          <span class="fb-sla">-</span>
    `;
    }

    if (deadline < now) {
      return `
            <span class="fb-sla fb-sla--late">
                ${fmtDate(deadline)}
            </span>
        `;
    }

    return `
        <span class="fb-sla fb-sla--ok">
            ${fmtDate(deadline)}
        </span>
    `;
  }
  const fmtDate = (value) => {
    if (!value) return "—";

    const date =
      value instanceof Date ? value : new Date(value.replace(" ", "T"));

    return new Intl.DateTimeFormat("pt-BR", {
      dateStyle: "short",
      timeStyle: "short",
    }).format(date);
  };
  const statusLabel = (s) =>
    ({
      ABERTA: "Aberta",
      AGUARDANDO_ACAO: "Aguardando ação",
      PAUSADA: "Pausada",
      RESOLVIDA: "Resolvida",
      CANCELADA: "Cancelada",
    })[s] || s;
  const urgencyLabel = (s) =>
    ({ CRITICA: "Crítica", ALTA: "Alta", NORMAL: "Normal", BAIXA: "Baixa" })[
      s
    ] || s;
  const saveUrl = (params) =>
    history.replaceState({}, "", `${location.pathname}?${params.toString()}`);

  async function listPage() {
    const table = $("#issues-table tbody");
    if (!table) return;
    const params = new URLSearchParams(location.search);
    let state = {
      page: Number(params.get("page")) || 1,
      status: params.get("status") || "",
      search: params.get("search") || "",
      mentioned: params.get("mentioned") === "1",
    };
    const search = $("#search");
    search.value = state.search;
    let options = null;
    const renderOptions = async () => {
      options = await api("options");
      const fill = (selector, data, label) => {
        const select = $(selector);
        if (!select) return;
        select.innerHTML =
          `<option value="">${label}</option>` +
          data
            .map((x) => `<option value="${x.id}">${esc(x.nome)}</option>`)
            .join("");
      };
      fill('[data-filter="tipo_id"]', options.types, "Todos os tipos");
      fill('[data-filter="fila_id"]', options.queues, "Todas as filas");
      fill(
        '[data-filter="responsavel_id"]',
        options.collaborators,
        "Todos os responsáveis",
      );
      fill('[data-filter="funcao_id"]', options.functions, "Todas as funções");
      fill("#issue-type", options.types, "Selecione o tipo");
      fill("#issue-queue", options.queues, "Não definida");
      fill("#issue-responsible", options.collaborators, "Não definido");
      document.querySelectorAll("[data-filter]").forEach((input) => {
        const v = params.get(input.dataset.filter);
        if (v !== null) input.value = v;
      });
    };
    const queryForApi = () => {
      const q = {
        page: state.page,
        search: state.search,
        status: state.status,
      };
      if (state.mentioned) q.mentioned = "1";
      document.querySelectorAll("[data-filter]").forEach((input) => {
        if (input.value) q[input.dataset.filter] = input.value;
      });
      ["obra_id", "imagem_id"].forEach((key) => {
        if (params.get(key)) q[key] = params.get(key);
      });
      return q;
    };
    const syncUrl = () => {
      const p = new URLSearchParams();
      Object.entries(queryForApi()).forEach(([k, v]) => {
        if (v) p.set(k, v);
      });
      saveUrl(p);
    };
    const load = async () => {
      $("#loading").hidden = false;
      $("#empty").hidden = true;
      try {
        const data = await api("list", { query: queryForApi() });
        renderRows(data.items);
        renderTabs(data.counts);
        renderPagination(data);
        syncUrl();
      } catch (error) {
        table.innerHTML = `<tr><td colspan="9">${esc(error.message)}</td></tr>`;
      } finally {
        $("#loading").hidden = true;
      }
    };
    const renderRows = (items) => {
      table.innerHTML = items
        .map((i) => {
          const origem =
            i.legado == 1
              ? "Migrada do HOLD"
              : `Aberta por ${esc(i.criador_nome || "—")}`;

          return `
        <tr data-id="${i.id}">
          <td>
            <span class="fb-code">
              ${esc(i.codigo)}
            </span>

            <span class="fb-secondary">
              ${origem}
            </span>
          </td>

          <td>
            <strong>
              ${esc(i.imagem_nome)}
            </strong>

            <span class="fb-secondary">
              ${esc(i.nome_funcao)}
            </span>
          </td>

          <td>
            <strong>
              ${esc(i.nomenclatura || "—")}
            </strong>

            <span class="fb-secondary">
              ${esc(i.nome_obra || "")}
            </span>
          </td>

          <td>
            ${esc(i.tipo_nome)}
          </td>

          <td>
            ${esc(i.responsavel_nome || "Não definido")}
          </td>

          <td>
            <span class="fb-pill fb-status-${i.status}">
              ${statusLabel(i.status)}
            </span>
          </td>

          <td>
              ${slaInfo(i)}
          </td>

          <td>
            <span class="fb-pill fb-urgency-${i.urgencia}">
              ${urgencyLabel(i.urgencia)}
            </span>
          </td>

          <td>
            ${fmtDate(i.criado_em)}
          </td>

          <td class="fb-blocked">
            ${esc(i.tempo_bloqueado)}
          </td>
        </tr>
      `;
        })
        .join("");

      $("#empty").hidden = items.length > 0;

      table.querySelectorAll("tr[data-id]").forEach((row) => {
        row.addEventListener("click", () => {
          const mentionedParam = state.mentioned ? "&mark_mentions=1" : "";

          const fromParam = location.search
            ? `&from=${encodeURIComponent(location.search)}`
            : "";

          location.href =
            `${config.detail}?id=${row.dataset.id}` +
            mentionedParam +
            fromParam;
        });
      });
    };
    const renderTabs = (counts) => {
      const map = {
        "": "TODAS",
        ABERTA: "ABERTA",
        AGUARDANDO_ACAO: "AGUARDANDO_ACAO",
        PAUSADA: "PAUSADA",
        RESOLVIDA: "RESOLVIDA",
        CANCELADA: "CANCELADA",
      };
      document.querySelectorAll("#status-tabs button").forEach((b) => {
        const isMentionedTab = b.dataset.mentioned === "1";
        b.classList.toggle(
          "is-active",
          isMentionedTab
            ? state.mentioned
            : !state.mentioned && b.dataset.status === state.status,
        );
        $("span", b).textContent = isMentionedTab
          ? counts.MENCIONARAM_VOCE || 0
          : counts[map[b.dataset.status]] || 0;
      });
    };
    const renderPagination = (data) => {
      const pages = Math.max(1, Math.ceil(data.total / data.per_page));
      $("#pagination-label").textContent =
        `Mostrando ${data.total ? (data.page - 1) * data.per_page + 1 : 0}–${Math.min(data.page * data.per_page, data.total)} de ${data.total}`;
      $("#pagination-buttons").innerHTML = Array.from(
        { length: pages },
        (_, x) => x + 1,
      )
        .filter((p) => p === 1 || p === pages || Math.abs(p - data.page) <= 1)
        .map(
          (p, i, a) =>
            `${i && p - a[i - 1] > 1 ? "<span>…</span>" : ""}<button class="${p === data.page ? "is-active" : ""}" data-page="${p}">${p}</button>`,
        )
        .join("");
      $("#pagination-buttons")
        .querySelectorAll("button")
        .forEach((b) =>
          b.addEventListener("click", () => {
            state.page = Number(b.dataset.page);
            load();
          }),
        );
    };
    await renderOptions();
    await load();
    let timer;
    search.addEventListener("input", () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        state.search = search.value.trim();
        state.page = 1;
        load();
      }, 250);
    });
    $("#filter-toggle").addEventListener("click", () => {
      $("#filter-panel").hidden = !$("#filter-panel").hidden;
    });
    document.querySelectorAll("[data-filter]").forEach((el) =>
      el.addEventListener("change", () => {
        state.page = 1;
        load();
      }),
    );
    $("#clear-filters").addEventListener("click", () => {
      document.querySelectorAll("[data-filter]").forEach((x) => (x.value = ""));
      state.page = 1;
      load();
    });
    document.querySelectorAll("#status-tabs button").forEach((b) =>
      b.addEventListener("click", () => {
        state.mentioned = b.dataset.mentioned === "1";
        state.status = state.mentioned ? "" : b.dataset.status;
        state.page = 1;
        load();
      }),
    );
    setupNewIssue(options, load);
    window.addEventListener("improov:flowBlockMention", (event) => {
      if (
        Number(event.detail?.recipient_id) ===
        Number(window.IMPROOV_COLABORADOR_ID)
      ) {
        load();
      }
    });
  }

  function setupNewIssue(options, afterCreate) {
    const dialog = $("#issue-dialog");
    if (!dialog) return;
    const open = $("#new-issue");
    const close = () => dialog.close();
    open.addEventListener("click", () => {
      dialog.showModal();
      const taskParam = new URLSearchParams(location.search).get("new_task");
      if (taskParam) {
        $("#task-id").value = taskParam;
        $("#task-search").value = "Tarefa selecionada";
      }
    });
    document
      .querySelectorAll("[data-close-dialog]")
      .forEach((b) => b.addEventListener("click", close));
    let taskTimer;
    $("#task-search").addEventListener("input", () => {
      const input = $("#task-search");
      $("#task-id").value = "";
      clearTimeout(taskTimer);
      taskTimer = setTimeout(async () => {
        const result = await api("tasks", { query: { search: input.value } });
        $("#task-results").innerHTML =
          result.tasks
            .map(
              (t) =>
                `<button type="button" class="fb-task-result" data-id="${t.id}" data-label="${esc(t.imagem_nome)} · ${esc(t.nome_funcao)}"><strong>${esc(t.imagem_nome)}</strong> · ${esc(t.nome_funcao)}<br><small>${esc(t.nomenclatura || "—")} · ${esc(t.nome_obra || "")}</small></button>`,
            )
            .join("") ||
          '<div class="fb-secondary">Nenhuma tarefa disponível.</div>';
        $("#task-results")
          .querySelectorAll("button")
          .forEach((b) =>
            b.addEventListener("click", () => {
              $("#task-id").value = b.dataset.id;
              input.value = b.dataset.label;
              $("#task-results").innerHTML = "";
            }),
          );
      }, 200);
    });
    $("#issue-form").addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        const result = await api("create", {
          method: "POST",
          body: {
            funcao_imagem_id: Number($("#task-id").value),
            tipo_id: Number($("#issue-type").value),
            fila_id: Number($("#issue-queue").value) || null,
            responsavel_id: Number($("#issue-responsible").value) || null,
            urgencia: $("#issue-urgency").value,
            descricao: $("#issue-description").value,
          },
        });
        location.href = `${config.detail}?id=${result.id}`;
      } catch (error) {
        alert(error.message);
      }
    });
  }

  async function detailPage() {
    const root = $("#flow-block-detail");
    if (!root) return;
    const issueId = Number(root.dataset.issueId);
    if (!issueId) {
      root.innerHTML = "<p>Issue inválida.</p>";
      return;
    }
    const detailParams = new URLSearchParams(location.search);
    const load = async () => {
      try {
        const query = { id: issueId };
        if (detailParams.get("mention_id"))
          query.mention_id = detailParams.get("mention_id");
        if (detailParams.get("mark_mentions") === "1")
          query.mark_mentions = "1";
        const data = await api("detail", { query });
        if (query.mention_id || query.mark_mentions) {
          detailParams.delete("mention_id");
          detailParams.delete("mark_mentions");
          history.replaceState(
            {},
            "",
            `${location.pathname}?${detailParams.toString()}`,
          );
        }
        await renderDetail(data);
      } catch (error) {
        root.innerHTML = `<a class="fb-back" href="index.php">← Flow Block</a><p>${esc(error.message)}</p>`;
      }
    };
    const renderDetail = async ({ issue, activities, attachments }) => {
      const terminal = ["RESOLVIDA", "CANCELADA"].includes(issue.status);
      const can = issue.can_resolve;
      const awaitingConfirmation =
        issue.status === "RESOLVIDA" && !issue.confirmada_em;
      const deadline = issue.proxima_cobranca_em
        ? `<span class="fb-sla ${issue.cobranca_atrasada ? "is-overdue" : ""}"><i class="ri-alarm-warning-line"></i> ${issue.cobranca_atrasada ? "Cobrança atrasada" : "Próxima cobrança"}: ${fmtDate(issue.proxima_cobranca_em)}</span>`
        : "";
      const actions =
        awaitingConfirmation
          ? ""
          : terminal && can
          ? `
      <button class="fb-button fb-button--ghost" data-transition="ABERTA">
        Reabrir Issue
      </button>
    `
          : !terminal && can
            ? `
        ${
          issue.status === "PAUSADA"
            ? `
              <button class="fb-button fb-button--info" data-update-pause>
                <i class="ri-refresh-line"></i>
                Atualizar pausa
              </button>
            `
            : `
              <button class="fb-button fb-button--warning" data-pause>
                <i class="ri-pause-circle-line"></i>
                Pausar Issue
              </button>
            `
        }

        <button class="fb-button fb-button--ghost" data-reassign>
          <i class="ri-user-shared-line"></i>
          Reatribuir Issue
        </button>

        <button class="fb-button fb-button--danger" data-transition="CANCELADA">
          <i class="ri-close-circle-line"></i>
          Cancelar Issue
        </button>

        <button class="fb-button fb-button--primary" data-transition="RESOLVIDA">
          <i class="ri-check-line"></i>
          Resolver Issue
        </button>
      `
            : "";
      const confirmationCta = awaitingConfirmation
        ? `<section class="fb-resolution-confirmation"><i class="ri-checkbox-circle-line"></i><div><strong>A pendência foi marcada como resolvida.</strong><p>Confirme se a resposta ou o material recebido é suficiente. A tarefa continuará em HOLD até ser replanejada.</p></div>${issue.can_confirm_resolution ? '<div class="fb-resolution-confirmation-actions"><button class="fb-button fb-button--ghost" data-transition="ABERTA">Reabrir Issue</button><button class="fb-button fb-button--primary" data-confirm-resolution>Entendi, confirmar resolução</button></div>' : '<span class="fb-secondary">Aguardando confirmação do dono da tarefa.</span>'}</section>`
        : "";
      const taskReadyNotice = !awaitingConfirmation && issue.task_ready_to_continue && issue.tarefa_status === "HOLD"
        ? '<section class="fb-resolution-confirmation"><i class="ri-calendar-schedule-line"></i><div><strong>Tudo resolvido.</strong><p>A tarefa permanece em HOLD até receber um novo prazo. No Kanban, use “Continuar tarefa” para replanejá-la.</p></div></section>'
        : "";
      const attachmentsByActivity = new Map();
      attachments.forEach((attachment) => {
        const key = Number(attachment.atividade_id);
        attachmentsByActivity.set(key, [
          ...(attachmentsByActivity.get(key) || []),
          attachment,
        ]);
      });
      const mentionOptions = await api("options").catch(() => ({
        collaborators: [],
      }));
      root.innerHTML = `<a class="fb-back" href="index.php${location.search.includes("from=") ? "?" + decodeURIComponent(new URLSearchParams(location.search).get("from")).replace(/^\?/, "") : ""}"><i class="ri-arrow-left-line"></i> Flow Block</a><header class="fb-detail-header"><div><div><h1>${esc(issue.codigo)} <span class="fb-pill fb-status-${issue.status}">${statusLabel(issue.status)}</span></h1></div><p class="fb-detail-meta">${esc(issue.imagem_nome)} · ${esc(issue.nome_funcao)} · ${esc(issue.nomenclatura || issue.nome_obra || "—")} · bloqueada há ${esc(issue.tempo_bloqueado)}</p>${deadline}</div><div class="fb-detail-actions">${actions}</div></header>${confirmationCta}${taskReadyNotice}<div class="fb-detail-grid"><section><div class="fb-timeline"><h2 class="fb-section-title"><i class="ri-git-commit-line"></i> Timeline</h2><div class="fb-timeline-list">${renderTimeline(activities, attachmentsByActivity) || '<p class="fb-secondary">Sem eventos.</p>'}</div></div><form class="fb-composer" id="comment-form"><h2>Adicionar comentário</h2><div class="fb-mention-field"><textarea id="comment-content" rows="4" placeholder="Escreva um comentário. Digite @ para mencionar alguém."></textarea><div class="fb-mention-picker" id="comment-mention-picker" hidden></div></div><div class="fb-attachment-control"><i class="ri-attachment-2"></i><input type="file" id="comment-file" accept=".pdf,.dwg,.dxf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.zip,.txt" multiple></div><footer><button class="fb-button fb-button--primary">Enviar</button></footer></form></section><aside class="fb-details-panel"><h2>Detalhes da Issue</h2>${detailItem("Tipo", issue.tipo_nome)}${detailItem("Observação", issue.descricao)}${detailItem("Criado por", issue.criador_nome)}${detailItem("Fila responsável", issue.fila_nome || "Não definida")}${detailItem("Responsável", issue.responsavel_nome || "Não definido")}${detailItem("Urgência", urgencyLabel(issue.urgencia))}${detailItem("Aberta em", fmtDate(issue.criado_em))}${detailItem("Última atualização", fmtDate(issue.atualizado_em))}${detailItem("Próxima cobrança", issue.proxima_cobranca_em ? fmtDate(issue.proxima_cobranca_em) : "—")}<a class="fb-detail-task" href="../inicio.php?funcao_imagem_id=${issue.funcao_imagem_id}"><i class="ri-external-link-line"></i> Tarefa relacionada</a></aside></div>`;
      const contextLinks = document.createElement("div");
      contextLinks.innerHTML = `<a class="fb-detail-task" href="index.php?image_id=${issue.imagem_id}"><i class="ri-image-line"></i> Issues da imagem</a><a class="fb-detail-task" href="index.php?obra_id=${issue.obra_id}"><i class="ri-building-line"></i> Issues da obra</a>`;
      $(".fb-details-panel").appendChild(contextLinks);
      const mentionedIds = setupMentionPicker(
        $("#comment-content"),
        $("#comment-mention-picker"),
        mentionOptions.collaborators || [],
      );
      $("#comment-form").addEventListener("submit", async (e) => {
        e.preventDefault();
        const field = $("#comment-content");
        const fileField = $("#comment-file");
        if (!field.value.trim() && !fileField.files.length) return;
        try {
          await sendComment({
            issueId,
            content: field.value,
            mentionIds: mentionedIds(),
            files: fileField.files,
          });
          load();
        } catch (error) {
          alert(error.message);
        }
      });
      root.querySelectorAll("[data-comment-action]").forEach((button) => {
        button.addEventListener("click", () =>
          handleCommentAction(
            button.dataset.commentAction,
            Number(button.dataset.activityId),
            issue,
            mentionOptions.collaborators || [],
            load,
          ),
        );
      });
      root
        .querySelector("[data-pause]")
        ?.addEventListener("click", () =>
          openPause(issue, mentionOptions.collaborators || [], load),
        );
      root
        .querySelector("[data-update-pause]")
        ?.addEventListener("click", () =>
          openPause(issue, mentionOptions.collaborators || [], load),
        );
      root
        .querySelector("[data-confirm-resolution]")
        ?.addEventListener("click", async () => {
          if (!confirm("Confirmar que a resolução é suficiente? A tarefa continuará em HOLD até ser replanejada.")) return;
          try {
            await api("confirm_resolution", { method: "POST", body: { id: issue.id } });
            load();
          } catch (error) {
            alert(error.message);
          }
        });
      root
        .querySelector("[data-reassign]")
        ?.addEventListener("click", () =>
          openReassignment(issue, mentionOptions.collaborators || [], load),
        );
      root
        .querySelectorAll("[data-transition]")
        .forEach((btn) =>
          btn.addEventListener("click", () =>
            openTransition(btn.dataset.transition, issue, load),
          ),
        );
    };
    window.addEventListener("improov:flowBlockUpdated", (event) => {
      if (Number(event.detail?.issue_id) === issueId) load();
    });
    await load();
  }
  async function sendComment({
    issueId,
    content,
    mentionIds = [],
    files,
    parentActivityId = null,
  }) {
    const form = new FormData();
    form.append("id", issueId);
    form.append("conteudo", content || "");
    form.append("mencionados", JSON.stringify(mentionIds));
    if (parentActivityId) form.append("atividade_pai_id", parentActivityId);
    [...(files || [])].forEach((file) => form.append("files[]", file));
    const response = await fetch(`${config.api}?action=comment`, {
      method: "POST",
      body: form,
    });
    const data = await response.json();
    if (!response.ok || !data.ok)
      throw new Error(data.message || "Não foi possível enviar o comentário.");
    return data;
  }

  function showInlineCommentForm(
    article,
    { mode, issue, activityId, collaborators, reload },
  ) {
    article.querySelector(".fb-inline-comment-form")?.remove();
    const form = document.createElement("form");
    form.className = "fb-inline-comment-form";
    form.innerHTML = `<div class="fb-mention-field"><textarea rows="3" placeholder="${mode === "reply" ? "Responder comentário…" : "Editar comentário…"}"></textarea><div class="fb-mention-picker" hidden></div></div>${mode === "reply" ? '<input type="file" multiple accept=".pdf,.dwg,.dxf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.zip,.txt">' : ""}<footer><button type="button" class="fb-text-button" data-cancel>Cancelar</button><button class="fb-button fb-button--primary">${mode === "reply" ? "Responder" : "Salvar"}</button></footer>`;
    const field = $("textarea", form);
    if (mode === "edit") field.value = article.dataset.commentContent || "";
    const mentionedIds = setupMentionPicker(
      field,
      $(".fb-mention-picker", form),
      collaborators,
    );
    $("[data-cancel]", form).addEventListener("click", () => form.remove());
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        if (mode === "reply") {
          await sendComment({
            issueId: issue.id,
            content: field.value,
            mentionIds: mentionedIds(),
            files: $("input[type=file]", form)?.files,
            parentActivityId: activityId,
          });
        } else {
          await api("comment_update", {
            method: "POST",
            body: {
              id: issue.id,
              atividade_id: activityId,
              conteudo: field.value,
              mencionados: mentionedIds(),
            },
          });
        }
        reload();
      } catch (error) {
        alert(error.message);
      }
    });
    article.appendChild(form);
    field.focus();
  }

  async function handleCommentAction(
    action,
    activityId,
    issue,
    collaborators,
    reload,
  ) {
    const article = document.querySelector(
      `.fb-event[data-activity-id="${activityId}"]`,
    );
    if (!article) return;
    if (action === "reply" || action === "edit") {
      showInlineCommentForm(article, {
        mode: action,
        issue,
        activityId,
        collaborators,
        reload,
      });
      return;
    }
    if (
      action === "delete" &&
      confirm(
        "Excluir este comentário? Os anexos permanecerão no histórico da Issue.",
      )
    ) {
      try {
        await api("comment_delete", {
          method: "POST",
          body: { id: issue.id, atividade_id: activityId },
        });
        reload();
      } catch (error) {
        alert(error.message);
      }
    }
  }

  function openPause(issue, collaborators, reload) {
    const dialog = $("#pause-dialog");
    if (!dialog) return;
    const reason = $("#pause-reason");
    const observation = $("#pause-observation");
    const returnAt = $("#pause-return-at");
    const responsibleGroup = $("#pause-responsible-group");
    const responsible = $("#pause-responsible");
    const updating = issue.status === "PAUSADA";
    const toLocalInput = (date) => {
      const pad = (value) => String(value).padStart(2, "0");
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };
    const setHoursFromNow = (hours) => {
      const target = new Date(Date.now() + hours * 60 * 60 * 1000);
      returnAt.value = toLocalInput(target);
    };
    $("#pause-title").textContent = updating ? "Atualizar pausa" : "Pausar Issue";
    $("#pause-help").textContent = updating
      ? "Renove a pausa com a nova informação. A Issue continuará pausada e receberá uma nova cobrança."
      : "A pausa encerra o SLA inicial e cria um novo compromisso de retorno para o responsável.";
    $("#pause-submit").textContent = updating ? "Atualizar pausa" : "Confirmar pausa";
    reason.value = updating ? issue.pausa_motivo || "" : "";
    observation.value = updating ? issue.pausa_observacao || "" : "";
    const existingReturn = String(
      issue.retorno_previsto_em || issue.proxima_cobranca_em || "",
    ).replace(" ", "T").slice(0, 16);
    if (updating && existingReturn) returnAt.value = existingReturn;
    else setHoursFromNow(4);
    responsible.innerHTML = `<option value="">Não alterar responsável</option>${collaborators
      .map((person) => `<option value="${Number(person.id)}">${esc(person.nome)}</option>`)
      .join("")}`;
    responsible.value = String(issue.responsavel_colaborador_id || "");
    responsibleGroup.hidden = !collaborators.length;
    dialog.showModal();
    dialog.querySelectorAll("[data-close-pause]").forEach((button) => {
      button.onclick = () => dialog.close();
    });
    dialog.querySelectorAll("[data-pause-hours]").forEach((button) => {
      button.onclick = () => setHoursFromNow(Number(button.dataset.pauseHours));
    });
    dialog.querySelector("[data-pause-tomorrow]").onclick = () => {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      tomorrow.setHours(9, 0, 0, 0);
      returnAt.value = toLocalInput(tomorrow);
    };
    $("#pause-form").onsubmit = async (event) => {
      event.preventDefault();
      if (!reason.value.trim() || !returnAt.value) return;
      try {
        await api("pause", {
          method: "POST",
          body: {
            id: issue.id,
            motivo: reason.value.trim(),
            observacao: observation.value.trim(),
            retorno_previsto_em: returnAt.value,
            responsavel_id: Number(responsible.value) || null,
          },
        });
        dialog.close();
        reload();
      } catch (error) {
        alert(error.message);
      }
    };
  }

  function openReassignment(issue, collaborators, reload) {
    const dialog = $("#reassign-dialog");
    if (!dialog) return;
    const select = $("#reassign-responsible");
    select.innerHTML = `<option value="">Selecione o responsável</option>${collaborators
      .map(
        (person) =>
          `<option value="${Number(person.id)}">${esc(person.nome)}</option>`,
      )
      .join("")}`;
    select.value = String(issue.responsavel_colaborador_id || "");
    dialog.showModal();
    dialog.querySelectorAll("[data-close-reassign]").forEach((button) => {
      button.onclick = () => dialog.close();
    });
    $("#reassign-form").onsubmit = async (event) => {
      event.preventDefault();
      const responsibleId = Number(select.value);
      if (!responsibleId) return;
      try {
        await api("update", {
          method: "POST",
          body: { id: issue.id, responsavel_colaborador_id: responsibleId },
        });
        dialog.close();
        reload();
      } catch (error) {
        alert(error.message);
      }
    };
  }

  function setupMentionPicker(field, picker, collaborators) {
    const selected = new Map();
    const normalize = (value) => String(value || "").toLocaleLowerCase("pt-BR");
    const hide = () => {
      picker.hidden = true;
      picker.innerHTML = "";
    };
    const choose = (person, start, end) => {
      field.setRangeText(`@${person.nome} `, start, end, "end");
      selected.set(Number(person.id), person.nome);
      hide();
      field.focus();
    };
    const show = () => {
      const cursor = field.selectionStart;
      const before = field.value.slice(0, cursor);
      const match = before.match(/(?:^|\s)@([^\s@]*)$/);
      if (!match) return hide();
      const query = normalize(match[1]);
      const start = cursor - match[1].length - 1;
      const matches = collaborators
        .filter((person) => normalize(person.nome).includes(query))
        .slice(0, 6);
      if (!matches.length) return hide();
      picker.innerHTML = matches
        .map(
          (person) =>
            `<button type="button" class="fb-mention-option" data-id="${Number(person.id)}"><i class="ri-at-line"></i>${esc(person.nome)}</button>`,
        )
        .join("");
      picker.hidden = false;
      picker.querySelectorAll("button").forEach((button) => {
        button.addEventListener("mousedown", (event) => event.preventDefault());
        button.addEventListener("click", () => {
          const person = matches.find(
            (item) => Number(item.id) === Number(button.dataset.id),
          );
          if (person) choose(person, start, cursor);
        });
      });
    };
    field.addEventListener("input", show);
    field.addEventListener("click", show);
    field.addEventListener("keydown", (event) => {
      if (event.key === "Escape") hide();
    });
    field.addEventListener("blur", () => window.setTimeout(hide, 120));
    return () => {
      const content = normalize(field.value);
      return [...selected.entries()]
        .filter(([, name]) => content.includes(`@${normalize(name)}`))
        .map(([id]) => id);
    };
  }
  const detailItem = (title, value) =>
    `<dl class="fb-detail-item"><dt>${esc(title)}</dt><dd>${esc(value || "—")}</dd></dl>`;
  const attachmentHtml = (attachment) =>
    attachment.is_image
      ? `<a class="fb-attachment-preview" href="${esc(attachment.url)}" target="_blank" rel="noopener"><img src="${esc(attachment.url)}" alt="${esc(attachment.nome_original)}"><span><i class="ri-image-line"></i>${esc(attachment.nome_original)}</span></a>`
      : `<a class="fb-attachment-file" href="${esc(attachment.url)}" target="_blank" rel="noopener"><i class="ri-attachment-2"></i><span>${esc(attachment.nome_original)}</span></a>`;
  const mentionsHtml = (mentions) =>
    mentions?.length
      ? `<div class="fb-event-mentions">${mentions.map((mention) => `<span><i class="ri-at-line"></i>${esc(mention.nome)}</span>`).join("")}</div>`
      : "";
  const renderTimeline = (activities, attachmentsByActivity) => {
    const replies = new Map();
    activities.forEach((activity) => {
      if (activity.atividade_pai_id) {
        const key = Number(activity.atividade_pai_id);
        replies.set(key, [...(replies.get(key) || []), activity]);
      }
    });
    return activities
      .filter((activity) => !activity.atividade_pai_id)
      .map((activity) =>
        eventHtml(
          activity,
          attachmentsByActivity.get(Number(activity.id)) || [],
          replies,
          attachmentsByActivity,
        ),
      )
      .join("");
  };
  const eventHtml = (
    a,
    attachments,
    replies = new Map(),
    attachmentsByActivity = new Map(),
  ) => {
    const isComment = a.tipo === "COMENTARIO";
    const deleted = Boolean(a.excluido_em);
    const actions =
      isComment && !deleted
        ? `<div class="fb-comment-actions"><button type="button" data-comment-action="reply" data-activity-id="${Number(a.id)}">Responder</button>${a.can_edit ? `<button type="button" data-comment-action="edit" data-activity-id="${Number(a.id)}">Editar</button><button type="button" data-comment-action="delete" data-activity-id="${Number(a.id)}">Excluir</button>` : ""}</div>`
        : "";
    const replyHtml = (replies.get(Number(a.id)) || [])
      .map((reply) =>
        eventHtml(
          reply,
          attachmentsByActivity.get(Number(reply.id)) || [],
          replies,
          attachmentsByActivity,
        ),
      )
      .join("");
    return `<article class="fb-event fb-event--${esc(a.tipo)} ${a.atividade_pai_id ? "fb-event--reply" : ""}" data-activity-id="${Number(a.id)}" data-comment-content="${esc(a.conteudo || "")}"><strong>${esc(a.autor_nome || "Sistema")} ${eventText(a)}</strong><time>${fmtDate(a.criado_em)}${a.atualizado_em ? " · editado" : ""}</time>${deleted ? '<div class="fb-event-content fb-event-content--deleted">Comentário excluído.</div>' : a.conteudo ? `<div class="fb-event-content">${esc(a.conteudo)}</div>` : ""}${mentionsHtml(a.mencoes)}${attachments.length ? `<div class="fb-event-attachments">${attachments.map(attachmentHtml).join("")}</div>` : ""}${actions}${replyHtml ? `<div class="fb-comment-replies">${replyHtml}</div>` : ""}</article>`;
  };
  const eventText = (a) =>
    ({
      CRIADA: "criou esta Issue",
      COMENTARIO: a.atividade_pai_id ? "respondeu" : "comentou",
      ANEXO: "anexou um arquivo",
      ALTERADA: a.metadados?.responsavel_colaborador_id
        ? "alterou o responsável"
        : "alterou os detalhes",
      RESOLVIDA: "resolveu a Issue",
      RESOLUCAO_CONFIRMADA: "confirmou a resolução",
      TAREFA_REPROGRAMADA: "reprogramou a tarefa e continuou o trabalho",
      CANCELADA: "cancelou a Issue",
      REABERTA: "reabriu a Issue",
      PAUSADA: "pausou a Issue",
      PAUSA_ATUALIZADA: "atualizou a pausa",
      TAREFA_LIBERADA: "liberou a tarefa",
    })[a.tipo] || "registrou uma atualização";
  function openTransition(target, issue, reload) {
    const dialog = $("#transition-dialog");
    if (!dialog) return;
    const terminal = target !== "ABERTA";
    $("#transition-title").textContent =
      target === "RESOLVIDA"
        ? "Resolver Issue"
        : target === "CANCELADA"
          ? "Cancelar Issue"
          : "Reabrir Issue";
    $("#transition-help").textContent =
      target === "ABERTA"
        ? "Explique por que a resposta não foi suficiente. A Issue voltará a bloquear a tarefa."
        : "A tarefa só voltará para Em andamento quando não houver outra Issue bloqueante.";
    $("#transition-comment").required = true;
    $("#transition-comment").value = "";
    dialog.showModal();
    document
      .querySelectorAll("[data-close-dialog]")
      .forEach((b) => (b.onclick = () => dialog.close()));
    $("#transition-form").onsubmit = async (e) => {
      e.preventDefault();
      try {
        await api("transition", {
          method: "POST",
          body: {
            id: issue.id,
            status: target,
            comentario: $("#transition-comment").value,
          },
        });
        dialog.close();
        reload();
      } catch (error) {
        alert(error.message);
      }
    };
  }

  if ($("#issues-table")) listPage();
  else if ($("#flow-block-detail")) detailPage();
})();
