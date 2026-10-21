// Ponte com os fluxos existentes da obra. A apresentação fica no componente.
let obraHistoryView;
function renderObraHistory(rows) {
  const container = document.getElementById("list_acomp");
  if (!container) return;
  if (!obraHistoryView)
    obraHistoryView = FlowObraHistory.mount(container, {
      change() {
        const pop = window.__acompPopover;
        if (pop) {
          pop.el.classList.add("hidden");
          pop.el.setAttribute("aria-hidden", "true");
          pop.openAcomp = null;
          pop.anchor = null;
        }
      },
      edit(row, title) {
        if (title.querySelector("input")) return;
        const span = title.querySelector(".acomp-texto");
        const input = document.createElement("input");
        input.className = "input-edicao";
        input.value = row.assunto;
        input.setAttribute("aria-label", "Descrição do registro");
        span.replaceWith(input);
        input.focus();
        let cancelled = false;
        input.addEventListener("keydown", (event) => {
          if (event.key === "Escape") {
            cancelled = true;
            input.blur();
          }
          if (event.key === "Enter") input.blur();
        });
        input.addEventListener(
          "blur",
          async () => {
            const value = input.value.trim();
            if (!cancelled && value && value !== row.assunto) {
              input.disabled = true;
              if (await salvarAcompanhamento(row.id, idObra, value, row.tipo)) {
                row.assunto = value;
                OBRA_ACOMP_CACHE.delete(String(idObra));
              }
            }
            span.textContent = row.assunto;
            input.replaceWith(span);
            obraHistoryView.refresh();
          },
          { once: true },
        );
      },
      async remove(row) {
        const confirmation = await FlowAlert.confirm({
          title: "Excluir acompanhamento?",
          confirmText: "Excluir",
        });
        if (!confirmation.isConfirmed) return;
        try {
          const response = await fetch("../deleteAcompanhamento.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({ id: row.id, action: "delete" }),
          });
          const result = await response.json();
          if (!response.ok || !(result.success || result.deleted))
            throw new Error(result.message || "Falha ao excluir.");
          window.__acompFetched = window.__acompFetched.filter(
            (item) => String(item.id) !== String(row.id),
          );
          OBRA_ACOMP_CACHE.delete(String(idObra));
          renderAcompanhamentosList(window.__acompFetched);
          Toastify({
            text: "Acompanhamento excluído",
            duration: 2500,
          }).showToast();
        } catch (error) {
          Toastify({ text: error.message, duration: 3500 }).showToast();
        }
      },
      files(row) {
        const files = Array.isArray(row.arquivos) ? row.arquivos : [];
        if (files.length) {
          const dialog =
            document.getElementById("historyFilesDialog") ||
            (() => {
              const element = document.createElement("dialog");
              element.id = "historyFilesDialog";
              element.className = "history-files-dialog";
              document.body.append(element);
              return element;
            })();
          dialog.replaceChildren();
          const heading = document.createElement("h2");
          heading.textContent = "Arquivos do registro";
          const close = document.createElement("button");
          close.textContent = "Fechar";
          close.addEventListener("click", () => dialog.close());
          dialog.append(heading, close);
          files.forEach((file) => {
            if (!file.url) {
              const text = document.createElement("p");
              text.textContent = file.nome_original || "Arquivo";
              dialog.append(text);
              return;
            }
            let url;
            try {
              url = new URL(file.url, document.baseURI);
            } catch {
              return;
            }
            if (!/^https?:$/.test(url.protocol)) return;
            const link = document.createElement("a");
            link.href = url.href;
            link.target = "_blank";
            link.rel = "noopener";
            link.textContent = file.nome_original || "Arquivo";
            dialog.append(link);
          });
          const drive = document.createElement("button");
          drive.textContent = "Arquivos da obra";
          drive.addEventListener("click", () => {
            dialog.close();
            carregarArquivosObra(idObra)?.catch(() => {});
            document
              .getElementById("secao-arquivos")
              ?.scrollIntoView({ behavior: "auto" });
          });
          dialog.append(drive);
          dialog.showModal();
          return;
        }
        carregarArquivosObra(idObra)?.catch(() => {});
        document.getElementById("secao-arquivos")?.scrollIntoView({
          behavior: matchMedia("(prefers-reduced-motion: reduce)").matches
            ? "auto"
            : "smooth",
        });
      },
      async delivery(row, anchor) {
        const state = window.__acompPopover,
          pop = state.el;
        if (state.openAcomp === row.id && !pop.classList.contains("hidden")) {
          pop.classList.add("hidden");
          pop.setAttribute("aria-hidden", "true");
          state.openAcomp = null;
          return;
        }
        state.openAcomp = row.id;
        state.anchor = anchor;
        pop.classList.remove("hidden");
        pop.setAttribute("aria-hidden", "false");
        const content = pop.querySelector(".itens");
        content.textContent = "Carregando...";
        positionPopover(pop, anchor);
        try {
          if (!state.cache[row.id]) {
            const response = await fetch(
              `../Obras/getItensEntregaPorAcompanhamento.php?acomp_id=${encodeURIComponent(row.id)}`,
            );
            const data = await response.json();
            if (!response.ok || !data.success)
              throw new Error("Erro ao carregar imagens da entrega.");
            state.cache[row.id] = data.itens || [];
          }
          if (state.openAcomp === row.id) {
            renderItensList(pop, state.cache[row.id]);
            positionPopover(pop, anchor);
          }
        } catch (error) {
          if (state.openAcomp === row.id) content.textContent = error.message;
        }
      },
    });
  obraHistoryView.setRows(rows);
}

function exportObraHistory(id, category) {
  const form = document.createElement("form");
  form.method = "POST";
  form.target = "_blank";
  form.action = "../Obras/historico_pdf.php";
  const state = obraHistoryView?.state() || {
    category,
    period: "all",
    query: "",
  };
  const ids = FlowObraHistory.filter(window.__acompFetched || [], state).map(
    (row) => row.id,
  );
  Object.entries({
    idobra: id,
    category,
    registro_ids: JSON.stringify(ids),
  }).forEach(([key, value]) => {
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = key;
    input.value = value;
    form.append(input);
  });
  document.body.append(form);
  form.submit();
  form.remove();
}
