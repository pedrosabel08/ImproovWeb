// Simple WebSocket server that subscribes to Redis pub/sub for upload progress
// Usage: cd scripts && npm install && npm start

const { createClient } = require('redis');
const WebSocket = require('ws');

const WS_PORT = process.env.WS_PORT ? parseInt(process.env.WS_PORT) : 8082;
const REDIS_URL = process.env.REDIS_URL || 'redis://127.0.0.1:6379';

(async () => {
  const wss = new WebSocket.Server({ port: WS_PORT });
  console.log(`WebSocket server listening on ws://0.0.0.0:${WS_PORT}`);

  // Redis client management with retry
  let client = null;
  let sub = null;
  let reconnectTimer = null;

  function socketCounts() {
    let open = 0;
    wss.clients.forEach((socket) => {
      if (socket.readyState === WebSocket.OPEN) open++;
    });
    return { connected: wss.clients.size, open };
  }

  function broadcastEnvelope(envelope, diagnosticScope, channel, payload) {
    const counts = socketCounts();
    let sent = 0;
    wss.clients.forEach((socket) => {
      if (socket.readyState === WebSocket.OPEN) {
        socket.send(envelope);
        sent++;
      }
    });
    console.log(`[WS-DIAG][Node][${diagnosticScope}] broadcast channel=${channel} event=${payload && payload.event ? payload.event : '(sem event)'} sockets_connected=${counts.connected} sockets_open=${counts.open} sockets_sent=${sent}`);
    return sent;
  }

  function broadcastBriefing(envelope, briefingId, channel, payload) {
    let sent = 0;
    wss.clients.forEach((socket) => {
      if (socket.readyState === WebSocket.OPEN && socket.briefingRooms && socket.briefingRooms.has(String(briefingId))) {
        socket.send(envelope);
        sent++;
      }
    });
    console.log(`[WS-DIAG][Node][Briefing] room=briefing:${briefingId} channel=${channel} event=${payload && payload.event ? payload.event : '(sem event)'} sent=${sent}`);
  }

  function disconnectBriefingRoom(briefingId, reason) {
    wss.clients.forEach((socket) => {
      if (socket.readyState === WebSocket.OPEN && socket.briefingRooms && socket.briefingRooms.has(String(briefingId))) {
        socket.close(4003, reason);
      }
    });
  }

  async function subscribeBriefing(ws, ticket) {
    if (!client || !client.isOpen || typeof ticket !== 'string' || !/^[a-f0-9]{64}$/i.test(ticket)) {
      ws.send(JSON.stringify({ error: 'briefing_subscription_denied' }));
      return;
    }
    try {
      const key = `briefing_ws_ticket:${require('crypto').createHash('sha256').update(ticket).digest('hex')}`;
      const raw = await client.get(key);
      if (raw) await client.del(key); // one ticket authorizes one socket only
      const claims = raw ? JSON.parse(raw) : null;
      if (!claims || !claims.briefing_id || !claims.participant_id || Number(claims.exp) < Math.floor(Date.now() / 1000)) {
        ws.send(JSON.stringify({ error: 'briefing_subscription_denied' }));
        return;
      }
      ws.briefingRooms.add(String(claims.briefing_id));
      ws.briefingParticipant = { id: Number(claims.participant_id), scope: claims.scope || 'CLIENTE' };
      ws.send(JSON.stringify({ info: 'briefing_subscribed', briefing_id: Number(claims.briefing_id) }));
    } catch (err) {
      console.error('Failed to subscribe briefing room', err);
      ws.send(JSON.stringify({ error: 'briefing_subscription_denied' }));
    }
  }

  async function startRedis() {
    try {
      console.log(`[WS-DIAG][Node] redis.connect.start REDIS_URL=${REDIS_URL}`);
      client = createClient({ url: REDIS_URL });
      client.on('error', (err) => console.error('Redis client error', err));
      await client.connect();

      // subscriber
      sub = client.duplicate();
      await sub.connect();
      console.log(`[WS-DIAG][Node] redis.connected REDIS_URL=${REDIS_URL}`);

      // psubscribe to upload_progress:* channels
      await sub.pSubscribe('upload_progress:*', (message, channel) => {
        try {
          const payload = JSON.parse(message);
          const envelope = JSON.stringify({ channel, payload });
          wss.clients.forEach((socket) => {
            if (socket.readyState === WebSocket.OPEN) {
              socket.send(envelope);
            }
          });
        } catch (err) {
          console.error('Failed to forward message', err);
        }
      });

      // psubscribe to pos_producao:* channels (table update broadcasts)
      await sub.pSubscribe('pos_producao:*', (message, channel) => {
        try {
          const payload = JSON.parse(message);
          const envelope = JSON.stringify({ channel, payload });
          const counts = socketCounts();
          console.log(`[WS-DIAG][Node][Pós] redis.received channel=${channel} event=${payload && payload.event ? payload.event : '(sem event)'} payload=${message} sockets_connected=${counts.connected} sockets_open=${counts.open}`);
          broadcastEnvelope(envelope, 'Pós', channel, payload);
        } catch (err) {
          console.error('Failed to forward pos_producao message', err);
        }
      });

      await sub.pSubscribe('render:*', (message, channel) => {
        try {
          const payload = JSON.parse(message);
          const envelope = JSON.stringify({ channel, payload });
          const counts = socketCounts();
          console.log(`[WS-DIAG][Node][Render] redis.received channel=${channel} event=${payload && payload.event ? payload.event : '(sem event)'} payload=${message} sockets_connected=${counts.connected} sockets_open=${counts.open}`);
          broadcastEnvelope(envelope, 'Render', channel, payload);
        } catch (err) {
          console.error('Failed to forward render message', err);
        }
      });

      await sub.pSubscribe('flow_review:*', (message, channel) => {
        try {
          const payload = JSON.parse(message);
          const envelope = JSON.stringify({ channel, payload });
          broadcastEnvelope(envelope, 'FlowReview', channel, payload);
        } catch (err) {
          console.error('Failed to forward flow_review message', err);
        }
      });

      await sub.pSubscribe('fotografico:*', (message, channel) => {
        try {
          const payload = JSON.parse(message);
          broadcastEnvelope(JSON.stringify({ channel, payload }), 'Fotografico', channel, payload);
        } catch (err) {
          console.error('Failed to forward fotografico message', err);
        }
      });

      // Flow Block issue lifecycle updates refresh the central list and the
      // affected task card without coupling them to image-level HOLDs.
      await sub.pSubscribe('flow_block:*', (message, channel) => {
        try {
          const payload = JSON.parse(message);
          broadcastEnvelope(JSON.stringify({ channel, payload }), 'FlowBlock', channel, payload);
        } catch (err) {
          console.error('Failed to forward Flow Block message', err);
        }
      });

      // Briefings are the first external-facing realtime feature. Unlike the
      // legacy module channels, they are sent only to a short-lived,
      // server-authorized room. The payload contains no answer content.
      await sub.pSubscribe('briefing:*', (message, channel) => {
        try {
          const payload = JSON.parse(message);
          const briefingId = String(payload.briefing_id || channel.split(':')[1] || '');
          if (!/^\d+$/.test(briefingId)) return;
          broadcastBriefing(JSON.stringify({ channel, payload }), briefingId, channel, payload);
          if (payload.event === 'briefing.access.revoked') {
            disconnectBriefingRoom(briefingId, 'briefing_access_revoked');
          }
        } catch (err) {
          console.error('Failed to forward Briefing message', err);
        }
      });

      // psubscribe to funcao_atualizada:* channels (function insert/update broadcasts)
      await sub.pSubscribe('funcao_atualizada:*', (message, channel) => {
        console.log('funcao_atualizada message received on channel:', channel);
        try {
          const payload = JSON.parse(message);
          const envelope = JSON.stringify({ channel, payload });
          let sent = 0;
          wss.clients.forEach((socket) => {
            if (socket.readyState === WebSocket.OPEN) {
              socket.send(envelope);
              sent++;
            }
          });
          console.log(`funcao_atualizada broadcast sent to ${sent} client(s)`);
        } catch (err) {
          console.error('Failed to forward funcao_atualizada message', err);
        }
      });

      console.log(`[WS-DIAG][Node] redis.subscribed REDIS_URL=${REDIS_URL} patterns=upload_progress:*,pos_producao:*,render:*,flow_review:*,fotografico:*,flow_block:*,briefing:*,funcao_atualizada:*`);

      // clear any reconnect timer if successful
      if (reconnectTimer) {
        clearInterval(reconnectTimer);
        reconnectTimer = null;
      }
    } catch (err) {
      console.error('[WS-DIAG][Node] redis.connect.error REDIS_URL=' + REDIS_URL, err && err.stack ? err.stack : (err.message || err));
      // schedule reconnect attempts
      if (!reconnectTimer) {
        reconnectTimer = setInterval(() => {
          console.log('Attempting to reconnect to Redis...');
          startRedis().catch(() => { });
        }, 5000);
      }
    }
  }

  // Try to start Redis subscriber but do not block WS server if Redis is unavailable
  startRedis();

  wss.on('connection', (ws) => {
    ws.isAlive = true;
    ws.briefingRooms = new Set();
    ws.on('pong', () => { ws.isAlive = true; });
    const counts = socketCounts();
    console.log(`[WS-DIAG][Node] socket.connected sockets_connected=${counts.connected} sockets_open=${counts.open}`);
    ws.send(JSON.stringify({ info: 'connected', timestamp: Date.now() }));
    ws.on('message', async (msg) => {
      try {
        const data = JSON.parse(msg.toString());
        if (data && data.type === 'briefing.subscribe') {
          await subscribeBriefing(ws, data.ticket);
          return;
        }
        if (data && data.subscribe) {
          const id = String(data.subscribe);
          // try to read latest snapshot from Redis and send to this socket
          if (client && client.isOpen) {
            try {
              const key = `upload_status:${id}`;
              const val = await client.get(key);
              if (val) {
                // forward as envelope similar to pubsub
                ws.send(JSON.stringify({ channel: `upload_status:${id}`, payload: JSON.parse(val) }));
              }
            } catch (err) {
              console.error('Failed to read upload_status from Redis', err);
            }
          }
          // send ack
          ws.send(JSON.stringify({ info: 'subscribed', id }));
        }
      } catch (err) {
        // ignore parse errors
      }
    });
  });

  const heartbeat = setInterval(() => {
    wss.clients.forEach((ws) => {
      if (ws.isAlive === false) return ws.terminate();
      ws.isAlive = false;
      ws.ping();
    });
  }, 30000);
  wss.on('close', () => clearInterval(heartbeat));

  process.on('SIGINT', async () => {
    console.log('Shutting down...');
    try { await sub.quit(); } catch (e) { }
    try { await client.quit(); } catch (e) { }
    wss.close(() => process.exit(0));
  });
})();
