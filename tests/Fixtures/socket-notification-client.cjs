const { io } = require('../../socketio/node_modules/socket.io-client');

async function main() {
  let input = '';
  for await (const chunk of process.stdin) input += chunk;
  const { origin, token } = JSON.parse(input);
  let ready = false;
  for (let attempt = 0; attempt < 100; attempt++) {
    try {
      if ((await fetch(`${origin}/health`)).ok) { ready = true; break; }
    } catch {}
    await new Promise(resolve => setTimeout(resolve, 50));
  }
  if (!ready) throw new Error('Socket server did not become healthy');

  const socket = io(origin, {
    transports: ['websocket'], reconnection: false, auth: { token }, timeout: 3000,
  });
  const received = new Set();
  const timeout = setTimeout(() => {
    console.error('Laravel notification delivery timed out');
    socket.close();
    process.exitCode = 1;
  }, 10000);
  socket.on('connect', () => console.log(JSON.stringify({ ready: true })));
  socket.on('connect_error', error => {
    clearTimeout(timeout);
    console.error(error.message);
    socket.close();
    process.exitCode = 1;
  });
  for (const event of ['notification.created', 'notification.state_changed']) {
    socket.on(event, data => {
      console.log(JSON.stringify({ event, data }));
      received.add(event);
      if (received.size === 2) {
        clearTimeout(timeout);
        socket.close();
      }
    });
  }
}

main().catch(error => { console.error(error.message); process.exitCode = 1; });
