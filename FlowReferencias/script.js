const modal = document.getElementById("uploadModal");
const btnOpen = document.getElementById("btnUpload");
const btnClose = document.getElementById("closeModal");

const filterAxis = document.getElementById("filter_axis");
const filterCategory = document.getElementById("filter_category");
const filterSubcategory = document.getElementById("filter_subcategory");
const filterExt = document.getElementById("filter_ext");

const axisSelect = document.getElementById("axisSelect");
const categorySelect = document.getElementById("categorySelect");
const subcategorySelect = document.getElementById("subcategorySelect");
const tipoHint = document.getElementById("tipoHint");
const arquivosInput = document.getElementById("arquivosInput");

btnOpen.addEventListener("click", () => (modal.style.display = "flex"));
btnClose.addEventListener("click", () => (modal.style.display = "none"));

let TAX = null;

function resetSelect(selectEl, placeholder) {
  selectEl.innerHTML = "";
  const opt = document.createElement("option");
  opt.value = "";
  opt.textContent = placeholder;
  selectEl.appendChild(opt);
}

function fillSelect(selectEl, items, placeholder) {
  resetSelect(selectEl, placeholder);
  (items || []).forEach((i) => {
    const opt = document.createElement("option");
    opt.value = String(i.id);
    opt.textContent = i.nome;
    selectEl.appendChild(opt);
  });
}

function uniqueExtsFromTax(tax) {
  const exts = new Set();
  tax?.axes?.forEach((ax) =>
    ax.categories?.forEach((cat) =>
      cat.subcategories?.forEach((sc) => {
        (sc.allowed_exts || []).forEach((e) => exts.add(e));
      }),
    ),
  );
  return Array.from(exts).sort();
}

async function loadTaxonomia() {
  const res = await fetch("getTaxonomia.php");
  TAX = await res.json();

  // filters
  fillSelect(filterAxis, TAX.axes, "Todos os eixos");
  resetSelect(filterCategory, "Todas as categorias");
  resetSelect(filterSubcategory, "Todas as subcategorias");

  // modal
  fillSelect(axisSelect, TAX.axes, "-- Selecione --");
  resetSelect(categorySelect, "-- Selecione --");
  resetSelect(subcategorySelect, "-- Selecione --");

  // ext filter
  resetSelect(filterExt, "Todos os tipos");
  uniqueExtsFromTax(TAX).forEach((ext) => {
    const opt = document.createElement("option");
    opt.value = ext;
    opt.textContent = ext.toUpperCase();
    filterExt.appendChild(opt);
  });
}

function getAxisById(id) {
  return TAX?.axes?.find((a) => String(a.id) === String(id)) || null;
}

function getCategoryById(axis, id) {
  return axis?.categories?.find((c) => String(c.id) === String(id)) || null;
}

function getSubcategoryById(category, id) {
  return (
    category?.subcategories?.find((s) => String(s.id) === String(id)) || null
  );
}

function updateFilterCategories() {
  const axis = getAxisById(filterAxis.value);
  fillSelect(
    filterCategory,
    axis ? axis.categories : [],
    "Todas as categorias",
  );
  resetSelect(filterSubcategory, "Todas as subcategorias");
}

function updateFilterSubcategories() {
  const axis = getAxisById(filterAxis.value);
  const cat = getCategoryById(axis, filterCategory.value);
  fillSelect(
    filterSubcategory,
    cat ? cat.subcategories : [],
    "Todas as subcategorias",
  );
}

function updateModalCategories() {
  const axis = getAxisById(axisSelect.value);
  fillSelect(categorySelect, axis ? axis.categories : [], "-- Selecione --");
  resetSelect(subcategorySelect, "-- Selecione --");
  tipoHint.style.display = "none";
  arquivosInput.accept = "";
}

function updateModalSubcategories() {
  const axis = getAxisById(axisSelect.value);
  const cat = getCategoryById(axis, categorySelect.value);
  fillSelect(
    subcategorySelect,
    cat ? cat.subcategories : [],
    "-- Selecione --",
  );
  tipoHint.style.display = "none";
  arquivosInput.accept = "";
}

