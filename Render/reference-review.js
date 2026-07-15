(function () {
  const state = {
    renderId: 0,
    posId: 0,
    approvalOrigin: null,
    editOnly: false,
    items: [],
    current: null,
    tool: "select",
    drawing: null,
    zoom: 1,
    pan: { x: 0, y: 0 },
    panning: null,
    commentTarget: null,
  };
  const $ = window.jQuery;
  const byId = (id) => document.getElementById(id);
  const flowBaseUrl = "https://improov.com.br/flow/ImproovWeb/";
  const imageUrl = (item) =>
    item.origin === "draft"
      ? item.arquivo
      : item.origin === "render_principal" || item.key === "main"
        ? flowBaseUrl +
          "uploads/renders/" +
          encodeURIComponent(item.preview || item.arquivo || "")
        : flowBaseUrl +
          "uploads/pos_referencias/" +
          encodeURIComponent(item.arquivo);
  const annotationsUrl = "../Pos-Producao/referencias_comentarios.php";

  function toast(text, color) {
    if (window.Toastify)
      Toastify({
        text,
        duration: 3500,
        gravity: "top",
        position: "right",
        backgroundColor: color || "#4caf50",
      }).showToast();
    else alert(text);
  }
  function current() {
    return state.items.find((item) => item.key === state.current);
  }
  function parsePath(comment) {
    try {
      return comment.path_data ? JSON.parse(comment.path_data) : null;
    } catch (_) {
      return null;
    }
  }
  function point(event) {
    const canvas = byId("rrCanvas"),
      rect = canvas.getBoundingClientRect();
    return {
      x: Math.max(
        0,
        Math.min(100, ((event.clientX - rect.left) / rect.width) * 100),
      ),
      y: Math.max(
        0,
        Math.min(100, ((event.clientY - rect.top) / rect.height) * 100),
      ),
    };
  }
  function canvasPoint(pointValue) {
    const c = byId("rrCanvas");
    return {
      x: (pointValue.x * c.width) / 100,
      y: (pointValue.y * c.height) / 100,
    };
  }
  function objectBounds(object) {
    const pts = object.points || [object.start, object.end].filter(Boolean);
    const xs = pts.map((p) => p.x),
      ys = pts.map((p) => p.y);
    return {
      minX: Math.min.apply(null, xs),
      maxX: Math.max.apply(null, xs),
      minY: Math.min.apply(null, ys),
      maxY: Math.max.apply(null, ys),
    };
  }
  function drawObject(ctx, object, color, width) {
    if (!object) return;
    const c = ctx.canvas,
      style = color || "#f59e0b",
      lineWidth = Number(width || object.espessura || 2);
    const start = object.start || (object.points || [])[0],
      end = object.end || start;
    if (!start || !end) return;
    const a = canvasPoint(start),
      b = canvasPoint(end);
    ctx.save();
    ctx.strokeStyle = style;
    ctx.fillStyle = style;
    ctx.lineWidth = lineWidth;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    if (object.tool === "pencil") {
      ctx.beginPath();
      (object.points || []).forEach((p, i) => {
        const q = canvasPoint(p);
        i ? ctx.lineTo(q.x, q.y) : ctx.moveTo(q.x, q.y);
      });
      ctx.stroke();
    } else if (object.tool === "arrow") {
      const angle = Math.atan2(b.y - a.y, b.x - a.x);
      ctx.beginPath();
      ctx.moveTo(a.x, a.y);
      ctx.lineTo(b.x, b.y);
      ctx.lineTo(
        b.x - 12 * Math.cos(angle - Math.PI / 6),
        b.y - 12 * Math.sin(angle - Math.PI / 6),
      );
      ctx.moveTo(b.x, b.y);
      ctx.lineTo(
        b.x - 12 * Math.cos(angle + Math.PI / 6),
        b.y - 12 * Math.sin(angle + Math.PI / 6),
      );
      ctx.stroke();
    } else if (object.tool === "rectangle")
      ctx.strokeRect(a.x, a.y, b.x - a.x, b.y - a.y);
    else if (object.tool === "circle") {
      ctx.beginPath();
      ctx.ellipse(
        (a.x + b.x) / 2,
        (a.y + b.y) / 2,
        Math.abs(b.x - a.x) / 2,
        Math.abs(b.y - a.y) / 2,
        0,
        0,
        Math.PI * 2,
      );
      ctx.stroke();
    }
    ctx.restore();
  }
  function drawCommentNumber(ctx, object, number, color) {
    if (!object || number == null) return;
    const anchor = object.start || (object.points || [])[0];
    if (!anchor) return;
    const point = canvasPoint(anchor),
      radius = 12,
      x = Math.max(
        radius + 2,
        Math.min(ctx.canvas.width - radius - 2, point.x),
      ),
      y = Math.max(
        radius + 2,
        Math.min(ctx.canvas.height - radius - 2, point.y),
      );
    ctx.save();
    ctx.fillStyle = color || "#f59e0b";
    ctx.strokeStyle = "#fff";
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(x, y, radius, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.fillStyle = "#111827";
    ctx.font = "700 12px Arial, sans-serif";
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(String(number), x, y + 0.5);
    ctx.restore();
  }
  function hasDrawing(annotation, object) {
    return (
      !!object && (annotation.possui_desenho || annotation.tipo === "freehand")
    );
  }
  function redraw() {
    const item = current(),
      canvas = byId("rrCanvas");
    if (!item || !canvas.width) return;
    const ctx = canvas.getContext("2d");
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const annotations = item.annotations || [];
    annotations.forEach((a, index) => {
      const object = parsePath(a);
      drawObject(ctx, object, a.cor, a.espessura);
      if (hasDrawing(a, object))
        drawCommentNumber(ctx, object, index + 1, a.cor);
    });
    (item.drafts || []).forEach((a, index) => {
      const object = a.path_data;
      drawObject(ctx, object, a.cor, a.espessura);
      if (hasDrawing(a, object))
        drawCommentNumber(ctx, object, annotations.length + index + 1, a.cor);
    });
    if (state.drawing)
      drawObject(
        ctx,
        state.drawing,
        byId("rrColor").value,
        byId("rrWidth").value,
      );
  }
  function updateTransform() {
    byId("rrLayer").style.transform =
      `translate(${state.pan.x}px, ${state.pan.y}px) scale(${state.zoom})`;
    byId("rrZoomLabel").textContent = Math.round(state.zoom * 100) + "%";
  }
  function fitCanvas() {
    const img = byId("rrImage"),
      canvas = byId("rrCanvas");
    canvas.width = img.clientWidth;
    canvas.height = img.clientHeight;
    canvas.style.width = img.clientWidth + "px";
    canvas.style.height = img.clientHeight + "px";
    redraw();
  }
  function renderList() {
    const list = byId("rrReferenceList");
    list.innerHTML = "";
    state.items.forEach((item) => {
      const node = document.createElement("div");
      node.className =
        "rr-reference-item" + (item.key === state.current ? " is-active" : "");
      node.dataset.key = item.key;
      node.innerHTML = `<img alt=""><span></span>`;
      node.querySelector("img").src = imageUrl(item);
      node.querySelector("span").textContent =
        item.nome_original ||
        (item.origin === "render_principal"
          ? "Render principal"
          : "Referência");
      if (item.origin !== "render_principal") {
        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "rr-reference-remove";
        remove.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        remove.addEventListener("click", (event) => {
          event.stopPropagation();
          removeReference(item);
        });
        node.appendChild(remove);
      }
      node.addEventListener("click", () => select(item.key));
      list.appendChild(node);
    });
  }
  function renderComments() {
    const item = current(),
      list = byId("rrCommentList");
    list.innerHTML = "";
    if (!item) return;
    const annotations = item.annotations || [];
    annotations.forEach((a, index) => {
      const number = index + 1;
      const node = document.createElement("div");
      node.className = "rr-comment";
      node.innerHTML = `<div class="rr-comment__head"><span></span><button type="button" class="rr-comment__delete"><i class="fa-regular fa-trash-can"></i></button></div><div></div>`;
      node.querySelector("span").textContent =
        number + " · " + (a.nome_colaborador || "Anotação");
      const description =
        a.possui_desenho && a.texto
          ? "Desenho: " + a.texto
          : a.texto || "Desenho";
      node.querySelector("div:last-child").textContent = description;
      node
        .querySelector("button")
        .addEventListener("click", () => removeAnnotation(item, a));
      list.appendChild(node);
    });
    (item.drafts || []).forEach((a, index) => {
      const number = annotations.length + index + 1;
      const node = document.createElement("div");
      node.className = "rr-comment";
      node.innerHTML = `<div class="rr-comment__head"><span></span><button type="button"><i class="fa-regular fa-trash-can"></i></button></div><div></div>`;
      node.querySelector("span").textContent =
        "Comentário #" + number + " · Não salvo";
      const description =
        a.possui_desenho && a.texto
          ? "Desenho: " + a.texto
          : a.texto || "Desenho";
      node.querySelector("div:last-child").textContent =
        "Comentário #" + number + ": " + description;
      node.querySelector("button").addEventListener("click", () => {
        if (state.commentTarget === a.draft_id) state.commentTarget = null;
        item.drafts.splice(index, 1);
        redraw();
        renderComments();
        updateCommentBinding();
      });
      list.appendChild(node);
    });
  }
  function loadAnnotations(item) {
    if (!item.reference_id) {
      item.annotations = [];
      item.drafts = item.drafts || [];
      renderComments();
      redraw();
      return;
    }
    fetch(
      annotationsUrl +
        "?referencia_id=" +
        encodeURIComponent(item.reference_id),
    )
      .then((r) => r.json())
      .then((data) => {
        item.annotations = data.sucesso ? data.comentarios : [];
        item.drafts = item.drafts || [];
        renderComments();
        redraw();
      });
  }
  function select(key) {
    state.current = key;
    state.drawing = null;
    state.commentTarget = null;
    updateCommentBinding();
    renderList();
    const item = current(),
      img = byId("rrImage");
    img.onload = () => {
      state.zoom = 1;
      state.pan = { x: 0, y: 0 };
      updateTransform();
      fitCanvas();
      loadAnnotations(item);
    };
    img.src = imageUrl(item);
  }
  function createDraftFromDrawing() {
    const item = current();
    if (!state.drawing || !item) return;
    if (!isMeaningfulDrawing(state.drawing)) {
      state.drawing = null;
      redraw();
      return;
    }
    item.drafts = item.drafts || [];
    const draft = {
      draft_id:
        "drawing_" + Date.now() + "_" + Math.random().toString(36).slice(2),
      texto: "",
      tipo: "freehand",
      possui_desenho: true,
      path_data: state.drawing,
      cor: byId("rrColor").value,
      espessura: Number(byId("rrWidth").value),
    };
    item.drafts.push(draft);
    state.commentTarget = draft.draft_id;
    state.drawing = null;
    redraw();
    renderComments();
    updateCommentBinding();
  }
  function isMeaningfulDrawing(drawing) {
    if (!drawing) return false;
    if (drawing.tool === "pencil") {
      const points = drawing.points || [];
      if (points.length < 2) return false;
      const first = points[0],
        last = points[points.length - 1];
      return (
        Math.abs(first.x - last.x) > 0.25 || Math.abs(first.y - last.y) > 0.25
      );
    }
    if (!drawing.start || !drawing.end) return false;
    return (
      Math.abs(drawing.start.x - drawing.end.x) > 0.25 ||
      Math.abs(drawing.start.y - drawing.end.y) > 0.25
    );
  }
  function updateCommentBinding() {
    const hint = byId("rrCommentBinding");
    if (!hint) return;
    const item = current();
    const drawing =
      item &&
      (item.drafts || []).find(
        (draft) =>
          draft.draft_id === state.commentTarget && draft.possui_desenho,
      );
    hint.textContent = drawing
      ? "O próximo comentário será vinculado ao último desenho."
      : "O comentário será salvo sem desenho.";
  }
  function removeAnnotation(item, annotation) {
    if (!annotation.id) return;
    const data = new FormData();
    data.append("referencia_id", item.reference_id);
    data.append("comentario_id", annotation.id);
    data.append("_method", "DELETE");
    fetch(annotationsUrl, { method: "POST", body: data })
      .then((r) => r.json())
      .then((response) => {
        if (!response.sucesso) throw new Error(response.erro);
        item.annotations = item.annotations.filter(
          (a) => a.id !== annotation.id,
        );
        redraw();
        renderComments();
      })
      .catch((e) => toast(e.message, "#c0392b"));
  }
  function eraseAt(item, p) {
    const candidates = (item.drafts || [])
      .map((a, i) => ({ a, i, draft: true }))
      .concat((item.annotations || []).map((a, i) => ({ a, i, draft: false })));
    const hit = candidates.reverse().find((entry) => {
      const object = entry.draft ? entry.a.path_data : parsePath(entry.a);
      if (!object) return false;
      const b = objectBounds(object);
      return (
        p.x >= b.minX - 3 &&
        p.x <= b.maxX + 3 &&
        p.y >= b.minY - 3 &&
        p.y <= b.maxY + 3
      );
    });
    if (!hit) return;
    if (hit.draft) {
      item.drafts.splice(hit.i, 1);
      redraw();
      renderComments();
    } else removeAnnotation(item, hit.a);
  }
  function saveCurrent() {
    const item = current(),
      text = byId("rrCommentText").value.trim();
    if (!item) return Promise.resolve();
    if (text) {
      const drawing = (item.drafts || []).find(
        (draft) =>
          draft.draft_id === state.commentTarget && draft.possui_desenho,
      );
      if (drawing) {
        drawing.texto = text;
        state.commentTarget = null;
      } else {
        item.drafts.push({
          texto: text,
          tipo: "ponto",
          possui_desenho: false,
          cor: byId("rrColor").value,
          espessura: Number(byId("rrWidth").value),
        });
      }
      byId("rrCommentText").value = "";
      updateCommentBinding();
    }
    if (!item.reference_id || !item.drafts.length) {
      renderComments();
      return Promise.resolve();
    }
    const pending = item.drafts.splice(0);
    return Promise.all(
      pending.map((a) => {
        const data = new FormData();
        data.append("referencia_id", item.reference_id);
        data.append("texto", a.texto || "");
        data.append("tipo", a.tipo || "freehand");
        data.append("possui_desenho", a.possui_desenho ? "1" : "0");
        data.append("cor", a.cor || "#f59e0b");
        data.append("espessura", a.espessura || 2);
        if (a.path_data) {
          data.append("path_data", JSON.stringify(a.path_data));
          data.append(
            "x",
            a.path_data.start
              ? a.path_data.start.x
              : (a.path_data.points || [{}])[0].x,
          );
          data.append(
            "y",
            a.path_data.start
              ? a.path_data.start.y
              : (a.path_data.points || [{}])[0].y,
          );
        }
        return fetch(annotationsUrl, { method: "POST", body: data }).then((r) =>
          r.json(),
        );
      }),
    )
      .then((responses) => {
        const failed = responses.find((response) => !response.sucesso);
        if (failed) throw new Error(failed.erro || "Falha ao salvar.");
        return loadAnnotations(item);
      })
      .catch((e) => {
        item.drafts = pending.concat(item.drafts || []);
        toast(e.message, "#c0392b");
      });
  }
  function addFiles(files) {
    Array.from(files || []).forEach((file, index) => {
      if (!file.type.startsWith("image/")) return;
      state.items.push({
        key:
          "upload_" +
          (state.items.filter((x) => x.key.indexOf("upload_") === 0).length +
            index),
        nome_original: file.name,
        arquivo: URL.createObjectURL(file),
        origin: "draft",
        file,
        drafts: [],
        annotations: [],
      });
    });
    renderList();
    if (state.items.length) select(state.items[state.items.length - 1].key);
  }
  function removeReference(item) {
    if (item.origin === "draft") {
      URL.revokeObjectURL(item.arquivo);
      state.items = state.items.filter((x) => x !== item);
      select((state.items[0] || {}).key);
      return;
    }
    const data = new FormData();
    data.append("action", "removeReference");
    data.append("reference_id", item.reference_id);
    fetch("ajax.php", { method: "POST", body: data })
      .then((r) => r.json())
      .then((response) => {
        if (response.status !== "sucesso") throw new Error(response.message);
        state.items = state.items.filter((x) => x !== item);
        select((state.items[0] || {}).key);
      })
      .catch((e) => toast(e.message, "#c0392b"));
  }
  function uploadAfterApproval() {
    const drafts = state.items.filter((i) => i.origin === "draft");
    if (!drafts.length) return Promise.resolve();
    const data = new FormData(),
      annotationDrafts = {};
    data.append("action", "addReferenceFiles");
    data.append("render_id", state.renderId);
    drafts.forEach((item, index) => {
      data.append("references[]", item.file);
      annotationDrafts["upload_" + index] = item.drafts || [];
    });
    data.append("reference_review_drafts", JSON.stringify(annotationDrafts));
    return fetch("ajax.php", { method: "POST", body: data })
      .then((r) => r.json())
      .then((response) => {
        if (response.status !== "sucesso") throw new Error(response.message);
        return open(state.renderId, null, true);
      });
  }
  function confirm() {
    const saves = state.items
      .filter((i) => i.reference_id)
      .map((i) => {
        state.current = i.key;
        return saveCurrent();
      });
    Promise.all(saves)
      .then(() => {
        if (state.posId) return uploadAfterApproval();
        const data = new FormData(),
          drafts = {};
        data.append("action", "approveToPos");
        data.append("idrender_alta", state.renderId);
        data.append("refs", "");
        data.append("obs", "");
        if (state.approvalOrigin)
          data.append("approval_origin", state.approvalOrigin);
        state.items
          .filter((i) => i.origin === "draft")
          .forEach((item, index) => {
            data.append("references[]", item.file);
            drafts["upload_" + index] = item.drafts || [];
          });
        const main = state.items.find((i) => i.key === "main");
        drafts.main = main ? main.drafts || [] : [];
        data.append("reference_review_drafts", JSON.stringify(drafts));
        return fetch("ajax.php", { method: "POST", body: data })
          .then((r) => r.json())
          .then((response) => {
            if (response.status === "aprovacao_interna_pendente") {
              fechar();
              if (window.mostrarModalAprovacaoInterna)
                window.mostrarModalAprovacaoInterna();
              return;
            }
            if (response.status !== "sucesso")
              throw new Error(response.message);
            toast("Render enviado para Pós-Produção.");
            fechar();
            if (window.loadRenders) window.loadRenders(1);
            if (window.loadRenderKpis) window.loadRenderKpis();
            $("#myModal").removeClass("is-open");
          });
      })
      .catch((e) =>
        toast(
          e.message || "Não foi possível salvar as referências.",
          "#c0392b",
        ),
      );
  }
  function fechar() {
    byId("renderReferenceReviewModal").classList.remove("is-open");
    byId("renderReferenceReviewModal").setAttribute("aria-hidden", "true");
  }
  function open(renderId, approvalOrigin, editOnly) {
    state.renderId = Number(renderId);
    state.approvalOrigin = approvalOrigin || null;
    state.editOnly = !!editOnly;
    byId("rrConfirm").textContent = editOnly
      ? "Salvar referências"
      : "Confirmar envio para Pós";
    const data = new FormData();
    data.append("action", "getReferenceReview");
    data.append("render_id", state.renderId);
    fetch("ajax.php", { method: "POST", body: data })
      .then((r) => r.json())
      .then((data) => {
        if (data.status !== "sucesso") throw new Error(data.message);
        state.posId = Number(data.pos_producao_id || 0);
        state.items = [
          {
            key: "main",
            reference_id: data.main_reference_id || null,
            arquivo: data.main_preview || "",
            preview: data.main_preview || "",
            nome_original: "Render principal",
            origin: "render_principal",
            drafts: [],
            annotations: [],
          },
        ].concat(
          (data.references || [])
            .filter((r) => r.origem !== "render_principal")
            .map((r) =>
              Object.assign(
                {
                  key: "reference_" + r.id,
                  reference_id: Number(r.id),
                  origin: r.origem || "upload",
                  drafts: [],
                  annotations: [],
                },
                r,
              ),
            ),
        );
        state.current = "main";
        byId("renderReferenceReviewModal").classList.add("is-open");
        byId("renderReferenceReviewModal").setAttribute("aria-hidden", "false");
        renderList();
        select("main");
      })
      .catch((e) => toast(e.message, "#c0392b"));
  }
  function bind() {
    const canvas = byId("rrCanvas"),
      stage = byId("rrStage");
    document.querySelectorAll("[data-rr-tool]").forEach((button) =>
      button.addEventListener("click", () => {
        state.tool = button.dataset.rrTool;
        document
          .querySelectorAll("[data-rr-tool]")
          .forEach((b) => b.classList.toggle("is-active", b === button));
        stage.dataset.tool = state.tool;
      }),
    );
    canvas.addEventListener("pointerdown", (event) => {
      const item = current();
      if (!item) return;
      const p = point(event);
      if (state.tool === "eraser") return eraseAt(item, p);
      if (state.tool === "select") {
        if (state.zoom > 1)
          state.panning = {
            x: event.clientX,
            y: event.clientY,
            pan: Object.assign({}, state.pan),
          };
        return;
      }
      state.drawing =
        state.tool === "pencil"
          ? { tool: "pencil", points: [p] }
          : { tool: state.tool, start: p, end: p };
      canvas.setPointerCapture(event.pointerId);
      redraw();
    });
    canvas.addEventListener("pointermove", (event) => {
      if (state.panning) {
        state.pan.x = state.panning.pan.x + event.clientX - state.panning.x;
        state.pan.y = state.panning.pan.y + event.clientY - state.panning.y;
        updateTransform();
        return;
      }
      if (!state.drawing) return;
      const p = point(event);
      if (state.drawing.tool === "pencil") state.drawing.points.push(p);
      else state.drawing.end = p;
      redraw();
    });
    canvas.addEventListener("pointerup", () => {
      if (state.panning) {
        state.panning = null;
        return;
      }
      createDraftFromDrawing();
    });
    stage.addEventListener(
      "wheel",
      (event) => {
        if (!event.ctrlKey) return;
        event.preventDefault();
        const rect = stage.getBoundingClientRect(),
          before = state.zoom,
          next = Math.max(
            0.4,
            Math.min(3, before + (event.deltaY < 0 ? 0.12 : -0.12)),
          );
        const x = event.clientX - rect.left,
          y = event.clientY - rect.top;
        state.pan.x = x - (x - state.pan.x) * (next / before);
        state.pan.y = y - (y - state.pan.y) * (next / before);
        state.zoom = next;
        updateTransform();
      },
      { passive: false },
    );
    byId("rrFiles").addEventListener("change", function () {
      addFiles(this.files);
      this.value = "";
    });
    byId("rrSaveCurrent").addEventListener("click", saveCurrent);
    byId("rrConfirm").addEventListener("click", confirm);
    byId("rrClose").addEventListener("click", fechar);
    byId("rrCancel").addEventListener("click", fechar);
  }
  document.addEventListener("DOMContentLoaded", bind);
  window.RenderReferenceReview = { open };
})();
