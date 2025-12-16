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

  async function startRedis() {
    try {
      client = createClient({ url: REDIS_URL });
      client.on('error', (err) => console.error('Redis client error', err));
      await client.connect();

      // subscriber
      sub = client.duplicate();
      await sub.connect();

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

      console.log('Connected to Redis and subscribed to upload_progress:*');

      // clear any reconnect timer if successful
      if (reconnectTimer) {
        clearInterval(reconnectTimer);
        reconnectTimer = null;
      }
    } catch (err) {
      console.error('Redis connect failed:', err.message || err);
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
    ws.send(JSON.stringify({ info: 'connected', timestamp: Date.now() }));
    ws.on('message', async (msg) => {
      try {
        const data = JSON.parse(msg.toString());
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

  process.on('SIGINT', async () => {
    console.log('Shutting down...');
    try { await sub.quit(); } catch (e) { }
    try { await client.quit(); } catch (e) { }
    wss.close(() => process.exit(0));
  });
})();