function updateModalHintAndAccept() {
  const axis = getAxisById(axisSelect.value);
  const cat = getCategoryById(axis, categorySelect.value);
  const sub = getSubcategoryById(cat, subcategorySelect.value);

  if (!sub) {
    tipoHint.style.display = "none";
    arquivosInput.accept = "";
    return;
  }

  const allowed = (sub.allowed_exts || []).map((e) => "." + e);
  arquivosInput.accept = allowed.join(",");
  tipoHint.style.display = "block";
  tipoHint.textContent = `Tipo esperado: ${sub.tipo_label} | Permitidos: ${(sub.allowed_exts || []).join(", ")}`;
}

async function carregarUploads() {
  const params = new URLSearchParams();
  if (filterAxis.value) params.set("axis_id", filterAxis.value);
  if (filterCategory.value) params.set("category_id", filterCategory.value);
  if (filterSubcategory.value)
    params.set("subcategory_id", filterSubcategory.value);
  if (filterExt.value) params.set("ext", filterExt.value);

  const res = await fetch("getUploads.php?" + params.toString());
  const dados = await res.json();

  const tbody = document.querySelector(".tabelaRefs tbody");
  tbody.innerHTML = "";

  dados.forEach((item) => {
    const tr = document.createElement("tr");
    const href = item.id ? "view.php?id=" + encodeURIComponent(item.id) : "#";
    const name = item.original_name || item.stored_name;

    const uploadedAt = item.uploaded_at
      ? (() => {
          const d = new Date(item.uploaded_at);
          if (isNaN(d.getTime())) return "";
          return d.toLocaleDateString() + " " + d.toLocaleTimeString();
        })()
      : "";

    tr.innerHTML = `
      <td><a class="file-link" href="${href}" target="_blank" rel="noopener">${escapeHtml(name)}</a></td>
      <td>${escapeHtml(item.axis_nome || "")}</td>
      <td>${escapeHtml(item.category_nome || "")}</td>
      <td>${escapeHtml(item.subcategory_nome || "")}</td>
      <td>${escapeHtml((item.ext || "").toUpperCase())}</td>
      <td>${escapeHtml(uploadedAt)}</td>
            <td><button class="btn-delete" data-id="${escapeHtml(String(item.id || ""))}">Excluir</button></td>
    `;

    tbody.appendChild(tr);
  });
}

