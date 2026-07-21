// Persistent realtime toasts for Flow Block mentions.
// Detail data is fetched through the authenticated API; WebSocket broadcasts
// carry only identifiers and are ignored unless addressed to this collaborator.
(() => {
  if (window.__flowBlockMentionToastsStarted) return;
  window.__flowBlockMentionToastsStarted = true;

  const currentUserId = Number(window.IMPROOV_COLABORADOR_ID || 0);
  if (!currentUserId) return;

  const seenStorageKey = "improov_flow_block_mention_toasts_seen";
  const seen = new Set();
  const resolving = new Set();
  const overflow = new Map();
  const maxIndividualToasts = 2;
  let channel = null;

  try {
    JSON.parse(sessionStorage.getItem(seenStorageKey) || "[]").forEach((id) =>
      seen.add(String(id)),
    );
  } catch (error) {}

  try {
    channel = new BroadcastChannel("improov-flow-block-mentions");
    channel.onmessage = (event) => {
      if (event.data?.type === "seen" && event.data.id) {
        seen.add(String(event.data.id));
      }
    };
  } catch (error) {
    channel = null;
  }

  const base = String(window.IMPROOV_APP_BASE || "/ImproovWeb").replace(
    /\/$/,
    "",
  );
  const apiUrl = `${base}/FlowBlock/api.php`;
  const issueUrl = (mention) =>
    `${base}/FlowBlock/issue.php?id=${encodeURIComponent(mention.issue_id)}&mention_id=${encodeURIComponent(mention.mention_id)}`;
  const escapeHtml = (value) =>
    String(value ?? "").replace(
      /[&<>'"]/g,
      (char) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          "'": "&#39;",
          '"': "&quot;",
        })[char],
    );
  const commentSnippet = (value) => {
    const text = String(value || "")
      .trim()
      .replace(/\s+/g, " ");
    return text.length > 160 ? `${text.slice(0, 157)}…` : text;
  };
  const markSeen = (id) => {
    const normalized = String(id);
    seen.add(normalized);
    try {
      sessionStorage.setItem(
        seenStorageKey,
        JSON.stringify([...seen].slice(-300)),
      );
    } catch (error) {}
    try {
      channel?.postMessage({ type: "seen", id: normalized });
    } catch (error) {}
  };
  const host = () => {
    let element = document.getElementById("flow-block-mention-toasts");
    if (!element) {
      element = document.createElement("section");
      element.id = "flow-block-mention-toasts";
      element.className = "fb-mention-toasts";
      element.setAttribute("aria-live", "polite");
      element.setAttribute("aria-label", "Novas menções do Flow Block");
      document.body.appendChild(element);
    }
    return element;
  };
  const individualCount = () =>
    host().querySelectorAll(".fb-mention-toast[data-mention-id]").length;

  const renderOverflow = () => {
    const container = host();
    let group = container.querySelector(".fb-mention-toast--group");
    if (!overflow.size) {
      group?.remove();
      return;
    }
    if (!group) {
      group = document.createElement("article");
      group.className = "fb-mention-toast fb-mention-toast--group";
      container.appendChild(group);
    }
    const count = overflow.size;
    group.innerHTML = `<div class="fb-mention-toast__icon"><i class="ri-at-line"></i></div><div class="fb-mention-toast__content"><strong>${count} ${count === 1 ? "nova menção" : "novas menções"}</strong><span>Há mais menções aguardando sua atenção.</span><div class="fb-mention-toast__actions"><button type="button" class="fb-mention-toast__open">Ver menções</button></div></div><button type="button" class="fb-mention-toast__close" aria-label="Fechar aviso"><i class="ri-close-line"></i></button>`;
    group
      .querySelector(".fb-mention-toast__open")
      .addEventListener("click", () => {
        window.location.href = `${base}/FlowBlock/index.php?mentioned=1`;
      });
    group
      .querySelector(".fb-mention-toast__close")
      .addEventListener("click", () => {
        overflow.clear();
        group.remove();
      });
  };

  const showToast = (mention) => {
    const container = host();
    const mentionId = String(mention.mention_id);
    if (container.querySelector(`[data-mention-id="${mentionId}"]`)) return;

    if (individualCount() >= maxIndividualToasts) {
      overflow.set(mentionId, mention);
      renderOverflow();
      return;
    }

    const toast = document.createElement("article");
    toast.className = "fb-mention-toast";
    toast.dataset.mentionId = mentionId;
    const task = [mention.imagem_nome, mention.nome_funcao]
      .filter(Boolean)
      .join(" · ");
    const snippet = commentSnippet(mention.conteudo);
    toast.innerHTML = `<div class="fb-mention-toast__icon"><i class="ri-at-line"></i></div><div class="fb-mention-toast__content"><strong>${escapeHtml(mention.autor_nome || "Alguém")} mencionou você</strong><span class="fb-mention-toast__meta">${escapeHtml(mention.codigo || "Issue")} · ${escapeHtml(task || "Tarefa")}</span>${snippet ? `<p>${escapeHtml(snippet)}</p>` : ""}<div class="fb-mention-toast__actions"><button type="button" class="fb-mention-toast__open">Abrir Issue</button></div></div><button type="button" class="fb-mention-toast__close" aria-label="Fechar aviso"><i class="ri-close-line"></i></button>`;
    toast
      .querySelector(".fb-mention-toast__open")
      .addEventListener("click", () => {
        window.location.href = issueUrl(mention);
      });
    toast
      .querySelector(".fb-mention-toast__close")
      .addEventListener("click", () => toast.remove());
    container.prepend(toast);
  };

  const receive = async (payload) => {
    if (!payload || payload.event !== "flow_block.mention.created") return;
    if (Number(payload.recipient_id) !== currentUserId || !payload.mention_id)
      return;
    const mentionId = String(payload.mention_id);
    if (seen.has(mentionId) || resolving.has(mentionId)) return;
    resolving.add(mentionId);
    try {
      const response = await fetch(
        `${apiUrl}?action=mention_pending&id=${encodeURIComponent(mentionId)}`,
        {
          credentials: "same-origin",
          cache: "no-store",
        },
      );
      const data = await response.json();
      if (!response.ok || !data?.ok || !data.mention) return;
      markSeen(mentionId);
      showToast(data.mention);
    } catch (error) {
      console.error("Flow Block mention toast:", error);
    } finally {
      resolving.delete(mentionId);
    }
  };

  const recoverPending = async () => {
    try {
      const response = await fetch(`${apiUrl}?action=mentions_pending`, {
        credentials: "same-origin",
        cache: "no-store",
      });
      const data = await response.json();
      if (!response.ok || !data?.ok || !Array.isArray(data.mentions)) return;
      data.mentions
        .slice()
        .reverse()
        .forEach((mention) => {
          const mentionId = String(mention.mention_id || "");
          if (!mentionId || seen.has(mentionId)) return;
          markSeen(mentionId);
          showToast(mention);
        });
    } catch (error) {
      console.error("Flow Block pending mentions:", error);
    }
  };

  window.addEventListener("improov:flowBlockMention", (event) =>
    receive(event.detail),
  );
  window.improovFlowBlockMentions = { receive };
  window.improovUploadWS?.connect?.();
  recoverPending();
})();
