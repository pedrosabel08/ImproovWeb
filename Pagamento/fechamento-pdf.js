/** PDF contínuo, com todas as páginas dos mesmos bytes entregues pela API. */
export function createPdfView(
  container,
  previous,
  next,
  label,
  zoomOut,
  zoomIn,
  zoomLabel,
) {
  let document = null,
    task = null,
    rendering = null,
    page = 1,
    sequence = 0,
    zoom = 1,
    drawing = false,
    fitWidth = 0,
    resizeTimer = null;
  window.pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
    "../assets/pdfjs/pdf.worker.min.js",
    import.meta.url,
  ).href;
  const controls = () => {
    previous.disabled = drawing || !document || page === 1;
    next.disabled = drawing || !document || page === document.numPages;
    zoomOut.disabled = drawing || !document || zoom === 1;
    zoomIn.disabled = drawing || !document || zoom === 3;
    zoomLabel.textContent = Math.round(zoom * 100) + "%";
  };
  function navigate(target) {
    if (drawing || !document) return;
    const element = container.querySelector('[data-pdf-page="' + target + '"]');
    if (!element) return;
    container.scrollTop +=
      element.getBoundingClientRect().top -
      container.getBoundingClientRect().top -
      2;
    page = target;
    updateLabel();
  }
  function updateLabel() {
    if (!document || drawing) return;
    label.textContent =
      "Página " +
      page +
      " de " +
      document.numPages +
      " · Todas as páginas exibidas";
    controls();
  }
  async function drawAll(current = sequence, target = page) {
    if (!document || current !== sequence) return false;
    drawing = true;
    controls();
    const pdfDocument = document;
    fitWidth = container.clientWidth;
    const width = Math.max(180, Math.min(1050, fitWidth - 24)) * zoom;
    const fragment = window.document.createDocumentFragment();
    try {
      for (let number = 1; number <= pdfDocument.numPages; number++) {
        label.textContent =
          "Carregando página " + number + " de " + pdfDocument.numPages;
        const pdfPage = await pdfDocument.getPage(number);
        if (current !== sequence) return false;
        const original = pdfPage.getViewport({ scale: 1 });
        const viewport = pdfPage.getViewport({ scale: width / original.width });
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        const section = window.document.createElement("section");
        section.className = "fc-pdf-page";
        section.dataset.pdfPage = number;
        section.setAttribute(
          "aria-label",
          "Página " + number + " de " + pdfDocument.numPages + " do PDF",
        );
        const canvas = window.document.createElement("canvas");
        canvas.width = Math.ceil(viewport.width * ratio);
        canvas.height = Math.ceil(viewport.height * ratio);
        canvas.style.width = viewport.width + "px";
        canvas.style.height = "auto";
        canvas.setAttribute("aria-hidden", "true");
        section.append(canvas);
        rendering = pdfPage.render({
          canvasContext: canvas.getContext("2d"),
          viewport,
          transform: [ratio, 0, 0, ratio, 0, 0],
        });
        await rendering.promise;
        if (current !== sequence) return false;
        const content = await pdfPage.getTextContent();
        if (current !== sequence) return false;
        const text = window.document.createElement("div");
        text.className = "fc-pdf-text";
        text.textContent = content.items.map((item) => item.str).join(" ");
        section.append(text);
        fragment.append(section);
      }
      if (current !== sequence) return false;
      container.replaceChildren(fragment);
      rendering = null;
      drawing = false;
      navigate(target);
      updateLabel();
      return true;
    } finally {
      if (current === sequence) {
        drawing = false;
        controls();
        onResize();
      }
    }
  }
  async function close() {
    ++sequence;
    rendering?.cancel();
    rendering = null;
    clearTimeout(resizeTimer);
    resizeTimer = null;
    fitWidth = 0;
    const oldTask = task;
    task = null;
    document = null;
    drawing = false;
    container.replaceChildren();
    label.textContent = "";
    zoom = 1;
    page = 1;
    controls();
    if (oldTask) await oldTask.destroy();
  }
  const redraw = () => {
    const current = sequence;
    drawAll(current, page).catch(() => {
      if (current === sequence)
        label.textContent = "Não foi possível exibir todas as páginas do PDF.";
    });
  };
  function onResize() {
    if (!document || drawing || container.clientWidth === fitWidth) return;
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(redraw, 120);
  }
  if (window.ResizeObserver)
    new window.ResizeObserver(onResize).observe(container);
  previous.onclick = () => navigate(page - 1);
  next.onclick = () => navigate(page + 1);
  zoomOut.onclick = () => {
    zoom = Math.max(1, zoom - 0.5);
    redraw();
  };
  zoomIn.onclick = () => {
    zoom = Math.min(3, zoom + 0.5);
    redraw();
  };
  container.addEventListener(
    "scroll",
    () => {
      if (!document || drawing) return;
      const marker =
        container.getBoundingClientRect().top + container.clientHeight / 2;
      const pages = [...container.querySelectorAll("[data-pdf-page]")];
      const current = pages.find(
        (element) => element.getBoundingClientRect().bottom > marker,
      );
      if (current) {
        page = Number(current.dataset.pdfPage);
        updateLabel();
      }
    },
    { passive: true },
  );
  return {
    close,
    async open(blob) {
      await close();
      const current = sequence;
      label.textContent = "Abrindo PDF";
      const bytes = new Uint8Array(await blob.arrayBuffer());
      if (current !== sequence) return false;
      task = window.pdfjsLib.getDocument({
        data: bytes,
        isEvalSupported: false,
      });
      const loaded = await task.promise;
      if (current !== sequence) return false;
      document = loaded;
      return await drawAll(current, 1);
    },
  };
}
