// PM2 is optional; cPanel Passenger uses app.js instead.
module.exports = {
  apps: [{
    name: 'socketio',
    cwd: __dirname,
    script: 'app.js',
    watch: false,
    autorestart: true,
    max_memory_restart: '512M',
    env: { NODE_ENV: 'development' },
    env_production: { NODE_ENV: 'production' },
  }],
};
