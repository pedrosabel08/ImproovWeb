// Global upload WebSocket listener.
(function () {
  const STORAGE_KEY = "improov_client_id";
  const STATE_KEY = "improov_upload_state";
  const NOTIFIED_PREFIX = "improov_upload_notified_";
  const BC_NAME = "improov-upload";

  let ws = null;
  let subscribedId = null;
  let reconnectTimer = null;

  function log() {
    if (window.console && console.log) console.log.apply(console, arguments);
  }

  function getClientId() {
    try {
      return localStorage.getItem(STORAGE_KEY);
    } catch (e) {
      return null;
    }
  }

  function markNotified(id) {
    try {
      localStorage.setItem(NOTIFIED_PREFIX + id, String(Date.now()));
    } catch (e) {}
  }

  function wasNotified(id) {
    try {
      return !!localStorage.getItem(NOTIFIED_PREFIX + id);
    } catch (e) {
      return false;
    }
  }

  function getActiveUploadIds() {
    try {
      var state = JSON.parse(localStorage.getItem(STATE_KEY) || "[]");
      var ids = [];
      state.forEach(function (s) {
        var id = s && s.id ? String(s.id) : "";
        if (id && !wasNotified(id) && ids.indexOf(id) === -1) ids.push(id);
      });
      return ids;
    } catch (e) {
      return [];
    }
  }

  function dispatchProgress(payload) {
    try {
      window.dispatchEvent(
        new CustomEvent("improov:uploadProgress", {
          detail: payload,
        }),
      );
    } catch (e) {}
  }

  function dispatchUploadDone(id, message) {
    if (!id) return;
    dispatchProgress({
      id: String(id),
      status: "done",
      progress: 100,
      message: message || "Upload finalizado com sucesso.",
    });
  }

  function sendSubscribe(id) {
    if (!ws || !id || wasNotified(id)) return;
    try {
      ws.send(JSON.stringify({ subscribe: id }));
    } catch (e) {}
  }

  function notify(title, body) {
    try {
      if (window.Notification) {
        if (Notification.permission === "granted") {
          new Notification(title, { body });
          return;
        }
        if (Notification.permission === "default") {
          Notification.requestPermission().then((p) => {
            if (p === "granted") {
              try {
                new Notification(title, { body });
              } catch (e) {
                log("notify new error", e);
              }
            }
          });
          return;
        }
      }
    } catch (e) {
      log("notify check error", e);
    }

    try {
      var t = document.createElement("div");
      t.textContent = title + " - " + body;
      t.style.position = "fixed";
      t.style.right = "16px";
      t.style.bottom = "16px";
      t.style.background = "rgba(0,0,0,0.85)";
      t.style.color = "white";
      t.style.padding = "10px 14px";
      t.style.borderRadius = "8px";
      t.style.zIndex = 99999;
      t.style.fontSize = "13px";
      document.body.appendChild(t);
      setTimeout(function () {
        try {
          t.remove();
        } catch (e) {}
      }, 6000);
    } catch (e) {
      log("notify fallback", e);
    }
  }

  function ensureNotify(payload) {
    var title = "Upload concluído";
    var body = payload.message || "Upload finalizado com sucesso.";
    try {
      if (window.Notification && Notification.permission === "granted") {
        notify(title, body);
        return;
      }
      if (window.Notification && Notification.permission === "default") {
        Notification.requestPermission()
          .then((p) => {
            notify(title, body);
          })
          .catch(function (e) {
            log("requestPermission error", e);
            notify(title, body);
          });
        return;
      }
    } catch (e) {
      log("ensureNotify error", e);
    }
    notify(title, body);
  }

  function connect() {
    const id = getClientId();
    const activeUploadIds = getActiveUploadIds();
    if (!id && activeUploadIds.length === 0) {
      log("upload-ws: no client id in localStorage; not connecting");
      return;
    }
    subscribedId = id || activeUploadIds[0] || null;

    const wsUrl =
      typeof window !== "undefined" && window.IMPROOV_WS_URL
        ? window.IMPROOV_WS_URL
        : location.protocol === "https:"
          ? "wss://" + location.hostname + "/ws/"
          : "ws://" + location.hostname + ":8082";

    try {
      ws = new WebSocket(wsUrl);
    } catch (e) {
      log("upload-ws create socket error", e);
      scheduleReconnect();
      return;
    }

    ws.addEventListener("open", () => {
      var idsToSubscribe = [];
      if (id && !wasNotified(id)) {
        idsToSubscribe.push(String(id));
      } else if (id) {
        try {
          localStorage.removeItem(STORAGE_KEY);
        } catch (e) {}
      }

      getActiveUploadIds().forEach(function (activeId) {
        if (idsToSubscribe.indexOf(activeId) === -1) idsToSubscribe.push(activeId);
      });

      idsToSubscribe.forEach(function (subscribeId) {
        log("upload-ws open", wsUrl, "subscribe", subscribeId);
        sendSubscribe(subscribeId);
      });
    });

    ws.addEventListener("message", (ev) => {
      try {
        const data = JSON.parse(ev.data);

        if (data.channel && data.channel.startsWith("pos_producao:")) {
          window.dispatchEvent(new CustomEvent("improov:posProducaoUpdated"));
          return;
        }

        if (data.channel && data.channel.startsWith("funcao_atualizada:")) {
          window.dispatchEvent(
            new CustomEvent("improov:funcaoAtualizada", {
              detail: data.payload,
            }),
          );
          return;
        }

        const payload = data.payload || data;
        if (!payload || !payload.id) return;
        if (payload.info === "subscribed") return;

        var payloadId = String(payload.id);
        var isTracked = payloadId === String(subscribedId);
        if (!isTracked) {
          try {
            var state = JSON.parse(localStorage.getItem(STATE_KEY) || "[]");
            isTracked = state.some(function (s) {
              return String(s.id) === payloadId;
            });
          } catch (e) {}
        }
        if (!isTracked) return;

        log("upload-ws got", payload);

        const isDone =
          (payload.status &&
            payload.status.toString().toLowerCase() === "done") ||
          Number(payload.progress) >= 100;

        if (isDone) {
          try {
            if (localStorage.getItem(STORAGE_KEY) === payloadId) {
              localStorage.removeItem(STORAGE_KEY);
            }
          } catch (e) {}
        }

        if (isDone && !wasNotified(payloadId)) {
          markNotified(payloadId);
          try {
            bc.postMessage({ type: "notified", id: payloadId });
          } catch (e) {}
          try {
            ensureNotify(payload);
          } catch (e) {
            notify(
              "Upload concluído",
              payload.message || "Upload finalizado com sucesso.",
            );
          }
        }

        dispatchProgress(payload);
      } catch (e) {
        log("upload-ws parse error", e, ev.data);
      }
    });

    ws.addEventListener("close", (ev) => {
      log("upload-ws closed", ev);
      scheduleReconnect();
    });

    ws.addEventListener("error", (err) => {
      log("upload-ws error", err);
      try {
        ws.close();
      } catch (e) {}
    });
  }

  function scheduleReconnect() {
    if (reconnectTimer) return;
    reconnectTimer = setTimeout(() => {
      reconnectTimer = null;
      connect();
    }, 2500);
  }

  const bc =
    typeof BroadcastChannel !== "undefined"
      ? new BroadcastChannel(BC_NAME)
      : null;

  if (bc) {
    bc.onmessage = (ev) => {
      try {
        const d = ev.data;
        if (!d) return;
        if (d.type === "notified" && d.id) {
          markNotified(d.id);
          dispatchUploadDone(d.id);
        }
      } catch (e) {}
    };
  }

  window.addEventListener("storage", (ev) => {
    try {
      if (!ev.key) return;
      if (ev.key === STORAGE_KEY) {
        log("upload-ws storage change client id", ev.newValue);
        if (ws) {
          try {
            ws.close();
          } catch (e) {}
        }
        connect();
      }
      if (ev.key.indexOf(NOTIFIED_PREFIX) === 0) {
        var doneId = ev.key.slice(NOTIFIED_PREFIX.length);
        if (doneId && ev.newValue) dispatchUploadDone(doneId);
      }
    } catch (e) {}
  });

  window.improovUploadWS = {
    subscribe: function (id) {
      var nextId = id ? String(id) : "";
      if (!nextId) return;
      subscribedId = nextId;
      try {
        localStorage.setItem(STORAGE_KEY, nextId);
      } catch (e) {}
      if (ws && ws.readyState === WebSocket.OPEN) {
        sendSubscribe(nextId);
      } else {
        if (ws) {
          try {
            ws.close();
          } catch (e) {}
        }
        connect();
      }
    },
  };

  try {
    if (getClientId() || getActiveUploadIds().length > 0) connect();
  } catch (e) {}
})();
