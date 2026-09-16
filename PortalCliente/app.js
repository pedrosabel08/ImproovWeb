"use strict";
(() => {
  const main = document.querySelector("#main"),
    notice = document.querySelector("#notice"),
    nav = document.querySelector("#nav");
  const token = new URLSearchParams(location.search).get("t") || "";
  let csrf = document.querySelector('meta[name="portal-csrf"]').content,
    data,
    email = "",
    view = "inicio",
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
    notice.scrollIntoView({ block: "nearest", behavior: "smooth" });
  };
  async function api(action, body = {}) {
    const controller = new AbortController(),
      timer = setTimeout(() => controller.abort(), 25000);
    try {
      const r = await fetch("api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-Portal-CSRF": csrf },
        body: JSON.stringify({ action, token, ...body }),
        signal: controller.signal,
      });
      let j;
      try {
        j = await r.json();
      } catch {
        throw Error(
          "Não conseguimos ler a resposta. Tente novamente em instantes.",
        );
      }
      if (!r.ok || !j.ok)
        throw Error(j.message || "Não foi possível continuar.");
      return j;
    } catch (e) {
      if (e.name === "AbortError" || e instanceof TypeError)
        throw Error(
          "A conexão foi interrompida. Confira sua internet e tente novamente.",
        );
      throw e;
    } finally {
      clearTimeout(timer);
    }
  }
  async function run(fn) {
    if (busy) return;
    busy = true;
    main.setAttribute("aria-busy", "true");
    main.querySelectorAll("button").forEach((b) => (b.disabled = true));
    try {
      await fn();
    } catch (e) {
      tell(e.message, true);
    } finally {
      busy = false;
      main.setAttribute("aria-busy", "false");
      main.querySelectorAll("button").forEach((b) => (b.disabled = false));
    }
  }
  const checks = (ids, selected = []) =>
    `<div class="choices">${data.catalog
      .filter((d) => ids.includes(+d.id))
      .map(
        (d) =>
          `<label><input type="checkbox" name="disciplinas" value="${+d.id}" ${selected.includes(+d.id) ? "checked" : ""}>${esc(d.nome)}</label>`,
      )
      .join("")}</div>`;
  const selected = (form) =>
    Array.from(new FormData(form).getAll("disciplinas"), Number);
  const disciplines = (ids) =>
    ids
      .map((id) => data.catalog.find((d) => +d.id === +id)?.nome)
      .filter(Boolean)
      .map((n) => `<span class="tag">${esc(n)}</span>`)
      .join("");
  function entry(project, closed = false) {
    nav.hidden = true;
    document.querySelector("#project-context").textContent = [
      project.cliente,
      project.local,
    ]
      .filter(Boolean)
      .join(" · ");
    main.innerHTML = `<h1>${esc(project.nome)}</h1><p class="lead">${closed ? "Este projeto está encerrado. Fale com seu contato na Improov para saber mais." : "Bem-vindo ao espaço do seu projeto. Vamos cuidar dos próximos passos juntos."}</p>${closed ? "" : `<form id="entry" class="form"><p>Para entrar, confirme quem você é. Enviaremos um código ao seu e-mail.</p><label>Seu nome<input name="nome" autocomplete="name" required maxlength="150"></label><label>Seu e-mail<input name="email" type="email" autocomplete="email" required maxlength="150"></label><label>Seu telefone<input name="telefone" type="tel" autocomplete="tel" required maxlength="30"></label><button class="primary">Receber código de acesso</button></form>`}`;
    const form = main.querySelector("#entry");
    form?.addEventListener("submit", (e) => {
      e.preventDefault();
      run(async () => {
        const body = Object.fromEntries(new FormData(e.target));
        await api("access.start", body);
        email = body.email;
        otp(body);
        tell("Código enviado. Confira seu e-mail para continuar.");
      });
    });
    if (form) {
      const resume = document.createElement("button");
      resume.type = "button";
      resume.className = "quiet";
      resume.textContent = "Já recebi um código";
      resume.onclick = () => {
        if (!form.reportValidity()) return;
        const body = Object.fromEntries(new FormData(form));
        email = body.email;
        otp(body);
      };
      form.append(resume);
    }
  }
  function otp(body) {
    main.innerHTML = `<h1>Um último cuidado antes de entrar.</h1><p class="lead">Enviamos um código para ${esc(email)}. Ele confirma sua identidade e protege o seu projeto.</p><form id="otp" class="form"><label>Código de acesso<input name="code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" required></label><button class="primary">Entrar no projeto</button><button type="button" id="resend" class="quiet">Enviar outro código</button><button type="button" id="back" class="quiet">Corrigir meus dados</button></form>`;
    main.querySelector("#otp").addEventListener("submit", (e) => {
      e.preventDefault();
      run(async () => {
        const j = await api("access.verify", {
          email,
          code: new FormData(e.target).get("code"),
        });
        csrf = j.csrf;
        await refresh();
        tell("Bem-vindo. Sua identidade foi confirmada.");
      });
    });
    main.querySelector("#resend").onclick = () =>
      run(async () => {
        await api("access.start", body);
        tell("Enviamos outro código. Use o mais recente.");
      });
    main.querySelector("#back").onclick = () => run(start);
  }
  async function refresh(target = view) {
    const j = await api("project.get");
    data = j.data;
    csrf = j.csrf;
    nav.hidden = false;
    document.querySelector("#project-context").textContent = [
      data.project.nome,
      data.project.cliente,
      data.project.local,
    ]
      .filter(Boolean)
      .join(" · ");
    render(target);
  }
  function render(target) {
    view = target;
    nav
      .querySelectorAll("[data-view]")
      .forEach((b) =>
        b.setAttribute(
          "aria-current",
          b.dataset.view === view ? "page" : "false",
        ),
      );
    if (target === "inicio") {
      const m = data.moment;
      main.innerHTML = `<h1>${esc(m.title)}</h1><p class="lead">${esc(m.context)}</p>${m.action ? `<button class="primary" id="dominant">${{ perfil: "Contar minha participação", preparacao: "Preparar o projeto", materiais: "Conhecer os materiais" }[m.action]}</button>` : ""}<div class="next"><strong>Próximo momento</strong><p>${esc(m.next)}</p></div>`;
      main
        .querySelector("#dominant")
        ?.addEventListener("click", () => render(m.action));
    }
    if (target === "preparacao") {
      main.innerHTML = `<h1>O que vamos construir juntos?</h1><p class="lead">Conte-nos quais disciplinas fazem parte deste projeto. Nossa equipe usará essa informação para preparar os materiais necessários.</p><form id="preparation" class="form"><fieldset><legend>Quais disciplinas estarão presentes?</legend>${checks(
        data.catalog.map((d) => +d.id),
        data.disciplinas,
      )}</fieldset><button class="primary">Confirmar disciplinas do projeto</button></form>`;
      main.querySelector("form").onsubmit = (e) => {
        e.preventDefault();
        run(async () => {
          const j = await api("preparation.save", {
            question: "disciplinas",
            disciplinas: selected(e.target),
            revisao: data.project.revisao,
          });
          await refresh("inicio");
          tell(j.message);
        });
      };
    }
    if (target === "perfil") {
      main.innerHTML = `<h1>Como você participa deste projeto?</h1><p class="lead">Você pode acompanhar uma ou várias disciplinas, ou participar sem escolher nenhuma.</p><form id="profile" class="form"><label>Seu nome<input name="nome" required maxlength="150" autocomplete="name" value="${esc(data.me.nome)}"></label><label>Seu telefone<input name="telefone" required type="tel" maxlength="30" autocomplete="tel" value="${esc(data.me.telefone)}"></label><p class="hint">${esc(data.me.email)} · identidade confirmada</p><fieldset><legend>Disciplinas em que você participa (opcional)</legend>${data.disciplinas.length ? checks(data.disciplinas, data.me.disciplinas) : '<p class="hint">As disciplinas ainda estão sendo definidas. Você poderá escolhê-las depois.</p>'}</fieldset>${data.project.aberto ? '<button class="primary">Confirmar minha participação</button>' : "<p>Este projeto está encerrado.</p>"}</form>`;
      main.querySelector("form").onsubmit = (e) => {
        e.preventDefault();
        run(async () => {
          const j = await api("profile.save", {
            ...Object.fromEntries(new FormData(e.target)),
            disciplinas: selected(e.target),
          });
          await refresh("inicio");
          tell(j.message);
        });
      };
    }
    if (target === "equipe") {
      main.innerHTML = `<h1>As pessoas que constroem com você.</h1><p class="lead">Este espaço continua aberto para quem faz parte do projeto. Cada pessoa escolhe como participa.</p>${data.project.aberto && data.project.inscricoes_abertas ? '<button class="primary" id="invite">Convidar uma pessoa</button><div id="share" class="share-box" hidden></div>' : ""}<div class="cards">${data.team.map((t) => `<article class="card"><h2>${esc(t.nome)}</h2>${t.administrador ? '<span class="tag">Administrador do projeto</span>' : ""}<p>${t.disciplinas.length ? disciplines(t.disciplinas) : "Acompanha o projeto"}</p>${!t.ingressou_em ? '<p class="hint">Convite preparado · aguardando primeiro acesso</p>' : ""}</article>`).join("")}</div>${data.project.aberto && !data.published ? '<button class="quiet" data-prep>Rever disciplinas do projeto</button>' : ""}`;
      main
        .querySelector("[data-prep]")
        ?.addEventListener("click", () => render("preparacao"));
      main.querySelector("#invite")?.addEventListener("click", () =>
        run(async () => {
          const j = await api("invite.share");
          const url = new URL("index.php", location.href);
          url.searchParams.set("t", token);
          const box = main.querySelector("#share");
          box.hidden = false;
          box.innerHTML = `<label class="field">Link do convite<input readonly value="${esc(url.href)}"></label><p class="hint">Compartilhe no grupo oficial do projeto. O link não substitui a identificação de cada pessoa.</p><button class="quiet" id="copy">Copiar convite</button>`;
          box.querySelector("#copy").onclick = () =>
            run(async () => {
              try {
                await navigator.clipboard.writeText(url.href);
                tell(
                  "Convite copiado. Agora você pode compartilhá-lo com a equipe.",
                );
              } catch {
                box.querySelector("input").select();
                tell("Selecione e copie o link acima.");
              }
            });
          tell(j.message);
        }),
      );
    }
    if (target === "materiais") {
      if (!data.published) {
        main.innerHTML =
          '<h1>Uma seleção feita para o seu projeto.</h1><p class="lead">Nossa equipe está organizando os materiais necessários. Quando estiverem prontos, você encontrará aqui o contexto de cada um.</p><div class="next"><strong>Próximo momento</strong><p>Conhecer os materiais que nos ajudarão a começar.</p></div>';
      } else {
        main.innerHTML = `<h1>Materiais para construir a próxima etapa.</h1><p class="lead">Preparamos esta seleção para que você saiba o que vamos precisar e por quê.</p>${[
          [
            "INICIO",
            "Precisamos para começar",
            "Essenciais para preparar a base do projeto.",
          ],
          [
            "DURANTE",
            "Vamos precisar durante o projeto",
            "Materiais que serão importantes nos próximos momentos.",
          ],
        ]
          .map(
            ([key, title, subtitle]) =>
              `<section class="group"><h2>${title}</h2><p>${subtitle}</p><div class="cards">${
                data.materials
                  .filter((m) => m.momento === key)
                  .map(
                    (m) =>
                      `<article class="card"><h3>${esc(m.titulo)}</h3>${disciplines([+m.disciplina_id])}${data.me.disciplinas.includes(+m.disciplina_id) ? '<span class="tag">Da sua disciplina</span>' : ""}<p>${esc(m.contexto)}</p><p><strong>Formatos aceitos</strong> · ${m.formatos.map(esc).join(", ")}</p>${m.observacao ? `<p>${esc(m.observacao)}</p>` : ""}</article>`,
                  )
                  .join("") ||
                '<p class="hint">Nenhum material solicitado neste momento.</p>'
              }</div></section>`,
          )
          .join(
            "",
          )}<div class="next"><strong>Próximo momento</strong><p>Reunir os materiais com você. O envio pelo Portal ainda não está disponível; fale com seu contato na Improov se precisar de orientação.</p></div>`;
      }
    }
    main.setAttribute("aria-busy", "false");
  }
  nav.addEventListener("click", (e) => {
    const b = e.target.closest("[data-view]");
    if (b && !busy) run(() => refresh(b.dataset.view));
  });
  document.querySelector("#logout").onclick = () =>
    run(async () => {
      await api("access.logout");
      location.reload();
    });
  async function start() {
    const j = await api("access.inspect");
    if (j.authenticated) await refresh();
    else entry(j.project, j.closed);
  }
  run(start).then(() => {
    if (main.textContent.includes("Estamos preparando seu espaço")) {
      main.innerHTML =
        '<h1>Vamos retomar seu acesso.</h1><p class="lead">Confira o convite recebido ou tente novamente em instantes.</p><button class="primary" id="retry">Tentar novamente</button>';
      main.querySelector("#retry").onclick = () => run(start);
    }
  });
})();
