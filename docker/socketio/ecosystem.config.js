// === PM2 ecosystem config for Socket.IO ===
// docker/socketio/ecosystem.config.js
module.exports = {
  apps: [
    {
      name: 'socketio',
      script: 'socket.io.config.cjs',
      watch: false,
      autorestart: true,
      max_memory_restart: '512M',
      env: {
        NODE_ENV: 'development',
        REDIS_HOST: process.env.REDIS_HOST || 'redis',
        REDIS_PORT: process.env.REDIS_PORT || 6379,
        SOCKET_IO_PORT: process.env.SOCKET_IO_PORT || 6001,
        CORS_ORIGIN: process.env.CORS_ORIGIN || 'http://localhost:3000,http://localhost',
      },
      env_production: {
        NODE_ENV: 'production',
        REDIS_HOST: process.env.REDIS_HOST || 'redis',
        REDIS_PORT: process.env.REDIS_PORT || 6379,
        SOCKET_IO_PORT: process.env.SOCKET_IO_PORT || 6001,
        CORS_ORIGIN: process.env.CORS_ORIGIN || 'https://yourdomain.com',
      },
    },
  ],
};
