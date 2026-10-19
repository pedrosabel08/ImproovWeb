/* Reuses the authenticated Flow task panel and all its original actions. */
(() => {
  const scriptUrl = document.currentScript.src;
  window.FlowTaskOverlay = {
    open(stage, trigger) {
      if (document.querySelector(".mp-task-dialog")) return;
      const body = document.querySelector(".mp-op-body"),
        scroll = body?.scrollTop;
      const dialog = document.createElement("dialog");
      dialog.className = "mp-task-dialog";
      dialog.setAttribute("aria-label", "Card da tarefa: " + stage.funcao_nome);
      const header = document.createElement("header"),
        title = document.createElement("strong"),
        close = document.createElement("button");
      title.textContent = stage.funcao_nome;
      close.type = "button";
      close.textContent = "×";
      close.setAttribute("aria-label", "Fechar card da tarefa");
      close.addEventListener("click", () => dialog.close());
      header.append(title, close);
      const loading = document.createElement("div");
      loading.className = "mp-task-loading";
      loading.setAttribute("role", "status");
      const orb = document.createElement("canvas");
      orb.dataset.thinkingOrb = "";
      orb.dataset.orbState = "composing";
      orb.dataset.orbSize = "48";
      orb.setAttribute("aria-label", "Carregando card da tarefa");
      const loadingText = document.createElement("span");
      loadingText.textContent = "Carregando card da tarefa";
      loading.append(orb, loadingText);
      const frame = document.createElement("iframe");
      frame.title = "Card completo de " + stage.funcao_nome;
      const url = new URL("../../../inicio.php", scriptUrl);
      url.searchParams.set("embed_task", "1");
      url.searchParams.set("focus_task", stage.funcao_imagem_id);
      url.searchParams.set("imagem_id", stage.imagem_id);
      frame.src = url.href;
      const message = (event) => {
        if (
          event.origin !== location.origin ||
          event.source !== frame.contentWindow
        )
          return;
        if (event.data?.type === "flow-task-close") dialog.close();
        if (event.data?.type === "flow-task-ready") loading.remove();
      };
      window.addEventListener("message", message);
      dialog.addEventListener(
        "close",
        () => {
          window.removeEventListener("message", message);
          dialog.remove();
          trigger?.focus({ preventScroll: true });
          if (body) body.scrollTop = scroll;
        },
        { once: true },
      );
      dialog.append(header, loading, frame);
      document.body.append(dialog);
      dialog.showModal();
      window.thinkingOrbs?.(loading);
    },
  };
})();
