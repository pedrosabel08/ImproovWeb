"use strict";
(() => {
  const main = document.querySelector("#main"),
    notice = document.querySelector("#notice"),
    projectSelect = document.querySelector("#project"),
    invitation = document.querySelector("#invitation");
  const csrf = document.querySelector('meta[name="portal-csrf"]').content;
  let boot,
    data,
    obra = 0,
    busy = false;
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
  const tell = (text, error = false) => {
    notice.hidden = false;
    notice.className = error ? "error" : "";
    notice.textContent = text;
  };
  async function api(action, body = {}) {
    const controller = new AbortController(),
      timer = setTimeout(() => controller.abort(), 25000);
    try {
      const r = await fetch("internal_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-Portal-CSRF": csrf },
        body: JSON.stringify({
          action,
          obra_id: obra,
          revisao: data?.project.revisao,
          ...body,
        }),
        signal: controller.signal,
      });
      let j;
      try {
        j = await r.json();
      } catch {
        throw Error(
          "Resposta indisponível. Confira sua sessão e tente novamente.",
        );
      }
      if (!r.ok || !j.ok)
        throw Error(j.message || "Não foi possível concluir.");
      return j;
    } catch (e) {
      if (e.name === "AbortError" || e instanceof TypeError)
        throw Error("A conexão foi interrompida. Tente novamente.");
      throw e;
    } finally {
      clearTimeout(timer);
    }
  }
  async function run(fn) {
    if (busy) return;
    busy = true;
    main.setAttribute("aria-busy", "true");
    projectSelect.disabled = true;
    main.querySelectorAll("button").forEach((b) => (b.disabled = true));
    try {
      await fn();
    } catch (e) {
      tell(e.message, true);
    } finally {
      busy = false;
      projectSelect.disabled = false;
      main.setAttribute("aria-busy", "false");
      main.querySelectorAll("button").forEach((b) => (b.disabled = false));
    }
  }
  function users(selected = 0) {
    return boot.users
      .map(
        (u) =>
          `<option value="${+u.idusuario}" ${+u.idusuario === +selected ? "selected" : ""}>${esc(u.nome_usuario)}</option>`,
      )
      .join("");
  }
  function showInvite(token) {
    if (!token) {
      invitation.hidden = true;
      return;
    }
    const url = new URL("index.php", location.href);
    url.searchParams.set("t", token);
    invitation.hidden = false;
    invitation.innerHTML = `<label class="field">Convite do projeto<input readonly value="${esc(url.href)}"></label><a href="${esc(url.href)}" target="_blank" rel="noopener noreferrer">Abrir entrada do cliente</a><button class="quiet" id="copy-invite">Copiar convite</button><p class="hint">Compartilhe com a pessoa central ou no grupo oficial. Guarde o link: por segurança, o banco armazena apenas seu hash.</p>`;
    invitation.querySelector("button").onclick = async () => {
      try {
        await navigator.clipboard.writeText(url.href);
        tell("Convite copiado.");
      } catch {
        invitation.querySelector("input").select();
        tell("Selecione e copie o link acima.");
      }
    };
  }
  async function load() {
    data = (await api("project.get")).data;
    render();
  }
  function setup() {
    data = null;
    main.innerHTML = `<h2>Preparar a entrada do cliente</h2><p>Designe a pessoa central do projeto e quem fará a curadoria na Improov. Contatos existentes serão reaproveitados pelo e-mail, sem alterar seus dados.</p><form id="setup" class="form"><div class="grid-fields"><label>Nome do administrador<input name="nome" required maxlength="150"></label><label>E-mail do administrador<input name="email" type="email" required maxlength="150"></label><label>Telefone do administrador<input name="telefone" type="tel" required maxlength="30"></label><label>Responsável pela curadoria<select name="curador_usuario_id" required><option value="">Selecione</option>${users()}</select></label></div><button class="primary">Configurar Portal e gerar convite</button></form>`;
    main.querySelector("form").onsubmit = (e) => {
      e.preventDefault();
      run(async () => {
        const j = await api(
          "project.configure",
          Object.fromEntries(new FormData(e.target)),
        );
        boot.projects.find((p) => +p.idobra === obra).portal_obra_id = obra;
        showInvite(j.token);
        await load();
        tell(
          "Portal configurado. Compartilhe o convite com o administrador do projeto.",
        );
      });
    };
  }
  function itemForm(m = {}) {
    return `<form class="form material" data-id="${m.id || 0}"><label>Nome do material<input name="titulo" required maxlength="180" value="${esc(m.titulo)}"></label><div class="grid-fields"><label>Tipo do material<select name="categoria_id">${data.categories.map((c) => `<option value="${+c.idcategoria}" ${+c.idcategoria === +m.categoria_id ? "selected" : ""}>${esc(c.nome_categoria)}</option>`).join("")}</select></label><label>Disciplina<select name="disciplina_id">${data.catalog
      .filter((d) => data.disciplinas.includes(+d.id))
      .map(
        (d) =>
          `<option value="${+d.id}" ${+d.id === +m.disciplina_id ? "selected" : ""}>${esc(d.nome)}</option>`,
      )
      .join(
        "",
      )}</select></label></div><label>Momento<select name="momento"><option value="INICIO" ${m.momento === "INICIO" ? "selected" : ""}>Precisamos para começar</option><option value="DURANTE" ${m.momento === "DURANTE" ? "selected" : ""}>Vamos precisar durante o projeto</option></select></label><label>Por que precisamos deste material?<textarea name="contexto" required maxlength="3000">${esc(m.contexto)}</textarea></label><label>Formatos aceitos<input name="formatos" required maxlength="260" placeholder="DWG, RVT, PDF" value="${esc((m.formatos || []).join(", "))}"></label><label>Orientação específica (opcional)<textarea name="observacao" maxlength="3000">${esc(m.observacao)}</textarea></label><div class="actions"><button class="quiet">${m.id ? "Salvar revisão" : "Adicionar material"}</button>${m.id ? `<button type="button" class="quiet danger" data-remove="${+m.id}">Remover material</button>` : ""}</div></form>`;
  }
  function render() {
    const p = data.project;
    main.innerHTML = `<h2>${esc(p.nome)}</h2><p>${esc(p.cliente)}${p.local ? " · " + esc(p.local) : ""}</p><p>${data.disciplinas.map((id) => `<span class="tag">${esc(data.catalog.find((d) => +d.id === id)?.nome)}</span>`).join("") || "Aguardando definição das disciplinas pelo cliente."}</p><details><summary>Equipe e acesso ao projeto</summary><p class="hint">Administrador é uma classificação. Todos os participantes têm as mesmas possibilidades. A remoção abaixo afeta somente o Portal.</p><div class="cards">${data.team.map((t) => `<article class="card"><h3>${esc(t.nome)}</h3><p>${t.administrador ? "Administrador do projeto" : "Participante"}${t.removido_em ? " · removido" : ""}</p><p>${t.disciplinas.map((id) => esc(data.catalog.find((d) => +d.id === id)?.nome)).join(", ") || "Sem disciplina"}</p><div class="actions">${!t.removido_em && !t.administrador ? `<button class="quiet" data-admin="${+t.contato_id}">Designar administrador</button>` : ""}${!t.administrador ? `<button class="quiet danger" data-member="${+t.contato_id}" data-restore="${t.removido_em ? "1" : "0"}">${t.removido_em ? "Restaurar participante" : "Remover participante"}</button>` : ""}</div></article>`).join("")}</div><p class="hint">Gerar um novo convite invalida o link anterior e seus códigos pendentes. As pessoas precisarão usar o novo link.</p><div class="actions"><button class="quiet" id="rotate">Gerar novo convite e invalidar anterior</button><button class="quiet danger" id="revoke">Revogar convite atual</button></div>${boot.admin ? `<form id="settings" class="form group"><label>Responsável pela curadoria<select name="curador_usuario_id">${users(p.curador_usuario_id)}</select></label><label>Estado do Portal<select name="estado"><option value="ABERTO" ${p.estado === "ABERTO" ? "selected" : ""}>Aberto</option><option value="ENCERRADO" ${p.estado === "ENCERRADO" ? "selected" : ""}>Encerrado</option></select></label><label><span><input type="checkbox" name="inscricoes_abertas" ${p.inscricoes_abertas ? "checked" : ""}> Permitir novas participações</span></label><button class="quiet">Salvar configuração</button></form>` : ""}</details>${data.published ? '<div class="next"><strong>Solicitação de materiais publicada</strong><p>A Parte 1 está concluída. A equipe continua aberta a novas pessoas. O ciclo de envio e conferência será a próxima implementação.</p></div>' : `<h2 class="group">Curadoria dos materiais</h2><p>As sugestões vêm das categorias do FlowDrive e dos requisitos desta obra. Revise contexto, momento e formatos de cada material antes de publicar.</p>${!data.disciplinas.length ? '<p class="hint">Assim que o cliente definir as disciplinas, as sugestões aparecerão aqui. Não é preciso esperar toda a equipe entrar.</p>' : ""}`}<div class="cards">${data.materials.map((m) => `<article class="card"><h3>${esc(m.titulo)} <span class="tag">${m.revisado_por ? "Revisado" : "Sugestão · revisar"}</span></h3>${data.published ? `<p>${m.momento === "INICIO" ? "Precisamos para começar" : "Vamos precisar durante o projeto"}</p><p>${esc(m.contexto)}</p><p>${m.formatos.map(esc).join(", ")}</p><p>${esc(m.observacao)}</p>` : itemForm(m)}</article>`).join("")}</div>${!data.published && data.disciplinas.length ? `<details><summary>Adicionar outro material</summary>${itemForm()}</details><button class="primary" id="publish">Publicar solicitação de materiais</button><p class="hint">Publicar torna esta seleção visível para todos os participantes. Não libera upload nem Briefing.</p>` : ""}`;
    main.querySelectorAll("form.material").forEach(
      (f) =>
        (f.onsubmit = (e) => {
          e.preventDefault();
          run(async () => {
            await api("material.save", {
              ...Object.fromEntries(new FormData(f)),
              id: +f.dataset.id,
            });
            await load();
            tell("Material revisado e salvo.");
          });
        }),
    );
    main.querySelectorAll("[data-remove]").forEach(
      (b) =>
        (b.onclick = () =>
          run(async () => {
            await api("material.remove", { id: +b.dataset.remove });
            await load();
            tell(
              "Material removido da solicitação. O registro permanece na auditoria.",
            );
          })),
    );
    main.querySelectorAll("[data-member]").forEach(
      (b) =>
        (b.onclick = () =>
          run(async () => {
            await api(
              b.dataset.restore === "1"
                ? "participant.restore"
                : "participant.remove",
              { contato_id: +b.dataset.member },
            );
            await load();
            tell("Participação atualizada.");
          })),
    );
    main.querySelectorAll("[data-admin]").forEach(
      (b) =>
        (b.onclick = () =>
          run(async () => {
            await api("administrator.set", { contato_id: +b.dataset.admin });
            await load();
            tell("Administrador do projeto atualizado.");
          })),
    );
    main.querySelector("#settings")?.addEventListener("submit", (e) => {
      e.preventDefault();
      run(async () => {
        const form = new FormData(e.target);
        await api("project.settings", {
          ...Object.fromEntries(form),
          inscricoes_abertas: form.has("inscricoes_abertas"),
        });
        await load();
        tell("Configuração atualizada.");
      });
    });
    main.querySelector("#rotate").onclick = () =>
      run(async () => {
        const j = await api("invite.rotate");
        showInvite(j.token);
        await load();
        tell("Novo convite gerado. O link anterior foi invalidado.");
      });
    main.querySelector("#revoke").onclick = () =>
      run(async () => {
        await api("invite.revoke");
        showInvite(null);
        await load();
        tell(
          "Convite revogado. Gere um novo link quando quiser retomar o acesso.",
        );
      });
    main.querySelector("#publish")?.addEventListener("click", () =>
      run(async () => {
        await api("materials.publish");
        await load();
        tell("Solicitação de materiais publicada. A Parte 1 está concluída.");
      }),
    );
  }
  projectSelect.onchange = () =>
    run(async () => {
      obra = +projectSelect.value;
      showInvite(null);
      notice.hidden = true;
      if (!obra) {
        main.innerHTML = "<p>Selecione um projeto para continuar.</p>";
        return;
      }
      const project = boot.projects.find((p) => +p.idobra === obra);
      if (project.portal_obra_id) await load();
      else if (boot.admin) setup();
    });
  run(async () => {
    boot = await api("bootstrap");
    projectSelect.innerHTML =
      '<option value="">Selecione um projeto</option>' +
      boot.projects
        .map(
          (p) =>
            `<option value="${+p.idobra}">${esc(p.nome_obra)}${p.portal_obra_id ? " · Portal configurado" : ""}</option>`,
        )
        .join("");
    main.innerHTML = boot.projects.length
      ? "<p>Selecione um projeto para preparar o convite ou revisar sua solicitação de materiais.</p>"
      : "<p>Nenhum projeto foi designado para sua curadoria. Peça à gestão para vincular seu usuário.</p>";
  });
})();