function escapeHtml(str) {
  return String(str || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

// wiring filters
filterExt.addEventListener("change", carregarUploads);
filterAxis.addEventListener("change", () => {
  updateFilterCategories();
  carregarUploads();
});
filterCategory.addEventListener("change", () => {
  updateFilterSubcategories();
  carregarUploads();
});
filterSubcategory.addEventListener("change", carregarUploads);

document
  .querySelector(".tabelaRefs tbody")
  .addEventListener("click", async (e) => {
    const btn = e.target.closest(".btn-delete");
    if (!btn) return;

    const id = btn.getAttribute("data-id");
    if (!id) return;

    const row = btn.closest("tr");
    const name = row ? row.querySelector(".file-link")?.textContent : "";

    const confirm = await Swal.fire({
      title: "Excluir arquivo?",
      text: name
        ? `Isso irá apagar "${name}" do NAS e do banco.`
        : "Isso irá apagar o arquivo do NAS e do banco.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Excluir",
      cancelButtonText: "Cancelar",
    });

    if (!confirm.isConfirmed) return;

    try {
      const body = new URLSearchParams();
      body.set("id", id);
      const res = await fetch("delete.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
      });
      const data = await res.json();
      if (data.ok) {
        Toastify({
          text: "Arquivo excluído.",
          duration: 3000,
          close: true,
          gravity: "top",
          position: "right",
          backgroundColor: "green",
        }).showToast();
        await carregarUploads();
      } else {
        (data.errors || ["Falha ao excluir."]).forEach((msg) =>
          Toastify({
            text: msg,
            duration: 5000,
            close: true,
            gravity: "top",
            position: "right",
            backgroundColor: "red",
          }).showToast(),
        );
      }
    } catch (err) {
      Toastify({
        text: "Falha ao excluir.",
        duration: 5000,
        close: true,
        gravity: "top",
        position: "right",
        backgroundColor: "red",
      }).showToast();
    }
  });

// modal dependent selects
axisSelect.addEventListener("change", updateModalCategories);
categorySelect.addEventListener("change", updateModalSubcategories);
subcategorySelect.addEventListener("change", updateModalHintAndAccept);

// submit upload

document.getElementById("uploadForm").addEventListener("submit", async (e) => {
  e.preventDefault();

  const form = e.target;
  const formData = new FormData(form);

  // remove empty files
  for (const [k, v] of formData.entries()) {
    if (v instanceof File && v.size === 0) formData.delete(k);
  }

  const xhr = new XMLHttpRequest();
  xhr.open("POST", "upload.php", true);

  let totalBytes = 0;
  for (const [k, v] of formData.entries()) {
    if (v instanceof File) totalBytes += v.size;
  }

  let cancelRequested = false;
  const uploadProgress = FlowAlert.progress({
    title: "Enviando arquivos",
    message: `0% · 0 KB de ${Math.round(totalBytes / 1024)} KB`,
    progress: 0,
    dismissible: false,
    cancelAction: {
      label: "Cancelar",
      onClick: () => {
        cancelRequested = true;
        xhr.abort();
      },
    },
  });
  xhr.upload.onprogress = function (evt) {
    if (!evt.lengthComputable) return;
    const pct = (evt.loaded / evt.total) * 100;
    uploadProgress.update({
      progress: pct,
      message: `${pct.toFixed(2)}% · ${Math.round(evt.loaded / 1024)} KB de ${Math.round(evt.total / 1024)} KB`,
    });
  };

  const uploadPromise = new Promise((resolve, reject) => {
    xhr.onload = () => {
      try {
        resolve(JSON.parse(xhr.responseText || "{}"));
      } catch {
        reject(new Error("Resposta inválida do servidor"));
      }
    };
    xhr.onerror = () => reject(new Error("Erro na requisição"));
    xhr.onabort = () => reject(new Error("Envio cancelado"));
  });

  xhr.send(formData);

  const race = await Promise.race([
    uploadPromise
      .then((res) => ({ type: "upload", res }))
      .catch((err) => ({ type: "upload_error", err })),
    uploadProgress.result.then((res) => ({ type: "alert", res })),
  ]);

  if (race.type === "alert" && cancelRequested) {
    try {
      xhr.abort();
    } catch {}
    Toastify({
      text: "Envio cancelado.",
      duration: 3000,
      close: true,
      gravity: "top",
      position: "right",
      backgroundColor: "red",
    }).showToast();
    return;
  }

  uploadProgress.close();

  if (race.type === "upload_error") {
    Toastify({
      text: race.err.message || "Erro ao enviar.",
      duration: 5000,
      close: true,
      gravity: "top",
      position: "right",
      backgroundColor: "red",
    }).showToast();
    return;
  }

  const result = race.res;
  if (result.success && result.success.length) {
    result.success.forEach((msg) =>
      Toastify({
        text: msg,
        duration: 3000,
        close: true,
        gravity: "top",
        position: "right",
        backgroundColor: "green",
      }).showToast(),
    );
    form.reset();
    tipoHint.style.display = "none";
    modal.style.display = "none";
    await carregarUploads();
  }
  if (result.errors && result.errors.length) {
    result.errors.forEach((msg) =>
      Toastify({
        text: msg,
        duration: 5000,
        close: true,
        gravity: "top",
        position: "right",
        backgroundColor: "red",
      }).showToast(),
    );
  }
});

// init
(async function init() {
  try {
    await loadTaxonomia();
    await carregarUploads();
  } catch (e) {
    console.error(e);
    Toastify({
      text: "Falha ao carregar dados.",
      duration: 5000,
      close: true,
      gravity: "top",
      position: "right",
      backgroundColor: "red",
    }).showToast();
  }
})();
