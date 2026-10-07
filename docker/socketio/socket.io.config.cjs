const express = require('express');
const { createServer } = require('node:http');
const { Server } = require('socket.io');
const { createClient } = require('redis');
const { createAdapter } = require('@socket.io/redis-adapter');
const { verifyToken } = require('./auth.cjs');

const secret = process.env.SOCKET_TOKEN_SECRET || '';
if (Buffer.byteLength(secret) < 32) throw new Error('SOCKET_TOKEN_SECRET must contain at least 32 bytes');
const prefix = process.env.REDIS_PREFIX || 'metrial_';
const clients = Array.from({ length: 3 }, () => createClient({
  socket: { host: process.env.REDIS_HOST || 'redis', port: Number(process.env.REDIS_PORT || 6379) },
  password: process.env.REDIS_PASSWORD || undefined,
  database: Number(process.env.REDIS_DB || 0),
  disableOfflineQueue: true,
}));
const [pub, sub, relay] = clients;
clients.forEach(client => client.on('error', () => console.error('REDIS_UNAVAILABLE')));
const app = express();
const server = createServer(app);
const io = new Server(server, {
  cors: { origin: (process.env.CORS_ORIGIN || 'http://localhost').split(',').map(x => x.trim()), methods: ['GET', 'POST'] },
  maxHttpBufferSize: 16384,
  transports: ['websocket'],
});
io.use((socket, next) => {
  try {
    if (!clients.every(client => client.isReady)) throw new Error('UNAVAILABLE');
    socket.data.identity = verifyToken(socket.handshake.auth?.token, secret);
    next();
  } catch { next(new Error('UNAUTHENTICATED')); }
});
io.on('connection', socket => {
  const identity = socket.data.identity;
  socket.join(identity.room);
  const expiry = setTimeout(() => socket.disconnect(true), Math.max(0, identity.exp * 1000 - Date.now()));
  socket.on('join:room', (room, reply) => {
    const allowed = room === identity.room && identity.exp > Math.floor(Date.now() / 1000);
    if (allowed) socket.join(identity.room);
    if (typeof reply === 'function') reply({ success: allowed, code: allowed ? 'OK' : 'FORBIDDEN' });
  });
  socket.on('disconnect', () => clearTimeout(expiry));
});
app.get('/health', (req, res) => {
  const ready = clients.every(client => client.isReady);
  res.status(ready ? 200 : 503).json({ status: ready ? 'ok' : 'unavailable' });
});
async function main() {
  await Promise.all(clients.map(client => client.connect()));
  io.adapter(createAdapter(pub, sub));
  await relay.pSubscribe(`${prefix}private-user.*`, (message, channel) => {
    const room = channel.slice(prefix.length);
    if (!/^private-user\.[1-9][0-9]*$/.test(room)) return;
    try {
      const envelope = JSON.parse(message);
      if (envelope.event !== 'notification.created' || typeof envelope.data !== 'object') return;
      // Each node receives Redis broadcasts; local emission avoids N-fold fan-out.
      io.local.to(room).emit(envelope.event, envelope.data);
    } catch { console.error('INVALID_BROADCAST'); }
  });
  server.listen(Number(process.env.SOCKET_IO_PORT || 6001));
}
for (const signal of ['SIGTERM', 'SIGINT']) process.on(signal, async () => {
  io.disconnectSockets(true);
  io.close();
  await Promise.allSettled(clients.map(client => client.quit()));
  process.exit(0);
});
main().catch(() => { console.error('SOCKET_STARTUP_FAILED'); process.exit(1); });
