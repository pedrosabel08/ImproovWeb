/* Realtime bridge for the authenticated Briefing room. It never confirms saves:
 * HTTP ACK remains the only persistence confirmation. */
window.FlowBriefingWS = (() => {
  let socket = null,
    retries = 0,
    stopped = false,
    ticketProvider = null;
  const url = () =>
    `${location.protocol === "https:" ? "wss" : "ws"}://${location.hostname}${location.protocol === "https:" ? "/ws/" : ":8082"}`;
  const wait = () => Math.min(30000, 1000 * 2 ** Math.min(retries, 5));
  async function connect(provider) {
    ticketProvider = provider || ticketProvider;
    stopped = false;
    if (
      !ticketProvider ||
      socket?.readyState === WebSocket.OPEN ||
      socket?.readyState === WebSocket.CONNECTING
    )
      return;
    let ticket;
    try {
      ticket = await ticketProvider();
    } catch {
      schedule();
      return;
    }
    socket = new WebSocket(url());
    socket.onopen = () => {
      retries = 0;
      socket.send(JSON.stringify({ type: "briefing.subscribe", ticket }));
    };
    socket.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        if (data?.channel?.startsWith("briefing:"))
          window.dispatchEvent(
            new CustomEvent("briefing:realtime", { detail: data.payload }),
          );
      } catch {}
    };
    socket.onclose = () => schedule();
    socket.onerror = () => socket?.close();
  }
  function schedule() {
    if (stopped) return;
    retries++;
    setTimeout(() => connect(), wait());
  }
  function close() {
    stopped = true;
    socket?.close();
    socket = null;
  }
  return { connect, close };
})();
